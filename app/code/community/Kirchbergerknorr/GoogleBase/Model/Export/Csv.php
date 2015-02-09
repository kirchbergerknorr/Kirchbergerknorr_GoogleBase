<?php
/** 
 * Export Product
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr_GoogleBase
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2015 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */ 

class Kirchbergerknorr_GoogleBase_Model_Export_Csv extends Kirchbergerknorr_GoogleBase_Model_Export_Abstract
{
    /**
     * Add row to CSV file
     *
     * @param array $data
     */
    protected function _writeItem($data, $header = false)
    {
        if (!is_array($data)) {
            return false;
        }

        if (!$header) {
            foreach ($data as &$item) {
                $item = str_replace(array("\r", "\n", "\""), array(' ', ' ', "''"), trim(strip_tags($item), ';'));
            }

            foreach ($this->_exportAttributeCodes as $code) {
                if (array_key_exists($code, $data)) {
                    $orderedArray[] = $data[$code];
                } else {
                    $orderedArray[] = '';
                }
            }
        } else {
            $orderedArray = $data;
        }

        $row = '"'.implode('";"', $orderedArray).'"'."\n";
        file_put_contents($this->_csvFileName.".processing", $row, FILE_APPEND);
    }

    protected function _writeHeader($data)
    {
        $this->_writeItem($data, true);
    }
}