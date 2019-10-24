# OP Payment Service for WooCommerce

**Contributors:** [Nomafin](https://github.com/nomafin), [villesiltala](https://github.com/villesiltala), [tommireinikainen](https://github.com/tommireinikainen), [onnimonni](https://github.com/onnimonni), [traone](https://github.com/traone) & [loueranta](https://github.com/loueranta)

**Required WordPress version:** 4.9

**Required PHP version:** 7.1

A WooCommerce extension to add support for OP Payment Service (formerly Checkout Finland) payment methods. 

## Installation

### From WordPress plugin directory

Open WordPress Admin panel and go to Plugins -> Add New. Search for [OP Payment Service for WooCommerce](https://wordpress.org/plugins/op-payment-service-for-woocommerce/), click install and then activate. 

After installation go to WooCommerce -> Settings -> Payments and select "Manage" next to OP Payment Service for WooCommerce to review settings.

### Manually

Go to the [Releases](https://github.com/OPMerchantServices/op-payment-service-for-woocommerce/releases) page and download 
the ready to install version numbered zip-file from the Assets-section. 

### Via Composer

1. If you have Composer installed:
    - You can use the command line to install the plugin

```
$ composer require op-merchant-services/op-payment-service-for-woocommerce
```
    - Or you can add the following json to your `composer.json` file:

```json
{
  "require": {
    "op-merchant-services/op-payment-service-for-woocommerce": "*"
  }
}
```
2. Activate the plugin.
3. Go to WooCommerce Settings and open Payments tab.
4. Enable OP Payment Service for WooCommerce with the toggle switch.
5. Configure your own OP Payment Service settings.

## Configuration

There are several settings you can configure via the payment gateway options page.

### Test mode

If test mode is enabled, your store will automatically use OP Payment Service's test credentials.

### Title and description

These texts will be shown on the front-end to the end-user.

### Merchant ID and Secret key

Your OP Payment Service credentials.
