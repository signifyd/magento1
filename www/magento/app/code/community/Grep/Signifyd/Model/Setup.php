<?php

class Grep_Signifyd_Model_Setup extends Mage_Core_Model_Resource_Setup
{
    const REGISTER_URL = 'http://grep.net.au/someurl';
    
    public function register()
    {
        try {
            $helper = Mage::helper('grep_signifyd');
            Mage::getConfig()->reinit();
            $data = array(
                'name' => $helper->getStoreName(),
                'email' => $helper->getStoreEmail(),
                'url' => $helper->getStoreUrl()
            );
            
            $helper->request(self::REGISTER_URL, http_build_query($data));
        } catch (Exception $e) {}
    }
}
