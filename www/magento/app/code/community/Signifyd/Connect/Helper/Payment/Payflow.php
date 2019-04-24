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
        $avsResponseCode = null;

        if (isset($this->signifydData['cc_avs_status'])) {
            $avsResponseCode = $this->signifydData['cc_avs_status'];

            if (isset($this->avsMap[$avsResponseCode])) {
                $avsResponseCode = $this->filterAvsResponseCode($this->avsMap[$avsResponseCode]);
            } else {
                $avsResponseCode = null;
            }
        }

        $this->logger->addLog('AVS found on payment helper: ' . (empty($avsResponseCode) ? 'false' : $avsResponseCode), $this->payment);

        return $avsResponseCode;
    }

    public function getCvvResponseCode()
    {
        $cvvResponseCode = null;

        if (isset($this->signifydData['cc_cid_status'])) {
            $cvvResponseCode = $this->signifydData['cc_cid_status'];

            if (isset($this->cvvMap[$cvvResponseCode])) {
                $cvvResponseCode = $this->filterCvvResponseCode($this->cvvMap[$cvvResponseCode]);
            } else {
                $cvvResponseCode = null;
            }
        }

        $this->logger->addLog('CVV found on payment helper: ' . (empty($cvvResponseCode) ? 'false' : $cvvResponseCode), $this->payment);

        return $cvvResponseCode;
    }

    public function getLast4()
    {
        if (isset($this->signifydData['cc_last4'])) {
            $last4 = $this->signifydData['cc_last4'];
            $last4 = $this->filterLast4($last4);
        }

        $this->logger->addLog('Last4 found on payment helper: ' . (empty($last4) ? 'false' : 'true'), $this->payment);

        if (empty($last4)) {
            $last4 = parent::getLast4();
        }

        return $last4;
    }

    public function getExpiryMonth()
    {
        if (isset($this->signifydData['cc_exp_month'])) {
            $expiryMonth = $this->signifydData['cc_exp_month'];
            $expiryMonth = $this->filterExpiryMonth($expiryMonth);
        }

        $this->logger->addLog('Expiry month found on payment helper: ' . (empty($expiryMonth) ? 'false' : $expiryMonth), $this->payment);

        if (empty($expiryMonth)) {
            $expiryMonth = parent::getExpiryMonth();
        }

        return $expiryMonth;
    }

    public function getExpiryYear()
    {
        if (isset($this->signifydData['cc_exp_year'])) {
            $expiryYear = $this->signifydData['cc_exp_year'];
            $expiryYear = $this->filterExpiryYear($expiryYear);
        }

        $this->logger->addLog('Expiry year found on payment helper: ' . (empty($expiryYear) ? 'false' : $expiryYear), $this->payment);

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

                if (isset($paymentData['avsaddr']) && isset($paymentData['avszip'])) {
                    $signifydData['cc_avs_status'] = $paymentData['avsaddr'] . $paymentData['avszip'];
                }

                if (isset($paymentData['cvv2match'])) {
                    $signifydData['cc_cid_status'] = $paymentData['cvv2match'];
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
            Mage::helper('signifyd_connect/log')->addLog('Failed to collect data from Payflow: ' . $e->getMessage(), $this->payment);
        }
    }
}