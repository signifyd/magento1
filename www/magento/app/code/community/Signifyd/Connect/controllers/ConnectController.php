<?php

class Signifyd_Connect_ConnectController extends Mage_Core_Controller_Front_Action
{
    public $_request = array();
    public $_topic = false;
    public $_order = false;
    public $_store_id = null;
    public $_case = false;
    
    public function getApiKey()
    {
        return Mage::getStoreConfig('signifyd_connect/settings/key');
    }
    
    public function holdThreshold()
    {
        return (int)Mage::getStoreConfig('signifyd_connect/advanced/hold_orders_threshold', $this->_store_id);
    }
    
    public function canHold()
    {
        return Mage::getStoreConfig('signifyd_connect/advanced/hold_orders', $this->_store_id);
    }
    
    public function canInvoice()
    {
        return Mage::getStoreConfig('signifyd_connect/advanced/invoice_orders', $this->_store_id);
    }
    
    public function notifyCustomer()
    {
        return Mage::getStoreConfig('signifyd_connect/advanced/invoice_orders_notify', $this->_store_id);
    }
    
    public function enabled()
    {
        $retrieve_scores = Mage::getStoreConfig('signifyd_connect/advanced/retrieve_score');
        $enabled = Mage::getStoreConfig('signifyd_connect/settings/enabled');
        
        return $enabled && $retrieve_scores;
    }
    
    
    public function getUrl($code)
    {
        return Mage::getStoreConfig('signifyd_connect/settings/url', $this->_store_id) . '/cases/' . $code;
    }
    
    public function logErrors()
    {
        return Mage::getStoreConfig('signifyd_connect/log/error');
    }
    
    public function logRequest()
    {
        return Mage::getStoreConfig('signifyd_connect/log/request');
    }
    
    public function getRawPost()
    {
        if (isset($HTTP_RAW_POST_DATA) && $HTTP_RAW_POST_DATA) {
            return $HTTP_RAW_POST_DATA;
        }
        
        $post = file_get_contents("php://input");
        
        if ($post) {
            return $post;
        }
        
        return '';
    }
    
    public function getDefaultMessage()
    {
        return 'This URL is working! Please copy & paste the current URL into your <a href="https://signifyd.com/settings">settings</a> page in the Notifications section';
    }
    
    public function getDisabledMessage()
    {
        return 'This URL is disabled in the Magento admin panel! Please enable score retrieval under Admin>System>Config>Signifyd Connect>Advanced before setting this url in your Signifyd <a href="https://signifyd.com/settings">settings</a> page.';
    }
    
    public function unsupported()
    {
        echo 'This request type is currently unsupported';
        
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1','403 Forbidden')
            ->sendResponse();
        
        exit;
    }
    
    public function complete()
    {
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1','200 Ok')
            ->sendResponse();
        
        exit;
    }
    
    public function validRequest($request, $hash)
    {
        $check = base64_encode(hash_hmac('sha256', $request, $this->getApiKey(), true));
        
        if ($this->logRequest()) {
            Mage::log('API request hash check: ' . $check, null, 'signifyd_connect.log');
        }
        
        if ($check == $hash) {
            return true;
        }
        
        return false;
    }
    
    public function initCase($order_increment)
    {
        $case = false;
        
        if (isset($this->_request['orderId'])) {
            $cases = Mage::getModel('signifyd_connect/case')->getCollection();
            $cases->addFieldToFilter('order_increment', $this->_request['orderId']);
            
            foreach ($cases as $c) {
                $case = $c;
                break;
            }
        }
        
        /*
        // Do not create cases if not found -- potential concurrency issues
        if (!$case) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($order_increment);
            
            if ($order && $order->getId()) {
                $case = Mage::helper('signifyd_connect')->generateCase($order);
            }
        }
        */
        
        return $case;
    }
    
    public function initRequest($request)
    {
        $this->_request = json_decode($request, true);
        
        $topic = $this->getHeader('HTTP_X_SIGNIFYD_WEBHOOK_TOPIC');
        
        $this->_topic = $topic;
        
        $this->_case = $this->initCase($this->_request['orderId']);
        
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($this->_request['orderId']);
        
        if ($this->_order && $this->_order->getId()) {
            $this->_store_id = $this->_order->getStoreId();
        }
        
        if (!$this->_case && $this->logRequest()) {
            Mage::log('No matching case was found for this request. order_increment: ' . $this->_request['orderId'], null, 'signifyd_connect.log');
        }
    }
    
    public function holdOrder($order)
    {
        if ($order && $order->getId() && $order->canHold() && $this->canHold()) {
            $order->hold();
            $order->save();
            
            if ($this->logRequest()) {
                Mage::log('Order ' . $order->getId() . ' held', null, 'signifyd_connect.log');
            }
        }
    }
    
    public function invoiceOrder($order)
    {
        if ($order && $order->getId() && $order->canInvoice() && $this->canInvoice()) {
            $items = array();
            foreach ($order->getAllItems() as $item) {
                $items[$item->getId()] = $item->getQtyOrdered();
            }
            
            $invoice_api = Mage::getModel('sales/order_invoice_api');
            
            try {
                $invoice_id = $invoice_api->create($order->getIncrementId(), $items, null, false, true);
                
                $invoice_api->capture($invoice_id);
            } catch (Exception $e) {
                if ($this->logErrors()) {
                    Mage::log('Exception while creating invoice: ' . $e->__toString(), null, 'signifyd_connect.log');
                }
            }
        }
    }
    
    public function processAdditional($case, $original_status=false)
    {
        if ($this->canHold() || $this->canInvoice()) {
            $order = $this->_order;
            $held = false;
            
            if ($order && $order->getId() && $this->canHold()) {
                $threshold = $this->holdThreshold();
                
                if (!$original_status || $original_status == 'PENDING') {
                    if ($threshold && $case->getScore() <= $threshold) {
                        $this->holdOrder($order);
                        $held = true;
                    }
                } else if ($original_status) {
                    if ($this->_request['reviewDisposition'] == 'FRAUDULENT') {
                        if ($order->canHold()) {
                            $order->hold();
                            $order->save();
                            
                            if ($this->logRequest()) {
                                Mage::log('Order ' . $order->getId() . ' held', null, 'signifyd_connect.log');
                            }
                        }
                    } else if ($this->_request['reviewDisposition'] == 'GOOD') {
                        if ($order->canUnhold()) {
                            $order->unhold();
                            $order->save();
                            
                            if ($this->logRequest()) {
                                Mage::log('Order ' . $order->getId() . ' unheld', null, 'signifyd_connect.log');
                            }
                        }
                    }
                }
            }
            
            if ($order && $order->getId() && $this->canInvoice() && !$held && !$original_status) {
                $this->invoiceOrder($order);
            }
        }
    }
    
    public function processCreation()
    {
        $case = $this->_case;
        
        if (!$case) {
            return;
        }
        
        if (isset($this->_request['score'])) {
            $case->setScore($this->_request['score']);
            
            if ($this->logRequest()) {
                Mage::log('Set score to ' . $this->_request['score'], null, 'signifyd_connect.log');
            }
        } else {
            if ($this->logRequest()) {
                Mage::log('No score value available', null, 'signifyd_connect.log');
            }
        }
        
        if (isset($this->_request['status'])) {
            $case->setSignifydStatus($this->_request['status']);
            
            if ($this->logRequest()) {
                Mage::log('Set status to ' . $this->_request['status'], null, 'signifyd_connect.log');
            }
        } else {
            if ($this->logRequest()) {
                Mage::log('No status value available', null, 'signifyd_connect.log');
            }
        }
        
        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
        
        if ($this->logRequest()) {
            Mage::log('Case ' . $case->getId() . ' created with status ' . $case->getSignifydStatus() . ' and score ' . $case->getScore(), null, 'signifyd_connect.log');
        }
        
        $this->processAdditional($case);
    }
    
    public function processReview()
    {
        $case = $this->_case;
        
        if (!$case) {
            return;
        }
        
        $original_status = $case->getSignifydStatus();
        
        if (isset($this->_request['score'])) {
            $case->setScore($this->_request['score']);
            
            if ($this->logRequest()) {
                Mage::log('Set score to ' . $this->_request['score'], null, 'signifyd_connect.log');
            }
        } else {
            if ($this->logRequest()) {
                Mage::log('No score value available', null, 'signifyd_connect.log');
            }
        }
        
        if (isset($this->_request['status'])) {
            $case->setSignifydStatus($this->_request['status']);
            
            if ($this->logRequest()) {
                Mage::log('Set status to ' . $this->_request['status'], null, 'signifyd_connect.log');
            }
        } else {
            if ($this->logRequest()) {
                Mage::log('No status value available', null, 'signifyd_connect.log');
            }
        }
        
        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
        
        $this->processAdditional($case, $original_status);
    }
    
    public function caseLookup()
    {
        $result = false;
        $case = $this->_case;
        
        try {
            $url = $this->getUrl($case->getCode());
            
            $response = Mage::helper('signifyd_connect')->request($url, null, $this->getApiKey(), null, 'application/json');
            
            $response_code = $response->getHttpCode();
            
            if (substr($response_code, 0, 1) == '2') {
                $result = json_decode($response->getRawResponse(), true);
            } else {
                if ($this->logRequest()) {
                    Mage::log('Fallback request received a ' . $response_code . ' response from Signifyd', null, 'signifyd_connect.log');
                }
            }
        } catch (Exception $e) {
            if ($this->logErrors()) {
                Mage::log('Fallback issue: ' . $e->__toString(), null, 'signifyd_connect.log');
            }
        }
        
        return $result;
    }
    
    public function processFallback($request)
    {
        if ($this->logRequest()) {
            Mage::log('Attempting auth via fallback request', null, 'signifyd_connect.log');
        }
        
        $request = json_decode($request, true);
        
        $this->_topic = "cases/review"; // Topic header is most likely not available
        
        if (is_array($request) && isset($request['orderId'])) {
            $cases = Mage::getModel('signifyd_connect/case')->getCollection();
            $cases->addFieldToFilter('order_increment', $request['orderId']);
            
            foreach ($cases as $case) {
                $this->_case = $case;
                break;
            }
            
            /*
            // Do not create cases if not found -- potential concurrency issues
            if (!$this->_case && isset($request['investigationId'])) {
                $case = Mage::getModel('signifyd_connect/case');
                $case->setOrderIncrement($request['orderId']);
                $case->setCode($request['investigationId']);
                $case->setCreated(strftime('%Y-%m-%d %H:%M:%S', time()));
                $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                
                $this->_case = $case;
            }
            */
        }
        
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($this->_request['orderId']);
        
        if ($this->_order && $this->_order->getId()) {
            $this->_store_id = $this->_order->getStoreId();
        }
        
        if ($this->_case) {
            $lookup = $this->caseLookup();
            
            if ($lookup && is_array($lookup)) {
                $this->_request = $lookup;
                
                $this->processReview();
            } else {
                if ($this->logRequest()) {
                    Mage::log('Fallback failed with an invalid response from Signifyd', null, 'signifyd_connect.log');
                }
            }
        } else {
            if ($this->logRequest()) {
                Mage::log('Fallback failed with no matching case found', null, 'signifyd_connect.log');
            }
        }
    }
    
    public function getHeader($header)
    {
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (isset($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }
        
        return '';
    }
    
    public function apiAction()
    {
        if (!$this->enabled()) {
            echo $this->getDisabledMessage();
            
            return;
        }
        
        $request = $this->getRawPost();
        
        $hash = $this->getHeader('HTTP_X_SIGNIFYD_HMAC_SHA256');
        
        if ($this->logRequest()) {
            Mage::log('API request: ' . $request, null, 'signifyd_connect.log');
            Mage::log('API request hash: ' . $hash, null, 'signifyd_connect.log');
        }
        
        if ($request) {
            if ($this->validRequest($request, $hash)) {
                $this->initRequest($request);
                
                $topic = $this->_topic;
                
                if ($this->logRequest()) {
                    Mage::log('API request topic: ' . $topic, null, 'signifyd_connect.log');
                }
                
                switch ($topic) {
                    case "cases/creation":
                        try {
                            $this->processCreation($request);
                        } catch (Exception $e) {
                            if ($this->logErrors()) {
                                Mage::log('Case scoring issue: ' . $e->__toString(), null, 'signifyd_connect.log');
                            }
                        }
                        break;
                    case "cases/rescore":
                    case "cases/review":
                        try {
                            $this->processReview($request);
                        } catch (Exception $e) {
                            if ($this->logErrors()) {
                                Mage::log('Case review issue: ' . $e->__toString(), null, 'signifyd_connect.log');
                            }
                        }
                        break;
                    default:
                        $this->unsupported();
                }
            } else {
                if ($this->logRequest()) {
                    Mage::log('API request failed auth', null, 'signifyd_connect.log');
                }
                
                $this->processFallback($request);
            }
        } else {
            echo $this->getDefaultMessage();
        }
        
        $this->complete();
    }
}