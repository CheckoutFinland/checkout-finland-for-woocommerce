<?php
/**
 * OP Payment Service WooCommerce payment Callback controller class
 */

namespace OpMerchantServices\WooCommercePaymentGateway\Controllers;

use OpMerchantServices\WooCommercePaymentGateway\Gateway;

class Callback extends AbstractController
{
    protected function index()
    {
        sleep(5);
        new Gateway();
    }
}
