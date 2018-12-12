<?php
/**
 * Checkout Finland WooCommerce payment gateway class
 */

namespace CheckoutFinland\WooCommercePaymentGateway;

/**
 * The gateway class
 */
final class Gateway extends \WC_Payment_Gateway {

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
        $this->set_form_fields();

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
     * @return void
     */
    protected function set_form_fields() {
        $this->form_fields = [
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
                'description' => sprintf( __( 'This enables logging all payment gateway events. The log will be written in %s. Recommended only for debugging purposes as this might save personal data.', 'woocommerce-payment-gateway-checkout-finland' ), '<code>' . \WC_Log_Handler_File::get_log_file_path( Plugin::GATEWAY_ID ) . '</code>' ),
            ],
            // Settings for front-end
            'title'       => [
                'title'   => __( 'Title', 'woocommerce-payment-gateway-checkout-finland' ),
                'type'    => 'text',
                'label'   => __( 'Title', 'woocommerce-payment-gateway-checkout-finland' ),
                'default' => 'Checkout Finland',
            ],
            'description' => [
                'title'   => __( 'Description', 'woocommerce-payment-gateway-checkout-finland' ),
                'type'    => 'text',
                'label'   => __( 'Description', 'woocommerce-payment-gateway-checkout-finland' ),
                'default' => '',
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

    /**
     * Show the payment method form in the checkout.
     *
     * @return void
     */
    public function payment_fields() {
        if ( is_checkout() ) {
            $this->provider_form();
        }
    }

    /**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) : array {
        $order = wc_get_order( $order_id );

        var_dump( $order );
        die;

        return [];
    }

    /**
     * Renders the provider selection form
     *
     * @return void
     */
    public function provider_form() {
        $plugin_instance = Plugin::instance();

        $plugin_dir_url = $plugin_instance->get_plugin_dir_url();

        $plugin_version = $plugin_instance->get_plugin_info()['Version'];

        wp_register_style( 'woocommerce-gateway-checkout-finland-payment-fields', $plugin_dir_url . 'assets/dist/main.css', [], $plugin_version );

        $provider_form_view = new View( 'ProviderForm' );

        $provider_form_view->render( json_decode( '{
            "transactionId": "fd605b6a-fd3f-11e8-b642-679d6dca4ac7",
            "href": "https://pay.checkout.fi/pay/fd605b6a-fd3f-11e8-b642-679d6dca4ac7",
            "providers": [
                {
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/masterpass/redirect",
                    "icon": "https://payment.checkout.fi/static/img/masterpass_arrow_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/masterpass.svg",
                    "name": "Masterpass",
                    "group": "mobile",
                    "id": "masterpass",
                    "parameters": [
                        {
                            "name": "sph-account",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-merchant",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-api-version",
                            "value": "20170627"
                        },
                        {
                            "name": "sph-timestamp",
                            "value": "2018-12-11T12:26:31Z"
                        },
                        {
                            "name": "sph-request-id",
                            "value": "eb10e7aa-cccf-4311-8168-b4ba03a97c03"
                        },
                        {
                            "name": "sph-amount",
                            "value": "1525"
                        },
                        {
                            "name": "sph-currency",
                            "value": "EUR"
                        },
                        {
                            "name": "sph-order",
                            "value": "968472563"
                        },
                        {
                            "name": "language",
                            "value": "FI"
                        },
                        {
                            "name": "description",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "sph-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/masterpass/success"
                        },
                        {
                            "name": "sph-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/masterpass/cancel"
                        },
                        {
                            "name": "sph-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/masterpass/cancel"
                        },
                        {
                            "name": "sph-webhook-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/masterpass/success"
                        },
                        {
                            "name": "sph-webhook-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/masterpass/cancel"
                        },
                        {
                            "name": "sph-webhook-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/masterpass/cancel"
                        },
                        {
                            "name": "sph-webhook-delay",
                            "value": "60"
                        },
                        {
                            "name": "sph-sub-merchant-id",
                            "value": "375917"
                        },
                        {
                            "name": "signature",
                            "value": "SPH1 checkout_1 a129c42d478bde794db1cb990b3a42a2578882fe162459481202309dc66bd682"
                        }
                    ]
                },
                {
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mobilepay/redirect",
                    "icon": "https://payment.checkout.fi/static/img/mobilepay_140x75_new.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/mobilepay.svg",
                    "name": "MobilePay",
                    "group": "mobile",
                    "id": "mobilepay",
                    "parameters": [
                        {
                            "name": "sph-account",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-merchant",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-api-version",
                            "value": "20170627"
                        },
                        {
                            "name": "sph-timestamp",
                            "value": "2018-12-11T12:26:31Z"
                        },
                        {
                            "name": "sph-request-id",
                            "value": "3589be95-e7d8-4d03-9bd2-30c5b1af2689"
                        },
                        {
                            "name": "sph-amount",
                            "value": "1525"
                        },
                        {
                            "name": "sph-currency",
                            "value": "EUR"
                        },
                        {
                            "name": "sph-order",
                            "value": "968472563"
                        },
                        {
                            "name": "language",
                            "value": "FI"
                        },
                        {
                            "name": "description",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "sph-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mobilepay/success"
                        },
                        {
                            "name": "sph-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mobilepay/cancel"
                        },
                        {
                            "name": "sph-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mobilepay/cancel"
                        },
                        {
                            "name": "sph-webhook-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mobilepay/success"
                        },
                        {
                            "name": "sph-webhook-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mobilepay/cancel"
                        },
                        {
                            "name": "sph-webhook-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mobilepay/cancel"
                        },
                        {
                            "name": "sph-webhook-delay",
                            "value": "60"
                        },
                        {
                            "name": "sph-sub-merchant-id",
                            "value": "375917"
                        },
                        {
                            "name": "signature",
                            "value": "SPH1 checkout_1 d50774d580f1cc2a614bd6eba64d94e704d3f43bbed338cea9dd15797fd88640"
                        }
                    ]
                },
                {
                    "name": "Osuuspankki",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/osuuspankki/redirect",
                    "icon": "https://payment.checkout.fi/static/img/op_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/op-logo.svg",
                    "id": "osuuspankki",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "action_id",
                            "value": "701"
                        },
                        {
                            "name": "VERSIO",
                            "value": "1"
                        },
                        {
                            "name": "MYYJA",
                            "value": "Esittelymyyja"
                        },
                        {
                            "name": "VAHVISTUS",
                            "value": "K"
                        },
                        {
                            "name": "TARKISTEVERSIO",
                            "value": "1"
                        },
                        {
                            "name": "MAKSUTUNNUS",
                            "value": "96507997"
                        },
                        {
                            "name": "SUMMA",
                            "value": "15.25"
                        },
                        {
                            "name": "VIITE",
                            "value": "968472563"
                        },
                        {
                            "name": "VIESTI",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "VIEST1",
                            "value": "Markkinointinimi 3759170"
                        },
                        {
                            "name": "VIEST2",
                            "value": ""
                        },
                        {
                            "name": "PALUU-LINKKI",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/osuuspankki/success?signature=df061d916fe125c4ef336eec73cf57fbac72b8a69ee61c8a9da9f451b29ebe0b&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.124Z&checkout-nonce=ba65357f-0c80-4f5c-a568-46b257990176"
                        },
                        {
                            "name": "PERUUTUSLINKKI",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/osuuspankki/cancel?signature=7e5e455296a2c297f8cd1b447f67a9c31f2442c5b254f8a70939bb654f17a151&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.125Z&checkout-nonce=d77ede77-9cfb-4444-8c72-c57cd78466c9"
                        },
                        {
                            "name": "VALUUTTALAJI",
                            "value": "EUR"
                        },
                        {
                            "name": "TARKISTE",
                            "value": "CFD2E75B51376015C2A75CD736400D0E"
                        }
                    ]
                },
                {
                    "name": "Nordea",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/nordea/redirect",
                    "icon": "https://payment.checkout.fi/static/img/nordea_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/nordea.svg",
                    "id": "nordea",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "VERSION",
                            "value": "0003"
                        },
                        {
                            "name": "RCV_NAME",
                            "value": "Checkout Oy"
                        },
                        {
                            "name": "CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "RCV_ID",
                            "value": "12345678"
                        },
                        {
                            "name": "RCV_ACCOUNT",
                            "value": "29501800000014"
                        },
                        {
                            "name": "KEYVERS",
                            "value": "0001"
                        },
                        {
                            "name": "AMOUNT",
                            "value": "15.25"
                        },
                        {
                            "name": "STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "REF",
                            "value": "968472563"
                        },
                        {
                            "name": "LANGUAGE",
                            "value": "1"
                        },
                        {
                            "name": "MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/nordea/success?signature=f2316f0f23810ef3adab21caad589da9e68a65dd3e3ae6a97b30506c298833c6&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.125Z&checkout-nonce=f7f34ccb-8e65-4f12-a104-ba6294bb648c"
                        },
                        {
                            "name": "CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/nordea/cancel?signature=4467fb74b2344206b83b54420fa5bd703750c1a6d0a0a0bd24610b9bd7519f10&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.126Z&checkout-nonce=5f1a24f1-bfa5-4c58-bb38-7c1c9528416e"
                        },
                        {
                            "name": "REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/nordea/cancel?signature=ae3e44040b8c0eca376ef744ba578e03e553bac23a0f00c3754dd9ef4c399681&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.134Z&checkout-nonce=e96a37cd-3efb-4df6-8d43-d7ef2211c1d2"
                        },
                        {
                            "name": "MAC",
                            "value": "688BF66E0AF742951AA13D13A89EBF68"
                        }
                    ]
                },
                {
                    "name": "Handelsbanken",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/handelsbanken/redirect",
                    "icon": "https://payment.checkout.fi/static/img/handelsbanken_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/handelsbanken.svg",
                    "id": "handelsbanken",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "NET_VERSION",
                            "value": "003"
                        },
                        {
                            "name": "NET_DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "NET_CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "NET_ALG",
                            "value": "03"
                        },
                        {
                            "name": "NET_SELLER_ID",
                            "value": "0000000000"
                        },
                        {
                            "name": "NET_STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "NET_AMOUNT",
                            "value": "15,25"
                        },
                        {
                            "name": "NET_CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "NET_REF",
                            "value": "968472563"
                        },
                        {
                            "name": "NET_MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "NET_RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/handelsbanken/success?signature=dd06c738ae6e1460c80a43a483f67e75f1725024dd195f65a48e1db11dc002e9&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.135Z&checkout-nonce=d14d85ce-c326-4c22-a275-664115bbbfad"
                        },
                        {
                            "name": "NET_CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/handelsbanken/cancel?signature=b9c3c3dc3926af03c9fc6daf754cdb0b509585b5e77bbea196d28a7df45d5624&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.135Z&checkout-nonce=8e3679c1-ed85-4c94-b351-44758d7f56b8"
                        },
                        {
                            "name": "NET_REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/handelsbanken/cancel?signature=890ba0304d56764994ac44e46f03831e6bb0061b107faaf7af02d9d8191185fa&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.135Z&checkout-nonce=0a038f92-4b1a-4356-8d88-0799cd83dd4e"
                        },
                        {
                            "name": "NET_MAC",
                            "value": "FC7D675659F084474E42628EBF7C0E599FE380FB1C53E31775B516551DEC5E3F"
                        }
                    ]
                },
                {
                    "name": "POP Pankki",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/pop/redirect",
                    "icon": "https://payment.checkout.fi/static/img/poppankki_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/pop-pankki.svg",
                    "id": "pop",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "NET_VERSION",
                            "value": "003"
                        },
                        {
                            "name": "NET_DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "NET_CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "NET_ALG",
                            "value": "03"
                        },
                        {
                            "name": "NET_SELLER_ID",
                            "value": "0000000000"
                        },
                        {
                            "name": "NET_STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "NET_AMOUNT",
                            "value": "15,25"
                        },
                        {
                            "name": "NET_CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "NET_REF",
                            "value": "968472563"
                        },
                        {
                            "name": "NET_MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "NET_RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/pop/success?signature=2360746ce78a37137923b6b19d61c20591904dcd09dcb83b088a51a82d02b4b9&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.136Z&checkout-nonce=39816ed8-6e9f-49a4-8ff2-4d1cd68ffaf9"
                        },
                        {
                            "name": "NET_CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/pop/cancel?signature=4bfa056d09300a4963d2023d55709e3c5f17fbec44a5793f9c4a5f7bfddbf633&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.136Z&checkout-nonce=337cced2-59ff-424a-b94f-0bfe61e862e6"
                        },
                        {
                            "name": "NET_REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/pop/cancel?signature=e6fd3db4c492683e8a284383c7ad596ae7baa52f4e75e41171a70eac157772c1&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.136Z&checkout-nonce=e3c2d289-0f18-438f-bc6f-7d6b1e60f34e"
                        },
                        {
                            "name": "NET_MAC",
                            "value": "7582A668A8BC98CF2992C2F38F9E79F54E10993764A7D17002B5FAF3A1D5D542"
                        }
                    ]
                },
                {
                    "name": "Aktia",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/aktia/redirect",
                    "icon": "https://payment.checkout.fi/static/img/aktia_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/aktia.svg",
                    "id": "aktia",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "NET_VERSION",
                            "value": "010"
                        },
                        {
                            "name": "NET_DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "NET_CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "NET_ALG",
                            "value": "03"
                        },
                        {
                            "name": "NET_SELLER_ID",
                            "value": "1111111111111"
                        },
                        {
                            "name": "NET_STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "NET_AMOUNT",
                            "value": "15,25"
                        },
                        {
                            "name": "NET_CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "NET_REF",
                            "value": "968472563"
                        },
                        {
                            "name": "NET_MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "NET_RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/aktia/success"
                        },
                        {
                            "name": "NET_CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/aktia/cancel"
                        },
                        {
                            "name": "NET_REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/aktia/cancel"
                        },
                        {
                            "name": "NET_KEYVERS",
                            "value": "0001"
                        },
                        {
                            "name": "NET_MAC",
                            "value": "CA4F133F0213467E108CDD3185ACBBCC1F6439530460AB7B8B51103533E5D7EF"
                        }
                    ]
                },
                {
                    "name": "S\u00e4\u00e4st\u00f6pankki",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/saastopankki/redirect",
                    "icon": "https://payment.checkout.fi/static/img/saastopankki_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/saastopankki.svg",
                    "id": "saastopankki",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "NET_VERSION",
                            "value": "003"
                        },
                        {
                            "name": "NET_DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "NET_CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "NET_ALG",
                            "value": "03"
                        },
                        {
                            "name": "NET_SELLER_ID",
                            "value": "0000000000"
                        },
                        {
                            "name": "NET_STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "NET_AMOUNT",
                            "value": "15,25"
                        },
                        {
                            "name": "NET_CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "NET_REF",
                            "value": "968472563"
                        },
                        {
                            "name": "NET_MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "NET_RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/saastopankki/success?signature=2aa775df2da1bb4229aa4ac76b2100a3d572164c001f9a8dde30dd2b8a4dcdd1&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.137Z&checkout-nonce=7bf17cf1-9d17-4194-af50-ff6315dce9f4"
                        },
                        {
                            "name": "NET_CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/saastopankki/cancel?signature=9e8149558b4aea4b4e6aad94bbddd4f572b1c29c75455cde4acb1d299a95fd2b&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.138Z&checkout-nonce=b1bafcb6-431b-4a33-b97c-75af003f6147"
                        },
                        {
                            "name": "NET_REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/saastopankki/cancel?signature=6f101708625a50753d5230515d038ab1e02e77ac573903cda74b8c0c52f80334&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.138Z&checkout-nonce=17dbd9b0-908e-4d7b-99a7-b7f038032b94"
                        },
                        {
                            "name": "NET_MAC",
                            "value": "68CCC7A6FD26B992CB4445C7C442C55E3D5D516048468B4535F7D8C4514CA7D5"
                        }
                    ]
                },
                {
                    "name": "OmaSp",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/omasp/redirect",
                    "icon": "https://payment.checkout.fi/static/img/omasp_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/omasp.svg",
                    "id": "omasp",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "NET_VERSION",
                            "value": "003"
                        },
                        {
                            "name": "NET_DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "NET_CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "NET_ALG",
                            "value": "03"
                        },
                        {
                            "name": "NET_SELLER_ID",
                            "value": "0000000000"
                        },
                        {
                            "name": "NET_STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "NET_AMOUNT",
                            "value": "15,25"
                        },
                        {
                            "name": "NET_CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "NET_REF",
                            "value": "968472563"
                        },
                        {
                            "name": "NET_MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "NET_RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/omasp/success?signature=c4c23f88b26b0937ddacdb68d0bae45607c544951c8b16eb5db41a697e32b1ad&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.139Z&checkout-nonce=9d79eefe-af3d-4ee7-9184-e4bdb52929d4"
                        },
                        {
                            "name": "NET_CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/omasp/cancel?signature=285cd796b6574711b67a21690f7502006678119b1c31d939edaf8d47d56b2c96&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.139Z&checkout-nonce=29c8f7cf-6265-4a6c-a658-a522e7ddc42e"
                        },
                        {
                            "name": "NET_REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/omasp/cancel?signature=308e6b61ecc57aa6fb9ae2b18d91f5be42ea3e5f56265a92de9c5db134745d66&checkout-transaction-id=fd605b6a-fd3f-11e8-b642-679d6dca4ac7&checkout-account=375917&checkout-method=GET&checkout-algorithm=sha256&checkout-timestamp=2018-12-11T12%3A26%3A31.139Z&checkout-nonce=c499a59d-73a3-45cc-875c-f527c3e36dcf"
                        },
                        {
                            "name": "NET_MAC",
                            "value": "0BEFF01F6C2FB8700E548A47FAAECC5046A069E2C6BD0A1F73B518977314F31A"
                        }
                    ]
                },
                {
                    "name": "S-pankki",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/spankki/redirect",
                    "icon": "https://payment.checkout.fi/static/img/s-pankki_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/spankki.svg",
                    "id": "spankki",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "AAB_VERSION",
                            "value": "0002"
                        },
                        {
                            "name": "AAB_DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "AAB_CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "AAB_ALG",
                            "value": "03"
                        },
                        {
                            "name": "AAB_RCV_ACCOUNT",
                            "value": "FI4139390001002369"
                        },
                        {
                            "name": "AAB_RCV_NAME",
                            "value": "Checkout Finland Oy"
                        },
                        {
                            "name": "AAB_KEYVERS",
                            "value": "0001"
                        },
                        {
                            "name": "AAB_STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "AAB_RCV_ID",
                            "value": "SPANKKIESHOPID"
                        },
                        {
                            "name": "AAB_LANGUAGE",
                            "value": "1"
                        },
                        {
                            "name": "AAB_AMOUNT",
                            "value": "15,25"
                        },
                        {
                            "name": "AAB_REF",
                            "value": "968472563"
                        },
                        {
                            "name": "AAB_MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "AAB_RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/spankki/success"
                        },
                        {
                            "name": "AAB_CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/spankki/cancel"
                        },
                        {
                            "name": "AAB_REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/spankki/cancel"
                        },
                        {
                            "name": "AAB_CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "AAB_MAC",
                            "value": "AB42F428DFD680A59DE34997DE6553B63911339E4D17463C05903504F2793959"
                        }
                    ]
                },
                {
                    "name": "\u00c5landsbanken",
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/alandsbanken/redirect",
                    "icon": "https://payment.checkout.fi/static/img/alandsbanken_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/alandsbanken.svg",
                    "id": "alandsbanken",
                    "group": "bank",
                    "parameters": [
                        {
                            "name": "AAB_VERSION",
                            "value": "0002"
                        },
                        {
                            "name": "AAB_DATE",
                            "value": "EXPRESS"
                        },
                        {
                            "name": "AAB_CONFIRM",
                            "value": "YES"
                        },
                        {
                            "name": "AAB_ALG",
                            "value": "01"
                        },
                        {
                            "name": "AAB_RCV_ACCOUNT",
                            "value": "FI7766010001130855"
                        },
                        {
                            "name": "AAB_RCV_NAME",
                            "value": "Checkout Finland Oy"
                        },
                        {
                            "name": "AAB_KEYVERS",
                            "value": "0001"
                        },
                        {
                            "name": "AAB_STAMP",
                            "value": "96507997"
                        },
                        {
                            "name": "AAB_RCV_ID",
                            "value": "AABESHOPID"
                        },
                        {
                            "name": "AAB_LANGUAGE",
                            "value": "1"
                        },
                        {
                            "name": "AAB_AMOUNT",
                            "value": "15,25"
                        },
                        {
                            "name": "AAB_REF",
                            "value": "968472563"
                        },
                        {
                            "name": "AAB_MSG",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "AAB_RETURN",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/alandsbanken/success"
                        },
                        {
                            "name": "AAB_CANCEL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/alandsbanken/cancel"
                        },
                        {
                            "name": "AAB_REJECT",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/alandsbanken/cancel"
                        },
                        {
                            "name": "AAB_CUR",
                            "value": "EUR"
                        },
                        {
                            "name": "AAB_MAC",
                            "value": "215C57B5E76C7A1C1E58294D02CDF01A"
                        }
                    ]
                },
                {
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/danske/redirect",
                    "icon": "https://payment.checkout.fi/static/img/danskebank_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/danske-bank.svg",
                    "name": "Danske Bank",
                    "group": "bank",
                    "id": "danske",
                    "parameters": [
                        {
                            "name": "KNRO",
                            "value": "000000000000"
                        },
                        {
                            "name": "VERSIO",
                            "value": "4"
                        },
                        {
                            "name": "ALG",
                            "value": "03"
                        },
                        {
                            "name": "SUMMA",
                            "value": "15.25"
                        },
                        {
                            "name": "VIITE",
                            "value": "968472563"
                        },
                        {
                            "name": "VALUUTTA",
                            "value": "EUR"
                        },
                        {
                            "name": "ERAPAIVA",
                            "value": "11.12.2018"
                        },
                        {
                            "name": "OKURL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/danske/success"
                        },
                        {
                            "name": "VIRHEURL",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/danske/cancel"
                        },
                        {
                            "name": "lng",
                            "value": "1"
                        },
                        {
                            "name": "TARKISTE",
                            "value": "A2760C9674A06B96723E42A87B6CFFCA41239C778837EF6B0D0AFF10C05B07D5"
                        }
                    ]
                },
                {
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/redirect",
                    "icon": "https://payment.checkout.fi/static/img/visa_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/visa.svg",
                    "name": "Visa",
                    "group": "creditcard",
                    "id": "creditcard",
                    "parameters": [
                        {
                            "name": "sph-account",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-merchant",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-api-version",
                            "value": "20170627"
                        },
                        {
                            "name": "sph-timestamp",
                            "value": "2018-12-11T12:26:31Z"
                        },
                        {
                            "name": "sph-request-id",
                            "value": "0ed9ed24-a9c9-4db3-b78c-61fbe9194da1"
                        },
                        {
                            "name": "sph-amount",
                            "value": "1525"
                        },
                        {
                            "name": "sph-currency",
                            "value": "EUR"
                        },
                        {
                            "name": "sph-order",
                            "value": "968472563"
                        },
                        {
                            "name": "language",
                            "value": "FI"
                        },
                        {
                            "name": "description",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "sph-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/success"
                        },
                        {
                            "name": "sph-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/success"
                        },
                        {
                            "name": "sph-webhook-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-delay",
                            "value": "60"
                        },
                        {
                            "name": "sph-sub-merchant-id",
                            "value": "375917"
                        },
                        {
                            "name": "signature",
                            "value": "SPH1 checkout_1 927ec82d13a724df11119dcd77f10227c213863ea3d28ea6c155f65598bc40bb"
                        }
                    ]
                },
                {
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/redirect",
                    "icon": "https://payment.checkout.fi/static/img/visae.gif",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/visa-electron.svg",
                    "name": "Visa Electron",
                    "group": "creditcard",
                    "id": "creditcard",
                    "parameters": [
                        {
                            "name": "sph-account",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-merchant",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-api-version",
                            "value": "20170627"
                        },
                        {
                            "name": "sph-timestamp",
                            "value": "2018-12-11T12:26:31Z"
                        },
                        {
                            "name": "sph-request-id",
                            "value": "47cd2996-cb8b-412e-bb0f-a0e1e9e36964"
                        },
                        {
                            "name": "sph-amount",
                            "value": "1525"
                        },
                        {
                            "name": "sph-currency",
                            "value": "EUR"
                        },
                        {
                            "name": "sph-order",
                            "value": "968472563"
                        },
                        {
                            "name": "language",
                            "value": "FI"
                        },
                        {
                            "name": "description",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "sph-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/success"
                        },
                        {
                            "name": "sph-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/success"
                        },
                        {
                            "name": "sph-webhook-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-delay",
                            "value": "60"
                        },
                        {
                            "name": "sph-sub-merchant-id",
                            "value": "375917"
                        },
                        {
                            "name": "signature",
                            "value": "SPH1 checkout_1 d24dbf987ac49c8f5a647243e1578c86286c61e13bd9ac6548c576a7270b930d"
                        }
                    ]
                },
                {
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/redirect",
                    "icon": "https://payment.checkout.fi/static/img/mastercard_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/mastercard.svg",
                    "name": "Mastercard",
                    "group": "creditcard",
                    "id": "creditcard",
                    "parameters": [
                        {
                            "name": "sph-account",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-merchant",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-api-version",
                            "value": "20170627"
                        },
                        {
                            "name": "sph-timestamp",
                            "value": "2018-12-11T12:26:31Z"
                        },
                        {
                            "name": "sph-request-id",
                            "value": "5b77f2ec-cad6-46fc-b6d9-b2be438dd6d8"
                        },
                        {
                            "name": "sph-amount",
                            "value": "1525"
                        },
                        {
                            "name": "sph-currency",
                            "value": "EUR"
                        },
                        {
                            "name": "sph-order",
                            "value": "968472563"
                        },
                        {
                            "name": "language",
                            "value": "FI"
                        },
                        {
                            "name": "description",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "sph-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/success"
                        },
                        {
                            "name": "sph-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/success"
                        },
                        {
                            "name": "sph-webhook-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/creditcard/cancel"
                        },
                        {
                            "name": "sph-webhook-delay",
                            "value": "60"
                        },
                        {
                            "name": "sph-sub-merchant-id",
                            "value": "375917"
                        },
                        {
                            "name": "signature",
                            "value": "SPH1 checkout_1 fe83f7030580a1ea127e1b29269de4d79f2d2e5c08813afef4f9d159592b78d5"
                        }
                    ]
                },
                {
                    "url": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/amex/redirect",
                    "icon": "https://payment.checkout.fi/static/img/american_express_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/american-express.svg",
                    "name": "American Express",
                    "group": "creditcard",
                    "id": "amex",
                    "parameters": [
                        {
                            "name": "sph-account",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-merchant",
                            "value": "checkout"
                        },
                        {
                            "name": "sph-api-version",
                            "value": "20170627"
                        },
                        {
                            "name": "sph-timestamp",
                            "value": "2018-12-11T12:26:31Z"
                        },
                        {
                            "name": "sph-request-id",
                            "value": "a9f3fc04-8351-4ba9-ad4b-159fc649a516"
                        },
                        {
                            "name": "sph-amount",
                            "value": "1525"
                        },
                        {
                            "name": "sph-currency",
                            "value": "EUR"
                        },
                        {
                            "name": "sph-order",
                            "value": "968472563"
                        },
                        {
                            "name": "language",
                            "value": "FI"
                        },
                        {
                            "name": "description",
                            "value": "Markkinointinimi / 3759170"
                        },
                        {
                            "name": "sph-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/amex/success"
                        },
                        {
                            "name": "sph-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/amex/cancel"
                        },
                        {
                            "name": "sph-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/amex/cancel"
                        },
                        {
                            "name": "sph-webhook-success-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/amex/success"
                        },
                        {
                            "name": "sph-webhook-cancel-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/amex/cancel"
                        },
                        {
                            "name": "sph-webhook-failure-url",
                            "value": "https://api.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/amex/cancel"
                        },
                        {
                            "name": "sph-webhook-delay",
                            "value": "60"
                        },
                        {
                            "name": "sph-sub-merchant-id",
                            "value": "375917"
                        },
                        {
                            "name": "signature",
                            "value": "SPH1 checkout_1 7583074b3b10426be5c897eda060c31570fc7186eba7967472ffceee8f571133"
                        }
                    ]
                },
                {
                    "name": "Collector",
                    "url": "https://pay.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/collectorb2c/",
                    "icon": "https://payment.checkout.fi/static/img/collector_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/collector.svg",
                    "parameters": [
                        {
                            "name": "checkout-transaction-id",
                            "value": "fd605b6a-fd3f-11e8-b642-679d6dca4ac7"
                        },
                        {
                            "name": "checkout-account",
                            "value": "375917"
                        },
                        {
                            "name": "checkout-method",
                            "value": "POST"
                        },
                        {
                            "name": "checkout-algorithm",
                            "value": "sha256"
                        },
                        {
                            "name": "checkout-timestamp",
                            "value": "2018-12-11T12:26:31.155Z"
                        },
                        {
                            "name": "checkout-nonce",
                            "value": "0fb0db3f-0544-4676-9b74-a8d2a5d0962a"
                        },
                        {
                            "name": "signature",
                            "value": "5121a9ce96538576357a0477cd1485337e6231e949f7d2b37c15d356b7ff4cc8"
                        }
                    ],
                    "id": "collectorb2c",
                    "group": "credit"
                },
                {
                    "name": "Mash",
                    "url": "https://pay.checkout.fi/payments/fd605b6a-fd3f-11e8-b642-679d6dca4ac7/mash/landing",
                    "icon": "https://payment.checkout.fi/static/img/mash-lasku-osamaksu_140x75.png",
                    "svg": "https://payment.checkout.fi/static/img/payment-methods/mash_lasku_osamaksu.svg",
                    "id": "mash",
                    "group": "credit",
                    "parameters": [
                        {
                            "name": "checkout-transaction-id",
                            "value": "fd605b6a-fd3f-11e8-b642-679d6dca4ac7"
                        },
                        {
                            "name": "checkout-account",
                            "value": "375917"
                        },
                        {
                            "name": "checkout-method",
                            "value": "POST"
                        },
                        {
                            "name": "checkout-algorithm",
                            "value": "sha256"
                        },
                        {
                            "name": "checkout-timestamp",
                            "value": "2018-12-11T12:26:31.156Z"
                        },
                        {
                            "name": "checkout-nonce",
                            "value": "a1a98038-5009-4b61-ad95-a37d96cfc592"
                        },
                        {
                            "name": "signature",
                            "value": "806096fe4dee33f66b3bba4877de15fb61edb23731ba305a2ca359d2485142ca"
                        }
                    ]
                }
            ]
        }' ) );
    }
}
