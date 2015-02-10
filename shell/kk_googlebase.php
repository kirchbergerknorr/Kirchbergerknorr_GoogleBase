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

    public function help()
    {
        $this->log('Kirchbergerknorr_GoogleBase Help:');

        $help = <<< HELP

    Start export:

      php kk_googlebase.php

HELP;

        $this->log($help);
    }

    public function run($params = false)
    {
        echo "Start\n";
        if (!$params || count($params) < 2) {
            $this->export(false);
            return false;
        } else {
            define('KK_GOOGLEBASE_ECHO_LOGS', true);

            if ($params[1] == 'restart') {
                $this->export(true);
            } elseif ($params[1] == 'debug') {
                define('KK_GOOGLEBASE_DEBUG', true);
                if (isset($params[2])) {
                    define('KK_GOOGLEBASE_DEBUG_SKU', $params[2]);
                }
                $this->export(true);
            }
        }
        echo "Finish\n";
    }
}


$shell = new Kirchbergerknorr_Shell_GoogleBase();

try {
    $shell->run($argv);
} catch (Exception $e) {
    $shell->log($e->getMessage());
}