<?php

class Signifyd_Connect_Model_Link
{
    public function getCommentText()
    {
        return "<a href=\"" . Mage::getUrl('signifyd/connect/api') . "\" target=\"_blank\">" . Mage::getUrl('signifyd/connect/api') . "</a>";
    }
}
