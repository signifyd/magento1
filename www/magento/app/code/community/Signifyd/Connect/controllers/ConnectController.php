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
    public $logger;

    /**
     * Getting the row post data
     * @return string
     */
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
        } elseif ($this->getHeader('X-SIGNIFYD-TOPIC') == "cases/test") {
            // In the case that this is a webhook test, the encoding ABCDE is allowed
            $check = base64_encode(hash_hmac('sha256', $request, 'ABCDE', true));
            if ($check == $hash) {
                return true;
            }
        }

        return false;
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
            $this->getResponse()->setBody($this->getDisabledMessage());
            return;
        }

        $this->logger = Mage::helper('signifyd_connect/log');

        $request = $this->getRawPost();
        $hash = $this->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
        $topic = $this->getHeader('X-SIGNIFYD-TOPIC');
        $topic = $this->getHeader('X-SIGNIFYD-TOPIC');

        $this->logger->addLog('API request: ' . $request);
        $this->logger->addLog('API request hash: ' . $hash);
        $this->logger->addLog('API request topic: ' . $topic);

        if ($request) {
            if ($this->validRequest($request, $hash)) {
                // Test is only verifying that the endpoint is reachable. So we just complete here
                if ($topic == 'cases/test') {
                    return;
                }

                $requestJson = json_decode($request, true);
                if (empty($requestJson) || !isset($requestJson['orderId'])) {
                    return;
                }

                /** @var Signifyd_Connect_Model_Case $case */
                $case = Mage::getModel('signifyd_connect/case')->load($requestJson['orderId']);

                if ($case->isObjectNew()) {
                    $this->logger->addLog('Case not yet in DB. Likely timing issue. order_increment: ' . $requestJson['orderId']);
                    $this->getResponse()->setHttpResponseCode(409);
                    return;
                }

                // Prevent recurring on save
                if (is_null(Mage::registry('signifyd_action_' . $requestJson['orderId']))) {
                    Mage::register('signifyd_action_' . $requestJson['orderId'], 1);
                }

                switch ($topic) {
                    case "cases/creation":
                        Mage::getModel('signifyd_connect/case')->processCreation($case, $requestJson);
                        break;
                    case "cases/rescore":
                    case "cases/review":
                        Mage::getModel('signifyd_connect/case')->processReview($case, $requestJson);
                        break;
                    case "guarantees/completion":
                        Mage::getModel('signifyd_connect/case')->processGuarantee($case, $requestJson);
                        break;
                    default:
                        $this->getResponse()->setHttpResponseCode(403);
                        $this->getResponse()->setBody('This request type is currently unsupported');
                }
            } else {
                $this->logger->addLog('API request failed auth');
                Mage::getModel('signifyd_connect/case')->processFallback($request);
            }
        } else {
            $this->getResponse()->setBody($this->getDefaultMessage());
        }
    }

    /**
     * Manually start the processing made by the cron job
     */
    public function cronAction()
    {
        Mage::getModel('signifyd_connect/cron')->retry();
    }

}