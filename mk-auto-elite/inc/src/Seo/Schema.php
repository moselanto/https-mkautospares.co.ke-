<?php
/**
 * JSON-LD structured data for SEO and Google Merchant Center.
 * Outputs Organization + LocalBusiness, WebSite, Product, Breadcrumb and FAQ.
 * Self-contained so no SEO plugin is required.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and prints a schema.org @graph in the document head.
 */
class Schema {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/**
	 * Print the JSON-LD graph.
	 */
	public function output(): void {
		$graph = array_values(
			array_filter(
				array(
					$this->organization(),
					$this->website(),
					$this->product(),
					$this->faq(),
					$this->breadcrumb(),
				)
			)
		);
		if ( empty( $graph ) ) {
			return;
		}
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Organization + Store (LocalBusiness).
	 *
	 * @return array<string,mixed>
	 */
	private function organization(): array {
		$logo = '';
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			$logo = $src ? $src[0] : '';
		}

		$node = array(
			'@type'       => array( 'Organization', 'Store' ),
			'@id'         => home_url( '/#organization' ),
			'name'        => get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'telephone'   => Customizer::get( 'phone' ),
			'email'       => Customizer::get( 'email' ),
			'address'     => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => Customizer::get( 'location' ),
				'addressCountry'  => 'KE',
			),
			'areaServed'  => 'KE',
			'openingHours' => 'Mo-Sa 08:00-18:00',
		);
		if ( $logo ) {
			$node['logo']  = $logo;
			$node['image'] = $logo;
		}
		return $node;
	}

	/**
	 * WebSite + site search action.
	 *
	 * @return array<string,mixed>
	 */
	private function website(): array {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'publisher'       => array( '@id' => home_url( '/#organization' ) ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Product schema on single product pages.
	 *
	 * @return array<string,mixed>|null
	 */
	private function product(): ?array {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return null;
		}
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		$id    = $product->get_id();
		$make  = (string) get_post_meta( $id, '_mk_make', true );
		$oem   = (string) get_post_meta( $id, '_mk_oem', true );
		$price = $product->get_price();

		$offer = array(
			'@type'         => 'Offer',
			'url'           => get_permalink( $id ),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
		);
		if ( '' !== $price && null !== $price && (float) $price > 0 ) {
			$offer['price'] = wc_format_decimal( $price, wc_get_price_decimals() );
		}

		$img = wp_get_attachment_url( $product->get_image_id() );

		$node = array(
			'@type'       => 'Product',
			'@id'         => get_permalink( $id ) . '#product',
			'name'        => wp_strip_all_tags( $product->get_name() ),
			'description' => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_name() ),
			'sku'         => $product->get_sku() ? $product->get_sku() : ( 'MK-' . $id ),
			'brand'       => array(
				'@type' => 'Brand',
				'name'  => $make ? $make : get_bloginfo( 'name' ),
			),
			'offers'      => $offer,
		);
		if ( $img ) {
			$node['image'] = $img;
		}
		if ( $oem ) {
			$node['mpn'] = $oem;
		}

		$rating_count = (int) $product->get_rating_count();
		if ( $rating_count > 0 ) {
			$node['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (string) $product->get_review_count(),
			);
			$reviews = get_comments(
				array(
					'post_id' => $id,
					'status'  => 'approve',
					'type'    => 'review',
					'number'  => 3,
				)
			);
			$items = array();
			foreach ( $reviews as $review ) {
				$stars = get_comment_meta( $review->comment_ID, 'rating', true );
				if ( '' === $stars ) {
					continue;
				}
				$items[] = array(
					'@type'         => 'Review',
					'reviewRating'  => array(
						'@type'       => 'Rating',
						'ratingValue' => (string) $stars,
						'bestRating'  => '5',
					),
					'author'        => array(
						'@type' => 'Person',
						'name'  => $review->comment_author ? $review->comment_author : __( 'Verified buyer', 'mk-auto-elite' ),
					),
					'reviewBody'    => wp_strip_all_tags( $review->comment_content ),
					'datePublished' => mysql2date( 'Y-m-d', $review->comment_date ),
				);
			}
			if ( ! empty( $items ) ) {
				$node['review'] = $items;
			}
		}

		return $node;
	}

	/**
	 * FAQ schema on the front page (mirrors the homepage accordion).
	 *
	 * @return array<string,mixed>|null
	 */
	private function faq(): ?array {
		$faqs = array();
		if ( is_front_page() ) {
			$faqs = array(
				array( __( 'How do I find the right part for my car?', 'mk-auto-elite' ), __( 'Use the vehicle finder: select your make, model and year, then browse compatible parts.', 'mk-auto-elite' ) ),
				array( __( 'Can I order on WhatsApp?', 'mk-auto-elite' ), __( 'Yes. Every product has a WhatsApp button that pre-fills the part details so we can confirm price and availability fast.', 'mk-auto-elite' ) ),
				array( __( 'Do you deliver countrywide?', 'mk-auto-elite' ), __( 'Yes, we deliver across Kenya through trusted couriers.', 'mk-auto-elite' ) ),
			);
		} elseif ( is_singular( 'mkae_landing' ) ) {
			$raw = (string) get_post_meta( get_the_ID(), '_mkae_l_faq', true );
			foreach ( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) as $line ) {
				$pair = explode( '|', $line, 2 );
				if ( count( $pair ) === 2 ) {
					$faqs[] = array( trim( $pair[0] ), trim( $pair[1] ) );
				}
			}
		}
		if ( empty( $faqs ) ) {
			return null;
		}
		$items = array();
		foreach ( $faqs as $faq ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => $faq[0],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $faq[1],
				),
			);
		}
		return array(
			'@type'      => 'FAQPage',
			'@id'        => ( is_front_page() ? home_url( '/#faq' ) : get_permalink() . '#faq' ),
			'mainEntity' => $items,
		);
	}

	/**
	 * Breadcrumb for products, product categories and pages.
	 *
	 * @return array<string,mixed>|null
	 */
	private function breadcrumb(): ?array {
		if ( is_front_page() ) {
			return null;
		}
		$crumbs = array(
			array( get_bloginfo( 'name' ), home_url( '/' ) ),
		);
		if ( is_singular() ) {
			$crumbs[] = array( wp_strip_all_tags( get_the_title() ), get_permalink() );
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name ) ) {
				$crumbs[] = array( $term->name, get_term_link( $term ) );
			}
		} else {
			return null;
		}

		$items = array();
		foreach ( $crumbs as $i => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb[0],
				'item'     => $crumb[1],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}
}
