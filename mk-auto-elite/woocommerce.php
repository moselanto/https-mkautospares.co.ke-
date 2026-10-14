<?php
/**
 * Generic WooCommerce wrapper. Applies the theme layout to all WC pages
 * (shop, product, cart, checkout, account). Product-specific templates are
 * layered on in a later phase.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="mkae-container mkae-shop mkae-site-woo">
	<main id="primary" class="mkae-shop__main">
	<?php
	/**
	 * woocommerce_content() renders the correct WooCommerce view for the
	 * current query. We wrap it in the theme container here because
	 * woocommerce_content() does NOT fire the woocommerce_before/after_main_content
	 * hooks, so the shop, product, cart and account pages get consistent
	 * left/right margins from .mkae-container.
	 */
	if ( function_exists( 'woocommerce_content' ) ) {
		woocommerce_content();
	}
	?>
	</main>
</div>
<?php
get_footer();
