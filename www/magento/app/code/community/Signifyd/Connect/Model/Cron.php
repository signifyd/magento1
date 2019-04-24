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
        /** @var Signifyd_Connect_Model_Case $case */
        foreach ($casesForResubmit as $case) {
            $this->getLogger()->addLog("Send {$case->getOrderIncrement()}, retry {$case->getRetries()}", $case);
            $this->getLogger()->addLog("Order state: {$case->getOrder()->getState()}, event: cron retry", $case);

            $this->getHelper()->buildAndSendOrderToSignifyd($case->getOrder(), true);
        }

        // Getting all the cases that are awaiting review from Signifyd
        $casesForResubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::IN_REVIEW_STATUS);
        /** @var Signifyd_Connect_Model_Case $case */
        foreach ($casesForResubmit as $case) {
            $this->getLogger()->addLog("Review {$case->getOrderIncrement()}, retry {$case->getRetries()}", $case);
            $this->processInReviewCase($case);
        }

        // Getting all the cases that need processing after the response was received
        $casesForResubmit = $this->getRetryCasesByStatus(Signifyd_Connect_Model_Case::PROCESSING_RESPONSE_STATUS);
        /** @var Signifyd_Connect_Model_Case $case */
        foreach ($casesForResubmit as $case) {
            $this->getLogger()->addLog("Process response for {$case->getOrderIncrement()}, retry {$case->getRetries()}", $case);
            $this->getLogger()->addLog("Order state: {$case->getOrder()->getState()}, event: cron retry", $case);

            Mage::getModel('signifyd_connect/case')->processAdditional($case);
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
        $retryTimes = $this->calculateRetryTimes();

        $time = time();
        $lastTime = $time - (end($retryTimes) + 60*60*24);
        $current = date('Y-m-d H:i:s', $time);
        $from = date('Y-m-d H:i:s', $lastTime);

        /** @var Signifyd_Connect_Model_Resource_Case_Collection $casesCollection */
        $casesCollection = Mage::getModel('signifyd_connect/case')->getCollection();
        $casesCollection->addFieldToFilter('updated', array('gteq' => $from));
        $casesCollection->addFieldToFilter('magento_status', array('eq' => $status));
        $casesCollection->addFieldToFilter('retries', array('lt' => count($retryTimes)));
        $casesCollection->addExpressionFieldToSelect('seconds_after_update',
            "TIME_TO_SEC(TIMEDIFF('{$current}', updated))", array('updated'));

        $casesToRetry = array();

        foreach ($casesCollection->getItems() as $case) {
            $retries = $case->getRetries();
            $secondsAfterUpdate = $case->getData('seconds_after_update');

            if ($secondsAfterUpdate > $retryTimes[$retries]) {
                $casesToRetry[$case->getId()] = $case;
                $case->setData('retries', $retries+1);
                $case->save();
            }
        }

        return $casesToRetry;
    }

    /**
     * Retry times calculated from last update
     *
     * @return array
     */
    public function calculateRetryTimes()
    {
        $retryTimes = array();

        for ($retry = 0; $retry < 15; $retry++) {
            // Increment retry times exponentially
            $retryTimes[$retry] = 20 * pow(2, $retry);
            // Increment should not be greater than one day
            $retryTimes[$retry] = $retryTimes[$retry] > 86400 ? 86400 : $retryTimes[$retry];
            // Sum retry time to previous, calculating total time to wait from last update
            $retryTimes[$retry] += isset($retryTimes[$retry-1]) ? $retryTimes[$retry-1] : 0;
        }

        return $retryTimes;
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

        $this->getLogger()->addLog('Process in review case: ' . $code, $case);
        $caseUrl = $this->getHelper()->getCaseUrl($code);
        $auth = $auth = $this->getHelper()->getConfigData('settings/key', $case);
        $response = $this->getHelper()->request($caseUrl, null, $auth, 'application/json', null, false, $case);

        try {
            $responseCode = $response->getHttpCode();

            if (substr($responseCode, 0, 1) == '2') {
                $responseData = $response->getRawResponse();
                Mage::getModel('signifyd_connect/case')->processFallback($responseData);
                return true;
            }
        } catch (Exception $e) {
            $this->getLogger()->addLog($e->__toString(), $case);
            return false;
        }

        return true;
    }

}