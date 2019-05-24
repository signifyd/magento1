# Signifyd Extension for Magento 1

Signifyd’s Magento extension enables merchants on Magento 1 to integrate with Signifyd, automating fraud prevention and protecting them in case of chargebacks.

## Install via Magento Connect Manager
View our Magento 1 product manual for instructions on installing the extension via [Magento's Connect Manager](https://www.signifyd.com/resources/manual/magento-v4-0/#installation)

## Install via SFTP/FTP or SSH

Before getting started "Compilation Status" must be "Disabled". You can disable this by going to the Magento admin System > Tools > Compilation. 

Download the [latest release](https://github.com/signifyd/magento1/releases/latest) and copy/upload the folder www/magento/app to the root of your Magento installation.

- For FTP and SFTP use a client software of your choice and upload www/magento/app folder at the root of your Magento installation
- For SSH, upload compressed file to target environment, decompress files and copy files using the following command line:
```
cp -R www/magento/app MAGENTO_ROOT/
```

On Magento admin clear the Magento cache from System > Cache Management, by selecting "Flush Magento Cache" and "Flush Cache Storage".

Log out of Magento admin and then log in again. Go to System > Configuration and look for "SIGNIFYD" session on configs. If Signifyd is visible from the page the extension was successfully installed.

If the compilation was previously enabled you can now re-enable it by going to System > Tools > Compilation.

_Compilation is not required for the Signifyd extension to work. If compilation was not previously enabled before installing the extension we recommend not enabling it._

If you run into an issue, view the [install troubleshooting doc](docs/INSTALL-TROUBLESHOOT.md).

## Configure
View our Magento 1 product manual to learn how to [configure the extension](https://www.signifyd.com/resources/manual/magento-v4-0/#installation)

## Logs

Info or general logs can be found on MAGENTO_ROOT/var/log/signifyd_connect.log file.
If debug mode is enabled those logs can be found on MAGENTO_ROOT/var/log/signifyd_connect_debug.log

## Advanced Settings

These settings enable fine grain control over advanced capabilities of the extension.

_Updating these settings should only be performed by an experienced developer under the supervision and instruction of Signifyd. If these steps are not completed correctly you may experience issues with the Signifyd extension and or your Magento store._

### Restrict orders by states

Restrict orders with specific order states (not status) from being sent to Signifyd.

[Learn how to restrict orders by states](docs/RESTRICT-STATES.md) 

### Restrict orders by payment methods

Restrict orders with specific payment methods from being sent to Signifyd.

[Learn how to restrict orders by payment methods](docs/RESTRICT-PAYMENTS.md) 

### Pass custom payment data using payment helpers

The Signifyd extension will try to collect payment data (avsResponseCode, cvvResponseCode, cardBin, cardLast4, cardExpiryMonth and cardExpiryYear) from default locations within the Magento database when submitting an order for fraud review. If you are not using one of the following payment gateways Authorize.net, Braintree, Paypal Payflow, or Stripe (Cryozonic extension) you may need to pass payment data to Signifyd using the extension's payment helper module. You can use the Signifyd console to determine whether payment data is being succesfully sent to Signifyd for a given order. 

[Learn how to pass payment details using the Payment Helper module](docs/PAYMENT-DETAILS.md)
