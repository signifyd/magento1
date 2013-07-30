<?php

class Signifyd_Connect_Model_Setup extends Mage_Core_Model_Resource_Setup
{
    const REGISTER_URL = 'https://signifyd.com/magento/installs';
    
    public function register()
    {
        try {
            $helper = Mage::helper('signifyd_connect');
            Mage::getConfig()->reinit();
            $data = array(
                'url' => $helper->getStoreUrl(),
                'email' => $helper->getStoreEmail()
            );
            
            $helper->request(self::REGISTER_URL, json_encode($data), null, 'application/json');
        } catch (Exception $e) {}
    }
}
