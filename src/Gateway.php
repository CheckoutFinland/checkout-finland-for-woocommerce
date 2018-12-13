<?php
/**
 * Checkout Finland WooCommerce payment gateway class
 */

namespace CheckoutFinland\WooCommercePaymentGateway;

use CheckoutFinland\SDK\Request\Payment as RequestPayment;
use CheckoutFinland\SDK\Model\Customer;
use CheckoutFinland\SDK\Model\Address;
use CheckoutFinland\SDK\Model\Item;
use CheckoutFinland\SDK\Model\CallbackUrl;


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
     * @var \WC_Logger
     */
    protected $logger = null;

    /**
     * Checkout Finland SDK Client instance
     *
     * @var \CheckoutFinland\SDK\Client
     */
    protected $client = null;

    /**
     * Object constructor
     */
    public function __construct() {
        // Set payment gateway ID
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
            $this->merchant_id = (int) Plugin::TEST_MERCHANT_ID;
            $this->secret_key  = Plugin::TEST_SECRET_KEY;
        }
        else {
            $this->merchant_id = (int) $this->get_option( 'merchant_id' );
            $this->secret_key  = $this->get_option( 'secret_key' );
        }

        // Create SDK client instance
        $this->client = new \CheckoutFinland\SDK\Client( $this->merchant_id, $this->secret_key );

        // Whether we are in debug mode or not.
        $this->debug = 'yes' === $this->get_option( 'debug', 'no' );

        // Add actions and filters.
        $this->add_actions();

        // Register stylesheet for payment fields
        $this->register_styles();

        // Check if we are in response phase
        $this->check_checkout_response();
    }

    /**
     * Add all actions and filters.
     *
     * @return void
     */
    protected function add_actions() {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'receipt_page' ] );
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
     * Receipt page to redirect user forward.
     *
     * @return void
     */
    public function receipt_page() {
        $view = new View( 'CheckoutForm' );

        $provider = WC()->session->get( 'payment_provider' );

        $view->render( $provider );
    }

    /**
     * Thank you page
     *
     * @return void
     */
    public function check_checkout_response() {
        $status = filter_input( INPUT_GET, 'checkout-status' );

        // Handle the response only if the status exists.
        if ( $status ) {
            if ( $status === 'ok' ) {
                $reference = filter_input( INPUT_GET, 'checkout-reference' );

                $posts = \get_posts( [
                    'post_type'   => 'shop_order',
                    'post_status' => array_values( \get_post_stati() ),
                    'meta_query'  => [
                        [
                            'key'     => 'checkout_reference_' . $reference,
                            'compare' => 'EXISTS',
                        ],
                    ],
                ] );

                if ( empty( $posts ) ) {
                    return;
                }
                else {
                    $post = $posts[0];
                }

                $order = wc_get_order( $post->ID );

                $order->update_status( 'completed' );
            }
        }
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
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        $payment = new RequestPayment();

        // Set the order ID as the stamp to the payment request
        $payment->setStamp( get_current_blog_id() . '-' . $order_id . '-' . time() );

        // Create a random reference for the order
        $reference = sha1( uniqid( true ) );

        // Create a unique hash to be used as the request reference
        $payment->setReference( $reference );

        // Save the reference for possible later use.
        update_post_meta( $order->get_id(), 'checkout_reference', $reference );

        // Save it also as a key for fast indexed searches.
        update_post_meta( $order->get_id(), 'checkout_reference_' . $reference, true );

        // Fetch current currency and the cart total
        $currency   = get_woocommerce_currency();
        $cart_total = $this->get_cart_total();

        // Set the aforementioned values to the payment request
        $payment->setCurrency( $currency )
            ->setAmount( $cart_total );

        // Create a customer object from the order
        $customer = $this->create_customer( $order );

        // Set the customer object to the payment request
        $payment->setCustomer( $customer );

        // Create a billing address and assign it to the payment request
        $billing_address = $this->create_address( $order, 'invoicing' );

        if ( $billing_address ) {
            $payment->setInvoicingAddress( $billing_address );
        }

        // Create a shipping address and assign it to the payment request
        $shipping_address = $this->create_address( $order, 'delivery' );

        if ( $shipping_address ) {
            $payment->setDeliveryAddress( $shipping_address );
        }

        // Get and assign the WordPress locale
        switch ( get_locale() ) {
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

        $payment->setLanguage( $locale );

        // Get the items from the cart
        $items = WC()->cart->get_cart_contents();

        // Convert items to SDK Item objects.
        $items = array_map( function( $item ) {
            return $this->create_item( $item );
        }, $items );

        $items[] = $this->create_shipping_item( $order );

        // Assign the items to the payment request
        $payment->setItems( $items );

        // Create and assign the return urls
        $payment->setRedirectUrls( $this->create_redirect_url( $order ) );

        // Get the wanted payment provider and save it to the order
        $payment_provider = filter_input( INPUT_POST, 'payment_provider' );

        $order->update_meta_data( 'checkout_payment_provider', $payment_provider );

        // Create a payment via Checkout SDK
        try {
            $response = $this->client->createPayment( $payment );
        }
        catch ( \Exception $e ) {
            var_dump( $e->getMessages() );
        }

        $providers = $response->getProviders();

        $wanted_provider = array_reduce( $providers, function( $carry, $item = null ) use ( $payment_provider ) {
            if ( $item && $item->getId() === $payment_provider ) {
                return $item;
            }

            return $carry;
        });

        WC()->session->set( 'payment_provider', $wanted_provider );

        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true ),
        ];
    }

    /**
     * Renders the provider selection form
     *
     * @return void
     */
    protected function provider_form() {
        $cart_total = $this->get_cart_total();

        $providers = $this->client->getPaymentProviders( $cart_total );

        $provider_form_view = new View( 'ProviderForm' );

        $provider_form_view->render( $providers );
    }

    /**
     * Create SDK customer object.
     *
     * @param \WC_Order $order The order to create the customer object from.
     *
     * @return \CheckoutFinland\SDK\Model\Customer
     */
    protected function create_customer( \WC_Order $order ) : Customer {
        $customer = new Customer();

        $customer->setEmail( $order->get_billing_email() ?? null )
            ->setFirstName( $order->get_billing_first_name() ?? null )
            ->setLastName( $order->get_billing_last_name() ?? null )
            ->setPhone( $order->get_billing_phone() ?? null );

        return $customer;
    }

    /**
     * Create SDK address object.
     *
     * @param \WC_Order $order The order to create the address object from.
     * @param string    $type  Whether we are creating an invoicing or a delivery address.
     * @return Address
     */
    protected function create_address( \WC_Order $order, string $type = 'invoicing' ) : ?Address {
        $address = new Address();

        switch ( $type ) {
            case 'delivery':
                $prefix = 'shipping_';
                break;
            default:
                $prefix = 'billing_';
                break;
        }

        $address_suffix = empty( $order->{ 'get_' . $prefix . 'address_2' }() ) ? null : ' ' . $order->{ 'get_' . $prefix . 'address_2' }();

        // Append 2nd address line to the address field if present
        $address->setStreetAddress( ( $order->{ 'get_' . $prefix . 'address_1' }() ?? '' . $address_suffix ) ?: null )
            ->setPostalCode( $order->{ 'get_' . $prefix . 'postcode' }() ?? null )
            ->setCity( $order->{ 'get_' . $prefix . 'city' }() ?? null )
            ->setCounty( $order->{ 'get_' . $prefix . 'state' }() ?? null )
            ->setCountry( $order->{ 'get_' . $prefix . 'country' }() ?? null );

        $has_values = array_filter(
            [ 'StreetAddress', 'PostalCode', 'City', 'County' ],
            function( $key ) use ( $address ) {
                return ! empty( $address->{ 'get' . $key }() );
            }
        );

        return $has_values ? $address : null;
    }

    /**
     * Create SDK item object.
     *
     * @param array $cart_item The cart item array to create the item ovject from.
     *
     * @return Item
     */
    protected function create_item( array $cart_item ) : Item {
        $product = $cart_item['data'];

        $item = new Item();

        $item->setUnitPrice( $this->handle_currency( $product->get_price() ) )
            ->setUnits( $cart_item['quantity'] );

        $tax_rates = \WC_Tax::get_rates( $product->get_tax_class() );

        // TODO: What to do if the are more than one tax rate for a product?
        if ( empty( $tax_rates ) ) {
            $tax_rate = 0;
        }
        else {
            $tax_rate = reset( $tax_rates );
            $tax_rate = $tax_rate['rate'];
        }

        $item->setVatPercentage( $tax_rate )
            ->setProductCode( $product->get_sku() )
            ->setDeliveryDate( date( 'Y-m-d' ) )
            ->setDescription( $product->get_description() )
            ->setStamp( $product->get_id() );

        return $item;
    }

    /**
     * Create SDK item object for shipping
     *
     * @param \WC_Order $order The order object.
     * @return Item
     */
    protected function create_shipping_item( \WC_Order $order ) : Item {
        $item = new Item();

        $vat_percentage = ( $order->get_shipping_tax() / $order->get_shipping_total() ) * 100;

        $item->setUnitPrice( $this->handle_currency( $order->get_shipping_total() + $order->get_shipping_tax() ) )
            ->setUnits( 1 )
            ->setVatPercentage( $vat_percentage )
            ->setProductCode( 'shipping' )
            ->setDeliveryDate( date( 'Y-m-d' ) );

        return $item;
    }

    /**
     * Create SDK callback URL object.
     *
     * @param \WC_Order $order The order object.
     * @return CallbackUrl
     */
    protected function create_redirect_url( \WC_Order $order ) : CallbackUrl {
        $callback = new CallbackUrl();

        $callback->setSuccess( $this->get_return_url() );
        $callback->setCancel( $order->get_cancel_order_url_raw() );

        return $callback;
    }

    /**
     * Get current WooCommerce cart total.
     *
     * @return integer
     */
    protected function get_cart_total() : int {
        $sum = WC()->cart->total;

        return $this->handle_currency( $sum );
    }

    /**
     * Currency specific formattings
     *
     * @param int|double $sum The sum to format.
     * @return integer
     */
    protected function handle_currency( $sum ) : int {
        $currency = get_woocommerce_currency();

        switch ( $currency ) {
            case 'EUR':
                $sum = round( $sum * 100 );
                break;
        }

        return $sum;
    }

    /**
     * Register payment fields styles
     *
     * @return void
     */
    protected function register_styles() {
        $plugin_instance = Plugin::instance();

        $plugin_dir_url = $plugin_instance->get_plugin_dir_url();

        $plugin_version = $plugin_instance->get_plugin_info()['Version'];

        wp_register_style( 'woocommerce-gateway-checkout-finland-payment-fields', $plugin_dir_url . 'assets/dist/main.css', [], $plugin_version );
    }
}
