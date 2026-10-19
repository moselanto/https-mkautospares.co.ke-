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

		// Brand: replace Rank Math's when it is missing, is just the shop name,
		// or is actually the vehicle make (a Lexus tie rod end made by 555 is a
		// 555 product, not a Lexus one). Never guess a brand we cannot prove.
		$current_brand = is_array( $entity['brand'] ?? null ) ? (string) ( $entity['brand']['name'] ?? '' ) : '';
		$is_bad_brand  = '' === $current_brand
			|| 0 === strcasecmp( (string) get_bloginfo( 'name' ), $current_brand )
			|| $this->is_vehicle_make( $product, $current_brand );
		if ( $is_bad_brand ) {
			if ( isset( $ours['brand']['name'] ) && ! $this->is_vehicle_make( $product, (string) $ours['brand']['name'] ) ) {
				$entity['brand'] = $ours['brand'];
			} else {
				unset( $entity['brand'] );
			}
		}
		foreach ( array( 'mpn', 'gtin', 'additionalProperty', 'itemCondition', 'isAccessoryOrSparePartFor', 'category' ) as $field ) {
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
					// Seller always points at the single site Organization by @id.
					// Seller and condition always come from us so the Offer agrees
					// with the Product (Rank Math defaults offers to NewCondition).
					if ( 'seller' === $field || 'itemCondition' === $field || empty( $offer[ $field ] ) ) {
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
		if ( ! self::printed( 'ItemList' ) ) {
			$nodes[] = $this->item_list();
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
		$phone = Customizer::get( 'phone' );
		if ( '' !== $phone ) {
			$fields['contactPoint'] = array(
				'@type'             => 'ContactPoint',
				'contactType'       => 'sales',
				'telephone'         => $phone,
				'areaServed'        => 'KE',
				'availableLanguage' => array( 'English', 'Swahili' ),
			);
		}
		$fields['description'] = sprintf(
			/* translators: 1: brand, 2: location. */
			__( '%1$s sells genuine, OEM, aftermarket and ex-Japan car, truck and motorcycle spare parts from %2$s, with fitment checks on WhatsApp, M-Pesa payment and delivery across Kenya.', 'mk-auto-elite' ),
			Customizer::get( 'brand_short' ),
			Customizer::get( 'location' )
		);
		$cats = Answers::top_terms( 'product_cat', 12 );
		if ( $cats ) {
			$fields['knowsAbout'] = array_values( array_map( 'strval', array_keys( $cats ) ) );
			$catalog              = array();
			foreach ( $cats as $name => $row ) {
				$catalog[] = array(
					'@type' => 'OfferCatalog',
					'name'  => (string) $name,
					'url'   => $row[0],
				);
			}
			$fields['hasOfferCatalog'] = array(
				'@type'           => 'OfferCatalog',
				'name'            => __( 'Car spare parts', 'mk-auto-elite' ),
				'itemListElement' => $catalog,
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

		// GTIN only when WooCommerce holds a structurally valid one (8/12/13/14 digits).
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$gtin = preg_replace( '/\s+/', '', (string) $product->get_global_unique_id() );
			if ( preg_match( '/^(\d{8}|\d{12,14})$/', $gtin ) ) {
				$facts['gtin'] = $gtin;
			}
		}

		// Verified catalogue fields as PropertyValue pairs (OEM number, engine, years).
		$props = array();
		foreach ( array(
			'_mk_oem'    => __( 'OEM part number', 'mk-auto-elite' ),
			'_mk_engine' => __( 'Engine', 'mk-auto-elite' ),
		) as $meta_key => $label ) {
			$value = trim( (string) get_post_meta( $id, $meta_key, true ) );
			if ( '' !== $value ) {
				$props[] = array(
					'@type' => 'PropertyValue',
					'name'  => $label,
					'value' => $value,
				);
			}
		}
		$years = \MKAutoElite\Woo\WooCommerce::year_range( $id );
		if ( '' !== $years ) {
			$props[] = array(
				'@type' => 'PropertyValue',
				'name'  => __( 'Vehicle years', 'mk-auto-elite' ),
				'value' => $years,
			);
		}
		if ( $props ) {
			$facts['additionalProperty'] = $props;
		}

		$price          = $product->get_price();
		$facts['price'] = ( '' !== $price && null !== $price && (float) $price > 0 ) ? (string) $price : null;
		return $facts;
	}

	/**
	 * Is this name one of the product's vehicle makes (mk_make terms or
	 * _mk_make meta)? Stops a vehicle make being published as the part brand.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $name    Brand name to test.
	 */
	private function is_vehicle_make( \WC_Product $product, string $name ): bool {
		$name = trim( $name );
		if ( '' === $name ) {
			return false;
		}
		$id    = $product->get_id();
		$makes = array();
		if ( taxonomy_exists( 'mk_make' ) ) {
			$terms = wp_get_post_terms( $id, 'mk_make', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) ) {
				$makes = array_map( 'strval', (array) $terms );
			}
		}
		$meta = trim( (string) get_post_meta( $id, '_mk_make', true ) );
		if ( '' !== $meta ) {
			$makes = array_merge( $makes, array_map( 'trim', explode( ',', $meta ) ) );
		}
		foreach ( $makes as $make ) {
			if ( '' !== $make && 0 === strcasecmp( $make, $name ) ) {
				return true;
			}
		}
		return false;
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
			$policy = get_page_by_path( 'return-refund' ) ? get_page_by_path( 'return-refund' ) : get_page_by_path( 'returns-refund' );
			if ( $policy ) {
				$extras['hasMerchantReturnPolicy']['merchantReturnLink'] = get_permalink( $policy );
			}
		}
		$condition = $this->condition( $product );
		if ( '' !== $condition ) {
			$extras['itemCondition'] = $condition;
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
		foreach ( array( 'brand', 'mpn', 'gtin', 'additionalProperty', 'itemCondition', 'isAccessoryOrSparePartFor', 'category' ) as $field ) {
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
			// Same text as the visible homepage accordion.
			$faqs = Answers::home_faqs();
		} elseif ( is_tax( array( 'product_cat', 'mk_make', 'mk_model' ) ) ) {
			// Same text as the visible archive FAQ under the product grid.
			$faqs = Answers::archive_faqs();
		} elseif ( is_singular( 'mkae_guide' ) ) {
			// Knowledge Centre guides: questions written as <p><strong>Question?</strong> Answer</p>.
			$html = (string) get_post_field( 'post_content', get_queried_object_id() );
			if ( preg_match_all( '#<p>\s*<strong>([^<]{5,200}\?)</strong>\s*(.+?)</p>#s', $html, $m, PREG_SET_ORDER ) ) {
				foreach ( array_slice( $m, 0, 10 ) as $row ) {
					$faqs[] = array( html_entity_decode( $row[1], ENT_QUOTES ), html_entity_decode( wp_strip_all_tags( $row[2] ), ENT_QUOTES ) );
				}
			}
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
			'@id'        => ( is_front_page() ? home_url( '/#faq' ) : ( is_tax() ? (string) get_term_link( get_queried_object() ) . '#faq' : get_permalink() . '#faq' ) ),
			'mainEntity' => $items,
		);
	}

	/**
	 * ItemList of the products shown on a category, make, model or shop
	 * archive (first page, unfiltered). Helps crawlers and answer engines map
	 * the archive to the parts it contains; it does not claim a rich result.
	 *
	 * @return array<string,mixed>|null
	 */
	private function item_list(): ?array {
		$archive = is_tax( array( 'product_cat', 'mk_make', 'mk_model' ) ) || ( function_exists( 'is_shop' ) && is_shop() && ! is_search() );
		if ( ! $archive || (int) get_query_var( 'paged' ) > 1 || Answers::is_faceted() ) {
			return null;
		}
		global $wp_query;
		$items = array();
		foreach ( array_slice( (array) $wp_query->posts, 0, 30 ) as $post ) {
			if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
				continue;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'url'      => get_permalink( $post ),
				'name'     => wp_strip_all_tags( get_the_title( $post ) ),
			);
		}
		if ( ! $items ) {
			return null;
		}
		return array(
			'@type'           => 'ItemList',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
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
