<?php
/**
 * JSON-LD structured data for SEO and Google Merchant Center.
 *
 * Two modes, decided per request:
 *
 *  1. Rank Math active (the live site). Rank Math already prints Organization,
 *     WebSite, WebPage, Product + Offer and BreadcrumbList. Printing our own
 *     copies produced duplicate entities on every page (audit 14 Oct 2026). So
 *     we hook rank_math/json_ld late, ENRICH Rank Math's entities in place
 *     (AutoPartsStore type, full address, hours, geo; correct part brand,
 *     vehicle compatibility, condition, shipping and returns on products) and
 *     record which types it printed. Our own block then prints only what Rank
 *     Math did not (FAQPage, Article on Knowledge Centre guides, anything for
 *     a post type Rank Math has schema switched off for).
 *
 *  2. No Rank Math. We print the full graph ourselves.
 *
 * Either way each entity type appears once per page.
 *
 * Product brand: the vehicle make (_mk_make) is NOT the brand of a part. A
 * Bosch filter for a Mazda is a Bosch product. Brand comes from the WooCommerce
 * product_brand taxonomy, a pa_brand attribute or _mk_brand meta, and is
 * omitted when unknown rather than guessed. The vehicle goes into
 * isAccessoryOrSparePartFor.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and prints a schema.org @graph, de-duplicated against Rank Math.
 */
class Schema {

	/**
	 * Schema types Rank Math printed on this request (lower-case keys).
	 *
	 * @var array<string,bool>
	 */
	private static array $printed = array();

	/** Max vehicles listed in isAccessoryOrSparePartFor. */
	private const MAX_VEHICLES = 12;

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_filter( 'rank_math/json_ld', array( $this, 'enrich_rank_math' ), 999, 2 );
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/* ------------------------------------------------------------------ */
	/* Rank Math integration                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Enrich Rank Math's entities and remember which types it prints.
	 *
	 * @param mixed $data   Rank Math entity map.
	 * @param mixed $jsonld Rank Math JsonLD instance (unused).
	 * @return mixed
	 */
	public function enrich_rank_math( $data, $jsonld = null ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $key => $entity ) {
			if ( ! is_array( $entity ) || empty( $entity['@type'] ) ) {
				continue;
			}
			$types = array_map( 'strtolower', (array) $entity['@type'] );

			if ( array_intersect( $types, array( 'organization', 'store', 'localbusiness', 'autopartsstore' ) ) ) {
				$data[ $key ] = $this->merge_business( $entity );
			} elseif ( in_array( 'product', $types, true ) && function_exists( 'is_product' ) && is_product() ) {
				$data[ $key ] = $this->merge_product( $entity );
			}

			foreach ( (array) $data[ $key ]['@type'] as $type ) {
				self::$printed[ strtolower( (string) $type ) ] = true;
			}
		}
		return $data;
	}

	/**
	 * Has Rank Math (or anything before us) already printed this type?
	 *
	 * @param string ...$types Types to check.
	 */
	private static function printed( string ...$types ): bool {
		foreach ( $types as $type ) {
			if ( isset( self::$printed[ strtolower( $type ) ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add local-business detail to Rank Math's Organization without dropping
	 * anything Rank Math set (logo, sameAs, contact points).
	 *
	 * @param array<string,mixed> $entity Organization entity.
	 * @return array<string,mixed>
	 */
	private function merge_business( array $entity ): array {
		$types = array_values( array_unique( array_merge( (array) $entity['@type'], array( 'Organization', 'AutoPartsStore' ) ) ) );
		// Store / LocalBusiness are implied by AutoPartsStore.
		$entity['@type'] = array_values( array_diff( $types, array( 'Store', 'LocalBusiness' ) ) );
		foreach ( $this->business_fields() as $field => $value ) {
			if ( empty( $entity[ $field ] ) ) {
				$entity[ $field ] = $value;
			}
		}
		// Rank Math often only has a locality; replace a thin address.
		if ( empty( $entity['address']['streetAddress'] ) ) {
			$entity['address'] = $this->postal_address();
		}
		return $entity;
	}

	/**
	 * Merge our product facts into Rank Math's Product entity.
	 *
	 * @param array<string,mixed> $entity Product entity.
	 * @return array<string,mixed>
	 */
	private function merge_product( array $entity ): array {
		$product = $this->current_product();
		if ( ! $product ) {
			return $entity;
		}
		$ours = $this->product_facts( $product );

		// Brand: replace Rank Math's when it is missing or is just the shop name.
		$current_brand = is_array( $entity['brand'] ?? null ) ? (string) ( $entity['brand']['name'] ?? '' ) : '';
		if ( isset( $ours['brand'] ) && ( '' === $current_brand || get_bloginfo( 'name' ) === $current_brand ) ) {
			$entity['brand'] = $ours['brand'];
		} elseif ( ! isset( $ours['brand'] ) && get_bloginfo( 'name' ) === $current_brand ) {
			unset( $entity['brand'] );
		}
		foreach ( array( 'mpn', 'itemCondition', 'isAccessoryOrSparePartFor', 'category' ) as $field ) {
			if ( isset( $ours[ $field ] ) && empty( $entity[ $field ] ) ) {
				$entity[ $field ] = $ours[ $field ];
			}
		}

		// Offers: unpriced (price-on-request) items must not carry an Offer with
		// no price or price 0 - that is invalid for Google and misleading.
		if ( null === $ours['price'] ) {
			unset( $entity['offers'] );
			return $entity;
		}
		if ( isset( $entity['offers'] ) && is_array( $entity['offers'] ) ) {
			$offers = isset( $entity['offers']['@type'] ) ? array( $entity['offers'] ) : $entity['offers'];
			foreach ( $offers as $i => $offer ) {
				if ( ! is_array( $offer ) ) {
					continue;
				}
				foreach ( $this->offer_extras( $product ) as $field => $value ) {
					if ( empty( $offer[ $field ] ) ) {
						$offer[ $field ] = $value;
					}
				}
				$offers[ $i ] = $offer;
			}
			$entity['offers'] = isset( $entity['offers']['@type'] ) ? $offers[0] : $offers;
		} else {
			$entity['offers'] = $this->offer( $product, $ours['price'] );
		}
		return $entity;
	}

	/* ------------------------------------------------------------------ */
	/* Own output                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Print the JSON-LD graph (only types nobody printed yet).
	 */
	public function output(): void {
		$nodes = array();

		if ( ! self::printed( 'Organization', 'Store', 'LocalBusiness', 'AutoPartsStore' ) ) {
			$nodes[] = $this->organization();
		}
		if ( ! self::printed( 'WebSite' ) ) {
			$nodes[] = $this->website();
		}
		if ( ! self::printed( 'Product' ) ) {
			$nodes[] = $this->product();
		}
		if ( ! self::printed( 'Article', 'BlogPosting', 'TechArticle', 'NewsArticle' ) ) {
			$nodes[] = $this->article();
		}
		if ( ! self::printed( 'FAQPage' ) ) {
			$nodes[] = $this->faq();
		}
		if ( ! self::printed( 'BreadcrumbList' ) ) {
			$nodes[] = $this->breadcrumb();
		}

		$graph = array_values( array_filter( $nodes ) );
		if ( empty( $graph ) ) {
			return;
		}
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
		echo '<script type="application/ld+json" class="mkae-schema">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
	}

	/* ------------------------------------------------------------------ */
	/* Business                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Structured postal address from the Customizer.
	 *
	 * @return array<string,string>
	 */
	private function postal_address(): array {
		return array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => Customizer::get( 'street_address' ),
				'addressLocality' => Customizer::get( 'city' ),
				'addressRegion'   => Customizer::get( 'region' ),
				'addressCountry'  => 'KE',
			)
		);
	}

	/**
	 * Local-business fields shared by both modes. Only public business data.
	 *
	 * @return array<string,mixed>
	 */
	private function business_fields(): array {
		$fields = array(
			'telephone'          => Customizer::get( 'phone' ),
			'email'              => Customizer::get( 'email' ),
			'address'            => $this->postal_address(),
			'areaServed'         => array(
				'@type' => 'Country',
				'name'  => 'Kenya',
			),
			'openingHours'       => Customizer::get( 'opening_hours' ),
			'currenciesAccepted' => 'KES',
			'paymentAccepted'    => 'M-Pesa, Cash',
		);
		$map = Customizer::get( 'map_link' );
		if ( $map ) {
			$fields['hasMap'] = $map;
		}
		$lat = Customizer::get( 'geo_lat' );
		$lng = Customizer::get( 'geo_lng' );
		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			$fields['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}
		$same_as = array_values( array_filter( Customizer::social_links() ) );
		if ( $same_as ) {
			$fields['sameAs'] = $same_as;
		}
		return array_filter( $fields );
	}

	/**
	 * Organization + AutoPartsStore (fallback when Rank Math is absent).
	 *
	 * @return array<string,mixed>
	 */
	private function organization(): array {
		$node = array(
			'@type' => array( 'Organization', 'AutoPartsStore' ),
			'@id'   => home_url( '/#organization' ),
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $src ) {
				$node['logo']  = $src[0];
				$node['image'] = $src[0];
			}
		}
		return array_merge( $node, $this->business_fields() );
	}

	/**
	 * WebSite + product search action (fallback).
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
				'target'      => home_url( '/?s={search_term_string}&post_type=product' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Product                                                             */
	/* ------------------------------------------------------------------ */

	/** The product on a single-product request, or null. */
	private function current_product(): ?\WC_Product {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return null;
		}
		$product = wc_get_product( get_queried_object_id() );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Facts about a product that matter for search: brand, MPN, condition,
	 * compatible vehicles, category, price (null when on request).
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private function product_facts( \WC_Product $product ): array {
		$id    = $product->get_id();
		$facts = array();

		$brand = $this->part_brand( $product );
		if ( '' !== $brand ) {
			$facts['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$oem = trim( (string) get_post_meta( $id, '_mk_oem', true ) );
		if ( '' !== $oem ) {
			$facts['mpn'] = $oem;
		}

		$condition = $this->condition( $product );
		if ( $condition ) {
			$facts['itemCondition'] = $condition;
		}

		$vehicles = $this->vehicles( $id );
		if ( $vehicles ) {
			$facts['isAccessoryOrSparePartFor'] = $vehicles;
		}

		$cats = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $cats ) && $cats ) {
			$facts['category'] = (string) $cats[0];
		}

		$price          = $product->get_price();
		$facts['price'] = ( '' !== $price && null !== $price && (float) $price > 0 ) ? (string) $price : null;
		return $facts;
	}

	/**
	 * Part manufacturer, never the vehicle make. Empty when unknown.
	 *
	 * @param \WC_Product $product Product.
	 */
	private function part_brand( \WC_Product $product ): string {
		$id = $product->get_id();
		foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = wp_get_post_terms( $id, $tax, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && $terms ) {
					return (string) $terms[0];
				}
			}
		}
		$attr = trim( (string) $product->get_attribute( 'pa_brand' ) );
		if ( '' === $attr ) {
			$attr = trim( (string) $product->get_attribute( 'brand' ) );
		}
		if ( '' !== $attr ) {
			$parts = array_map( 'trim', explode( ',', $attr ) );
			return (string) $parts[0];
		}
		$meta = trim( (string) get_post_meta( $id, '_mk_brand', true ) );
		return (string) apply_filters( 'mkae_schema_part_brand', $meta, $product );
	}

	/**
	 * schema.org condition. Explicit data wins; otherwise "used" / "ex-Japan"
	 * in the product name marks it used. Unknown returns '' (omitted) rather
	 * than claiming every part is new.
	 *
	 * @param \WC_Product $product Product.
	 */
	private function condition( \WC_Product $product ): string {
		$raw = strtolower(
			trim(
				(string) ( get_post_meta( $product->get_id(), '_mk_condition', true )
				?: $product->get_attribute( 'pa_condition' )
				?: $product->get_attribute( 'condition' ) )
			)
		);
		if ( '' === $raw ) {
			$name = strtolower( $product->get_name() );
			if ( preg_match( '/\b(used|ex[\s-]?japan|second[\s-]?hand|pre[\s-]?owned)\b/', $name ) ) {
				$raw = 'used';
			}
		}
		$map = array(
			'new'           => 'https://schema.org/NewCondition',
			'genuine'       => 'https://schema.org/NewCondition',
			'oem'           => 'https://schema.org/NewCondition',
			'aftermarket'   => 'https://schema.org/NewCondition',
			'used'          => 'https://schema.org/UsedCondition',
			'ex-japan'      => 'https://schema.org/UsedCondition',
			'ex japan'      => 'https://schema.org/UsedCondition',
			'refurbished'   => 'https://schema.org/RefurbishedCondition',
			'reconditioned' => 'https://schema.org/RefurbishedCondition',
		);
		foreach ( $map as $needle => $url ) {
			if ( false !== strpos( $raw, $needle ) ) {
				return (string) apply_filters( 'mkae_schema_condition', $url, $product );
			}
		}
		return (string) apply_filters( 'mkae_schema_condition', '', $product );
	}

	/**
	 * Compatible vehicles from the fitment taxonomies / meta.
	 *
	 * @param int $id Product ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function vehicles( int $id ): array {
		$out    = array();
		$models = taxonomy_exists( 'mk_model' ) ? wp_get_post_terms( $id, 'mk_model' ) : array();
		$makes  = taxonomy_exists( 'mk_make' ) ? wp_get_post_terms( $id, 'mk_make', array( 'fields' => 'names' ) ) : array();
		$make   = ( ! is_wp_error( $makes ) && $makes ) ? (string) $makes[0] : trim( (string) get_post_meta( $id, '_mk_make', true ) );

		if ( ! is_wp_error( $models ) ) {
			foreach ( array_slice( (array) $models, 0, self::MAX_VEHICLES ) as $term ) {
				$name = $term->name;
				if ( $make && 0 !== stripos( $name, $make ) ) {
					$name = $make . ' ' . $name;
				}
				$out[] = array_filter(
					array(
						'@type' => 'Vehicle',
						'name'  => $name,
						'brand' => $make ? array(
							'@type' => 'Brand',
							'name'  => $make,
						) : null,
						'url'   => is_wp_error( get_term_link( $term ) ) ? null : get_term_link( $term ),
					)
				);
			}
		}
		if ( ! $out ) {
			$model = trim( (string) get_post_meta( $id, '_mk_model', true ) );
			if ( $make || $model ) {
				$out[] = array_filter(
					array(
						'@type' => 'Vehicle',
						'name'  => trim( $make . ' ' . $model ),
						'brand' => $make ? array(
							'@type' => 'Brand',
							'name'  => $make,
						) : null,
					)
				);
			}
		}
		return $out;
	}

	/**
	 * Offer fields Google Merchant listings ask for, from public settings only.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private function offer_extras( \WC_Product $product ): array {
		$extras = array(
			'seller'                  => array( '@id' => home_url( '/#organization' ) ),
			'shippingDetails'         => array(
				'@type'               => 'OfferShippingDetails',
				'shippingDestination' => array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => 'KE',
				),
			),
		);
		$days = absint( Customizer::get( 'return_days' ) );
		if ( $days > 0 ) {
			$extras['hasMerchantReturnPolicy'] = array(
				'@type'                => 'MerchantReturnPolicy',
				'applicableCountry'    => 'KE',
				'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
				'merchantReturnDays'   => $days,
				'returnMethod'         => 'https://schema.org/ReturnInStore',
			);
			$policy = get_page_by_path( 'returns-refund' );
			if ( $policy ) {
				$extras['hasMerchantReturnPolicy']['merchantReturnLink'] = get_permalink( $policy );
			}
		}
		return (array) apply_filters( 'mkae_schema_offer_extras', $extras, $product );
	}

	/**
	 * Full Offer (fallback mode).
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $price   Price.
	 * @return array<string,mixed>
	 */
	private function offer( \WC_Product $product, string $price ): array {
		$stock = $product->is_on_backorder() ? 'https://schema.org/BackOrder' : ( $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' );
		return array_merge(
			array(
				'@type'         => 'Offer',
				'url'           => get_permalink( $product->get_id() ),
				'priceCurrency' => get_woocommerce_currency(),
				'price'         => wc_format_decimal( $price, wc_get_price_decimals() ),
				'availability'  => $stock,
			),
			$this->offer_extras( $product )
		);
	}

	/**
	 * Product node (fallback mode).
	 *
	 * @return array<string,mixed>|null
	 */
	private function product(): ?array {
		$product = $this->current_product();
		if ( ! $product ) {
			return null;
		}
		$id    = $product->get_id();
		$facts = $this->product_facts( $product );
		$desc  = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
		$desc  = wp_trim_words( wp_strip_all_tags( (string) $desc ), 60, '' );

		$node = array(
			'@type'       => 'Product',
			'@id'         => get_permalink( $id ) . '#product',
			'name'        => wp_strip_all_tags( $product->get_name() ),
			'description' => '' !== $desc ? $desc : wp_strip_all_tags( $product->get_name() ),
			'sku'         => $product->get_sku() ? $product->get_sku() : ( 'MK-' . $id ),
			'url'         => get_permalink( $id ),
		);
		foreach ( array( 'brand', 'mpn', 'itemCondition', 'isAccessoryOrSparePartFor', 'category' ) as $field ) {
			if ( isset( $facts[ $field ] ) ) {
				$node[ $field ] = $facts[ $field ];
			}
		}
		$img = wp_get_attachment_url( $product->get_image_id() );
		if ( $img ) {
			$node['image'] = $img;
		}
		if ( null !== $facts['price'] ) {
			$node['offers'] = $this->offer( $product, $facts['price'] );
		}

		$rating_count = (int) $product->get_rating_count();
		if ( $rating_count > 0 ) {
			$node['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (string) $product->get_review_count(),
			);
		}
		return $node;
	}

	/* ------------------------------------------------------------------ */
	/* Content                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Article for Knowledge Centre guides (Rank Math prints none for this
	 * post type on the live site).
	 *
	 * @return array<string,mixed>|null
	 */
	private function article(): ?array {
		if ( ! is_singular( 'mkae_guide' ) ) {
			return null;
		}
		$id   = get_queried_object_id();
		$node = array(
			'@type'            => 'Article',
			'@id'              => get_permalink( $id ) . '#article',
			'headline'         => wp_strip_all_tags( get_the_title( $id ) ),
			'description'      => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $id ) ), 40, '' ),
			'url'              => get_permalink( $id ),
			'mainEntityOfPage' => get_permalink( $id ),
			'datePublished'    => get_the_date( 'c', $id ),
			'dateModified'     => get_the_modified_date( 'c', $id ),
			'author'           => array( '@id' => home_url( '/#organization' ) ),
			'publisher'        => array( '@id' => home_url( '/#organization' ) ),
			'inLanguage'       => get_bloginfo( 'language' ),
		);
		$img = get_the_post_thumbnail_url( $id, 'full' );
		if ( $img ) {
			$node['image'] = $img;
		}
		$topics = wp_get_post_terms( $id, 'mkae_guide_topic', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $topics ) && $topics ) {
			$node['articleSection'] = (string) $topics[0];
		}
		return $node;
	}

	/**
	 * FAQ schema on the front page (mirrors the homepage accordion) and on
	 * fitment landing pages (from the FAQ meta).
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
			$raw = (string) get_post_meta( get_queried_object_id(), '_mkae_l_faq', true );
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
				'name'           => wp_strip_all_tags( $faq[0] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $faq[1] ),
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
	 * Breadcrumb (fallback mode only; Rank Math prints a fuller one).
	 * Home > Shop > Category > Product, Home > Knowledge Centre > Guide.
	 *
	 * @return array<string,mixed>|null
	 */
	private function breadcrumb(): ?array {
		if ( is_front_page() || is_404() || is_search() ) {
			return null;
		}
		$crumbs = array( array( __( 'Home', 'mk-auto-elite' ), home_url( '/' ) ) );

		if ( function_exists( 'is_product' ) && is_product() ) {
			$shop = wc_get_page_permalink( 'shop' );
			if ( $shop ) {
				$crumbs[] = array( __( 'Shop', 'mk-auto-elite' ), $shop );
			}
			$cats = wp_get_post_terms( get_queried_object_id(), 'product_cat' );
			if ( ! is_wp_error( $cats ) && $cats ) {
				$crumbs[] = array( $cats[0]->name, get_term_link( $cats[0] ) );
			}
			$crumbs[] = array( wp_strip_all_tags( get_the_title() ), get_permalink() );
		} elseif ( is_singular() ) {
			$type = get_post_type();
			if ( $type && 'page' !== $type && 'post' !== $type ) {
				$archive = get_post_type_archive_link( $type );
				$obj     = get_post_type_object( $type );
				if ( $archive && $obj ) {
					$crumbs[] = array( $obj->labels->name, $archive );
				}
			}
			$crumbs[] = array( wp_strip_all_tags( get_the_title() ), get_permalink() );
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$crumbs[] = array( $term->name, $link );
				}
			}
		} elseif ( is_post_type_archive() ) {
			$crumbs[] = array( post_type_archive_title( '', false ), get_post_type_archive_link( get_query_var( 'post_type' ) ) );
		} else {
			return null;
		}

		$items = array();
		foreach ( $crumbs as $i => $crumb ) {
			if ( ! is_string( $crumb[1] ) || '' === $crumb[1] ) {
				continue;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
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
