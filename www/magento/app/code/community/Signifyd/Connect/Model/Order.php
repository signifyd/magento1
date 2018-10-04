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
    /**
     * @var Signifyd_Connect_Helper_Log
     */
    protected $logger;

    /**
     * @var Signifyd_Connect_Helper_Data
     */
    protected $helper;

    /**
     * Internal magento constructor
     */
    public function _construct()
    {
        $this->logger = Mage::helper('signifyd_connect/log');
        $this->helper = Mage::helper('signifyd_connect');
        parent::_construct();
    }

    /**
     * Returns false when the extension shouldn't interfere on workflow
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function checkSettings(Mage_Sales_Model_Order $order)
    {
        if (!$this->helper->isEnabled($order)) {
            return false;
        }

        $acceptedFromGuarantyAction = $this->helper->getAcceptedFromGuaranty($order);
        $declinedFromGuaranty = $this->helper->getDeclinedFromGuaranty($order);

        // If both workflow configurations are set to 'Do nothing', do not hold the order
        if ($acceptedFromGuarantyAction == 3 && $declinedFromGuaranty == 3) {
            return false;
        }

        if ($order->isEmpty()) {
            $this->logger->addLog("Order was not found");
            return false;
        }

        if ($this->helper->isRestricted($order->getPayment()->getMethod(), $order->getState())) {
            $this->logger->addLog("Order {$order->getIncrementId()} payment method or order state is restricted");
            return false;
        }

        if ($this->helper->isIgnored($order)) {
            $this->logger->addLog("Order {$order->getIncrementId()} ignored");
            return false;
        }

        return true;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string $reason
     *
     * @return bool
     */
    public function unholdOrder(Mage_Sales_Model_Order $order, $reason = '')
    {
        if (!$this->checkSettings($order)) {
            return false;
        }

        $incrementId = $order->getIncrementId();

        if (!$order->canUnhold()) {
            $state = $order->getState();

            if ($state != $order::STATE_HOLDED) {
                $reason = "order is not holded";
            } elseif ($order->isPaymentReview()) {
                $reason = 'order is in payment review';
            } elseif ($this->getActionFlag($order::ACTION_FLAG_UNHOLD) === false) {
                $reason = "order action flag is set to do not unhold";
            } else {
                $reason = "unknown reason";
            }

            $case = Mage::getModel('signifyd_connect/case')->load($incrementId);
            $this->logger->addLog(
                "Order {$incrementId} ({$order->getState()} > {$order->getStatus()}) " .
                "can not be unheld because {$reason}. " .
                "Case status: {$case->getSignifydStatus()}"
            );
            return false;
        }

        try {
            $order->unhold();
            $order->addStatusHistoryComment("Signifyd: order unheld, $reason");
            $order->save();

            $this->logger->addLog("Order {$order->getIncrementId()} unheld because {$reason}");
        } catch (Exception $e) {
            $this->logger->addLog("Order {$order->getIncrementId()} unable to be saved because {$e->getMessage()}");
            $this->logger->addLog("Order {$order->getIncrementId()} was not unheld.");
            return false;
        }

        return $order->getStatus() == Mage_Sales_Model_Order::STATE_HOLDED ? false : true;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string $reason
     *
     * @return bool
     */
    public function holdOrder(Mage_Sales_Model_Order $order, $reason = '')
    {
        if (!$this->checkSettings($order)) {
            return false;
        }

        $incrementId = $order->getIncrementId();

        if (!$order->canHold()) {
            $state = $order->getState();

            if ($order->isCanceled()) {
                $reason = 'order is canceled';
            } elseif ($order->isPaymentReview()) {
                $reason = 'order is in payment review';
            } elseif (in_array($state, array($order::STATE_COMPLETE, $order::STATE_CLOSED, $order::STATE_HOLDED))) {
                $reason = "order is on {$state} state";
            } elseif ($this->getActionFlag($order::ACTION_FLAG_HOLD) === false) {
                $reason = "order action flag is set to do not hold";
            } else {
                $reason = "unknown reason";
            }

            $this->logger->addLog("Order {$incrementId} can not be held because {$reason}");
            return false;
        }

        try {
            $order->hold();
            $order->addStatusHistoryComment("Signifyd: {$reason}");
            $order->save();

            $this->logger->addLog("Order {$incrementId} held: {$reason}");
        } catch (Exception $e) {
            $this->logger->addLog("Order {$incrementId} unable to be saved because " . $e->getMessage());
            $this->logger->addLog("Order {$incrementId} was not held.");
            return false;
        }

        return $order->getStatus() == Mage_Sales_Model_Order::STATE_HOLDED ? true : false;
    }

    /**
     * Method to hold the order whe the guaranty is declined and magento config is set to cancel the order
     * @param Mage_Sales_Model_Order $order
     * @param $reason
     * @return bool
     */
    public function cancelOrder(Mage_Sales_Model_Order $order, $reason)
    {
        if (!$this->checkSettings($order) && !$order->isCanceled()) {
            return false;
        }

        $incrementId = $order->getIncrementId();

        if ($order->isCanceled()) {
            $this->logger->addLog("Order {$incrementId} already canceled");
            return true;
        }

        $status = $this->helper->getOrderPaymentStatus($order);
        if ($status['authorize'] === true && $status['capture'] === true) {
            $this->holdOrder($order, $reason);
            $this->logger->addLog("Order {$incrementId} cannot be canceled because it has a sum already captured");
            $order->addStatusHistoryComment(
                'Signifyd: order cannot be canceled because it has a sum already captured. ' .
                'Please do a manual refund/cancelation.'
            );
            $order->save();
            return false;
        }

        if (!$this->unholdOrder($order, $reason)) {
            return false;
        }

        if (!$order->canCancel()) {
            $this->holdOrder($order, $reason);
            $this->logger->addLog("Order {$incrementId} cannot be canceled");
            $order->addStatusHistoryComment("Signifyd: order cannot be canceled. Tried to cancel order because {$reason}.");
            $order->save();
            return false;
        }

        try {
            $order->cancel();
            $order->addStatusHistoryComment("Signifyd: order canceled because $reason");
            $order->save();

            $this->logger->addLog("Order {$incrementId} cancelled because {$reason}");
        } catch (Exception $e){
            $this->logger->addLog("Order {$incrementId} unable to be saved because {$e->getMessage()}");
            $this->logger->addLog("Order {$incrementId} was not canceled.");
            return false;
        }

        if (!$order->isCanceled()) {
            $this->holdOrder($order, $reason);
        }
        
        return $order->isCanceled();
    }

    /**
     * Method to walk the flow for an order when the config is set to unhold, invoice and capture.
     * When guaranty is approved
     *
     * @param Mage_Sales_Model_Order $order
     * @param $reason
     * @return bool
     */
    public function unholdOrderAndCapture(Mage_Sales_Model_Order $order, $reason)
    {
        if (!$this->checkSettings($order)) {
            return false;
        }

        if (!$this->unholdOrder($order, $reason)) {
            return false;
        }

        $status = $this->helper->getOrderPaymentStatus($order);

        try {
            // Authorize the order
            $result = $this->authorizeOrder($order);
            if ($result === false) {
                $this->holdOrder($order, 'failed to authorize order');
            }

            // Generate the invoice
            if ($status['capture'] !== true) {
                $result = $this->generateInvoice($order);
                if ($result === false) {
                    $this->holdOrder($order, 'failed to generate invoice');
                }
            }
        } catch (Exception $e) {
            $this->holdOrder($order, 'failed to authorize/invoice order');
            $this->logger->addLog("Order {$order->getIncrementId()} unable to be saved because {$e->getMessage()}");
            $this->logger->addLog("Order {$order->getIncrementId()} was not unheld.");
            return false;
        }

        return true;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function authorizeOrder(Mage_Sales_Model_Order $order)
    {
        // Get the payment status for this order
        $status = $this->helper->getOrderPaymentStatus($order);

        if($status['authorize']){
            $this->logger->addLog("Order {$order->getIncrementId()} is already authorize");
            return true;
        }

        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();
        if(!$method->canAuthorize()){
            $this->logger->addLog("Order {$order->getIncrementId()} can not be authorize");
            return true;
        }

        $amount = $order->getData('grand_total');

        try{
            // check if method authorize returns anything usefully
            $method->authorize($payment, $amount);
            $this->logger->addLog("Authorize order {$order->getIncrementId()} was successful");
        } catch (Exception $e) {
            $this->logger->addLog("Authorize order {$order->getIncrementId()} was not authorized:");
            $this->logger->addLog($e->__toString());
            return false;
        }

        return true;
    }

    /**
     * Method to generate the invoice after a order was placed
     * @param $order
     * @return bool
     */
    public function generateInvoice(Mage_Sales_Model_Order $order)
    {
        if (!$order->canInvoice()) {
            $this->logger->addLog("Order {$order->getIncrementId()} can not be invoiced");
            return false;
        }

        try {
            /** @var Mage_Sales_Model_Order_Invoice $invoice */
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
}