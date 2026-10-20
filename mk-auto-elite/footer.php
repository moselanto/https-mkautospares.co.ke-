<?php
/**
 * Site footer: brand/about block, curated link columns (support, policies,
 * categories, vehicle brands), social icons, newsletter, map, payments bar
 * and the WhatsApp float.
 *
 * The brand/about, support and policies columns are always rendered (they are
 * core storefront identity, not generic widget slots) so leftover widgets from
 * a previous theme can never leak into the footer.
 *
 * @package MK_Auto_Elite
 */

use MKAutoElite\Customizer;
use MKAutoElite\Woo\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mkae_page_url' ) ) {
	/**
	 * Resolve a page URL by slug in a permalink-structure-safe way.
	 */
	function mkae_page_url( string $slug ): string {
		$page = get_page_by_path( $slug );
		return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
	}
}

if ( ! function_exists( 'mkae_social_icon' ) ) {
	/**
	 * Return an inline SVG for a social network key.
	 */
	function mkae_social_icon( string $key ): string {
		$icons = array(
			'facebook'  => '<path d="M13 22v-8h2.7l.4-3H13V9.1c0-.9.3-1.5 1.6-1.5H16V5c-.3 0-1.2-.1-2.2-.1-2.2 0-3.8 1.3-3.8 3.9V11H7.5v3H10v8z"/>',
			'instagram' => '<path d="M12 8.9A3.1 3.1 0 1 0 12 15.1 3.1 3.1 0 1 0 12 8.9zm0 5.1a2 2 0 1 1 0-4 2 2 0 0 1 0 4zM16.9 8a.7.7 0 1 1-1.4 0 .7.7 0 0 1 1.4 0zM7.6 4.5h8.8A3.1 3.1 0 0 1 19.5 7.6v8.8a3.1 3.1 0 0 1-3.1 3.1H7.6a3.1 3.1 0 0 1-3.1-3.1V7.6A3.1 3.1 0 0 1 7.6 4.5zm0 1.2A1.9 1.9 0 0 0 5.7 7.6v8.8a1.9 1.9 0 0 0 1.9 1.9h8.8a1.9 1.9 0 0 0 1.9-1.9V7.6a1.9 1.9 0 0 0-1.9-1.9z"/>',
			'x_twitter' => '<path d="M17.5 4h2.6l-5.7 6.5L21 20h-5.3l-4.1-5.4L6.8 20H4.2l6.1-7L4 4h5.4l3.7 4.9zm-.9 14.4h1.4L8.4 5.5H6.9z"/>',
			'tiktok'    => '<path d="M16.5 3c.3 2 1.5 3.4 3.5 3.6v2.4c-1.2 0-2.4-.4-3.5-1.1v5.9c0 3-2.2 5.2-5.1 5.2-2.7 0-4.9-2-4.9-4.7 0-2.8 2.3-4.8 5.4-4.5v2.5c-.4-.1-.9-.2-1.3-.1-1.2.1-2 .9-2 2.1 0 1.3 1 2.2 2.3 2.2 1.4 0 2.4-1 2.4-2.6V3z"/>',
			'youtube'   => '<path d="M21.6 8.2s-.2-1.4-.8-2c-.7-.8-1.5-.8-1.9-.8C16.2 5.2 12 5.2 12 5.2h0s-4.2 0-6.9.2c-.4 0-1.2 0-1.9.8-.6.6-.8 2-.8 2S2.2 9.8 2.2 11.5v1c0 1.7.2 3.3.2 3.3s.2 1.4.8 2c.7.8 1.7.8 2.1.9 1.5.1 6.7.2 6.7.2s4.2 0 6.9-.2c.4 0 1.2 0 1.9-.8.6-.6.8-2 .8-2s.2-1.6.2-3.3v-1c0-1.7-.2-3.3-.2-3.3zM9.9 14.6V9.4l4.5 2.6z"/>',
		);
		$path = $icons[ $key ] ?? '';
		if ( '' === $path ) {
			return '';
		}
		return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}
}

$mkae_has_woo = class_exists( WooCommerce::class );
$mkae_cats    = $mkae_has_woo ? WooCommerce::top_categories( 6 ) : array();
$mkae_makes   = $mkae_has_woo ? WooCommerce::top_makes( 8 ) : array();
$mkae_socials = Customizer::social_links();
$mkae_labels  = array(
	'facebook'  => __( 'Facebook', 'mk-auto-elite' ),
	'instagram' => __( 'Instagram', 'mk-auto-elite' ),
	'x_twitter' => __( 'X', 'mk-auto-elite' ),
	'tiktok'    => __( 'TikTok', 'mk-auto-elite' ),
	'youtube'   => __( 'YouTube', 'mk-auto-elite' ),
);
?>
</div><!-- .mkae-site-content -->

<footer class="mkae-footer">

	<div class="mkae-container mkae-footer__cols">

		<div class="mkae-footer__col mkae-footer__col--about">
			<?php if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) : ?>
				<div class="mkae-footer__logo"><?php the_custom_logo(); ?></div>
			<?php else : ?>
				<h3 class="mkae-widget__title"><?php bloginfo( 'name' ); ?></h3>
			<?php endif; ?>
			<ul class="mkae-footer__contact">
				<?php
				$mkae_addr     = Customizer::get( 'address' ) ? Customizer::get( 'address' ) : Customizer::get( 'location' );
				$mkae_map_link = Customizer::get( 'map_link' );
				?>
				<li><?php echo \MKAutoElite\MobileNav::icon( 'pin' ); // phpcs:ignore ?>
					<?php if ( $mkae_map_link ) : ?>
						<a href="<?php echo esc_url( $mkae_map_link ); ?>" target="_blank" rel="noopener"><?php echo nl2br( esc_html( $mkae_addr ) ); ?></a>
					<?php else : ?>
						<?php echo nl2br( esc_html( $mkae_addr ) ); ?>
					<?php endif; ?>
				</li>
				<li><?php echo \MKAutoElite\MobileNav::icon( 'clock' ); // phpcs:ignore ?> <?php echo esc_html( Customizer::get( 'hours' ) ); ?></li>
				<li><?php echo \MKAutoElite\MobileNav::icon( 'phone' ); // phpcs:ignore ?> <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', Customizer::get( 'phone' ) ) ); ?>"><?php echo esc_html( Customizer::get( 'phone' ) ); ?></a></li>
				<li><?php echo \MKAutoElite\MobileNav::icon( 'mail' ); // phpcs:ignore ?> <a href="mailto:<?php echo esc_attr( Customizer::get( 'email' ) ); ?>"><?php echo esc_html( Customizer::get( 'email' ) ); ?></a></li>
				<?php if ( Customizer::get( 'mpesa' ) ) : ?>
					<li><?php echo \MKAutoElite\MobileNav::icon( 'wallet' ); // phpcs:ignore ?> <span><?php echo esc_html( Customizer::get( 'mpesa' ) ); ?></span></li>
				<?php endif; ?>
			</ul>
			<?php if ( ! empty( $mkae_socials ) ) : ?>
				<div class="mkae-footer__social">
					<?php foreach ( $mkae_socials as $key => $url ) : ?>
						<a class="mkae-social mkae-social--<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener nofollow" aria-label="<?php echo esc_attr( $mkae_labels[ $key ] ?? $key ); ?>"><?php echo mkae_social_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="mkae-footer__col">
			<h3 class="mkae-widget__title"><?php esc_html_e( 'Customer Support', 'mk-auto-elite' ); ?></h3>
			<ul class="mkae-footer__links">
				<li><a href="<?php echo esc_url( mkae_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'Contact Us', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( mkae_page_url( 'track-order' ) ); ?>"><?php esc_html_e( 'Track Your Order', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( mkae_page_url( 'shipping-delivery' ) ); ?>"><?php esc_html_e( 'Shipping & Delivery', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( mkae_page_url( 'return-refund' ) ); ?>"><?php esc_html_e( 'Returns & Refunds', 'mk-auto-elite' ); ?></a></li>
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<li><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'My Account', 'mk-auto-elite' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</div>

		<div class="mkae-footer__col">
			<h3 class="mkae-widget__title"><?php esc_html_e( 'Company & Policies', 'mk-auto-elite' ); ?></h3>
			<ul class="mkae-footer__links">
				<li><a href="<?php echo esc_url( mkae_page_url( 'about-us' ) ); ?>"><?php esc_html_e( 'About Us', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( mkae_page_url( 'payment-methods' ) ); ?>"><?php esc_html_e( 'Payment Methods', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( mkae_page_url( 'terms' ) ); ?>"><?php esc_html_e( 'Terms & Conditions', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : mkae_page_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'mk-auto-elite' ); ?></a></li>
			</ul>
		</div>

		<div class="mkae-footer__col">
			<h3 class="mkae-widget__title"><?php esc_html_e( 'Shop by Category', 'mk-auto-elite' ); ?></h3>
			<ul class="mkae-footer__links">
				<?php
				if ( ! empty( $mkae_cats ) ) {
					foreach ( $mkae_cats as $name => $url ) {
						printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $name ) );
					}
				} else {
					printf( '<li><a href="%s">%s</a></li>', esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ), esc_html__( 'All Products', 'mk-auto-elite' ) );
				}
				?>
			</ul>
		</div>

		<div class="mkae-footer__col">
			<h3 class="mkae-widget__title"><?php esc_html_e( 'Shop by Vehicle', 'mk-auto-elite' ); ?></h3>
			<ul class="mkae-footer__links">
				<?php
				if ( ! empty( $mkae_makes ) ) {
					foreach ( $mkae_makes as $name => $url ) {
						printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $name ) );
					}
				}
				// Always offer the vehicle finder as a clear call to action; it
				// jumps to the finder on the homepage (id="vehicle-finder").
				printf( '<li><a class="mkae-footer__finder-link" href="%s">%s</a></li>', esc_url( home_url( '/#vehicle-finder' ) ), esc_html__( 'Find parts for your car', 'mk-auto-elite' ) );
				?>
			</ul>
		</div>
	</div>

	<div class="mkae-footer__news">
		<div class="mkae-container mkae-footer__news-inner">
			<div class="mkae-footer__news-copy">
				<h3><?php esc_html_e( 'Stay in the loop', 'mk-auto-elite' ); ?></h3>
				<p><?php esc_html_e( 'New arrivals, offers and fitment tips. No spam.', 'mk-auto-elite' ); ?></p>
			</div>
			<?php if ( shortcode_exists( 'mc4wp_form' ) ) : ?>
				<?php echo do_shortcode( '[mc4wp_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php elseif ( shortcode_exists( 'newsletter_form' ) ) : ?>
				<?php echo do_shortcode( '[newsletter_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<?php $mkae_nl = Customizer::get( 'newsletter_action' ); ?>
				<form class="mkae-news-form" action="<?php echo $mkae_nl ? esc_url( $mkae_nl ) : 'mailto:' . esc_attr( Customizer::get( 'email' ) ); ?>" method="post"<?php echo $mkae_nl ? ' target="_blank" rel="noopener"' : ' enctype="text/plain"'; ?>>
					<label class="screen-reader-text" for="mkae-news-email"><?php esc_html_e( 'Your email address', 'mk-auto-elite' ); ?></label>
					<input id="mkae-news-email" type="email" name="<?php echo $mkae_nl ? 'EMAIL' : 'subscriber'; ?>" placeholder="<?php esc_attr_e( 'you@email.com', 'mk-auto-elite' ); ?>" required>
					<button type="submit" class="mkae-btn mkae-btn--primary"><?php esc_html_e( 'Subscribe', 'mk-auto-elite' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( Customizer::get( 'map_embed' ) ) : ?>
		<div class="mkae-footer__map">
			<iframe src="<?php echo esc_url( Customizer::get( 'map_embed' ) ); ?>" title="<?php esc_attr_e( 'Store location map', 'mk-auto-elite' ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
		</div>
	<?php endif; ?>

	<div class="mkae-footer__bar">
		<div class="mkae-container mkae-footer__bar-inner">
			<p class="mkae-footer__copy">
				<?php
				printf(
					/* translators: 1: year, 2: site name. */
					esc_html__( '%1$s %2$s. All rights reserved.', 'mk-auto-elite' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</p>
			<div class="mkae-footer__pay" aria-label="<?php esc_attr_e( 'Accepted payments', 'mk-auto-elite' ); ?>">
				<span class="mkae-pay">M-Pesa</span>
				<span class="mkae-pay"><?php esc_html_e( 'Cash on Delivery', 'mk-auto-elite' ); ?></span>
			</div>
			<a href="#" class="mkae-footer__top"><?php esc_html_e( 'Back to top', 'mk-auto-elite' ); ?> &#8593;</a>
		</div>
	</div>
</footer>

<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
	<?php $mkae_cart_count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0; ?>
<?php $mkae_tab = class_exists( '\\MKAutoElite\\MobileNav' ) ? \MKAutoElite\MobileNav::active_tab() : ''; ?>
<nav class="mkae-tabbar" aria-label="<?php esc_attr_e( 'Quick navigation', 'mk-auto-elite' ); ?>">
	<a class="mkae-tabbar__item<?php echo 'home' === $mkae_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo 'home' === $mkae_tab ? ' aria-current="page"' : ''; ?>>
		<span class="mkae-tabbar__ico"><?php echo \MKAutoElite\MobileNav::icon( 'home' ); // phpcs:ignore ?></span>
		<span class="mkae-tabbar__label"><?php esc_html_e( 'Home', 'mk-auto-elite' ); ?></span>
	</a>
	<a class="mkae-tabbar__item<?php echo 'shop' === $mkae_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
		<span class="mkae-tabbar__ico"><?php echo \MKAutoElite\MobileNav::icon( 'shop' ); // phpcs:ignore ?></span>
		<span class="mkae-tabbar__label"><?php esc_html_e( 'Shop', 'mk-auto-elite' ); ?></span>
	</a>
	<a class="mkae-tabbar__item" href="<?php echo esc_url( add_query_arg( array( 's' => '', 'post_type' => 'product' ), home_url( '/' ) ) ); ?>" data-mkd-search>
		<span class="mkae-tabbar__ico"><?php echo \MKAutoElite\MobileNav::icon( 'search' ); // phpcs:ignore ?></span>
		<span class="mkae-tabbar__label"><?php esc_html_e( 'Search', 'mk-auto-elite' ); ?></span>
	</a>
	<button type="button" class="mkae-tabbar__item mkae-tabbar__item--cart<?php echo 'cart' === $mkae_tab ? ' is-active' : ''; ?>" data-mkae-cart aria-label="<?php esc_attr_e( 'Open cart', 'mk-auto-elite' ); ?>">
		<span class="mkae-tabbar__ico">
			<?php echo \MKAutoElite\MobileNav::icon( 'cart' ); // phpcs:ignore ?>
			<span class="mkae-tool__count<?php echo $mkae_cart_count > 0 ? '' : ' is-empty'; ?>"><?php echo esc_html( (string) $mkae_cart_count ); ?></span>
		</span>
		<span class="mkae-tabbar__label"><?php esc_html_e( 'Cart', 'mk-auto-elite' ); ?></span>
	</button>
	<button type="button" class="mkae-tabbar__item" aria-controls="mkd" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open menu', 'mk-auto-elite' ); ?>" data-mkd-open>
		<span class="mkae-tabbar__ico"><?php echo \MKAutoElite\MobileNav::icon( 'menu' ); // phpcs:ignore ?></span>
		<span class="mkae-tabbar__label"><?php esc_html_e( 'Menu', 'mk-auto-elite' ); ?></span>
	</button>
</nav>
<?php endif; ?>

<a class="mkae-wa-float" href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D+/', '', Customizer::get( 'whatsapp' ) ) ); ?>" target="_blank" rel="nofollow noopener" aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'mk-auto-elite' ); ?>">
	<?php echo \MKAutoElite\Woo\WaOrder::icon( 26 ); // phpcs:ignore ?>
</a>

<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
<div class="mkae-cart-drawer" data-mkae-cart-drawer aria-hidden="true">
	<div class="mkae-cart-drawer__scrim" data-mkae-cart-close></div>
	<aside class="mkae-cart-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Shopping cart', 'mk-auto-elite' ); ?>">
		<header class="mkae-cart-drawer__head">
			<h2 class="mkae-cart-drawer__title"><?php esc_html_e( 'Your Cart', 'mk-auto-elite' ); ?></h2>
			<button type="button" class="mkae-cart-drawer__close" data-mkae-cart-close aria-label="<?php esc_attr_e( 'Close cart', 'mk-auto-elite' ); ?>">&times;</button>
		</header>
		<div class="mkae-cart-drawer__body" data-mkae-cart-body>
			<p class="mkae-cart-drawer__empty"><?php esc_html_e( 'Your cart is empty.', 'mk-auto-elite' ); ?></p>
		</div>
		<footer class="mkae-cart-drawer__foot" data-mkae-cart-foot hidden>
			<div class="mkae-cart-drawer__subtotal">
				<span><?php esc_html_e( 'Subtotal', 'mk-auto-elite' ); ?></span>
				<span data-mkae-cart-subtotal></span>
			</div>
			<a class="mkae-btn mkae-btn--outline mkae-btn--block" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'View cart', 'mk-auto-elite' ); ?></a>
			<a class="mkae-btn mkae-btn--primary mkae-btn--block" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Checkout', 'mk-auto-elite' ); ?></a>
		</footer>
	</aside>
</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
