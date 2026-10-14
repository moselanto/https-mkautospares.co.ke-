<?php
/**
 * SEO content types: Knowledge Centre guides and Location pages, plus a guide
 * topic taxonomy. Powers the silo's editorial + local-SEO layer.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers custom post types + taxonomy and flushes rewrite rules on theme
 * activation so the new URLs resolve without a manual permalink save.
 */
class Content {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_action( 'after_switch_theme', array( $this, 'flush' ), 20 );
	}

	/**
	 * Register the guide topic taxonomy + guide/location post types.
	 *
	 * The taxonomy is registered BEFORE the guide post type on purpose: its
	 * rewrite base (knowledge/topic) is nested under the guide archive slug
	 * (knowledge). Registering the taxonomy first gives its rewrite rules
	 * precedence, so /knowledge/topic/{term}/ resolves instead of 404ing.
	 */
	public function register(): void {
		register_taxonomy(
			'mkae_guide_topic',
			'mkae_guide',
			array(
				'labels'            => $this->labels( __( 'Topic', 'mk-auto-elite' ), __( 'Guide Topics', 'mk-auto-elite' ) ),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'         => 'knowledge/topic',
					'with_front'   => false,
					'hierarchical' => false,
				),
			)
		);

		register_post_type(
			'mkae_guide',
			array(
				'labels'       => $this->labels( __( 'Guide', 'mk-auto-elite' ), __( 'Knowledge Centre', 'mk-auto-elite' ) ),
				'public'       => true,
				'has_archive'  => 'knowledge',
				'menu_icon'    => 'dashicons-book-alt',
				'rewrite'      => array(
					'slug'       => 'knowledge',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			'mkae_location',
			array(
				'labels'       => $this->labels( __( 'Location', 'mk-auto-elite' ), __( 'Locations', 'mk-auto-elite' ) ),
				'public'       => true,
				'has_archive'  => 'locations',
				'menu_icon'    => 'dashicons-location',
				'rewrite'      => array(
					'slug'       => 'spare-parts',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Build a labels array from a singular + plural name.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural / menu label.
	 * @return array<string,string>
	 */
	private function labels( string $singular, string $plural ): array {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: singular label. */
			'add_new_item'  => sprintf( __( 'Add New %s', 'mk-auto-elite' ), $singular ),
			/* translators: %s: singular label. */
			'edit_item'     => sprintf( __( 'Edit %s', 'mk-auto-elite' ), $singular ),
			/* translators: %s: plural label. */
			'search_items'  => sprintf( __( 'Search %s', 'mk-auto-elite' ), $plural ),
			'not_found'     => __( 'None found', 'mk-auto-elite' ),
		);
	}

	/**
	 * Register post types then flush rewrite rules (activation only).
	 */
	public function flush(): void {
		$this->register();
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules once whenever the theme version changes. This covers
	 * the common case where the theme is updated in place (files replaced
	 * without a theme switch), so the /knowledge/, topic, and other custom URLs
	 * resolve automatically without needing a manual Settings > Permalinks save.
	 */
	public function maybe_flush(): void {
		$version = defined( 'MK_AUTO_ELITE_VERSION' ) ? MK_AUTO_ELITE_VERSION : '0';
		if ( get_option( 'mkae_rewrite_version' ) === $version ) {
			return;
		}
		flush_rewrite_rules();
		update_option( 'mkae_rewrite_version', $version );
	}
}
