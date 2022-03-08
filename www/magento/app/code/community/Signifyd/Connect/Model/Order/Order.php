<?php

class Signifyd_Connect_Model_Order_Order extends Mage_Sales_Model_Order
{
    /**
     * @var Signifyd_Connect_Helper_Debug
     */
    protected $logger;
    
    public function __construct()
    {
        $this->logger = Mage::helper('signifyd_connect/debug');
        
        parent::__construct();
    }

    /**
     * @param string $state
     * @param bool $status
     * @param string $comment
     * @param null $isCustomerNotified
     * @return Mage_Sales_Model_Order
     */
    protected function _setState($state, $status = false, $comment = '', $isCustomerNotified = null, $shouldProtectState = false)
    {
        try {
            $log = Mage::helper('signifyd_connect')->getConfigData('log/all', $this);

            // Log level 2 => debug
            if ($log == 2) {
                $currentState = $this->getState();
                $currentUrl = Mage::helper('core/url')->getCurrentUrl();

                if ($currentState != $state) {
                    $this->logger->addLog("Order {$this->getIncrementId()} state change from {$currentState} to {$state}", $this);
                    $this->logger->addLog("Request URL: {$currentUrl}", $this);
                }
            }
        } catch (\Exception $e) {
            $this->logger->addLog('Exception logging order state change: ' . $e->getMessage(), $this);
        }
        
        return parent::_setState($state, $status, $comment, $isCustomerNotified, $shouldProtectState);
    }
}