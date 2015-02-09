<?php
/**
 * Export Product
 *
 * filename.csv             Exported CSV
 * filename.csv.processing  Partly exported CSV (currently under process)
 * filename.csv.run         File shows that process is running. File content is amount of found products.
 * filename.csv.thread      Log file
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
    protected $_deliveryBlock;
    protected $_exportAttributeCodes = null;
    protected $_categoryNames = null;
    protected $_productsToCategoryPath = null;
    protected $_exportAttributes = null;

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

                $count = Mage::getModel('catalog/product')->getCollection()
                    ->addFieldToFilter('visibility',4)
                    ->addFieldToFilter('status', 1)->getSize();

                $this->log("Export started for {$count}");

                if (file_exists($this->_csvFileName)) {
                    unlink($this->_csvFileName);
                }

                $this->log("Filename: {$this->_csvFileName}");

                file_put_contents($this->_csvFileName.".processing", '');
                file_put_contents($this->_csvFileName.".run", $count);
                file_put_contents($this->_csvFileName.".thread", '');
                break;

            case('continue'):

                $this->log("Statistics: found: ".$this->_foundCount.", exported:".$this->_exportedCount);
                if (file_exists($this->_csvFileName.".run")) {
                    $this->log("Continue from {$this->_lastProductId}");
                    $file = Mage::getBaseDir().'/shell/kk_googlebase.php';
                    $this->_runNextProcess($file, $this->_csvFileName.".thread");
                } else {
                    $this->log("Continue skipped");
                }
                break;

            case('finished'):

                // Remove all service files
                foreach (array('.run', '.locked', '.last') as $ext) {
                    if (file_exists($this->_csvFileName.$ext)) {
                        unlink($this->_csvFileName.$ext);
                    }
                }

                rename($this->_csvFileName.".processing", $this->_csvFileName);

                $this->log("Export finished");
                break;
        }
    }

    /**
     * Process should exit in case if there is no log file or the last page reached.
     *
     * If you want to kill this process in a system use the following command:
     * export pid=`ps aux | grep kk_googlebase | awk 'NR==1{print $2}'`; kill -9 $pid
     */
    protected function _runNextProcess($file, $log)
    {
        $this->log("php $file >> $log &");
        shell_exec("php $file >> $log &");
    }

    /**
     * Retrieve searchable products per store and set limit from configuration
     *
     * @param int $storeId
     * @param array $staticFields
     * @param array|int $productIds
     * @param int $lastProductId
     * @param int $limit
     * @return array
     */
    protected function _getSearchableProducts($storeId, array $staticFields, $productIds = null, $lastProductId = 0,
                                              $limit = 100)
    {
        $limit = Mage::getStoreConfig('kk_googlebase/general/queue');
        return parent::_getSearchableProducts($storeId, $staticFields, $productIds, $lastProductId, $limit);
    }

    public function getLastProductId()
    {
        $lastFileName = $this->_csvFileName.".last";

        if (!file_exists($lastFileName)) {
            return 0;
        } else {
            $lastProductId = file_get_contents($lastFileName);
            if ($lastProductId) {
                return $lastProductId;
            } else {
                throw new Exception('getLastProductId is empty');
            }
        }
    }

    public function isLocked()
    {
        $isLocked = file_exists($this->_csvFileName.".locked");
        if ($isLocked) {
            $this->log("Locked: ".$this->_csvFileName.".locked");
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
    }

    protected function _isExportableProduct($product)
    {
        return $product->isSaleable();
    }

    protected function _getDelivery($product)
    {
        if ($this->_deliveryBlock == null) {
            $this->_deliveryBlock = Mage::app()->getLayout()->createBlock('teamalpin_enterprisetheme/catalog_product_view_configurabletable');
        }

        $state = $this->_deliveryBlock->getDelivery($product);
        $delivery = '';

        if($state == 'state_green') {
            $delivery = 'Verfügbar';
        } elseif($state == 'state_blue_intersys') {
            $delivery = 'Lieferbar ab ' . Mage::helper('core')->formatDate($product->getIntersysDeliverydate(), 'medium');
        } elseif($state == 'state_blue_teamalpin') {
            $delivery = 'Lieferbar ab ' . $product->getAttributeText('teamalpin_deliverytime');
        } elseif($state == 'state_red') {
            $delivery = 'Lieferung innerhalb einer Woche';
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

    /**
     * Export Product Data with Attributes
     * Output to CSV file
     *
     * @param int $storeId Store View Id
     */
    public function doExport($storeId = null, $restart = false)
    {
        if (!$restart && $this->isLocked())
        {
            $this->log("Another process is running! Aborted");
            return false;
        }

        try {
            $this->lockAndBlock();

            if ($restart) {
                $this->setState('started');
                if (file_exists($this->_csvFileName.".last")) {
                    unlink($this->_csvFileName.".last");
                }
            }

            $header = $this->_getExportAttributes($storeId);
            if ($restart) {
                $this->_writeHeader($header);
            }

            $lastProductId = $this->getLastProductId();

            $products = $this->_getSearchableProducts($storeId, array('sku'), null, $lastProductId);

            $this->_foundCount = count($products);

            if (!$products) {
                $this->setState('finished');
                return false;
            }

            $productRelations   = array();
            foreach ($products as $productData) {
                $lastProductId = $productData['entity_id'];
                $productAttributes[$productData['entity_id']] = $productData['entity_id'];
            }

            file_put_contents($this->_csvFileName.".last", $lastProductId);

            $this->_exportedCount = 0;

            foreach ($products as $productData) {
                if (!isset($productAttributes[$productData['entity_id']])) {
                    continue;
                }

                $this->_lastProductId = $productData['entity_id'];

                $product = Mage::getModel("catalog/product");
                $product->setStoreId($storeId);
                $product->load($productData['entity_id']);

                if (!$this->_isExportableProduct($product)) {
                    continue;
                }

                $this->_exportedCount += 1;

                $productIndex = array(
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
                    'price' => $product->getPrice(),
                    'special_price' => $product->getSpecialPrice(),
                    'deeplink' => $product->getProductUrl(),
                    'delivery_time' => $this->_getDelivery($product),
                    'shipping_costs_de' => $this->_getShippingCosts($product, 'DE'),
                    'shipping_costs_at' => $this->_getShippingCosts($product, 'AT'),
                    'shipping_costs_ch' => $this->_getShippingCosts($product, 'CH'),
                );

                try {
                    $productIndex['image_small'] = (string) $this->_imageHelper->init($product, 'small_image')->resize('150');
                    $productIndex['image_big'] = (string) $this->_imageHelper->init($product, 'image')->resize('300');
                } catch (Exception $e) {

                }

                $this->_writeItem($productIndex);
            }


            unset($products);
            unset($productAttributes);
            unset($productRelations);
            flush();

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