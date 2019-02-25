[Signifyd Extension for Magento 1](../README.md) > Install Troubleshoot

# Install Troubleshoot

##Third-party cache errors

If anything does not go as expected, try to clear any additional caches on environment (e.g. PHP APC or OPCache, Redis, Varnish).

##There is no "SIGNIFYD" session on System > Configuration

Go to System > Configuration, click on section ADVANCED > Advanced and look for Signifyd_Connect on modules list at "Disable Modules Output" tab. It must be present and must be Enable.

If it is "Disable", enable it, clear Magento cache, log out, log in again and check for it again.

If it is not present check if it is possible to see below files on Magento installation folder:
- MAGENTO_ROOT/app/etc/modules/Signifyd_Connect.xml
- MAGENTO_ROOT/app/code/community/Signifyd/Connect/etc/system.xml

If above files are not present, please repeat installation steps.

##A 404 page shows when accessing Signifyd session on System > Configuration

Try to log out and log in on admin and check it again.

##Logs show database related errors

On MySQL database check for the existence of 'signifyd_connect_case' table using below command:

```
DESC signifyd_connect_case
```

It is expected to see below columns on this table:
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

If anything it is out of expected, check if Magento installation scripts had run for the latest version. 

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

Results of above command should match with `<version>` tag from config.xml file. If does not match, run installation steps again and make sure to clean every possible cache on Magento administration and environment.

##All steps followed and some error prevents extension to work as expected for installation

Check for any log errors on web server (e.g. Apache, NGINX) and on PHP logs. Also check for errors on MAGENTO_ROOT/var/log on files system.log, exception.log and signifyd_connect.log.