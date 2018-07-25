<?php

require_once '../../../../../Mage.php';
 
class Signifyd_Connect_SignifydTest extends PHPUnit_Framework_TestCase
{
    protected $products = null;
    protected $items = null;
    protected $customer = null;
    protected $quote = null;
    protected $order = null;
    protected $payment = null;
    protected $shipping = null;
    protected $billing = null;
    
    protected $customer_data = null;
    protected $quote_data = null;
    protected $order_data = null;
    protected $payment_data = null;
    protected $shipping_data = null;
    protected $billing_data = null;
    protected $helper = null;
    
    public function resetData()
    {
        $this->products = null;
        $this->items = null;
        $this->customer = null;
        $this->quote = null;
        $this->order = null;
        $this->payment = null;
        $this->shipping = null;
        $this->billing = null;
        
        $this->customer_data = null;
        $this->quote_data = null;
        $this->order_data = null;
        $this->payment_data = null;
        $this->shipping_data = null;
        $this->billing_data = null;
    }
    
    public function initData()
    {
        $this->order = Mage::getModel('sales/order')->addData($this->order_data);
        $this->quote = Mage::getModel('sales/quote')->addData($this->quote_data);
        if ($this->customer_data && is_array($this->customer_data) && count($this->customer_data)) {
            $this->customer = Mage::getModel('customer/customer')->addData($this->customer_data);
        }

        $this->billing = Mage::getModel('sales/order_address')->addData($this->billing_data);
        $this->shipping = Mage::getModel('sales/order_address')->addData($this->shipping_data);
        $this->payment = Mage::getModel('sales/order_payment')->addData($this->payment_data);
        
        $this->order->addPayment($this->payment);
        $this->order->setBillingAddress($this->billing);
        $this->order->setShippingAddress($this->shipping);
        $this->order->setCustomer($this->customer);
        $this->order->setQuote($this->quote);
        
        foreach ($this->quote->getItemsCollection() as $itemId => $item) {
            $this->quote->getItemsCollection()->removeItemByKey($itemId);
        }
        
        $items = array();
        $products = array();
        
        foreach ($this->products_data as $item_id => $product_data) {
            $product = Mage::getModel('catalog/product')->addData($product_data);
            $products[$item_id] = $product;
        }
        
        foreach ($this->items_data as $item_id => $item_data) {
            $item = Mage::getModel('sales/quote_item')->addData($item_data);
            
            if (isset($products[$item_id])) {
                $item->setData('product', $products[$item_id]);
            }
            
            $this->quote->getItemsCollection()->addItem($item);
            
            $items[$item_id] = $item;
            
            if ($item->getParentItemId()) {
                if (in_array($item->getParentItemId(), array_keys($items))) {
                    $item->setData('parent', $items[$item->getParentItemId()]);
                }
            }
        }

//        $this->model->order = $this->order;
//        $this->model->billing_address = $this->billing;
//        $this->model->shipping_address = $this->shipping;
//        $this->model->customer = $this->customer;
//        $this->model->payment = $this->payment;
//        $this->model->quote = $this->quote;
    }
    
    public function clearHistory()
    {
        Mage::getModel('signifyd_connect/case')->getCollection()->delete();
    }
    
    public function setUp()
    {
        Mage::app();
        $this->model = Mage::getModel('signifyd_connect/observer');
        $this->helper = Mage::helper('signifyd_connect');

        $this->defaultData();
        $this->initData();
    }
    
    public function testRecipient()
    {
        $this->defaultData();
        $this->initData();

//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);

        $this->assertEquals("Frank Guest", $case['recipient']['fullName']);
        $this->assertEquals("guest@example.com", $case['recipient']['confirmationEmail']);
        $this->assertEquals("1234123", $case['recipient']['confirmationPhone']);
        $this->assertEquals("10 Guest St", $case['recipient']['deliveryAddress']['streetAddress']);
        $this->assertEquals(null, $case['recipient']['deliveryAddress']['unit']);
        $this->assertEquals("Testville", $case['recipient']['deliveryAddress']['city']);
        $this->assertEquals("VT", $case['recipient']['deliveryAddress']['provinceCode']);
        $this->assertEquals("123412", $case['recipient']['deliveryAddress']['postalCode']);
        $this->assertEquals("US", $case['recipient']['deliveryAddress']['countryCode']);

    }
    
    public function testPurchase()
    {
        $this->defaultData();
        $this->initData();
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);

        $this->assertEquals("127.0.0.1", $case['purchase']['browserIpAddress']);
        $this->assertEquals("100000033", $case['purchase']['orderId']);
        $this->assertEquals("USD", $case['purchase']['currency']);
        $this->assertEquals(203.0, $case['purchase']['totalPrice']);
        $this->assertEquals(5, $case['purchase']['shippingPrice']);
        $this->assertEquals("checkmo", $case['purchase']['paymentGateway']);
    }
    
    public function testOneProduct()
    {
        $this->defaultData();
        $this->initData();
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);
        
        $this->assertEquals(1, count($case['purchase']['products']));
        $this->assertEquals("amuletum-ring-sterling-silver", $case['purchase']['products'][0]['itemId']);
        $this->assertEquals("Amuletum Ring: Recycled Sterling Silver", $case['purchase']['products'][0]['itemName']);
        // The URL may vary depending on the testing environment, so images & links are not asserted here
        $this->assertEquals(1, $case['purchase']['products'][0]['itemQuantity']);
        $this->assertEquals(198, $case['purchase']['products'][0]['itemPrice']);
        $this->assertEquals(0, $case['purchase']['products'][0]['itemWeight']);
    }
    
    public function testMultipleProducts()
    {
        $this->defaultData();
        $this->initData();
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);
        
        $this->assertEquals(1, count($case['purchase']['products']));
        $this->assertEquals("amuletum-ring-sterling-silver", $case['purchase']['products'][0]['itemId']);
        $this->assertEquals("Amuletum Ring: Recycled Sterling Silver", $case['purchase']['products'][0]['itemName']);
        $this->assertEquals(1, $case['purchase']['products'][0]['itemQuantity']);
        $this->assertEquals(198, $case['purchase']['products'][0]['itemPrice']);
        $this->assertEquals(0, $case['purchase']['products'][0]['itemWeight']);
    }
    
    public function testConfigurable()
    {
        $this->weightData();
        $this->initData();
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);
        
        $this->assertEquals(1, count($case['purchase']['products']));
        $this->assertEquals(1.0, $case['purchase']['products'][0]['itemWeight']);
    }
    
    public function testRunOnce()
    {
        $order = Mage::getModel('sales/order')->setIncrementId('999');
        $helper = Mage::helper('signifyd_connect');
        
        $helper->unmarkProcessed($order);
        
        $is_processed = $helper->processedStatus($order);
        $this->assertEquals(0, $is_processed);
        
        $helper->markProcessed($order);
        
        $is_processed = $helper->processedStatus($order);
        $this->assertEquals(1, $is_processed);
        
        $helper->unmarkProcessed($order);
        
        $is_processed = $helper->processedStatus($order);
        $this->assertEquals(0, $is_processed);
    }
    
    public function testNoCard()
    {
        $this->defaultData();
        $this->initData();
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);
        
        $this->assertEquals("Frank Guest", $case['card']['cardHolderName']);
        $this->assertEquals(null, $case['card']['last4']);
        $this->assertEquals(null, $case['card']['expiryMonth']);
        $this->assertEquals(null, $case['card']['expiryYear']);
        $this->assertEquals(null, $case['card']['bin']);
        
        $this->assertEquals("10 Guest St", $case['card']['billingAddress']['streetAddress']);
        $this->assertEquals(null, $case['card']['billingAddress']['unit']);
        $this->assertEquals("Testville", $case['card']['billingAddress']['city']);
        $this->assertEquals("VT", $case['card']['billingAddress']['provinceCode']);
        $this->assertEquals("123412", $case['card']['billingAddress']['postalCode']);
        $this->assertEquals("US", $case['card']['billingAddress']['countryCode']);
    }
    
    public function testCard()
    {
        $this->ccData();
        $this->initData();
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);

        $this->assertEquals("A", $case['card']['cardHolderName']);
        $this->assertEquals("1111", $case['card']['last4']);
        $this->assertEquals("1", $case['card']['expiryMonth']);
        $this->assertEquals("2015", $case['card']['expiryYear']);
        $this->assertEquals("444433", $case['card']['bin']);
    }
    
    public function testBin()
    {
        $this->binData();
        $this->initData();
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);
        
        $this->assertEquals("A", $case['card']['cardHolderName']);
        $this->assertEquals("1111", $case['card']['last4']);
        $this->assertEquals("1", $case['card']['expiryMonth']);
        $this->assertEquals("2015", $case['card']['expiryYear']);
        $this->assertEquals(null, $case['card']['bin']);
    }
    
    public function testNoUser()
    {
        $this->defaultData();
        $this->initData();
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);
        
        $this->assertEquals(null, $case['userAccount']['emailAddress']);
        $this->assertEquals(null, $case['userAccount']['username']);
        $this->assertEquals(null, $case['userAccount']['phone']);
        $this->assertEquals(null, $case['userAccount']['createdDate']);
        $this->assertEquals(null, $case['userAccount']['accountNumber']);
        $this->assertEquals(null, $case['userAccount']['lastOrderId']);
        $this->assertEquals(null, $case['userAccount']['aggregateOrderCount']);
        $this->assertEquals(null, $case['userAccount']['aggregateOrderDollars']);
        $this->assertEquals(null, $case['userAccount']['lastUpdateDate']);
    }
    
    public function testUser()
    {
        $this->userData();
        $this->initData();
        
//        $case = $this->model->generateCase();
        $case = $this->helper->generateCase($this->order, $this->payment, $this->customer);
        
        $this->assertEquals("guest@example.com", $case['userAccount']['emailAddress']);
        $this->assertEquals(null, $case['userAccount']['username']);
        $this->assertEquals("1234123", $case['userAccount']['phone']);
        $this->assertEquals("2", $case['userAccount']['accountNumber']);
        // The user doesn't really exist or have orders, so we don't assert the aggregate order value, last order & order count
    }
    
    public function defaultData()
    {
        $this->resetData();
        
        $this->order_data = json_decode('{"increment_id":"100000033","store_id":"1","quote_id":"40","quote":{},"customer":{},"remote_ip":"127.0.0.1","x_forwarded_for":null,"customer_id":null,"customer_email":"guest@example.com","customer_prefix":null,"customer_firstname":"Frank","customer_middlename":null,"customer_lastname":"Guest","customer_suffix":null,"customer_group_id":0,"customer_tax_class_id":"3","customer_note":null,"customer_note_notify":"1","customer_is_guest":true,"customer_dob":null,"customer_taxvat":null,"customer_gender":null,"quote_base_grand_total":203,"global_currency_code":"USD","base_currency_code":"USD","store_currency_code":"USD","order_currency_code":"USD","store_to_base_rate":"1.0000","store_to_order_rate":"1.0000","base_to_global_rate":"1.0000","base_to_order_rate":"1.0000","coupon_code":null,"is_virtual":0,"is_multi_payment":null,"applied_rule_ids":"","total_qty_ordered":1,"gift_message_id":null,"weight":0,"shipping_method":"flatrate_flatrate","shipping_description":"Flat Rate - Fixed","shipping_rate":null,"subtotal":198,"tax_amount":0,"tax_string":null,"discount_amount":0,"shipping_amount":5,"shipping_incl_tax":5,"shipping_tax_amount":0,"custbalance_amount":null,"grand_total":203,"base_subtotal":198,"base_tax_amount":0,"base_discount_amount":0,"base_shipping_amount":"5.0000","base_shipping_incl_tax":5,"base_shipping_tax_amount":0,"base_custbalance_amount":null,"base_grand_total":203,"hidden_tax_amount":0,"base_hidden_tax_amount":0,"shipping_hidden_tax_amount":0,"base_shipping_hidden_tax_amount":0,"base_shipping_hidden_tax_amnt":0,"discount_description":"","shipping_discount_amount":0,"base_shipping_discount_amount":0,"subtotal_incl_tax":198,"base_subtotal_incl_tax":198,"applied_taxes":[],"converting_from_quote":true,"store_name":"Main Website\nMain Store\nEnglish","total_item_count":1,"protect_code":"0cd01b","created_at":"2013-07-24 02:51:45","updated_at":"2013-07-24 02:51:45","entity_id":"33","billing_address_id":65,"shipping_address_id":66}', true);
        $this->shipping_data = json_decode('{"store_id":null,"address_type":"shipping","customer_id":null,"customer_address_id":null,"vat_id":null,"vat_is_valid":null,"vat_request_id":null,"vat_request_date":null,"vat_request_success":null,"prefix":null,"firstname":"Frank","middlename":null,"lastname":"Guest","suffix":null,"company":null,"street":"10 Guest St","city":"Testville","region":"Vermont","region_id":"59","postcode":"123412","country_id":"US","telephone":"1234123","fax":null,"email":"guest@example.com","parent_id":"33","created_at":"2013-07-24 02:51:45","updated_at":"2013-07-24 02:51:45"}', true); //,"entity_id":"66"
        $this->billing_data = json_decode('{"store_id":null,"address_type":"billing","customer_id":null,"customer_address_id":null,"vat_id":null,"vat_is_valid":null,"vat_request_id":null,"vat_request_date":null,"vat_request_success":null,"prefix":null,"firstname":"Frank","middlename":null,"lastname":"Guest","suffix":null,"company":null,"street":"10 Guest St","city":"Testville","region":"Vermont","region_id":"59","postcode":"123412","country_id":"US","telephone":"1234123","fax":null,"email":"guest@example.com","parent_id":"33","created_at":"2013-07-24 02:51:45","updated_at":"2013-07-24 02:51:45"}', true); //,"entity_id":"65"
        $this->customer_data = json_decode('[]', true);
        $this->payment_data = json_decode(' {"store_id":null,"customer_payment_id":null,"method":"checkmo","additional_data":null,"additional_information":[],"po_number":null,"cc_type":null,"cc_number_enc":null,"cc_last4":null,"cc_owner":null,"cc_exp_month":"0","cc_exp_year":"0","cc_number":null,"cc_cid":null,"cc_ss_issue":null,"cc_ss_start_month":"0","cc_ss_start_year":"0","parent_id":"33","method_instance":{},"created_at":"2013-07-24 02:51:45","updated_at":"2013-07-24 02:51:45"}', true); //,"entity_id":"33"
        $this->quote_data = json_decode('{"entity_id":"40","store_id":"1","created_at":"2013-07-24 02:47:02","updated_at":"2013-07-24 02:51:45","converted_at":null,"is_active":"1","is_virtual":"0","is_multi_shipping":"0","items_count":1,"items_qty":1,"orig_order_id":"0","store_to_base_rate":1,"store_to_quote_rate":1,"base_to_global_rate":1,"base_to_quote_rate":1,"global_currency_code":"USD","base_currency_code":"USD","store_currency_code":"USD","quote_currency_code":"USD","grand_total":203,"base_grand_total":203,"checkout_method":"guest","customer_id":null,"customer_tax_class_id":"3","customer_group_id":0,"customer_email":"guest@example.com","customer_prefix":null,"customer_firstname":"Frank","customer_middlename":null,"customer_lastname":"Guest","customer_suffix":null,"customer_dob":null,"customer_note":null,"customer_note_notify":"1","customer_is_guest":true,"customer_taxvat":null,"remote_ip":"127.0.0.1","applied_rule_ids":"","reserved_order_id":"100000033","password_hash":null,"coupon_code":null,"subtotal":198,"base_subtotal":198,"subtotal_with_discount":198,"base_subtotal_with_discount":198,"gift_message_id":null,"is_changed":1,"trigger_recollect":0,"ext_shipping_info":null,"customer_gender":null,"is_persistent":"0","x_forwarded_for":null,"virtual_items_qty":0,"taxes_for_items":[],"can_apply_msrp":false,"totals_collected_flag":true,"inventory_processed":true}', true);
        $this->items_data = json_decode('{"63":{"item_id":"63","quote_id":"40","created_at":"2013-07-24 02:47:02","updated_at":"2013-07-24 02:47:02","product_id":"509","store_id":"1","parent_item_id":null,"is_virtual":"0","sku":"amuletum-ring-sterling-silver","name":"Amuletum Ring: Recycled Sterling Silver","description":null,"applied_rule_ids":"","additional_data":null,"free_shipping":false,"is_qty_decimal":"0","no_discount":"0","weight":"0.0000","qty":1,"price":198,"base_price":198,"custom_price":null,"discount_percent":0,"discount_amount":0,"base_discount_amount":0,"tax_percent":0,"tax_amount":0,"base_tax_amount":0,"row_total":198,"base_row_total":198,"row_total_with_discount":"0.0000","row_weight":0,"product_type":"simple","base_tax_before_discount":null,"tax_before_discount":null,"original_custom_price":null,"gift_message_id":null,"weee_tax_applied":"a:0:{}","weee_tax_applied_amount":0,"weee_tax_applied_row_amount":0,"base_weee_tax_applied_amount":0,"base_weee_tax_applied_row_amnt":null,"weee_tax_disposition":0,"weee_tax_row_disposition":0,"base_weee_tax_disposition":0,"base_weee_tax_row_disposition":0,"redirect_url":null,"base_cost":null,"price_incl_tax":198,"base_price_incl_tax":198,"row_total_incl_tax":198,"base_row_total_incl_tax":198,"hidden_tax_amount":null,"base_hidden_tax_amount":null,"qty_options":[],"product":{},"tax_class_id":"2","is_recurring":"0","has_error":false,"is_nominal":false,"base_calculation_price":198,"calculation_price":198,"converted_price":198,"base_original_price":198,"taxable_amount":198,"base_taxable_amount":198,"is_price_incl_tax":false,"base_weee_tax_applied_row_amount":0,"original_price":198}}', true);
        $this->products_data = json_decode('{"63":{"entity_id":"509","entity_type_id":"10","attribute_set_id":"66","type_id":"simple","sku":"amuletum-ring-sterling-silver","created_at":"2012-11-26 19:32:28","updated_at":"2012-11-26 19:32:28","has_options":"0","required_options":"0","name":"Amuletum Ring: Recycled Sterling Silver","small_image":"\/a\/m\/amuletum_ring_18.jpg","url_key":"amuletum-ring-sterling-silver","thumbnail":"\/a\/m\/amuletum_ring_18.jpg","gift_message_available":"0","url_path":"amuletum-ring-sterling-silver.html","msrp_enabled":"2","msrp_display_actual_price_type":"4","status":"1","tax_class_id":"2","visibility":"4","enable_googlecheckout":"1","is_recurring":"0","price":"198.0000","cost":null,"weight":"0.0000","special_price":null,"msrp":null,"special_from_date":null,"special_to_date":null,"is_salable":"1","stock_item":{},"request_path":"amuletum-ring-sterling-silver.html","tier_price":[],"is_in_stock":"1","store_id":"1","customer_group_id":"0","final_price":null,"group_price":[],"group_price_changed":0}}', true);
    }
    
    public function userData()
    {
        $this->defaultData();
        
        $this->customer_data = json_decode('{"website_id":"1","entity_id":"2","entity_type_id":"1","attribute_set_id":"0","email":"guest@example.com","group_id":"1","increment_id":null,"store_id":"1","created_at":"2013-01-09 23:51:59","updated_at":"2013-07-24 03:57:34","is_active":"1","disable_auto_group_change":"0","firstname":"Frank","lastname":"Guest","password_hash":"bd54ffd4de1e2d9152c9c7d88ebb11a5:PO","created_in":"English","default_billing":"1","default_shipping":"1","tax_class_id":"3","parent_id":0,"confirmation":null}', true);
    }
    
    public function ccData()
    {
        $this->defaultData();
        
        $this->payment_data = json_decode('{"store_id":null,"customer_payment_id":null,"method":"ccsave","additional_data":null,"additional_information":[],"po_number":null,"cc_type":"VI","cc_number_enc":"eu0qZQ+FtOrsjrSC18+hxw==","cc_last4":"1111","cc_owner":"A","cc_exp_month":"1","cc_exp_year":"2015","cc_number":"4444333322221111","cc_cid":"123","cc_ss_issue":null,"cc_ss_start_month":null,"cc_ss_start_year":null,"parent_id":"34","method_instance":{},"created_at":"2013-07-24 03:57:35","updated_at":"2013-07-24 03:57:35","entity_id":"34"}', true);
    }
    
    public function binData()
    {
        $this->defaultData();
        
        $this->payment_data = json_decode('{"store_id":null,"customer_payment_id":null,"method":"ccsave","additional_data":null,"additional_information":[],"po_number":null,"cc_type":"VI","cc_number_enc":"eu0qZQ+FtOrsjrSC18+hxw==","cc_last4":"1111","cc_owner":"A","cc_exp_month":"1","cc_exp_year":"2015","cc_number":"44$$safd44","cc_cid":"123","cc_ss_issue":null,"cc_ss_start_month":null,"cc_ss_start_year":null,"parent_id":"34","method_instance":{},"created_at":"2013-07-24 03:57:35","updated_at":"2013-07-24 03:57:35","entity_id":"34"}', true);
    }
    
    public function productsData()
    {
        $this->defaultData();
        
        $this->items_data = json_decode('{"59":{"item_id":"59","quote_id":"37","created_at":"2013-07-23 06:52:12","updated_at":"2013-07-23 06:52:12","product_id":"539","store_id":"1","parent_item_id":null,"is_virtual":"0","sku":"kbr-5","name":"Kissing Bird Ring: 14K Gold","description":null,"applied_rule_ids":"","additional_data":null,"free_shipping":false,"is_qty_decimal":"0","no_discount":"0","weight":"1.0000","qty":2,"price":385,"base_price":385,"custom_price":null,"discount_percent":0,"discount_amount":0,"base_discount_amount":0,"tax_percent":0,"tax_amount":0,"base_tax_amount":0,"row_total":770,"base_row_total":770,"row_total_with_discount":"0.0000","row_weight":2,"product_type":"simple","base_tax_before_discount":null,"tax_before_discount":null,"original_custom_price":null,"gift_message_id":null,"weee_tax_applied":"a:0:{}","weee_tax_applied_amount":0,"weee_tax_applied_row_amount":0,"base_weee_tax_applied_amount":0,"base_weee_tax_applied_row_amnt":null,"weee_tax_disposition":0,"weee_tax_row_disposition":0,"base_weee_tax_disposition":0,"base_weee_tax_row_disposition":0,"redirect_url":null,"base_cost":null,"price_incl_tax":385,"base_price_incl_tax":385,"row_total_incl_tax":770,"base_row_total_incl_tax":770,"hidden_tax_amount":null,"base_hidden_tax_amount":null,"qty_options":[],"product":{},"tax_class_id":"2","is_recurring":"0","has_error":false,"is_nominal":false,"base_calculation_price":385,"calculation_price":385,"converted_price":385,"base_original_price":385,"taxable_amount":770,"base_taxable_amount":770,"is_price_incl_tax":false,"base_weee_tax_applied_row_amount":0,"original_price":385},"65":{"item_id":"65","quote_id":"37","created_at":"2013-07-24 03:53:15","updated_at":"2013-07-24 03:53:39","product_id":"507","store_id":"1","parent_item_id":null,"is_virtual":"0","sku":"protector-serpent-ring-brass","name":"Protector Serpent Ring: Recycled Brass","description":null,"applied_rule_ids":"","additional_data":null,"free_shipping":false,"is_qty_decimal":"0","no_discount":"0","weight":"0.0000","qty":1,"price":120,"base_price":120,"custom_price":null,"discount_percent":0,"discount_amount":0,"base_discount_amount":0,"tax_percent":0,"tax_amount":0,"base_tax_amount":0,"row_total":120,"base_row_total":120,"row_total_with_discount":"0.0000","row_weight":0,"product_type":"simple","base_tax_before_discount":null,"tax_before_discount":null,"original_custom_price":null,"gift_message_id":null,"weee_tax_applied":"a:0:{}","weee_tax_applied_amount":0,"weee_tax_applied_row_amount":0,"base_weee_tax_applied_amount":0,"base_weee_tax_applied_row_amnt":null,"weee_tax_disposition":0,"weee_tax_row_disposition":0,"base_weee_tax_disposition":0,"base_weee_tax_row_disposition":0,"redirect_url":null,"base_cost":null,"price_incl_tax":120,"base_price_incl_tax":120,"row_total_incl_tax":120,"base_row_total_incl_tax":120,"hidden_tax_amount":null,"base_hidden_tax_amount":null,"qty_options":[],"product":{},"tax_class_id":"2","is_recurring":"0","has_error":false,"is_nominal":false,"base_calculation_price":120,"calculation_price":120,"converted_price":120,"base_original_price":120,"taxable_amount":120,"base_taxable_amount":120,"is_price_incl_tax":false,"base_weee_tax_applied_row_amount":0,"original_price":120}}', true);
        $this->products_data = json_decode('{"59":{"entity_id":"539","entity_type_id":"10","attribute_set_id":"65","type_id":"simple","sku":"kbr-5","created_at":"2012-11-26 19:33:02","updated_at":"2013-01-09 03:19:25","has_options":"0","required_options":"0","name":"Kissing Bird Ring: 14K Gold","small_image":"no_selection","url_key":"kissing-bird-ring-14k-gold","thumbnail":"no_selection","gift_message_available":"0","url_path":"kissing-bird-ring-14k-gold-549.html","msrp_enabled":"2","msrp_display_actual_price_type":"4","status":"1","tax_class_id":"2","visibility":"4","enable_googlecheckout":"1","is_recurring":"0","weight":"1.0000","price":"385.0000","cost":null,"special_price":null,"msrp":null,"special_from_date":null,"special_to_date":null,"is_salable":"1","stock_item":{},"request_path":"kissing-bird-ring-14k-gold-549.html","tier_price":[],"is_in_stock":"1","store_id":"1","customer_group_id":"1","final_price":null,"group_price":[],"group_price_changed":0},"65":{"entity_id":"507","entity_type_id":"10","attribute_set_id":"66","type_id":"simple","sku":"protector-serpent-ring-brass","created_at":"2012-11-26 19:32:25","updated_at":"2012-11-26 19:32:25","has_options":"0","required_options":"0","name":"Protector Serpent Ring: Recycled Brass","small_image":"\/p\/r\/protectorring1_18.jpg","url_key":"protector-serpent-ring-brass","thumbnail":"\/p\/r\/protectorring1_18.jpg","gift_message_available":"0","url_path":"protector-serpent-ring-brass.html","msrp_enabled":"2","msrp_display_actual_price_type":"4","status":"1","tax_class_id":"2","visibility":"4","enable_googlecheckout":"1","is_recurring":"0","price":"120.0000","cost":null,"weight":"0.0000","special_price":null,"msrp":null,"special_from_date":null,"special_to_date":null,"is_salable":"1","stock_item":{},"request_path":"protector-serpent-ring-brass.html","tier_price":[],"is_in_stock":"1","store_id":"1","customer_group_id":"1","final_price":null,"group_price":[],"group_price_changed":0}}', true);
    }
    
    public function weightData()
    {
        $this->defaultData();
        
        $this->items_data = json_decode('{"21":{"store_id":"1","quote_item_id":"3","quote_parent_item_id":null,"product_id":"2","product_type":"configurable","qty_backordered":null,"product_options":"a:6:{s:15:\"info_buyRequest\";a:5:{s:4:\"uenc\";s:92:\"aHR0cDovLzEyNy4wLjAuMS9tYWdlbnRvL2NhdC9jb25maWd1cmFibGUuaHRtbD9fX19TSUQ9VSZvcHRpb25zPWNhcnQ,\";s:7:\"product\";s:1:\"2\";s:15:\"related_product\";s:0:\"\";s:15:\"super_attribute\";a:1:{i:80;s:1:\"5\";}s:3:\"qty\";s:1:\"1\";}s:15:\"attributes_info\";a:1:{i:0;a:2:{s:5:\"label\";s:5:\"Color\";s:5:\"value\";s:3:\"Red\";}}s:11:\"simple_name\";s:3:\"Red\";s:10:\"simple_sku\";s:3:\"red\";s:20:\"product_calculations\";i:1;s:13:\"shipment_type\";i:0;}","sku":"test-configurable","name":"Configurable","description":null,"weight":"1.0000","is_qty_decimal":"0","qty_ordered":1,"is_virtual":"0","original_price":1,"applied_rule_ids":"","additional_data":null,"price":1,"base_price":"1.0000","tax_percent":0,"tax_amount":0,"tax_before_discount":null,"base_tax_before_discount":null,"tax_string":null,"row_weight":1,"row_total":1,"base_original_price":"1.0000","base_tax_amount":0,"base_row_total":1,"base_cost":null,"price_incl_tax":null,"base_price_incl_tax":null,"row_total_incl_tax":null,"base_row_total_incl_tax":null,"weee_tax_applied":"a:0:{}","weee_tax_applied_amount":0,"weee_tax_applied_row_amount":0,"base_weee_tax_applied_amount":0,"base_weee_tax_applied_row_amount":0,"weee_tax_disposition":0,"base_weee_tax_disposition":0,"weee_tax_row_disposition":0,"base_weee_tax_row_disposition":0,"discount_percent":0,"discount_amount":0,"base_discount_amount":0,"gift_message_id":null,"gift_message_available":"2","order_id":"11","has_children":true,"created_at":"2013-07-25 01:27:02","updated_at":"2013-07-25 01:27:02","item_id":"21"},"22":{"store_id":"1","quote_item_id":"4","quote_parent_item_id":"3","product_id":"1","product_type":"simple","qty_backordered":null,"product_options":"a:1:{s:15:\"info_buyRequest\";a:5:{s:4:\"uenc\";s:92:\"aHR0cDovLzEyNy4wLjAuMS9tYWdlbnRvL2NhdC9jb25maWd1cmFibGUuaHRtbD9fX19TSUQ9VSZvcHRpb25zPWNhcnQ,\";s:7:\"product\";s:1:\"2\";s:15:\"related_product\";s:0:\"\";s:15:\"super_attribute\";a:1:{i:80;s:1:\"5\";}s:3:\"qty\";s:1:\"1\";}}","sku":"red","name":"Red","description":null,"weight":"1.0000","is_qty_decimal":"0","qty_ordered":1,"is_virtual":"0","original_price":0,"applied_rule_ids":"","additional_data":null,"price":0,"base_price":"0.0000","tax_percent":"0.0000","tax_amount":"0.0000","tax_before_discount":null,"base_tax_before_discount":null,"tax_string":null,"row_weight":"0.0000","row_total":"0.0000","base_original_price":"0.0000","base_tax_amount":"0.0000","base_row_total":"0.0000","base_cost":null,"price_incl_tax":null,"base_price_incl_tax":null,"row_total_incl_tax":null,"base_row_total_incl_tax":null,"weee_tax_applied":"a:0:{}","weee_tax_applied_amount":"0.0000","weee_tax_applied_row_amount":"0.0000","base_weee_tax_applied_amount":"0.0000","base_weee_tax_applied_row_amount":"0.0000","weee_tax_disposition":"0.0000","base_weee_tax_disposition":"0.0000","weee_tax_row_disposition":"0.0000","base_weee_tax_row_disposition":"0.0000","discount_percent":"0.0000","discount_amount":"0.0000","base_discount_amount":"0.0000","gift_message_id":null,"gift_message_available":"2","order_id":"11","parent_item_id":"21","created_at":"2013-07-25 01:27:03","updated_at":"2013-07-25 01:27:03","item_id":"22"}}', true);
        $this->products_data = json_decode('{"21":{"entity_id":"2","entity_type_id":"4","attribute_set_id":"4","type_id":"configurable","sku":"test-configurable","has_options":"1","required_options":"1","created_at":"2013-07-25 01:12:29","updated_at":"2013-07-25 01:12:29","price":"1.0000","special_price":null,"name":"Configurable","meta_title":"","meta_description":"","url_key":"configurable","url_path":"configurable.html","custom_design":"","page_layout":"","options_container":"container2","gift_message_available":"2","description":"Test","short_description":"Test","meta_keyword":"","custom_layout_update":"","special_from_date":null,"special_to_date":null,"news_from_date":null,"news_to_date":null,"custom_design_from":null,"custom_design_to":null,"status":"1","tax_class_id":"0","visibility":"4","enable_googlecheckout":"1","tier_price":[],"tier_price_changed":0,"media_gallery":{"images":[],"values":[]},"stock_item":{},"is_in_stock":"1","is_salable":"1"},"22":{"entity_id":"1","entity_type_id":"4","attribute_set_id":"4","type_id":"simple","sku":"red","has_options":"0","required_options":"0","created_at":"2013-07-25 01:12:14","updated_at":"2013-07-25 01:12:14","weight":"1.0000","price":"1.0000","special_price":null,"cost":null,"name":"Red","meta_title":"","meta_description":"","url_key":"red","url_path":"red.html","custom_design":"","page_layout":"","options_container":"container2","gift_message_available":"2","description":"red","short_description":"red","meta_keyword":"","custom_layout_update":"","special_from_date":null,"special_to_date":null,"news_from_date":null,"news_to_date":null,"custom_design_from":null,"custom_design_to":null,"status":"1","color":"5","visibility":"1","tax_class_id":"0","enable_googlecheckout":"1","tier_price":[],"tier_price_changed":0,"media_gallery":{"images":[],"values":[]},"stock_item":{},"is_in_stock":"1","is_salable":"1"}}', true);
    }
    
    public function noData()
    {
        $this->resetData();
        
        $this->order_data = json_decode('', true);
        $this->shipping_data = json_decode('', true);
        $this->billing_data = json_decode('', true);
        $this->customer_data = json_decode('', true);
        $this->payment_data = json_decode('', true);
        $this->quote_data = json_decode('', true);
        $this->items_data = json_decode('', true);
        $this->products_data = json_decode('', true);
    }
}
