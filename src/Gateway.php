<?php
/**
 * Checkout Finland WooCommerce payment gateway class
 */

namespace CheckoutFinland\WooCommercePaymentGateway;

use CheckoutFinland\SDK\Request\PaymentRequest;
use CheckoutFinland\SDK\Model\Customer;
use CheckoutFinland\SDK\Model\Address;
use CheckoutFinland\SDK\Model\Item;
use CheckoutFinland\SDK\Model\CallbackUrl;
use CheckoutFinland\SDK\Exception\HmacException;
use CheckoutFinland\SDK\Request\RefundRequest;
use CheckoutFinland\SDK\Client;
use CheckoutFinland\SDK\Request\EmailRefundRequest;


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
     * Supported features.
     *
     * @var array
     */
    public $supports = [
        'products',
        'refunds',
    ];

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
        $this->client = new Client( $this->merchant_id, $this->secret_key );

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
        add_filter( 'woocommerce_admin_order_items_after_refunds', [ $this, 'refund_items' ], 10, 1 );
        add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', [ $this, 'handle_custom_searches' ], 10, 2 );
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
        $status           = filter_input( INPUT_GET, 'checkout-status' );
        $refund_callback  = filter_input( INPUT_GET, 'refund_callback' );
        $refund_unique_id = filter_input( INPUT_GET, 'refund_unique_id' );
        $order_id         = filter_input( INPUT_GET, 'order_id' );

        // Handle the response only if the status exists.
        if ( $status && ! $refund_callback ) {
            $this->handle_payment_response( $status );
        }
        elseif ( $status && $refund_callback ) {
            $this->handle_refund_response( $refund_callback, $refund_unique_id, $order_id );
        }
    }

    /**
     * Handle payment response functionalities
     *
     * @param string $status The status of the response.
     *
     * @return void
     */
    public function handle_payment_response( string $status ) {
        // Check the HMAC
        try {
            $this->client->validateHmac( filter_input_array( INPUT_GET ), '', filter_input( INPUT_GET, 'signature' ) );
        }
        catch ( HmacException $e ) {
            wp_die( esc_html__( 'HMAC signature is invalid.', 'woocommerce-payment-gateway-checkout-finland' ) );
        }

        $reference = filter_input( INPUT_GET, 'checkout-reference' );

        $orders = \wc_get_orders( [ 'checkout_reference' => $reference ] );

        if ( ! empty( $orders ) ) {
            $order = $orders[0];

            if ( $status === 'ok' ) {
                $transaction_id = filter_input( INPUT_GET, 'checkout-transaction-id' );

                // Mark payment completed and store the transaction ID.
                $order->payment_complete( $transaction_id );
                // Translators: placeholder is transaction ID.
                $order->add_order_note( sprintf( esc_html__( 'Payment completed with transaction ID %s.', 'woocommerce-payment-gateway-checkout-finland' ), $transaction_id ) );
            }
            else {
                $order->update_status( 'failed' );
                $order->add_order_note( __( 'Payment failed.', 'woocommerce-payment-gateway-checkout-finland' ) );
            }
        }
    }

    /**
     * Handle refund response functionalities
     *
     * @param string $refund_callback  Refund callback status.
     * @param string $refund_unique_id Unique ID for the refund.
     * @param string $order_id         Order ID.
     * @return void
     */
    public function handle_refund_response( string $refund_callback, string $refund_unique_id, string $order_id ) {
        // Remove the callback indicators from the GET array
        $get = filter_input_array( INPUT_GET );

        unset( $get['refund_callback'] );
        unset( $get['refund_unique_id'] );
        unset( $get['order_id'] );

        // Check the HMAC
        try {
            $this->client->validateHmac( $get, '', filter_input( INPUT_GET, 'signature' ) );
        }
        catch ( HmacException $e ) {
            wp_die( esc_html__( 'HMAC signature is invalid.', 'woocommerce-payment-gateway-checkout-finland' ) );
        }

        $refunds = \wc_get_orders( [
            'type'                      => 'shop_order_refund',
            'checkout_refund_unique_id' => $refund_unique_id,
        ]);

        if ( empty( $refunds ) ) {
            wp_die( esc_html__( 'Refund cannot be found.', 'woocommerce-payment-gateway-checkout-finland' ) );
        }
        else {
            $refund = $refunds[0];
        }

        switch ( $refund_callback ) {
            case 'success':
                $amount = get_post_meta( $refund->get_id(), '_checkout_refund_amount', true );
                $reason = get_post_meta( $refund->get_id(), '_checkout_refund_reason', true );

                $refund->set_amount( $amount );
                $refund->set_reason( $reason );
                $refund->save();

                $order = \wc_get_order( $order_id );

                $order->add_order_note(
                    __( 'Refund process completed.', 'woocommerce-payment-gateway-checkout-finland' )
                );

                update_post_meta( $refund->get_id(), '_checkout_refund_processing', false );
                break;
            case 'cancel':
                $refund->delete( true );

                $order->add_order_note(
                    __( 'Refund was cancelled by the payment provider.', 'woocommerce-payment-gateway-checkout-finland' )
                );

                do_action( 'woocommerce_refund_delete', $refund->get_id(), $order_id );
                break;
        }

        die( 'ok' );
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

        $payment = new PaymentRequest();

        // Set the order ID as the stamp to the payment request
        $payment->setStamp( get_current_blog_id() . '-' . $order_id . '-' . time() );

        // Create a random reference for the order
        $reference = sha1( uniqid( true ) );

        // Create a unique hash to be used as the request reference
        $payment->setReference( $reference );

        // Save the reference for possible later use.
        update_post_meta( $order->get_id(), '_checkout_reference', $reference );

        // Save it also as a key for fast indexed searches.
        update_post_meta( $order->get_id(), '_checkout_reference_' . $reference, true );

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

        $order->update_meta_data( '_checkout_payment_provider', $payment_provider );

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

        $order->add_order_note( __( 'Payment request created.', 'woocommerce-payment-gateway-checkout-finland' ) );

        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true ),
        ];
    }

    /**
     * Process refunds.
     *
     * @param integer $order_id Order ID to refund from.
     * @param integer $amount   Optionally the refund amount if not the whole sum.
     * @param string  $reason   Optional reason for the refund.
     * @return boolean
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        try {
            $order = \wc_get_order( $order_id );

            // Create a unique identifier for the refund
            $refund_unique_id = sha1( uniqid( true ) );

            $refund = new RefundRequest();

            if ( $amount ) {
                $refund->setAmount( $this->handle_currency( $amount ) );
            }
            else {
                $refund->setAmount( $this->handle_currency( $order->get_total() ) );
                $amount = $order->get_total();
            }

            $price = $amount;

            $url = new CallbackUrl();

            $callbacks = $this->create_redirect_url( $order );

            $url->setSuccess( $callbacks->getSuccess() . '?refund_callback=success&refund_unique_id=' . $refund_unique_id . '&order_id=' . $order_id )
                ->setCancel( $callbacks->getSuccess() . '?refund_callback=cancel&refund_unique_id=' . $refund_unique_id . '&order_id=' . $order_id );

            $refund->setCallbackUrls( $url );

            $transaction_id = $order->get_transaction_id();

            $order->add_order_note(
                sprintf(
                    // Translators: placeholder is the optional reason for the refund.
                    __( 'Refunding process started.%s', 'woocommerce-payment-gateway-checkout-finland' ),
                    $reason ? esc_html__( ' Reason: ', 'woocommerce-payment-gateway-checkout-finland' ) . esc_html( $reason ) : ''
                )
            );

            // Do some additional stuff after the refund object has been created
            add_action( 'woocommerce_order_refunded', function( $order_id, $refund_id ) use ( $order, $refund, $transaction_id, $amount, $price, $refund_unique_id ) {
                $refund_object = new \WC_Order_Refund( $refund_id );

                try {
                    $this->client->refund( $refund, $transaction_id );
                }
                catch ( \Exception $e ) {
                    switch ( $e->getCode() ) {
                        case 422:
                            // An email refund request is needed
                            $email = $order->get_billing_email();

                            $email_refund_request = new EmailRefundRequest();

                            $email_refund_request->setEmail( $email );
                            $email_refund_request->setAmount( $refund->getAmount() );
                            $email_refund_request->setCallbackUrls( $refund->getCallbackUrls() );

                            if ( count( $refund->getItems() ) > 0 ) {
                                $email_refund_request->setItems( $refund->getItems() );
                            }

                            try {
                                $this->client->emailRefund( $email_refund_request, $transaction_id );
                            }
                            catch ( \Exception $e ) {
                                switch ( $e->getCode() ) {
                                    case 422:
                                        $refund_object->delete( true );
                                        $order->add_order_note(
                                            __( 'The payment provider does not support either regular or email refunds. The refund was cancelled.', 'woocommerce-payment-gateway-checkout-finland' )
                                        );
                                        break;
                                    // Default, should be 400.
                                    default:
                                        $refund_object->delete( true );
                                        $order->add_order_note(
                                            __( 'Something went wrong with the email refund and it was cancelled.', 'woocommerce-payment-gateway-checkout-finland' )
                                        );
                                        break;
                                }
                            }
                            break;
                        // Default, should be 400.
                        default:
                            $refund_object->delete( true );
                            $order->add_order_note(
                                __( 'Something went wrong with the refund and it was cancelled.', 'woocommerce-payment-gateway-checkout-finland' )
                            );
                            break;
                    }
                }

                $reason = $refund_object->get_reason();

                update_post_meta( $refund_object->get_id(), '_checkout_refund_amount', $amount );
                update_post_meta( $refund_object->get_id(), '_checkout_refund_reason', $reason );
                update_post_meta( $refund_object->get_id(), '_checkout_refund_unique_id', $refund_unique_id );
                update_post_meta( $refund_object->get_id(), '_checkout_refund_processing', true );

                $refund_object->set_amount( 0 );

                $refund_object->set_reason( $reason . ' Refund is still being processed. The status and the amount (' . $price . ') of the refund will update when the processing is completed.' );

                $refund_object->save();
            }, 10, 2 );

            return true;
        }
        catch ( \Exception $e ) {
            die( $e->getMessage() );
        }
    }

    /**
     * Make processing refund items amounts to show in italic
     *
     * @param int $order_id Order ID to handle.
     * @return void
     */
    public function refund_items( $order_id ) {
        $order = new \WC_Order( $order_id );

        $refunds = $order->get_refunds();

        if ( $refunds ) {
            array_walk( $refunds, function( $refund ) {
                $meta = get_post_meta( $refund->get_id(), '_checkout_refund_processing', true );

                if ( $meta ) {
                    echo '<style>';
                    echo '[data-order_refund_id=' . esc_html( $refund->get_id() ) . '] span.amount {';
                    echo 'font-style: italic;';
                    echo '}';
                    echo '</style>';
                };
            });
        }
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

        // If we have any of the listed properties, we are good to go
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
     * Handle custom search query vars to get orders by certain reference or refund identifier.
     *
     * @param array $query      Args for WP_Query.
     * @param array $query_vars Query vars from WC_Order_Query.
     * @return array
     */
    public function handle_custom_searches( array $query, array $query_vars ) : array {
        if ( ! empty( $query_vars['checkout_reference'] ) ) {
            $query['meta_query'][] = [
                'key'     => '_checkout_reference_' . esc_attr( $query_vars['checkout_reference'] ),
                'compare' => 'EXISTS',
            ];
        }

        if ( ! empty( $query_vars['checkout_refund_unique_id'] ) ) {
            $query['meta_query'][] = [
                'key'     => '_checkout_refund_unique_id',
                'compare' => '=',
                esc_attr( $query_vars['checkout_refund_unique_id'] ),
            ];
        }

        return $query;
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
