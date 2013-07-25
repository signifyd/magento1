<?php

class Grep_Signifyd_Helper_Data extends Mage_Core_Helper_Abstract
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
        $collection = Mage::getModel('grep_signifyd/case')->getCollection()->addFieldToFilter('order_increment', $order->getIncrementId());
        
        if (count($collection)) {
            return true;
        }
        
        return false;
    }
    
    public function markProcessed($order)
    {
        $case = Mage::getModel('grep_signifyd/case');
        $case->setOrderIncrement($order->getIncrementId());
        $case->save();
    }
    
    public function unmarkProcessed($order)
    {
        $collection = Mage::getModel('grep_signifyd/case')->getCollection()->addFieldToFilter('order_increment', $order->getIncrementId());
        
        foreach ($collection as $case) {
            $case->delete();
        }
    }
    
    public function request($url, $data=null, $auth=null, $contenttype="application/x-www-form-urlencoded")
    {
        if (Mage::getStoreConfig('grep_signifyd/log/request')) {
            Mage::log("Request:\nURL: $url \nAuth: $auth\nData: $data", null, 'grep_signifyd.log');
        }
        
        $curl = curl_init();
        $response = new Varien_Object;

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

        if ($data) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: $contenttype", "Content-length: " . strlen($data)));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $raw_response = curl_exec($curl);
        $response->setRawResponse($raw_response);
        
        $response_data = curl_getinfo($curl);
        $response->addData($response_data);
        
        if (Mage::getStoreConfig('grep_signifyd/log/response')) {
            Mage::log("Response ($url):\n " . print_r($response, true), null, 'grep_signifyd.log');
        }
        
        if ($raw_response === false || curl_errno($curl)) {
            $error = curl_error($curl);
            
            if (Mage::getStoreConfig('grep_signifyd/log/error')) {
                Mage::log("ERROR ($url):\n$error", null, 'grep_signifyd.log');
            }
            
            $response->setData('error', $error);
        }
        
        curl_close($curl);
        
        return $response;
    }
}
