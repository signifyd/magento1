<?php


class Signifyd_Connect_Model_System_Config_Source_Options_Accepted
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 2,
                'label' => 'Leave on hold'
            ),
            array(
                'value' => 1,
                'label' => 'Update status to processing'
            ),
            array(
                'value' => 3,
                'label' => 'Do nothing'
            )
        );
    }
}