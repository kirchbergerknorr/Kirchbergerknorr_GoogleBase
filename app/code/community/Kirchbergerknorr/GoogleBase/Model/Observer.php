<?php
/**
 * Observer
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr_GoogleBase
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2015 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Kirchbergerknorr_GoogleBase_Model_Observer
{
    const LOGFILE = 'kk_googlebase';

    /*
     * Show debug bugtrace
     */
    private $debug = false;

    public function log($message)
    {
        Mage::log($message, null, self::LOGFILE.'.log');

        if (defined('KK_GOOGLEBASE_ECHO_LOGS')) {
            echo date("H:i:s").": ".$message."\n";
        }
    }

    private function debug(Exception $e)
    {
        $lines = $e->getTrace();

        $debug = "[DEBUG]\n\n";
        foreach ($lines as $line) {
            $debug .=
                @$line['class'].".".$line['function']."\n\n";
        }

        $this->log($debug);
    }

    public function logException(Exception $e)
    {
        $this->log("[EXCEPTION] ".$e->getMessage());

        if ($this->debug) {
            $this->debug($e);
        };

        Mage::logException($e);
    }

    public function export($observer = null, $restart = false)
    {
        if (!Mage::getStoreConfig('kk_googlebase/general/enabled')) {
            return false;
        }

        try {
            $csv = Mage::getModel('kk_google_base/export_csv');
            $csv->doExport(2, $restart);
        } catch (Exception $e) {
            $this->logException($e);
        }
    }
}