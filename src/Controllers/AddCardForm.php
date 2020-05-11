<?php
/**
 * OP Payment Service WooCommerce payment Add card form controller class
 */

namespace OpMerchantServices\WooCommercePaymentGateway\Controllers;

use OpMerchantServices\SDK\Exception\HmacException;
use OpMerchantServices\SDK\Exception\ValidationException;
use OpMerchantServices\WooCommercePaymentGateway\Gateway;

class AddCardForm extends AbstractController
{
    protected function index()
    {
        $gateway = new Gateway();
        try {
            $gateway->add_card_form();
        } catch (HmacException $e) {
        } catch (ValidationException $e) {
        }
    }
}