<?php

try {
    $this->startSetup();
    $this->register();
    $this->checkColumns();
    $this->run("ALTER TABLE  `{$this->getTable('signifyd_connect_case')}` ADD INDEX (  `order_increment` );");
    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}
