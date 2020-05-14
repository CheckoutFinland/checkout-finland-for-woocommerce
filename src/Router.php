<?php
/**
 * OP Payment Service WooCommerce payment router class
 */

namespace OpMerchantServices\WooCommercePaymentGateway;

use OpMerchantServices\WooCommercePaymentGateway\Controllers\AddCardForm;
use OpMerchantServices\WooCommercePaymentGateway\Controllers\Card;
use OpMerchantServices\WooCommercePaymentGateway\Controllers\CardCancel;
use OpMerchantServices\WooCommercePaymentGateway\Controllers\CardSuccess;

class Router
{
    const ACTION_BASE_URL = 'op-payment-service-action';

    const ROUTE_BASE_URL = 'op-payment-service-route';

    /**
     * Router constructor.
     */
    public function __construct()
    {
        add_filter('init', [$this, 'register_rewrites']);
        add_filter('template_include', [$this, 'routes']);
    }

    public function register_rewrites()
    {
        // @todo: fix plain structure
        $base_url = Plugin::BASE_URL;
        $action_base_url = self::ACTION_BASE_URL;
        $route_base_url = self::ROUTE_BASE_URL;
        add_rewrite_rule("op-payment-service/([^/]*)/([^/]*)/?$", 'index.php?' . $route_base_url . '=$matches[1]&' . $action_base_url . '=$matches[2]', 'top');
        add_rewrite_tag("%$route_base_url%", '([^&]+)');
        add_rewrite_tag("%$action_base_url%", '([^&]+)');
    }

    /**
     * @param $template
     * @return mixed
     */
    public function routes($template)
    {
        $route = get_query_var(self::ROUTE_BASE_URL);

        if (!$route) {
            return $template;
        }
        $action = get_query_var(self::ACTION_BASE_URL) ?? 'index';
        switch ($route) {
            case Plugin::ADD_CARD_FORM_URL:
                $controller = new AddCardForm();
                break;
            case Plugin::ADD_CARD_REDIRECT_SUCCESS_URL:
                $controller = new CardSuccess();
                break;
            case Plugin::ADD_CARD_REDIRECT_CANCEL_URL:
                $controller = new CardCancel();
                break;
            case Plugin::CARD_ENDPOINT:
                $controller = new Card();
                break;
            default:
                echo 'Route did not match';
        }

        $controller->execute($action);

        return $template;
    }
}