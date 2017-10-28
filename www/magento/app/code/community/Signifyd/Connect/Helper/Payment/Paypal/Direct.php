<?php

class Signifyd_Connect_Helper_Payment_Paypal_Direct extends Signifyd_Connect_Helper_Payment_Default
{
    /**
     * @return null|string
     */
    public function getAvsResponseCode()
    {
        $avsResponseCode = $this->additionalInformation['paypal_avs_code'];
        return $this->filterAvsResponseCode($avsResponseCode);
    }

    /**
     * @return null|string
     */
    public function getCvvResponseCode()
    {
        $cvvResponseCode = $this->additionalInformation['paypal_cvv2_match'];
        return $this->filterCvvResponseCode($cvvResponseCode);
    }
}