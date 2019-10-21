<?php
/**
 * Provider form view
 */

// Ensure that the file is being run within the WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

wp_enqueue_style( 'op-payment-service-woocommerce-payment-fields' );

// Something went wrong loading the providers.
if ( ! empty( $data['error'] ) ) {
    printf(
        '<p class="op-payment-service-woocommerce-payment-fields__error">%s</p>',
        esc_html( $data['error'] )
    );
    return;
}

$group_titles = [
    'mobile'     => __( 'Mobile payment methods', 'op-payment-service-woocommerce' ),
    'bank'       => __( 'Bank payment methods', 'op-payment-service-woocommerce' ),
    'creditcard' => __( 'Card payment methods', 'op-payment-service-woocommerce' ),
    'credit'     => __( 'Invoice and instalment payment methods', 'op-payment-service-woocommerce' ),
];

array_walk( $data, function( $provider_group, $title ) use ( $group_titles ) {

    echo '<h4>' . esc_html( $group_titles[ $title ] ?? $title ) . '</h4>';

    echo '<ul class="op-payment-service-woocommerce-payment-fields">';
    array_walk( $provider_group, function( $provider ) {
        printf(
            '<li class="op-payment-service-woocommerce-payment-fields--list-item">
                <label>
                    <input
                        class="op-payment-service-woocommerce-payment-fields--list-item--input"
                        type="radio" name="payment_provider" value="%s">
                    <div class="op-payment-service-woocommerce-payment-fields--list-item--wrapper">
                        <img class="op-payment-service-woocommerce-payment-fields--list-item--img" src="%s">
                    </div>
                </label>
            </li>',
            esc_html( $provider->getId() ),
            esc_html( $provider->getSvg() )
        );
    });

    echo '</ul>';
});

