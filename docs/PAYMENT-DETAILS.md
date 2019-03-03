[Signifyd Extension for Magento 1](../README.md) > Pass payment gateway details

# Pass payment gateway details

## Overview

The extension will try to fetch the following payment data:

- AVS Response Code
- CVV Response Code
- Bin
- Last 4
- Expiry Month
- Expiry Year

As each payment gateway has it own workflow, there is no guarantee that the extension will find the payment data. To support a variety of payment gateways and extensibliity helpers can be used to pass payment data from any payment gateway.  

## Basic Structure

There are pre-built helpers for specific payment gateways (authorize.net, braintree, stripe, and paypal payflow), as well as, helpers for Payment Bridge, and a generic helper that tries to find payment details for any other payment gateway.

You should follow these guidelines
1. Use the specific helper, if it exists
2. If the payment method is using Payment Bridge, use the default Payment Bridge helper
3. Use the helper for other payment methods

All helpers are implemented based on ` Signifyd_Connect_Helper_Payment_Interface`, that guides which methods are expected.

There is also the helper `Signifyd_Connect_Helper_Payment_Default`, whose purpose is to implement data collection from the Magento default locations. Most of the other helpers use this one as a fallback: if the information is not found on the payment method-specific location, then it will try the default location. This helper also initializes some data on the object and has filters for data validation.

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

Another way to find the payment method code is on the database. Get an increment ID of any order placed with the desired payment method and use the following script on the database to get the payment method code.

**_Replace INCREMENT_ID with the order increment ID_**

```
SELECT method FROM sales_flat_order_payment WHERE parent_id IN (SELECT entity_id FROM sales_flat_order WHERE increment_id='INCREMENT_ID');
```

### Method 01: code/local pool

Create a helper inside the folder app/code/local/Signifyd/Connect/Helper/Payment naming the subfolders and PHP file according to the payment method code. There must be a subfolder for each part of the payment method code separated by an underline, and the last part must be the PHP file.

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

If the development is being done by the payment method extension developer or on a custom extension, use this method.

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

The helper should implement `Signifyd_Connect_Helper_Payment_Interface` interface. To fulfill this requirement extend the class `Signifyd_Connect_Helper_Payment_Default`, which already implements the desired interface and handles a couple of other things of the process. The class declaration should look like this:

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

It is not required to implement all the methods, only the ones that will actually get data from the custom payment method. If a method is not implemented, the default one will be used, that is, the one on the parent class. (Signifyd_Connect_Helper_Payment_Default).

Each implemented method should return the related data as the result. Before returning the data, it is possible to validate it with some filter methods listed bellow. These same filters will also be applied later when the API call to create a case is made to the Signifyd API.

```
public function filterAvsResponseCode($avsResponseCode);
public function filterCvvResponseCode($cvvResponseCode);
public function filterBin($bin);
public function filterLast4($last4);
public function filterExpiryMonth($expiryMonth);
public function filterExpiryYear($expiryYear);
```

Some relevant data is initialized on the helper and the developer can use it to write their code.

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

Here is a list of the payment methods that have a built in helper on the extension and will have payment data collected. If the cardholder name is not found, the billing first and last name will be used.

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
