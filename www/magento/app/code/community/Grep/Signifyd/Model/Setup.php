<?php

class Grep_Signifyd_Model_Setup extends Mage_Core_Model_Resource_Setup
{
    const REGISTER_URL = 'https://signifyd.com/magento/installs';
    
    public function register()
    {
        try {
            $helper = Mage::helper('grep_signifyd');
            Mage::getConfig()->reinit();
            $data = array(
                'url' => $helper->getStoreUrl(),
                'email' => $helper->getStoreEmail()
            );
            
            $helper->request(self::REGISTER_URL, json_encode($data), 'application/json');
        } catch (Exception $e) {}
    }
}
