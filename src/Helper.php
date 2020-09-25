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

    /**
     * @param string|int $orderId
     * @return bool
     */
    public function waitLockForProcessing($orderId): bool
    {
        return $this->handleLock($orderId, 'lock');
    }

    /**
     * @param string|int $orderId
     */
    public function removeOrderLockFile($orderId): void
    {
        $this->handleLock($orderId, 'unlock');
    }

    /**
     * @param string $orderId
     * @param string $status
     * @return bool
     */
    protected function handleLock(string $orderId, $status = 'lock'): bool
    {
        try {
            $fileName = wp_upload_dir()['basedir'] . '/order-' . $orderId . '.lock';
            set_error_handler(function ($type, $msg) use (&$error) {
                $error = $msg;
            });
            if (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                if ($handle = fopen($fileName, 'x')) {
                    chmod($fileName, 0666);
                } elseif (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                    usleep(100); // Give some time for chmod() to complete
                    $handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r');
                }
            }
            restore_error_handler();
            if (!$handle) {
                return false;
            }
            if ('unlock' === $status) {
                $flock = flock($handle, LOCK_UN);
            } else {
                $flock = flock($handle, LOCK_EX);
            }
            if (!$flock) {
                fclose($handle);
                return false;
            }
            if ('unlock' === $status) {
                unlink($fileName);
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
}
