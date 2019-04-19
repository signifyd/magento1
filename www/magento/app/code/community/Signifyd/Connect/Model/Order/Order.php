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
    public function setState($state, $status = false, $comment = '', $isCustomerNotified = null)
    {
        try {
            $log = Mage::getStoreConfig('signifyd_connect/log/all');

            // Log level 2 => debug
            if ($log == 2) {
                $currentState = $this->getState();
                $currentUrl = Mage::helper('core/url')->getCurrentUrl();

                $this->logger->addLog("Order {$this->getIncrementId()} state change from {$currentState} to {$state}");
                $this->logger->addLog("Request URL: {$currentUrl}");

                $debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $debugBacktraceLog = [];
                $nonMagentoModules = [];

                foreach ($debugBacktrace as $i => $step) {
                    $debugBacktraceLog[$i] = [];
                    $function = '';

                    if (isset($step['class'])) {
                        $function .= $step['class'];

                        if ($step['class'] != '1Signifyd_Connect_Model_Order_Order') {
                            list($vendor, $module, $class) = explode('_', $step['class'], 3);

                            if ($vendor != "Magento") {
                                $nonMagentoModules["{$vendor}_{$module}"] = '';
                            }
                        }
                    }

                    if (isset($step['type'])) {
                        $function .= $step['type'];
                    }

                    if (isset($step['function'])) {
                        $function .= $step['function'];
                    }

                    $debugBacktraceLog[$i][] = "\t[{$i}] {$function}";

                    $file = isset($step['file']) ? str_replace(BP, '', $step['file']) : false;

                    if ($file !== false) {
                        $debugBacktraceLog[$i][] = "line {$step['line']} on {$file}";
                    }

                    $debugBacktraceLog[$i] = implode(', ', $debugBacktraceLog[$i]);
                }

                if (empty($nonMagentoModules) == false) {
                    $nonMagentoModulesList = implode(', ', array_keys($nonMagentoModules));
                    $this->logger->addLog("WARNING: non Magento modules found on backtrace: {$nonMagentoModulesList}");
                }

                $debugBacktraceLog = implode("\n", $debugBacktraceLog);
                $this->logger->addLog("Backtrace: \n{$debugBacktraceLog}\n\n");
            }
        } catch (\Exception $e) {
            $this->logger('Exception logging order state change: ' . $e->getMessage());
        }
        
        return parent::setState($state, $status, $comment, $isCustomerNotified);
    }
}