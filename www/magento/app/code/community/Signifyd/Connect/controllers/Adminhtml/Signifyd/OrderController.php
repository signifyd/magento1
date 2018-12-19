<?php

require_once Mage::getModuleDir('controllers', 'Mage_Adminhtml').DS.'Sales'.DS.'OrderController.php';

class Signifyd_Connect_Adminhtml_Signifyd_OrderController extends Mage_Adminhtml_Sales_OrderController
{
    public function unholdAction()
    {
        try {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('order_id'));
        } catch (Exception $e) {
            return parent::unholdAction();
        }

        /** @var $case Signifyd_Connect_Model_Case */
        $case = Mage::getModel('signifyd_connect/case');
        $case->load($order->getIncrementId());

        if (!$case->isHoldReleased()) {
            $case->setEntries('hold_released', 1);
            $case->save();
        }

        /** @var Mage_Admin_Model_Session $adminSession */
        $adminSession = Mage::getSingleton('admin/session');
        /** @var Mage_Admin_Model_User $admin */
        $admin = $adminSession->getUser();
        $order->addStatusHistoryComment("Signifyd: order status updated by {$admin->getUsername()}");
        $order->save();

        $result = parent::unholdAction();

        return $result;
    }
}
