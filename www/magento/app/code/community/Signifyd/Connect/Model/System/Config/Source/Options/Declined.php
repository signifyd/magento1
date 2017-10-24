<?php


class Signifyd_Connect_Model_System_Config_Source_Options_Declined
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 1,
                'label' => 'Leave on hold'
            ),
            array(
                'value' => 2,
                'label' => 'Update status to canceled'
            ),
            array(
                'value' => 3,
                'label' => 'Do nothing'
            )
        );
    }
}