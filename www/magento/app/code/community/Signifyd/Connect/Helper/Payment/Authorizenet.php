<?php

class Signifyd_Connect_Helper_Payment_Authorizenet extends Signifyd_Connect_Helper_Payment_Default
{
    /**
     * @var mixed
     */
    protected $authorizeCard;

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return $this
     */
    public function init(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Payment $payment)
    {
        $return = parent::init($order, $payment);

        $this->authorizeCard = array_shift($this->additionalInformation['authorize_cards']);

        return $return;
    }

    /**
     * @return null|string
     */
    public function getCvvResponseCode()
    {
        $cvvResponseCode = $this->payment->getCcCidStatus();

        if ($cvvResponseCode == 'B') {
            $cvvResponseCode = 'U';
        }

        return $this->filterCvvResponseCode($cvvResponseCode);
    }

    /**
     * @return string
     */
    public function getCardHolderName()
    {
        $cardHolderName = empty($this->authorizeCard) ? null : trim($this->authorizeCard['cc_owner']);

        if (empty($cardHolderName)) {
            return parent::getCardHolderName();
        } else {
            return $cardHolderName;
        }
    }

    /**
     * @return int|null
     */
    public function getBin()
    {
        if (!empty($this->authorizeCard) && isset($this->authorizeCard['cc_bin'])) {
            $bin = $this->authorizeCard['cc_bin'];
            return $this->filterBin($bin);
        }

        return parent::getBin();
    }

    /**
     * @return null|string
     */
    public function getLast4()
    {
        if (!empty($this->authorizeCard)) {
            $last4 = $this->authorizeCard['cc_last4'];
            return $this->filterLast4($last4);
        }

        return parent::getLast4();
    }

    /**
     * @return null|string
     */
    public function getExpiryMonth()
    {
        if (!empty($this->authorizeCard)) {
            $expiryMonth = $this->authorizeCard['cc_exp_month'];
            return $this->filterExpiryMonth($expiryMonth);
        }

        return parent::getExpiryMonth();
    }

    /**
     * @return int|null
     */
    public function getExpiryYear()
    {
        if (!empty($this->authorizeCard)) {
            $expiryYear = $this->authorizeCard['cc_exp_year'];
            return $this->filterExpiryYear($expiryYear);
        }

        return parent::getExpiryYear();
    }
}