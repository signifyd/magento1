<?php

try {
    $this->startSetup();
    $this->register();
    $this->run("ALTER TABLE  `{$this->getTable('signifyd_connect_case')}` ADD  `magento_status`  VARCHAR( 64 ) NOT NULL DEFAULT 'waiting_submission'");
    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}