<?php

class Signifyd_Connect_Adminhtml_SignifydController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_title($this->__('Sales'))->_title($this->__('Signifyd Scores'));
        $this->loadLayout();
        $this->_setActiveMenu('sales/sales');
        $this->_addContent($this->getLayout()->createBlock('signifyd_connect/adminhtml_sales_order'));
        $this->renderLayout();
    }
    
    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('signifyd_connect/adminhtml_sales_order_grid')->toHtml()
        );
    }

    public function sendAction()
    {
        Mage::helper('signifyd_connect')->bulkSend($this);
        $this->_redirectReferer();
    }
}
