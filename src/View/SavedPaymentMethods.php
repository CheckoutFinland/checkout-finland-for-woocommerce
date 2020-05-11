<?php

use OpMerchantServices\WooCommercePaymentGateway\Gateway;
use OpMerchantServices\WooCommercePaymentGateway\Plugin;

// Ensure that the file is being run within the WordPress context.
if (!defined('ABSPATH')) {
    die;
}

$saved_methods = wc_get_customer_saved_methods_list(get_current_user_id());
$has_methods = (bool)$saved_methods;
$add_card_form_url = Plugin::BASE_URL . Plugin::ADD_CARD_FORM_URL;
?>
<div class="provider-group-title mobile"><?php echo __('Select saved card for payment') ?></div>
<?php if ($has_methods) : ?>
    <?php (new Gateway)->saved_payment_methods(); ?>
<?php endif; ?>
<a href="<?php echo home_url() . '/' . $add_card_form_url . '/index' ?>"><?php echo __('Add new card as payment method') ?></a>
