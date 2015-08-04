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
        try {
            $orderIds = $this->getRequest()->getParam('order_ids');
            if (!is_array($orderIds)) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select order(s)'));
            } else {
                $collection = Mage::getModel('sales/order')->getCollection()
                    ->addFieldToSelect('*')
                    ->addFieldToFilter('entity_id', array('in' => $orderIds));

                foreach ($collection as $order) {
                    $result = Mage::helper('signifyd_connect')->buildAndSendOrderToSignifyd($order, /*forceSend*/ true);
                    if($result == "sent") {
                        Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Successfully sent order ' . $order->getIncrementId() . '.'));
                    } else if ($result == "exists") {
                        Mage::getSingleton('adminhtml/session')->addWarning(Mage::helper('adminhtml')->__('Order ' . $order->getIncrementId() . ' has already been sent to Signifyd.'));
                    } else {
                        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Order ' . $order->getIncrementId() . ' failed to send. See log for details.'));
                    }
                }
            }
        } catch(Exception $ex) {
            Mage::log($ex->__toString(), null, 'signifyd_connect.log');
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Send failed. See log for details'));
        }
        $this->_redirectReferer();
    }
}
