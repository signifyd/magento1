<?php

class Signifyd_Connect_Model_System_Config_Source_Options_Positive
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'nothing',
                'label' => 'Nothing',
            ),
            array(
                'value' => 'unhold',
                'label' => 'Unhold Order',
            ),
        );
    }
}