<?php

class Signifyd_Connect_Model_Link
{
    public function getCommentText()
    {
        return "<a href=\"" . Mage::getUrl('signifyd/connect/api') . "\" target=\"_blank\">" . Mage::getUrl('signifyd/connect/api') . '</a><br />Use this URL to setup your Magento <a href="https://app.signifyd.com/settings/notifications">webhook</a> from the Signifyd console. You MUST setup the webhook to enable order workflows and syncing of guarantees back to Magento.';
    }
}
