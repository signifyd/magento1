<?php

class Signifyd_Connect_Block_Adminhtml_Sales_Order extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'signifyd_connect';
        $this->_controller = 'adminhtml_sales_order';
        $this->_headerText = Mage::helper('signifyd_connect')->__('Signifyd Scores');
        parent::__construct();
        $this->_removeButton('add');
    }
}
