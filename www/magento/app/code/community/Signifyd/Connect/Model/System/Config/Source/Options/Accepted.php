<?php


class Signifyd_Connect_Model_System_Config_Source_Options_Accepted
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 2,
                'label' => 'Leave On Hold'
            ),
            array(
                'value' => 1,
                'label' => 'Unhold Order'
            )
        );
    }
}