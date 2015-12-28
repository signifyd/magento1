<?php

class Signifyd_Connect_Model_Resource_Retries extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {  
        $this->_init('signifyd_connect/retries', 'order_increment');
        $this->_isPkAutoIncrement = false;
    }
}
