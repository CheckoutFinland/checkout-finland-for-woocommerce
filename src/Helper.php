<?php
/**
 * OP Payment Service WooCommerce payment Helper class
 */

namespace OpMerchantServices\WooCommercePaymentGateway;

use Exception;
use LogicException;

class Helper
{
    /**
     * @return bool
     */
    public static function getIsSubscriptionsEnabled()
    {
        if (!class_exists('WC_Subscriptions_Cart')) {
            return false;
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            return false;
        }
        if (!function_exists('wcs_cart_contains_renewal')) {
            return false;
        }
        return (
            \WC_Subscriptions_Cart::cart_contains_subscription() ||
            wcs_cart_contains_renewal() ||
            filter_input(INPUT_GET, 'change_payment_method')
        );
    }

    /**
     * @return mixed
     */
    public static function getIsChangeSubscriptionPaymentMethod()
    {
        return filter_input(INPUT_GET, 'change_payment_method');
    }

    /**
     * Currency specific formattings
     *
     * @param int|double $sum The sum to format.
     * @return integer
     */
    public function handle_currency($sum): int
    {
        $currency = \get_woocommerce_currency();

        switch ($currency) {
            default:
                $sum = round($sum * 100);
                break;
        }

        return $sum;
    }

    /**
     * Get current WooCommerce cart total.
     *
     * @return integer
     */
    public function get_cart_total(): int
    {
        $sum = WC()->cart->total;

        return $this->handle_currency($sum);
    }

    public static function getLocale(): string
    {
        $full_locale = get_locale();

        $short_locale = substr( $full_locale, 0, 2 );

        // Get and assign the WordPress locale
        switch ( $short_locale ) {
            case 'sv':
                $locale = 'SV';
                break;
            case 'fi':
                $locale = 'FI';
                break;
            default:
                $locale = 'EN';
                break;
        }
        return $locale;
    }

}
