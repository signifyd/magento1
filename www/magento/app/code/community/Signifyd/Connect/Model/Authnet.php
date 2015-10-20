<?php

class Signifyd_Connect_Model_Authnet extends Mage_Paygate_Model_Authorizenet
{
    protected function _registercard(varien_object $response, mage_sales_model_order_payment $payment)
    {
        Mage::log(">: ".$response->getTransactionId(), null, 'signifyd_connect.log');
        $card = parent::_registercard($response,$payment);
        $card->setCcAvsResultCode($response->getAvsResultCode());
        $card->setCcResponseCode($response->getCardCodeResponseCode());
        $payment->setCcAvsStatus($response->getAvsResultCode());
        $payment->setCcCidStatus($response->getCardCodeResponseCode());
        $payment->setCcTransId($response->getTransactionId());
        $payment->getCcTransId();
        return $card;
    }
}
