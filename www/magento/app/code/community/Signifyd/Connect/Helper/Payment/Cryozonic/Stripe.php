<?php

class Signifyd_Connect_Helper_Payment_Cryozonic_Stripe extends Signifyd_Connect_Helper_Payment_Default
{
    /**
     * @var /Stripe/Charge
     */
    protected $charge;

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
            if (!empty($last4)) return $last4;
        }

        return parent::getLast4();
    }

    /**
     * @return null|string
     */
    public function getExpiryMonth()
    {
        if (is_object($this->charge) && isset($this->charge->source->exp_month)) {
            $expiryMonth = $this->filterExpiryMonth($this->charge->source->exp_month);
            if (!empty($expiryMonth)) return $expiryMonth;
        }

        return parent::getExpiryMonth();
    }

    /**
     * @return null|string
     */
    public function getExpiryYear()
    {
        if (is_object($this->charge) && isset($this->charge->source->exp_year)) {
            $expiryYear = $this->filterExpiryYear($this->charge->source->exp_year);
            if (!empty($expiryYear)) return $expiryYear;
        }

        return parent::getExpiryYear();
    }
}