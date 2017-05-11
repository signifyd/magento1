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
    protected $logger;
    protected $helper;

    /**
     * Signifyd_Connect_Model_Cron constructor.
     */
    public function __construct()
    {
        $this->logger = Mage::helper('signifyd_connect/log');
        $this->helper = Mage::helper('signifyd_connect');
    }

    /**
     * Method to reprocessing the cases than did not fully complete
     */
    public function retry()
    {
        $this->logger->addLog("Main retry method called");
        // Getting all the cases that were not submitted to Signifyd
        $cases_for_resubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::WAITING_SUBMISSION_STATUS);
        foreach ($cases_for_resubmit as $current_case) {
            $this->logger->addLog("Signifyd: preparing for send case no: {$current_case['order_increment']}");
            $current_order_id = $current_case['order_increment'];
            $current_order = Mage::getModel('sales/order')->loadByIncrementId($current_order_id);
            Mage::helper('signifyd_connect')->buildAndSendOrderToSignifyd($current_order,true);
        }

        // Getting all the cases that are awaiting review from Signifyd
        $cases_for_resubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::IN_REVIEW_STATUS);
        foreach ($cases_for_resubmit as $current_case) {
            $this->logger->addLog("Signifyd: preparing for review case no: {$current_case['order_increment']}");
            $this->processInReviewCase($current_case);
        }

        // Getting all the cases that need processing after the response was received
        $cases_for_resubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::PROCESSING_RESPONSE_STATUS);
        foreach ($cases_for_resubmit as $current_case) {
            $this->logger->addLog("Signifyd: preparing for response processing of case no: {$current_case['order_increment']}");
            $current_order_id = $current_case['order_increment'];
            $current_order = Mage::getModel('sales/order')->loadByIncrementId($current_order_id);
            Mage::getModel('signifyd_connect/case')->processAdditional($current_case, false, $current_order);
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
     * @return bool
     */
    public function processInReviewCase($case)
    {
        if(empty($case['code'])) return false;

        $this->logger->addLog('Process in review case: ' . $case['code']);
        $case_url = $this->helper->getCaseUrl($case['code']);
        $response = $this->helper->request($case_url,null, $this->helper->getAuth(),'application/json');
        try {
            $response_code = $response->getHttpCode();
            if (substr($response_code, 0, 1) == '2') {
                $response_data = $response->getRawResponse();
                Mage::getModel('signifyd_connect/case')->processFallback($response_data);
                return true;
            }
        } catch (Exception $e) {
            Mage::log($e->__toString(), null, 'signifyd_connect.log');
            return false;
        }

        return true;
    }

}

/* Filename: Cron.php */
/* Location: ../app/code/Community/Signifyd/Connect/Model/Cron.php */