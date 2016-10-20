<?php

class Signifyd_Connect_Model_Case extends Mage_Core_Model_Abstract
{
    protected $logger;

    protected function _construct()
    {  
        $this->_init('signifyd_connect/case');
        $this->_isPkAutoIncrement = false;
        $this->logger = Mage::helper('signifyd_connect/log');
    }

    public function setMagentoStatusTo($case, $status)
    {
        $id  = (is_array($case))? $case['order_increment'] : $case->getId();
        $caseLoaded = Mage::getModel('signifyd_connect/case')->load($id);
        try {
            $caseLoaded->setMagentoStatus($status);
            $caseLoaded->save();
            $this->logger->addLog("Signifyd: Case no:{$caseLoaded->getId()} status set to {$status}");
        } catch (Exception $e){
            $this->logger->addLog("Signifyd: Error setting case no:{$caseLoaded->getId()} status to {$status}");
            return false;
        }

        return true;
    }
}
