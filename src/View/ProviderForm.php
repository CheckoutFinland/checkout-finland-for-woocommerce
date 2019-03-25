<?php
/**
 * Provider form view
 */

// Ensure that the file is being run within the WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

wp_enqueue_style( 'woocommerce-gateway-checkout-finland-payment-fields' );

// Something went wrong loading the providers.
if ( ! empty( $data['error'] ) ) {
    printf(
        '<p class="woocommerce-gateway-checkout-finland-payment-fields__error">%s</p>',
        esc_html( $data['error'] )
    );
    return;
}

$group_titles = [
    'mobile'     => __( 'Mobile payment methods', 'woocommerce-payment-gateway-checkout-finland' ),
    'bank'       => __( 'Bank payment methods', 'woocommerce-payment-gateway-checkout-finland' ),
    'creditcard' => __( 'Card payment methods', 'woocommerce-payment-gateway-checkout-finland' ),
    'credit'     => __( 'Invoice and instalment payment methods', 'woocommerce-payment-gateway-checkout-finland' ),
];

array_walk( $data, function( $provider_group, $title ) use ( $group_titles ) {

    echo '<h4>' . esc_html( $group_titles[ $title ] ?? $title ) . '</h4>';

    echo '<ul class="woocommerce-gateway-checkout-finland-payment-fields">';
    array_walk( $provider_group, function( $provider ) {
        printf(
            '<li class="woocommerce-gateway-checkout-finland-payment-fields--list-item">
                <label>
                    <input
                        class="woocommerce-gateway-checkout-finland-payment-fields--list-item--input"
                        type="radio" name="payment_provider" value="%s">
                    <div class="woocommerce-gateway-checkout-finland-payment-fields--list-item--wrapper">
                        <img class="woocommerce-gateway-checkout-finland-payment-fields--list-item--img" src="%s">
                    </div>
                </label>
            </li>',
            esc_html( $provider->getId() ),
            esc_html( $provider->getSvg() )
        );
    });

    echo '</ul>';
});

