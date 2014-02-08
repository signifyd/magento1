<?php

class Signifyd_Connect_Model_Observer extends Varien_Object
{
    public $customer = null;
    public $order = null;
    public $payment = null;
    public $quote = null;
    public $shipping_address = null;
    public $billing_address = null;
    
    public function getProducts()
    {
        $products = array();
        $helper = Mage::helper('signifyd_connect');
        
        foreach ($this->quote->getAllItems() as $item) {
            if (!$item->getProductType() || $item->getProductType() == 'simple') {
                $product_object = $item->getData('product');
                
                if (!$product_object || !$product_object->getId()) {
                    $product_object = Mage::getModel('catalog/product')->load($item->getProductId());
                }
                
                if ($product_object) {
                    $product = array();
                    
                    $product['itemId'] = $item->getSku();
                    $product['itemName'] = $item->getName();
                    $product['itemUrl'] = $helper->getProductUrl($product_object);
                    $product['itemImage'] = $helper->getProductImage($product_object);
                    
                    $qty = 1;
                    if ($item->getQty()) {
                        $qty = $item->getQty();
                    } else if ($item->getQtyOrdered()) {
                        $qty = $item->getQtyOrdered();
                    }
                    
                    $price = 0;
                    if ($item->getBasePrice() > 0) {
                        $price = $item->getBasePrice();
                    } else if ($item->getPrice() > 0) {
                        $price = $item->getPrice();
                    } else if ($product_object->getData('price') > 0) {
                        $price = $product_object->getData('price');
                    } else {
                        $parent = $item->getData('parent');
                        
                        if (!$parent) {
                            $parent = $item->getParentItem();
                        }
                        
                        if ($parent) {
                            if ($parent->getBasePrice() > 0) {
                                $price = $parent->getBasePrice();
                            } else if ($parent->getPrice()) {
                                $price = $parent->getPrice();
                            }
                        }
                    }
                    
                    $weight = 0;
                    if ($item->hasWeight()) {
                        $weight = $item->getWeight();
                    } else if ($product_object->hasWeight()) {
                        $weight = $product_object->getWeight();
                    }
                    
                    $product['itemQuantity'] = intval($qty);
                    $product['itemPrice'] = floatval($price);
                    $product['itemWeight'] = floatval($weight);
                    
                    $products[] = $product;
                }
            }
        }
        
        return $products;
    }
    
    public function getIPAddress()
    {
        if ($this->order->getRemoteIp()) {
            if ($this->order->getXForwardedFor()) {
                return $this->order->getXForwardedFor();
            }
            
            return $this->order->getRemoteIp();
        }
        
        // Checks each configured value in app/etc/local.xml & falls back to REMOTE_ADDR. See app/etc/local.xml.additional for examples.
        return Mage::helper('core/http')->getRemoteAddr(false);
    }
    
    public function formatAvs($value)
    {
        // http://www.emsecommerce.net/avs_cvv2_response_codes.htm
        $codes = array('X', 'Y', 'A', 'W', 'Z', 'N', 'U', 'R', 'E', 'S', 'D', 'M', 'B', 'P', 'C', 'I', 'G');
        
        if ($value) {
            $value = strtoupper($value);
            
            if (strlen($value) > 1) {
                if (preg_match('/\([A-Z]\)/', $value)) {
                    $matches = array();
                    
                    preg_match('/\([A-Z]\)/', $value, $matches);
                    
                    foreach ($matches as $match) {
                        $match = preg_replace('/[^A-Z]/', '', $match);
                        
                        if (in_array($match, $codes)) {
                            $value = $match;
                        }
                    }
                }
            }
            
            if (strlen($value) > 1) {
                $value = substr($value, 0, 1);
            }
            
            if (!in_array($value, $codes)) {
                $value = null;
            }
        }
        
        return $value;
    }
    
    public function getAvsResponse()
    {
        $payment = $this->payment;
        
        $value = null;
        
        if ($payment->getAdditionalInformation('paypal_avs_code')) {
            $value = $payment->getAdditionalInformation('paypal_avs_code');
        } else if ($payment->getAdditionalInformation('cc_avs_status')) {
            $value = $payment->getAdditionalInformation('cc_avs_status');
        }
        
        return $this->formatAvs($value);
    }
    
    public function getCvvResponse()
    {
        $payment = $this->payment;
        
        if ($payment->getAdditionalInformation('paypal_cvv2_match')) {
            return $payment->getAdditionalInformation('paypal_cvv2_match');
        }
        
        return null;
    }
    
    public function getPaymentMethod()
    {
        return $this->payment->getMethod();
    }
    
    public function getPurchase()
    {
        $purchase = array();
        
        $purchase['browserIpAddress'] = $this->getIpAddress();
        $purchase['orderId'] = $this->order->getIncrementId();
        $purchase['createdAt'] = date('c', strtotime($this->order->getCreatedAt())); // e.g: 2004-02-12T15:19:21+00:00
        $purchase['currency'] = $this->order->getBaseCurrencyCode();
        $purchase['totalPrice'] = floatval($this->order->getGrandTotal());
        $purchase['shippingPrice'] = floatval($this->order->getShippingAmount());
        $purchase['products'] = $this->getProducts();
        $purchase['paymentGateway'] = $this->getPaymentMethod();
        
        $purchase['avsResponseCode'] = $this->getAvsResponse();
        $purchase['cvvResponseCode'] = $this->getCvvResponse();
        
        return $purchase;
    }
    
    public function getCard()
    {
        $payment = $this->payment;
        $billing = $this->billing_address;
        
        $card = array();
        
        $card['cardHolderName'] = null;
        $card['bin'] = null;
        $card['last4'] = null;
        $card['expiryMonth'] = null;
        $card['expiryYear'] = null;
        $card['hash'] = null;
        
        $card['billingAddress'] = $this->getBillingAddress();
        
        if ($payment->getData('cc_last4')) {
            $card['last4'] = $payment->getData('cc_last4');
        }
        
        if ($payment->getData('cc_exp_year')) {
            $card['expiryYear'] = $payment->getData('cc_exp_year');
        }
        
        if ($payment->getData('cc_exp_month')) {
            $card['expiryMonth'] = $payment->getData('cc_exp_month');
        }
        
        if ($payment->getData('cc_number_enc')) {
            $card['hash'] = $payment->getData('cc_number_enc');
        }
        
        if ($payment->getData('cc_number') && is_numeric($payment->getData('cc_number')) && strlen((string)$payment->getData('cc_number')) > 6) {
            $card['bin'] = substr((string)$payment->getData('cc_number'), 0, 6);
        }
        
        if ($payment->getCcOwner()) {
            $card['cardHolderName'] = $payment->getCcOwner();
        } else {
            $card['cardHolderName'] = $billing->getFirstname() . ' ' . $billing->getLastname();
        }
        
        return $card;
    }
    
    public function getAddress($address_object)
    {
        $address = array();
        
        $address['streetAddress'] = $address_object->getStreet1();
        $address['unit'] = null;
        
        if ($address_object->getStreet2()) {
            $address['unit'] = $address_object->getStreet2();
        }
        
        $address['city'] = $address_object->getCity();
        
        $address['provinceCode'] = $address_object->getRegionCode();
        $address['postalCode'] = $address_object->getPostcode();
        $address['countryCode'] = $address_object->getCountryId();
        
        $address['latitude'] = null;
        $address['longitude'] = null;
        
        return $address;
    }
    
    public function getBillingAddress()
    {
        return $this->getAddress($this->billing_address);
    }
    
    public function getShippingAddress()
    {
        return $this->getAddress($this->shipping_address);
    }
    
    public function getRecipient()
    {
        $recipient = array();
        
        $recipient['fullName'] = $this->shipping_address->getFirstname() . ' ' . $this->shipping_address->getLastname();
        // Email: Note that this field is always the same for both addresses
        $recipient['confirmationEmail'] = $this->shipping_address->getEmail();
        if (!$recipient['confirmationEmail']) {
            $recipient['confirmationEmail'] = $this->order->getCustomerEmail();
        }
        $recipient['confirmationPhone'] = $this->shipping_address->getTelephone();
        
        $recipient['deliveryAddress'] = $this->getShippingAddress();
        
        return $recipient;
    }
    
    public function getUserAccount()
    {
        $customer = $this->customer;
        
        $user = array(
            "emailAddress" => null,
            "username" => null,
            "phone" => null,
            "createdDate" => null,
            "accountNumber" => null,
            "lastOrderId" => null,
            "aggregateOrderCount" => null,
            "aggregateOrderDollars" => null,
            "lastUpdateDate" => null
        );
        
        if ($customer && $customer->getId()) {
            $user['emailAddress'] = $customer->getEmail();
            
            $user['phone'] = $this->billing_address->getTelephone();
            
            $user['createdDate'] = date('c', strtotime($customer->getCreatedAt()));
            $user['lastUpdateDate'] = date('c', strtotime($customer->getUpdatedAt()));
            
            $user['accountNumber'] = $customer->getId();
            
            $last_order_id = null;
            
            $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('customer_id', $customer->getId());
            $orders->getSelect()->order('created_at DESC');
            
            $aggregate_total = 0.;
            $order_count = 0;
            
            foreach ($orders as $order) {
                if ($last_order_id === null) {
                    $last_order_id = $order->getIncrementId();
                }
                
                $aggregate_total += floatval($order->getGrandTotal());
                $order_count += 1;
            }
            
            $user['lastOrderId'] = $last_order_id;
            $user['aggregateOrderCount'] = $order_count;
            $user['aggregateOrderDollars'] = floatval($aggregate_total);
        }
        
        return $user;
    }
    
    public function getUrl()
    {
        if ($this->hasData('url')) {
            return $this->getData('url');
        }
        
        return Mage::getStoreConfig('signifyd_connect/settings/url') . '/cases';
    }
    
    public function getAuth()
    {
        if ($this->hasData('auth')) {
            return $this->getData('auth');
        }
        
        return Mage::getStoreConfig('signifyd_connect/settings/key');
    }
    
    public function submitCase($case)
    {
        $case = json_encode($case);
        
        return Mage::helper('signifyd_connect')->request($this->getUrl(), $case, $this->getAuth(), 'application/json');
    }
    
    public function generateCase()
    {
        $case = array();
        
        $case['purchase'] = $this->getPurchase();
        $case['recipient'] = $this->getRecipient();
        $case['card'] = $this->getCard();
        $case['userAccount'] = $this->getUserAccount();
        
        return $case;
    }
    
    public function getInvoiced($order)
    {
        $collection = $order->getInvoiceCollection();
        
        foreach ($collection as $invoice) {
            return true;
        }
        
        return false;
    }
    
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
            
            if ($order && $order->getId()) {
                if (Mage::helper('signifyd_connect')->isProcessed($order) && !$this->getForceProcess()) {
                    return;
                }
                
                $payments = $order->getPaymentsCollection();
                
                foreach ($payments as $payment) {
                    $this->payment = $payment;
                }
                
                $method = $this->payment->getMethod();
                
                $state = $order->getState();
                
                if (!$state || $state == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    return;
                }
                
                $this->order = $order;
                $this->billing_address = $order->getBillingAddress();
                $this->shipping_address = $order->getShippingAddress();
                
                if (!$this->shipping_address) {
                    $this->shipping_address = $this->billing_address;
                }
                
                if ($order->getCustomer()) {
                    $this->customer = $order->getCustomer();
                }
                
                $this->quote = $order->getQuote();
                
                if (!$this->quote) {
                    $this->quote = $order;
                }
                
                $case = $this->generateCase();
                
                $response = $this->submitCase($case);
                
                $case_object = Mage::helper('signifyd_connect')->markProcessed($order);
                
                try {
                    $response_data = json_decode($response->getRawResponse(), true);
                    $case_object->setCode($response_data['investigationId']);
                    $case_object->save();
                } catch (Exception $e) {
                    
                }
            }
        } catch (Exception $e) {
            Mage::log($e->__toString(), null, 'signifyd_connect.log');
        }
    }
    
    public function logData()
    {
        // Used to capture data for testing with
        
        $order_data = json_encode($this->order->getData());
        $billing_data = json_encode($this->order->getBillingAddress()->getData());
        $shipping_data = json_encode($this->order->getShippingAddress()->getData());
        $customer_data = json_encode($this->customer->getData());
        $payment_data = json_encode($this->payment->getData());
        $quote_data = json_encode($this->quote->getData());
        $items = array();
        $products = array();
        
        foreach ($this->quote->getAllItems() as $item) {
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
    
    public function eavCollectionAbstractLoadBefore($observer)
    {
        $x = $observer->getCollection();
        
        $request = Mage::app()->getRequest();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();
        $action = $request->getActionName();
        
        if ($module != 'admin' || $controller != 'sales_order') {
            return;
        }
        
        $clss = get_class($x);
        if ($clss == 'Mage_Sales_Model_Mysql4_Order_Collection') {
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
        $action = $request->getActionName();
        
        if ($module != 'admin' || $controller != 'sales_order') {
            return;
        }
        
        $clss = get_class($x);
        if ($clss == 'Mage_Sales_Model_Mysql4_Order_Collection') {
            $observer->setOrderGridCollection($x);
            return $this->salesOrderGridCollectionLoadBefore($observer);
        }
    }
    
    public function isCe()
    {
        return !@class_exists('Enterprise_Cms_Helper_Data');
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
        $collection = $observer->getOrderGridCollection();
        $select = $collection->getSelect();
        
        if ($this->belowSix()) {
            $select->joinLeft(array('signifyd'=>$collection->getTable('signifyd_connect/case')), 'signifyd.order_increment=e.increment_id', array('score'=>'score'));
        } else {
            $select->joinLeft(array('signifyd'=>$collection->getTable('signifyd_connect/case')), 'signifyd.order_increment=main_table.increment_id', array('score'=>'score'));
        }
    }
    
    public function coreBlockAbstractToHtmlBefore(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfig('signifyd_connect/settings/retrieve_score')) {
            $helper	= Mage::helper('signifyd_connect');
            $block = $observer->getEvent()->getBlock();
            
            if ($block->getId() == 'sales_order_grid') {
                $block->addColumnAfter(
                    'score',
                    array(
                        'header' => $helper->__('Signifyd Score'),
                        'align' => 'left',
                        'type' => 'text',
                        'index' => 'score',
                        'filter_index' => 'score',
                        'width' => '100px',
                    ),
                    'status'
                );
                
                $block->sortColumnsByOrder();
            }
        }
    }
}
