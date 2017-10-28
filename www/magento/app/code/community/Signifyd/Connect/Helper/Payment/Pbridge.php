<?php

class Signifyd_Connect_Helper_Payment_Pbridge extends Signifyd_Connect_Helper_Payment_Default
{
    /**
     * @return null|string
     */
    public function getLast4()
    {
        $last4 = parent::getLast4();

        if (empty($last4)) {
            $last4 = $this->filterLast4($this->additionalInformation['pbridge_data']['cc_last4']);
        }

        return $last4;
    }

    /**
     * Payment Bridge does not return to Magento cardholder name, bin, expiry month or expiry year
     * The next methods prevents the collection of wrong data, saved by Magento on database by mistake
     * 
     * E.g.: if the customer select Saved CC methods, fill the form, submit to the next step (one page checkout)
     * then the customer changes his mind, get back to payment step, use a Payment Bridge method and place the order.
     * When the Saved CC informations are submitted to the server, Magento keep them and save them to the default
     * database location. 
     */

    /**
     * Payment Bridge does not return cardholder name to Magento
     * @return string
     */
    public function getCardHolderName()
    {
        $billing = $this->order->getBillingAddress();
        return $billing->getFirstname() . ' ' . $billing->getLastname();
    }

    /**
     * Payment Bridge does not return bin or credit card number to Magento
     * @return null
     */
    public function getBin()
    {
        return null;
    }

    /**
     * Payment Bridge does not return expiry month to Magento
     * @return null
     */
    public function getExpiryMonth()
    {
        return null;
    }

    /**
     * Payment Bridge does not return expiry year to Magento
     * @return null
     */
    public function getExpiryYear()
    {
        return null;
    }
}
