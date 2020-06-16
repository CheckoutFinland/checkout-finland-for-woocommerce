<?php
/**
 * OP Payment Service WooCommerce payment Card success controller class
 */

namespace OpMerchantServices\WooCommercePaymentGateway\Controllers;

use OpMerchantServices\SDK\Exception\HmacException;
use OpMerchantServices\SDK\Exception\ValidationException;
use OpMerchantServices\WooCommercePaymentGateway\Gateway;

class CardSuccess extends AbstractController
{
    protected function checkout()
    {
        $gateway = new Gateway();
        try {
            $gateway->process_card_token();
            wc_add_notice(__('Card was added successfully', 'op-payment-service-woocommerce'), 'success');
        } catch (HmacException $e) {
            wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        } catch (ValidationException $e) {
            wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        }
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    protected function my_account()
    {
        $gateway = new Gateway();
        try {
            $gateway->process_card_token();
            wc_add_notice(__('Card was added successfully', 'op-payment-service-woocommerce'), 'success');
        } catch (HmacException $e) {
            wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        } catch (ValidationException $e) {
            wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        }
        wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
        exit;
    }

    protected function change_payment_method()
    {
        $gateway = new Gateway();
        try {
            $gateway->process_card_token();
            wc_add_notice(__('Card was added successfully', 'op-payment-service-woocommerce'), 'success');
        } catch (HmacException $e) {
            wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        } catch (ValidationException $e) {
            wc_add_notice(__('Could not add card details', 'op-payment-service-woocommerce'), 'error');
        }
        wp_safe_redirect(wc_get_account_endpoint_url('subscriptions'));
        exit;
    }
}