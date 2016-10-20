<?php
/**
 * Connect Controller
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */
class Signifyd_Connect_ConnectController extends Mage_Core_Controller_Front_Action
{
    public $_request = array();
    public $_topic = false;
    public $_order = false;
    public $_store_id = null;
    public $_case = false;
    public $_previousGuarantee = false;
    public $_previousScore = false;
    public $_unholdRetries = 0;

    const WAITING_SUBMISSION_STATUS     = "waiting_submission";
    const IN_REVIEW_STATUS              = "in_review";
    const PROCESSING_RESPONSE_STATUS    = "processing_response";
    const COMPLETED_STATUS              = "completed";

    public function getApiKey()
    {
        return Mage::getStoreConfig('signifyd_connect/settings/key');
    }

    public function getAcceptedFromGuaranty(){
        return Mage::getStoreConfig('signifyd_connect/advanced/accepted_from_guaranty', $this->_store_id);
    }

    public function getDeclinedFromGuaranty(){
        return Mage::getStoreConfig('signifyd_connect/advanced/declined_from_guaranty', $this->_store_id);
    }

    public function enabled()
    {
        return Mage::getStoreConfig('signifyd_connect/settings/enabled');
    }

    public function getUrl($code)
    {
//        return Mage::getStoreConfig('signifyd_connect/settings/url', $this->_store_id) . '/cases/' . $code;
        return 'https://api.signifyd.com/v2/cases/' . $code;
    }

    public function logErrors()
    {
        return Mage::getStoreConfig('signifyd_connect/log/all');
    }

    public function logRequest()
    {
        return Mage::getStoreConfig('signifyd_connect/log/all');
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
        try {
            if (isset($this->_request['guaranteeDisposition'])) {
                $case->setGuarantee($this->_request['guaranteeDisposition']);
                $case->setMagentoStatus(self::PROCESSING_RESPONSE_STATUS);

                if ($this->logRequest()) {
                    Mage::log('Set guarantee to ' . $this->_request['guaranteeDisposition'], null,
                        'signifyd_connect.log');
                }
            }
        } catch(Exception $e) {
            if ($this->logErrors()) {
                Mage::log('ERROR ON WEBHOOK: ' . $e->__toString(), null, 'signifyd_connect.log');
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

    public function processAdditional($case, $original_status = false,$custom_order = null)
    {
        if ($custom_order)
            $order = $custom_order;
        else
            $order = $this->_order;

        if ($order && $order->getId()) {
            $positiveAction = $this->getAcceptedFromGuaranty();
            $negativeAction = $this->getDeclinedFromGuaranty();
            $newGuarantee = null;
            try{
                if ($custom_order)
                    $newGuarantee = $case['guarantee'];
                else
                    $newGuarantee = isset($this->_request ['guaranteeDisposition']) ? $this->_request ['guaranteeDisposition'] : null;
            } catch(Exception $e){
                if ($this->logErrors()) {
                    Mage::log('ERROR ON WEBHOOK: ' . $e->__toString(), null, 'signifyd_connect.log');
                }
            }
            // If a guarantee has been set, we no longer care about other actions
            if (isset($newGuarantee) && $newGuarantee != $this->_previousGuarantee) {
                // Loading the signifyd order model
                $orderModel = Mage::getModel('signifyd_connect/order');
                if ($newGuarantee == 'DECLINED' ) {
                    if ($negativeAction == 1) {
                        // this is for when config is set to keep order on hold
                        $orderModel->keepOrderOnHold($order, "guarantee declined");
                        $orderModel->finalStatus($order, 1, $case);
                    } else if ($negativeAction == 2) {
                        // this is for when config is set to cancel close order
                        // $orderModel->cancelCloseOrder($order, "guarantee declined");
                        $orderModel->finalStatus($order, 2, $case);
                    } else {
                        // this is when the config is not set or it is set to something unknown
                        Mage::log("Unknown action $negativeAction", null, 'signifyd_connect.log');
                    }
                } else if ($newGuarantee == 'APPROVED') {
                    if ($positiveAction == 1) {
                        // this is for when config is set to unhold order
                        $orderModel->unholdOrder($order, "guarantee approved");
                        $orderModel->finalStatus($order, 2, $case);
                    } elseif($positiveAction == 2){
                        // this is for when config is set to unhold, invoice and capture
                        // $orderModel->unholdOrderAndCapture($order, "guarantee approved");
                        $orderModel->finalStatus($order, 2, $case);
                    } else {
                        // this is when the config is not set or it is set to something unknown
                        Mage::log("Unknown action $positiveAction", null, 'signifyd_connect.log');
                    }
                }
                // add else for unknown guarantee
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
        $this->updateGuarantee($case);

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
        $this->updateGuarantee($case);

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

    /**
     * Main entry point for the signifyd callback
     */
    public function apiAction()
    {
        if (!$this->enabled()) {
            echo $this->getDisabledMessage();
            return;
        }

        // Prevent recurring on save
        if(is_null(Mage::registry('signifyd_action'))){
            Mage::register('signifyd_action', 1);
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

    public function cronAction()
    {
        Mage::getModel('signifyd_connect/cron')->retry();
    }

}

/* Filename: ConnectController.php */
/* Location: ../app/code/Community/Signifyd/Connect/controllers/ConnectController.php */