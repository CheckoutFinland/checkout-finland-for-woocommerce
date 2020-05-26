<?php

use OpMerchantServices\WooCommercePaymentGateway\Gateway;
use OpMerchantServices\WooCommercePaymentGateway\Plugin;
use OpMerchantServices\WooCommercePaymentGateway\Router;

// Ensure that the file is being run within the WordPress context.
if (!defined('ABSPATH')) {
    die;
}

$saved_methods = wc_get_customer_saved_methods_list(get_current_user_id());
$has_methods = (bool)$saved_methods;
$add_card_form_url = Router::get_url(Plugin::CARD_ENDPOINT, 'add');
$delete_card_url = Router::get_url(Plugin::CARD_ENDPOINT, 'delete');
?>
<div class="provider-group-title mobile op-payment-service-woocommerce-tokenized-payment-methods-saved-payment-methods-title">
    <?php esc_html_e('Pay with saved card', 'op-payment-service-woocommerce') ?>
</div>
<?php if ($has_methods) : ?>
    <?php (new Gateway)->saved_payment_methods(); ?>
    <a class="op-payment-service-woocommerce-tokenized-payment-method-links delete-card-button button"
       href="#"><?php esc_html_e('Delete selected card', 'op-payment-service-woocommerce') ?></a>
<?php endif; ?>
<a class="op-payment-service-woocommerce-tokenized-payment-method-links add-card-button button"
   href="<?php echo $add_card_form_url ?>">
    <span class="op-payment-service-woocommerce-tokenized-payment-add-card-button dashicons dashicons-plus"></span>
    <?php esc_html_e('Add new card', 'op-payment-service-woocommerce') ?>
</a>

<script>
    jQuery(document).ready(function () {
        openTokenizedCardProviderGroupSelection();
    });

    function openTokenizedCardProviderGroupSelection() {
        jQuery("input.op-payment-service-woocommerce-tokenized-payment-method-input[type='radio']").each(function () {
            let creditCardProviderGroup = jQuery('.provider-group-title.creditcard').parent();

            if (jQuery(this).prop('checked')) {
                jQuery(creditCardProviderGroup).closest(creditCardProviderGroup).addClass('selected');
                jQuery(this).closest('.op-payment-service-woocommerce-payment-fields').removeClass('hidden');
            }
        });
    }

    jQuery('.op-payment-service-woocommerce-payment-fields input[type=radio]').click(function () {
        jQuery('.op-payment-service-woocommerce-payment-fields input[type=radio]:checked').not(this).prop('checked', false);
    });

    jQuery(".op-payment-service-woocommerce-tokenized-payment-method-links.delete-card-button").click(function (evt) {
        evt.preventDefault();
        let cardTokenId = jQuery("input[name='wc-checkout_finland-payment-token']:checked").val();

        jQuery.ajax({
            type: 'POST',
            contentType: 'application/json',
            url: '<?php echo $delete_card_url ?>',
            data: JSON.stringify({token_id: cardTokenId}),
            success: function (response) {
                if (response.success) {
                    jQuery('body').trigger('update_checkout')
                }
            }
        })
    });
</script>
