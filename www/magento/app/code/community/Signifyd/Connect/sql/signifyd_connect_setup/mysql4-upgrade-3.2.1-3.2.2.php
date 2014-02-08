<?php

try {
    $this->startSetup();
    $this->register();
    $this->run("
    ALTER TABLE  `{$this->getTable('signifyd_connect_case')}` ADD  `status` VARCHAR( 64 ) NOT NULL DEFAULT  'PENDING',
        ADD  `code`  varchar(255) NOT NULL,
        ADD  `score` FLOAT NULL DEFAULT NULL ,
        ADD  `entries` TEXT NOT NULL ,
        ADD  `created_at` TIMESTAMP NULL DEFAULT NULL ,
        ADD  `updated_at` TIMESTAMP NULL DEFAULT NULL ;
    ");
    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}
