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
    protected $log;

    protected $logFile = 'signifyd_connect.log';

    public function isGlobalLogEnabled()
    {
        if (!isset($this->log)) {
            $this->log = Mage::helper('signifyd_connect')->getConfigData('log/all');
        }

        return $this->log;
    }

    public function addLog($msg, $entity = null)
    {
        if (is_object($entity)) {
            $log = Mage::helper('signifyd_connect')->getConfigData('log/all', $entity);
        } else {
            $log = $this->isGlobalLogEnabled();
        }

        if ($log == false) {
            return false;
        }

        Mage::log($msg, null, $this->logFile);
    }
}