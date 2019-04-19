<?php

class Signifyd_Connect_Helper_Debug extends Signifyd_Connect_Helper_Log
{
    protected $logFile = 'signifyd_connect_debug.log';

    public function isLogEnabled()
    {
        return (Mage::getStoreConfig('signifyd_connect/log/all') == 2);
    }
}