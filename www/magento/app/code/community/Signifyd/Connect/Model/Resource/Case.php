<?php

class Signifyd_Connect_Model_Resource_Case extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {  
        $this->_init('signifyd_connect/case', 'case_id');
    }
}
