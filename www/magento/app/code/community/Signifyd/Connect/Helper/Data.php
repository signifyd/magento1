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
    protected $apiUrl = 'https://api.signifyd.com/v2/cases';

    /**
     * Restricted states and payment methods
     * @var array
     */
    protected $restrictedStatesMethods = array(
        'all' => array(
            'checkmo', 'cashondelivery', 'banktransfer', 'purchaseorder'
        ),
        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT => array(
            'all'
        ),
        Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW => array(
            'all'
        ),
        Mage_Sales_Model_Order::STATE_CANCELED => array(
            'all'
        ),
        Mage_Sales_Model_Order::STATE_CLOSED => array(
            'all'
        ),
        Mage_Sales_Model_Order::STATE_COMPLETE => array(
            'all'
        )
    );

    /** @var  Signifyd_Connect_Helper_Payment_Interface */
    protected $paymentHelper;

    /**
     * Check if order is restricted by payment method and state
     *
     * @param $method
     * @param null $state
     * @return bool
     */
    public function isRestricted($method, $state)
    {
        if (empty($state)) {
            return true;
        }

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

    /**
     * Check if order is ignored based on installation date
     *
     * If there is no record of the installation date on database order will not be ignored
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isIgnored(Mage_Sales_Model_Order $order)
    {
        $installationDateConfig = Mage::getStoreConfig('signifyd_connect/log/installation_date');

        if (empty($installationDateConfig)) {
            return false;
        }

        $installationDate = Varien_Date::toTimestamp($installationDateConfig);
        $createdAtDate = Varien_Date::toTimestamp($order->getCreatedAt());

        if ($createdAtDate < $installationDate) {
            $this->log('Installation date: ' . $installationDate);
            $this->log('Created at date: ' . $createdAtDate);

            return true;
        } else {
            return false;
        }
    }

    public function log($message)
    {
        Mage::helper('signifyd_connect/log')->addLog($message);
    }

    public function getDiscountCodes(Mage_Sales_Model_Order $order)
    {
        $discountCodes = array();
        $couponCode = $order->getCouponCode();

        if (!empty($couponCode)) {
            $discountCodes[] = array(
                'amount' => abs($order->getDiscountAmount()),
                'code' => $couponCode
            );
        }

        return $discountCodes;
    }

    public function getShipments(Mage_Sales_Model_Order $order)
    {
        $shipments = array();
        $shippingMethod = $order->getShippingMethod();

        if (!empty($shippingMethod)) {
            $shippingMethod = $order->getShippingMethod(true);

            $shipments[] = array(
                'shippingPrice' => floatval($order->getShippingAmount()),
                'shipper' => $shippingMethod->getCarrierCode(),
                'shippingMethod' => $shippingMethod->getMethod()
            );
        }

        return $shipments;
    }

    public function getProducts(Mage_Sales_Model_Order $order)
    {
        $products = array();

        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach ($order->getAllItems() as $item) {
            $productType = $item->getProductType();

            if (!$productType || $productType == 'simple' || $productType == 'downloadable'
                || $productType == 'grouped' || $productType == 'virtual') {
                $productObject = $item->getData('product');

                if (!$productObject || !$productObject->getId()) {
                    $productObject = Mage::getModel('catalog/product')->load($productType);
                }

                if ($productObject) {
                    $product = array();

                    $product['itemId'] = $item->getSku();
                    $product['itemName'] = $item->getName();
                    $product['itemIsDigital'] = (bool) $item->getIsVirtual();
                    $product['itemUrl'] = $this->getProductUrl($productObject);
                    $product['itemImage'] = $this->getProductImage($productObject);

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
                    } else if ($productObject->getData('price') > 0) {
                        $price = $productObject->getData('price');
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
                    } else if ($productObject->hasWeight()) {
                        $weight = $productObject->getWeight();
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
        $ipAddress = $this->filterIp($order->getXForwardedFor());

        if (empty($ipAddress) == false) {
            return $ipAddress;
        }

        $ipAddress = $this->filterIp($order->getRemoteIp());

        if (empty($ipAddress) == false) {
            return $ipAddress;
        }

        // Checks each configured value in app/etc/local.xml & falls back to REMOTE_ADDR.
        // See app/etc/local.xml.additional for examples.
        return $this->filterIp(Mage::helper('core/http')->getRemoteAddr(false));
    }

    /**
     * Gets XForwardedFor as a list
     *
     * @param $order
     * @return array|mixed
     */
    public function getXForwardedFor($order)
    {
        $matches = array();

        $count = $this->pregMatchAllIps($order->getXForwardedFor(), $matches);

        $this->log($order->getXForwardedFor());
        $this->log("Count: {$count}");

        if ($count > 0) {
            $this->log(print_r($matches, true));
            return $matches[0];
        }

        return array();
    }

    public function filterIp($ip)
    {
        $matches = array();
        $validIps = array();
        $validPublicIps = array();

        $count = $this->pregMatchAllIps($ip, $matches);

        if ($count > 0) {
            foreach ($matches[0] as $match) {
                if (filter_var($match,FILTER_VALIDATE_IP)) {
                    $validIps[] = $match;

                    if (filter_var($match,FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
                        $validPublicIps[] = $match;
                    }
                }
            }

            if (count($validPublicIps) > 0) {
                return array_shift($validPublicIps);
            } elseif (count($validIps)) {
                return array_shift($validIps);
            }
        }

        return false;
    }

    /**
     * Performs a preg_match_all searching IP addresses
     *
     * Populates $matches param with matches and return match count, same behavior as preg_match_all
     *
     * @param $string
     * @param $matches
     * @return int
     */
    public function pregMatchAllIps($string, &$matches)
    {
        $ipv4Pattern = '[0-9]{1,3}(?:\.[0-9]{1,3}){3}';
        $ipv6Pattern = '[a-f0-9]{0,4}(?:\:[a-f0-9]{0,4}){2,7}';
        $ipPattern = '/' . $ipv4Pattern . '|' . $ipv6Pattern . '/';

        return preg_match_all($ipPattern, $string, $matches);
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
        if (is_array($transId) && is_string($transId[0])) {
            $transId = $transId[0];
        } else if (!is_string($transId)) {
            $transId = null;
        }

        return $transId;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Signifyd_Connect_Model_Case
     * @return array
     */
    public function getPurchase($order, $payment, $case = null)
    {
        $purchase = $this->getPurchaseUpdate($order, $payment, $case);
        $originStoreCode = $order->getData('origin_store_code');

        if (!empty($originStoreCode) && $originStoreCode != 'admin' && $this->isDeviceFingerprintEnabled()) {
            $purchase['orderSessionId'] = 'M1' . base64_encode(Mage::getBaseUrl()) . $order->getQuoteId();
        }

        $purchase['browserIpAddress'] = $this->getIpAddress($order);
        $purchase['xForwardedIpAddresses'] = $this->getXForwardedFor($order);
        $purchase['orderId'] = $order->getIncrementId();
        $purchase['createdAt'] = date('c', strtotime($order->getCreatedAt())); // e.g: 2004-02-12T15:19:21+00:00
        $purchase['currency'] = $order->getOrderCurrencyCode();
        $purchase['totalPrice'] = floatval($order->getGrandTotal());
        $purchase['shippingPrice'] = floatval($order->getShippingAmount());
        $purchase['products'] = $this->getProducts($order);
        $purchase['discountCodes'] = $this->getDiscountCodes($order);
        $purchase['shipments'] = $this->getShipments($order);

        if ($originStoreCode == 'admin') {
            $purchase['orderChannel'] = 'PHONE';
        } elseif (!empty($originStoreCode)) {
            $purchase['orderChannel'] = 'WEB';
        }

        return $purchase;
    }

    /**
     * Purchase update does not contain all fields
     *
     * @param $order
     * @param $payment
     * @param Signifyd_Connect_Model_Case
     * @return array
     */
    public function getPurchaseUpdate($order, $payment, $case = null)
    {
        $purchase = array();

        $purchase['paymentGateway'] = $payment->getMethod();
        $purchase['transactionId'] = $this->getTransactionId($payment);

        $paymentHelper = $this->getPaymentHelper($order, $payment);
        $purchase['avsResponseCode'] = $paymentHelper->getAvsResponseCode();
        $purchase['cvvResponseCode'] = $paymentHelper->getCvvResponseCode();
        $purchase['avsResponseCode'] = $paymentHelper->filterAvsResponseCode($purchase['avsResponseCode']);
        $purchase['cvvResponseCode'] = $paymentHelper->filterCvvResponseCode($purchase['cvvResponseCode']);

        $this->log("Purchase from payment method: " . json_encode($purchase));

        if (is_object($case) && $case->getId()) {
            $purchaseData = $case->getEntries('purchase_data');

            if (empty($purchaseData) == false && is_array($purchaseData)) {
                foreach ($purchaseData as $key => $value) {
                    // If purchase data has not been provided, but it has been provided before, use stored value
                    if (empty($purchase[$key]) && empty($value) == false) {
                        $purchase[$key] = $value;
                    }
                }
            }
        }

        $this->log("Purchase with database data: " . json_encode($purchase));

        // Sorting array by key to avoid unnecessary case updates to Signifyd
        ksort($purchase);

        foreach ($purchase as $field => $info) {
            if (empty($info)) {
                unset($purchase[$field]);
            }
        }

        return $purchase;
    }

    /**
     * @param $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Signifyd_Connect_Model_Case
     * @return array
     */
    public function getCard($order, $payment, $case = null)
    {
        $card = $this->getPaymentHelper($order, $payment)->getCardData();
        $card['billingAddress'] = $this->getSignifydAddress($order->getBillingAddress());

        if (is_object($case) && $case->getId()) {
            $cardData = $case->getEntries('card_data');

            if (empty($cardData) == false && is_array($cardData)) {
                foreach ($cardData as $key => $value) {
                    // If card data has not been provided, but it has been provided before, use stored value
                    if (empty($card[$key]) && empty($value) == false) {
                        $card[$key] = $value;
                    }
                }
            }
        }

        // Sorting array by key to avoid unnecessary case updates to Signifyd
        ksort($card);

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
        if ($shipping_address) {
            $recipient['deliveryAddress'] = $this->getSignifydAddress($shipping_address);
            $recipient['fullName'] = $shipping_address->getFirstname() . ' ' . $shipping_address->getLastname();
            $recipient['confirmationPhone'] = $shipping_address->getTelephone();
            // Email: Note that this field is always the same for both addresses
            $recipient['confirmationEmail'] = $shipping_address->getEmail();
        }

        // Some customers have reported seeing "n/a@na.na" come through instead of a valid or null address
        //  We suspect that it is due to an older version of Magento. If it becomes unnecessary, do remove the extra check.
        if (!isset($recipient['confirmationEmail']) || $recipient['confirmationEmail'] == 'n/a@na.na') {
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

    public function generateCaseCreateData($order, $payment, $customer, $case = null)
    {
        $caseData = array();

        $caseData['purchase'] = $this->getPurchase($order, $payment, $case);
        $caseData['recipient'] = $this->getRecipient($order);
        $caseData['card'] = $this->getCard($order, $payment, $case);
        $caseData['userAccount'] = $this->getUserAccount($customer, $order);
        $caseData['clientVersion'] = $this->getVersions();

        return $caseData;
    }

    public function generateCaseUpdateData($order, $payment, $case = null)
    {
        $caseData = array();

        $caseData['purchase'] = $this->getPurchaseUpdate($order, $payment, $case);
        $caseData['card'] = $this->getCard($order, $payment, $case);
        $caseData['recipient'] = $this->getRecipient($order);

        return $caseData;
    }

    /**
     * Getting the cases url
     * @return string
     */
    public function getUrl()
    {
        return $this->apiUrl;
    }

    /**
     * Getting the case url based on the case code
     * @param $caseCode
     * @return string
     */
    public function getCaseUrl($caseCode)
    {
        return $this->getUrl() . '/' . $caseCode;
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
            $collection->addFieldToFilter('main_table.increment_id', $incrementId);
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
        if ($entity instanceof Mage_Sales_Model_Order && !$entity->isEmpty()) {
            $storeId = $entity->getStoreId();
        } elseif ($entity instanceof Signifyd_Connect_Model_Case && !$entity->isEmpty()) {
            $storeId = $this->getCaseStoreId($entity);
        } else {
            $storeId = null;
        }

        $return = Mage::getStoreConfig("signifyd_connect/{$path}", $storeId);

        return $return;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param bool $forceSend
     * @return string
     */
    public function buildAndSendOrderToSignifyd($order, $forceSend = false, $updateOnly = false)
    {
        $this->log('buildAndSendOrderToSignifyd');

        if ($order instanceof Mage_Sales_Model_Order && !$order->isEmpty()) {
            if (!$this->isEnabled($order)) {
                return 'disabled';
            }

            $orderIncrementId = $order->getIncrementId();

            /** @var Signifyd_Connect_Model_Case $case */
            $case = Mage::getModel('signifyd_connect/case')->load($orderIncrementId);
            /** @var Mage_Sales_Model_Resource_Order_Payment_Collection $payments */
            $payments = $order->getPaymentsCollection();
            /** @var Mage_Sales_Model_Order_Payment $lastPayment */
            $lastPayment = $payments->getLastItem()->isEmpty() ? null : $payments->getLastItem();
            $customer = $order->getCustomer() ? $order->getCustomer() : null;

            $caseUpdateData = $this->generateCaseUpdateData($order, $lastPayment, $case);
            $caseJson = json_encode($caseUpdateData);
            $newMd5 = md5($caseJson);

            if ($case->getId()) {
                if ($case->getMagentoStatus() == Signifyd_Connect_Model_Case::WAITING_SUBMISSION_STATUS) {
                    $isUpdate = false;
                } else {
                    $isUpdate = true;
                }
            } else {
                $isUpdate = false;
            }

            $state = $order->getState();

            if ($this->isRestricted($order->getPayment()->getMethod(), $state) ||
                $state == Mage_Sales_Model_Order::STATE_HOLDED && !$isUpdate) {
                $this->log('Case creation/update for order ' . $orderIncrementId . ' with state ' . $state . ' is restricted');
                return 'restricted';
            }

            if ($this->isIgnored($order)) {
                $this->log('Case creation/update for order ' . $orderIncrementId . ' ignored');
                return 'ignored';
            }

            if (!$isUpdate || $forceSend) {
                if ($updateOnly) {
                    return 'not ready';
                }

                $caseCreateData = $this->generateCaseCreateData($order, $lastPayment, $customer, $case);
                $caseJson = json_encode($caseCreateData);

                // Save card data to use on case update
                $cardData = $caseCreateData['card'];
                unset($cardData['billingAddress']);

                // Save payment data on purchase to use on case update
                $purchaseData = array();

                if (isset($caseCreateData['purchase']['transactionId'])) {
                    $purchaseData['transactionId'] = $caseCreateData['purchase']['transactionId'];
                }

                if (isset($caseCreateData['purchase']['avsResponseCode'])) {
                    $purchaseData['avsResponseCode'] = $caseCreateData['purchase']['avsResponseCode'];
                }

                if (isset($caseCreateData['purchase']['cvvResponseCode'])) {
                    $purchaseData['cvvResponseCode'] = $caseCreateData['purchase']['cvvResponseCode'];
                }

                $case->setOrderIncrement($orderIncrementId);
                $case->setCreated(strftime('%Y-%m-%d %H:%M:%S', time()));
                $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                $case->setEntries('md5', $newMd5);
                $case->setEntries('card_data', $cardData);
                $case->setEntries('purchase_data', $purchaseData);
                $case->save();

                $requestUri = $this->getUrl();
            } else {
                $caseCode = $case->getCode();
                if (empty($caseCode)) {
                    return 'no case for update';
                }

                $currentMd5 = $case->getEntries('md5');
                // If the case exists and has not changed, return 'exists'
                // MD5 checks must occur on case update data only
                if ($currentMd5 == $newMd5) {
                    return 'exists';
                }

                $requestUri = $this->getCaseUrl($caseCode);
            }

            $apiKey = Mage::helper('signifyd_connect')->getConfigData('settings/key', $order);
            $response = $this->request($requestUri, $caseJson, $apiKey, 'application/json', null, $isUpdate);

            try {
                $responseCode = $response->getHttpCode();
                $this->log("Response code: {$responseCode}");

                if (substr($responseCode, 0, 1) == '2') {
                    $responseData = json_decode($response->getRawResponse(), true);
                    // investigationId is deprecated
                    $caseId = isset($responseData['caseId']) ? $responseData['caseId'] : $responseData['investigationId'];

                    $case->setCode($caseId);
                    $case->setCode($caseId);

                    if ($isUpdate) {
                        $case->setEntries('md5', $newMd5);
                        $previousScore = intval($case->getScore());
                        Mage::getModel('signifyd_connect/case')->processCreation($case, $responseData);
                        $newScore = intval($case->getScore());

                        $orderComment = 'Order update submitted to Signifyd';
                        $return = 'updated';

                        if (Mage::getDesign()->getArea() == 'adminhtml') {
                            Mage::getSingleton('core/session')->addSuccess($orderComment);

                            if ($previousScore != $newScore) {
                                Mage::getSingleton('adminhtml/session')
                                    ->addWarning("Signifyd score for order {$orderIncrementId} changed from {$previousScore} to {$newScore}");
                            }
                        }
                    } else {
                        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                        $case->setTransactionId($case['purchase']['transactionId']);
                        $case->setMagentoStatus(Signifyd_Connect_Model_Case::IN_REVIEW_STATUS);
                        $case->save();

                        $orderComment = "Signifyd: case {$caseId} created for order";
                        $return = 'sent';
                    }

                    $order->addStatusHistoryComment($orderComment);
                    $order->save(); // Note: this will trigger recursion

                    return $return;
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

    public function getProductImage($product, $size = "150")
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

    public function cancelGuarantee($case)
    {
        $caseId = $case->getCode();
        $url = $this->getUrl() . "/$caseId/guarantee";
        $body = json_encode(array("guaranteeDisposition" => "CANCELED"));
        $auth = Mage::helper('signifyd_connect')->getConfigData('settings/key', $case);
        $response = $this->request($url, $body, $auth, 'application/json', null, true);

        $code = $response->getHttpCode();

        if (substr($code, 0, 1) == '2') {
            $case->setGuarantee('CANCELED');
            $case->save();

            $orderComment = "Guarantee canceled on Signifyd";
        } else {
            $orderComment = "Failed to cancel guarantee on Signifyd";
        }

        @$responseBody = json_decode($response->getRawResponse());
        if (is_object($responseBody) && !empty($responseBody->messages)) {
            $messages = implode(', ', $responseBody->messages);
            $orderComment .= " with message '{$messages}'";
        }

        $this->log("{$orderComment} (HTTP code {$code})");

        if (!empty($orderComment)) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($case->getOrderIncrement());

            if (!$order->isEmpty()) {
                $order->addStatusHistoryComment($orderComment);
                $order->save();
            }
        }
    }

    public function request(
        $url,
        $data = null,
        $auth = null,
        $contenttype = "application/x-www-form-urlencoded",
        $accept = null,
        $is_update = false
    ) {
        if (Mage::getStoreConfig('signifyd_connect/log/all')) {
            $authMask = preg_replace("/\S/", "*", $auth, strlen($auth) - 4);
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
            if ($is_update) curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
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

        $code = intval($response->getHttpCode());

        if ($code >= 400 || curl_errno($curl)) {
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
        if (!empty($paymentAuthorized)) {
            $status['authorize'] = true;
        }

        // Special case for Paypal payment type "order"
        if ($this->isPaypalOrder($paymentMethod)) {
            $paymentAdditional = $paymentMethod->getData('additional_information');
            if (isset($paymentAdditional['is_order_action']) && $paymentAdditional['is_order_action']) {
                $status['authorize'] = true;
            }
        }

        // Check capture
        if (!empty($baseTotalPaid)) {
            $status['capture'] = true;
        }

        // Check credit memo
        if (!empty($baseTotalRefunded)) {
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
        return (stripos($code, 'paypal_express') !== false) ? true : false;
    }

    public function isGuarantyDeclined($order)
    {
        $case = Mage::getModel('signifyd_connect/case')->load($order->getIncrementId());
        return ($case->getGuarantee() == 'DECLINED') ? true : false;
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
        return (bool)Mage::getStoreConfig('signifyd_connect/settings/enable_device_fingerprint');
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

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Signifyd_Connect_Helper_Payment_Interface
     */
    public function getPaymentHelper(Mage_Sales_Model_Order $order = null, Mage_Sales_Model_Order_Payment $payment = null)
    {
        if ($this->needToInitHelper($order, $payment)) {
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

    /**
     * Check if helper it is already initialized with giver order and payment
     *
     * @param Mage_Sales_Model_Order|null $order
     * @param Mage_Sales_Model_Order_Payment|null $payment
     * @return bool
     */
    protected function needToInitHelper(Mage_Sales_Model_Order $order = null, Mage_Sales_Model_Order_Payment $payment = null)
    {
        if (is_object($this->paymentHelper)) {
            if (in_array('Signifyd_Connect_Helper_Payment_Interface', class_implements($this->paymentHelper))) {
                if ($this->paymentHelper->isInitializedForOrderPayment($order, $payment)) {
                    return false;
                } else {
                    return true;
                }
            } else {
                return true;
            }
        } else {
            return true;
        }
    }
}