<?php
/*
Plugin Name: Checkout Finland WooCommerce Payment Gateway
Plugin URI: https://github.com/CheckoutFinland/plugin-woocommerce
Description: WooCommerce extension for supporting Checkout Finland payment methods
Version: 0.0.1
Author: Checkout Finland
Author URI: http://www.checkout.fi/
Copyright: Checkout Finland
Text Domain: woocommerce-payment-gateway-checkout-finland
*/

namespace CheckoutFinland\WooCommercePaymentGateway;

// Ensure that the file is being run within the WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * The main plugin class
 */
final class Plugin {

    /**
     * WooCommerce payment gateway ID.
     */
    public const GATEWAY_ID = 'checkout_finland';

    /**
     * Merchant ID for the test mode.
     */
    public const TEST_MERCHANT_ID = 375917;

    /**
     * Secret key for the test mode.
     */
    public const TEST_SECRET_KEY = 'SAIPPUAKAUPPIAS';

    /**
     * The URL from which the method description should be fetched.
     */
    public const METHOD_INFO_URL = 'https://cdn2.hubspot.net/hubfs/2610868/ext-media/op-psp-service-info.json';

    /**
     * The URL of the payment method icon
     */
    public const ICON_URL = 'https://cdn2.hubspot.net/hubfs/2610868/ext-media/op-psp-master-logo.svg';

    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    private static $instance;

    /**
     * Plugin version.
     *
     * @var string
     */
    protected $version;

    /**
     * Plugin directory.
     *
     * @var string
     */
    protected $plugin_dir;

    /**
     * Plugin directory URL.
     *
     * @var string
     */
    protected $plugin_dir_url;

    /**
     * Container array for possible initialization errors.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Plugin info
     *
     * @var array
     */
    protected $plugin_info = [
        'Plugin Name',
        'Plugin URI',
        'Description',
        'Version',
        'Author',
        'Author URI',
        'Text Domain',
        'Domain Path',
    ];

    /**
     * Constructor function
     */
    protected function __construct() {
        $this->plugin_dir     = __DIR__;
        $this->plugin_dir_url = plugin_dir_url( __FILE__ );
        $this->plugin_info    = array_combine( $this->plugin_info, get_file_data( __FILE__, $this->plugin_info ) );
    }

    /**
     * Singleton instance getter function
     *
     * @return Plugin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            // Construct the object.
            self::$instance = new self();

            // Run initialization checks. If any of the checks
            // fails, interrupt the execution.
            if ( ! self::$instance->initialization_checks() ) {
                return;
            }

            // Check if Composer has been initialized in this directory.
            // Otherwise we just use global composer autoloading.
            if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
                require_once __DIR__ . '/vendor/autoload.php';
            }

            // Add the gateway class to WooCommerce.
            add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
                $gateways[] = Gateway::CLASS;

                return $gateways;
            });
        }

        return self::$instance;
    }

    /**
     * Run checks for plugin requirements.
     *
     * Returns false if checks failed.
     *
     * @return bool
     */
    protected function initialization_checks() {
        $this->check_php_version();
        $this->check_woocommerce_active_status();
        $this->check_woocommerce_version();

        if ( ! empty( $this->errors ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error">';
                array_walk( $this->errors, 'esc_html_e' );
                echo '</div>';
            });

            return false;
        }
        else {
            return true;
        }
    }

    /**
     * Ensure that the PHP version is at least 7.0.0.
     *
     * @return void
     */
    protected function check_php_version() {
        if ( ! version_compare( PHP_VERSION, '7.0.0', '>=' ) ) {
            $this->errors[] = sprintf(
                // translators: The placeholder contains the current PHP version.
                esc_html__( 'Checkout Finland payment gateway plugin requires a PHP version of at least 7.0. You are currently running version %1$s.', 'woocommerce-payment-gateway-checkout-finland' ),
                esc_html( PHP_VERSION )
            );
        }
    }

    /**
     * Ensure that the WooCommerce plugin is active.
     *
     * @return void
     */
    protected function check_woocommerce_active_status() {
        if ( ! class_exists( '\WC_Payment_Gateway' ) ) {
            $this->errors[] = esc_html__( 'Checkout Finland payment gateway plugin requires WooCommerce to be activated.', 'woocommerce-payment-gateway-checkout-finland' );
        }
    }

    /**
     * Ensure that we have at least version 3.5 of the WooCommerce plugin.
     *
     * @return void
     */
    protected function check_woocommerce_version() {
        if (
            defined( 'WOO_COMMERCE_VERSION' ) &&
            version_compare( WOO_COMMERCE_VERSION, '3.5' ) === -1
        ) {
            $this->errors[] = esc_html__( 'Checkout Finland payment gateway plugin requires WooCommerce version of 3.5 or greater.', 'woocommerce-payment-gateway-checkout-finland' );
        }
    }

    /**
     * Get plugin directory.
     *
     * @return string
     */
    public function get_plugin_dir() : string {
        return $this->plugin_dir;
    }

    /**
     * Get plugin directory URL.
     *
     * @return string
     */
    public function get_plugin_dir_url() : string {
        return $this->plugin_dir_url;
    }

    /**
     * Get plugin info.
     *
     * @return array
     */
    public function get_plugin_info() : array {
        return $this->plugin_info;
    }
}

add_action( 'plugins_loaded', function() {
    Plugin::instance();
});
