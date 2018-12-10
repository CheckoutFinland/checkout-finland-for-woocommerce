<?php
/**
 * Checkout Finland WooCommerce payment gateway class
 */

namespace CheckoutFinland\WooCommercePaymentGateway;

/**
 * The gateway class
 */
final class Gateway extends WC_Payment_Gateway {

    /**
     * Checkout Finland merchant ID.
     *
     * @var int
     */
    protected $merchant_id;

    /**
     * Checkout Finland secret key.
     *
     * @var string
     */
    protected $secret_key;

    /**
     * Whether test mode is enabled.
     *
     * @var boolean
     */
    public $testmode = false;

    /**
     * Whether the debug mode is enabled.
     *
     * @var boolean
     */
    public $debug = false;

    /**
     * WooCommerce logger instance
     *
     * @var WC_Logger
     */
    protected $logger = null;

    /**
     * Object constructor
     */
    public function __construct() {
        $this->id = Plugin::GATEWAY_ID;

        // TODO: Set property 'icon'
        $this->has_fields = true;

        // These strings will show in the backend.
        $this->method_title       = 'Checkout Finland - TODO METHOD TITLE';
        $this->method_description = 'TODO METHOD DESCRIPTION';

        // Set gateway admin settings fields.
        $this->form_fields = $this->get_form_fields();

        // Initialize gateway settings.
        $this->init_settings();

        // Get options
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        // Whether we are in test mode or not.
        $this->testmode = 'yes' === $this->get_option( 'testmode', 'no' );

        // Set merchant ID and secret key either from the options or for test mode.
        if ( $this->testmode ) {
            $this->merchant_id = Plugin::TEST_MERCHANT_ID;
            $this->secret_key  = Plugin::TEST_SECRET_KEY;
        }
        else {
            $this->merchant_id = $this->get_option( 'merchant_id' );
            $this->secret_key  = $this->get_option( 'secret_key' );
        }

        // Whether we are in debug mode or not.
        $this->debug = 'yes' === $this->get_option( 'debug', 'no' );

        // Add actions and filters.
        $this->add_actions();
    }

    /**
     * Add all actions and filters.
     *
     * @return void
     */
    protected function add_actions() {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    /**
     * Returns admin form fields.
     *
     * @return array
     */
    protected function get_form_fields() : array {
        return [
            // Whether the payment gateway is enabled.
            'enabled'     => [
                'title'   => __( 'Enable/Disable', 'woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Checkout Finland', 'woocommerce-payment-gateway-checkout-finland' ),
                'default' => 'yes',
            ],
            // Whether test mode is enabled
            'testmode'    => [
                'title'   => __( 'Enable/Disable', 'woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable test mode', 'woocommerce-payment-gateway-checkout-finland' ),
                'default' => 'no',
            ],
            // Whether debug mode is enabled
            'debug'       => [
                'title'       => __( 'Debug log', 'woocommerce' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'woocommerce' ),
                'default'     => 'no',
                // translators: %s: URL
                'description' => sprintf( __( 'This enables logging all payment gateway events. The log will be written in %s. Recommended only for debugging purposes as this might save personal data.', 'woocommerce-payment-gateway-checkout-finland' ), '<code>' . WC_Log_Handler_File::get_log_file_path( Plugin::GATEWAY_ID ) . '</code>' ),
            ],
            // Settings for front-end
            'title'       => [
                'title'   => __( 'Title', 'woocommerce-payment-gateway-checkout-finland' ),
                'type'    => 'text',
                'label'   => __( 'Title', 'woocommerce-payment-gateway-checkout-finland' ),
                'default' => 'Checkout Finland',
            ],
            'description' => [
                'description' => __( 'Description', 'woocommerce-payment-gateway-checkout-finland' ),
                'type'        => 'text',
                'label'       => __( 'Description', 'woocommerce-payment-gateway-checkout-finland' ),
                'default'     => '',
            ],
            // Checkout Finland credentials
            'merchant_id' => [
                'title'   => __( 'Merchant ID', 'woocommerce-payment-gateway-checkout-finland' ),
                'type'    => 'text',
                'label'   => __( 'Merchant ID', 'woocommerce-payment-gateway-checkout-finland' ),
                'default' => '',
            ],
            'secret_key'  => [
                'title'   => __( 'Secret key', 'woocommerce-payment-gateway-checkout-finland' ),
                'type'    => 'password',
                'label'   => __( 'Secret key', 'woocommerce-payment-gateway-checkout-finland' ),
                'default' => '',
            ],
        ];
    }

    /**
	 * Save admin options.
	 *
	 * @return boolean
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		// Clear logs if debugging was disabled.
		if ( 'yes' !== $this->get_option( 'debug', 'no' ) ) {
			if ( empty( $this->logger ) ) {
				$this->logger = wc_get_logger();
            }

			$this->logger->clear( Plugin::GATEWAY_ID );
		}

		return $saved;
	}

    /**
	 * Insert new message to the log.
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level. Defaults to 'info'. Possible values:
	 *                        emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public function log( $message, $level = 'info' ) {
		if ( $this->debug ) {
			if ( empty( $this->logger ) ) {
				$this->logger = \wc_get_logger();
            }

			$this->logger->log( $level, $message, [ 'source' => Plugin::GATEWAY_ID ] );
		}
	}
}
