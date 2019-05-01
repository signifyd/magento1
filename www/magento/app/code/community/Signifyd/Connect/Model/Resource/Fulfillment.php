<?php

class Signifyd_Connect_Model_Resource_Fulfillment extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {  
        $this->_init('signifyd_connect/fulfillment', 'id');
        $this->_isPkAutoIncrement = false;
    }
}
