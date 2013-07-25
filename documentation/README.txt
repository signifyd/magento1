== Grep_Signifyd ==

Integrates the Signifyd fraud detection service with Magento

When enabled, each new order will be submited as a new signifyd case, allowing merchants to review fraud scores before shipment.

See: http://signifyd.com for further information.

The following stable versions of Magento are supported:

Magento Community Edition 1.4.0.0 - 1.7.0.2
Magento Enterprise Edition 1.9.0.0 - 1.13.0.1


== Installation ==

This extension can be installed via Magento Connect, or manually via SSH.


To install via Magento connect:

1. Retrieve the extension's installation key from the Magento connect website

2. Navigate to the Magento Connect Manager on your site:
    Magento Admin -> System -> Magento Connect -> Magento Connect Manager

3. Log in

4. Take note of any warnings or error messages displayed on this page, and correct them before continuing

5. Paste the extension key into the appropriate field in the "Install New Extensions" section

6. Click the "Install" button

7. Log out of the Magento Admin panel, and log back in (to refresh permissions)

8. Confirm that the extension has been installed correctly by navigating to its settings page:
    Magento Admin -> System -> Configuration -> Signifyd -> Signifyd


To install manually:

1. Upload the extension tarball into the Magento root directory. e.g:
    $ scp grep_signifyd-1.0.0.tar.gz example.com:/path/to/magento/

2. Extract the tarball into the root directory. e.g:
    $ ssh example.com
    $ cd /path/to/magento
    $ tar -zxvf grep_signifyd-1.0.0.tar.gz

3. Navigate to the cache management page in the Magento admin panel:
    Magento Admin -> System -> Cache Management

4. Flush the system cache by clicking the "Flush cache storage" button

5. Refresh the page

6. Log out of the Magento Admin panel, and log back in (to refresh permissions)

7. Confirm that the extension has been installed correctly by navigating to its settings page:
    Magento Admin -> System -> Configuration -> Signifyd -> Signifyd



== Configuration == 

This extension can be configured at the location below:

Magento Admin -> System -> Configuration -> Signifyd -> Signifyd

Please ensure to enter a valid API URL and Key before enabling the extension. If you do not have one of these details, please contact Signifyd support.
