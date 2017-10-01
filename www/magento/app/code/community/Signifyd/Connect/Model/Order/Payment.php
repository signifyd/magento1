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
        /** @var Signifyd_Connect_Helper_Data $helper */
        $helper = Mage::helper('signifyd_connect');
        $helper->log("Signifyd: Register capture notification");
        parent::registerCaptureNotification($amount, $skipFraudDetection = false);
        $order = $this->getOrder();
        $isDeclined = $helper->isGuarantyDeclined($order);
        if($isDeclined){
            $helper->log("Signifyd: Register capture notification execute hold status and state: order {$order->getIncrementId()}");
            $order->setState(Mage_Sales_Model_Order::STATE_HOLDED);
            $order->setStatus(Mage_Sales_Model_Order::STATE_HOLDED);
            $order->addStatusHistoryComment("Signifyd: order held because guarantee declined");
        }

        return $this;
    }
}

/* Filename: Cron.php */
/* Location: ../app/code/Community/Signifyd/Connect/Model/Cron.php */