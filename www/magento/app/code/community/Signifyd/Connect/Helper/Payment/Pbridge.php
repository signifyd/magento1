<?php

class Signifyd_Connect_Helper_Payment_Pbridge extends Signifyd_Connect_Helper_Payment_Default
{
    public function getLast4()
    {
        $last4 = parent::getLast4();

        if (empty($last4)) {
            $last4 = $this->filterLast4($this->additionalInformation['pbridge_data']['cc_last4']);
        }

        return $last4;
    }
}
