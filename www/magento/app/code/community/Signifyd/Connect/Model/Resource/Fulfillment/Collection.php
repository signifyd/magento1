<?php

class Signifyd_Connect_Model_Resource_Fulfillment_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {  
        $this->_init('signifyd_connect/fulfillment');
    }
}
