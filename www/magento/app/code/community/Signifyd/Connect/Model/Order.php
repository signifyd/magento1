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
    protected $dataHelper;

    /**
     * Internal magento constructor
     */
    public function _construct()
    {
        $this->logger = Mage::helper('signifyd_connect/log');
        $this->dataHelper = Mage::helper('signifyd_connect');
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
        if (!$this->dataHelper->isEnabled()) {
            return false;
        }

        $acceptedFromGuarantyAction = $this->dataHelper->getAcceptedFromGuaranty();
        $declinedFromGuaranty = $this->dataHelper->getDeclinedFromGuaranty();

        // If both workflow configurations are set to 'Do nothing', do not hold the order
        if ($acceptedFromGuarantyAction == 3 && $declinedFromGuaranty == 3) {
            return false;
        }

        if ($order->isEmpty()) {
            $this->logger->addLog("Order was not found");
            return false;
        }

        if ($this->dataHelper->isRestricted($order->getPayment()->getMethod(), $order->getState())) {
            $this->logger->addLog("Order {$order->getIncrementId()} payment method is restricted");
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

        if ($order->getStatus() != Mage_Sales_Model_Order::STATE_HOLDED) {
            $this->logger->addLog("Order {$incrementId} already released from hold");
            return true;
        }

        if (!$order->canUnhold()) {
            $this->logger->addLog("Order state: " . $order->getState() . "; status: " . $order->getStatus());
            $case = Mage::getModel('signifyd_connect/case')->load($incrementId);
            $this->logger->addLog("Case status: " . $case->getSignifydStatus());
            $this->logger->addLog("Order {$incrementId} can not be unheld");
            return false;
        }

        try {
            $order->unhold();
            $order->addStatusHistoryComment("Signifyd: order unheld because $reason");
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

        if ($order->getStatus() == Mage_Sales_Model_Order::STATE_HOLDED) {
            $this->logger->addLog("Order {$incrementId} already on hold status");
            return true;
        }

        if (!$order->canHold()) {
            $this->logger->addLog("Order {$incrementId} can not be held");
            return false;
        }

        try {
            $order->hold();
            $order->addStatusHistoryComment("Signifyd: order held {$reason}");
            $order->save();

            $this->logger->addLog("Order {$incrementId} held {$reason}");
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
        if (!$this->checkSettings($order)) {
            return false;
        }

        $incrementId = $order->getIncrementId();

        if ($order->isCanceled()) {
            $this->logger->addLog("Order {$incrementId} already canceled");
            return true;
        }

        $status = $this->dataHelper->getOrderPaymentStatus($order);
        if ($status['authorize'] === true && $status['capture'] === true) {
            $this->holdOrder($order, $reason);
            $this->logger->addLog("Order {$incrementId} can not be canceled because it has a sum already captured");
            $order->addStatusHistoryComment('Signifyd: order can not be canceled because it has a sum already captured. ' .
                'Please do a manual refund.');
            return false;
        }

        if (!$this->unholdOrder($order, $reason)) {
            return false;
        }

        if (!$order->canCancel()) {
            $this->logger->addLog("Order {$incrementId} can not be canceled");
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
}