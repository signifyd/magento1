<?php

class Grep_Signifyd_Model_Resource_Case extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {  
        $this->_init('grep_signifyd/case', 'case_id');
    }
}
