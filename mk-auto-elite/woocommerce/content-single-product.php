<?php
/**
 * Apple-style single product layout.
 * Overrides WooCommerce's content-single-product.php. All standard hooks are
 * preserved so the gallery, add-to-cart, tabs and related products still work.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

/**
 * woocommerce_before_single_product hook.
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'mkae-ps', $product ); ?>>

	<div class="mkae-ps__top">
		<div class="mkae-ps__gallery">
			<?php
			/**
			 * Sale flash (10) and product gallery/images (20).
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<div class="summary entry-summary mkae-ps__summary">
			<?php
			/**
			 * Title, rating, price, excerpt, add-to-cart, meta, plus theme
			 * additions (trust badges, WhatsApp, fitment panel).
			 */
			do_action( 'woocommerce_single_product_summary' );
			?>
		</div>
	</div>

	<div class="mkae-ps__bottom">
		<?php
		/**
		 * Product data tabs, upsells and related products.
		 */
		do_action( 'woocommerce_after_single_product_summary' );
		?>
	</div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
