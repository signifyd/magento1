[Signifyd Extension for Magento 1](../README.md) > Restrict orders by states

# Restrict orders by states

Orders with a specific state can be excluded from being sent to Signifyd. E.g. by default, the extension restricts any action on payment_review state, to ensure the extension does not interfere with the payment workflow.

**_Warning: the wrong settings can interfere with the checkout and payment workflows. If you need to modify the restricted states it's recommended you first test it on your development environment. The default restricted states have already been tested with Magento's default payment methods._**

There are three different settings for states restrictions: default, create and update. By default, these settings are as below:
- default: `pending_payment,payment_review,canceled,closed,complete`
- create: `holded,pending_payment,payment_review,canceled,closed,complete`
- update: `[empty]`

The default settings will be used on all actions that do not have a setting of their own. The create setting will be used for sending an order to Signifyd and the update setting will be used to restrict states for case update.

Besides case creation and update, the default setting is also used for order workflow actions: hold, remove from hold, cancel and capture payment.

## Things to know before getting started
Be aware that these settings use Magento states (not status), which must be one of these: `new, pending_payment, payment_review, processing, complete, closed, canceled, holded`. States should be provided as a comma separated list of one or more values. You will also need to clear the configuration or full cache for the change to take effect. 

## Changing create states

Use the command below on the database to work with the create setting. Replace `holded,pending_payment,payment_review,canceled,closed,complete` with the desired states. 

### Add custom states

To include custom create states use the command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd_connect/settings/restrict_states_create', 'holded,pending_payment,payment_review,canceled,closed,complete');
```
### Update custom states

To modify an existing custom state, use the command below:

```
UPDATE core_config_data SET value='holded,pending_payment,payment_review,canceled,closed,complete' WHERE path='signifyd_connect/settings/restrict_states_create';
```
### Delete custom states

To use the extension default states, use the command below:

```
DELETE FROM core_config_data WHERE path='signifyd_connect/settings/restrict_states_create';
```

## Changing update setting
Use the command below on database to work with the update setting. Replace `pending_payment,payment_review,canceled,closed,complete` with the desired states. 

### Add custom states

To add custom states for updates, use the command below on your database:

```   
INSERT INTO core_config_data (path, value) VALUES ('signifyd_connect/settings/restrict_states_update', 'pending_payment,payment_review,canceled,closed,complete');
```
### Update custom states

To modify an existing custom state, use the command below:
   
```
UPDATE core_config_data SET value='pending_payment,payment_review,canceled,closed,complete' WHERE path='signifyd_connect/settings/restrict_states_update';
```
### Delete custom states

To use the extension defaults, use the command below:

```   
DELETE FROM core_config_data WHERE path='signifyd_connect/settings/restrict_states_update';
```

## Changing default setting

**_Warning: changing the default settings is not recommended as it can impact the checkout and payment workflows._**

Use the commands below on the database to work with the default setting. Replace
`pending_payment,payment_review,canceled,closed,complete` with the desired states. The default setting will be used to restrict all workflow actions (hold, remove from hold, capture and cancel) - also for restricting case creation and update if no specific settings are defined for those.

### Add custom states
To add custom default states, use the command below on your database:

```
INSERT INTO core_config_data (path, value) VALUES ('signifyd_connect/settings/restrict_states_default', 'pending_payment,payment_review,canceled,closed,complete');
```

### Update custom states
To modify an existing setting, use the command below:

```
UPDATE core_config_data SET value='pending_payment,payment_review,canceled,closed,complete' WHERE path='signifyd_connect/settings/restrict_states_default';
```
### Delete custom states

To use the extension defaults, use the command below:

```
DELETE FROM core_config_data WHERE path='signifyd_connect/settings/restrict_states_default';
```

### Checking current restriction settings

To check the current custom state settings, run the command below:

```
SELECT * FROM core_config_data WHERE path LIKE 'signifyd_connect/settings/restrict%';
```
