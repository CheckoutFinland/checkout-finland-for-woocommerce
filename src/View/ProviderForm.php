<?php
/**
 * Provider form view
 */

// Ensure that the file is being run within the WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

wp_enqueue_style( 'op-payment-service-woocommerce-payment-fields' );
wp_enqueue_script( 'op-payment-service-woocommerce-payment-fields' );

// Something went wrong loading the providers.
if ( ! empty( $data['error'] ) ) {
    printf(
        '<p class="op-payment-service-woocommerce-payment-fields__error">%s</p>',
        esc_html( $data['error'] )
    );
    return;
}

// Terms
$terms_link = $data['terms'];
echo '<div class="checkout-terms-link">' . $terms_link . '</div>';

array_walk( $data['groups'], function( $group ) {
    echo '<div class="provider-group">';
    $providers_list = [];
    //var_dump($group['providers']);
    echo '<style type="text/css">';
    foreach( $group['providers'] as $key => $provider ) {
        // Create simple list of provider names only
        $providers_list[] = $provider->getName();
        // Styles for group icons
        $group_id =  $group['id'];
        $group_icon = $group['icon'];
        if ($key === 0) {

            echo <<<EOL
            .payment_method_checkout_finland .provider-group-title.$group_id i {
                background: url($group_icon) no-repeat;
                background-size: 28px 28px;
                background-position-y: center;
            }
            .payment_method_checkout_finland .provider-group.selected .provider-group-title.$group_id i {
                background: url($group_icon) no-repeat;
                background-size: 28px 28px;
                background-position-y: center;
            }
EOL;
        }
        
    }
    echo '</style>';
    echo '<div class="provider-group-title ' . $group['id']  . '">';
    echo '<i></i>';
    echo esc_html( $group['name'] );
    echo '</div>';
    echo '<div class="provider-list">';
    echo implode( ', ', $providers_list );
    echo '</div>';
    echo '</div>';
    echo '<ul class="op-payment-service-woocommerce-payment-fields hidden">';
    if (!\OpMerchantServices\WooCommercePaymentGateway\Helper::getIsSubscriptionsEnabled()) {
        array_walk( $group['providers'], function ($provider) {
            echo '<li class="op-payment-service-woocommerce-payment-fields--list-item">';
            echo '<label>';
            echo '<input class="op-payment-service-woocommerce-payment-fields--list-item--input" type="radio" name="payment_provider" value="' . $provider->getId() . '">';
            echo '<div class="op-payment-service-woocommerce-payment-fields--list-item--wrapper">';
            echo '<img class="op-payment-service-woocommerce-payment-fields--list-item--img" src="' . $provider->getSvg() . '">';
            echo '</div>';
            echo '</label>';
            echo '</li>';
        });
    }
    if (is_user_logged_in() && $group['id'] == 'creditcard') {
        (new \OpMerchantServices\WooCommercePaymentGateway\Gateway)->render_saved_payment_methods();
    }
    echo '</ul>';
});

// @todo move this where it is more suitable
// toggle payment method group sections' visibility
// add class to handle different theme layouts 2 or 5 items per row
echo "
<script>
    if (typeof initOpcheckout === 'function'){
        initOpcheckout();
    }
</script>
";