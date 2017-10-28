<?php

interface Signifyd_Connect_Helper_Payment_Interface
{
    public function getCardData();

    public function getAvsResponseCode();
    public function getCvvResponseCode();
    public function getCardHolderName();
    public function getBin();
    public function getLast4();
    public function getExpiryMonth();
    public function getExpiryYear();

    public function filterAvsResponseCode($avsResponseCode);
    public function filterCvvResponseCode($cvvResponseCode);
    public function filterBin($bin);
    public function filterLast4($last4);
    public function filterExpiryMonth($expiryMonth);
    public function filterExpiryYear($expiryYear);

}