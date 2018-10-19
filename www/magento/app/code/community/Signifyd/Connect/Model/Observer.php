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

    public function openCaseCheckout($observer)
    {
        $this->getHelper()->log('openCaseCheckout');
        return $this->openCase($observer);
    }

    public function openCaseOrderSave($observer)
    {
        $this->getHelper()->log('openCaseOrderSave');
        return $this->openCase($observer);
    }

    public function updateCaseAddressSave($observer)
    {
        $this->getHelper()->log('updateCaseAddressSave');

        /** @var Mage_Sales_Model_Order_Address $orderAddress */
        $orderAddress = $observer->getAddress();
        $observer->getEvent()->setOrder($orderAddress->getOrder());

        return $this->openCase($observer, true);
    }

    public function updateCasePaymentSave($observer)
    {
        $this->getHelper()->log('updateCasePaymentSave');

        /** @var Mage_Sales_Model_Order_Payment $orderPayment */
        $orderPayment = $observer->getPayment();
        $observer->getEvent()->setOrder($orderPayment->getOrder());

        return $this->openCase($observer, true);
    }

    public function openCase($observer, $updateOnly = false)
    {
        try {
            $orders = array();

            if ($observer->getEvent()->hasOrder()) {
                // Onepage checkout and API
                $orders[] = $observer->getEvent()->getOrder();
            } elseif ($observer->getEvent()->hasOrders()) {
                // Multishipping
                $orders = $observer->getEvent()->getOrders();
            } else {
                // Look for registry key, for methods that open case on other events than sales_order_place_after
                $incrementId = Mage::registry('signifyd_last_increment_id');
                Mage::unregister('signifyd_last_increment_id');

                if (empty($incrementId)) {
                    $incrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
                }

                if (!empty($incrementId)) {
                    $orders[] = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
                }
            }

            /** @var Mage_Sales_Model_Order $order */
            foreach ($orders as $order) {
                try {
                    if (!is_object($order) || $order->isEmpty()) {
                        $this->getHelper()->log('Empty order passed to observer to open case. Ignoring order.');
                        continue;
                    }

                    $originStoreCode = $order->getData('origin_store_code');
                    if (empty($originStoreCode)) {
                        $request = Mage::app()->getFrontController()->getRequest();

                        if (stripos($request->getRequestUri(), '/api/') === false) {
                            $order->setData('origin_store_code', Mage::app()->getStore()->getCode());
                        }
                    }

                    if (is_null(Mage::registry('signifyd_action_' . $order->getIncrementId()))) {
                        Mage::register('signifyd_action_' . $order->getIncrementId(), 1); // Avoid recurssions
                    } else {
                        // Order already been processed, ignore it
                        continue;
                    }

                    $eventName = $observer->getEvent()->getName();
                    $this->getHelper()->log("Order {$order->getIncrementId()} state: {$order->getState()}, event: {$eventName}");

                    $result = $this->getHelper()->buildAndSendOrderToSignifyd($order, false, $updateOnly);
                    $this->getHelper()->log("Create case result for " . $order->getIncrementId() . ": {$result}");

                    //PayPal express can't be held before everything is processed or it won't send confirmation e-mail to customer
                    //Also there is no different status before the process is complete as is with PayFlow
                    $asyncHoldMethods = array('paypal_express', 'payflow_link', 'payflow_advanced');
                    if ($result == "sent" && !in_array($order->getPayment()->getMethod(), $asyncHoldMethods)) {
                        $this->putOrderOnHold($order);
                    }
                } catch (Exception $e) {
                    $incrementId = $order->getIncrementId();
                    $incrementId = empty($incrementId) ? '' : " $incrementId";
                    $this->getHelper()->log("Failed to open case for order{$incrementId}: " . $e->__toString());
                }

                if (!is_null(Mage::registry('signifyd_action_' . $order->getIncrementId()))) {
                    Mage::unregister('signifyd_action_' . $order->getIncrementId()); // Avoid recurssions
                }
            }
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
            $select->joinLeft(
                array(
                'signifyd'=>$collection->getTable('signifyd_connect/case')),
                'signifyd.order_increment=e.increment_id',
                array('score' => 'score')
            );
            $this->joins++;
        } else {
            $select->joinLeft(
                array(
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

    public function handleCancel($incrementId)
    {
        $helper = $this->getHelper();

        $case = Mage::getModel('signifyd_connect/case')->load($incrementId);
        if ($case->isObjectNew()) {
            $helper->log("Guarantee cancel: Signifyd case for order {$incrementId} does not exist in DB");
            return;
        }

        if (in_array($case->getGuarantee(), array('N/A', 'DECLINED', 'CANCELED'))) {
            $helper->log('Guarantee cancel skipped, because case guarantee is ' . $case->getGuarantee());
            return;
        }

        $helper->log("Guarantee cancel for case " . $case->getCode());
        $helper->cancelGuarantee($case);
    }

    public function salesOrderPaymentCancel($observer)
    {
        $helper = $this->getHelper();

        try {
            /** @var Mage_Sales_Model_Order_Payment $payment */
            $payment = $observer->getEvent()->getPayment();

            if ($payment instanceof Mage_Sales_Model_Order_Payment) {
                $order = $payment->getOrder();

                if ($order instanceof Mage_Sales_Model_Order) {
                    $this->handleCancel($order->getIncrementId());
                }
            } else {
                $helper->log("Event salesOrderPaymentCancel has no order");
                return;
            }
        } catch (Exception $e) {
            $helper->log("Guarantee cancel: " . $e->getMessage());
        }
    }

    public function salesOrderCreditmemoRefund(Varien_Event_Observer $observer)
    {
        $helper = $this->getHelper();

        try {
            /** @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
            $creditmemo = $observer->getEvent()->getCreditmemo();

            if ($creditmemo instanceof Mage_Sales_Model_Order_Creditmemo) {
                $order = $creditmemo->getOrder();

                if ($order->canCreditmemo()) {
                    $helper->log("Order {$order->getIncrementId()} still can be refunded, case will not be cancelled");
                    return;
                }

                if ($order instanceof Mage_Sales_Model_Order) {
                    $this->handleCancel($order->getIncrementId());
                }
            } else {
                $helper->log("Event salesOrderCreditmemoCancel has no order");
                return;
            }
        } catch(Exception $e) {
            $helper->log("Guarantee cancel: " . $e->getMessage());
        }
    }

    /**
     * Putting an order on hold after the order was placed until the response comes back and an action can be taken.
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function putOrderOnHold(Mage_Sales_Model_Order $order)
    {
        if (!$order->isEmpty()) {
            /** @var Signifyd_Connect_Model_Case $case */
            $case = Mage::getModel('signifyd_connect/case')->load($order->getIncrementId());

            if ($case->isEmpty() || $case->getMagentoStatus() != Signifyd_Connect_Model_Case::COMPLETED_STATUS) {
                $this->getOrderModel()->holdOrder($order, 'after order place');
            }
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

            $this->putOrderOnHold($order);
        }

        return $this;
    }
}
