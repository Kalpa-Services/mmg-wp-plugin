# MMG Checkout Payment Plugin

## Description

MMG Checkout Payment is a WordPress plugin that enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers. This plugin integrates seamlessly with WooCommerce to provide a secure and efficient payment gateway for your online store.


## Prerequisites

- WordPress 5.6 or later
- WooCommerce 7.0 or later
- PHP 7.4 or later
- mbstring extension enabled
- MMG Checkout API credentials (obtain from merchantservices@mmg.gy)
- RSA keys for encryption and decryption

## Features

- Easy integration with WooCommerce
- Secure payment processing
- Admin settings page for easy configuration
- Automatic generation of callback url

## Installation

1. Download the latest release of the plugin from the [GitHub Releases page](https://github.com/Kalpa-Services/mmg-wp-plugin/releases).
2. Upload the `mmg-checkout-payment` folder to the `/wp-content/plugins/` directory on your WordPress site.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Configure the plugin settings in the WordPress admin area under 'Settings' > 'MMG Checkout'.

## Configuration

1. Go to 'Settings' > 'MMG Checkout' in the WordPress admin area
2. Enter your MMG Merchant credentials:
   - Merchant Name (if different from site title)
   - Client ID
   - Merchant ID
   - Secret Key
   - RSA Public Key (MMG)
   - RSA Private Key (Merchant) // used to decrypt the response from MMG, do not share this key with anyone.
3. Save the settings

### WooCommerce Integration

The plugin automatically adds MMG Checkout as a payment method in WooCommerce. Customers can select it during the checkout process.

## Support

For support or feature requests, please contact the plugin author or submit an issue on the plugin's GitHub repository.

## License

This plugin is released under the GPL v2 or later license.

## Author

Kalpa Services Inc.

## Contributors

- Jay Carter https://www.jaycarter.gy/

# Roadmap

- Shortcode support for flexible placement of the checkout button
