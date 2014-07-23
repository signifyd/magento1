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
    
    public function checkColumns()
    {
        /* Attempt to add commonly missing columns, allow silent failure if present */
        
        try {
            $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` CHANGE  `status`  `signifyd_status` VARCHAR( 64 ) NOT NULL DEFAULT  'PENDING'");
        } catch (Exception $e) {};
        
        try {
            $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD  `signifyd_status` VARCHAR( 64 ) NOT NULL DEFAULT 'PENDING';");
        } catch (Exception $e) {};
        
        try {
            $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD  `code` VARCHAR(255) NOT NULL;");
        } catch (Exception $e) {};
        
        try {
            $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD  `score` FLOAT NULL DEFAULT NULL;");
        } catch (Exception $e) {};
        
        try {
            $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD  `entries` TEXT NULL DEFAULT NULL;");
        } catch (Exception $e) {};
        
        try {
            $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD  `created_at` TIMESTAMP NULL DEFAULT NULL;");
        } catch (Exception $e) {};
        
        try {
            $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD  `updated_at` TIMESTAMP NULL DEFAULT NULL;");
        } catch (Exception $e) {};
    }
}
