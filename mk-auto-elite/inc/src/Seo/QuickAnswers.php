<?php
/**
 * Product page "Quick answers" (2.33.0).
 *
 * Why: buyers (and answer engines reading the page) ask the same four things
 * about a part - how much, will it fit, what is the part number, how fast is
 * delivery. Each answer is built only from data that exists on the product
 * (price, stock, OEM number, fitment taxonomies / meta, condition) plus the
 * Customizer delivery settings already used by Seo\Answers and the Shipping
 * page. A question whose data is missing is skipped, never guessed.
 *
 * Visible HTML only. No FAQPage schema: product pages already carry Product
 * markup and FAQ rich results are not shown for shops, so extra markup would
 * be duplicate or ineligible.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

use MKAutoElite\Customizer;
use MKAutoElite\Woo\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and renders the product Quick answers block.
 */
class QuickAnswers {

	/** Max vehicles named in the fitment answer. */
	private const MAX_VEHICLES = 6;

	/**
	 * Hook in.
	 */
	public function __construct() {
		// After the description / compatibility tabs (10), before upsells (15).
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render' ), 12 );
	}

	/**
	 * Format a shilling amount.
	 *
	 * @param float $amount Amount.
	 */
	private static function ksh( float $amount ): string {
		return 'KSh ' . number_format_i18n( $amount, 0 );
	}

	/**
	 * Human list: "a, b and c".
	 *
	 * @param string[] $items Items.
	 */
	private static function and_list( array $items ): string {
		$items = array_values( array_filter( array_map( 'strval', $items ) ) );
		if ( count( $items ) < 2 ) {
			return implode( '', $items );
		}
		$last = array_pop( $items );
		/* translators: 1: comma list, 2: last item. */
		return sprintf( __( '%1$s and %2$s', 'mk-auto-elite' ), implode( ', ', $items ), $last );
	}

	/**
	 * Vehicles the product is listed for (model terms, else make/model meta).
	 *
	 * @param int $id Product ID.
	 * @return string[]
	 */
	private static function vehicles( int $id ): array {
		$make  = '';
		$makes = taxonomy_exists( 'mk_make' ) ? wp_get_post_terms( $id, 'mk_make', array( 'fields' => 'names' ) ) : array();
		if ( ! is_wp_error( $makes ) && $makes ) {
			$make = (string) $makes[0];
		} else {
			$make = trim( (string) get_post_meta( $id, '_mk_make', true ) );
		}
		$out    = array();
		$models = taxonomy_exists( 'mk_model' ) ? wp_get_post_terms( $id, 'mk_model', array( 'fields' => 'names' ) ) : array();
		if ( ! is_wp_error( $models ) ) {
			foreach ( array_slice( (array) $models, 0, self::MAX_VEHICLES ) as $model ) {
				$model = (string) $model;
				$out[] = ( '' !== $make && 0 !== stripos( $model, $make ) ) ? $make . ' ' . $model : $model;
			}
		}
		if ( ! $out ) {
			$model = trim( (string) get_post_meta( $id, '_mk_model', true ) );
			if ( '' !== $model ) {
				$out[] = trim( ( 0 !== stripos( $model, $make ) ? $make . ' ' : '' ) . $model );
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Recorded condition label, or ''. Explicit data only.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function condition( \WC_Product $product ): string {
		$raw = trim(
			(string) ( get_post_meta( $product->get_id(), '_mk_condition', true )
			?: $product->get_attribute( 'pa_condition' )
			?: $product->get_attribute( 'condition' ) )
		);
		return $raw;
	}

	/**
	 * Questions and answers for a product (plain text).
	 *
	 * @param \WC_Product $product Product.
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function faqs( \WC_Product $product ): array {
		$id    = $product->get_id();
		$name  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $product->get_name() ) ) );
		$phone = Customizer::get( 'phone' );
		$faqs  = array();

		// 1. Price and stock (only when the part has a real price).
		$price = (float) wc_get_price_to_display( $product );
		if ( '' !== (string) $product->get_price() && $price > 0 ) {
			if ( $product->is_on_backorder() ) {
				$stock = __( 'It is available on backorder; we confirm the delivery date on WhatsApp.', 'mk-auto-elite' );
			} elseif ( $product->is_in_stock() ) {
				$stock = __( 'It is in stock.', 'mk-auto-elite' );
			} else {
				/* translators: %s: phone. */
				$stock = sprintf( __( 'It is currently out of stock; message us on WhatsApp (%s) and we check when it will be back or offer an alternative.', 'mk-auto-elite' ), $phone );
			}
			$faqs[] = array(
				/* translators: %s: product name. */
				sprintf( __( 'How much is the %s in Kenya?', 'mk-auto-elite' ), $name ),
				/* translators: 1: price, 2: stock sentence. */
				sprintf( __( 'The current website price is %1$s. %2$s', 'mk-auto-elite' ), self::ksh( $price ), $stock ),
			);
		}

		// 2. Fitment (only when vehicles are recorded).
		$vehicles = self::vehicles( $id );
		if ( $vehicles ) {
			$years  = WooCommerce::year_range( $id );
			$engine = trim( (string) get_post_meta( $id, '_mk_engine', true ) );
			/* translators: %s: list of vehicles. */
			$answer = sprintf( __( 'This part is listed for the %s', 'mk-auto-elite' ), self::and_list( $vehicles ) );
			if ( '' !== $years ) {
				/* translators: %s: years. */
				$answer .= sprintf( __( ' (%s)', 'mk-auto-elite' ), $years );
			}
			if ( '' !== $engine ) {
				/* translators: %s: engine code. */
				$answer .= sprintf( __( ', engine %s', 'mk-auto-elite' ), $engine );
			}
			/* translators: %s: phone. */
			$answer .= '. ' . sprintf( __( 'Parts can change between generations and facelifts of the same model, so send your chassis number or a photo of the old part on WhatsApp (%s) and we confirm it fits before you pay.', 'mk-auto-elite' ), $phone );
			$faqs[]  = array(
				/* translators: %s: first vehicle. */
				sprintf( __( 'Will it fit my %s?', 'mk-auto-elite' ), $vehicles[0] ),
				$answer,
			);
		}

		// 3. Part number (only when an OEM number is recorded).
		$oem = trim( (string) get_post_meta( $id, '_mk_oem', true ) );
		if ( '' !== $oem ) {
			/* translators: %s: OEM number. */
			$answer = sprintf( __( 'The OEM part number is %s. Compare it with the number stamped or printed on your old part.', 'mk-auto-elite' ), $oem );
			$sku    = trim( (string) $product->get_sku() );
			if ( '' !== $sku && 0 !== strcasecmp( $sku, $oem ) ) {
				/* translators: %s: SKU. */
				$answer .= ' ' . sprintf( __( 'Our stock code is %s; quote it when you order on WhatsApp.', 'mk-auto-elite' ), $sku );
			}
			$faqs[] = array( __( 'What is the part number?', 'mk-auto-elite' ), $answer );
		}

		// 4. Condition (only when recorded on the product).
		$condition = self::condition( $product );
		if ( '' !== $condition ) {
			$faqs[] = array(
				__( 'Is this part new or used?', 'mk-auto-elite' ),
				/* translators: %s: condition, e.g. New, Ex-Japan. */
				sprintf( __( 'Condition: %s, as recorded for this listing.', 'mk-auto-elite' ), $condition ),
			);
		}

		// 5. Delivery (Customizer settings, same figures as the Shipping page).
		$fee      = absint( Customizer::get( 'delivery_fee' ) );
		$free     = absint( Customizer::get( 'free_delivery_from' ) );
		$delivery = __( 'We deliver across Kenya by courier and parcel services. Nairobi orders usually arrive in 1-2 business days, major towns in 1-3 and other areas in 2-5 business days after dispatch.', 'mk-auto-elite' );
		if ( $fee > 0 ) {
			if ( $free > 0 && $price >= $free ) {
				/* translators: %s: free-delivery threshold. */
				$delivery .= ' ' . sprintf( __( 'Delivery is free on orders of %s or more, so this part qualifies on its own.', 'mk-auto-elite' ), self::ksh( $free ) );
			} elseif ( $free > 0 ) {
				/* translators: 1: fee, 2: free-delivery threshold. */
				$delivery .= ' ' . sprintf( __( 'Delivery costs %1$s per order and is free on orders of %2$s or more.', 'mk-auto-elite' ), self::ksh( $fee ), self::ksh( $free ) );
			} else {
				/* translators: %s: fee. */
				$delivery .= ' ' . sprintf( __( 'Delivery costs %s per order.', 'mk-auto-elite' ), self::ksh( $fee ) );
			}
		}
		/* translators: %s: shop location. */
		$delivery .= ' ' . sprintf( __( 'Pickup from our shop on %s is free.', 'mk-auto-elite' ), Customizer::get( 'location' ) );
		$faqs[]    = array( __( 'How fast is delivery?', 'mk-auto-elite' ), $delivery );

		return array_slice( (array) apply_filters( 'mkae_product_quick_answers', $faqs, $product ), 0, 5 );
	}

	/** Render the block on single product pages. */
	public function render(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() || post_password_required() ) {
			return;
		}
		$product = wc_get_product( get_the_ID() );
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$faqs = self::faqs( $product );
		if ( ! $faqs ) {
			return;
		}
		echo '<section class="mkae-qa" aria-labelledby="mkae-qa-title"><h2 class="mkae-qa__title" id="mkae-qa-title">' . esc_html__( 'Quick answers', 'mk-auto-elite' ) . '</h2><dl class="mkae-qa__list">';
		foreach ( $faqs as $faq ) {
			printf(
				'<div class="mkae-qa__item"><dt class="mkae-qa__q">%s</dt><dd class="mkae-qa__a">%s</dd></div>',
				esc_html( $faq[0] ),
				esc_html( $faq[1] )
			);
		}
		echo '</dl></section>';
	}
}
