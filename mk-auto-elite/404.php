<?php
/**
 * 404 template: compact recovery page.
 *
 * @package MK_Auto_Elite
 */

use MKAutoElite\Customizer;
use MKAutoElite\MobileNav;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$mkae_wa   = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
$mkae_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$mkae_cats = class_exists( '\MKAutoElite\Woo\WooCommerce' ) ? \MKAutoElite\Woo\WooCommerce::top_categories( 8 ) : array();
?>
<main id="primary" class="mkae-container mk404">
	<p class="mk404__code">404</p>
	<h1 class="mk404__title"><?php esc_html_e( "We couldn't find that page", 'mk-auto-elite' ); ?></h1>
	<p class="mk404__sub"><?php esc_html_e( 'The link may be old or the page has moved. Search for a part, browse the shop, or ask us on WhatsApp.', 'mk-auto-elite' ); ?></p>
	<div class="mk404__actions">
		<a class="mkae-btn mkae-btn--primary" href="<?php echo esc_url( $mkae_shop ); ?>"><?php echo MobileNav::icon( 'shop' ); // phpcs:ignore ?><?php esc_html_e( 'Shop parts', 'mk-auto-elite' ); ?></a>
		<a class="mkae-btn mkae-btn--outline" href="<?php echo esc_url( home_url( '/#find-parts' ) ); ?>"><?php echo MobileNav::icon( 'car' ); // phpcs:ignore ?><?php esc_html_e( 'Find by car', 'mk-auto-elite' ); ?></a>
		<a class="mkae-btn mkae-btn--whatsapp" href="<?php echo esc_url( 'https://wa.me/' . $mkae_wa ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'Ask on WhatsApp', 'mk-auto-elite' ); ?></a>
	</div>
	<?php if ( $mkae_cats ) : ?>
		<nav class="mk404__cats" aria-label="<?php esc_attr_e( 'Popular categories', 'mk-auto-elite' ); ?>">
			<p class="mk404__label"><?php esc_html_e( 'Popular categories', 'mk-auto-elite' ); ?></p>
			<?php foreach ( $mkae_cats as $mkae_name => $mkae_url ) : ?>
				<a class="mkf__chip" href="<?php echo esc_url( $mkae_url ); ?>"><?php echo esc_html( $mkae_name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</main>
<?php
get_footer();
