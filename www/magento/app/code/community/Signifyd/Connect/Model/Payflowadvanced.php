<?php

class Signifyd_Connect_Model_Payflowadvanced extends Mage_Paypal_Model_Payflowadvanced
{
    protected function _postRequest(Varien_Object $request)
    {
        /** @var Varien_Object $result */
        $response = parent::_postRequest($request);

        Mage::helper('signifyd_connect/payment_payflow')->collectFromPayflowResponse($response);

        return $response;
    }
}