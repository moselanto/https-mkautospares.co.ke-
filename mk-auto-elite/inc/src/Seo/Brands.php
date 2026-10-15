<?php
/**
 * Brand URL consolidation (added 2.19.6).
 *
 * The site has two brand taxonomies tagging the same products:
 *  - product_brand (WooCommerce Brands, /brand/<slug>/), plain archives.
 *  - mk_make (theme Make hubs, /brands/<slug>/), with headings and guides.
 * Every mk_make slug has an identical product_brand term, so the pairs were
 * duplicate pages competing in search. This module:
 *  - 301-redirects /brand/<slug>/ to /brands/<slug>/ when a Make exists,
 *    keeping the page number and query string.
 *  - Noindexes the remaining product_brand archives (part brands such as
 *    Febest or Koito, 1-17 products each) while keeping them usable.
 *  - Redirects the bare /brands/ and /brand/ paths (404s) to the home page
 *    "shop by make" section.
 *  - Asks Rank Math to include Make hubs in the XML sitemap.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consolidates /brand/ archives into /brands/ Make hubs.
 */
class Brands {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'redirect' ), 5 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'rank_math_robots' ), 25 );
		add_filter( 'wp_robots', array( $this, 'core_robots' ), 25 );
		add_filter( 'rank_math/sitemap/exclude_taxonomy', array( $this, 'sitemap_taxonomy' ), 20, 2 );
	}

	/**
	 * Matching Make term for the current product_brand archive, if any.
	 *
	 * @return \WP_Term|null
	 */
	private static function matching_make(): ?\WP_Term {
		if ( ! is_tax( 'product_brand' ) || ! taxonomy_exists( 'mk_make' ) ) {
			return null;
		}
		$brand = get_queried_object();
		if ( ! $brand instanceof \WP_Term ) {
			return null;
		}
		$make = get_term_by( 'slug', $brand->slug, 'mk_make' );
		return ( $make instanceof \WP_Term ) ? $make : null;
	}

	/**
	 * Redirect duplicate brand archives and the bare brand paths.
	 */
	public function redirect(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$make = self::matching_make();
		if ( $make ) {
			$url = get_term_link( $make );
			if ( is_wp_error( $url ) ) {
				return;
			}
			$paged = (int) get_query_var( 'paged' );
			if ( $paged > 1 ) {
				$url = trailingslashit( $url ) . 'page/' . $paged . '/';
			}
			$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
			if ( '' !== $query ) {
				$url .= '?' . $query;
			}
			wp_safe_redirect( $url, 301, 'MK Auto Elite' );
			exit;
		}

		if ( is_404() ) {
			$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
			$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
				$path = substr( $path, strlen( $home ) );
			}
			$path = trim( $path, '/' );
			if ( in_array( $path, array( 'brands', 'brand' ), true ) ) {
				wp_safe_redirect( home_url( '/#shop-by-make' ), 301, 'MK Auto Elite' );
				exit;
			}
		}
	}

	/** Is this a part-brand archive with no Make hub? */
	private static function unmatched_brand(): bool {
		return is_tax( 'product_brand' ) && null === self::matching_make();
	}

	/**
	 * Rank Math robots: noindex unmatched part-brand archives.
	 *
	 * @param mixed $robots Robots.
	 * @return mixed
	 */
	public function rank_math_robots( $robots ) {
		if ( is_array( $robots ) && self::unmatched_brand() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
			unset( $robots['max-snippet'], $robots['max-video-preview'], $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Core robots fallback when Rank Math is not active.
	 *
	 * @param array<string,mixed> $robots Robots.
	 * @return array<string,mixed>
	 */
	public function core_robots( $robots ) {
		$rank_math = defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath' );
		if ( ! $rank_math && self::unmatched_brand() ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Include Make hubs in Rank Math's sitemap; keep product_brand out.
	 *
	 * @param bool   $exclude  Rank Math's decision.
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	public function sitemap_taxonomy( $exclude, $taxonomy ) {
		if ( 'mk_make' === $taxonomy ) {
			return false;
		}
		if ( 'product_brand' === $taxonomy ) {
			return true;
		}
		return $exclude;
	}
}
