<?php


class Signifyd_Connect_Model_Fulfillment extends Mage_Core_Model_Abstract
{
    // Fulfillment created on database and not submitted to Signifyd
    const WAITING_SUBMISSION_STATUS = "waiting_submission";

    // Fulfillment successfully submited to Signifyd
    const COMPLETED_STATUS = "completed";

    protected function _construct()
    {
        $this->_init('signifyd_connect/fulfillment');
    }
}