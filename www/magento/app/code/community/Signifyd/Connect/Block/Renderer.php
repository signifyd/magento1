<?php

class Signifyd_Connect_Block_Renderer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $value = $row->getData($this->getColumn()->getIndex());
        $column = $this->getColumn()->getId();



        switch ($column) {
            case 'score':
                if ($column == 'score' && !is_numeric($value)) {
                    return 'N/A';
                }

                $value = floor($value);
                break;

            case 'guarantee':
                $entries = $row->getEntries();
                if (!empty($entries)) {
                    @$entries = unserialize($entries);
                    if (is_array($entries) && isset($entries['testInvestigation']) && $entries['testInvestigation'] == true) {
                        $value = "TEST: {$value}";
                    }
                }

                if ($column == 'guarantee' && substr($value, -3) == 'N/A') {
                    return $value;
                }
                break;
        }

        $url = Mage::helper('signifyd_connect')->getCaseUrlByOrderId($row->getIncrementId());
        if ($url) {
            $value = "<a href=\"$url\" target=\"_blank\">$value</a>";
        }
        
        return $value;
    }
}
