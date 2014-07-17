<?php

class Signifyd_Connect_Block_Renderer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {   
        $value = $row->getData($this->getColumn()->getIndex());
        $helper = Mage::helper('signifyd_connect');
        
        $url = $helper->getCaseUrl($row->getIncrementId());
        
        if (!is_numeric($value)) {
            return $helper->__('N/A');
        }
        
        $value = floor($value);
        
        if ($url) {
            $value = "<a href=\"$url\" target=\"_blank\">$value</a>";
        }
        
        return $value;
    }
}
