<?php

/**
 * Class Signifyd_Connect_Helper_Payment_Paypal_Express
 *
 * PayPal Express does not give any informations about the credit card used on the transaction
 * 
 * This class prevents the extension from fetch mistaken information. E.g.: if customer fills a embedded form from another
 * payment method, submit it to server and then switch to PayPal Express for any reasons, the payment information from 
 * the other method can be saved to the database, because PayPal Express redirects the user from the payment step
 * on the checkout process
 */
class Signifyd_Connect_Helper_Payment_Paypal_Express extends Signifyd_Connect_Helper_Payment_Default
{
    public function getAvsResponseCode()
    {
        return null;
    }

    public function getCvvResponseCode()
    {
        return null;
    }

    public function getCardHolderName()
    {
        return null;
    }

    public function getBin()
    {
        return null;
    }

    public function getLast4()
    {
        return null;
    }

    public function getExpiryMonth()
    {
        return null;
    }

    public function getExpiryYear()
    {
        return null;
    }
}