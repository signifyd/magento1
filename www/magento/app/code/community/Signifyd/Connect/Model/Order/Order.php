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

                    $debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                    $debugBacktraceLog = [];
                    $nonMagentoModules = [];

                    foreach ($debugBacktrace as $i => $step) {
                        $debugBacktraceLog[$i] = [];
                        $function = '';

                        if (isset($step['class'])) {
                            $function .= $step['class'];

                            if ($step['class'] != 'Signifyd_Connect_Model_Order_Order') {
                                $parts = explode('_', $step['class'], 3);
                                $vendor = isset($parts[0]) ? $parts[0] : null;
                                $module = isset($parts[1]) ? $parts[1] : null;

                                if ($vendor != "Mage") {
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
                        $this->logger->addLog("WARNING: non Magento modules found on backtrace: {$nonMagentoModulesList}", $this);
                    }

                    $debugBacktraceLog = implode("\n", $debugBacktraceLog);
                    $this->logger->addLog("Backtrace: \n{$debugBacktraceLog}\n\n", $this);
                }
            }
        } catch (\Exception $e) {
            $this->logger->addLog('Exception logging order state change: ' . $e->getMessage(), $this);
        }
        
        return parent::_setState($state, $status, $comment, $isCustomerNotified, $shouldProtectState);
    }
}