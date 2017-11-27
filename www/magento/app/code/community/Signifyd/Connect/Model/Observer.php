<?php

class Signifyd_Connect_Model_Observer extends Varien_Object
{
    public $joins = 0;

    /**
     * @var Signifyd_Connect_Helper_Data|null
     */
    protected $helper;

    /**
     * @var Signifyd_Connect_Model_Order|null
     */
    protected $orderModel;

    /**
     * @return Mage_Core_Helper_Abstract|Signifyd_Connect_Helper_Data
     */
    public function getHelper()
    {
        if (!$this->helper instanceof Signifyd_Connect_Helper_Data) {
            $this->helper = Mage::helper('signifyd_connect');
        }

        return $this->helper;
    }

    /**
     * @return false|Mage_Core_Model_Abstract|null|Signifyd_Connect_Model_Order
     */
    public function getOrderModel()
    {
        if (!$this->orderModel instanceof Signifyd_Connect_Model_Order) {
            $this->orderModel = Mage::getModel('signifyd_connect/order');
        }

        return $this->orderModel;
    }

    public function openCase($observer)
    {
        try {
            if (!Mage::getStoreConfig('signifyd_connect/settings/enabled') && !$this->getEnabled()) {
                return $this;
            }

            $event = $observer->getEvent();

            /** @var Mage_Sales_Model_Order $order */
            if ($event->hasOrder()) {
                $order = $event->getOrder();
            } else if ($event->hasObject()) {
                $order = $event->getObject();
            }

            $orderClass = Mage::getConfig()->getModelClassName('sales/order');
            if (!($order instanceof $orderClass) || $order->isEmpty()) {
                return $this;
            }

            if(Mage::registry('signifyd_action_' . $order->getIncrementId()) == 1) {
                return $this;
            }

            $this->getHelper()->buildAndSendOrderToSignifyd($order);
        } catch (Exception $e) {
            $this->getHelper()->log($e->__toString());
        }
        // If we get here, then we have failed to create the case.
        return $this;
    }

    public function logData($order, $payment, $quote)
    {
        // Used to capture data for testing with

        $order_data = json_encode($order->getData());
        $billing_data = json_encode($order->getBillingAddress()->getData());
        $shipping_data = json_encode($order->getShippingAddress()->getData());
        $customer_data = json_encode($order->getCustomer()->getData());
        $payment_data = json_encode($payment->getData());
        $quote_data = json_encode($quote->getData());
        $items = array();
        $products = array();

        foreach ($quote->getAllItems() as $item) {
            $items[$item->getId()] = $item->getData();
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            $products[$item->getId()] = $product->getData();
        }

        $items = json_encode($items);
        $products = json_encode($products);

        Mage::log("Order:\n $order_data", null, 'signifyd_connect_objects.log');
        Mage::log("Billing:\n $billing_data", null, 'signifyd_connect_objects.log');
        Mage::log("Shipping:\n $shipping_data", null, 'signifyd_connect_objects.log');
        Mage::log("Customer:\n $customer_data", null, 'signifyd_connect_objects.log');
        Mage::log("Payment:\n $payment_data", null, 'signifyd_connect_objects.log');
        Mage::log("Quote:\n $quote_data", null, 'signifyd_connect_objects.log');
        Mage::log("Items:\n $items", null, 'signifyd_connect_objects.log');
        Mage::log("Products:\n $products", null, 'signifyd_connect_objects.log');
    }

    public function getAdminRoute()
    {
        $route = false;

        try {
            // 1.4.0.0 support means we need to hard code these paths
            if ((bool)(string)Mage::getConfig()->getNode('default/admin/url/use_custom_path')) {
                $route = Mage::getConfig()->getNode('default/admin/url/custom_path');
            } else {
                $route = Mage::getConfig()->getNode('admin/routers/adminhtml/args/frontName');
            }
        } catch (Exception $e) {

        }

        if (!$route) {
            $route = 'admin';
        }

        return $route;
    }

    public function eavCollectionAbstractLoadBefore($observer)
    {
        $x = $observer->getCollection();

        $request = Mage::app()->getRequest();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();

        if ($module != $this->getAdminRoute() || $controller != 'sales_order') {
            return;
        }

        $clss = get_class($x);
        if ($clss == 'Mage_Sales_Model_Mysql4_Order_Collection' || $clss == 'Mage_Sales_Model_Mysql4_Order_Grid_Collection') {
            $observer->setOrderGridCollection($x);
            return $this->salesOrderGridCollectionLoadBefore($observer);
        }
    }

    public function coreCollectionAbstractLoadBefore($observer)
    {
        $x = $observer->getCollection();

        $request = Mage::app()->getRequest();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();

        if ($module != $this->getAdminRoute() || $controller != 'sales_order') {
            return;
        }

        $clss = get_class($x);

        if ($clss == 'Mage_Sales_Model_Mysql4_Order_Collection' || $clss == 'Mage_Sales_Model_Mysql4_Order_Grid_Collection') {
            $observer->setOrderGridCollection($x);
            return $this->salesOrderGridCollectionLoadBefore($observer);
        }
    }

    public function isCe()
    {
        return !@class_exists('Enterprise_Cms_Helper_Data');
    }

    public function oldSupport()
    {
        $version = Mage::getVersion();

        if ($this->isCe()) {
            return version_compare($version, '1.4.1.0', '<');
        } else {
            return version_compare($version, '1.10.0.0', '<');
        }

        return false;
    }

    public function belowSix()
    {
        $version = Mage::getVersion();

        if ($this->isCe()) {
            return version_compare($version, '1.6.0.0', '<');
        } else {
            return version_compare($version, '1.11.0.0', '<');
        }

        return false;
    }

    public function salesOrderGridCollectionLoadBefore($observer)
    {
        $request = Mage::app()->getRequest();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();

        if ($module != $this->getAdminRoute() || $controller != 'sales_order') {
            return;
        }

        $collection = $observer->getOrderGridCollection();
        $select = $collection->getSelect();

		// This will prevent us from rejoining.
        if(strchr($select, 'signifyd')) {
            return;
        }

        if ($this->oldSupport()) {
            $select->joinLeft(array(
                'signifyd'=>$collection->getTable('signifyd_connect/case')),
                'signifyd.order_increment=e.increment_id',
                array('score' => 'score')
            );
            $this->joins++;
        } else {
            $select->joinLeft(array(
                'signifyd'=>$collection->getTable('signifyd_connect/case')),
                'signifyd.order_increment=main_table.increment_id',
                array('score' => 'score', 'guarantee' => 'guarantee', 'entries' => 'entries')
            );
            $this->joins++;
        }
    }

    public function coreBlockAbstractToHtmlBefore(Varien_Event_Observer $observer)
    {
        $request = Mage::app()->getRequest();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();

        if ($module != $this->getAdminRoute() || $controller != 'sales_order') {
            return;
        }

        $helper = $this->getHelper();
        $block = $observer->getEvent()->getBlock();

        if ($block->getId() == 'sales_order_grid') {
            $block->addColumnAfter(
                'score',
                array(
                    'header' => $helper->__('Signifyd Score'),
                    'align' => 'left',
                    'type' => 'text',
                    'index' => 'score',
                    'filter' => false,
                    'renderer' => 'signifyd_connect/renderer',
                    'width' => '100px',
                ),
                'status'
            );
            $block->addColumnAfter(
                'guarantee',
                array(
                    'header' => $helper->__('Guarantee Status'),
                    'align' => 'left',
                    'type' => 'text',
                    'index' => 'guarantee',
                    'filter' => false,
                    'renderer' => 'signifyd_connect/renderer',
                    'width' => '100px',
                ),
                'status'
            );
            $block->sortColumnsByOrder();
        }
    }

    public function handleCancel($order)
    {
        $helper = $this->getHelper();
        $case = Mage::getModel('signifyd_connect/case')->load($order);
        if($case->isObjectNew()) {
            $helper->log("Guarantee cancel: Signifyd case for order $order does not exist in DB");
            return;
        }
        if($case->getGuarantee() == 'N/A' || $case->getGuarantee() == 'DECLINED') {
            $helper->log("Guarantee cancel: Skipped. No guarantee active");
            return;
        }

        $helper->log("Guarantee cancel for case " . $case->getCode());
        $helper->cancelGuarantee($case);
    }

    public function salesOrderPaymentCancel($observer)
    {
        $helper = $this->getHelper();
        try {
            $event = $observer->getEvent();
            if($event->getPayment()->getOrder()) {
                $order = $event->getPayment()->getOrder()->getIncrementId();
            } else {
                $helper->log("Event salesOrderPaymentCancel has no order");
                return;
            }
            $this->handleCancel($order);
        } catch(Exception $ex) {
            $helper->log("Guarantee cancel: $ex");
        }
    }

    /**
     * Putting an order on hold after the order was placed until the response comes back and an action can be taken.
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function putOrderOnHold(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        //PayPal express can't be held before everything is processed or it won't send confirmation e-mail to customer
        //Also there is no different status before the process is complete as is with PayFlow Link
        if (!in_array($order->getPayment()->getMethod(), array('paypal_express'))) {
            $this->getOrderModel()->holdOrder($order, 'after order place');
        }

        return $this;
    }

    /**
     * For some payment methods it is not possible to put the order on hold at order create moment
     * To be able to put the order on hold after the payment is processed it is needed to save the increment_id
     * field from checkout/session, to keep it on the same workflow as the original payment method extension
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function saveLastIncrementId(Varien_Event_Observer $observer)
    {
        $incrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        if (!empty($incrementId)) {
            Mage::register('signifyd_last_increment_id', $incrementId);
        }

            return $this;
        }

    /**
     * Put the order on hold asynchronously. Used for payment geteways that can't have the order on hold
     * at the order creation
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function putOrderOnHoldAsync(Varien_Event_Observer $observer)
    {
        $incrementId = Mage::registry('signifyd_last_increment_id');
        if (empty($incrementId)) {
            $incrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        }
        $this->getHelper()->log("putOrderOnHoldAsync: " . $incrementId);

        if (!empty($incrementId)) {
            Mage::unregister('signifyd_last_increment_id');

            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($incrementId);

            if (!$order->isEmpty()) {
                $acceptedFromGuarantyAction = $this->getHelper()->getAcceptedFromGuaranty($order->getStoreId());
                $declinedFromGuaranty = $this->getHelper()->getDeclinedFromGuaranty();
                $this->getHelper()->log("Accepted From Guaranty: {$acceptedFromGuarantyAction}");
                $this->getHelper()->log("Declined From Guaranty: {$declinedFromGuaranty}");

                if ($acceptedFromGuarantyAction == 1 || $declinedFromGuaranty == 2) {
                    /** @var Signifyd_Connect_Model_Case $case */
                    $case = Mage::getModel('signifyd_connect/case')->load($incrementId);

                    if (!$case->isEmpty()) {
                        // If the configuration is set to 'Update status to processing' on approval
                        // and the case it is already APPROVED, doesn't need to hold
                        if ($acceptedFromGuarantyAction == 1 && $case->getGuarantee() == 'APPROVED') {
                            return $this;
                        }

                        // If the configuration is set to 'Update status to canceled' when declined
                        // and the case it is already DECLINED, doesn't need to hold
                        if ($declinedFromGuaranty == 2 && $case->getGuarantee() == 'DECLINED') {
                            return $this;
                        }
                    }
                }

                $this->getOrderModel()->holdOrder($order, 'after order place');
            }
        }

        return $this;
    }
}
