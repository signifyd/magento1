<?php

/*  @var $this Mage_Sales_Model_Mysql4_Setup  */
$this->startSetup();

/** @var Signifyd_Connect_Helper_Data $helper */
$helper = Mage::helper('signifyd_connect');

// Payment method and states restrictions have been removed from extension code
// If any value is returned, it is needed to check if there is any customizations for payment methods restrictions
$oldRestrictedStatesMethods = $helper->getRestrictStatesMethods();

if (is_array($oldRestrictedStatesMethods) &&
    empty($oldRestrictedStatesMethods) == false &&
    isset($oldRestrictedStatesMethods['all'])) {

    $oldRestrictedPaymentMethods = explode(',', $oldRestrictedStatesMethods['all']);
    $oldRestrictedPaymentMethods = array_map('trim', $oldRestrictedPaymentMethods);

    $restrictedPaymentMethods = Mage::getStoreConfig('signifyd_connect/settings/restrict_payment_methods');
    $restrictedPaymentMethods = explode(',', $restrictedPaymentMethods);
    $restrictedPaymentMethods = array_map('trim', $restrictedPaymentMethods);

    $diff1 = array_diff($oldRestrictedPaymentMethods, $restrictedPaymentMethods);
    $diff2 = array_diff($restrictedPaymentMethods, $oldRestrictedPaymentMethods);

    // If anything is different, so use $oldRestrictedPaymentMethods on database settings
    if (empty($diff1) == false || empty($diff2) == false) {
        $oldRestrictedPaymentMethods = implode(',', $oldRestrictedPaymentMethods);
        Mage::getConfig()->saveConfig('signifyd_connect/settings/restrict_payment_methods', $oldRestrictedPaymentMethods);
    }
}

$this->endSetup();
