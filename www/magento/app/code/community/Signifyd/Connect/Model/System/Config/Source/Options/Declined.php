<?php


class Signifyd_Connect_Model_System_Config_Source_Options_Declined
{
    public function toOptionArray()
    {
        $options = array(
            // Disabled, Chris Morris request. We'll drop this by now and resume on future
//            array(
//                'value' => 2,
//                'label' => 'Update status to canceled'
//            ),
            array(
                'value' => 3,
                'label' => 'Do nothing'
            )
        );

        if (Mage::getStoreConfig('signifyd_connect/advanced/declined_from_guaranty') == 1) {
            $options[] = array(
                'value' => 1,
                'label' => 'Leave on hold'
            );
        }

        return $options;
    }
}