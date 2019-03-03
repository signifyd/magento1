[Signifyd Extension for Magento 1](../README.md) > Install Troubleshoot

# Install Troubleshoot

## Third-party cache errors

If something does not go as expected, try to clear any additional caches on the environment (e.g. PHP APC or OPCache, Redis, Varnish).

## There is no "SIGNIFYD" session on System > Configuration

Go to System > Configuration, click on section ADVANCED > Advanced and look for Signifyd_Connect on the modules list. This can found on the "Disable Modules Output" tab. Be sure that this section is visible and the setting is enabled.

If it is "Disabled", enable it, clear Magento cache, log out, log in again and check for it again.

If it is not present, check if it is possible to see the files below on the Magento installation folder:
- MAGENTO_ROOT/app/etc/modules/Signifyd_Connect.xml
- MAGENTO_ROOT/app/code/community/Signifyd/Connect/etc/system.xml

If the above files are not present, please repeat the installation steps.

## A 404 page shows when accessing Signifyd session on System > Configuration

Try to log out and log in on admin and check it again.

## Logs show database related errors

On the MySQL database check for the existence of 'signifyd_connect_case' table using below command:

```
DESC signifyd_connect_case
```

Verify you see the following columns on the table:
- order_increment
- signifyd_status
- code
- score
- guarantee
- entries
- transaction_id
- created
- updated
- magento_status
- retries

If you find any missing columns or issues with the table, check if the Magento installation scripts has been ran for the latest version. 

On file MAGENTO_ROOT/app/code/community/Signifyd/Connect/etc/config.xml check for `<version>` tag

```
<modules>
    <Signifyd_Connect>
        <version>4.4.2</version>
    </Signifyd_Connect>
</modules>
```

Run below SQL command on MySQL:

```
SELECT * FROM core_resource WHERE code='signifyd_connect_setup';
```

The results of the above command should match with `<version>` tag from config.xml file. If does not match, run installation steps again and make sure to clean every possible cache on Magento administration and environment.

## All of the steps were followed but some error prevented the extension from installing succesfully

Check for any log errors on the web server (e.g. Apache, NGINX) and on PHP logs. Also check for errors on MAGENTO_ROOT/var/log on files system.log, exception.log and signifyd_connect.log. If you are still stuck you can [contact our support team](https://community.signifyd.com/support/s/)
