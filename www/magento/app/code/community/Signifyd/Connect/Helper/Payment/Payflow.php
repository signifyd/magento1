<?php

class Signifyd_Connect_Helper_Payment_Payflow extends Signifyd_Connect_Helper_Payment_Default
{
    public function getAvsResponseCode()
    {
        $avsResponseCode = $this->additionalInformation['signifyd_data']['cc_avs_status'];
        $avsResponseCode = $this->filterAvsResponseCode($avsResponseCode);
        
        if (empty($avsResponseCode)) {
            $avsResponseCode = parent::getAvsResponseCode();
        }
        
        return $avsResponseCode;
    }

    public function getCvvResponseCode()
    {
        $cvvResponseCode = $this->additionalInformation['signifyd_data']['cc_cid_status'];
        $cvvResponseCode = $this->filterCvvResponseCode($cvvResponseCode);

        if (empty($cvvResponseCode)) {
            $cvvResponseCode = parent::getCvvResponseCode();
        }

        return $cvvResponseCode;
    }

    public function getLast4()
    {
        $last4 = $this->additionalInformation['signifyd_data']['cc_last4'];
        $last4 = $this->filterLast4($last4);

        if (empty($last4)) {
            $last4 = parent::getLast4();
        }

        return $last4;
    }

    public function getExpiryMonth()
    {
        $expiryMonth = $this->additionalInformation['signifyd_data']['cc_exp_month'];
        $expiryMonth = $this->filterExpiryMonth($expiryMonth);

        if (empty($expiryMonth)) {
            $expiryMonth = parent::getExpiryMonth();
        }

        return $expiryMonth;
    }

    public function getExpiryYear()
    {
        $expiryYear = $this->additionalInformation['signifyd_data']['cc_exp_year'];
        $expiryYear = $this->filterExpiryYear($expiryYear);

        if (empty($expiryYear)) {
            $expiryYear = parent::getExpiryYear();
        }

        return $expiryYear;
    }
}