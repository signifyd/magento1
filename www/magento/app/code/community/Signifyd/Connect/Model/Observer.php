<?php

class Signifyd_Connect_Model_Observer extends Varien_Object
{
    public $joins = 0;
    
    public function openCase($observer)
    {
        try {
            if (!Mage::getStoreConfig('signifyd_connect/settings/enabled') && !$this->getEnabled()) {
                return;
            }
            
            $event = $observer->getEvent();
            
            if ($event->hasOrder()) {
                $order = $event->getOrder();
            } else if ($event->hasObject()) {
                $order = $event->getObject();
            }
            
            $order_model = get_class(Mage::getModel('sales/order'));
            
            if (!($order instanceof $order_model)) {
                return;
            }

            Mage::helper('signifyd_connect')->buildAndSendOrderToSignifyd($order);

        } catch (Exception $e) {
            Mage::log($e->__toString(), null, 'signifyd_connect.log');
        }
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
        if ($this->joins === 0) {
            $request = Mage::app()->getRequest();
            $module = $request->getModuleName();
            $controller = $request->getControllerName();
            
            if ($module != $this->getAdminRoute() || $controller != 'sales_order') {
                return;
            }
            
            $collection = $observer->getOrderGridCollection();
            $select = $collection->getSelect();

            $show_scores = Mage::getStoreConfig('signifyd_connect/advanced/show_scores');
            $show_guarantee = Mage::getStoreConfig('signifyd_connect/advanced/show_guarantee');
            if ($show_scores || $show_guarantee) {
                if ($this->oldSupport()) {
                    $select->joinLeft(array('signifyd'=>$collection->getTable('signifyd_connect/case')), 'signifyd.order_increment=e.increment_id', array('score'=>'score'));
                    $this->joins++;
                } else {
                    $select->joinLeft(array('signifyd'=>$collection->getTable('signifyd_connect/case')), 'signifyd.order_increment=main_table.increment_id', array('score'=>'score',
                        'guarantee' => 'guarantee'));
                    $this->joins++;
                }
            }
        }
    }

    public function coreBlockAbstractPrepareLayoutBefore(Varien_Event_Observer $observer)
    {
        if (!Mage::getStoreConfig('signifyd_connect/settings/enabled') && !$this->getEnabled()) {
            return;
        }

        $block = $observer->getEvent()->getBlock();
        if(get_class($block) =='Mage_Adminhtml_Block_Widget_Grid_Massaction'
            && $block->getRequest()->getControllerName() == 'sales_order')
        {
            $block->setFormFieldName('increment_id');
            $block->addItem('signifyd_connect', array(
                'label' => 'Send order(s) to Signifyd',
                'url' => Mage::helper("adminhtml")->getUrl('signifyd/adminhtml_signifyd/send'),
            ));
        }
    }
    
    public function coreBlockAbstractToHtmlBefore(Varien_Event_Observer $observer)
    {
        $show_scores = Mage::getStoreConfig('signifyd_connect/advanced/show_scores');
        $show_guarantee = Mage::getStoreConfig('signifyd_connect/advanced/show_guarantee');
        if ($show_scores || $show_guarantee) {
            $request = Mage::app()->getRequest();
            $module = $request->getModuleName();
            $controller = $request->getControllerName();
            
            if ($module != $this->getAdminRoute() || $controller != 'sales_order') {
                return;
            }
            
            $helper = Mage::helper('signifyd_connect');
            $block = $observer->getEvent()->getBlock();
            
            if ($block->getId() == 'sales_order_grid') {
                if($show_scores) {
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
                }
                if($show_guarantee) {
                    $block->addColumnAfter(
                        'guarantee',
                        array(
                            'header' => $helper->__('Guarantee Status'),
                            'align' => 'left',
                            'type' => 'text',
                            'index' => 'guarantee',
                            'filter' => false,
                            'width' => '100px',
                        ),
                        'status'
                    );
                }
                $block->sortColumnsByOrder();
            }
        }
    }
}
