<?php
/**
 * Product card template (archives, shortcode grids, related, upsells).
 * Overrides WooCommerce's default content-product.php.
 *
 * @package MK_Auto_Elite
 */

use MKAutoElite\Woo\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! is_a( $product, 'WC_Product' ) || ! $product->is_visible() ) {
	return;
}

$mkae_id      = $product->get_id();
$mkae_link    = get_permalink( $mkae_id );
$mkae_fit     = WooCommerce::fitment_line( $product );
$mkae_quote   = WooCommerce::needs_quote( $product );
$mkae_wa      = WooCommerce::whatsapp_url( $product );
$mkae_price   = $product->get_price_html();
?>
<li <?php wc_product_class( 'mkae-product', $product ); ?>>
	<a class="mkae-product__media" href="<?php echo esc_url( $mkae_link ); ?>">
		<?php
		echo wp_kses_post( $product->get_image( 'mkae-card', array( 'loading' => 'lazy', 'decoding' => 'async', 'class' => 'mkae-product__img', 'sizes' => '(max-width: 640px) 46vw, (max-width: 1024px) 30vw, 260px' ) ) );
		if ( $product->is_on_sale() ) {
			echo '<span class="mkae-badge mkae-badge--sale">' . esc_html__( 'Sale', 'mk-auto-elite' ) . '</span>';
		}
		if ( ! $product->is_in_stock() ) {
			echo '<span class="mkae-badge mkae-badge--oos">' . esc_html__( 'Out of stock', 'mk-auto-elite' ) . '</span>';
		}
		?>
	</a>
	<div class="mkae-product__body">
		<?php if ( $mkae_fit ) : ?>
			<p class="mkae-product__fit"><?php echo esc_html( $mkae_fit ); ?></p>
		<?php endif; ?>
		<h3 class="mkae-product__title">
			<a href="<?php echo esc_url( $mkae_link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</h3>
		<div class="mkae-product__price">
			<?php echo wp_kses_post( $mkae_price ? $mkae_price : '<span class="mkae-price-ask">' . esc_html__( 'Price on request', 'mk-auto-elite' ) . '</span>' ); ?>
		</div>
		<div class="mkae-product__actions">
			<?php if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && ! $mkae_quote ) : ?>
				<button type="button" class="mkae-btn mkae-btn--primary mkae-btn--sm mkae-add-to-cart" data-mkae-add data-product-id="<?php echo esc_attr( $mkae_id ); ?>">
					<span class="mkae-add-to-cart__text"><?php esc_html_e( 'Add to cart', 'mk-auto-elite' ); ?></span>
				</button>
			<?php else : ?>
				<a class="mkae-btn mkae-btn--outline mkae-btn--sm" href="<?php echo esc_url( $mkae_link ); ?>">
					<?php esc_html_e( 'View details', 'mk-auto-elite' ); ?>
				</a>
			<?php endif; ?>
			<a class="mkae-btn mkae-btn--whatsapp mkae-btn--sm" href="<?php echo esc_url( $mkae_wa ); ?>" target="_blank" rel="nofollow noopener"<?php echo \MKAutoElite\Woo\WaOrder::attr( $product ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php echo \MKAutoElite\Woo\WaOrder::icon( 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo esc_html( $mkae_quote ? __( 'Ask price', 'mk-auto-elite' ) : __( 'WhatsApp', 'mk-auto-elite' ) ); ?>
			</a>
		</div>
	</div>
</li>
