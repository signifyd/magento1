<?php

try {
    $this->startSetup();
    $this->run("
        ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD `transaction_id` VARCHAR(64) NULL;

        CREATE TABLE IF NOT EXISTS `{$this->getTable('signifyd_connect_retries')}` (
          `order_increment` varchar(255) NOT NULL,
          `created` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`order_increment`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;
    ");
    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}
