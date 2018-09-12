<?php

$this->startSetup();

Mage::getConfig()->saveConfig(
    'signifyd_connect/log/installation_date',
    Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'),
    'default',
    0);

$this->endSetup();
