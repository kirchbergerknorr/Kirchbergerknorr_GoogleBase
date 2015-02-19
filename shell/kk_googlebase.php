<?php

ini_set('session.use_cookies', 0);
ini_set('session.cache_limiter', '');

require_once "../app/Mage.php";
require_once 'abstract.php';

Mage::app('admin');
Mage::setIsDeveloperMode(true);

class Kirchbergerknorr_Shell_GoogleBase extends Mage_Shell_Abstract
{
    public function log($message, $p1 = null, $p2 = null, $p3 = null)
    {
        echo sprintf($message, $p1, $p2, $p3)."\n";
    }

    public function export($restart = false)
    {
        if (!Mage::getStoreConfig('kk_googlebase/general/enabled')) {
            $this->log('Kirchbergerknorr_GoogleBase is disabled');
            return false;
        }

        Mage::getModel('kk_google_base/observer')->export(null, $restart);
    }

    public function run($params = false)
    {
        if (!$params || count($params) < 2) {
            $this->export(false);
            return false;
        } else {
            if ($params[1] == 'stop') {
                $this->log("Killing process");
                shell_exec("kill $(ps aux | grep kk_googlebase | grep -v 'grep' | awk '{print $2}')");
            } elseif ($params[1] == 'restart') {
                $this->export(true);
            } elseif ($params[1] == 'test') {
                $csvFileName = Mage::getStoreConfig('kk_googlebase/general/export_path');

                if (!$csvFileName) {
                    throw new Exception("Export file name is not set.");
                }

                $file = Mage::getBaseDir() . $csvFileName;

                if (!file_exists($file)) {
                    throw new Exception("Export file is not exists. Run export first.");
                }

                $array = array();
                $ids  = array();

                if (($handle = fopen($file, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle, 9000, ";")) !== FALSE) {
                        $array[] = $data;
                    }
                    fclose($handle);
                }

                foreach ($array as $data) {
                    if (!isset($ids[$data[0]])) {
                        $ids[$data[0]] = 1;
                    } else {
                        $ids[$data[0]]++;
                    }
                }

                foreach ($ids as $id=>$count) {
                    if ($count == 1) {
                        unset($ids[$id]);
                    }
                }

                print_r($ids);

                echo "Total: ".count($array)."\n";
                echo "Duplicates: ".count($ids)."\n";

                echo "\n";
            } elseif ($params[1] == 'debug') {
                define('KK_GOOGLEBASE_ECHO_LOGS', true);
                define('KK_GOOGLEBASE_DEBUG', true);
                if (isset($params[2])) {
                    define('KK_GOOGLEBASE_DEBUG_SKU', $params[2]);
                }
                $this->export(true);
            }
        }
    }
}

$shell = new Kirchbergerknorr_Shell_GoogleBase();

try {
    $shell->run($argv);
} catch (Exception $e) {
    $shell->log($e->getMessage());
}