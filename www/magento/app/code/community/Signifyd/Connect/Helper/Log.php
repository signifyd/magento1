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
    protected $logFile = 'signifyd_connect.log';

    public function addLog($msg)
    {
        if ($this->isLogEnabled()) {
            Mage::log($msg, null, $this->logFile);
        }
    }

    public function isLogEnabled()
    {
        return Mage::getStoreConfig('signifyd_connect/log/all');
    }
}