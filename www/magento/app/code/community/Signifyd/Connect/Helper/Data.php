<?php

class Signifyd_Connect_Helper_Data extends Mage_Core_Helper_Abstract
{
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
        $collection = Mage::getModel('signifyd_connect/case')->getCollection()->addFieldToFilter('order_increment', $order_id);
        
        foreach ($collection as $case) {
            if ($case->getCode()) {
                return "https://www.signifyd.com/cases/" . $case->getCode();
            }
        }
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
        $collection = Mage::getModel('signifyd_connect/case')->getCollection()->addFieldToFilter('order_increment', $order->getIncrementId());
        
        if (count($collection)) {
            return true;
        }
        
        return false;
    }
    
    public function markProcessed($order)
    {
        $case = Mage::getModel('signifyd_connect/case');
        $case->setOrderIncrement($order->getIncrementId());
        $case->setCreatedAt(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->setUpdatedAt(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
        
        return $case;
    }
    
    public function unmarkProcessed($order)
    {
        $collection = Mage::getModel('signifyd_connect/case')->getCollection()->addFieldToFilter('order_increment', $order->getIncrementId());
        
        foreach ($collection as $case) {
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
