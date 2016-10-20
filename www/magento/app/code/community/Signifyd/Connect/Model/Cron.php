<?php
/**
 * Cron Model
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */
class Signifyd_Connect_Model_Cron
{
    /* The status when a case is created */
    const WAITING_SUBMISSION_STATUS     = "waiting_submission";

    /* The status for a case when the first response from Signifyd is received */
    const IN_REVIEW_STATUS              = "in_review";

    /* The status for a case when the case is processing the response */
    const PROCESSING_RESPONSE_STATUS    = "processing_response";

    /* The status for a case that is completed */
    const COMPLETED_STATUS              = "completed";

    protected $logger;

    public function __construct()
    {
        $this->logger = Mage::helper('signifyd_connect/log');
    }

    /**
     * Method to reprocessing the cases than did not fully complete
     */
    public function retry()
    {
        $this->logger->addLog("Main retry method called");
        // Getting all the cases that were not submitted to Signifyd
        $cases_for_resubmit = $this->getRetryCasesByStatus(self::WAITING_SUBMISSION_STATUS);
        foreach ($cases_for_resubmit as $current_case) {
            $this->logger->addLog("Signifyd: preparing for send case no: {$current_case['order_increment']}");
            $current_order_id = $current_case['order_increment'];
            $current_order = Mage::getModel('sales/order')->loadByIncrementId($current_order_id);
            Mage::helper('signifyd_connect')->buildAndSendOrderToSignifyd($current_order,true);
        }

        // Getting all the cases that are awaiting review from Signifyd
        $cases_for_resubmit = $this->getRetryCasesByStatus(self::IN_REVIEW_STATUS);
        foreach ($cases_for_resubmit as $current_case) {
            $this->logger->addLog("Signifyd: preparing for review case no: {$current_case['order_increment']}");
            $this->processInReviewCase($current_case);
        }

        // Getting all the cases that need processing after the response was received
        $cases_for_resubmit = $this->getRetryCasesByStatus(self::PROCESSING_RESPONSE_STATUS);
        foreach ($cases_for_resubmit as $current_case) {
            $this->logger->addLog("Signifyd: preparing for review case no: {$current_case['order_increment']}");
            $current_order_id = $current_case['order_increment'];
            $current_order = Mage::getModel('sales/order')->loadByIncrementId($current_order_id);
            // need to refactor this
            $this->loadClass();
            $signifyd_controller = @new Signifyd_Connect_ConnectController();
            $signifyd_controller->processAdditional($current_case,false,$current_order);
        }

        $this->logger->addLog("Main retry method ended");
        return;
    }

    /**
     * Getting the retry cases by the status of the case
     * @param $status
     * @return mixed
     */
    public function getRetryCasesByStatus($status)
    {

        $time = time();
        $lastTime = $time -  60*60*24*7; // not longer than 7 days
        $firstTime = $time -  60*30; // longer than last 30 minuted
        $from = date('Y-m-d H:i:s', $lastTime);
        $to = date('Y-m-d H:i:s', $firstTime);

        $cases = Mage::getModel('signifyd_connect/case')->getCollection()
            ->addFieldToFilter('updated', array('from' => $from, 'to' => $to))
            ->addFieldToFilter('magento_status', $status);

        return $cases->getData();

    }

    /**
     * Process the cases that are in review
     * @param $case
     */
    public function processInReviewCase($case)
    {
        $case_url = $this->getCaseUrl($case['code']);
        $response = Mage::helper('signifyd_connect')->request($case_url,null,Mage::helper('signifyd_connect')->getAuth(),'application/json');
        try {
            $response_code = $response->getHttpCode();
            if (substr($response_code, 0, 1) == '2') {
                $response_data = $response->getRawResponse();
                // need to refactor this
                $this->loadClass();
                $signifyd_controller = @new Signifyd_Connect_ConnectController();
                $signifyd_controller->processFallback($response_data);
                return;
            }
        } catch (Exception $e) {
            Mage::log($e->__toString(), null, 'signifyd_connect.log');
        }
    }

    /**
     * Get the cases url
     * @param $case_code
     * @return string
     */
    public function getCaseUrl($case_code)
    {
//        $url = Mage::getStoreConfig('signifyd_connect/settings/url') . '/cases/' . $case_code;
//        return (empty($url))? "https://api.signifyd.com/v2/cases/" . $case_code : $url;
        return 'https://api.signifyd.com/v2/cases/' . $case_code;
    }

    public function loadClass(){
        if(!@class_exists('Signifyd_Connect_ConnectController')) //in case the class already exists
        {
            $dir = Mage::getModuleDir('controllers', 'Signifyd_Connect');
            require_once($dir . '/ConnectController.php');
        }
        return true;
    }
}

/* Filename: Cron.php */
/* Location: ../app/code/Community/Signifyd/Connect/Model/Cron.php */