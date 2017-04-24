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
    public $_case = false;
    public $logger;

    /**
     * Getting the row post data
     * @return string
     */
    public function getRawPost()
    {
        if (isset($HTTP_RAW_POST_DATA) && $HTTP_RAW_POST_DATA)
            return $HTTP_RAW_POST_DATA;

        $post = file_get_contents("php://input");
        if ($post)
            return $post;

        return '';
    }

    /**
     * Getting the default message for the API
     * @return string
     */
    public function getDefaultMessage()
    {
        return 'This URL is working! Please copy & paste the current URL into your <a href="https://signifyd.com/settings">settings</a> page in the Notifications section';
    }

    /**
     * Getting the disabled message for the extension
     * @return string
     */
    public function getDisabledMessage()
    {
        return 'This URL is disabled in the Magento admin panel! Please enable score retrieval under Admin>System>Config>Signifyd Connect>Advanced before setting this url in your Signifyd <a href="https://signifyd.com/settings">settings</a> page.';
    }

    /**
     * Returning response as unsupported
     */
    public function unsupported()
    {
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1', '403 Forbidden')
            ->sendResponse();
        echo 'This request type is currently unsupported';
        exit;
    }

    /**
     * Returning response as completed
     */
    public function complete()
    {
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1', '200 Ok')
            ->sendResponse();

        exit;
    }

    /**
     * Returning response as conflict
     */
    public function conflict()
    {
        Mage::app()->getResponse()
            ->setHeader('HTTP/1.1', '409 Conflict')
            ->sendResponse();
        exit;
    }

    /**
     * Checking if the received request is valid
     * @param $request
     * @param $hash
     * @return bool
     */
    public function validRequest($request, $hash)
    {
        $check = base64_encode(hash_hmac('sha256', $request, Mage::helper('signifyd_connect')->getAuth(), true));
        $this->logger->addLog('API request hash check: ' . $check);

        if ($check == $hash) {
            return true;
        } else if ($this->getHeader('X-SIGNIFYD-TOPIC') == "cases/test"){
            // In the case that this is a webhook test, the encoding ABCDE is allowed
            $check = base64_encode(hash_hmac('sha256', $request, 'ABCDE', true));
            if ($check == $hash)
                return true;
        }

        return false;
    }

    /**
     * Initializing the Signifyd Case
     * @return bool|Mage_Core_Model_Abstract
     */
    public function initCase()
    {
        $case = false;
        
        if (isset($this->_request['orderId'])) {
            $case = Mage::getModel('signifyd_connect/case')->load($this->_request['orderId']);
            if($case->isObjectNew()) {
                $this->logger->addLog('Case not yet in DB. Likely timing issue. order_increment: ' . $this->_request['orderId']);
                $this->conflict();
            }
        }

        return $case;
    }

    /**
     * Initializing the class params
     * @param $request
     */
    public function initRequest($request)
    {
        $this->_request = json_decode($request, true);

        $topic = $this->getHeader('X-SIGNIFYD-TOPIC');

        $this->_topic = $topic;

        // For the webhook test, all of the request data will be invalid
        if ($topic == "cases/test") return;

        $this->_case = $this->initCase();
        if (!$this->_case)
            $this->logger->addLog('No matching case was found for this request. order_increment: ' . $this->_request['orderId']);
    }

    /**
     * Retrieving the header
     * @param $header
     * @return string
     */
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

        $this->logger->addLog('Valid Header Not Found: ' . $header);
        return '';

    }

    /**
     * Main entry point for the signifyd callback
     */
    public function apiAction()
    {
        if (!Mage::helper('signifyd_connect')->isEnabled()) {
            echo $this->getDisabledMessage();
            return;
        }

        $this->logger = Mage::helper('signifyd_connect/log');

        // Prevent recurring on save
        if(is_null(Mage::registry('signifyd_action')))
            Mage::register('signifyd_action', 1);

        $request = $this->getRawPost();

        $hash = $this->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');

        $this->logger->addLog('API request: ' . $request);
        $this->logger->addLog('API request hash: ' . $hash);

        if ($request) {
            if ($this->validRequest($request, $hash)) {
                $this->initRequest($request);

                $topic = $this->_topic;
                $this->logger->addLog('API request topic: ' . $topic);

                switch ($topic) {
                    case "cases/creation":
                        Mage::getModel('signifyd_connect/case')->processCreation($this->_case, $this->_request);
                        break;
                    case "cases/rescore":
                    case "cases/review":
                        Mage::getModel('signifyd_connect/case')->processReview($this->_case, $this->_request);
                        break;
                    case "guarantees/completion":
                        Mage::getModel('signifyd_connect/case')->processGuarantee($this->_case, $this->_request);
                        break;
                    case "cases/test":
                        // Test is only verifying that the endpoint is reachable. So we just complete here
                        break;

                    default:
                        $this->unsupported();
                }
            } else {
                $this->logger->addLog('API request failed auth');
                Mage::getModel('signifyd_connect/case')->processFallback($this->_request);
            }
        } else {
            echo $this->getDefaultMessage();
        }

        $this->complete();
    }

    /**
     * Manually start the processing made by the cron job
     */
    public function cronAction()
    {
        Mage::getModel('signifyd_connect/cron')->retry();
    }

}

/* Filename: ConnectController.php */
/* Location: ../app/code/Community/Signifyd/Connect/controllers/ConnectController.php */