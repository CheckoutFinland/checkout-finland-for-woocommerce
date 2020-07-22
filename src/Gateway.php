<?php
/**
 * OP Payment Service WooCommerce payment gateway class
 */

namespace OpMerchantServices\WooCommercePaymentGateway;

use OpMerchantServices\SDK\Exception\ValidationException;
use OpMerchantServices\SDK\Request\AddCardFormRequest;
use OpMerchantServices\SDK\Request\CitPaymentRequest;
use OpMerchantServices\SDK\Request\GetTokenRequest;
use OpMerchantServices\SDK\Request\MitPaymentRequest;
use OpMerchantServices\SDK\Request\PaymentRequest;
use OpMerchantServices\SDK\Model\Customer;
use OpMerchantServices\SDK\Model\Address;
use OpMerchantServices\SDK\Model\Item;
use OpMerchantServices\SDK\Model\CallbackUrl;
use OpMerchantServices\SDK\Exception\HmacException;
use OpMerchantServices\SDK\Request\RefundRequest;
use OpMerchantServices\SDK\Client;
use OpMerchantServices\SDK\Request\EmailRefundRequest;
use OpMerchantServices\SDK\Model\Provider;
use OpMerchantServices\SDK\Response\GetTokenResponse;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;
use GuzzleHttp\Exception\RequestException;
use WC_Payment_Token_CC;

/**
 * Class Gateway
 * The Gateway class
 * @package OpMerchantServices\WooCommercePaymentGateway
 */
final class Gateway extends \WC_Payment_Gateway
{

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
        'tokenization',
        'subscriptions',
        'subscription_cancellation',
        'subscription_suspension',
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_payment_method_change',
        'subscription_payment_method_change_customer',
        'multiple_subscriptions'
    ];

    /**
     * Dynamic method info that will be populated from an endpoint.
     *
     * @var array
     */
    public $method_info = [];

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
     * @var Helper
     */
    protected $helper = null;

    /**
     * Object constructor
     */
    public function __construct() {
        // Set payment gateway ID
        $this->id = Plugin::GATEWAY_ID;

        $this->has_fields = true;

        // Get dynamic payment method info.
        $this->method_info = $this->get_method_info();

        // These strings will show in the backend.
        $this->method_title       = $this->method_info['title'];
        $this->method_description = $this->method_info['description'];

        // These strings may show in the frontend.
        $this->title       = $this->get_option('custom_provider_name') ?? $this->method_info['title'];
        $this->description = $this->get_option('custom_provider_description') ?? $this->method_info['description'];

        // Icon temporarily disabled for size issues
        // $this->icon = Plugin::ICON_URL;

        // Set gateway admin settings fields.
        $this->set_form_fields();

        // Initialize gateway settings.
        $this->init_settings();

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

        $cofPluginVersion = 'woocommerce-' . \OpMerchantServices\WooCommercePaymentGateway\Plugin::$version;

        // Create SDK client instance
        $this->client = new Client(
            $this->merchant_id,
            $this->secret_key,
            $cofPluginVersion
        );

        // Whether we are in debug mode or not.
        $this->debug = 'yes' === $this->get_option( 'debug', 'no' );

        // Add actions and filters.
        $this->add_actions();

        // Register stylesheet for payment fields
        $this->register_styles();

        // Check if we are in response phase
        $this->check_checkout_response();

        // Create Helper instance
        $this->helper = new Helper();
    }

    /**
     * Add all actions and filters.
     *
     * @return void
     */
    protected function add_actions()
    {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_scheduled_subscription_payment_'.Plugin::GATEWAY_ID, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
        add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'receipt_page' ] );
        add_filter( 'woocommerce_admin_order_items_after_refunds', [ $this, 'refund_items' ], 10, 1 );
        add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', [ $this, 'handle_custom_searches' ], 10, 2 );
        add_filter('woocommerce_payment_gateway_get_saved_payment_method_option_html', [ $this, 'get_token_payment_option_html' ], 10, 2);
    }

    /**
     * Returns the payment method description string.
     *
     * @return array
     */
    protected function get_method_info() : array
    {
        $method_info = [
            'title' => __('OP Payment Service for WooCommerce', 'op-payment-service-woocommerce'),
            'description' => __('OP Payment Service for WooCommerce - the most comprehensive suite of payment methods in the market with a single contract', 'op-payment-service-woocommerce'),
        ];
        return $method_info;
    }

    /**
     * Returns admin form fields.
     *
     * @return void
     */
    protected function set_form_fields()
    {
        $this->form_fields = [
            // Whether the payment gateway is enabled.
            'enabled'     => [
                'title'   => __( 'Payment gateway status', 'op-payment-service-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable OP Payment Service for WooCommerce', 'op-payment-service-woocommerce' ),
                'default' => 'yes',
            ],
            // Whether test mode is enabled
            'testmode'    => [
                'title'   => __( 'Test mode', 'op-payment-service-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable test mode', 'op-payment-service-woocommerce' ),
                'default' => 'no',
            ],
            // Whether debug mode is enabled
            'debug'       => [
                'title'       => __( 'Debug log', 'op-payment-service-woocommerce' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'op-payment-service-woocommerce' ),
                'default'     => 'no',
                // translators: %s: URL
                'description' => sprintf( __( 'This enables logging all payment gateway events. The log will be written in %s. Recommended only for debugging purposes as this might save personal data.', 'op-payment-service-woocommerce' ), '<code>' . \WC_Log_Handler_File::get_log_file_path( Plugin::GATEWAY_ID ) . '</code>' ),
            ],
            // Alternative text + description to show on the Checkout page
            'custom_provider_name' => [
                'title'       => __( 'Payment provider title', 'op-payment-service-woocommerce' ),
                'type'        => 'text',
                'label'       => __( 'Used on the Checkout page title', 'op-payment-service-woocommerce' ),
                'default'     => 'OP Payment Service for WooCommerce',
                'description' => __( 'This title is displayed on the Checkout page before the payment provider images.', 'op-payment-service-woocommerce' )
            ],
            'custom_provider_description' => [
                'title'       => __( 'Payment provider description', 'op-payment-service-woocommerce' ),
                'type'        => 'text',
                'label'       => __( 'Used on the Checkout page title', 'op-payment-service-woocommerce' ),
                'default'     => 'OP Payment Service for WooCommerce',
                'description' => __( 'Depending on your theme, this description might be displayed on the Checkout page before the payment provider images.', 'op-payment-service-woocommerce' )
            ],
            // Whether to show the payment provider wall or choose the method in the store
            'provider_selection' => [
                'title'       => __( 'Payment provider selection', 'op-payment-service-woocommerce' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable payment provider selection in the checkout page', 'op-payment-service-woocommerce' ),
                'default'     => 'yes',
                'description' => __( 'Choose whether you want the payment provider selection to happen in the checkout page or in a separate page.', 'op-payment-service-woocommerce' ),
            ],
            // Checkout Finland credentials
            'merchant_id' => [
                'title'   => __( 'Merchant ID', 'op-payment-service-woocommerce' ),
                'type'    => 'text',
                'label'   => __( 'Merchant ID', 'op-payment-service-woocommerce' ),
                'default' => '',
            ],
            'secret_key'  => [
                'title'   => __( 'Secret key', 'op-payment-service-woocommerce' ),
                'type'    => 'password',
                'label'   => __( 'Secret key', 'op-payment-service-woocommerce' ),
                'default' => '',
            ],
            'fallback_country'  => [
                'title'   => __( 'Fallback country', 'op-payment-service-woocommerce' ),
                'type'    => 'select',
                'label'   => __( 'Fallback country', 'op-payment-service-woocommerce' ),
                'default' => '',
                'description' => __( 'Select country to be used as fallback if no country specified in checkout.', 'op-payment-service-woocommerce' ),
                'options' => array_merge(['' => 'Select country'], WC()->countries->get_countries())
            ],
        ];
    }

    /**
     * Save admin options.
     *
     * @return boolean
     */
    public function process_admin_options()
    {
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
    public function receipt_page()
    {
        $view = new View( 'CheckoutForm' );

        $provider = WC()->session->get( 'payment_provider' );

        $view->render( $provider );
    }

    /**
     * Renders SavedPaymentMethods view
     *
     * @return void
     */
    public function render_saved_payment_methods()
    {
        $view = new View( 'SavedPaymentMethods' );

        $view->render();
    }

    /**
     * @throws HmacException
     * @throws ValidationException
     */
    public function add_card_form($context = Plugin::ADD_CARD_CONTEXT_CHECKOUT)
    {
        $datetime = new \DateTime();
        $checkout_nonce = sha1(uniqid(true));

        $full_locale = get_locale();
        $short_locale = substr($full_locale, 0, 2);

        // Get and assign the WordPress locale
        switch ($short_locale) {
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

        if (Plugin::ADD_CARD_CONTEXT_MY_ACCOUNT === $context) {
            $success_url = Router::get_url(Plugin::ADD_CARD_REDIRECT_SUCCESS_URL, Plugin::ADD_CARD_CONTEXT_MY_ACCOUNT);
            $cancel_url = Router::get_url(Plugin::ADD_CARD_REDIRECT_CANCEL_URL, Plugin::ADD_CARD_CONTEXT_MY_ACCOUNT);
        } else if (Helper::getIsChangeSubscriptionPaymentMethod()) {
            $success_url = Router::get_url(
                Plugin::ADD_CARD_REDIRECT_SUCCESS_URL,
                Plugin::ADD_CARD_CONTEXT_CHANGE_PAYMENT_METHOD
            );
            $cancel_url = Router::get_url(
                Plugin::ADD_CARD_REDIRECT_CANCEL_URL,
                Plugin::ADD_CARD_CONTEXT_CHANGE_PAYMENT_METHOD
            );
        } else {
            $success_url = Router::get_url(Plugin::ADD_CARD_REDIRECT_SUCCESS_URL, Plugin::ADD_CARD_CONTEXT_CHECKOUT);
            $cancel_url = Router::get_url(Plugin::ADD_CARD_REDIRECT_CANCEL_URL, Plugin::ADD_CARD_CONTEXT_CHECKOUT);
        }

        $add_card_form_request = new AddCardFormRequest();
        $add_card_form_request->setCheckoutAccount($this->merchant_id);
        $add_card_form_request->setCheckoutAlgorithm('sha256');
        $add_card_form_request->setCheckoutMethod('POST');
        $add_card_form_request->setCheckoutTimestamp($datetime->format('Y-m-d\TH:i:s.u\Z'));
        $add_card_form_request->setCheckoutNonce($checkout_nonce);
        $add_card_form_request->setCheckoutRedirectSuccessUrl($success_url);
        $add_card_form_request->setCheckoutRedirectCancelUrl($cancel_url);
        $add_card_form_request->setLanguage($locale);

        // Create a addCardFormRequest via Checkout SDK
        /** @var \GuzzleHttp\Psr7\Response $response */
        $response = $this->client->createAddCardFormRequest($add_card_form_request);

        if ($location = $response->getHeader('Location')) {
            wp_redirect($location[0]);
            exit;
        }
    }

    /**
     * @return bool
     * @throws HmacException
     * @throws ValidationException
     */
    public function process_card_token()
    {
        $getTokenRequest = new GetTokenRequest();
        $getTokenRequest->setCheckoutTokenizationId(filter_input( INPUT_GET, 'checkout-tokenization-id' ));

        $response = $this->client->createGetTokenRequest($getTokenRequest);

        return (bool)$this->save_card_token($response);
    }

    /**
     * @param GetTokenResponse $card_token
     */
    private function save_card_token(GetTokenResponse $card_token)
    {
        $token = new WC_Payment_Token_CC();
        $token->set_card_type($card_token->getCard()->getType());
        $token->set_expiry_month($card_token->getCard()->getExpireMonth());
        $token->set_expiry_year($card_token->getCard()->getExpireYear());
        $token->set_last4($card_token->getCard()->getPartialPan());
        $token->set_token($card_token->getToken());
        $token->set_user_id(get_current_user_id());
        $token->set_gateway_id(Plugin::GATEWAY_ID);
        \WC_Payment_Tokens::set_users_default(get_current_user_id(), $token->get_id());

        return $token->save();
    }

    /**
     * @return array
     * @throws HmacException
     * @throws ValidationException
     */
    public function add_payment_method()
    {
        $this->add_card_form(Plugin::ADD_CARD_CONTEXT_MY_ACCOUNT);

        return array(
            'result' => 'success',
            'redirect' => wc_get_endpoint_url('payment-methods'),
        );
    }

    /**
     * Grab and display users saved card payment methods.
     */
    public function saved_payment_methods()
    {
        $html = '<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods" data-count="' . esc_attr(count($this->get_tokens())) . '">';
        foreach ($this->get_tokens() as $token) {
            $html .= $this->get_saved_payment_method_option_html($token);
        }

        $html .= '</ul>';

        echo apply_filters('wc_payment_gateway_form_saved_payment_methods_html', $html, $this); // @codingStandardsIgnoreLine
    }

    /**
     * @param $html
     * @param $token
     * @return string
     */
    public function get_token_payment_option_html($html, $token)
    {
        $html = sprintf(
            '<li class="woocommerce-SavedPaymentMethods-token op-payment-service-woocommerce-tokenized-payment-method">
				<label for="wc-%1$s-payment-token-%2$s">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" class="op-payment-service-woocommerce-tokenized-payment-method-input" %6$s />
				<div class="op-payment-service-woocommerce-tokenized-payment-method-title" title="%4$s">%5$s%3$s</div>
				</label>
			</li>',
            esc_attr( $this->id ),
            esc_attr( $token->get_id() ),
            $this->get_display_name($token),
            esc_html( $token->get_display_name() ),
            $this->get_card_image($token),
            checked( $token->is_default(), true, false )
        );

        return $html;
    }

    /**
     * @param $token
     * @return string
     */
    private function get_display_name($token)
    {
        $display = sprintf(
        /* translators: 1: last 4 digits 2: expiry month 3: expiry year */
            __( 'xxxx xxxx xxxx %1$s %2$s/%3$s', 'op-payment-service-woocommerce' ),
            $token->get_last4(),
            '<span id="op-payment-service-woocommerce-tokenized-payment-method-card-expire-date">'.$token->get_expiry_month(),
            substr( $token->get_expiry_year(), 2 ).'</span>'
        );

        return $display;
    }

    /**
     * @param $token
     * @return string
     */
    private function get_card_image($token)
    {
        $token_card_type = strtolower($token->get_card_type());

        if ($token_card_type === 'amex') {
            $token_card_type = 'american-express';
        }

        $html = sprintf(
            '<img alt="'.$token->get_card_type().'" src="'.Plugin::PAYMENT_METHOD_IMG_URL.'/%1$s.svg" class="op-payment-service-woocommerce-tokenized-payment-method-title-image" />',
            esc_html( preg_replace('/[[:space:]]+/', '-', $token_card_type) )
        );

        return $html;
    }

    /**
     * Thank you page
     *
     * @return void
     */
    public function check_checkout_response()
    {
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
    public function handle_payment_response( string $status )
    {
        // Check the HMAC
        try {
            $this->client->validateHmac( filter_input_array( INPUT_GET ), '', filter_input( INPUT_GET, 'signature' ) );
        }
        catch ( HmacException $exception ) {
            $this->signature_error( $exception );
        }

        $reference = filter_input( INPUT_GET, 'checkout-reference' );

        $orders = \wc_get_orders( [ 'checkout_reference' => $reference ] );

        if ( ! empty( $orders ) ) {
            $order = $orders[0];

            switch ( $status ) {
                case 'ok':
                    $transaction_id = filter_input( INPUT_GET, 'checkout-transaction-id' );

                    $order_status = $order->get_status();

                    if ( $order_status === 'completed' || $order_status === 'processing' ) {
                        // This order has already been processed.
                        return;
                    }

                    // Mark payment completed and store the transaction ID.
                    $order->payment_complete( $transaction_id );

                    if ( ! $this->use_provider_selection() ) {
                        // Get the chosen payment provider and save it to the order
                        $payment_provider = filter_input( INPUT_GET, 'checkout-provider' );
                        $payment_amount   = filter_input( INPUT_GET, 'checkout-amount' );

                        $order->update_meta_data( '_checkout_payment_provider', $payment_provider );

                        $providers = $this->get_payment_providers( $payment_amount );

                        if ( ! empty( $providers['error'] ) ) {
                            $provider_name = ucfirst( $payment_provider );
                        }
                        else {
                            // Get only the wanted payment provider object
                            $wanted_provider = $this->get_wanted_provider($providers, $payment_provider);
                            if (null !== $wanted_provider) {
                                $provider_name = $wanted_provider->getName() ?? ucfirst( $wanted_provider->getId() );
                            } else {
                                $provider_name = ucfirst( $payment_provider );
                            }
                        }

                        WC()->session->set( 'payment_provider', $wanted_provider );

                        $message = sprintf(
                            // translators: First parameter is transaction ID, the other is the name of the payment provider.
                            __( 'Payment completed with transaction ID %1$s and payment provider %2$s.', 'op-payment-service-woocommerce' ),
                            $transaction_id,
                            $provider_name
                        );

                        $order->add_order_note( $message );
                    }
                    else {
                        $order_note = sprintf(
                            // Translators: The placeholder is a transaction ID.
                            esc_html__(
                                'Payment completed with transaction ID %s.',
                                'op-payment-service-woocommerce'
                            ),
                            $transaction_id
                        );

                        $order->add_order_note( $order_note );
                    }

                    // Clear the cart.
                    WC()->cart->empty_cart();
                    break;
                case 'pending':
                    $order->update_status( 'on-hold' );
                    $order->add_order_note( __( 'Payment pending.', 'op-payment-service-woocommerce' ) );
                    break;
                default:
                    $order->update_status( 'failed' );
                    $order->add_order_note( __( 'Payment failed.', 'op-payment-service-woocommerce' ) );
                    break;
            }
        }
    }

    /**
     * Whether we want to use in-store provider selection or not.
     *
     * @return boolean
     */
    protected function use_provider_selection() : bool
    {
        return 'yes' === $this->get_option( 'provider_selection', 'yes' );
    }

    /**
     * Handle refund response functionalities
     *
     * @param string $refund_callback  Refund callback status.
     * @param string $refund_unique_id Unique ID for the refund.
     * @param string $order_id         Order ID.
     * @return void
     */
    public function handle_refund_response( string $refund_callback, string $refund_unique_id, string $order_id )
    {
        // Remove the callback indicators from the GET array
        $get = filter_input_array( INPUT_GET );

        unset( $get['refund_callback'] );
        unset( $get['refund_unique_id'] );
        unset( $get['order_id'] );

        // Check the HMAC
        try {
            $this->client->validateHmac( $get, '', filter_input( INPUT_GET, 'signature' ) );
        }
        catch ( HmacException $exception ) {
            $this->signature_error( $exception );
        }

        $refunds = \wc_get_orders(
            [
                'type'                      => 'shop_order_refund',
                'checkout_refund_unique_id' => $refund_unique_id,
            ]
        );

        if ( empty( $refunds ) ) {
            wp_die( esc_html__( 'Refund cannot be found.', 'op-payment-service-woocommerce' ), '', 404 );
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
                    __( 'Refund process completed.', 'op-payment-service-woocommerce' )
                );

                update_post_meta( $refund->get_id(), '_checkout_refund_processing', false );
                break;
            case 'cancel':
                $refund->delete( true );

                $order_note = __(
                    'Refund was cancelled by the payment provider.',
                    'op-payment-service-woocommerce'
                );

                $order = \wc_get_order( $order_id );
                $order->add_order_note( $order_note );

                do_action( 'woocommerce_refund_delete', $refund->get_id(), $order_id );
                break;
        }

        die( 'ok' );
    }

    /**
     * Show the payment method form in the checkout.
     *
     * @return void
     */
    public function payment_fields() {
        if ( is_checkout() && $this->use_provider_selection() )
        {
            $this->provider_form();
        }
    }

    /**
     * Process the payment and return the result.
     *
     * @param  int $order_id Order ID.
     * @return array
     * @throws \Exception If the processing fails, this error is handled by WooCommerce.
     */
    public function process_payment( $order_id )
    {
        /** @var WC_Order $order */
        $order = wc_get_order( $order_id );
        $token_id = filter_input(INPUT_POST,'wc-checkout_finland-payment-token');

        // Define if the process should die if an error occurs.
        $die_on_error = filter_input( INPUT_POST, 'woocommerce_pay') ? true : false;

        // Get the wanted payment provider and check that it exists
        if ($this->use_provider_selection()) {
            $payment_provider = filter_input( INPUT_POST, 'payment_provider' );
        } else {
            $payment_provider = filter_input( INPUT_POST, 'payment_method' );
        }

        $is_token_payment = !empty($token_id);

        if ( ! $payment_provider && ! $is_token_payment ) {
            throw new \Exception( __(
                'The payment provider was not chosen.',
                'op-payment-service-woocommerce'
            ));
        } elseif ($is_token_payment) {
            $payment_provider = 'creditcard';
        }

        if ($is_token_payment) {
            $token = \WC_Payment_Tokens::get($token_id);

            $order->add_payment_token($token);

            if ($this->helper::getIsSubscriptionsEnabled()) {
                $subscriptions = wcs_get_subscriptions_for_order($order_id);

                foreach ($subscriptions as $subscription) {
                    $subscription->add_payment_token($token);
                }
            }

            $payment = new CitPaymentRequest();

            $payment->setToken($token->get_token());
        } else {
            $payment = new PaymentRequest();
        }

        if (0 == floatval($order->get_total())) {
            $order->payment_complete();

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        }

        $this->set_base_payment_data($payment, $order);

        // Save the reference for possible later use.
        update_post_meta( $order->get_id(), '_checkout_reference', $payment->getReference() );

        // Save it also as a key for fast indexed searches.
        update_post_meta( $order->get_id(), '_checkout_reference_' . $payment->getReference(), true );

        // Save the wanted payment provider to the order
        $order->update_meta_data( '_checkout_payment_provider', $payment_provider );

        // Create a payment via Checkout SDK
        try {
            if ($is_token_payment) {
                return $this->create_cit_payment($payment, $order);
            } else {
                return $this->create_normal_payment($payment, $order, $payment_provider);
            }
        }
        catch ( ValidationException $exception ) {
            $message = __(
                'An error occurred validating the payment.',
                'op-payment-service-woocommerce'
            );

            $this->error( $exception, $message, $die_on_error );
        }
        catch ( HmacException $exception ) {
            $this->signature_error( $exception, $die_on_error );
        }
        catch ( RequestException $exception ) {
            $message = __(
                'An error occurred performing the payment request.',
                'op-payment-service-woocommerce'
            );

            $this->error( $exception, $message, $die_on_error );
        }

        return [
            'result'   => 'failure'
        ];
    }

    /**
     * @param PaymentRequest|CitPaymentRequest $payment
     * @param WC_Order $order
     * @param $payment_provider
     * @return array
     * @throws HmacException
     * @throws ValidationException
     * @throws \Exception
     */
    private function create_normal_payment($payment, $order, $payment_provider)
    {
        try {
            $response = $this->client->createPayment( $payment );

            // Log the payment request if debug log is enabled.
            $this->log('OpMerchantServices\SDK\Request\PaymentRequest: ' . json_encode($payment), 'info');
        } catch (\Exception $exception) {
            // Log the error message if debug log is enabled.
            $this->log( $exception->getMessage() . $exception->getTraceAsString(), 'error' );
            new \WP_Error( $exception->getCode(), $exception->getMessage() );
        }

        if ( $this->use_provider_selection() ) {
            $providers = $response->getProviders();

            // Get only the wanted payment provider object
            $wanted_provider = $this->get_wanted_provider($providers, $payment_provider);

            WC()->session->set( 'payment_provider', $wanted_provider );

            $message = sprintf(
            // translators: First parameter is transaction ID, the other is the name of the payment provider.
                __(
                    'Transaction %1$s created with payment provider %2$s.',
                    'op-payment-service-woocommerce'
                ),
                $response->getTransactionId(),
                $wanted_provider->getName() ?? ucfirst( $payment_provider )
            );

            $order->add_order_note( $message );

            return [
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true ),
            ];
        }
        else {
            $message = sprintf(
            // translators: First parameter is transaction ID, the other is the name of the payment provider.
                __(
                    'Transaction %1$s created and user redirected to the payment provider selection page.',
                    'op-payment-service-woocommerce'
                ),
                $response->getTransactionId()
            );

            $order->add_order_note( $message );

            return [
                'result'   => 'success',
                'redirect' => $response->getHref(),
            ];
        }
    }

    /**
     * @param CitPaymentRequest $payment
     * @param WC_Order $order
     * @throws HmacException
     * @throws ValidationException
     */
    private function create_cit_payment($payment, $order)
    {
        try {
            $response = $this->client->createCitPaymentCharge($payment);

            // Log the payment request if debug log is enabled.
            $this->log('OpMerchantServices\SDK\Request\CitPaymentRequest: ' . json_encode($payment), 'info');
        } catch (\Exception $exception) {
            $fail_message = __('Failed to create token payment using card.', 'op-payment-service-woocommerce');

            // Log the error message if debug log is enabled.
            $this->log( $exception->getMessage() . $exception->getTraceAsString(), 'error' );
            new \WP_Error( $exception->getCode(), $exception->getMessage() );

            wc_add_notice( $fail_message, 'error' );

            $order->add_order_note( $fail_message );

            return [
                'result'   => 'fail'
            ];
        }
        $requires_threeds = $response->getThreeDSecureUrl() !== null;

        if ($response->getTransactionId() === null && $requires_threeds) {
            throw new \Exception('Transcaction Id not found');
        }

        $message = sprintf(
        // translators: First parameter is transaction ID, and the other whether 3DS authentication was required.
            __(
                'Transaction %1$s created by token payment using card. Requires 3DS: %2$s',
                'op-payment-service-woocommerce'
            ),
            $response->getTransactionId(),
            $requires_threeds ? __('yes', 'op-payment-service-woocommerce') : __('no', 'op-payment-service-woocommerce')
        );

        $order->add_order_note( $message );

        if (!$requires_threeds) {
            $order->payment_complete( $response->getTransactionId() );
        }

        $redirect_url = $response->getThreeDSecureUrl() ?? $this->get_return_url($order);

        return [
            'result'   => 'success',
            'redirect' => $redirect_url
        ];
    }

    /**
     * @param MitPaymentRequest $payment
     * @param WC_Order $order
     * @return bool
     * @throws \Exception
     */
    private function create_mit_payment($payment, $order)
    {
        try {
            $response = $this->client->createMitPaymentCharge($payment);

            // Log the payment request if debug log is enabled.
            $this->log('OpMerchantServices\SDK\Request\MitPaymentRequest: ' . json_encode($payment), 'info');
        } catch (\Exception $exception) {
            $fail_message = __('Failed to create token payment using card.', 'op-payment-service-woocommerce');

            // Log the error message if debug log is enabled.
            $this->log( $exception->getMessage() . $exception->getTraceAsString(), 'error' );
            new \WP_Error( $exception->getCode(), $exception->getMessage() );

            $order->add_order_note( $fail_message );

            return false;
        }

        if ($response->getTransactionId() === null) {
            throw new \Exception('Transcaction Id not found');
        }

        $message = sprintf(
        // translators: First parameter is transaction ID.
            __(
                'Transaction %1$s created by token payment using card.',
                'op-payment-service-woocommerce'
            ),
            $response->getTransactionId()
        );

        $order->add_order_note( $message );

        $order->payment_complete( $response->getTransactionId() );

        return true;
    }

    /**
     * @param PaymentRequest|CitPaymentRequest|MitPaymentRequest $payment
     * @param WC_Order $order
     * @return mixed
     * @throws \Exception
     */
    private function set_base_payment_data($payment, $order)
    {
        // Set the order ID as the stamp to the payment request
        $payment->setStamp( get_current_blog_id() . '-' . $order->get_id() . '-' . time() );

        // Create a random reference for the order
        $reference = sha1( uniqid( true ) );

        // Create a unique hash to be used as the request reference
        $payment->setReference( $reference );

        // Fetch current currency and the cart total
        $currency    = get_woocommerce_currency();
        $order_total = $this->helper->handle_currency( $order->get_total() );

        // Set the aforementioned values to the payment request
        $payment->setCurrency( $currency )
            ->setAmount( $order_total );

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

        $payment->setLanguage( $locale );

        // Get the items from the order
        $items = $this->get_order_items($order);

        // Assign the items to the payment request.
        $payment->setItems( array_filter( $items ) );

        // Create and assign the return urls
        $payment->setRedirectUrls( $this->create_redirect_url( $order ) );
        $payment->setCallbackUrls( $this->create_callback_url());

        return $payment;
    }

    /**
     * @param WC_Order|\WC_Subscription $order
     * @return array
     * @throws \Exception
     */
    private function get_order_items($order)
    {
        // Get the items from the order
        $order_items = $order->get_items( [ 'line_item', 'fee', 'shipping' ] );
        $order_total = $this->helper->handle_currency( $order->get_total() );

        // Convert items to SDK Item objects.
        $items = array_map(
            function( $item ) use ( $order ) {
                return $this->create_item( $item, $order );
            }, $order_items
        );

        $sub_sum = array_sum( array_map( function( Item $item ) : int {
            return ( $item->getUnitPrice() * $item->getUnits() );
        }, $items ) );

        $qty_sum = array_sum( array_map( function( Item $item ) : int {
            return $item->getUnits();
        }, $items ) );

        if ( $sub_sum !== $order_total ) {
            $diff = absint( $sub_sum - $order_total );

            $rounding_item = new Item();
            $rounding_item->setDescription( __( 'Rounding', 'op-payment-service-woocommerce' ) );
            $rounding_item->setDeliveryDate( date( 'Y-m-d' ) );
            $rounding_item->setVatPercentage( 0 );
            $rounding_item->setUnits( ( $order_total - $sub_sum > 0 ) ? 1 : -1 );
            $rounding_item->setUnitPrice( $diff );
            $rounding_item->setProductCode( 'rounding-row' );

            $items[] = $rounding_item;
        }

        return $items;
    }

    /**
     * @param $providers
     * @param $payment_provider
     * @return mixed|null
     */
    private function get_wanted_provider($providers, $payment_provider)
    {
        // Get only the wanted payment provider object
        return
            array_reduce(
                $providers, function( $carry, $item = null ) use ( $payment_provider ) : ?Provider {
                    if ( $item && $item->getId() === $payment_provider ) {
                        return $item;
                    }
                    return $carry;
                }
            );
    }

    /**
     * @param $amount
     * @param WC_Order $order
     * @throws \Exception
     */
    public function scheduled_subscription_payment($amount, $order)
    {
        $tokens = \WC_Payment_Tokens::get_order_tokens($order->get_id());
        $token = reset($tokens);

        $payment = new MitPaymentRequest();
        $payment->setToken($token->get_token());

        $this->set_base_payment_data($payment, $order);

        // Save the reference for possible later use.
        update_post_meta( $order->get_id(), '_checkout_reference', $payment->getReference() );

        // Save it also as a key for fast indexed searches.
        update_post_meta( $order->get_id(), '_checkout_reference_' . $payment->getReference(), true );

        $this->create_mit_payment($payment, $order);
    }

    /**
     * Process refunds.
     *
     * @param integer $order_id Order ID to refund from.
     * @param integer $amount   Optionally the refund amount if not the whole sum.
     * @param string  $reason   Optional reason for the refund.
     * @return boolean|\WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' )
    {
        try {
            $order = \wc_get_order( $order_id );

            // Create a unique identifier for the refund
            $refund_unique_id = sha1( uniqid( true ) );

            $refund = new RefundRequest();

            if ( $amount ) {
                $refund->setAmount( $this->helper->handle_currency( $amount ) );
            }
            else {
                $refund->setAmount( $this->helper->handle_currency( $order->get_total() ) );
                $amount = $order->get_total();
            }

            if ( $refund->getAmount() === 0 ) {
                return new \WP_Error(
                    '400',
                    __(
                        'The refund amount must be larger than 0.',
                        'op-payment-service-woocommerce'
                    )
                );
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
                    __( 'Refunding process started.%s', 'op-payment-service-woocommerce' ),
                    $reason ? esc_html__( ' Reason: ', 'op-payment-service-woocommerce' ) . esc_html( $reason ) : ''
                )
            );

            // Do some additional stuff after the refund object has been created
            add_action(
                'woocommerce_order_refunded',
                function( $order_id, $refund_id ) use ( $order, $refund, $reason, $transaction_id, $amount, $price, $refund_unique_id ) {
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
                                                __(
                                                    'The payment provider does not support either regular or email refunds. The refund was cancelled.',
                                                    'op-payment-service-woocommerce'
                                                )
                                            );
                                            return false; // Return when an error occurred.
                                        // Default, should be 400.
                                        default:
                                            $refund_object->delete( true );
                                            $order->add_order_note(
                                                __(
                                                    'Something went wrong with the email refund and it was cancelled.',
                                                    'op-payment-service-woocommerce'
                                                )
                                            );
                                            return false; // Return when an error occurred.
                                    }
                                }
                                break; // Break the email refund processing.
                            // Default, should be 400.
                            default:
                                $refund_object->delete( true );
                                $order->add_order_note(
                                    __(
                                        'Something went wrong with the refund and it was cancelled.',
                                        'op-payment-service-woocommerce'
                                    )
                                );
                                return false; // Return when an error occurred.
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

                    return true;
                }, 10, 2
            );

            return true;
        }
        catch ( \Exception $exception ) {
            $this->log( $exception->getMessage() . $exception->getTraceAsString(), 'error' );

            return new \WP_Error( $exception->getCode(), $exception->getMessage() );
        }
    }

    /**
     * Make processing refund items amounts to show in italic
     *
     * @param int $order_id Order ID to handle.
     * @return void
     */
    public function refund_items( $order_id )
    {
        $order = new \WC_Order( $order_id );

        $refunds = $order->get_refunds();

        if ( $refunds ) {
            array_walk(
                $refunds, function( $refund ) {
                    $meta = get_post_meta( $refund->get_id(), '_checkout_refund_processing', true );

                    if ( $meta ) {
                        echo '<style>';
                        echo '[data-order_refund_id=' . esc_html( $refund->get_id() ) . '] span.amount {';
                        echo 'font-style: italic;';
                        echo '}';
                        echo '</style>';
                    };
                }
            );
        }
    }

    /**
     * Renders the provider selection form
     *
     * @return void
     */
    protected function provider_form()
    {
        $cart_total = $this->helper->get_cart_total();
        $res = [];

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

        $providers = $this->get_grouped_payment_providers( $cart_total, $locale );

        // If there was an error getting the payment providers, show it
        if ( ! empty( $providers['error'] ) ) {
            echo '<p>' . esc_html( $providers['error'] ) . '</p>';
            return;
        }
        $res['terms'] = $providers['terms'] ?? '';
        $res['groups'] = $providers['groups'];

        $provider_form_view = new View( 'ProviderForm' );

        $provider_form_view->render( $res );
    }

    /**
     * Create SDK customer object.
     *
     * @param \WC_Order $order The order to create the customer object from.
     *
     * @return \CheckoutFinland\SDK\Model\Customer
     */
    protected function create_customer( \WC_Order $order ) : Customer
    {
        $customer = new Customer();

        $customer->setEmail( $order->get_billing_email() ?? null )
            ->setFirstName( $order->get_billing_first_name() ?? null )
            ->setLastName( $order->get_billing_last_name() ?? null )
            ->setPhone( $order->get_billing_phone() ?? null );

        return $customer;
    }

    /**
     * Get the list of payment providers
     *
     * @param integer $payment_amount Payment amount in currency minor unit, eg. cents.
     * @return array
     */
    protected function get_payment_providers( int $payment_amount ) : array
    {
        try {
            $providers = $this->client->getPaymentProviders( $payment_amount );
        }
        catch ( HmacException $exception ) {
            $providers = $this->get_payment_providers_error_handler( $exception );
        }
        catch ( RequestException $exception ) {
            $providers = $this->get_payment_providers_error_handler( $exception );
        }

        return $providers;
    }

    /**
     * Get the groupd list of payment providers
     *
     * @param integer $payment_amount Payment amount in currency minor unit, eg. cents.
     * @return array
     */
    protected function get_grouped_payment_providers( int $payment_amount, string $locale ) : array
    {
        $groups = [];

        if ($this->helper::getIsSubscriptionsEnabled()) {
            $groups = ['creditcard'];
        }

        try {
            $providers = $this->client->getGroupedPaymentProviders( $payment_amount, $locale, $groups );
        }
        catch ( HmacException $exception ) {
            $providers = $this->get_payment_providers_error_handler( $exception );
        }
        catch ( RequestException $exception ) {
            $providers = $this->get_payment_providers_error_handler( $exception );
        }

        return $providers;
    }

    /**
     * Error handler for get_payment_providers method.
     *
     * @param \Exception $exception Exception to handle.
     * @return array
     */
    protected function get_payment_providers_error_handler( \Exception $exception ) : array
    {

        // Log the error message.
        $this->log( $exception->getMessage() . $exception->getTraceAsString(), 'error' );

        $error = __(
            'An error occurred loading the payment providers.',
            'op-payment-service-woocommerce'
        );

        // You can use this filter to modify the error message.
        $error     = apply_filters( 'checkout_finland_provider_form_error', $error );
        $providers = [
            'error' => $error,
        ];

        return $providers;
    }

    /**
     * Create SDK address object.
     *
     * @param \WC_Order $order The order to create the address object from.
     * @param string    $type  Whether we are creating an invoicing or a delivery address.
     * @return Address
     */
    protected function create_address( \WC_Order $order, string $type = 'invoicing' ) : ?Address
    {
        $address = new Address();

        switch ( $type ) {
            case 'delivery':
                $prefix = 'shipping_';
                break;
            default:
                $prefix = 'billing_';
                break;
        }

        $address_suffix = empty( $order->{ 'get_' . $prefix . 'address_2' }() )
            ? null : ' ' . $order->{ 'get_' . $prefix . 'address_2' }();

        // Append 2nd address line to the address field if present
        $address->setStreetAddress( ( $order->{ 'get_' . $prefix . 'address_1' }() ?? '' . $address_suffix ) ?: null )
            ->setPostalCode( $order->{ 'get_' . $prefix . 'postcode' }() ?? null )
            ->setCity( $order->{ 'get_' . $prefix . 'city' }() ?? null )
            ->setCounty( $order->{ 'get_' . $prefix . 'state' }() ?? null )
            ->setCountry( $order->{ 'get_' . $prefix . 'country' }() ?: $this->get_option( 'fallback_country', '' ) );

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
     * Create a SDK item object.
     *
     * @param WC_Order_Item $order_item The order item object to create the item object from.
     * @param WC_Order      $order      The current order object.
     *
     * @return Item|null
     */
    protected function create_item( WC_Order_Item $order_item, WC_Order $order ) : Item
    {
        $item = new Item();

        // Get the item total with taxes and without rounding.
        // Then convert it into the integer format required by Checkout Finland.
        $sub_total = $this->helper->handle_currency( $order->get_item_total( $order_item, true, false ) );
        $item->setUnitPrice( $sub_total )
            ->setUnits( (int) $order_item->get_quantity() );

        $tax_rate = $this->get_item_tax_rate( $order_item, $order );

        $item->setVatPercentage( $tax_rate )
            ->setProductCode( $this->get_item_product_code( $order_item ) )
            ->setDeliveryDate( apply_filters( 'checkout_finland_delivery_date', date( 'Y-m-d' ) ) )
            ->setDescription( $this->get_item_description( $order_item ) )
            ->setStamp( (string) $order_item->get_id() );

        return $item;
    }

    /**
     * Get an order item product code text.
     *
     * @param WC_Order_Item $item An order item.
     *
     * @return string
     */
    protected function get_item_product_code( WC_Order_Item $item ) : string
    {
        $product_code = '';
        switch ( get_class( $item ) ) {
            case WC_Order_Item_Product::class:
                $product_code = $item->get_product()->get_sku() ?: $item->get_product()->get_id();
                break;
            case WC_Order_Item_Fee::class:
                $product_code = __( 'fee', 'op-payment-service-woocommerce' );
                $item->get_type();
                break;
            case WC_Order_Item_Shipping::class:
                $product_code = __( 'shipping', 'op-payment-service-woocommerce' );
                break;
        }
        return apply_filters( 'checkout_finland_item_product_code', $product_code, $item );
    }

    /**
     * Get an order item description text.
     *
     * @param WC_Order_Item $item An order item.
     *
     * @return string
     */
    protected function get_item_description( WC_Order_Item $item ) : string
    {
        switch ( get_class( $item ) ) {
            case WC_Order_Item_Product::class:
                $description = $item->get_product()->get_name() ?: $item->get_product()->get_id();
                break;
            default:
                $description = $item->get_name();
                break;
        }

        // Ensure the description is maximum of 1000 characters long.
        $description = mb_substr( $description, 0, 1000 );

        return apply_filters( 'checkout_finland_item_description', $description, $item );
    }

    /**
     * Get the tax rate of an order line item.
     *
     * @param WC_Order_Item $item  The order line item.
     * @param WC_Order      $order The current order object.
     *
     * @return int The tax percentage.
     */
    protected function get_item_tax_rate( WC_Order_Item $item, WC_Order $order )
    {
        $total     = $order->get_line_total( $item, false );
        $tax_total = $order->get_line_tax( $item );

        // Not taxes set.
        if ( $tax_total === 0.0 ) {
            return 0;
        }

        $tax_rate = round( ( $tax_total / $total ) * 100 );

        return (int) $tax_rate;
    }

    /**
     * Create SDK callback URL object.
     *
     * @param \WC_Order $order The order object.
     * @return CallbackUrl
     */
    protected function create_redirect_url( \WC_Order $order ) : CallbackUrl
    {
        $callback = new CallbackUrl();

        $callback->setSuccess( $this->get_return_url( $order ) );
        $callback->setCancel( $order->get_cancel_order_url_raw() );

        return $callback;
    }

    /**
     * Create SDK callback URL object for Callback urls.
     *
     * @return CallbackUrl
     */
    protected function create_callback_url() : CallbackUrl
    {
        $callback = new CallbackUrl();

        $callback->setSuccess( Router::get_url(Plugin::CALLBACK_URL, 'index') );
        $callback->setCancel( Router::get_url(Plugin::CALLBACK_URL, 'index') );

        return $callback;
    }

    /**
     * Handle custom search query vars to get orders by certain reference or refund identifier.
     *
     * @param array $query      Args for WP_Query.
     * @param array $query_vars Query vars from WC_Order_Query.
     * @return array
     */
    public function handle_custom_searches( array $query, array $query_vars ) : array
    {
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
    protected function register_styles()
    {
        $plugin_instance = Plugin::instance();

        $plugin_dir_url = $plugin_instance->get_plugin_dir_url();

        $plugin_version = $plugin_instance->get_plugin_info()['Version'];

        wp_register_style(
            'op-payment-service-woocommerce-payment-fields',
            $plugin_dir_url . 'assets/dist/main.css',
            [],
            $plugin_version
        );
    }

    /**
     * Insert new message to the log.
     *
     * @param string $message Message to log.
     * @param string $level   Log level. Defaults to 'info'. Possible values:
     *                        emergency|alert|critical|error|warning|notice|info|debug.
     */
    public function log( $message, $level = 'info' )
    {
        if ( $this->debug ) {
            if ( empty( $this->logger ) ) {
                $this->logger = \wc_get_logger();
            }

            $context = [ 'source' => Plugin::GATEWAY_ID ];

            $this->logger->log( $level, $message, $context );
        }
    }

    /**
     * A wrapper for killing the process, logging and displaying error messages.
     *
     * @param \Exception $exception An exception instance.
     * @param string     $message   A message to print out for the end user.
     * @param bool       $die       Defines if the process should be terminated.
     * @throws \Exception If the process is not killed, the error is passed on.
     */
    protected function error( \Exception $exception, string $message, bool $die = true )
    {
        $glue = PHP_EOL . '- ';

        $log_message = $message . $glue;

        $this->log( $log_message . PHP_EOL . $exception->getTraceAsString(), 'error' );

        // You can use this filter to modify the error message.
        $error = apply_filters( 'checkout_finland_error_message', $message, $exception );

        if ( $die === true ) {
            wp_die( esc_html( $error ), '', esc_html( $exception->getCode() ) );
        }
        else {
            throw $exception;
        }
    }

    /**
     * Kills the process and prints out a signature error message.
     *
     * @param HmacException $exception The exception instance.
     * @param bool          $die       Defines if the process should be terminated.
     */
    protected function signature_error( HmacException $exception, bool $die = true )
    {
        $message = __(
            'An error occurred validating the signature.',
            'op-payment-service-woocommerce'
        );

        // You can use this filter to modify the error message.
        $message = apply_filters( 'checkout_finland_signature_error', $message, $exception );

        $this->error( $exception, $message, $die );
    }
}
