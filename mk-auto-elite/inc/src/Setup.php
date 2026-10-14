<?php
/**
 * Theme setup: supports, menus, image sizes, widgets, i18n.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers core theme features.
 */
class Setup {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_action( 'after_setup_theme', array( $this, 'content_width' ), 0 );
		add_action( 'init', array( $this, 'register_menus' ) );
		add_action( 'after_setup_theme', array( $this, 'image_sizes' ) );
		add_action( 'widgets_init', array( $this, 'widget_areas' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Declare theme support.
	 */
	public function theme_supports(): void {
		load_theme_textdomain( 'mk-auto-elite', MK_AUTO_ELITE_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 64,
				'width'       => 260,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);

		// WooCommerce support + product gallery enhancements.
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}

	/**
	 * Global content width.
	 */
	public function content_width(): void {
		if ( ! isset( $GLOBALS['content_width'] ) ) {
			$GLOBALS['content_width'] = 1280;
		}
	}

	/**
	 * Navigation locations.
	 */
	public function register_menus(): void {
		register_nav_menus(
			array(
				'primary'   => __( 'Primary Menu', 'mk-auto-elite' ),
				'mega'      => __( 'Mega Menu (Shop by Category)', 'mk-auto-elite' ),
				'utility'   => __( 'Top Utility Menu', 'mk-auto-elite' ),
				'footer_1'  => __( 'Footer: Company', 'mk-auto-elite' ),
				'footer_2'  => __( 'Footer: Customer Support', 'mk-auto-elite' ),
				'footer_3'  => __( 'Footer: Policies', 'mk-auto-elite' ),
			)
		);
	}

	/**
	 * Responsive image sizes tuned for automotive product cards.
	 */
	public function image_sizes(): void {
		add_image_size( 'mkae-card', 480, 480, true );
		add_image_size( 'mkae-card-2x', 960, 960, true );
		add_image_size( 'mkae-hero', 1600, 720, true );
		add_image_size( 'mkae-brand', 200, 120, false );
	}

	/**
	 * Widgetized areas.
	 */
	public function widget_areas(): void {
		$defaults = array(
			'before_widget' => '<section id="%1$s" class="mkae-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="mkae-widget__title">',
			'after_title'   => '</h3>',
		);

		register_sidebar( array_merge( $defaults, array(
			'name' => __( 'Shop Sidebar', 'mk-auto-elite' ),
			'id'   => 'mkae-shop',
		) ) );

		for ( $i = 1; $i <= 4; $i++ ) {
			register_sidebar( array_merge( $defaults, array(
				/* translators: %d: footer column number. */
				'name' => sprintf( __( 'Footer Column %d', 'mk-auto-elite' ), $i ),
				'id'   => 'mkae-footer-' . $i,
			) ) );
		}
	}

	/**
	 * Register theme shortcodes for use in page content (e.g. the Contact page).
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'mkae_directions', array( $this, 'shortcode_directions' ) );
		add_shortcode( 'mkae_mpesa', array( $this, 'shortcode_mpesa' ) );
	}

	/**
	 * [mkae_directions] -> a "Get directions" link built from the Customizer
	 * Google Maps link. Outputs nothing until the link is set, so it never
	 * renders a broken button.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 */
	public function shortcode_directions( $atts ): string {
		$atts = shortcode_atts( array( 'label' => __( 'Get directions', 'mk-auto-elite' ) ), (array) $atts, 'mkae_directions' );
		$link = Customizer::get( 'map_link', '' );
		if ( '' === $link ) {
			return '';
		}
		return sprintf(
			'<a class="mkae-btn mkae-btn--primary mkae-directions" href="%s" target="_blank" rel="noopener"><span aria-hidden="true">&#128205;</span> %s</a>',
			esc_url( $link ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [mkae_mpesa] -> the M-Pesa pay instruction from the Customizer.
	 */
	public function shortcode_mpesa(): string {
		return esc_html( Customizer::get( 'mpesa', '' ) );
	}
}
