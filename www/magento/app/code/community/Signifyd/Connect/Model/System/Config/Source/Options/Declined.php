<?php


class Signifyd_Connect_Model_System_Config_Source_Options_Declined
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 1,
                'label' => 'Keep order Onhold'
            ),
            array(
                'value' => 2,
                'label' => 'Cancel order with authorize redeem'
            )
        );
    }
}