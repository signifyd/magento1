<?php

try {
    $this->startSetup();
    $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` CHANGE `created_at` `created` timestamp NULL DEFAULT NULL");
    $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` CHANGE `updated_at` `updated` timestamp NULL DEFAULT NULL");
    Mage::getConfig()->saveConfig('signifyd_connect/advanced/retrieve_score', 0, 'default', 0);
    Mage::getConfig()->saveConfig('signifyd_connect/advanced/show_scores', 0, 'default', 0);
    Mage::getConfig()->saveConfig('signifyd_connect/advanced/hold_orders', 0, 'default', 0);
    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}
