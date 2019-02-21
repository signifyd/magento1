[Signifyd Extension for Magento 1](../README.md) > Restrict orders by payment methods

# Restrict orders by payment methods

The extension can be restricted from acting on some payment methods. Orders placed with restricted payment methods will not have a case created on Signifyd, neither will the extension interfere in any way with their status workflow.

On all below SQL statements, `checkmo,cashondelivery,banktransfer,purchaseorder must be replaced with the desired settings. The list of payment methods codes must be comma separated in order for them to be restricted for Signifyd cases creation. So include the payment methods that must not have their orders submitted to Signifyd analysis on the list.

To modify the restricted payment methods, run the command below on the database:`

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd_connect/settings/restrict_payment_methods', 'paypal_express,checkmo,cashondelivery,banktransfer,purchaseorder');
```

To modify an existing setting use the command below:

```
UPDATE core_config_data SET value='checkmo,cashondelivery,banktransfer,purchaseorder' WHERE path='signifyd_connect/settings/restrict_payment_methods';
```

To exclude setting and use the extension defaults, just delete it from the database:

```
DELETE FROM core_config_data WHERE path='signifyd_connect/settings/restrict_payment_methods';
```

## Checking current restriction settings

To check all current restriction settings on the database, run the command below:

```
SELECT * FROM core_config_data WHERE path LIKE 'signifyd_connect/settings/restrict%';