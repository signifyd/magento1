<?php


class Signifyd_Connect_Model_System_Config_Source_Options_Loglevel
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 1,
                'label' => 'Info'
            ),
            array(
                'value' => 0,
                'label' => 'None'
            ),
            array(
                'value' => 2,
                'label' => 'Debug'
            )
        );
    }
}