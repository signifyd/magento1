<?php

class Signifyd_Connect_Block_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{
    public function __construct()
    {
        $return = parent::__construct();

        /** @var Mage_Sales_Model_Order $order */
        $order = $this->getOrder();

        if ($this->_isAllowedAction('unhold') && $order->canUnhold()) {
            /** @var Signifyd_Connect_Model_Case $case */
            $case = Mage::getModel('signifyd_connect/case');
            $case->load($order->getIncrementId());

            if (!$case->isEmpty()) {
                $guarantee = $case->getData('guarantee');

                if (!$case->isHoldReleased() && $guarantee == 'N/A') {
                    $this->removeButton('order_unhold');

                    $message = 'Signifyd has not reviewed this order, are you sure you want to unhold?';
                    $url = $this->getUrl('adminhtml/signifyd_order/unhold');

                    $this->_addButton(
                        'order_unhold', array(
                        'label' => Mage::helper('sales')->__('Unhold'),
                        'onclick' => 'if (confirm(\''. $message . '\')) { setLocation(\'' . $url . '\'); }',
                        )
                    );
                }
            }
        }

        return $return;
    }
}