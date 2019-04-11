<?php

class Signifyd_Connect_Model_Container_Fingerprint extends Enterprise_PageCache_Model_Container_Abstract
{
    /**
     * Get identifier from cookies
     *
     * @return string
     */
    protected function _getCacheId()
    {
        return $this->_getCookieValue(Enterprise_PageCache_Model_Cookie::COOKIE_CART, '')
            . $this->_getCookieValue(Enterprise_PageCache_Model_Cookie::COOKIE_CUSTOMER, '');
    }

    /**
     * Render block content
     *
     * @return string
     */
    protected function _renderBlock()
    {
        return $this->_getPlaceHolderBlock()->toHtml();
    }
}
