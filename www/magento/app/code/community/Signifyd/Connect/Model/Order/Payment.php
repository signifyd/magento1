<?php
/**
 * Payment Model
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */
class Signifyd_Connect_Model_Order_Payment extends Mage_Sales_Model_Order_Payment
{
    public function registerCaptureNotification($amount, $skipFraudDetection = false)
    {
        parent::registerCaptureNotification($amount, $skipFraudDetection);

        /** @var Signifyd_Connect_Helper_Data $helper */
        $helper = Mage::helper('signifyd_connect');

        /** @var Signifyd_Connect_Helper_Log $logger */
        $logger = Mage::helper('signifyd_connect/log');

        $order = $this->getOrder();

        if ($helper->isGuarantyDeclined($order)) {
            $logger->addLog("Register capture notification execute hold order {$order->getIncrementId()}", $order);
            Mage::getModel('signifyd_connect/order')->holdOrder($order, "guarantee declined");
        }

        return $this;
    }
}