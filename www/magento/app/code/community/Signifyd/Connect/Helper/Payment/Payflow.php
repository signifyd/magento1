<?php

class Signifyd_Connect_Helper_Payment_Payflow extends Signifyd_Connect_Helper_Payment_Default
{
    /**
     * List of mapping AVS codes
     *
     * Keys are concatenation of Street (avsaddr) and ZIP (avszip) codes
     *
     * @var array
     */
    protected $avsMap = array(
        'YN' => 'A',
        'NN' => 'N',
        'XX' => 'U',
        'YY' => 'Y',
        'NY' => 'Z'
    );

    /**
     * List of mapping CVV codes
     *
     * @var array
     */
    protected $cvvMap = array(
        'Y' => 'M',
        'N' => 'N',
        'X' => 'U'
    );

    public function getAvsResponseCode()
    {
        $avsResponseCode = $this->signifydData['cc_avs_status'];
        $avsResponseCode = $this->filterAvsResponseCode($avsResponseCode);

        if (empty($avsResponseCode)) {
            $avsResponseCode = parent::getAvsResponseCode();
        }

        return $avsResponseCode;
    }

    public function getCvvResponseCode()
    {
        $cvvResponseCode = $this->signifydData['cc_cid_status'];
        $cvvResponseCode = $this->filterCvvResponseCode($cvvResponseCode);

        if (empty($cvvResponseCode)) {
            $cvvResponseCode = parent::getCvvResponseCode();
        }

        return $cvvResponseCode;
    }

    public function getLast4()
    {
        $last4 = $this->signifydData['cc_last4'];
        $last4 = $this->filterLast4($last4);

        if (empty($last4)) {
            $last4 = parent::getLast4();
        }

        return $last4;
    }

    public function getExpiryMonth()
    {
        $expiryMonth = $this->signifydData['cc_exp_month'];
        $expiryMonth = $this->filterExpiryMonth($expiryMonth);

        if (empty($expiryMonth)) {
            $expiryMonth = parent::getExpiryMonth();
        }

        return $expiryMonth;
    }

    public function getExpiryYear()
    {
        $expiryYear = $this->signifydData['cc_exp_year'];
        $expiryYear = $this->filterExpiryYear($expiryYear);

        if (empty($expiryYear)) {
            $expiryYear = parent::getExpiryYear();
        }

        return $expiryYear;
    }

    public function collectFromPayflowResponse($response)
    {
        // Using try .. catch to avoid errors on order processing if anything goes wrong
        try {
            $paymentData = $response->getData();

            if (isset($paymentData['custref']) && !empty($paymentData['custref'])) {
                $signifydData = array();

                if (isset($paymentData['avsaddr']) && isset($paymentData['avszip']) &&
                    isset($this->avsMap[$paymentData['avsaddr'] . $paymentData['avszip']])) {
                    $signifydData['cc_avs_status'] = $this->avsMap[$paymentData['avsaddr'] . $paymentData['avszip']];
                }

                if (isset($paymentData['cvv2match']) && isset($this->cvvMap[$paymentData['cvv2match']])) {
                    $signifydData['cc_cid_status'] = $this->cvvMap[$paymentData['cvv2match']];
                }

                if (isset($paymentData['acct'])) {
                    $signifydData['cc_last4'] = substr($paymentData['acct'], -4);
                }

                if (isset($paymentData['expdate'])) {
                    $signifydData['cc_exp_month'] = substr($paymentData['expdate'], 0, 2);
                    $signifydData['cc_exp_year'] = substr($paymentData['expdate'], -2);
                }

                if (!empty($signifydData)) {
                    Mage::register('signifyd_data', $signifydData);
                }
            }
        }
        catch (Exception $e) {
            Mage::helper('signifyd_connect/log')->addLog('Failed to collect data from Payflow: ' . $e->getMessage());
        }
    }
}