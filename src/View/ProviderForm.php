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

// Currently terms are only in FI, SV and EN are coming soon
// in the future, this link / text will be served via Checkout API response
$terms_link = 'Maksamalla tilauksen hyväksyt <a href="https://www.checkout.fi/ehdot-ja-sopimukset/maksuehdot" target="_blank">maksupalvelun ehdot</a>.';
echo '<div class="checkout-terms-link">' . $terms_link . '</div>';

array_walk( $data, function( $provider_group, $title ) use ( $group_titles ) {

    $providers_list = [];

    foreach ( $provider_group as $provider ) {
        $providers_list[] = $provider->getName();
    }

    $provider_group_html = '<div class="provider-group"><h4 class="provider-group-title ' . $title . '">' . esc_html( $group_titles[ $title ] ?? $title ) .
        '</h4><div class="provider-list">' .
        implode( ', ', $providers_list ) . '</div></div>';

    echo $provider_group_html;

    echo '<ul class="op-payment-service-woocommerce-payment-fields hidden">';
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

// @todo move this where it is more suitable
// toggle payment method group sections' visibility
// add class to handle different theme layouts 2 or 5 items per row
echo "
<script>

    const providerGroups = document.getElementsByClassName('provider-group');
    
    for (let i = 0; i < providerGroups.length; i++) {
        providerGroups[i].addEventListener('click', function(e) {
            e.preventDefault();
            // Clear active state
            const active = document.getElementsByClassName('provider-group selected');
            if (active.length !== 0) {
                active[0].classList.remove('selected');
            }
            // Hide payment fields
            const fields = document.getElementsByClassName('op-payment-service-woocommerce-payment-fields');
            for (let ii = 0; ii < fields.length; ii++) {
                fields[ii].classList.add('hidden');
            }
            // Show current group            
            this.classList.add('selected');
            this.nextSibling.classList.remove('hidden');
        });
    }

    let handleSize = function(elem, size) {
        if (size < 600) {
            elem.classList.remove('col-wide');
            elem.classList.add('col-narrow');
        } else {
            elem.classList.remove('col-narrow');
            elem.classList.add('col-wide');
        }
    };
    
    // Payment gateways container
    const container = document.getElementById('payment');
    // Checkout container
    const checkoutContainer = document.getElementsByClassName('payment_method_checkout_finland');
    // Add some css class to help out with different width columns
    handleSize(checkoutContainer[0], Math.round(container.offsetWidth));

    // handleSize for resize event
    let timeout = false;
    let delta = 300;
    let startTime;
    let handleResize = function() {
        if (new Date() - startTime < delta) {
            setTimeout(handleResize, delta)
        } else {
            timeout = false;
            handleSize(checkoutContainer[0], Math.round(container.offsetWidth));
        }
    };
    
    window.addEventListener('resize', function() {
        startTime = new Date();
        if (timeout === false) {
            timeout = true;
            setTimeout(handleResize, delta);
        }
    });

</script>
";