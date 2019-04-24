<?php

class Signifyd_Connect_Helper_Payment_Cryozonic_Stripe extends Signifyd_Connect_Helper_Payment_Default
{
    /**
     * @var /Stripe/Charge
     */
    protected $charge;

    /**
     * List of mapping AVS codes
     *
     * Keys are concatenation of Address Line 1 (address_line1_check) and
     * Postal Code (address_zip_check) verification responses
     *
     * @var array
     */
    protected $avsMap = array(
        'pass-pass' => 'Y',
        'pass-fail' => 'A',
        'fail-pass' => 'Z',
        'fail-fail' => 'N',
        'pass-unchecked' => 'A',
        'unchecked-pass' => 'Z',
        'unchecked-fail' => 'N',
        'fail-unchecked' => 'N',
        'unchecked-unchecked' => 'U'
    );

    /**
     * List of mapping CVV codes
     *
     * @var array
     */
    protected $cvvMap = array(
        'pass' => 'M',
        'fail' => 'N',
        'unchecked' => 'P',
        'unavailable' => 'P'
    );

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return $this
     */
    public function init(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Payment $payment)
    {
        $return = parent::init($order, $payment);

        try {
            /** @var Cryozonic_Stripe_Model_Standard $paymentObject */
            $paymentObject = $this->payment->getMethodInstance();
            $this->charge = $paymentObject->retrieveCharge($this->payment->getLastTransId());

            if (!is_object($this->charge) || !is_object($this->charge->source)) {
                $this->charge = null;
            }
        } catch (Exception $e) {
            $this->charge = null;
        }

        return $return;
    }

    /**
     * @return null|string
     */
    public function getAvsResponseCode()
    {
        $avsResponseCode = null;

        if (is_object($this->charge) &&
            isset($this->charge->source->address_line1_check) &&
            isset($this->charge->source->address_zip_check) ) {
            $addressLine1Check = $this->charge->source->address_line1_check;
            $addressZipCheck = $this->charge->source->address_zip_check;
            $key = "{$addressLine1Check}-{$addressZipCheck}";

            if (isset($this->avsMap[$key])) {
                $avsResponseCode = $this->filterAvsResponseCode($this->avsMap[$key]);
            }
        }

        $this->logger->addLog('AVS found on payment helper: ' . (empty($avsResponseCode) ? 'false' : $avsResponseCode), $this->payment);

        return $avsResponseCode;
    }

    /**
     * @return null|string
     */
    public function getCvvResponseCode()
    {
        $cvvResponseCode = null;

        if (is_object($this->charge) && isset($this->charge->source->cvc_check)) {
            $cvcCheck = $this->charge->source->cvc_check;

            if (isset($this->cvvMap[$cvcCheck])) {
                $cvvResponseCode = $this->filterCvvResponseCode($this->cvvMap[$cvcCheck]);
            }
        }

        $this->logger->addLog('CVV found on payment helper: ' . (empty($cvvResponseCode) ? 'false' : $cvvResponseCode), $this->payment);

        return $cvvResponseCode;
    }

    /**
     * @return string
     */
    public function getCardHolderName()
    {
        if (is_object($this->charge) && isset($this->charge->source->name)) {
            $cardHolderName = trim($this->charge->source->name);
            if (!empty($cardHolderName)) return $cardHolderName;
        }

        return parent::getCardHolderName();
    }

    /**
     * @return null|string
     */
    public function getLast4()
    {
        if (is_object($this->charge) && isset($this->charge->source->last4)) {
            $last4 = $this->filterLast4($this->charge->source->last4);
        }

        $this->logger->addLog('Last4 found on payment helper: ' . (empty($last4) ? 'false' : 'true'), $this->payment);

        if (empty($last4)) {
            $last4 = parent::getLast4();
        }

        return $last4;
    }

    /**
     * @return null|string
     */
    public function getExpiryMonth()
    {
        if (is_object($this->charge) && isset($this->charge->source->exp_month)) {
            $expiryMonth = $this->filterExpiryMonth($this->charge->source->exp_month);
        }

        $this->logger->addLog('Expiry month found on payment helper: ' . (empty($expiryMonth) ? 'false' : $expiryMonth), $this->payment);

        if (empty($expiryMonth)) {
            $expiryMonth = parent::getExpiryMonth();
        }

        return $expiryMonth;
    }

    /**
     * @return null|string
     */
    public function getExpiryYear()
    {
        if (is_object($this->charge) && isset($this->charge->source->exp_year)) {
            $expiryYear = $this->filterExpiryYear($this->charge->source->exp_year);
        }

        $this->logger->addLog('Expiry year found on payment helper: ' . (empty($expiryYear) ? 'false' : $expiryYear), $this->payment);

        if (empty($expiryYear)) {
            $expiryYear = parent::getExpiryYear();
        }

        return $expiryYear;
    }
}