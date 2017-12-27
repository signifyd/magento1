<?php

class Signifyd_Connect_Block_Sales_Order_View_Tab_Info extends Mage_Adminhtml_Block_Sales_Order_View_Tab_Info
{
    public function getGiftOptionsHtml()
    {
        /** @var Mage_Core_Block_Template $block */
        $block = $this->getLayout()->createBlock('core/template');
        $block->setTemplate("sales/order/signifyd.phtml");
        $block->setCase($this->getCase());
        $html = $block->toHtml();

        $html .= parent::getGiftOptionsHtml();

        return $html;
    }

    /**
     * @return Signifyd_Connect_Model_Case
     */
    public function getCase()
    {
        /** @var Signifyd_Connect_Model_Case $case */
        $case = Mage::getModel('signifyd_connect/case');

        /** @var Mage_Sales_Model_Order $order */
        $order = $this->getOrder();
        if ($order->isEmpty()) return $case;

        $case->load($order->getIncrementId());
        return $case;
    }
}