<?php

class Signifyd_Connect_Model_Link
{
    public function getCommentText()
    {
        return "Turn this on if you would like to receive signifyd scores. Visit this url to confirm the callback functionality is working: <a href=\"" . Mage::getUrl('signifyd/connect/api') . "\" target=\"_blank\">" . Mage::getUrl('signifyd/connect/api') . "</a>";
    }
}
