<?php
/**
 * Provider form view
 */

// Ensure that the file is being run within the WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

wp_enqueue_style( 'woocommerce-gateway-checkout-finland-payment-fields' );

echo '<ul class="woocommerce-gateway-checkout-finland-payment-fields">';

array_walk( $data->providers, function( $provider ) {
    printf(
        '<li class="woocommerce-gateway-checkout-finland-payment-fields--list-item">
            <label>
                <input class="woocommerce-gateway-checkout-finland-payment-fields--list-item--input" type="radio" name="payment_provider" value="%s">
                <div class="woocommerce-gateway-checkout-finland-payment-fields--list-item--wrapper">
                    <img class="woocommerce-gateway-checkout-finland-payment-fields--list-item--img" src="%s">
                </div>
            </label>
        </li>',
        esc_html( $provider->id ),
        esc_html( $provider->svg )
    );
});

echo '</ul>';
