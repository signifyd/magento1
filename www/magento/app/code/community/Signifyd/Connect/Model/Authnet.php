<?php

class Signifyd_Connect_Model_Authnet extends Mage_Paygate_Model_Authorizenet
{
    protected function _registerCard(varien_object $response, mage_sales_model_order_payment $payment)
    {
        $ccBin = substr($payment->getCcNumber(), 0, 6);
        $card = parent::_registerCard($response, $payment);
        $card->setCcBin($ccBin);
        $this->getCardsStorage($payment)->updateCard($card);

        $payment->setCcAvsStatus($response->getAvsResultCode());
        $payment->setCcCidStatus($response->getCardCodeResponseCode());
        $payment->setCcTransId($response->getTransactionId());

        return $card;
    }
}
