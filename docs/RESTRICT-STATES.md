[Signifyd Extension for Magento 1](../README.md) > Restrict orders by states

# Restrict orders by states

The actions of the extension can also be restricted according to the order state. E.g. by default, the extension restricts any action on payment_review state, to make sure that the extension will not interfere with the payment workflow.

**_Warning: the wrong settings can interfere on the checkout and payment workflows and make the process no longer work as expected. It is recommended to test new settings on a development environment carefully. Default settings have been tested with Magento default payment methods on clean installations and all of them work well._**

There are three different settings for states restrictions: default, create and update. By default, these settings are as below:
- default: `pending_payment,payment_review,canceled,closed,complete`
- create: `holded,pending_payment,payment_review,canceled,closed,complete`
- update: `[empty]`

Default setting will be used on all actions that do not have a setting of their own. Create setting will be used for Signifyd case creation. Update setting will be used to restrict states for case update.

As default setting includes values for default and create, and does not include any setting for update, case creation will use create setting and all other situations will use the default one.

Besides case creation and update, the default setting is also used on order workflow actions: hold, remove from hold, cancel and capture payment.

Be aware that these settings use Magento states (not status), which must be one of these: new, pending_payment, payment_review, processing, complete, closed, canceled, holded. States should be provided as a comma separated list of one or more of those values.

## Changing create setting

Use the commands below on the database to work with the create setting. Replace `holded,pending_payment,payment_review,canceled,closed,complete` with desired states. The extension will not submit the case to Signifyd for provided states:

To include create setting in the database, use the command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd_connect/settings/restrict_states_create', 'holded,pending_payment,payment_review,canceled,closed,complete');
```

To modify an existing setting, use the command below:

```
UPDATE core_config_data SET value='holded,pending_payment,payment_review,canceled,closed,complete' WHERE path='signifyd_connect/settings/restrict_states_create';
```

To exclude a setting and use the extension defaults, just delete it from database:

```
DELETE FROM core_config_data WHERE path='signifyd_connect/settings/restrict_states_create';
```

## Changing update setting
Use the commands below on database to work with the update setting. Replace `pending_payment,payment_review,canceled,closed,complete` with desired states. The extension will not update the case on Signifyd for the provided states.
   
To include update setting in the database, use the command below on your database:

```   
INSERT INTO core_config_data (path, value) VALUES ('signifyd_connect/settings/restrict_states_update', 'pending_payment,payment_review,canceled,closed,complete');
```

To modify an existing setting, use the command below:
   
```
UPDATE core_config_data SET value='pending_payment,payment_review,canceled,closed,complete' WHERE path='signifyd_connect/settings/restrict_states_update';
```

To exclude a setting and use the extension defaults, just delete it from the database:

```   
DELETE FROM core_config_data WHERE path='signifyd_connect/settings/restrict_states_update';
```

## Changing default setting

**_Warning: changing the default settings is not recommended and can impact the checkout and payment workflows. Also it can cause the Signifyd integration to malfunction._**

Use the commands below on the database to work with the default setting. Replace
`pending_payment,payment_review,canceled,closed,complete` with the desired states. Default setting will be used to restrict all workflow actions (hold, remove from hold, capture and cancel) - also for restricting case creation and update if no specific settings are defined for those.

To include default setting in the database, use the command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd_connect/settings/restrict_states_default', 'pending_payment,payment_review,canceled,closed,complete');
```

To modify an existing setting, use the command below:

```
UPDATE core_config_data SET value='pending_payment,payment_review,canceled,closed,complete' WHERE path='signifyd_connect/settings/restrict_states_default';
```

To exclude a setting and use the extension defaults, just delete it from the database:

```
DELETE FROM core_config_data WHERE path='signifyd_connect/settings/restrict_states_default';
```

### Checking current restriction settings

To check all current restriction settings on the database, run the command below:

```
SELECT * FROM core_config_data WHERE path LIKE 'signifyd_connect/settings/restrict%';
```