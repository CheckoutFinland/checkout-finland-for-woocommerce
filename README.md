# Checkout Finland WooCommerce Payment Gateway Plugin

**Contributors:** [Nomafin](https://github.com/nomafin) and [villesiltala](https://github.com/villesiltala)

**Required WordPress version:** 4.9

**Required PHP version:** 7.1

A WooCommerce extension to add support for Checkout Finland payment methods.

## Installation

### Manually

1. Download the plugin zip file.
    a. Either extract the plugin to your `wp-content/plugins` directory.
    b. Or add the zip file via Admin's `Plugins > Add New` feature.
2. Activate the plugin.
3. Go to WooCommerce Settings and open Payments tab.
4. Enable Checkout Finland with the switch.
5. Configure your own Checkout Finland settings.

### Via Composer

1. If you have Composer installed:
    a. You can use the command line:
```$ composer require checkoutfinland/woocommerce-gateway-checkout-finland```
    b. Or add it in your `composer.json`:
```json
{
  "require": {
    "checkoutfinland/woocommerce-gateway-checkout-finland": "*"
  }
}
```
2. Activate the plugin.
3. Go to WooCommerce Settings and open Payments tab.
4. Enable Checkout Finland with the switch.
5. Configure your own Checkout Finland settings.

## Configuration

There are several settings you can configure via the payment gateway options page.

### Test mode

If test mode is enabled, your store will automatically use Checkout Finland's test credentials.

### Title and description

These texts will be shown on the front-end to the end-user.

### Merchant ID and Secret key

Your Checkout Finland credentials.