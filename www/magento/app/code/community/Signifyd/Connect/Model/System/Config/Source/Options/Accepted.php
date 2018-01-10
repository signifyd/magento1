<?php


class Signifyd_Connect_Model_System_Config_Source_Options_Accepted
{
    public function toOptionArray()
    {
        $options = array(
            array(
                'value' => 1,
                'label' => 'Update order status'
            ),
            array(
                'value' => 3,
                'label' => 'Do nothing'
            ),
            array(
                'value' => 4,
                'label' => 'Capture payment and update order status'
            )
        );

        if (Mage::getStoreConfig('signifyd_connect/advanced/accepted_from_guaranty') == 2) {
            $options[] = array(
                'value' => 2,
                'label' => 'Leave on hold'
            );
        }

        return $options;
    }
}