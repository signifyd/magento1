<?php

class Signifyd_Connect_Model_Payflowlink extends Mage_Paypal_Model_Payflowlink
{
    protected function _postRequest(Varien_Object $request)
    {
        /** @var Varien_Object $result */
        $result = parent::_postRequest($request);

        // Using try .. catch to avoid errors on order processing if anything goes wrong
        try {
            $paymentData = $result->getData();

            if (isset($paymentData['custref']) && !empty($paymentData['custref'])) {
                // At the moment the data is returned the order is alreary placed on Magento and the order object
                // inside the payment method ($this->getOrder()) is empty, so it is needed to load it
                /** @var Mage_Sales_Model_Order $order */
                $order = Mage::getModel('sales/order')->loadByIncrementId($paymentData['custref']);

                if (!$order->isEmpty()) {
                    $signifydData = array();

                    if (isset($paymentData['procavs'])) {
                        $signifydData['cc_avs_status'] = $paymentData['procavs'];
                    }
                    if (isset($paymentData['proccvv2'])) {
                        $signifydData['cc_cid_status'] = $paymentData['proccvv2'];
                    }
                    if (isset($paymentData['acct'])) {
                        $signifydData['cc_last4'] = substr($paymentData['acct'], -4);
                    }
                    if (isset($paymentData['expdate'])) {
                        $signifydData['cc_exp_month'] = substr($paymentData['expdate'], 0, 2);
                        $signifydData['cc_exp_year'] = substr($paymentData['expdate'], -2);
                    }

                    if (!empty($signifydData)) {
                        $order->getPayment()->setAdditionalInformation('signifyd_data', $signifydData);
                        $order->getPayment()->save();
                    }
                }
            }
        }
        catch (Exception $e) {
            Mage::helper('signifyd_connect')->logError('Failed to collect data from Payflow: ' . $e->getMessage());
        }

        return $result;
    }
}