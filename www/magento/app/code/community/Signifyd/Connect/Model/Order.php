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
            $this->logger->addLog("Order {$order->getIncrementId()} payment method or order state is restricted", $order);
            return false;
        }

        if ($this->helper->isIgnored($order)) {
            $this->logger->addLog("Order {$order->getIncrementId()} ignored", $order);
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

            $order->addStatusHistoryComment("Signifyd: order status cannot be updated, {$reason}");
            $order->save();

            $this->logger->addLog("Order {$incrementId} cannot be updated, {$reason}", $order);
            return false;
        }

        try {
            $order->unhold();
            $order->addStatusHistoryComment("Signifyd: order status updated, {$reason}");
            $order->save();

            $this->logger->addLog("Order {$order->getIncrementId()} removed from hold: {$reason}", $order);
        } catch (Exception $e) {
            $this->logger->addLog("Order {$order->getIncrementId()} was not removed from hold: {$e->getMessage()}", $order);
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

            $order->addStatusHistoryComment("Signifyd: order cannot be updated to on hold, {$reason}");
            $order->save();

            $this->logger->addLog("Order {$incrementId} cannot be updated to on hold, {$reason}", $order);

            return false;
        }

        try {
            $order->hold();
            $order->addStatusHistoryComment("Signifyd: {$reason}");
            $order->save();

            $this->logger->addLog("Order {$incrementId} put on hold: {$reason}", $order);
        } catch (Exception $e) {
            $this->logger->addLog("Order {$incrementId} was not put on hold: " . $e->getMessage(), $order);
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

        $this->unholdOrder($order, $reason);

        if (!$order->canCancel()) {
            $state = $order->getState();

            if ($order->isPaymentReview()) {
                $reason = 'order is in payment review';
            } else {
                $allInvoiced = true;

                foreach ($order->getAllItems() as $item) {
                    if ($item->getQtyToInvoice()) {
                        $allInvoiced = false;
                        break;
                    }
                }

                if ($allInvoiced) {
                    $reason = 'all order items are invoiced';
                } elseif ($order->isCanceled()) {
                    $reason = 'order is cancelled';
                } elseif (in_array($state, array($order::STATE_COMPLETE, $order::STATE_CLOSED))) {
                    $reason = "order is on {$state} state";
                } elseif ($order->getActionFlag($order::ACTION_FLAG_CANCEL) === false) {
                    $reason = "order action flag is set to do not cancel";
                } else {
                    $paymentStatus = $this->helper->getOrderPaymentStatus($order);

                    if ($paymentStatus['authorize'] === true && $paymentStatus['capture'] === true) {
                        $reason = "order is already captured. Please do a manual refund/cancelation";
                    }
                }
            }

            $this->holdOrder($order, $reason);
            $this->logger->addLog("Order {$incrementId} cannot be canceled, {$reason}", $order);
            $order->addStatusHistoryComment("Signifyd: order cannot be canceled, {$reason}");
            $order->save();

            return false;
        }

        try {
            $order->cancel();
            $order->addStatusHistoryComment("Signifyd: order canceled, {$reason}");
            $order->save();

            $this->logger->addLog("Order {$incrementId} cancelled, {$reason}", $order);
        } catch (Exception $e){
            $this->logger->addLog("Order {$incrementId} unable to be saved, {$e->getMessage()}", $order);
            $this->logger->addLog("Order {$incrementId} was not canceled", $order);
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
            // Generate the invoice
            if ($status['capture'] !== true) {
                $result = $this->generateInvoice($order);
                if ($result === false) {
                    $this->holdOrder($order, 'failed to generate invoice');
                }
            }
        } catch (Exception $e) {
            $this->holdOrder($order, 'failed to authorize/invoice order');
            $this->logger->addLog("Order {$order->getIncrementId()} was not removed from hold: {$e->getMessage()}", $order);
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
        $incrementId = $order->getIncrementId();

        if (!$order->canInvoice()) {
            $state = $order->getState();

            $notInvoiceableStates = array(
                $order::STATE_CANCELED,
                $order::STATE_PAYMENT_REVIEW,
                $order::STATE_COMPLETE,
                $order::STATE_CLOSED,
                $order::STATE_HOLDED
            );

            if (in_array($state, $notInvoiceableStates)) {
                $reason = "order is on {$state} state";
            } elseif ($order->getActionFlag($order::ACTION_FLAG_UNHOLD) === false) {
                $reason = "order action flag is set to do not invoice";
            } else {
                $canInvoiceAny = false;

                /** @var Mage_Sales_Model_Order_Item $item */
                foreach ($order->getAllItems() as $item) {
                    if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                        $canInvoiceAny = true;
                        break;
                    }
                }

                if ($canInvoiceAny) {
                    $reason = "unknown reason";
                } else {
                    $reason = "no items can be invoiced";
                }
            }

            $order->addStatusHistoryComment("Signifyd: unable to create invoice, {$reason}");
            $order->save();

            $this->logger->addLog("Order {$incrementId}, unable to create invoice, {$reason}", $order);

            return false;
        }

        try {
            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            if ($invoice->isEmpty()) {
                throw new Exception('failed to generate invoice');
            }

            if ($invoice->getTotalQty() == 0) {
                throw new Exception('no items found to invoice');
            }

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();

            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();

            $this->logger->addLog("Invoice was created for order: {$order->getIncrementId()}", $order);

            $invoice->addComment('Signifyd: create order invoice', false);
            $invoice->sendEmail();
            $invoice->setEmailSent(true);
            $invoice->save();

            $order->addStatusHistoryComment("Signifyd: create order invoice: {$invoice->getIncrementId()}");
            $order->save();
        } catch (Exception $e) {
            $this->logger->addLog('Exception while creating invoice: ' . $e->__toString(), $order);

            $order->addStatusHistoryComment("Signifyd: unable to create invoice, {$e->getMessage()}");
            $order->save();

            return false;
        }

        return true;
    }
}