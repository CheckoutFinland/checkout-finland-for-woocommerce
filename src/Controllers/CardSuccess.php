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
        } catch (HmacException $e) {
        } catch (ValidationException $e) {
        }
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    protected function my_account()
    {
        $gateway = new Gateway();
        try {
            $gateway->process_card_token();
        } catch (HmacException $e) {
        } catch (ValidationException $e) {
        }
        wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
        exit;
    }
}