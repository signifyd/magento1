<?php

class Signifyd_Connect_ConnectController extends Mage_Core_Controller_Front_Action
{
    public $_request = array();
    public $_topic = false;
    public $_order = false;
    public $_store_id = null;
    public $_case = false;
    public $_previousGuarantee = false;
    public $_previousScore = false;

    public function getApiKey()
    {
        return Mage::getStoreConfig('signifyd_connect/settings/key');
    }

    public function holdThreshold()
    {
        return (int)Mage::getStoreConfig('signifyd_connect/advanced/hold_orders_threshold', $this->_store_id);
    }

    public function canReviewHold()
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

    public function negativeGuaranteeAction()
    {
        return Mage::getStoreConfig('signifyd_connect/advanced/guarantee_negative_action', $this->_store_id);
    }

    public function positiveGuaranteeAction()
    {
        return Mage::getStoreConfig('signifyd_connect/advanced/guarantee_positive_action', $this->_store_id);
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
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1', '403 Forbidden')
            ->sendResponse();
        echo 'This request type is currently unsupported';
        exit;
    }

    public function complete()
    {
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1', '200 Ok')
            ->sendResponse();

        exit;
    }

    public function conflict()
    {
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1', '409 Conflict')
            ->sendResponse();
        exit;
    }

    private function updateScore($case)
    {
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
    }

    private function updateStatus($case)
    {
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
    }

    private function updateGuarantee($case)
    {
        if (isset($this->_request['guaranteeDisposition'])) {
            $case->setGuarantee($this->_request['guaranteeDisposition']);

            if ($this->logRequest()) {
                Mage::log('Set guarantee to ' . $this->_request['guaranteeDisposition'], null, 'signifyd_connect.log');
            }
        } else {
            if ($this->logRequest()) {
                Mage::log('No guarantee available', null, 'signifyd_connect.log');
            }
        }
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
        else if ($this->getHeader('X-SIGNIFYD-TOPIC') == "cases/test"){
            // In the case that this is a webhook test, the encoding ABCDE is allowed
            $check = base64_encode(hash_hmac('sha256', $request, 'ABCDE', true));
            if ($check == $hash) {
                return true;
            }
        }

        return false;
    }

    public function initCase($order_increment)
    {
        $case = false;
        
        if (isset($this->_request['orderId']))
        {
            $case = Mage::getModel('signifyd_connect/case')->load($this->_request['orderId']);
            if($case->isObjectNew()) {
                if ($this->logErrors()) {
                    Mage::log('Case not yet in DB. Likely timing issue. order_increment: ' . $this->_request['orderId'], null, 'signifyd_connect.log');
                }
                $this->conflict();
            }
        }

        return $case;
    }

    public function initRequest($request)
    {
        $this->_request = json_decode($request, true);

        $topic = $this->getHeader('X-SIGNIFYD-TOPIC');

        $this->_topic = $topic;

        // For the webhook test, all of the request data will be invalid
        if ($topic == "cases/test") return;

        $this->_case = $this->initCase($this->_request['orderId']);
        $this->_previousGuarantee = $this->_case->getGuarantee();
        $this->_previousScore = $this->_case->getScore();

        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($this->_request['orderId']);

        if ($this->_order && $this->_order->getId()) {
            $this->_store_id = $this->_order->getStoreId();
        }

        if (!$this->_case && $this->logRequest()) {
            Mage::log('No matching case was found for this request. order_increment: ' . $this->_request['orderId'], null, 'signifyd_connect.log');
        }
    }

    public function holdOrder($order, $reason)
    {
        if ($order && $order->getId() && $order->canHold()) {
            $order->hold();
            $order->addStatusHistoryComment("Signifyd: order held because $reason");
            $order->save();

            if ($this->logRequest()) {
                Mage::log('Order ' . $order->getId() . ' held', null, 'signifyd_connect.log');
            }
        }
    }

    public function unholdOrder($order, $reason)
    {
        if ($order && $order->getId() && $order->canUnhold()) {
            $order->unhold();
            $order->addStatusHistoryComment("Signifyd: order unheld because $reason");
            $order->save();

            if ($this->logRequest()) {
                Mage::log('Order ' . $order->getId() . ' unheld', null, 'signifyd_connect.log');
            }
        }
    }

    public function cancelOrder($order, $reason)
    {
        if ($order && $order->getId() && $order->canCancel()) {
            $order->cancel();
            $order->addStatusHistoryComment("Signifyd: order canceled because $reason");
            $order->save();

            if ($this->logRequest()) {
                Mage::log('Order ' . $order->getId() . ' cancelled', null, 'signifyd_connect.log');
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

    public function processAdditional($case, $original_status = false)
    {
        $order = $this->_order;

        if ($order && $order->getId()) {
            $threshold = $this->holdThreshold();

            $negativeAction = $this->negativeGuaranteeAction();
            $positiveAction = $this->positiveGuaranteeAction();
            $newGuarantee = $this->_request ['guaranteeDisposition'];
            $newScore = $case->getScore();

            // If a guarantee has been set, we no longer care about other actions
            if (isset($newGuarantee) && $newGuarantee != $this->_previousGuarantee) {
                if ($newGuarantee == 'DECLINED' && $negativeAction != 'nothing') {
                    if ($negativeAction == 'hold') {
                        $this->holdOrder($order, "guarantee declined");
                    } else if ($negativeAction == 'cancel') {
                        $this->cancelOrder($order, "guarantee declined");
                    } else {
                        Mage::log("Unknown action $negativeAction", null, 'signifyd_connect.log');
                    }
                } else if ($newGuarantee == 'APPROVED' && $positiveAction != 'nothing') {
                    if ($positiveAction == 'unhold') {
                        $this->unholdOrder($order, "guarantee approved");
                    } else {
                        Mage::log("Unknown action $positiveAction", null, 'signifyd_connect.log');
                    }
                }
            } else if($this->_previousGuarantee == "N/A") {
                if (!$original_status || $original_status == 'PENDING') {
                    if ($threshold  && $this->_previousScore != $newScore
                                    && $newScore <= $threshold
                                    && $this->canReviewHold())
                    {
                        $this->holdOrder($order, "score below threshold");
                    }
                } else if ($original_status) {
                    if ($this->_request['reviewDisposition'] == 'FRAUDULENT') {
                        if ($this->canReviewHold()) {
                            $this->holdOrder($order, "case review fraudulent");
                        }
                    } else if ($this->_request['reviewDisposition'] == 'GOOD') {
                        if ($this->canReviewHold()) {
                            $this->unholdOrder($order, "case review good");
                        }
                    }
                }
            }
        }
    }

    public function processCreation()
    {
        $case = $this->_case;

        if (!$case) {
            return;
        }

        $this->updateScore($case);
        $this->updateStatus($case);

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

        $this->updateScore($case);
        $this->updateStatus($case);

        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();

        $this->processAdditional($case, $original_status);
    }

    public function processGuarantee()
    {
        $case = $this->_case;

        if (!$case) {
            return;
        }

        $original_status = $case->getSignifydStatus();

        $this->updateScore($case);
        $this->updateStatus($case);
        $this->updateGuarantee($case);

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
        
        if (is_array($request) && isset($request['orderId']))
        {
            $this->_case = Mage::getModel('signifyd_connect/case')->load($request['orderId']);
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
        // T379: Some frameworks add an extra HTTP_ before the header, so check for both names
        // Header values stored in the $_SERVER variable have dashes converted to underscores, hence str_replace
        $direct = strtoupper(str_replace('-', '_', $header));
        $extraHttp = 'HTTP_' . $direct;

        // Check the $_SERVER global
        if (isset($_SERVER[$direct])) {
            return $_SERVER[$direct];
        } else if (isset($_SERVER[$extraHttp])) {
            return $_SERVER[$extraHttp];
        }

        Mage::log('Valid Header Not Found: ' . $header, null, 'signifyd_connect.log');
        return '';

    }

    public function retriesAction()
    {
        Mage::helper('signifyd_connect')->processRetryQueue();
    }

    public function sendAction()
    {
        try {
            // This request handles the send action only if we are using an unsecured
            // connection. Otherwise, it does nothing
            if (!Mage::getStoreConfig('signifyd_connect/settings/enabled') ||
                !Mage::getStoreConfig('signifyd_connect/advanced/use_unsecure_requests')
            ) {
                Mage::log("Attempting to access send endpoint from frontend when it is currently disabled.", null, 'signifyd_connect.log');
                return;
            }
            Mage::helper('signifyd_connect')->bulkSend($this);
        } catch (Exception $e) {
            Mage::log('Exception while sending: ' . $e->__toString(), null, 'signifyd_connect.log');
        }
        $this->_redirectReferer();
    }

    public function apiAction()
    {
        if (!$this->enabled()) {
            echo $this->getDisabledMessage();

            return;
        }

        $request = $this->getRawPost();

        $hash = $this->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');

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
                    case "guarantees/completion":
                        try {
                            $this->processGuarantee($request);
                        } catch (Exception $ex) {
                            if ($this->logErrors()) {
                                Mage::log('Case guarantee issue: ' . $ex->__toString(), null, 'signifyd_connect.log');
                            }
                        }
                        break;
                    case "cases/test":
                        // Test is only verifying that the endpoint is reachable. So we just complete here
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
