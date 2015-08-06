<?php

class Signifyd_Connect_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getProducts($quote)
    {
        $products = array();

        foreach ($quote->getAllItems() as $item) {
            $product_type = $item->getProductType();

            if (!$product_type || $product_type == 'simple' || $product_type == 'downloadable' || $product_type == 'grouped') {
                $product_object = $item->getData('product');

                if (!$product_object || !$product_object->getId()) {
                    $product_object = Mage::getModel('catalog/product')->load($product_type);
                }

                if ($product_object) {
                    $product = array();

                    $product['itemId'] = $item->getSku();
                    $product['itemName'] = $item->getName();
                    $product['itemUrl'] = $this->getProductUrl($product_object);
                    $product['itemImage'] = $this->getProductImage($product_object);

                    $qty = 1;
                    if ($item->getQty()) {
                        $qty = $item->getQty();
                    } else if ($item->getQtyOrdered()) {
                        $qty = $item->getQtyOrdered();
                    }

                    $price = 0;
                    if ($item->getPrice() > 0) {
                        $price = $item->getPrice();
                    } else if ($item->getBasePrice() > 0) {
                        $price = $item->getBasePrice();
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

    public function getIPAddress($order)
    {
        if ($order->getRemoteIp()) {
            if ($order->getXForwardedFor()) {
                return $this->filterIp($order->getXForwardedFor());
            }

            return $this->filterIp($order->getRemoteIp());
        }

        // Checks each configured value in app/etc/local.xml & falls back to REMOTE_ADDR. See app/etc/local.xml.additional for examples.
        return $this->filterIp(Mage::helper('core/http')->getRemoteAddr(false));
    }

    public function filterIp($ip)
    {
        $matches = array();

        if (preg_match('/[0-9]{1,3}(?:\.[0-9]{1,3}){3}/', $ip, $matches)) { //ipv4
            return current($matches);
        }

        if (preg_match('/[a-f0-9]{0,4}(?:\:[a-f0-9]{0,4}){2,7}/', strtolower($ip), $matches)) { //ipv6
            return current($matches);
        }

        return preg_replace('/[^0-9a-zA-Z:\.]/', '', strtok(str_replace($ip, ',', "\n"), "\n"));
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

    public function getAvsResponse($payment)
    {
        $value = null;

        if ($payment->getAdditionalInformation('paypal_avs_code')) {
            $value = $payment->getAdditionalInformation('paypal_avs_code');
        } else if ($payment->getAdditionalInformation('cc_avs_status')) {
            $value = $payment->getAdditionalInformation('cc_avs_status');
        }

        return $this->formatAvs($value);
    }

    public function getCvvResponse($payment)
    {
        if ($payment->getAdditionalInformation('paypal_cvv2_match')) {
            return $payment->getAdditionalInformation('paypal_cvv2_match');
        }

        return null;
    }

    public function getPurchase($order)
    {
        $purchase = array();
        $payment = $order->getPayment();

        // T715: Send null rather than false when we can't get the IP Address
        $purchase['browserIpAddress'] = ($this->getIpAddress($order) ? $this->getIpAddress($order) : null);
        $purchase['orderId'] = $order->getIncrementId();
        $purchase['createdAt'] = date('c', strtotime($order->getCreatedAt())); // e.g: 2004-02-12T15:19:21+00:00
        $purchase['currency'] = $order->getOrderCurrencyCode();
        $purchase['totalPrice'] = floatval($order->getGrandTotal());
        $purchase['shippingPrice'] = floatval($order->getShippingAmount());
        $purchase['products'] = $this->getProducts($order);
        $purchase['paymentGateway'] = $payment->getMethod();
        $purchase['transactionId'] = $payment->getTransactionId();

        $purchase['avsResponseCode'] = $this->getAvsResponse($payment);
        $purchase['cvvResponseCode'] = $this->getCvvResponse($payment);

        return $purchase;
    }

    public function isPaymentCC($payment)
    {
        // Although the payment structure only has the entity data for the payment
        // the original payment method object is stored within the entity data.
        // It's not a requirement, but every CC handler I've found subclasses
        // from Mage_Payment_Model_Method_Cc, so we are using that as an
        // assumption for whether a method is based on CC data
        $method = $payment->getData('method_instance');
        if($method)
        {
            return is_subclass_of($method, 'Mage_Payment_Model_Method_Cc');
        }
        return false;
    }

    public function getCard($order, $payment)
    {
        $billing = $order->getBillingAddress();

        $card = array();

        $card['cardHolderName'] = null;
        $card['bin'] = null;
        $card['last4'] = null;
        $card['expiryMonth'] = null;
        $card['expiryYear'] = null;
        $card['hash'] = null;

        $card['billingAddress'] = $this->getSignifydAddress($billing);

        if ($payment->getCcOwner()) {
            $card['cardHolderName'] = $payment->getCcOwner();
        } else {
            $card['cardHolderName'] = $billing->getFirstname() . ' ' . $billing->getLastname();
        }

        // Card data may be set on payment even if payment was not with card.
        // If it is, we want to ignore the data
        if(!$this->isPaymentCC($payment)) return $card;

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

        return $card;
    }

    public function getSignifydAddress($address_object)
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

    public function getRecipient($order)
    {
        $recipient = array();

        // In the case of non-shipped (ex: downloadable) orders, shipping address will be null so
        // in that case, we need to avoid the exception.
        $shipping_address = $order->getShippingAddress();
        if($shipping_address) {
            $recipient['deliveryAddress'] = $this->getSignifydAddress($shipping_address);
            $recipient['fullName'] = $shipping_address->getFirstname() . ' ' . $shipping_address->getLastname();
            $recipient['confirmationPhone'] = $shipping_address->getTelephone();
            // Email: Note that this field is always the same for both addresses
            $recipient['confirmationEmail'] = $shipping_address->getEmail();
        }
        // Some customers have reported seeing "n/a@na.na" come through instead of a valid or null address
        //  We suspect that it is due to an older version of Magento. If it becomes unnecessary, do remove the extra check.
        if (!$recipient['confirmationEmail'] || $recipient['confirmationEmail'] == 'n/a@na.na') {
            $recipient['confirmationEmail'] = $order->getCustomerEmail();
        }

        return $recipient;
    }

    public function getUserAccount($customer, $order)
    {
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

            $user['phone'] = $order->getBillingAddress()->getTelephone();

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

    public function generateCase($order, $payment, $customer)
    {
        $case = array();

        $case['purchase'] = $this->getPurchase($order);
        $case['recipient'] = $this->getRecipient($order);
        $case['card'] = $this->getCard($order, $payment);
        $case['userAccount'] = $this->getUserAccount($customer, $order);

        return $case;
    }

    public function submitCase($case, $url, $auth)
    {
        $case = json_encode($case);

        return $this->request($url, $case, $auth, 'application/json');
    }

    public function getUrl()
    {
        return Mage::getStoreConfig('signifyd_connect/settings/url') . '/cases';
    }

    public function getAuth()
    {
        return Mage::getStoreConfig('signifyd_connect/settings/key');
    }

    public function buildAndSendOrderToSignifyd($order, $forceSend = false)
    {
        if ($order && $order->getId()) {
            if ($this->isProcessed($order) && !$forceSend) {
                return "exists";
            }

            $payments = $order->getPaymentsCollection();
            $last_payment = null;
            foreach ($payments as $payment) {
                $last_payment = $payment;
            }

            $state = $order->getState();

            if (!$state || $state == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                return "notready"; // Note: would not be in the order grid if this were the case
            }

            $customer = null;
            if ($order->getCustomer()) {
                $customer = $order->getCustomer();
            }

            $case = $this->generateCase($order, $last_payment, $customer);

            $case_object = $this->markProcessed($order);

            $response = $this->submitCase($case, $this->getUrl(), $this->getAuth());

            try {
                $response_code = $response->getHttpCode();

                if (substr($response_code, 0, 1) == '2') {
                    $response_data = json_decode($response->getRawResponse(), true);

                    $case_object = Mage::getModel('signifyd_connect/case')->load($case_object->getOrderIncrement());
                    $case_object->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case_object->setCode($response_data['investigationId']);
                    $case_object->save();
                    return "sent";
                }
            } catch (Exception $e) {
                Mage::log($e->__toString(), null, 'signifyd_connect.log');
                return "error";
            }
        }
    }

    public function getProductUrl($product)
    {
        $url = null;
        
        try {
            $url = $product->getUrlModel()->getProductUrl($product);
        } catch (Exception $e) {
            $url = null;
        }
        
        return $url;
    }
    
    public function getCaseUrl($order_id)
    {
        $case = Mage::getModel('signifyd_connect/case')->load($order_id);

        if ($case->getCode()) {
            return "https://www.signifyd.com/cases/" . $case->getCode();
        }
        Mage::log('Case URL not found: '.$order_id, null, 'signifyd_connect.log');
        return '';
    }
    
    public function getProductImage($product, $size="150")
    {
        $image = null;
        
        try {
            $image = (string)Mage::helper('catalog/image')->init($product, 'image')->resize($size, $size)->keepFrame(true)->keepAspectRatio(true);
        } catch (Exception $e) {
            $image = null;
        }
        
        return $image;
    }
    
    public function getStoreName()
    {
        $store = Mage::app()->getStore();
        
        return Mage::getStoreConfig('trans_email/ident_general/name', 0);
    }
    
    public function getStoreEmail()
    {
        return Mage::getStoreConfig('trans_email/ident_general/email', 0);
    }
    
    public function getStoreUrl()
    {
        return Mage::getBaseUrl();
    }
    
    public function isProcessed($order)
    {
        $case = Mage::getModel('signifyd_connect/case')->load($order->getIncrementId());
        
        if ($case->getId())
        {
            return true;
        }
        
        return false;
    }
    
    public function markProcessed($order)
    {
        $case = Mage::getModel('signifyd_connect/case');
        $case->setOrderIncrement($order->getIncrementId());
        $case->setCreated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
        
        return $case;
    }
    
    public function unmarkProcessed($order)
    {
        $case = Mage::getModel('signifyd_connect/case')->load($order->getIncrementId());
        if($case)
        {
            $case->delete();
        }
    }
    
    public function request($url, $data=null, $auth=null, $contenttype="application/x-www-form-urlencoded", $accept=null)
    {
        if (Mage::getStoreConfig('signifyd_connect/log/request')) {
            Mage::log("Request:\nURL: $url \nAuth: $auth\nData: $data", null, 'signifyd_connect.log');
        }
        
        $curl = curl_init();
        $response = new Varien_Object;
        $headers = array();

        curl_setopt($curl, CURLOPT_URL, $url);
        
        if (stripos($url, 'https://') === 0) {
            curl_setopt($curl, CURLOPT_PORT, 443);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        
        if ($auth) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $auth);
        }
        
        if ($accept) {
            $headers[] = 'Accept: ' . $accept;
        }

        if ($data) {
            curl_setopt($curl, CURLOPT_POST, 1);
            $headers[] = "Content-Type: $contenttype";
            $headers[] = "Content-length: " . strlen($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        
        if (count($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($curl, CURLOPT_TIMEOUT, 4);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 4);

        $raw_response = curl_exec($curl);
        $response->setRawResponse($raw_response);
        
        $response_data = curl_getinfo($curl);
        $response->addData($response_data);
        
        if (Mage::getStoreConfig('signifyd_connect/log/response')) {
            Mage::log("Response ($url):\n " . print_r($response, true), null, 'signifyd_connect.log');
        }
        
        if ($raw_response === false || curl_errno($curl)) {
            $error = curl_error($curl);
            
            if (Mage::getStoreConfig('signifyd_connect/log/error')) {
                Mage::log("ERROR ($url):\n$error", null, 'signifyd_connect.log');
            }
            
            $response->setData('error', $error);
        }
        
        curl_close($curl);
        
        return $response;
    }
}
