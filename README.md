# MMG Checkout Payment Plugin

## Description

MMG Checkout Payment is a WordPress plugin that enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers. This plugin integrates seamlessly with WooCommerce to provide a secure and efficient payment gateway for your online store.

## Features

- Easy integration with WooCommerce
- Secure payment processing
- Customizable checkout button
- Admin settings page for easy configuration
- Shortcode support for flexible placement of the checkout button

## Installation

1. Upload the `mmg-checkout-payment` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings in the WordPress admin area under 'Settings' > 'MMG Checkout'

## Configuration

1. Go to 'Settings' > 'MMG Checkout' in the WordPress admin area
2. Enter your MMG Merchant credentials:
   - Base URL
   - Client ID
   - Merchant ID
   - Secret Key
   - RSA Public Key
3. Save the settings

## Usage

### Shortcode

You can use the following shortcode to add the MMG Checkout button to any post or page:

```
[mmg_checkout_button amount="100" description="Product Description"]
```

Replace `100` with the desired amount and `"Product Description"` with an appropriate description for the transaction.

### WooCommerce Integration

The plugin automatically adds MMG Checkout as a payment method in WooCommerce. Customers can select it during the checkout process.

## Support

For support or feature requests, please contact the plugin author or submit an issue on the plugin's GitHub repository.

## License

This plugin is released under the GPL v2 or later license.

## Author

Kalpa Services Inc.

## Version

1.0
