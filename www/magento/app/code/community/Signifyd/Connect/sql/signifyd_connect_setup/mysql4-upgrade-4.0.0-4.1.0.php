<?php

try {
    $this->startSetup();

    $currentAcceptedAction = Mage::getStoreConfig('signifyd_connect/advanced/accepted_from_guaranty');
    $currentDeclinedAction = Mage::getStoreConfig('signifyd_connect/advanced/declined_from_guaranty');

    $currentAcceptedAction = Mage::getConfig()->getNode('signifyd_connect/advanced/accepted_from_guaranty', 'default', 0);
    $currentDeclinedAction = Mage::getConfig()->getNode('signifyd_connect/advanced/declined_from_guaranty', 'default', 0);

    $newAcceptedAction = $currentAcceptedAction == 1 ? 3 : ($currentAcceptedAction == 2 ? 4 : $currentAcceptedAction);
    $newDeclinedAction = $currentDeclinedAction == 1 ? 3 : $currentDeclinedAction;

    Mage::getConfig()->saveConfig('signifyd_connect/advanced/accepted_from_guaranty', $newAcceptedAction, 'default', 0);
    Mage::getConfig()->saveConfig('signifyd_connect/advanced/declined_from_guaranty', $newDeclinedAction, 'default', 0);

    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}
