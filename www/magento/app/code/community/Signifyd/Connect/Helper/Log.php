<?php
/**
 * Log Helper
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */
class Signifyd_Connect_Helper_Log extends Mage_Core_Helper_Abstract
{
    protected $logFile;

    public function __construct()
    {
        $this->logFile = 'signifyd_connect.log';
    }

    public function addLog($msg)
    {
        if($this->isLogEnabled())
            Mage::log($msg, null, $this->logFile);

        return true;
    }

    public function isLogEnabled()
    {
        return Mage::getStoreConfig('signifyd_connect/log/all');
    }
}

/* Filename: Log.php */
/* Location: ../app/code/Community/Signifyd/Connect/Helper/Log.php */