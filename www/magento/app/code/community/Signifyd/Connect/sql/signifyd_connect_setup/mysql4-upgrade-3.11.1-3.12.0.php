<?php

try {
    $this->startSetup();
    $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` ADD `transaction_id` VARCHAR(64) NULL;"
    );
    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}
