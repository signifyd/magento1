<?php
/**
 * Order Model
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */
class Signifyd_Connect_Model_Order extends Mage_Core_Model_Abstract
{
    /* Property for instance of the log helper  */
    protected $logger;

    /* Property for instance of the data helper  */
    protected $dataHelper;

    /* Property for configuration */
    protected $logRequest;

    /**
     * Internal magento constructor
     */
    public function _construct()
    {
        $this->logger = Mage::helper('signifyd_connect/log');
        $this->dataHelper = Mage::helper('signifyd_connect');
        $this->logRequest = Mage::getStoreConfig('signifyd_connect/log/request');
        parent::_construct();
    }

    /**
     * Method to authorize an order after order place
     * @param $order
     * @return bool
     */
    public function authorizeOrder($order)
    {
        // Get the payment status for this order
        $status = $this->dataHelper->getOrderPaymentStatus($order);
        if($status['authorize']){
            $this->logger->addLog("Order {$order->getIncrementId()} is already authorize");
            return true;
        }
        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();
        if(!$method->canAuthorize()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be authorize");
            return false;
        }

        $amount = $order->getData('grand_total');

        try{
            // check if method authorize returns anything usefully
            $method->authorize($payment, $amount);
            $this->logger->addLog("Authorize order {$order->getIncrementId()} was successful");
        } catch (Exception $e) {
            $this->logger->addLog("Authorize order {$order->getIncrementId()} was not authorized:");
            $this->logger->addLog($e);
            return false;
        }

        return true;
    }

    /**
     * Method to generate the invoice after a order was placed
     * @param $order
     * @return bool
     */
    public function generateInvoice($order)
    {
        if(!$order->canInvoice()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be invoiced");
            return false;
        }

        try {

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
            $this->logger->addLog("Invoice was created for order: {$order->getIncrementId()}");

            $invoice->addComment('Automatic Invoice', false);
            $invoice->sendEmail();
            $invoice->setEmailSent(true);
            $invoice->save();

            $order->addStatusHistoryComment('Automatic Invoice: '.$invoice->getIncrementId());
            $order->save();
        } catch (Exception $e) {
            $this->logger->addLog('Exception while creating invoice: ' . $e->__toString());
            return false;
        }

        return true;
    }

    /**
     * Method to walk the flow for an order when the config is set to unhold, invoice and capture.
     * When guaranty is approved
     * @param $order
     * @param $reason
     * @return bool
     */
    public function unholdOrderAndCapture($order, $reason)
    {
        if(is_null($order->getId())){
            $this->logger->addLog("Order was not found");
            return false;
        }

        if(!$order->canUnhold()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be unheld");
            return false;
        }

        try {
            $order->unhold();
            $order->addStatusHistoryComment("Signifyd: order unheld because $reason");
            $order->save();

            // Authorize the order
            $this->authorizeOrder($order);

            // Generate the invoice
            $this->generateInvoice($order);

            if ($this->logRequest) {
                $this->logger->addLog("Order {$order->getIncrementId()} unheld because {$reason}");
            }
        } catch (Exception $e) {
            $this->logger->addLog("Order {$order->getIncrementId()} unable to be saved because {$e->getMessage()}");
            $this->logger->addLog("Order {$order->getIncrementId()} was not unheld.");
            return false;
        }

        return true;
    }

    /**
     * Method to walk the flow for an order when the config is set to unhold only.
     * When guaranty is approved
     * @param $order
     * @param $reason
     * @return bool
     */
    public function unholdOrder($order, $reason)
    {
        if(is_null($order->getId())){
            $this->logger->addLog("Order was not found");
            return false;
        }

        if(!$order->canUnhold()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be unheld");
            return false;
        }

        try {
            $order->unhold();
            $order->addStatusHistoryComment("Signifyd: order unheld because $reason");
            $order->save();

            // Authorize the order
            $this->authorizeOrder($order);

            if ($this->logRequest) {
                $this->logger->addLog("Order {$order->getIncrementId()} unheld because {$reason}");
            }
        } catch (Exception $e) {
            $this->logger->addLog("Order {$order->getIncrementId()} unable to be saved because {$e->getMessage()}");
            $this->logger->addLog("Order {$order->getIncrementId()} was not unheld.");
            return false;
        }
        return true;
    }

    /**
     * Method to hold the order whe the guaranty is declined
     * @param $order
     * @param $reason
     * @return bool
     */
    public function holdOrder($order, $reason)
    {
        if(is_null($order->getId())){
            $this->logger->addLog("Order was not found");
            return false;
        }

        if(!$order->canHold()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be held");
            return false;
        }

        try {
            $order->hold();
            $order->addStatusHistoryComment("Signifyd: order held because {$reason}");
            $order->save();

            if ($this->logRequest) {
                $this->logger->addLog("Order {$order->getIncrementId()} held because {$reason}");
            }
        } catch (Exception $e){
            $this->logger->addLog("Order {$order->getIncrementId()} unable to be saved because {$e->getMessage()}");
            $this->logger->addLog("Order {$order->getIncrementId()} was not held.");
            return false;
        }

        return true;
    }

    /**
     * Method to hold the order whe the guaranty is declined and magento config is set to cancel the order
     * @param $order
     * @param $reason
     * @return bool
     */
    public function cancelOrder($order, $reason)
    {
        if(is_null($order->getId())){
            $this->logger->addLog("Order was not found");
            return false;
        }

        if ($order->getState() === $order::STATE_HOLDED) {
            $this->unholdOrder($order, $reason);
        }

        if(!$order->canCancel()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be canceled");
            return false;
        }

        try {
            $order->cancel();
            $order->addStatusHistoryComment("Signifyd: order canceled because $reason");
            $order->save();

            if ($this->logRequest) {
                $this->logger->addLog("Order {$order->getIncrementId()} cancelled because {$reason}");
            }
        } catch (Exception $e){
            $this->logger->addLog("Order {$order->getIncrementId()} unable to be saved because {$e->getMessage()}");
            $this->logger->addLog("Order {$order->getIncrementId()} was not canceled.");
            return false;
        }

        return true;
    }

    public function voidOrderPayment($order, $reason)
    {
        if(is_null($order->getId())){
            $this->logger->addLog("Order was not found");
            return false;
        }
        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();

        if(!$method->canVoid($payment)){
            $this->logger->addLog("Payment for order {$order->getIncrementId()} can not be voided");
            return false;
        }

        try{
            $method->void($payment);
            $order->save();
            $this->cancelOrder($order, $reason);
            $this->logger->addLog("Void order payment {$order->getIncrementId()} was successful");
        } catch (Exception $e) {
            $this->holdOrder($order, 'Signifyd: Failed to void order payment');
            $this->logger->addLog("Void order payment {$order->getIncrementId()} was not authorized:");
            $this->logger->addLog($e->__toString());
            return false;
        }

        return true;


    }

    public function keepOrderOnHold($order, $reason)
    {
        if(is_null($order->getId())){
            $this->logger->addLog("Order was not found");
            return false;
        }
        $this->logger->addLog("Order {$order->getIncrementId()} was kept on hold because {$reason}");

        return true;
    }

    public function cancelCloseOrder($order, $reason)
    {
        $status = $this->dataHelper->getOrderPaymentStatus($order);
        if(is_null($order->getId())){
            $this->logger->addLog("Order was not found");
            return false;
        }

        if(!$order->canUnhold()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be unheld");
            return false;
        }

        try {
            $order->unhold();
            $order->addStatusHistoryComment("Signifyd: order unheld because $reason");
            $order->save();
        } catch (Exception $e){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be unheld");
            return false;
        }

        if($status['authorize'] === true && $status['capture'] === false) {
            $this->voidOrderPayment($order, $reason);
        } elseif($status['authorize'] === true && $status['capture'] === true) {
            $this->canNotCancel($order, $reason);
        } else {
            $this->cancelOrder($order, $reason);
        }

        return true;
    }

    public function checkStatus($order, $status)
    {
        $theOrder = Mage::getModel('sales/order')->load($order->getId());
        return ($theOrder->getStatus() == $status)? true : false;
    }

    public function finalStatus($order, $type, $case)
    {
        try {
            $holdStatus = $this->checkStatus($order, Mage_Sales_Model_Order::STATE_HOLDED);
            if($type == 1){
                // needs to be true
                if($holdStatus === true){
                    Mage::getModel('signifyd_connect/case')->setMagentoStatusTo($case, Signifyd_Connect_Helper_Data::COMPLETED_STATUS);
                }
            } elseif($type == 2) {
                // needs to be false
                if($holdStatus === false){
                    Mage::getModel('signifyd_connect/case')->setMagentoStatusTo($case, Signifyd_Connect_Helper_Data::COMPLETED_STATUS);
                }
            } else {
                $this->logger->addLog('Final status unknown type');
            }
            $this->logger->addLog("Case no:{$case->getId()}, set status to complete successful.");
        } catch (Exception $e){
            $this->logger->addLog("Case no:{$case->getId()}, set status to complete fail: " . $e->__toString());
            return false;
        }

        return true;
    }

    public function canNotCancel($order, $reason)
    {
        try {
            $order->hold();
            $order->addStatusHistoryComment("Signifyd: order held because $reason");
            $order->addStatusHistoryComment("Signifyd: order can not be canceled because it has a sum already captured, Please do a manual refund.");
            $order->save();
            $this->logger->addLog("Order {$order->getIncrementId()} was held because {$reason}");
        } catch (Exception $e){
            $this->logger->addLog("Order {$order->getIncrementId()} could not be held: " . $e->__toString());
            return false;
        }

        return true;
    }

}

/* Filename: Order.php */
/* Location: ../app/code/Community/Signifyd/Connect/Model/Order.php */