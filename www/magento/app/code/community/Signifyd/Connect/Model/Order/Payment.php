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
        Mage::log("Signifyd: Register capture notification", null, 'signifyd_connect.log');
        parent::registerCaptureNotification($amount, $skipFraudDetection = false);
        $order = $this->getOrder();
        $isDeclined = Mage::helper('signifyd_connect')->isGuarantyDeclined($order);
        if($isDeclined){
            Mage::log("Signifyd: Register capture notification execute hold status and state: order {$order->getIncrementId()}", null, 'signifyd_connect.log');
            $order->setState(Mage_Sales_Model_Order::STATE_HOLDED);
            $order->setStatus(Mage_Sales_Model_Order::STATE_HOLDED);
            $order->addStatusHistoryComment("Signifyd: order held because guarantee declined");
        }

        return $this;
    }
}

/* Filename: Cron.php */
/* Location: ../app/code/Community/Signifyd/Connect/Model/Cron.php */