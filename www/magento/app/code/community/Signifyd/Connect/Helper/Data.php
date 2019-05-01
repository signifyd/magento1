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
    protected $apiUrl = 'https://api.signifyd.com/v2';

    /**
     * Restricted states and payment methods
     * @var array
     * @deprecated
     *
     * Restricted payment methods are no longer managed in this method.
     * Please add customized restricted payment methods to core_config_data table as below.
     *
     * INSERT INTO core_config_data(path, value) VALUES (
     * 'signifyd_connect/settings/restrict_payment_methods',
     * 'checkmo,cashondelivery,banktransfer,purchaseorder'
     * );
     */
    protected $restrictedStatesMethods;

    /** @var  Signifyd_Connect_Helper_Payment_Interface */
    protected $paymentHelper;

    /**
     * @var Signifyd_Connect_Helper_Log
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = Mage::helper('signifyd_connect/log');
    }

    /**
     * Used on mysql4-upgrade-4.4.0-4.4.1.php for backward compatibility
     *
     * Do not remove this method
     *
     * Tries to get restricted states methods (and payment methods) from class property
     *
     * @return array
     */
    public function getRestrictStatesMethods()
    {
        if (isset($this->restrictedStatesMethods) && empty($this->restrictedStatesMethods) == false) {
            return $this->restrictedStatesMethods;
        } else {
            return false;
        }
    }

    /**
     * Check if order is restricted by payment method and state
     *
     * @param $method
     * @param null $state
     * @return bool
     */
    public function isRestricted($method, $state, $action='default')
    {
        if (empty($state)) {
            return true;
        }

        $restrictedPaymentMethods = Mage::getStoreConfig('signifyd_connect/settings/restrict_payment_methods');
        $restrictedPaymentMethods = explode(',', $restrictedPaymentMethods);
        $restrictedPaymentMethods = array_map('trim', $restrictedPaymentMethods);

        if (in_array($method, $restrictedPaymentMethods)) {
            return true;
        }

        return $this->isStateRestricted($state, $action);
    }

    public function isStateRestricted($state, $action='default')
    {
        $restrictedStates = Mage::getStoreConfig("signifyd_connect/settings/restrict_states_{$action}");
        $restrictedStates = explode(',', $restrictedStates);
        $restrictedStates = array_map('trim', $restrictedStates);
        $restrictedStates = array_filter($restrictedStates);

        if (empty($restrictedStates) && $action != 'default') {
            return $this->isStateRestricted($state, 'default');
        }

        if (in_array($state, $restrictedStates)) {
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
            $this->logger->addLog('Installation date: ' . $installationDate, $order);
            $this->logger->addLog('Created at date: ' . $createdAtDate, $order);

            return true;
        } else {
            return false;
        }
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

        $this->logger->addLog($order->getXForwardedFor(), $order);
        $this->logger->addLog("Count: {$count}", $order);

        if ($count > 0) {
            $this->logger->addLog(print_r($matches, true), $order);
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

        $this->logger->addLog("Purchase from payment method: " . json_encode($purchase), $order);

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

        $this->logger->addLog("Purchase with database data: " . json_encode($purchase), $order);

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
     * Getting the API url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->apiUrl;
    }

    /**
     * Getting the case url given case code
     *
     * @param $caseCode
     * @return string
     */
    public function getCaseUrl($caseCode = null)
    {
        return $this->getUrl() . '/cases' . (empty($caseCode) ? '' : '/' . $caseCode);
    }

    /**
     * Getting a fulfillment URL for given order increment ID
     *
     * @param $orderIncrementId
     * @return string
     */
    public function getFulfillmentUrl($orderIncrementId)
    {
        return $this->getUrl() . "/fulfillments/{$orderIncrementId}";
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
        } elseif ($entity instanceof Mage_Sales_Model_Order_Shipment && $entity->getId() > 0) {
            $storeId = $entity->getStoreId();
        } elseif ($entity instanceof Mage_Sales_Model_Order_Payment && $entity->getId() > 0) {
            $storeId = $entity->getOrder()->getStoreId();
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
        $this->logger->addLog('buildAndSendOrderToSignifyd', $order);

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
            $caseCreateError = $case->getEntries('create_error');

            // If case is marked with a case creation error and it is trying to create, do not restrict
            if (($isUpdate == false && $caseCreateError == 1 && $state == Mage_Sales_Model_Order::STATE_HOLDED) == false &&
                $this->isRestricted($order->getPayment()->getMethod(), $state, ($isUpdate ? 'update' : 'create'))) {

                $this->logger->addLog('Case creation/update for order ' . $orderIncrementId . ' with state ' . $state . ' is restricted', $order);
                return 'restricted';
            }

            if ($this->isIgnored($order)) {
                $this->logger->addLog('Case creation/update for order ' . $orderIncrementId . ' ignored', $order);
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
                $case->setMagentoStatus(Signifyd_Connect_Model_Case::WAITING_SUBMISSION_STATUS);
                $case->setEntries('md5', $newMd5);
                $case->setEntries('card_data', $cardData);
                $case->setEntries('purchase_data', $purchaseData);
                $case->save();

                $requestUri = $this->getCaseUrl();
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

            $apiKey = $this->getConfigData('settings/key', $order);
            $response = $this->request($requestUri, $caseJson, $apiKey, 'application/json', null, $isUpdate, $order);

            try {
                $responseCode = $response->getHttpCode();
                $this->logger->addLog("Response code: {$responseCode}", $order);

                if ($responseCode == 204) {
                    $case->setMagentoStatus($case::COMPLETED_STATUS);
                    $case->setEntries('create_error', null);
                    $case->save();

                    $orderComment = 'Signifyd: order requested to be excluded from guarantee';
                    $order->addStatusHistoryComment($orderComment);
                    $order->save();

                    $this->logger->addLog($orderComment, $order);

                    return 'signifyd_restricted';
                } elseif (substr($responseCode, 0, 1) == '2') {
                    $responseData = json_decode($response->getRawResponse(), true);
                    // investigationId is deprecated
                    $caseId = isset($responseData['caseId']) ? $responseData['caseId'] : $responseData['investigationId'];

                    $case->setCode($caseId);

                    if ($isUpdate) {
                        $case->setEntries('md5', $newMd5);
                        $previousScore = intval($case->getScore());
                        Mage::getModel('signifyd_connect/case')->processCreation($case, $responseData);
                        $newScore = intval($case->getScore());

                        $orderComment = 'Signifyd: case updated';
                        $return = 'updated';

                        if (Mage::getDesign()->getArea() == 'adminhtml') {
                            Mage::getSingleton('core/session')->addSuccess($orderComment);

                            if ($previousScore != $newScore) {
                                Mage::getSingleton('adminhtml/session')
                                    ->addWarning("Signifyd score for order {$orderIncrementId} changed from {$previousScore} to {$newScore}");
                            }
                        }
                    } else {
                        $case->setTransactionId($case['purchase']['transactionId']);
                        $case->setMagentoStatus(Signifyd_Connect_Model_Case::IN_REVIEW_STATUS);

                        $orderComment = "Signifyd: case created {$caseId}";
                        $return = 'sent';
                    }

                    $case->setEntries('create_error', null);
                    $case->save();

                    $order->addStatusHistoryComment($orderComment);
                    $order->save();

                    return $return;
                } else {
                    if ($case->getMagentoStatus() == Signifyd_Connect_Model_Case::WAITING_SUBMISSION_STATUS) {
                        if ($response->getData('timeout') == true ||
                            $responseCode == 409 ||
                            substr($responseCode, 0, 1) == '5') {

                            if ($order->canHold()) {
                                $order->hold();
                            }

                            $order->addStatusHistoryComment('Signifyd: failed to create case');
                            $order->save();

                            $case->setEntries('create_error', 1);
                            $case->save();
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->addLog($e->__toString(), $order);
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

    public function cancelGuarantee(Signifyd_Connect_Model_Case $case)
    {
        $caseId = $case->getCode();
        $url = $this->getCaseUrl($caseId) . '/guarantee';
        $body = json_encode(array("guaranteeDisposition" => "CANCELED"));
        $auth = $this->getConfigData('settings/key', $case);
        $response = $this->request($url, $body, $auth, 'application/json', null, true, $case);

        $code = $response->getHttpCode();

        if (substr($code, 0, 1) == '2') {
            $case->setGuarantee('CANCELED');
            $case->save();

            $orderComment = "Signifyd: guarantee canceled";
        } else {
            $orderComment = "Signifyd: failed to cancel guarantee";
        }

        @$responseBody = json_decode($response->getRawResponse());
        if (is_object($responseBody) && !empty($responseBody->messages)) {
            $messages = implode(', ', $responseBody->messages);
            $orderComment .= " with message '{$messages}'";
        }

        $this->logger->addLog("{$orderComment} (HTTP code {$code})", $case);

        if (!empty($orderComment) && !$case->getOrder()->isEmpty()) {
            $case->getOrder()->addStatusHistoryComment($orderComment);
            $case->getOrder()->save();
        }
    }

    public function request(
        $url,
        $data = null,
        $auth = null,
        $contenttype = "application/x-www-form-urlencoded",
        $accept = null,
        $isUpdate = false,
        $entity = null
    ) {
        $isLogEnable = $this->getConfigData('signifyd_connect/log/all', $entity);

        if ($isLogEnable) {
            $dataObject = json_decode($data);
            $jsonError = json_last_error();

            if (empty($jsonError)) {
                if (isset($dataObject->card) && isset($dataObject->card->last4)) {
                    $dataObject->card->last4 = empty($dataObject->card->last4) ? 'not found' : 'found';
                }

                $logData = json_encode($dataObject);
            } else {
                $logData = $data;
            }

            $authMask = preg_replace("/\S/", "*", $auth, strlen($auth) - 4);
            $this->logger->addLog("Request:\nURL: {$url} \nAuth: {$authMask}\nData: {$logData}", $entity);
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
            if ($isUpdate) curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
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

        $this->logger->addLog("Response ($url):\n " . print_r($response, true), $entity);

        $code = intval($response->getHttpCode());
        $curlErrNo = curl_errno($curl);

        if ($code >= 400 || $curlErrNo) {
            $error = curl_error($curl);

            $this->logger->addLog("ERROR ($url):\n$error", $entity);

            $response->setData('error', $error);

            if ($curlErrNo == 28) {
                $response->setData('timeout', true);
            }
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
        $this->logger->addLog("Order: {$order->getIncrementId()} has a status of " . json_encode($status), $order);

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

    public function buildAndSendFulfillmentToSignifyd(Mage_Sales_Model_Order_Shipment $shipment)
    {
        if ($shipment->getId() <= 0) {
            return false;
        }

        $orderIncrementId = $shipment->getOrder()->getIncrementId();

        /** @var Signifyd_Connect_Model_Case $case */
        $case = Mage::getModel('signifyd_connect/case')->load($orderIncrementId);
        $caseCode = $case instanceof Signifyd_Connect_Model_Case ? $case->getCode() : null;

        if (empty($caseCode)) {
            return false;
        }

        $fulfillment = Mage::getModel('signifyd_connect/fulfillment')->load($shipment->getIncrementId());

        if ($fulfillment->getId()) {
            $this->logger->addLog("Fulfillment for shipment {$shipment->getIncrementId()} already sent", $shipment);
            return false;
        }

        $this->logger->addLog("Fulfillment for case order {$orderIncrementId}", $shipment);

        try {
            $fulfillmentData = $this->generateFulfillmentData($shipment);
        } catch (Exception $e) {
            $this->logger->addLog("Fulfillment error: {$e->getMessage()}", $shipment);
            return false;
        }

        if ($fulfillmentData == false) {
            $this->logger->addLog("Fulfillment for shipment {$shipment->getId()} is not ready to be sent", $shipment);
            return false;
        }

        $fulfillment = $this->prepareFulfillmentToDatabase($fulfillmentData);
        $fulfillmentJson = json_encode($fulfillmentData);
        $requestUri = $this->getFulfillmentUrl($orderIncrementId);
        $apiKey = $this->getConfigData('settings/key', $shipment);
        $response = $this->request($requestUri, $fulfillmentJson, $apiKey, 'application/json', null, false, $shipment);

        $responseHttpCode = $response->getHttpCode();

        if (substr($responseHttpCode, 0, 1) == '2') {
            $message = "Signifyd: Fullfilment sent";

            $shipment->getOrder()->addStatusHistoryComment("Signifyd: created fulfillment");
            $shipment->getOrder()->save();

            $case->setEntries('fulfilled', 1);
            $case->save();

            $fulfillment->setMagentoStatus(Signifyd_Connect_Model_Fulfillment::COMPLETED_STATUS);
            $fulfillment->save();
        } else {
            $message = "Signifyd: Fullfilment failed to send";
        }

        $this->logger->addLog($message, $shipment);

        $shipment->addComment($message);
        $shipment->save();
    }

    /**
     * @param $fulfillmentData
     * @return Signifyd_Connect_Model_Fulfillment
     */
    public function prepareFulfillmentToDatabase($fulfillmentData)
    {
        /** @var Signifyd_Connect_Model_Fulfillment $fulfillment */
        $fulfillment = Mage::getModel('signifyd_connect/fulfillment');
        $fulfillment->setData('id', $fulfillmentData['fulfillments'][0]['id']);
        $fulfillment->setData('order_id', $fulfillmentData['fulfillments'][0]['orderId']);
        $fulfillment->setData('created_at', $fulfillmentData['fulfillments'][0]['createdAt']);
        $fulfillment->setData('delivery_email', $fulfillmentData['fulfillments'][0]['deliveryEmail']);
        $fulfillment->setData('fulfillment_status', $fulfillmentData['fulfillments'][0]['fulfillmentStatus']);
        $fulfillment->setData('tracking_numbers', serialize($fulfillmentData['fulfillments'][0]['trackingNumbers']));
        $fulfillment->setData('tracking_urls', serialize($fulfillmentData['fulfillments'][0]['trackingUrls']));
        $fulfillment->setData('products', serialize($fulfillmentData['fulfillments'][0]['products']));
        $fulfillment->setData('shipment_status', $fulfillmentData['fulfillments'][0]['shipmentStatus']);
        $fulfillment->setData('delivery_address', serialize($fulfillmentData['fulfillments'][0]['deliveryAddress']));
        $fulfillment->setData('recipient_name', $fulfillmentData['fulfillments'][0]['recipientName']);
        $fulfillment->setData('confirmation_name', $fulfillmentData['fulfillments'][0]['confirmationName']);
        $fulfillment->setData('confirmation_phone', $fulfillmentData['fulfillments'][0]['confirmationPhone']);
        $fulfillment->setData('shipping_carrier', $fulfillmentData['fulfillments'][0]['shippingCarrier']);

        return $fulfillment;
    }

    public function generateFulfillmentData(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $trackingNumbers = $this->getTrackingNumbers($shipment);

        // At this moment fulfillment must be sent only if it has tracking numbers
        if (empty($trackingNumbers)) {
            return false;
        }

        $deliveryEmail = $this->isOrderVirtual($shipment->getOrder()) ? $this->getDeliveryEmail($shipment) : null;

        $fulfillment = array(
            'id' => $shipment->getIncrementId(),
            'orderId' => $shipment->getOrder()->getIncrementId(),
            'createdAt' => $shipment->getCreatedAtDate()->toString("yyyy-MM-ddTHH:mm:ss") . 'Z',
            'deliveryEmail' => $deliveryEmail,
            'fulfillmentStatus' => $this->getFulfillmentStatus($shipment),
            'trackingNumbers' => $trackingNumbers,
            'trackingUrls' => $this->getTrackingUrls($shipment),
            'products' => $this->getFulfillmentProducts($shipment),
            'shipmentStatus' => $this->getShipmentStatus($shipment),
            'deliveryAddress' => array(
                'streetAddress' => $shipment->getShippingAddress()->getStreetFull(),
                'unit' => null,
                'city' => $shipment->getShippingAddress()->getCity(),
                'provinceCode' => $shipment->getShippingAddress()->getRegionCode(),
                'postalCode' => $shipment->getShippingAddress()->getPostcode(),
                'countryCode' => $shipment->getShippingAddress()->getCountry()
            ),
            'recipientName' => $shipment->getShippingAddress()->getName(),
            'confirmationName' => null,
            'confirmationPhone' => null,
            'shippingCarrier' => $shipment->getOrder()->getShippingMethod()
        );

        return array('fulfillments' => array($fulfillment));
    }

    public function isOrderVirtual(Mage_Sales_Model_Order $order)
    {
        $isVirtual = true;

        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($order->getAllItems() as $item) {
            if ($item->getIsVirtual() == false) {
                $isVirtual = false;
                break;
            }
        }

        return $isVirtual;
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return string
     */
    public function getDeliveryEmail(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $deliveryEmail = $shipment->getOrder()->getShippingAddress()->getEmail();

        if (empty($deliveryEmail)) {
            $deliveryEmail = $shipment->getOrder()->getCustomerEmail();
        }

        return $deliveryEmail;
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return string
     */
    public function getFulfillmentStatus(Mage_Sales_Model_Order_Shipment $shipment)
    {
        if ($shipment->getOrder()->canShip() == false) {
            return 'complete';
        } else {
            return 'partial';
        }
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return array
     */
    public function getTrackingNumbers(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $trackingNumbers = array();

        /** @var Mage_Sales_Model_Resource_Order_Shipment_Track_Collection $trackingCollection */
        $trackingCollection = $shipment->getTracksCollection();

        /** @var Mage_Sales_Model_Order_Shipment_Track $tracking */
        foreach ($trackingCollection->getItems() as $tracking) {
            $number = trim($tracking->getNumber());

            if (empty($number) == false) {
                $trackingNumbers[] = $tracking->getNumber();
            }
        }

        return $trackingNumbers;
    }

    /**
     * Magento default tracking URLs are not accessible if you're not logged in
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return array
     */
    public function getTrackingUrls(Mage_Sales_Model_Order_Shipment $shipment)
    {
        return array();
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return array
     */
    public function getFulfillmentProducts(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $products = array();

        /** @var Mage_Sales_Model_Order_Shipment_Item $item */
        foreach ($shipment->getAllItems() as $item) {
            $product = $item->getOrderItem()->getProduct();

            /**
             * About fields itemCategory and itemSubCategory, Chris Morris has explained on MAG-286
             *
             * This is meant to determine which products that were in the create case are associated to the fulfillment.
             * Since we don’t pass itemSubCategory or itemCategory in the create case we should keep these empty.
             */

            try {
                $imageUrl = $item->getOrderItem()->getProduct()->getImageUrl();
            } catch (Exception $e) {
                $imageUrl = null;
            }

            $products[] = array(
                'itemId' => $item->getSku(),
                'itemName' => $item->getName(),
                'itemIsDigital' => (bool) $item->getOrderItem()->getIsVirtual(),
                'itemCategory' => null,
                'itemSubCategory' => null,
                'itemUrl' => $product->getUrlInStore(),
                'itemImage' => $imageUrl,
                'itemQuantity' => floatval($item->getQty()),
                'itemPrice' => floatval($item->getPrice()),
                'itemWeight' => floatval($item->getWeight())
            );
        }

        return $products;
    }

    /**
     * Magento do not track shipment stauts
     *
     * Rewrite this method if you have and want to send these informations to Signifyd
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return null
     */
    public function getShipmentStatus(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $validShipmentStatus = array(
            'in transit',
            'out for delivery',
            'waiting for pickup',
            'failed attempt',
            'delivered',
            'exception'
        );

        return null;
    }
}