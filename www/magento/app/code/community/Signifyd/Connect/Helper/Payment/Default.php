<?php

class Signifyd_Connect_Helper_Payment_Default
    extends Mage_Core_Helper_Abstract
    implements Signifyd_Connect_Helper_Payment_Interface
{
    /**
     * @var Mage_Sales_Model_Order
     */
    protected $order;

    /**
     * @var Mage_Sales_Model_Order_Payment
     */
    protected $payment;

    /**
     * @var array|mixed|null
     */
    protected $additionalInformation;

    /**
     * Data collected directly by Signifyd extension (e.g.: credit card data form submited to the server)
     * @var
     */
    protected $signifydData = array();

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return $this
     */
    public function init(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Payment $payment)
    {
        $this->order = $order;
        $this->payment = $payment;
        $this->additionalInformation = $payment->getAdditionalInformation();
        $this->signifydData = array();

        $registrySignifydData = Mage::registry('signifyd_data');
        if (!empty($registrySignifydData) && is_array($registrySignifydData)) {
            $this->signifydData = $registrySignifydData;
            Mage::unregister('signifyd_data');
        }

        return $this;
    }

    /**
     * Check if helper is initialized for given order and payment
     *
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return bool
     */
    public function isInitializedForOrderPayment(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Payment $payment)
    {
        // Is helper initialized
        if ($this->order->isEmpty() || $this->payment->getId() == false) {
            return false;
        }

        // Empty parameters
        if ($order->isEmpty() || $payment->getId() == false) {
            return false;
        }

        // Both order and payment parameters helper match helper properties
        if ($this->order->getId() == $order->getId() && $this->payment->getId() == $payment->getId()) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getCardData()
    {
        $card = array();

        $card['cardHolderName'] = $this->getCardHolderName();
        $card['bin'] = $this->getBin();
        $card['last4'] = $this->getLast4();
        $card['expiryMonth'] = $this->getExpiryMonth();
        $card['expiryYear'] = $this->getExpiryYear();

        $card['bin'] = $this->filterBin($card['bin']);
        $card['last4'] = $this->filterLast4($card['last4']);
        $card['expiryMonth'] = $this->filterExpiryMonth($card['expiryMonth']);
        $card['expiryYear'] = $this->filterExpiryYear($card['expiryYear']);

        foreach ($card as $field => $info) {
            if (empty($info)) {
                unset($card[$field]);
            }
        }

        return $card;
    }

    /**
     * @return null|string
     */
    public function getAvsResponseCode()
    {
        $avsResponseCode = $this->payment->getCcAvsStatus();
        return $this->filterAvsResponseCode($avsResponseCode);
    }

    /**
     * @return null|string
     */
    public function getCvvResponseCode()
    {
        $cvvResponseCode = $this->payment->getCcCidStatus();
        return $this->filterCvvResponseCode($cvvResponseCode);
    }

    /**
     * @return string
     */
    public function getCardHolderName()
    {
        $cardHolderName = trim($this->payment->getCcOwner());

        if (!empty($cardHolderName)) {
            return $cardHolderName;
        } elseif (!empty($this->signifydData['cc_owner']) && strlen(trim($this->signifydData['cc_owner'])) > 3) {
            return $this->signifydData['cc_owner'];
        } else {
            $billing = $this->order->getBillingAddress();
            return $billing->getFirstname() . ' ' . $billing->getLastname();
        }
    }

    /**
     * @return int|null
     */
    public function getBin()
    {
        $ccNumber = (string) preg_replace('/\D/', '', $this->payment->getData('cc_number'));
        if (!empty($ccNumber) && strlen($ccNumber) > 6) {
            $bin = $this->filterBin(substr($ccNumber, 0, 6));
        }

        if (empty($bin)) {
            $bin = isset($this->signifydData['cc_bin']) ? $this->signifydData['cc_bin'] : null;

            $bin = $this->filterBin($bin);
        }

        return $bin;
    }

    /**
     * @return null|string
     */
    public function getLast4()
    {
        $last4 = $this->payment->getCcLast4();
        $last4 = $this->filterLast4($last4);

        if (empty($last4)) {
            $last4 = isset($this->signifydData['cc_last4']) ? $this->signifydData['cc_last4'] : null;
            $last4 = $this->filterLast4($last4);
        }

        return $last4;
    }

    /**
     * @return null|string
     */
    public function getExpiryMonth()
    {
        $expiryMonth = $this->payment->getCcExpMonth();
        $expiryMonth = $this->filterExpiryMonth($expiryMonth);

        if (empty($expiryMonth) && isset($this->signifydData['cc_exp_month'])) {
            $expiryMonth = $this->filterExpiryMonth($this->signifydData['cc_exp_month']);
        }

        return $expiryMonth;
    }

    /**
     * @return int|null
     */
    public function getExpiryYear()
    {
        $expiryYear = $this->payment->getCcExpYear();
        $expiryYear = $this->filterExpiryYear($expiryYear);

        if (empty($expiryYear) && isset($this->signifydData['cc_exp_year'])) {
            $expiryYear = $this->filterExpiryYear($this->signifydData['cc_exp_year']);
        }

        return $expiryYear;
    }

    /**
     * @param $avsResponseCode
     * @return null|string
     */
    public function filterAvsResponseCode($avsResponseCode)
    {
        if (empty($avsResponseCode)) {
            return null;
        }

        // http://www.emsecommerce.net/avs_cvv2_response_codes.htm
        $validAvsResponseCodes = array('X', 'Y', 'A', 'W', 'Z', 'N', 'U', 'R', 'E', 'S', 'D', 'M', 'B', 'P', 'C', 'I', 'G');
        $avsResponseCode = trim(strtoupper($avsResponseCode));

        for ($i = 0; $i < strlen($avsResponseCode); $i++) {
            if (!in_array(substr($avsResponseCode, $i, 1), $validAvsResponseCodes)) {
                return null;
            }
        }

        return $avsResponseCode;
    }

    /**
     * @param $cvvResponseCode
     * @return null|string
     */
    public function filterCvvResponseCode($cvvResponseCode)
    {
        if (empty($cvvResponseCode)) {
            return null;
        }

        // http://www.emsecommerce.net/cvv_cvv2_response_codes.htm
        $validCvvResponseCodes = array('M', 'N', 'P', 'S', 'U');
        $cvvResponseCode = trim(strtoupper($cvvResponseCode));

        for ($i = 0; $i < strlen($cvvResponseCode); $i++) {
            if (!in_array(substr($cvvResponseCode, $i, 1), $validCvvResponseCodes)) {
                return null;
            }
        }

        return $cvvResponseCode;
    }

    /**
     * @param $bin
     * @return int|null
     */
    public function filterBin($bin)
    {
        $bin = intval(trim($bin));

        // A credit card does not starts with zero, so the bin intaval has to be at least 100.000
        if ($bin >= 100000) {
            return $bin;
        }

        return null;
    }

    /**
     * @param $last4
     * @return null|string
     */
    public function filterLast4($last4)
    {
        $last4 = trim($last4);

        if (!empty($last4) && strlen($last4) == 4 && is_numeric($last4)) {
            return strval($last4);
        }

        return null;
    }

    /**
     * @param $expiryMonth
     * @return int|null
     */
    public function filterExpiryMonth($expiryMonth)
    {
        $expiryMonth = intval($expiryMonth);
        if ($expiryMonth >= 1 && $expiryMonth <= 12) {
            return intval($expiryMonth);
        }

        return null;
    }

    /**
     * @param $expiryYear
     * @return int|null
     */
    public function filterExpiryYear($expiryYear)
    {
        $expiryYear = intval($expiryYear);
        if ($expiryYear > 0) {
            return $expiryYear;
        }

        return null;
    }
}