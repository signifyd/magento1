[Signifyd Extension for Magento 1](../README.md) > Pass payment gateway details

# Pass payment gateway details

## Overview

The functionality tries to fetch most as possible of these payment informations:

- AVS Response Code
- CVV Response Code
- Bin
- Last 4
- Expiry Month
- Expiry Year

As each payment method has it own workflow, there is no guarantee that informations will be found at same database locations. However many payment methods follow some Magento standards, and we’ve used this.

## Basic Structure

The solution fetch the desired information using helpers. There are helpers working on specific payment methods and also exists helpers to work on Payment Bridge default behavior and another helper that tries to found the informations on any other payment method.

The workflow is:
1. Use the specific helper, if exists
2. If the payment method is using Payment Bridge, use default Payment Bridge helper
3. Use the helper for other payment methods

All helpers are implemented based on ` Signifyd_Connect_Helper_Payment_Interface`, that guides which methods are expected.

There is also the helper `Signifyd_Connect_Helper_Payment_Default`, which main objective is to implement data colect for the Magento default locations for the desired informations. All other helpers developed so far extends this helper. Most of the other helpers uses this one as a fallback: if the information it is not found on the method specific location, try the default location. This helper also initializes some data on the object and has filters for data validations.

The helper for other payment methods tries first the default locations and if the information is not found, it tries to found it on additional informations of the payment method, which is the field available for the payment methods to save these kind of information on Magento database.

In addition to helpers, there is also a process that capture and save informations from payment data submitted to the store server. The extension does not submit any new data to store server, it only take advantage of the data already submitted. The data collected and saved on this process are: cardholder name, expiry month, expiry year, bin and last 4 digits. In order to be able to collect these informations, the credit card form submitted to store server must have fields named as: cc_owner, cc_exp_month, cc_exp_year, cc_number. The whole credit card number or the CVV it is not saved on database by this functionality.

## Including custom payment method

### Finding the payment method code

For the inclusion of a custom payment, it is necessary to find the payment method code.

Usually it is possible to find the payment method code inside the payment method config.xml file, inside the `<default><payment>` tag. Something like this:

```
<default>
	<payment>
		<payment_method_code>
		...
		</payment_method_code>
	</payment>
</default>
```

Another way to find out the payment method code it is on the database. Get a increment ID of any order placed with the desired payment method and use the follow script on database to get the payment method code.

**_Replace INCREMENT_ID with the order increment ID_**

```
SELECT method FROM sales_flat_order_payment WHERE parent_id IN (SELECT entity_id FROM sales_flat_order WHERE increment_id='INCREMENT_ID');
```

### Method 01: code/local pool

Create a helper inside the folder app/code/local/Signifyd/Connect/Helper/Payment naming the subfolders and PHP file according the payment method code. There must exist a subfolder for each part of the payment method code separated by a underline, and the last part must be the PHP file.

**Example 01**
- Payment method code: vendor_payment_method
- Subfolder and file paths: Vendor/Payment/Method.php
- Final file path: app/code/local/Signifyd/Connect/Helper/Payment/Vendor/Payment/Method.php
- Final class name: `Signifyd_Connect_Helper_Payment_Vendor_Payment_Method`

**Example 02**
- Payment method code: paymentmethod
- Subfolder and file paths: Paymentmethod.php
- Final file path: app/code/local/Signifyd/Connect/Helper/Payment/Paymentmethod.php
- Final class name: `Signifyd_Connect_Helper_Payment_Paymentmethod`

### Method 02: using rewrite on a custom extension

If the development is been done by the payment method extension developer or on a custom extension, use this method.

Open the config.xml file of the extension and add the follow code:

**_Replace payment\_method\_code with the word "payment\_" followed by the desired payment method code. E.g.: if the payment method code it is "ccsave" this tag should be "payment\_ccsave"_**

**_Replace `Vendor_Module_Helper_Custom_Helper` with the Helper class name inside the extension: create a helper for each payment method_**

```
<global>
	...
	<helpers>
		<signifyd_connect>
			<rewrite>
<payment_method_code>Vendor_Module_Helper_Custom_Helper</payment_method_code>
			</rewrite>
		</signifyd_connect>
	</helpers>
	...
</global>
```

### Writing the Helper

The helper should implement `Signifyd_Connect_Helper_Payment_Interface` interface. To fulfill this requirement extend the class `Signifyd_Connect_Helper_Payment_Default`, which already implements the desired interface and handle a couple of other things of the process. The class declaration should look like this:

**Using code/local pool method**

```
class Signifyd_Connect_Helper_Payment_Vendor_Payment_Method extends Signifyd_Connect_Helper_Payment_Default
{
}
```

**Using rewrite**
```
class Vendor_Module_Helper_Custom_Helper extends Signifyd_Connect_Helper_Payment_Default
{
}
```

Create methods to provide the data, like AVS/CSS response codes. The possible methods are:

```
public function getAvsResponseCode();
public function getCvvResponseCode();
public function getCardHolderName();
public function getBin();
public function getLast4();
public function getExpiryMonth();
public function getExpiryYear();
```

It is not required to implement all the methods, only the ones that will actually get data from the custom payment method. If a method it is not implemented, the default one will be used, that is, the one on parent class (Signifyd_Connect_Helper_Payment_Default).

Each implemented method should return the related data as the result. Before return the data, it is possible to validate it with some filter methods listed bellow. These same filters will also be applied later on the API integration.

```
public function filterAvsResponseCode($avsResponseCode);
public function filterCvvResponseCode($cvvResponseCode);
public function filterBin($bin);
public function filterLast4($last4);
public function filterExpiryMonth($expiryMonth);
public function filterExpiryYear($expiryYear);
```

Some relevant data are initialized on helper and the developer can use them to write his code.

```
$this->order; //Mage_Sales_Model_Order
$this->payment; //Mage_Sales_Model_Order_Payment
$this->additionalInformation; //unserialized payment method additional information
```

A final implementation of the helper class should look like this (code/local pool method example):

```
class Signifyd_Connect_Helper_Payment_Vendor_Payment_Method
	extends Signifyd_Connect_Helper_Payment_Default
{
	public function getAvsResponseCode()
	{
		// Or get form ohter database location
		// Or retrieve it from payment method API
		$avsResponseCode = $this->additionalInformation['vendoravscode'];
		return $this->filterAvsResponseCode($avsResponseCode);
	}
}
```

## Built in helpers

Here is a list of the payment methods that have a built in helper on extension and which data is been collected so far. For cardholder name if it is not found, billing first and last name will be used.

### Authorize.Net
- Code: authorizenet
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Bin
- Last4
- Expiry Month
- Expiry Year

### Stripe
- Code: cryozonic_stripe
- Third party: [https://store.cryozonic.com/stripe-payments.html](https://store.cryozonic.com/stripe-payments.html)

**Available data**
- Cardholder Name
- Last4
- Expiry Month
- Expiry Year

### PayPal Standard (Express)
- Code: paypal_express
- Magento built in

**No data is available**

### PayPal Payments Pro
- Code: paypal_direct
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Bin
- Last4
- Expiry Month
- Expiry Year

### PayPal Payflow Pro
- Code: verisign
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Bin
- Last4
- Expiry Month
- Expiry Year

### PayPal Payflow Link
- Code: payflow_link
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Last4
- Expiry Month
- Expiry Year

### PayPal Payments Advanced
- Code: payflow_advanced
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Last4
- Expiry Month
- Expiry Year

### Payment Bridge
- Code: pbridge_[payment_method]
- Magento EE built in

This payment method is available on Enterprise Edition only.

Payment methods tested with Payment Bridge are:
- Authorize.Net (code: pbridge_authorizenet)
- PayPal Payflow Pro (code: pbridge_verisign)
- Braintree (code: pbridge_braintree_basic)

**Available data**
- Last4
