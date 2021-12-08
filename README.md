# Checkout Finland for WooCommerce

## ⚠️ DEPRECATED

**This plugin has been deprecated**.  All of its functionality now exists as the rebranded 
[`paytrail/paytrail-for-woocommerce`](https://github.com/paytrail/paytrail-for-woocommerce) plugin.<br/> 
It is advised to upgrade to the new module.

<hr/>

**Required WordPress version:** 4.9

**Required PHP version:** 7.3

**[Checkout Finland](https://www.checkout.fi)** is a payment gateway that offers 20+ payment methods for Finnish customers. NOTICE: This plugin is no longer maintained. Use Paytrail for Woocommerce instead.

The payment gateway provides all the popular payment methods with one simple integration. The provided payment methods include but are not limited to credit cards, online banking and mobile payments.

To use this extension, you need to sign up for a Checkout Finland account. Transaction fees will be charged for every transaction. Transaction cost may vary from merchant to merchant, based on what is agreed upon with Checkout Finland when negotiating your contract. For more information and to register, please visit [our website](https://www.checkout.fi)  (in Finnish only) or contact [asiakaspalvelu@checkout.fi](mailto:asiakaspalvelu@checkout.fi) directly.

We employ the industry's best security practices and tools to maintain bank-level security for merchants and end customers. Checkout Finland is PCI DSS Level I and GDPR compliant. 

Upon checkout, customers are redirected to the Checkout Finland payment service. The customer enters his or her payment information directly into our secure environment so that the web shop never comes into contact with the customers payment data. Once the payment process is complete, customers will redirected back to your store. Tokenization is used to run transactions with stored payment information. No confidential card data is ever stored on your server.

## Installation

### From WordPress plugin directory

Open WordPress Admin panel and go to Plugins -> Add New. Search for [Checkout Finland for WooCommerce](https://wordpress.org/plugins/op-payment-service-for-woocommerce/), click install and then activate. 

After installation go to WooCommerce -> Settings -> Payments and select "Manage" next to Checkout Finland for WooCommerce to review settings.

### Via Composer

1. If you have Composer installed:
- You can use the command line to install the plugin:

```
$ composer require checkout-finland/checkout-finland-for-woocommerce
```
- Or you can add the following json to your `composer.json` file:

```json
{
  "require": {
    "checkout-finland/checkout-finland-for-woocommerce": "*"
  }
}
```
2. Activate the plugin.
3. Go to WooCommerce Settings and open Payments tab.
4. Enable Checkout Finland for WooCommerce with the toggle switch.
5. Configure your own Checkout Finland payment service settings.

## Configuration

There are several settings you can configure via the options page.

### Test mode

If test mode is enabled, your store will automatically use Checkout Finland's test credentials.

### Debug log

Enable logging all payment gateway events. Recommended only for debugging purposes as this might save personal data.

### Payment provider selection

Choose whether you want the payment provider selection to happen in the checkout page or in a separate page.

### Merchant ID and Secret key

Your Checkout Finland credentials. You can obtain the keys by registering at our [website](https://www.checkout.fi) (in Finnish only) or contact [asiakaspalvelu@checkout.fi](mailto:asiakaspalvelu@checkout.fi) directly.

### Fallback Country

Setup fallback country to be used if no country is provided from Woocommerce checkout. Use only if billing_country/shipping_country is removed from checkout fields.

## Router

### Callback Routes

Permalinks enabled:

    Success: /op-payment-service/callback/index
    Cancel: /op-payment-service/callback/index

Permalinks disabled:

    Success: /index.php?op-payment-service-route=callback&op-payment-service-action=index
    Cancel: /index.php?op-payment-service-route=callback&op-payment-service-action=index

## Credits

**Contributors:** [Nomafin](https://github.com/nomafin), [villesiltala](https://github.com/villesiltala), [tommireinikainen](https://github.com/tommireinikainen), [onnimonni](https://github.com/onnimonni), [traone](https://github.com/traone), [villepietarinen](https://github.com/villepietarinen) & [loueranta](https://github.com/loueranta)
