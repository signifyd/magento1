<?php

$this->startSetup();
Mage::getConfig()->saveConfig('signifyd_connect/settings/url', "https://api.signifyd.com/v2", 'default', 0);
$this->endSetup();
