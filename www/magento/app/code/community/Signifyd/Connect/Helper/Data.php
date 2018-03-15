<?php
/**
 * Data Helper
 *
 * @category    Signifyd Connect
 * @package     Signifyd_Connect
 * @author      Signifyd
 */
class Signifyd_Connect_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Restricted status on specific states only
     * @var array
     */
    protected $restrictedStatesMethods = array(
        'all' => array(
            'checkmo', 'cashondelivery', 'banktransfer','purchaseorder'
        ),
        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT => array(
            'payflow_link', 'payflow_advanced'
        ),
        Mage_Sales_Model_Order::STATE_CANCELED => array(
            'all'
        )
    );

    /** @var  Signifyd_Connect_Helper_Payment_Interface */
    protected $paymentHelper;

    /**
     * @param $method
     * @param null $state
     * @return bool
     */
    public function isRestricted($method, $state = null)
    {
        if (in_array($method, $this->restrictedStatesMethods['all'])) {
            return true;
        }

        if (isset($this->restrictedStatesMethods[$state]) &&
            is_array($this->restrictedStatesMethods[$state]) &&
            in_array('all', $this->restrictedStatesMethods[$state])) {
            return true;
        }

        if (!empty($state) && isset($this->restrictedStatesMethods[$state]) &&
            is_array($this->restrictedStatesMethods[$state]) &&
            in_array($method, $this->restrictedStatesMethods[$state])) {
            return true;
        }

        return false;
    }

    public function log($message)
    {
        Mage::helper('signifyd_connect/log')->addLog($message);
    }

    public function getProducts($quote)
    {
        $products = array();

        foreach ($quote->getAllItems() as $item) {
            $product_type = $item->getProductType();

            if (!$product_type || $product_type == 'simple' || $product_type == 'downloadable'
                || $product_type == 'grouped' || $product_type == 'virtual' ) {
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

    protected function getVersions()
    {
        $version = array();
        $version['storePlatformVersion'] = Mage::getVersion();
        $version['signifydClientApp'] = 'Magento';
        $version['storePlatform'] = 'Magento';
        $version['signifydClientAppVersion'] = (string)(Mage::getConfig()->getNode()->modules->Signifyd_Connect->version);
        return $version;
    }

    protected function getTransactionId($payment)
    {
        $transId = $payment->getCcTransId();
        if(is_array($transId) && is_string($transId[0])) {
            $transId = $transId[0];
        } else if(!is_string($transId)) {
            $transId = null;
        }
        return $transId;
    }

    public function getPurchase($order, $payment)
    {
        $purchase = array();

        if (!$this->isAdmin() && $this->isDeviceFingerprintEnabled()) {
            $purchase['orderSessionId'] = 'M1' . base64_encode(Mage::getBaseUrl()) . $order->getQuoteId();
        }
        // T715: Send null rather than false when we can't get the IP Address
        $purchase['browserIpAddress'] = ($this->getIpAddress($order) ? $this->getIpAddress($order) : null);
        $purchase['orderId'] = $order->getIncrementId();
        $purchase['createdAt'] = date('c', strtotime($order->getCreatedAt())); // e.g: 2004-02-12T15:19:21+00:00
        $purchase['currency'] = $order->getOrderCurrencyCode();
        $purchase['totalPrice'] = floatval($order->getGrandTotal());
        $purchase['shippingPrice'] = floatval($order->getShippingAmount());
        $purchase['products'] = $this->getProducts($order);
        $purchase['paymentGateway'] = $payment->getMethod();
        $purchase['transactionId'] = $this->getTransactionId($payment);

        $paymentHelper = $this->getPaymentHelper($order, $payment);
        $purchase['avsResponseCode'] = $paymentHelper->getAvsResponseCode();
        $purchase['cvvResponseCode'] = $paymentHelper->getCvvResponseCode();
        $purchase['avsResponseCode'] = $paymentHelper->filterAvsResponseCode($purchase['avsResponseCode']);
        $purchase['cvvResponseCode'] = $paymentHelper->filterCvvResponseCode($purchase['cvvResponseCode']);

        return $purchase;
    }

    /**
     * @param $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return array
     */
    public function getCard($order, $payment)
    {
        $card = $this->getPaymentHelper($order, $payment)->getCardData();
        $card['billingAddress'] = $this->getSignifydAddress($order->getBillingAddress());

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

        $case['purchase'] = $this->getPurchase($order, $payment);
        $case['recipient'] = $this->getRecipient($order);
        $case['card'] = $this->getCard($order, $payment);
        $case['userAccount'] = $this->getUserAccount($customer, $order);
        $case['clientVersion'] = $this->getVersions();

        return $case;
    }

    /**
     * Getting the cases url
     * @return string
     */
    public function getUrl()
    {
        return 'https://api.signifyd.com/v2/cases';
    }

    /**
     * Getting the case url based on the case code
     * @param $caseCode
     * @return string
     */
    public function getCaseUrl($caseCode)
    {
        return 'https://api.signifyd.com/v2/cases/' . $caseCode;
    }

    /**
     * Get storeId given $case object
     *
     * @param Signifyd_Connect_Model_Case $case
     * @return bool|int|mixed
     */
    public function getCaseStoreId(Signifyd_Connect_Model_Case $case)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $case->getOrder();

        if (is_object($order) && !$order->isEmpty()) {
            return $order->getStoreId();
        }

        $incrementId = $case->getId();

        if (!empty($incrementId)) {
            /** @var Mage_Sales_Model_Mysql4_Order_Grid_Collection $collection */
            $collection = Mage::getResourceModel('sales/order_grid_collection');
            $collection->addFieldToFilter('increment_id', $incrementId);
            /** @var Mage_Sales_Model_Order $orderGrid */
            $orderGrid = $collection->getFirstItem();

            return $orderGrid->getData('store_id');
        }

        return null;
    }

    /**
     * @param $path
     * @param Mage_Sales_Model_Order $order
     * @return mixed
     */
    public function getConfigData($path, Mage_Core_Model_Abstract $entity = null)
    {
        $this->log("Get config {$path} for entity " . get_class($entity));

        if ($entity instanceof Mage_Sales_Model_Order && !$entity->isEmpty()) {
            $storeId = $entity->getStoreId();
        } elseif ($entity instanceof Signifyd_Connect_Model_Case && !$entity->isEmpty()) {
            $storeId = $this->getCaseStoreId($entity);
        } else {
            $storeId = null;
        }

        $this->log("Getting config {$path} for store {$storeId}");

        $return = Mage::getStoreConfig("signifyd_connect/{$path}", $storeId);

        $this->log("Config value {$return}");

        return $return;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param bool $forceSend
     * @return string
     */
    public function buildAndSendOrderToSignifyd($order, $forceSend = false)
    {
        if ($order instanceof Mage_Sales_Model_Order && !$order->isEmpty()) {
            if (!$this->isEnabled($order)) {
                return 'disabled';
            }

            if ($this->isRestricted($order->getPayment()->getMethod(), $order->getState())) {
                return 'restricted';
            }

            $case = Mage::getModel('signifyd_connect/case')->load($order->getIncrementId());
            if ($case->getId() && !$forceSend) {
                return "exists";
            }

            /** @var Mage_Sales_Model_Resource_Order_Payment_Collection $payments */
            $payments = $order->getPaymentsCollection();
            /** @var Mage_Sales_Model_Order_Payment $lastPayment */
            $lastPayment = $payments->getLastItem()->isEmpty() ? null : $payments->getLastItem();

            $state = $order->getState();
            if (!$state || $state == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                return "notready"; // Note: would not be in the order grid if this were the case
            }

            if (is_null(Mage::registry('signifyd_action_' . $order->getIncrementId()))) {
                Mage::register('signifyd_action_' . $order->getIncrementId(), 1); // Work will now take place
            }

            $customer = null;
            if ($order->getCustomer()) {
                $customer = $order->getCustomer();
            }

            $case = $this->generateCase($order, $lastPayment, $customer);
            $caseObject = $this->markProcessed($order);
            $apiKey = Mage::helper('signifyd_connect')->getConfigData('settings/key', $order);
            $response = $this->request($this->getUrl(), json_encode($case), $apiKey, 'application/json');

            try {
                $responseCode = $response->getHttpCode();

                if (substr($responseCode, 0, 1) == '2') {
                    $responseData = json_decode($response->getRawResponse(), true);

                    $caseId = $responseData['investigationId'];
                    $caseObject = Mage::getModel('signifyd_connect/case')->load($caseObject->getOrderIncrement());
                    $caseObject->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $caseObject->setCode($caseId);
                    $caseObject->setTransactionId($case['purchase']['transactionId']);
                    $caseObject->setMagentoStatus(Signifyd_Connect_Model_Case::IN_REVIEW_STATUS);
                    $caseObject->save();

                    $order->addStatusHistoryComment("Signifyd: case $caseId created for order");
                    $order->save(); // Note: this will trigger recursion

                    return "sent";
                }
            } catch (Exception $e) {
                $this->log($e->__toString());
            }

            return "error";
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

    public function getCaseUrlByOrderId($orderId)
    {
        $case = Mage::getModel('signifyd_connect/case')->load($orderId);

        if ($case->getCode()) {
            return "https://www.signifyd.com/cases/" . $case->getCode();
        }

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
        if($case && !$case->isObjectNew())
        {
            Mage::register('isSecureArea', true);
            $case->delete();
            Mage::unregister('isSecureArea');
        }
    }

    public function cancelGuarantee($case)
    {
        $caseId = $case->getCode();
        $url = $this->getUrl() . "/$caseId/guarantee";
        $body = json_encode(array("guaranteeDisposition" => "CANCELED"));
        $auth = Mage::helper('signifyd_connect')->getConfigData('settings/key', $case);
        $response = $this->request($url, $body, $auth, 'application/json', null, true);
        $code = $response->getHttpCode();
        if(substr($code, 0, 1) == '2') {
            $case->setGuarantee('CANCELED');
            $case->save();
        } else {
            $this->log("Guarantee cancel failed");
        }
        $this->log("Received $code from guarantee cancel");
    }

    public function request($url, $data = null, $auth = null, $contenttype = "application/x-www-form-urlencoded",
                            $accept = null, $is_update = false)
    {
        if (Mage::getStoreConfig('signifyd_connect/log/all')) {
            $authMask = preg_replace ( "/\S/", "*", $auth, strlen($auth) - 4 );
            $this->log("Request:\nURL: $url \nAuth: $authMask\nData: $data");
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
            if($is_update) curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            else curl_setopt($curl, CURLOPT_POST, 1);

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

        if (Mage::getStoreConfig('signifyd_connect/log/all')) {
            $this->log("Response ($url):\n " . print_r($response, true));
        }

        if ($raw_response === false || curl_errno($curl)) {
            $error = curl_error($curl);

            if (Mage::getStoreConfig('signifyd_connect/log/all')) {
                $this->log("ERROR ($url):\n$error");
            }

            $response->setData('error', $error);
        }

        curl_close($curl);

        return $response;
    }

    /**
     * Get the order payment status
     * @param $order
     * @return array
     */
    public function getOrderPaymentStatus($order)
    {
        $status = array('authorize' => false, 'capture' => false, 'credit_memo' => false);
        $logger = Mage::helper('signifyd_connect/log');
        $paymentMethod = $order->getPayment();
        $paymentAuthorized = $paymentMethod->getBaseAmountAuthorized();
        $baseTotalPaid = $order->getBaseTotalPaid();
        $baseTotalRefunded = $order->getBaseTotalRefunded();

        // Check authorization
        if(!empty($paymentAuthorized)){
            $status['authorize'] = true;
        }

        // Special case for Paypal payment type "order"
        if($this->isPaypalOrder($paymentMethod)){
            $paymentAdditional = $paymentMethod->getData('additional_information');
            if(isset($paymentAdditional['is_order_action']) && $paymentAdditional['is_order_action']){
                $status['authorize'] = true;
            }
        }

        // Check capture
        if(!empty($baseTotalPaid)){
            $status['capture'] = true;
        }

        // Check credit memo
        if(!empty($baseTotalRefunded)){
            $status['credit_memo'] = true;
        }

        // Log status
        $logger->addLog("Order: {$order->getIncrementId()} has a status of " . json_encode($status));

        return $status;
    }

    /**
     * Checking if the payment method si paypal_express
     * @param $paymentMethod
     * @return bool
     */
    public function isPaypalOrder($paymentMethod)
    {
        $code = $paymentMethod->getMethodInstance()->getCode();
        return (stripos($code, 'paypal_express') !== false)? true : false;
    }

    public function isGuarantyDeclined($order)
    {
        $case = Mage::getModel('signifyd_connect/case')->load($order->getIncrementId());
        return ($case->getGuarantee() == 'DECLINED')? true : false;
    }

    /**
     * Is the extension enabled in the admin
     * @return mixed
     */
    public function isEnabled(Mage_Core_Model_Abstract $entity = null)
    {
        $enabled = $this->getConfigData('settings/enabled', $entity);
        $apiKey = $this->getConfigData('settings/key', $entity);

        return $enabled && !empty($apiKey);
    }

    public function isDeviceFingerprintEnabled()
    {
        return (bool) Mage::getStoreConfig('signifyd_connect/settings/enable_device_fingerprint');
    }

    /**
     * Getting the action for accepted from guaranty
     * @param $storeId
     * @return mixed
     */
    public function getAcceptedFromGuaranty(Mage_Core_Model_Abstract $entity)
    {
        return $this->getConfigData('advanced/accepted_from_guaranty', $entity);
    }

    /**
     * Getting the action for declined from guaranty
     * @param $storeId
     * @return mixed
     */
    public function getDeclinedFromGuaranty(Mage_Core_Model_Abstract $entity)
    {
        return $this->getConfigData('advanced/declined_from_guaranty', $entity);
    }

    public function isAdmin()
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return true;
        }

        if (Mage::getDesign()->getArea() == 'adminhtml') {
            return true;
        }

        return false;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Signifyd_Connect_Helper_Payment_Interface
     */
    public function getPaymentHelper(Mage_Sales_Model_Order $order = null, Mage_Sales_Model_Order_Payment $payment = null)
    {
        if (!is_object($this->paymentHelper) ||
            !in_array('Signifyd_Connect_Helper_Payment_Interface', class_implements($this->paymentHelper))) {
            $paymentMethodCode = $payment->getMethod();
            $helperName = "signifyd_connect/payment_{$paymentMethodCode}";
            $helperClass = Mage::getConfig()->getHelperClassName($helperName);
            $paymentHelper = null;

            // Using @ avoid class_exists exceptions
            if (@class_exists($helperClass)) {
                $paymentHelper = Mage::helper("signifyd_connect/payment_{$paymentMethodCode}");
            }

            if (!is_object($paymentHelper) ||
                !in_array('Signifyd_Connect_Helper_Payment_Interface', class_implements($paymentHelper))) {
                if (substr($paymentMethodCode, 0, 8) == 'pbridge_') {
                    $paymentHelper = Mage::helper('signifyd_connect/payment_pbridge');
                } else {
                    $paymentHelper = Mage::helper('signifyd_connect/payment_other');
                }
            }

            $paymentHelper->init($order, $payment);
            $this->paymentHelper = $paymentHelper;
        }

        return $this->paymentHelper;
    }
}