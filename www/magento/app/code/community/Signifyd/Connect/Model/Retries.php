<?php

class Signifyd_Connect_Model_Retries extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {  
        $this->_init('signifyd_connect/retries');
        $this->_isPkAutoIncrement = false;
    }
}
