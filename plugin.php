<?php
/**
 * Plugin Name: OP Payment Service for WooCommerce
 * Plugin URI: https://github.com/OPMerchantServices/op-payment-service-for-woocommerce
 * Description: OP Payment Service (previously known as Checkout Finland) is a payment gateway that offers 20+ payment methods for Finnish customers.
 * Version: 1.2
 * Requires at least: 4.9
 * Requires PHP: 7.1
 * Author: OP Merchant Services
 * Author URI: https://www.op-kauppiaspalvelut.fi
 * Text Domain: op-payment-service-for-woocommerce
 * Domain Path: /languages
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Copyright: OP Merchant Services
 */

namespace OpMerchantServices\WooCommercePaymentGateway;

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
    public static $version;

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

        self::$version = $this->plugin_info['Version'];

        // Load the plugin textdomain.
        load_plugin_textdomain( 'op-payment-service-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
        $errors = [];

        $errors[] = self::check_php_version();
        $errors[] = self::check_woocommerce_active_status();
        $errors[] = self::check_woocommerce_version();

        $errors = array_filter( $errors );

        if ( ! empty( $errors ) ) {
            add_action( 'admin_notices', function() use ( $errors ) {
                echo '<div class="notice notice-error">';
                array_walk( $errors, 'esc_html_e' );
                echo '</div>';
            });

            return false;
        }
        else {
            return true;
        }
    }

    /**
     * Checks to run on plugin activation
     *
     * @return void
     */
    public static function activation_check() {
        $checks = [
            'check_php_version',
            'check_woocommerce_active_status',
            'check_woocommerce_version',
        ];

        array_walk( $checks, function( $check ) {
            $error = call_user_func( __CLASS__ . '::' . $check );

            if ( $error ) {
                wp_die( esc_html( $error ) );
            }
        });
    }

    /**
     * Ensure that the PHP version is at least 7.0.0.
     *
     * @return string|null
     */
    public static function check_php_version() : ?string {
        if ( ! version_compare( PHP_VERSION, '7.1.0', '>=' ) ) {
            return sprintf(
                // translators: The placeholder contains the current PHP version.
                esc_html__( 'OP Payment Service gateway plugin requires a PHP version of at least 7.1. You are currently running version %1$s.', 'op-payment-service-woocommerce' ),
                esc_html( PHP_VERSION )
            );
        }

        return null;
    }

    /**
     * Ensure that the WooCommerce plugin is active.
     *
     * @return string|null
     */
    public static function check_woocommerce_active_status() : ?string {
        if ( ! class_exists( '\WC_Payment_Gateway' ) ) {
            return esc_html__( 'OP Payment Service gateway plugin requires WooCommerce to be activated.', 'op-payment-service-woocommerce' );
        }

        return null;
    }

    /**
     * Ensure that we have at least version 3.5 of the WooCommerce plugin.
     *
     * @return string|null
     */
    public static function check_woocommerce_version() : ?string {
        if (
            defined( 'WOO_COMMERCE_VERSION' ) &&
            version_compare( WOO_COMMERCE_VERSION, '3.5' ) === -1
        ) {
            return esc_html__( 'OP Payment Service gateway plugin requires WooCommerce version of 3.5 or greater.', 'op-payment-service-woocommerce' );
        }

        return null;
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


register_activation_hook( __FILE__, __NAMESPACE__ . '\\Plugin::activation_check' );
