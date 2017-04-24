<?php
/**
 * Case Model
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */
class Signifyd_Connect_Model_Case extends Mage_Core_Model_Abstract
{
    /* The log helper */
    protected $logger;

    /* The order related to the case */
    protected $order = false;

    /* The store id of the order related to the case */
    protected $storeId;

    /* The topic of the request */
    protected $topic;

    /* The data helper */
    protected $helper;

    /* The request data */
    protected $_request;

    /* The previous guarantee for the case */
    public $_previousGuarantee = false;

    /* The previous score for the case */
    public $_previousScore = false;

    /* The status when a case is created */
    const WAITING_SUBMISSION_STATUS = "waiting_submission";

    /* The status for a case when the first response from Signifyd is received */
    const IN_REVIEW_STATUS = "in_review";

    /* The status for a case when the case is processing the response */
    const PROCESSING_RESPONSE_STATUS = "processing_response";

    /* The status for a case that is completed */
    const COMPLETED_STATUS = "completed";

    protected function _construct()
    {  
        $this->_init('signifyd_connect/case');
        $this->_isPkAutoIncrement = false;
        $this->logger = Mage::helper('signifyd_connect/log');
        $this->helper = Mage::helper('signifyd_connect');
    }

    public function setMagentoStatusTo($case, $status)
    {
        $id  = (is_array($case))? $case['order_increment'] : $case->getId();
        $caseLoaded = Mage::getModel('signifyd_connect/case')->load($id);
        try {
            $caseLoaded->setMagentoStatus($status);
            $caseLoaded->save();
            $this->logger->addLog("Signifyd: Case no:{$caseLoaded->getId()} status set to {$status}");
        } catch (Exception $e){
            $this->logger->addLog("Signifyd: Error setting case no:{$caseLoaded->getId()} status to {$status}");
            return false;
        }

        return true;
    }

    public function processFallback($request)
    {
        $this->logger->addLog('Attempting auth via fallback request');
        $request = json_decode($request, true);
        $this->_request = $request;
        $this->topic = "cases/review"; // Topic header is most likely not available

        if (is_array($request) && isset($request['orderId']))
            $case = $this->load($request['orderId']);
        else
            return false;

        $this->order = Mage::getModel('sales/order')->loadByIncrementId($request['orderId']);

        if ($this->order && $this->order->getId()) {
            $this->storeId = $this->order->getStoreId();
        }

        if ($case) {
            $lookup = $this->caseLookup($case);
            if ($lookup && is_array($lookup)) {
                $this->processReview($case, $lookup);
            } else {
                $this->logger->addLog('Fallback failed with an invalid response from Signifyd');
            }
        } else {
            $this->logger->addLog('Fallback failed with no matching case found');
        }

        return true;
    }

    public function processReview($case, $request)
    {
        if (!$case) return;
        $this->_request = $request;
        $this->setPrevious($case);
        $this->setOrder();
        $this->logger->addLog('Process review case:' . $case->getId());

        $original_status = $case->getSignifydStatus();

        $case = $this->updateScore($case);
        $case = $this->updateStatus($case);
        $case = $this->updateGuarantee($case);

        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        try {
            $case->save();
            $this->processAdditional($case, $original_status);
        } catch (Exception $e) {
            $this->logger->addLog('Process review error: ' . $e->__toString());
        }

    }

    public function processAdditional($case, $original_status = false, $custom_order = null)
    {
        $this->logger->addLog('Process additional case: ' . $case['order_increment']);
        if ($custom_order)
            $order = $custom_order;
        else
            $order = $this->order;

        if ($order && $order->getId()) {
            $positiveAction = $this->helper->getAcceptedFromGuaranty($order->getStoreId());
            $negativeAction = $this->helper->getDeclinedFromGuaranty($order->getStoreId());
            $newGuarantee = null;
            try{
                if ($custom_order)
                    $newGuarantee = $case['guarantee'];
                else
                    $newGuarantee = isset($this->_request['guaranteeDisposition']) ? $this->_request['guaranteeDisposition'] : null;
            } catch(Exception $e){
                $this->logger->addLog('ERROR ON WEBHOOK: ' . $e->__toString());
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
                        $this->logger->addLog("Unknown action $negativeAction");
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
                        $this->logger->addLog("Unknown action $positiveAction");
                    }
                }
                // add else for unknown guarantee
            }
        }

        return true;
    }

    public function caseLookup($case)
    {
        $result = false;

        try {
            $url = $this->helper->getUrl() . '/' . $case->getCode();
            $response = $this->helper->request($url, null, $this->helper->getAuth(), null, 'application/json');
            $response_code = $response->getHttpCode();
            if (substr($response_code, 0, 1) == '2') {
                $result = json_decode($response->getRawResponse(), true);
            } else {
                $this->logger->addLog('Fallback request received a ' . $response_code . ' response from Signifyd');
            }
        } catch (Exception $e) {
            $this->logger->addLog('Fallback issue: ' . $e->__toString());
        }

        return $result;
    }

    private function updateScore($case)
    {
        if (isset($this->_request['score'])) {
            $case->setScore($this->_request['score']);
            $this->logger->addLog('Set score to ' . $this->_request['score']);
        } else {
            $this->logger->addLog('No score value available');
        }

        return $case;
    }

    private function updateStatus($case)
    {
        if (isset($this->_request['status'])) {
            $case->setSignifydStatus($this->_request['status']);
            $this->logger->addLog('Set status to ' . $this->_request['status']);
        } else {
            $this->logger->addLog('No status value available');
        }

        return $case;
    }

    private function updateGuarantee($case)
    {
        try {
            if (isset($this->_request['guaranteeDisposition'])) {
                $case->setGuarantee($this->_request['guaranteeDisposition']);
                $case->setMagentoStatus(self::PROCESSING_RESPONSE_STATUS);
                $this->logger->addLog('Set guarantee to ' . $this->_request['guaranteeDisposition']);
            }
        } catch(Exception $e) {
            $this->logger->addLog('ERROR ON WEBHOOK: ' . $e->__toString());
        }

        return $case;
    }

    public function processCreation($case, $request)
    {
        if (!$case) return false;
        $this->_request = $request;
        $this->setPrevious($case);
        $this->setOrder();

        $case = $this->updateScore($case);
        $case = $this->updateStatus($case);
        $case = $this->updateGuarantee($case);

        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        try {
            $case->save();
            $this->processAdditional($case);
            $this->logger->addLog('Case ' . $case->getId() . ' created with status ' . $case->getSignifydStatus() . ' and score ' . $case->getScore());
        } catch (Exception $e) {
            $this->logger->addLog('Process creation error: ' . $e->__toString());
            return false;
        }

        return true;
    }

    public function processGuarantee($case, $request)
    {
        if (!$case) return false;
        $this->_request = $request;
        $this->setPrevious($case);
        $this->setOrder();

        $original_status = $case->getSignifydStatus();

        $case = $this->updateScore($case);
        $case = $this->updateStatus($case);
        $case = $this->updateGuarantee($case);

        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        try {
            $case->save();
            $this->processAdditional($case, $original_status);
        } catch (Exception $e) {
            $this->logger->addLog('Process guarantee error: ' . $e->__toString());
            return false;
        }

        return true;
    }

    /**
     * Setting the previous case guarantee and score
     * @param $case
     */
    public function setPrevious($case)
    {
        $this->_previousGuarantee = $case->getGuarantee();
        $this->_previousScore = $case->getScore();
    }

    public function setOrder(){
        if($this->order === false){
            $this->order = Mage::getModel('sales/order')->loadByIncrementId($this->_request['orderId']);
        }
        return;
    }
}

/* Filename: Case.php */
/* Location: ../app/code/Community/Signifyd/Connect/Model/Case.php */