<?php
/**
 * Export Product
 *
 * filename.csv             Exported CSV
 * filename.csv.processing  Partly exported CSV (currently under process)
 * filename.csv.last        Last exported ProductId
 * filename.csv.locked      Lock to block parallel processes. File content is datetime of start.
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr_GoogleBase
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2015 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Kirchbergerknorr_GoogleBase_Model_Export_Abstract extends Mage_CatalogSearch_Model_Mysql4_Fulltext
{
    protected $_csvFileName;
    protected $_lastProductId;
    protected $_exportedCount;
    protected $_foundCount;
    protected $_totalCount;
    protected $_timeStarted;
    protected $_deliveryBlock;
    protected $_exportAttributeCodes = null;
    protected $_categoryNames = null;
    protected $_productsToCategoryPath = null;
    protected $_exportAttributes = null;
    protected $_storeId = null;
    protected $_productCollection = null;

    abstract protected function _writeItem($data);
    abstract protected function _writeHeader($data);

    public function log($message)
    {
        Mage::getModel('kk_google_base/observer')->log($message);
    }

    public function logException($e)
    {
        Mage::getModel('kk_google_base/observer')->logException($e);
    }

    /**
     * Init resource model
     *
     */
    public function __construct()
    {
        $csvFileName = Mage::getStoreConfig('kk_googlebase/general/export_path');

        if (!$csvFileName) {
            throw new Exception("Export file name is not set");
        }

        $this->_csvFileName = Mage::getBaseDir() . $csvFileName;
    }

    /**
     * Get Export Attributes
     *
     * @param int $storeId
     * @return array
     */
    protected function _getExportAttributes($storeId = null)
    {
        if($this->_exportAttributeCodes === null){
            // todo: move it to configuration
            $headerDefault = array(
                'sku',
                'name',
                'short_description',
                'description',
                'category',
                'category_url',
                'manufacturer',
                'ean',
                'size',
                'color',
                'price',
                'special_price',
                'image_small',
                'image_big',
                'deeplink',
                'delivery_time',
                'shipping_costs_de',
                'shipping_costs_at',
                'shipping_costs_ch',
            );
            $this->_imageHelper = Mage::helper('catalog/image');
            $this->_exportAttributeCodes = $headerDefault;
        }
        return $this->_exportAttributeCodes;
    }

    public function setState($state)
    {
        switch ($state)
        {
            case('started'):
                $this->_totalCount = $this->getProductCollection()->getSize();
                $this->_timeStarted = date('Y-m-d H:i:s');

                $this->log("Export started for {$this->_totalCount}");

                if (file_exists($this->_csvFileName.".last")) {
                    unlink($this->_csvFileName.".last");
                }

                $this->log("Filename: {$this->_csvFileName}.processing");

                file_put_contents($this->_csvFileName.".processing", '');
                break;

            case('continue'):

                if (file_exists($this->_csvFileName.".processing")) {
                    $this->startNewThread(false);
                } else {
                    $this->log("Continue skipped");
                }
                break;

            case('finished'):

                // Remove all service files
                if (file_exists($this->_csvFileName.'.last')) {
                    unlink($this->_csvFileName.'.last');
                }

                $this->log("Renaming ".$this->_csvFileName.".processing to ".$this->_csvFileName);
                rename($this->_csvFileName.".processing", $this->_csvFileName);

                $date = date("Y-m-d H:i:s\n");
                file_put_contents($this->_csvFileName.".history", $date, FILE_APPEND);

                $this->log("Export finished");
                break;
        }
    }

    /**
     * Process should exit in case if there is no log file or the last page reached.
     */
    public function startNewThread($restart = false)
    {
        $folder = Mage::getBaseDir().'/shell/';
        $file = 'kk_googlebase.php';

        if (!defined('KK_GOOGLEBASE_DEBUG')) {
            $restartParam = '';
            if ($restart) {
                $this->log("\n\n\n\n\n");
                $restartParam = 'restart';

                if (file_exists($this->_csvFileName.".pid")) {
                    $pid = file_get_contents($this->_csvFileName . ".pid");
                    if ($pid) {
                        $this->log("Killing previous process pid: ".$pid);
                        exec("kill -9 ".$pid);
                        unlink($this->_csvFileName.".pid");
                    }
                }
            }

            $cmd = "cd $folder && php $file $restartParam";
            $outputFile =  Mage::getBaseDir('log').'/'.Kirchbergerknorr_GoogleBase_Model_Observer::LOGFILE.'.log';
            $pidFile = $this->_csvFileName.".pid";

            $this->log("Starting new background process");
            $this->log($cmd);
            exec(sprintf("%s >> %s 2>>%s & echo $! >> %s &", $cmd, $outputFile, $outputFile, $pidFile));

            $pid = file_get_contents($this->_csvFileName . ".pid");
            $this->log("pid: ".$pid);
        }
    }

    public function getLastProductId()
    {
        $lastFileName = $this->_csvFileName.".last";

        if (!file_exists($lastFileName)) {
            return 1;
        } else {
            $lastInfo = file_get_contents($lastFileName);
            $this->_exportedCount = 0;
            if ($lastInfo) {
                $lastInfoArray = json_decode($lastInfo, true);
                $this->_exportedCount = $lastInfoArray['exportedCount'];
                $this->_totalCount = $lastInfoArray['totalCount'];
                $this->_timeStarted = $lastInfoArray['timeStarted'];

                $this->log("Started: ".$this->_timeStarted);
                $this->log("Total Count: ".$this->_totalCount);
                $this->log("Exported Count: ".$this->_exportedCount);

                if ($this->_totalCount > 0) {
                    $started = strtotime($this->_timeStarted);
                    $now = time();
                    $duration = $now - $started;
                    $one = $duration/$this->_exportedCount;
                    $estimate = $one * $this->_totalCount;
                    $this->log("Duration: ".gmdate("H:i:s", $duration));
                    $this->log("Estimate: ".gmdate("H:i:s", $estimate));
                    $this->log("Finish in: ".gmdate("H:i:s", $estimate-$duration));
                    $this->log("Progress: ".round($this->_exportedCount/$this->_totalCount*100, 2)."%");
                    $this->log("---");
                }
                return $lastInfoArray['lastProductId'];
            } else {
                throw new Exception('getLastProductId is empty');
            }
        }
    }

    public function isLocked()
    {
        $isLocked = file_exists($this->_csvFileName.".locked");
        if ($isLocked) {
            $this->log("Lock file - ".$this->_csvFileName.".locked");

            if (file_exists($this->_csvFileName.".pid")) {
                $pid = file_get_contents($this->_csvFileName.".pid");
                $this->log("Pid file - ".$this->_csvFileName.".pid");

                try {
                    $result = shell_exec(sprintf("ps %d", $pid));
                    if (count(preg_split("/\n/", $result)) > 2) {
                        $this->log("Proccess is running - ".$pid);
                        $isLocked = true;
                    } else {
                        $this->log("Proccess is not running");
                        $isLocked = false;
                    }
                } catch (Exception $e) {
                    $this->logException($e);
                }
            } else {
                $isLocked = false;
            }
        }

        return $isLocked;
    }

    public function lockAndBlock()
    {
        file_put_contents($this->_csvFileName.".locked", date('Y-m-d H:i:s'));
    }

    public function unlock()
    {
        if (file_exists($this->_csvFileName.".locked")) {
            unlink($this->_csvFileName.".locked");
        }

        if (file_exists($this->_csvFileName.".pid")) {
            unlink($this->_csvFileName.".pid");
        }
    }

    protected function _isExportableProduct($product)
    {
        $stockItem = $product->getStockItem();
        return $product->isSaleable() && $stockItem->getIsInStock();
    }

    protected function _getDelivery($product)
    {
        if ($this->_deliveryBlock == null) {
            $this->_deliveryBlock = Mage::app()->getLayout()->createBlock('teamalpin_enterprisetheme/catalog_product_view_configurabletable');
        }

        $state = $this->_deliveryBlock->getDelivery($product);
        $delivery = '';

        if($state == 'state_green') {
            $delivery = 'Verfügbar - Lieferzeit 1-3 Tage';
        } elseif($state == 'state_blue_intersys') {
            $delivery = 'Lieferzeit ' . Mage::helper('core')->formatDate($product->getIntersysDeliverydate(), 'medium');
        } elseif($state == 'state_blue_teamalpin') {
            $delivery = 'Lieferzeit ' . $product->getAttributeText('teamalpin_deliverytime');
        } elseif($state == 'state_red') {
            $delivery = 'Lieferzeit 5-7 Tage';
        }

        return $delivery;
    }

    protected function _getShippingCosts($product, $country = 'DE')
    {
        switch ($country) {
            case "DE":
                if ($product->getIsBulky()) {
                    $shipping = 5;
                } else if ($product->getPrice()>40){
                    $shipping = 0;
                } else {
                    $shipping = 2.95;
                }

                break;
            case "AT":
                if ($product->getIsBulky()) {
                    $shipping = 10;
                } else {
                    $shipping = 3.95;
                }

                break;
            case "CH":
                if ($product->getIsBulky()) {
                    $shipping = 25;
                } else {
                    $shipping = 20;
                }

                break;
        }

        return $shipping;
    }

    public function getProductCollection()
    {
        if (!$this->_productCollection) {
            // todo: status attribute id status (87) load dynamically
            $this->_productCollection = Mage::getModel("catalog/product")->getCollection()
                ->setStoreId($this->_storeId)
                ->addAttributeToSelect('*')
                ->addAttributeToSelect('visibility')
                ->addAttributeToFilter('status', 1)
                ->addFieldToFilter('type_id', 'simple')
                ->joinTable(array('p' => 'catalog/product_relation'), 'child_id=entity_id', array(
                    'parent_id' => 'parent_id',
                ), array(), 'left')
                ->joinTable(array('ps' => 'catalog_product_entity_int'), 'entity_id=parent_id', array(
                    'parent_status' => 'value',
                ), array('attribute_id' => 87, 'store_id' => $this->_storeId), 'left')
            ;

            if (defined('KK_GOOGLEBASE_DEBUG_SKU')) {
                $this->_productCollection->addAttributeToFilter('sku', KK_GOOGLEBASE_DEBUG_SKU);
            }

            $this->_productCollection->getSelect()->where('ps.value = 1');
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($this->_productCollection);
        }

        return $this->_productCollection;
    }

    /**
     * Export Product Data with Attributes
     * Output to CSV file
     *
     * @param int $storeId Store View Id
     */
    public function doExport($storeId = null, $restart = false)
    {
        if (!$storeId) {
            throw new Exception("No store_id is specified");
            $this->_storeId = $storeId;
        }

        if ($restart) {
            $this->log('Restarted export for store_id: '.$storeId);
        }

        if (!$restart && $this->isLocked())
        {
            $this->log("Another process is running! Aborted");
            return false;
        }

        try {
            $this->lockAndBlock();

            if (!file_exists($this->_csvFileName.".last")) {
                $this->log('Restarted, while file does not exist: '.$this->_csvFileName.".last");
                $restart = true;
            }

            if ($restart) {
                $this->setState('started');
            }

            $header = $this->_getExportAttributes($storeId);
            if ($restart) {
                $this->_writeHeader($header);
            }

            $lastProductId = $this->getLastProductId();

            $pageSize = Mage::getStoreConfig('kk_googlebase/general/queue');

            $products = $this->getProductCollection();
            $products->getSelect()
                ->limit($pageSize)
                ->where('e.entity_id > ' . $lastProductId)
                ->order('e.entity_id ASC')
                ->group('e.entity_id');

            if (defined('KK_GOOGLEBASE_DEBUG')) {
                $this->log("SQL:\n" . (string)$products->getSelect());
            }

            $products->load();
            $this->_foundCount = sizeof($products);

            $this->log("Found: ".$this->_foundCount);

            $exportedInIteration = 0;
            if ($this->_foundCount) {
                foreach ($products as $productData) {
                    $product = Mage::getModel("catalog/product");
                    $product->setStoreId($storeId);
                    $product->load($productData['entity_id']);

                    $parentProduct = null;
                    if ($productData['parent_id']) {
                        $parentProduct = Mage::getModel('catalog/product');
                        $parentProduct->setStoreId($storeId);
                        $parentProduct->load($productData['parent_id']);
                    }

                    if (defined('KK_GOOGLEBASE_DEBUG')) {
                        $this->log($productData['sku'] . ' exporting...');
                    }

                    $lastInfo = json_encode(array(
                        "totalCount" => $this->_totalCount,
                        "timeStarted" => $this->_timeStarted,
                        "exportedCount" => $this->_exportedCount,
                        "lastProductId" => $product->getId(),
                    ), JSON_PRETTY_PRINT);

                    if (isset($lastInfo)) {
                        file_put_contents($this->_csvFileName . ".last", $lastInfo);
                    }

                    if ($product->getTypeID() != 'simple') {
                        if (defined('KK_GOOGLEBASE_DEBUG')) {
                            $this->log($productData['sku'] . ' skipped as not simple');
                        }
                        continue;
                    }

                    if ($product->getStatus() != 1 || ($parentProduct && $parentProduct->getStatus() != 1)) {
                        if (defined('KK_GOOGLEBASE_DEBUG')) {
                            if ($product->getStatus() != 1) {
                                $this->log($productData['sku'] . ' skipped as disabled');
                            } else {
                                if ($parentProduct) {
                                    $this->log('configurable: ' . $productData['parent_id']);
                                    $this->log($productData['sku'] . ' skipped as no configurable');
                                } else {
                                    $this->log($productData['sku'] . ' skipped as configurable disabled');
                                }
                            }
                        }
                        continue;
                    }

                    if (!$parentProduct && (int)$product->getVisibility() !== 4) {
                        if (defined('KK_GOOGLEBASE_DEBUG')) {
                            $this->log($productData['sku'] . ' skipped as visibility='.$product->getVisibility());
                        }
                        continue;
                    }

                    if ($parentProduct && (int)$parentProduct->getVisibility() !== 4) {
                        if (defined('KK_GOOGLEBASE_DEBUG')) {
                            $this->log($productData['sku'] . ' skipped as parent '.$parentProduct->getSku().' visibility='.$parentProduct->getVisibility());
                        }
                        continue;
                    }

                    if (!$this->_isExportableProduct($product)) {
                        if (defined('KK_GOOGLEBASE_DEBUG')) {
                            $this->log($productData['sku'] . ' skipped as not exportable');
                        }
                        continue;
                    }

                    if ($parentProduct) {
                        $stockItem = $parentProduct->getStockItem();
                        if (!$stockItem->getIsInStock()) {
                            if (defined('KK_GOOGLEBASE_DEBUG')) {
                                $this->log($productData['sku'] . ' skipped as parent '.$parentProduct->getSku().' not exportable');
                            }
                            continue;
                        }
                    }

                    $this->_exportedCount++;
                    $exportedInIteration++;

                    // todo: move it to product model
                    $productIndex = array(
                        'id' => $product->getId(),
                        'type' => $product->getTypeId(),
                        'visibility' => $product->getVisibility(),
                        'status' => $product->getStatus(),
                        'sku' => $productData['sku'],
                        'name' => $product->getName(),
                        'short_description' => $product->getShortDescription(),
                        'description' => $product->getDescription(),
                        'category' => $this->_getCategoryPath($productData['entity_id'], $storeId),
                        'category_url' => $this->_getCategoriesUrls($product, $storeId),
                        'manufacturer' => $product->getAttributeText('manufacturer'),
                        'ean' => $product->getIntersysEan(),
                        'size' => $product->getAttributeText('intersys_size'),
                        'color' => $product->getAttributeText('intersys_color'),
                        'deeplink' => $product->getProductUrl(),
                        'delivery_time' => $this->_getDelivery($product),
                        'shipping_costs_de' => $this->_getShippingCosts($product, 'DE'),
                        'shipping_costs_at' => $this->_getShippingCosts($product, 'AT'),
                        'shipping_costs_ch' => $this->_getShippingCosts($product, 'CH'),
                    );

                    $productIndex['image_small'] = (string)$this->_imageHelper->init($product, 'small_image')->resize('150');
                    $productIndex['image_big'] = (string)$this->_imageHelper->init($product, 'image')->resize('300');

                    $productIndex['price'] = Mage::helper('tax')->getPrice($product, $product->getPrice());
                    $productIndex['special_price'] = Mage::helper('tax')->getPrice($product, $product->getFinalPrice());

                    if ($parentProduct) {
                        if (method_exists($parentProduct->getTypeInstance(true), 'getConfigurableAttributes')) {
                            $attributes = $parentProduct->getTypeInstance(true)->getConfigurableAttributes($parentProduct);
                            $parentPrice = $parentProduct->getPrice();
                            $parentSpecialPrice = $parentProduct->getFinalPrice();

                            $pricesByAttributeValues = array();
                            if (count($attributes)) {
                                foreach ($attributes as $attribute) {
                                    $prices = $attribute->getPrices();
                                    if (count($prices)) {
                                        foreach ($prices as $price) {
                                            if ($price['is_percent']) { //if the price is specified in percents
                                                $pricesByAttributeValues[$price['value_index']] = (float)$price['pricing_value'] * $parentSpecialPrice / 100;
                                            } else { //if the price is absolute value
                                                $pricesByAttributeValues[$price['value_index']] = (float)$price['pricing_value'];
                                            }
                                        }
                                    }
                                }
                            }

                            $simple = $parentProduct->getTypeInstance()->getUsedProducts();

                            foreach ($simple as $sProduct) {
                                if ($sProduct->getId() == $productIndex['id']) {

                                    foreach ($attributes as $attribute) {
                                        $value = $sProduct->getData($attribute->getProductAttribute()->getAttributeCode());
                                        if (isset($pricesByAttributeValues[$value])) {
                                            $parentPrice += $pricesByAttributeValues[$value];
                                            $parentSpecialPrice += $pricesByAttributeValues[$value];
                                        }
                                    }

                                }
                            }

                            $productIndex['price'] = $parentPrice;
                            $productIndex['special_price'] = $parentSpecialPrice;
                        }

                        $productIndex['name'] = $parentProduct->getName();
                        $productIndex['parent_id'] = $parentProduct->getId();
                        $productIndex['parent_visibility'] = $parentProduct->getVisibility();
                        $productIndex['parent_status'] = $parentProduct->getStatus();

                        $productIndex['deeplink'] = $parentProduct->getProductUrl();

                        // todo: check if simple product picture is not a placeholder
                        $productIndex['image_small'] = (string)$this->_imageHelper->init($parentProduct, 'small_image')->resize('150');
                        $productIndex['image_big'] = (string)$this->_imageHelper->init($parentProduct, 'image')->resize('300');

                        if (!$productIndex['category']) {
                            $productIndex['category'] = $this->_getCategoryPath($parentProduct->getId(), $storeId);
                            $productIndex['category_url'] = $this->_getCategoriesUrls($parentProduct, $storeId);
                        }

                        if (!$productIndex['manufacturer']) {
                            $productIndex['manufacturer'] = $parentProduct->getAttributeText('manufacturer');
                        }

                        if (strlen($productIndex['short_description']) < 2) {
                            $productIndex['short_description'] = $parentProduct->getShortDescription();
                        }

                        if (strlen($productIndex['description']) < 2) {
                            $productIndex['description'] = $parentProduct->getDescription();
                        }
                    }

                    if ($productIndex['color'] && $productIndex['color'][0] == '-') {
                        $productIndex['color'] = '';
                    } else {
                        $productIndex['name'] .= ' - ' . $productIndex['color'];
                    }

                    if ($productIndex['size'] && $productIndex['size'][0] == '-') {
                        $productIndex['size'] = '';
                    } else {
                        $productIndex['name'] .= ' - ' . $productIndex['size'];
                    }

                    $productIndex['name'] = htmlentities($productIndex['name']);

                    $this->_writeItem($productIndex);
                }
            }

            unset($products);
            unset($productAttributes);
            unset($productRelations);
            flush();

            $this->log("Exported: ".$exportedInIteration);

            if ($this->_foundCount < 10 && $exportedInIteration == 0) {
                $this->setState('finished');
                $this->unlock();
                return false;
            }

            $this->unlock();
            $this->setState('continue');
        } catch (Exception $e) {
            $this->logException($e);
            $this->unlock();
        }
    }

    protected function _getCategoriesUrls($product, $storeId = null)
    {
        Mage::app()->getStore($storeId);
        $urls = array();
        $categories = $product->getCategoryIds();
        foreach($categories as $categoryId){
            $_category = Mage::getModel('catalog/category')->load($categoryId);
            $url = $_category->getUrl();
            $urls[] = str_replace('kk_googlebase.php/admin/', 'de_de/', $url);
        }

        return join(' | ', $urls);
    }

    protected function _getWriteAdapter()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    protected function _getReadAdapter()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_read');
    }

    public function getTable($table)
    {
        return Mage::getSingleton('core/resource')->getTableName($table);
    }

    /**
     * Get Category Path by Product ID
     *
     * @param   int $productId
     * @param    int $storeId
     * @return  string
     */
    protected function _getCategoryPath($productId, $storeId = null)
    {
        if ($this->_categoryNames === null) {
            $categoryCollection = Mage::getResourceModel('catalog/category_attribute_collection');
            $categoryCollection->getSelect()->where("attribute_code IN('name', 'is_active')");

            foreach ($categoryCollection as $categoryModel) {
                ${$categoryModel->getAttributeCode().'Model'} = $categoryModel;
            }

            // TODO: Replace magic with array key
            $select = $this->_getReadAdapter()->select()
                ->from(
                    array('main' => $nameModel->getBackendTable()),
                    array('entity_id', 'value')
                )
                ->join(
                    array('e' => $is_activeModel->getBackendTable()),
                    'main.entity_id=e.entity_id AND (e.store_id = 0 OR e.store_id = '.$storeId.') AND e.attribute_id='.$is_activeModel->getAttributeId(),
                    null
                )
                ->where('main.attribute_id=?', $nameModel->getAttributeId())
                ->where('e.value=?', '1')
                ->where('main.store_id = 0 OR main.store_id = ?', $storeId);

            $this->_categoryNames = $this->_getReadAdapter()->fetchPairs($select);
        }

        if ($this->_productsToCategoryPath === null) {
            $select = $this->_getReadAdapter()->select()
                ->from(
                    array('main' => $this->getTable('catalog/category_product_index')),
                    array('product_id')
                )
                ->join(
                    array('e' => $this->getTable('catalog/category')),
                    'main.category_id=e.entity_id',
                    null
                )
                ->columns(array('e.path' => new Zend_Db_Expr('GROUP_CONCAT(e.path)')))
                ->where('main.store_id = ?', $storeId)
                ->where('e.path LIKE \'1/' . Mage::app()->getStore($storeId)->getRootCategoryId() .'/%\'')
                ->group('main.product_id');

            $this->_productsToCategoryPath = $this->_getReadAdapter()->fetchPairs($select);
        }

        $value = '';
        if (isset($this->_productsToCategoryPath[$productId])) {
            $paths = explode(',', $this->_productsToCategoryPath[$productId]);
            foreach ($paths as $path) {
                $categoryIds = explode('/', $path);
                $categoryIdsCount = count($categoryIds);
                $categoryPathArray = array();
                for($i=2;$i < $categoryIdsCount;$i++) {
                    if (!isset($this->_categoryNames[$categoryIds[$i]])) {
                        continue 2;
                    }
                    $categoryPathArray[] = trim($this->_categoryNames[$categoryIds[$i]]);
                }
                $categoryPath = join(' > ', $categoryPathArray);
                if ($categoryIdsCount > 2) {
                    $value .= rtrim($categoryPath,'/').'|';
                }
            }
            $value = trim($value, '|');
        }

        return $value;
    }
}