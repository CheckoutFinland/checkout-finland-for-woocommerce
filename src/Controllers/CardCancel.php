<?php
/**
 * OP Payment Service WooCommerce payment Card cancel controller class
 */

namespace OpMerchantServices\WooCommercePaymentGateway\Controllers;

class CardCancel extends AbstractController
{
    protected function checkout()
    {
        wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    protected function my_account()
    {
        wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
        exit;
    }
}