<?php
/**
 * Device Block
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */

class Signifyd_Connect_Block_Device extends Mage_Core_Block_Template
{
    protected $quote;

    public function _construct()
    {
        $this->quote = Mage::getSingleton('checkout/session')->getQuote();
        parent::_construct();
    }

    public function getDeviceFingerPrint()
    {
        return 'M1' . base64_encode(Mage::getBaseUrl()) . $this->getQuoteId();
    }

    public function getQuoteId()
    {
        return $this->quote->getId();
    }

    public function isActive()
    {
        $enabled = Mage::helper('signifyd_connect')->isDeviceFingerprintEnabled();
        $quoteId = $this->getQuoteId();

        return $enabled && !is_null($quoteId);
    }
}