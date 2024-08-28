=== MMG Checkout Payment for WooCommerce ===
Contributors: kalpaservices
Tags: woocommerce, payment gateway, mmg, checkout
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers.

== Description ==

MMG Checkout Payment is a WordPress plugin that enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers. This plugin integrates seamlessly with WooCommerce to provide a secure and efficient payment gateway for your online store.

Key Features:
* Easy integration with WooCommerce
* Secure payment processing
* Support for both live and demo modes
* Customizable payment gateway settings
* Compatibility with WooCommerce Blocks
* Admin settings page for easy configuration
* Automatic generation of callback URL

== Installation ==

1. Log in to your WordPress admin panel.
2. Navigate to 'Plugins' -> 'Add New'.
3. In the search box, type "MMG Checkout Payment for WooCommerce".
4. Look for the plugin in the search results and click "Install Now".
5. After installation, click "Activate" to enable the plugin.
6. Configure the plugin settings in the WordPress admin area under 'Settings' > 'MMG Checkout'.

== Configuration ==

1. Go to 'WooCommerce' -> 'Settings' -> 'Payments' in the WordPress admin area
2. Click on 'MMG Checkout' to configure the payment method
3. Enter your MMG Merchant credentials:
   - Merchant Name (if different from site title)
   - Client ID
   - Merchant ID
   - Secret Key
   - RSA Public Key (MMG)
   - RSA Private Key (Merchant) // used to decrypt the response from MMG, do not share this key with anyone.
4. Save the settings

== Prerequisites ==

- WordPress 5.6 or later
- WooCommerce 7.0 or later
- PHP 7.4 or later
- mbstring extension enabled
- MMG Checkout API credentials (obtain from merchantservices@mmg.gy)
- RSA keys for encryption and decryption

== Frequently Asked Questions ==

= Is this plugin compatible with the latest version of WooCommerce? =

Yes, this plugin is regularly updated to maintain compatibility with the latest versions of WooCommerce.

= How do I get MMG Merchant credentials? =

Please contact MMG directly at merchantservices@mmg.gy to register as a merchant and obtain the necessary credentials.

== Changelog ==

= 1.1.15 =
* Latest version with improvements and bug fixes.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.15 =
This version includes important updates and improvements. Please upgrade to ensure compatibility and optimal performance.

== Screenshots ==

1. MMG Checkout Payment gateway settings page
![MMG Checkout Payment gateway settings page](public/images/settings-page.png)

2. MMG Checkout option on WooCommerce checkout page
![MMG Checkout option on WooCommerce checkout page](public/images/checkout-options.png)

== Support ==

For support or feature requests, please contact Kalpa Services Inc. at info@kalpa.dev, visit our website at https://kalpa.dev, or submit an issue on the plugin's GitHub repository.
