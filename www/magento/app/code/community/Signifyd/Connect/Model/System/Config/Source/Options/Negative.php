<?php

class Signifyd_Connect_Model_System_Config_Source_Options_Negative
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'nothing',
                'label' => 'Nothing',
            ),
            array(
                'value' => 'hold',
                'label' => 'Hold Order',
            ),
            array(
                'value' => 'cancel',
                'label' => 'Cancel Order',
            ),
        );
    }
}