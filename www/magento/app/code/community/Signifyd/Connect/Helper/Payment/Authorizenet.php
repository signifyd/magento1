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

        $cvvResponseCode = $this->filterCvvResponseCode($cvvResponseCode);

        $this->logger->addLog('CVV found on payment helper: ' . (empty($cvvResponseCode) ? 'false' : $cvvResponseCode), $this->payment);

        return $cvvResponseCode;
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
            $bin = $this->filterBin($bin);
        }

        $this->logger->addLog('Bin found on payment helper: ' . (empty($bin) ? 'false' : $bin), $this->payment);

        if (empty($bin)) {
            $bin = parent::getBin();
        }

        return $bin;
    }

    /**
     * @return null|string
     */
    public function getLast4()
    {
        if (!empty($this->authorizeCard)) {
            $last4 = $this->authorizeCard['cc_last4'];
            $last4 = $this->filterLast4($last4);
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
        if (!empty($this->authorizeCard)) {
            $expiryMonth = $this->authorizeCard['cc_exp_month'];
            $expiryMonth = $this->filterExpiryMonth($expiryMonth);
        }

        $this->logger->addLog('Expiry month found on payment helper: ' . (empty($expiryMonth) ? 'false' : $expiryMonth), $this->payment);

        if (empty($expiryMonth)) {
            $expiryMonth = parent::getExpiryMonth();
        }

        return $expiryMonth;
    }

    /**
     * @return int|null
     */
    public function getExpiryYear()
    {
        if (!empty($this->authorizeCard)) {
            $expiryYear = $this->authorizeCard['cc_exp_year'];
            $expiryYear = $this->filterExpiryYear($expiryYear);
        }

        $this->logger->addLog('Expiry year found on payment helper: ' . (empty($expiryYear) ? 'false' : $expiryYear), $this->payment);

        if (empty($expiryYear)) {
            $expiryYear = parent::getExpiryYear();
        }

        return $expiryYear;
    }
}