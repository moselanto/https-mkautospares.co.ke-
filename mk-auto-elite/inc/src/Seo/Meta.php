<?php
/**
 * Meta fallbacks for titles, descriptions, robots and share images.
 *
 * Rank Math owns titles and meta on the live site. This module only fills
 * the gaps found in the 14 Oct 2026 audit, and never overrides anything typed
 * into Rank Math for a specific page or term:
 *
 *  - Product categories had no meta description; shop, Knowledge Centre and
 *    fitment archives used "X Archive - <site name>"; guide descriptions were
 *    cut mid-word. We generate keyword-led, accurate descriptions instead.
 *  - Archive titles were "Shop - <long site name>". We give product category,
 *    vehicle make/model, shop and Knowledge Centre archives purposeful titles.
 *  - /fitment/ and /locations/ archives were indexable while empty ("coming
 *    soon"). They are noindexed until they have published posts.
 *  - Several pages had no og:image. A default share image is added only when
 *    Rank Math found none.
 *
 * Without Rank Math the core document title and wp_robots filters are used.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title, description, robots and OG image fallbacks.
 */
class Meta {

	/** Description length target (Google truncates around 155-160). */
	private const DESC_MAX = 155;

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_title' ), 20 );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_description' ), 20 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'filter_rank_math_robots' ), 20 );
		add_action( 'rank_math/opengraph/facebook/add_additional_images', array( $this, 'default_og_image' ) );

		// Fallbacks when Rank Math is not active.
		add_filter( 'pre_get_document_title', array( $this, 'core_title' ), 20 );
		add_filter( 'wp_robots', array( $this, 'core_robots' ), 20 );
		add_action( 'wp_head', array( $this, 'core_description' ), 2 );
	}

	/** Rank Math present? */
	private static function rank_math(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath' );
	}

	/** Short brand used as the title suffix. */
	private static function brand(): string {
		return Customizer::get( 'brand_short' );
	}

	/* ------------------------------------------------------------ titles */

	/**
	 * Purposeful title for the current request, or '' to leave it alone.
	 */
	private function generated_title(): string {
		$brand = self::brand();

		if ( function_exists( 'is_shop' ) && is_shop() && ! is_search() ) {
			if ( self::has_post_override( (int) wc_get_page_id( 'shop' ), 'rank_math_title' ) ) {
				return '';
			}
			return sprintf( __( 'Buy Car Spare Parts Online in Kenya | %s', 'mk-auto-elite' ), $brand );
		}

		if ( is_tax( array( 'product_cat', 'mk_make', 'mk_model' ) ) ) {
			$term = get_queried_object();
			if ( ! $term instanceof \WP_Term || self::has_term_override( $term, 'rank_math_title' ) ) {
				return '';
			}
			$paged = self::page_suffix();
			if ( 'product_cat' === $term->taxonomy ) {
				return sprintf( __( '%1$s Parts in Kenya%2$s | %3$s', 'mk-auto-elite' ), $term->name, $paged, $brand );
			}
			$name = self::vehicle_name( $term );
			return sprintf( __( '%1$s Spare Parts in Kenya%2$s | %3$s', 'mk-auto-elite' ), $name, $paged, $brand );
		}

		if ( is_post_type_archive( 'mkae_guide' ) ) {
			return sprintf( __( 'Car Parts Guides and Maintenance Tips for Kenya%1$s | %2$s', 'mk-auto-elite' ), self::page_suffix(), $brand );
		}

		if ( is_singular( array( 'mkae_guide', 'mkae_landing', 'mkae_location' ) ) ) {
			$id = get_queried_object_id();
			if ( self::has_post_override( $id, 'rank_math_title' ) ) {
				return '';
			}
			return wp_strip_all_tags( get_the_title( $id ) ) . ' | ' . $brand;
		}

		return '';
	}

	/**
	 * Rank Math title filter.
	 *
	 * @param mixed $title Title.
	 * @return mixed
	 */
	public function filter_title( $title ) {
		$new = $this->generated_title();
		return '' !== $new ? $new : $title;
	}

	/**
	 * Core fallback (no Rank Math).
	 *
	 * @param mixed $title Title.
	 * @return mixed
	 */
	public function core_title( $title ) {
		if ( self::rank_math() ) {
			return $title;
		}
		$new = $this->generated_title();
		return '' !== $new ? $new : $title;
	}

	/* ------------------------------------------------------ descriptions */

	/**
	 * Generated description for the current request, or ''.
	 */
	private function generated_description(): string {
		$brand = self::brand();
		$where = Customizer::get( 'location' );

		if ( function_exists( 'is_shop' ) && is_shop() && ! is_search() ) {
			if ( self::has_post_override( (int) wc_get_page_id( 'shop' ), 'rank_math_description' ) ) {
				return '';
			}
			$count = (int) wp_count_posts( 'product' )->publish;
			return self::trim(
				sprintf(
					/* translators: 1: number of parts, 2: brand, 3: location */
					__( 'Shop %1$s car spare parts online at %2$s, %3$s. Find parts by vehicle, check fitment, pay by M-Pesa or order on WhatsApp. Delivery across Kenya.', 'mk-auto-elite' ),
					number_format_i18n( $count ),
					$brand,
					$where
				)
			);
		}

		if ( is_tax( array( 'product_cat', 'mk_make', 'mk_model' ) ) ) {
			$term = get_queried_object();
			if ( ! $term instanceof \WP_Term || self::has_term_override( $term, 'rank_math_description' ) ) {
				return '';
			}
			$own = trim( wp_strip_all_tags( (string) $term->description ) );
			if ( '' !== $own ) {
				return self::trim( $own );
			}
			if ( 'product_cat' === $term->taxonomy ) {
				return self::trim(
					sprintf(
						/* translators: 1: count, 2: category, 3: brand, 4: location */
						__( '%1$s %2$s parts in stock at %3$s, %4$s. Genuine and quality aftermarket parts, fitment checked for your vehicle. M-Pesa, WhatsApp orders, delivery across Kenya.', 'mk-auto-elite' ),
						number_format_i18n( (int) $term->count ),
						strtolower( $term->name ),
						$brand,
						$where
					)
				);
			}
			return self::trim(
				sprintf(
					/* translators: 1: vehicle, 2: brand, 3: location */
					__( '%1$s spare parts in Kenya: brakes, suspension, filters, engine and electrical parts that fit. Check fitment, order on WhatsApp or pay by M-Pesa at %2$s, %3$s.', 'mk-auto-elite' ),
					self::vehicle_name( $term ),
					$brand,
					$where
				)
			);
		}

		if ( is_post_type_archive( 'mkae_guide' ) ) {
			return self::trim( __( 'Practical car parts guides for Kenyan drivers: genuine vs aftermarket vs ex-Japan, finding parts by chassis number, common faults, servicing and maintenance.', 'mk-auto-elite' ) );
		}

		if ( is_post_type_archive( array( 'mkae_landing', 'mkae_location' ) ) ) {
			return self::trim( sprintf( __( 'Find spare parts by vehicle and part at %1$s, %2$s. Fitment-checked parts, M-Pesa payment and delivery across Kenya.', 'mk-auto-elite' ), $brand, $where ) );
		}

		if ( is_singular( array( 'mkae_guide', 'mkae_landing', 'mkae_location' ) ) ) {
			$id = get_queried_object_id();
			if ( self::has_post_override( $id, 'rank_math_description' ) ) {
				return '';
			}
			$post = get_post( $id );
			$text = $post && '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : ( $post ? $post->post_content : '' );
			return self::trim( wp_strip_all_tags( strip_shortcodes( (string) $text ) ) );
		}

		return '';
	}

	/**
	 * Rank Math description filter. Replaces empty and generic
	 * "Archive - ..." descriptions and mid-word truncations.
	 *
	 * @param mixed $description Description.
	 * @return mixed
	 */
	public function filter_description( $description ) {
		$new = $this->generated_description();
		return '' !== $new ? $new : $description;
	}

	/** Print a description when Rank Math is absent. */
	public function core_description(): void {
		if ( self::rank_math() ) {
			return;
		}
		$desc = $this->generated_description();
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
	}

	/* ------------------------------------------------------------ robots */

	/** Empty fitment / location archive (thin, "coming soon")? */
	private static function empty_archive(): bool {
		if ( ! is_post_type_archive( array( 'mkae_landing', 'mkae_location' ) ) ) {
			return false;
		}
		global $wp_query;
		return empty( $wp_query->found_posts );
	}

	/**
	 * Rank Math robots filter.
	 *
	 * @param mixed $robots Robots map.
	 * @return mixed
	 */
	public function filter_rank_math_robots( $robots ) {
		if ( self::empty_archive() && is_array( $robots ) ) {
			$robots['index'] = 'noindex';
			unset( $robots['max-snippet'], $robots['max-video-preview'], $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Core robots fallback.
	 *
	 * @param array<string,mixed> $robots Robots.
	 * @return array<string,mixed>
	 */
	public function core_robots( $robots ) {
		if ( ! self::rank_math() && self::empty_archive() ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/* ---------------------------------------------------------- og:image */

	/**
	 * Add a default share image only when Rank Math has none for the page.
	 *
	 * @param mixed $image Rank Math OpenGraph Image object.
	 */
	public function default_og_image( $image ): void {
		if ( ! is_object( $image ) || ! method_exists( $image, 'add_image' ) || ! method_exists( $image, 'has_images' ) ) {
			return; // Unknown Rank Math API: do nothing rather than guess.
		}
		if ( $image->has_images() ) {
			return;
		}
		$url = Customizer::get( 'og_image' );
		if ( '' === $url ) {
			$url = MK_AUTO_ELITE_URI . '/assets/images/hero.jpg';
		}
		$image->add_image( esc_url_raw( $url ) );
	}

	/* ----------------------------------------------------------- helpers */

	/** " - Page N" on paginated archives. */
	private static function page_suffix(): string {
		$paged = (int) get_query_var( 'paged' );
		/* translators: %d: page number */
		return $paged > 1 ? sprintf( __( ' - Page %d', 'mk-auto-elite' ), $paged ) : '';
	}

	/**
	 * Model terms often omit the make ("Fielder"); prefix the parent make.
	 *
	 * @param \WP_Term $term Term.
	 */
	private static function vehicle_name( \WP_Term $term ): string {
		$name = $term->name;
		if ( $term->parent ) {
			$parent = get_term( $term->parent, $term->taxonomy );
			if ( $parent instanceof \WP_Term && 0 !== stripos( $name, $parent->name ) ) {
				$name = $parent->name . ' ' . $name;
			}
		}
		return $name;
	}

	/** Has the editor set this Rank Math field on the post? */
	private static function has_post_override( int $id, string $key ): bool {
		return $id > 0 && '' !== trim( (string) get_post_meta( $id, $key, true ) );
	}

	/** Has the editor set this Rank Math field on the term? */
	private static function has_term_override( \WP_Term $term, string $key ): bool {
		return '' !== trim( (string) get_term_meta( $term->term_id, $key, true ) );
	}

	/**
	 * Collapse whitespace and cut at a word boundary, ending cleanly.
	 *
	 * @param string $text Text.
	 */
	private static function trim( string $text ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text ) <= self::DESC_MAX ) {
			return $text;
		}
		$cut  = mb_substr( $text, 0, self::DESC_MAX );
		$stop = max( (int) mb_strrpos( $cut, '. ' ), 0 );
		if ( $stop > 80 ) {
			return mb_substr( $cut, 0, $stop + 1 );
		}
		$space = (int) mb_strrpos( $cut, ' ' );
		return rtrim( mb_substr( $cut, 0, $space > 0 ? $space : self::DESC_MAX ), " ,;:-" ) . '...';
	}
}
