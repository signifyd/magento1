signifyd_connect
================

Signifyd Magento Extension Code. This repository contains all the code required for Signifyd's Magento extension.

Here is the list of current directories in this repository:

	build - Files to help auto build the magento extension
	documentation - Documentation for packaging, readme, support and tests
	testbase - Tests for all versions (community > 1.4, enterprise > 1.10)
	www - magento extension code
	
Build for Upload to Magento Connect

	./build.php --path='/home/magento/signifyd_connect/testbase/ce1.7.0.2/' --config='/home/magento/signifyd_connect/build/config.php' --copy --v1="/home/magento/signifyd_connect/testbase/ce1.4.1.0/" --v2="/home/magento/signifyd_connect/testbase/ce1.7.0.2/" --version="3.1.6"

Where

	path = base version to use while building tar file. In this case we are using 1.7.0.2
	config = config file that includes all the necessary magento settings for community and enterprise
	v1 & v2 = auto test the build on two specific versions 
	version = set the version number for our extesnion. Magento requires this when uploading to connect
	
How to Upload to Magento Connect

	Goto http://www.magentocommerce.com/magento-connect/fraud-and-chargeback-detection-prevent-fraud.html
	Click My Account at the top right (login/password to be provided - need to add to keepass)
	Click Developers
	Click Edit under the Live Version
	Click Versions
	Click Add New Version
	Add Version Number (e.g. 3.4.9)
	Select "Stable" version
	Add Release Title and Release Notes
	Select Versions
	Continue to Upload
	Upload Tar File
