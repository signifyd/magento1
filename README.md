# Signifyd Extension for Magento 1

This extension integrates Magento 1 to Signifyd API, sending order and payment information and receiving guarantee disposition for the orders.

## Install

Disable Magento compilation if it is enable. It is possible to check it on Magento administration on System > Tools > Compilation. Before getting started "Compilation Status" must be "Disabled".

Download the [latest realease](https://github.com/signifyd/magento1/releases/latest) and copy/upload folder www/magento/app to the root of your Magento installation.

- For FTP and SFTP use a client software of your choice and upload www/magento/app folder at the root of your Magento installation
- For SSH, upload compressed file to target environment, decompress files and copy files using below command line:
```
cp -R www/magento/app MAGENTO_ROOT/
```

On Magento administration clear Magento cache on Sytstem > Cache Management, by clicking on both buttons at the top of the screen, "Flush Magento Cache" and "Flush Cache Storage".

Log out and log in again. Go to System > Configuration and look for "SIGNIFYD" session on configs. If you can see it, extension has been successfully installed.

If compilation is used on store, now it can be re-enabled on System > Tools > Compilation.

_Compilation it is not required for the extension to work. If store doesn't got compilation enabled before Signifyd extension installation, we advice to keep that way._

If anything goes out of expected, check [Install Troubleshoot](docs/INSTALL-TROUBLESHOOT.md).

## Configure

On Magento administration go to System > Configuration, look for section SIGNIFYD > Signifyd.

**_Before enable extension, review all settings carefully to avoid unexpected behavious_**

Basic setup consists on provide an API Key and enable extension. These settings can be found on "General" tab. Instructions to get an API Key can be found below "API Key" field.

Extension can interfere on order workflow to help store administrators process orders. These settings and instructions for using them can be found on "Order Workflow" tab.

Also it is possible to find on "Webhook URL" tab instructions to set up webhook notifications.

_Please, keep logs enabled on "Loggin" tab. Also make sure Magento logs are enable on System > Configuration, section ADVANCED > Developer, look for tab "Log Settings"_

## Test

After install and configure extension, it is important to run some tests to make sure everything is working as expected.

Place some orders and check results on administration area and Singifyd console.

If anything goes out of expected, double check configuration and log files.

## Logs

Logs can be found on MAGENTO_ROOT/var/log/signifyd_connect.log file.

## Adcanced options

These options need some technical skils and must be follow only if needed. If everything works fine, there is no needing to go over them.

_Bellow actions should be performed only by a technician or if requested by Signifyd support team._

### Restrict orders by states

It is possible to restrict extension to take actions on specific order states (not status). If orders workflow are not working as expected and [Configure](#Configure) settings does not help, follow below link for instructions.

[Restrict orders by states](docs/RESTRICT-STATES.md) 

### Restrict orders by payment methods

It is possible to restrict extension to take actions on specific payment methods. If orders workflow are not working as expected and issue is related to payment method, follow below link for instructions.

[Restrict orders by payment methods](docs/RESTRICT-PAYMENTS.md) 

### Pass payment gateway details

Signifyd need some payment information to process guarantee. More information means a better guarantee process.

Extension already try to collects all information needed, but as Magento it is very flexible, sometimes these information are on different locations on database or on external APIs.

If any information is missing on Signifyd console and a developer is available to perform customizations to get them to Signifyd, follow bellow link for instructions.   

[Pass payment gateway details](docs/PAYMENT-DETAILS.md)