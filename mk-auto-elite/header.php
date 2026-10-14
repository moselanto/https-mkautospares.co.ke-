<?php
/**
 * Site header: utility bar, main header (logo, search, tools), primary nav,
 * and an auto-built "Shop by Category" mega menu that works on mobile.
 *
 * @package MK_Auto_Elite
 */

use MKAutoElite\Customizer;
use MKAutoElite\Woo\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'mkae' ); ?>>
<?php wp_body_open(); ?>
<a class="mkae-skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'mk-auto-elite' ); ?></a>

<div class="mkae-topbar">
	<div class="mkae-container mkae-topbar__inner">
		<ul class="mkae-topbar__contacts">
			<li><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', Customizer::get( 'phone' ) ) ); ?>"><?php echo esc_html( Customizer::get( 'phone' ) ); ?></a></li>
			<li><a href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D+/', '', Customizer::get( 'whatsapp' ) ) ); ?>" target="_blank" rel="noopener">WhatsApp</a></li>
			<li><a href="mailto:<?php echo esc_attr( Customizer::get( 'email' ) ); ?>"><?php echo esc_html( Customizer::get( 'email' ) ); ?></a></li>
		</ul>
		<ul class="mkae-topbar__meta">
			<li><?php echo esc_html( Customizer::get( 'hours' ) ); ?></li>
			<li class="mkae-topbar__delivery"><?php echo esc_html( Customizer::get( 'delivery_note' ) ); ?></li>
			<li><?php echo esc_html( Customizer::get( 'location' ) ); ?></li>
		</ul>
	</div>
</div>

<header class="mkae-header" data-mkae-sticky>
	<div class="mkae-container mkae-header__inner">
		<div class="mkae-header__brand">
			<button class="mkae-header__burger" aria-label="<?php esc_attr_e( 'Open menu', 'mk-auto-elite' ); ?>" aria-expanded="false" data-mkae-menu-toggle>
				<span></span><span></span><span></span>
			</button>
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="mkae-header__logo-text" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>

		<div class="mkae-header__search">
			<?php get_search_form(); ?>
		</div>

		<nav class="mkae-header__tools" aria-label="<?php esc_attr_e( 'Account and cart', 'mk-auto-elite' ); ?>">
			<a class="mkae-tool" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ); ?>">
				<span class="mkae-tool__label"><?php esc_html_e( 'Account', 'mk-auto-elite' ); ?></span>
			</a>
			<a class="mkae-tool" href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>"><span class="mkae-tool__label"><?php esc_html_e( 'Wishlist', 'mk-auto-elite' ); ?></span></a>
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="mkae-tool mkae-tool--cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" data-mkae-cart>
					<span class="mkae-tool__label"><?php esc_html_e( 'Cart', 'mk-auto-elite' ); ?></span>
					<span class="mkae-tool__count"><?php echo esc_html( (string) ( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ) ); ?></span>
				</a>
			<?php endif; ?>
		</nav>
	</div>

	<nav class="mkae-nav" aria-label="<?php esc_attr_e( 'Primary', 'mk-auto-elite' ); ?>" data-mkae-nav>
		<div class="mkae-container mkae-nav__inner">
			<?php $mkae_catmenu = class_exists( 'MKAutoElite\Woo\WooCommerce' ) ? WooCommerce::category_menu() : ''; ?>
			<?php if ( $mkae_catmenu ) : ?>
				<div class="mkae-catmenu" data-mkae-catmenu>
					<button type="button" class="mkae-catmenu__toggle" aria-expanded="false" data-mkae-catmenu-toggle>
						<span class="mkae-catmenu__bars" aria-hidden="true"></span>
						<?php esc_html_e( 'Shop by Category', 'mk-auto-elite' ); ?>
					</button>
					<div class="mkae-catmenu__panel">
						<?php echo wp_kses_post( $mkae_catmenu ); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'mkae-nav__menu',
					'fallback_cb'    => '__return_empty_string',
					'depth'          => 3,
				)
			);
			?>
			<ul class="mkae-nav__utility">
				<li><a href="<?php echo esc_url( home_url( '/book-a-service/' ) ); ?>"><?php esc_html_e( 'Book a Service', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ); ?>"><?php esc_html_e( 'My Account', 'mk-auto-elite' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>"><?php esc_html_e( 'Wishlist', 'mk-auto-elite' ); ?></a></li>
			</ul>
		</div>
	</nav>
</header>

<div id="content" class="mkae-site-content">
