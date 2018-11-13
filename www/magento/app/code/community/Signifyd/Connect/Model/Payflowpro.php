<?php

class Signifyd_Connect_Model_Payflowpro extends Mage_Paypal_Model_Payflowpro
{
    protected function _postRequest(Varien_Object $request)
    {
        /** @var Varien_Object $result */
        $result = parent::_postRequest($request);

        // Using try .. catch to avoid errors on order processing iof anything goes wrong
        try {
            $paymentData = $result->getData();
            $signifydData = array();

            if (isset($paymentData['procavs'])) {
                $signifydData['cc_avs_status'] = $paymentData['procavs'];
            }

            if (isset($paymentData['avsaddr']) && isset($paymentData['avszip'])) {
                $signifydData['cc_avs_status'] = $paymentData['avsaddr'] . $paymentData['avszip'];
            }

            if (isset($paymentData['cvv2match'])) {
                $signifydData['cc_cid_status'] = $paymentData['cvv2match'];
            }

            if (isset($paymentData['acct'])) {
                $signifydData['cc_last4'] = substr($paymentData['acct'], -4);
            }

            if (isset($paymentData['expdate'])) {
                $signifydData['cc_exp_month'] = substr($paymentData['expdate'], 0, 2);
                $signifydData['cc_exp_year'] = substr($paymentData['expdate'], -2);
            }

            if (!empty($signifydData)) {
                Mage::register('signifyd_data', $signifydData);
            }
        }
        catch (Exception $e) {
            Mage::helper('signifyd_connect')->logError('Failed to collect data from Payflow: ' . $e->getMessage());
        }

        return $result;
    }
}