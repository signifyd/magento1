<?php

interface Signifyd_Connect_Helper_Payment_Interface
{
    public function getAvsResponseCode();
    public function getCvvResponseCode();
    public function getCardHolderName();
    public function getBin();
    public function getLast4();
    public function getExpiryMonth();
    public function getExpiryYear();
}