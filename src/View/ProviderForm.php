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

// Terms
$terms_link = $data['terms'];
echo '<div class="checkout-terms-link">' . $terms_link . '</div>';

array_walk( $data['groups'], function( $group ) {
    echo '<div class="provider-group">';
    $providers_list = [];
    echo '<style type="text/css">';
    foreach( $group['providers'] as $provider ) {
        // Create simple list of provider names only
        $providers_list[] = $provider->getName();
        // Styles for group icons
        $group_id =  $group['id'];
        $group_icon = $group['icon'];
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
            if (this.classList.contains('selected')) {
                this.classList.remove('selected');
                this.nextSibling.classList.add('hidden');
                return;
            }
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