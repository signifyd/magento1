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
    /** @var Signifyd_Connect_Helper_Log */
    protected $logger;

    /** @var Signifyd_Connect_Helper_Data */
    protected $helper;

    /**
     * @return Signifyd_Connect_Helper_Data
     */
    public function getHelper()
    {
        if (!$this->helper instanceof Signifyd_Connect_Helper_Data) {
            $this->helper = Mage::helper('signifyd_connect');
        }

        return $this->helper;
    }

    /**
     * @return Signifyd_Connect_Helper_Log
     */
    public function getLogger()
    {
        if (!$this->logger instanceof Signifyd_Connect_Helper_Log) {
            $this->logger = Mage::helper('signifyd_connect/log');
        }

        return $this->logger;
    }

    /**
     * Method to reprocessing the cases than did not fully complete
     */
    public function retry()
    {
        $this->getLogger()->addLog("Main retry method called");
        
        // Getting all the cases that were not submitted to Signifyd
        $casesForResubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::WAITING_SUBMISSION_STATUS);
        /** @var Signifyd_Connect_Model_Case $currentCase */
        foreach ($casesForResubmit->getItems() as $currentCase) {
            $currentOrderId = $currentCase->getData('order_increment');
            /** @var Mage_Sales_Model_Order $currentOrder */
            $currentOrder = Mage::getModel('sales/order')->loadByIncrementId($currentOrderId);

            $this->getLogger()->addLog("Preparing for send case no: {$currentOrderId}");
            $this->getLogger()->addLog("Order {$currentOrderId} state: {$currentOrder->getState()}, event: cron retry");

            $this->getHelper()->buildAndSendOrderToSignifyd($currentOrder, true);
        }

        // Getting all the cases that are awaiting review from Signifyd
        $casesForResubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::IN_REVIEW_STATUS);
        /** @var Signifyd_Connect_Model_Case $currentCase */
        foreach ($casesForResubmit->getItems() as $currentCase) {
            $this->getLogger()->addLog('Preparing for review case no: ' . $currentCase->getData('order_increment'));
            $this->processInReviewCase($currentCase);
        }

        // Getting all the cases that need processing after the response was received
        $casesForResubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::PROCESSING_RESPONSE_STATUS);
        /** @var Signifyd_Connect_Model_Case $currentCase */
        foreach ($casesForResubmit->getItems() as $currentCase) {
            $currentOrderId = $currentCase->getData('order_increment');
            /** @var Mage_Sales_Model_Order $currentOrder */
            $currentOrder = Mage::getModel('sales/order')->loadByIncrementId($currentOrderId);

            $this->getLogger()->addLog("Preparing for response processing of case no: {$currentOrderId}");
            $this->getLogger()->addLog("Order {$currentOrderId} state: {$currentOrder->getState()}, event: cron retry");

            Mage::getModel('signifyd_connect/case')->processAdditional($currentCase->getData(), $currentOrder);
        }

        $this->getLogger()->addLog("Main retry method ended");
        
        return;
    }

    /**
     * Getting the retry cases by the status of the case
     * 
     * @param $status
     * @return Signifyd_Connect_Model_Resource_Case_Collection
     */
    public function getRetryCasesByStatus($status)
    {
        $time = time();
        $lastTime = $time -  60*60*24*7; // not longer than 7 days
        $firstTime = $time -  60*30; // longer than last 30 minuted
        $from = date('Y-m-d H:i:s', $lastTime);
        $to = date('Y-m-d H:i:s', $firstTime);

        /** @var Signifyd_Connect_Model_Resource_Case_Collection $casesCollection */
        $casesCollection = Mage::getModel('signifyd_connect/case')->getCollection();
        $casesCollection->addFieldToFilter('updated', array('from' => $from, 'to' => $to));
        $casesCollection->addFieldToFilter('magento_status', $status);

        return $casesCollection;
    }

    /**
     * Process the cases that are in review
     * 
     * @param Signifyd_Connect_Model_Case $case
     * @return bool
     */
    public function processInReviewCase(Signifyd_Connect_Model_Case $case)
    {
        $code = $case->getData('code');

        if (empty($code)) {
            return false;
        }

        $this->getLogger()->addLog('Process in review case: ' . $code);
        $caseUrl = $this->getHelper()->getCaseUrl($code);
        $auth = $auth = $this->getHelper()->getConfigData('settings/key', $case);
        $response = $this->getHelper()->request($caseUrl, null, $auth, 'application/json');

        try {
            $responseCode = $response->getHttpCode();

            if (substr($responseCode, 0, 1) == '2') {
                $responseData = $response->getRawResponse();
                Mage::getModel('signifyd_connect/case')->processFallback($responseData);
                return true;
            }
        } catch (Exception $e) {
            $this->getLogger()->addLog($e->__toString());
            return false;
        }

        return true;
    }

}