<?php
/**
 * WooCommerce integration: HPOS compat, layout wrappers, WhatsApp ordering,
 * price-on-request, Apple-style single-product enhancements (trust badges,
 * compatibility tab) and the product-category mega menu helper.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Woo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All WooCommerce-specific behaviour. Runtime callbacks self-guard so the theme
 * activates cleanly even before WooCommerce is installed.
 */
class WooCommerce {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );

		// Layout wrappers. woocommerce_content() (used by our woocommerce.php)
		// does not fire these hooks, so the container is applied directly in the
		// template. We still strip WooCommerce's default wrapper for safety.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

		add_filter( 'loop_shop_columns', array( $this, 'loop_columns' ) );

		// Show "Price on request" instead of an empty price (quote mode).
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 10, 2 );

		// Use our clean branded placeholder for products without an image.
		add_filter( 'woocommerce_placeholder_img_src', array( $this, 'placeholder_img_src' ) );

		// Apple-style single product: gallery zoom/lightbox/slider support.
		add_action( 'after_setup_theme', array( $this, 'gallery_support' ) );

		// Single-product summary additions, in display order:
		//  - trust badges (delivery / warranty / secure / genuine) at 29
		//  - one WhatsApp button that works even when unpriced at 35
		//  - fitment / reference panel via product-meta hook.
		add_action( 'woocommerce_single_product_summary', array( $this, 'trust_badges' ), 29 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'condition_label' ), 11 );

		// Product condition: optional admin field, falls back to the product name.
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'condition_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_condition_field' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'single_whatsapp_button' ), 35 );
		add_action( 'woocommerce_product_meta_end', array( $this, 'fitment_panel' ) );

		// A dedicated "Compatibility & Fitment" product tab.
		add_filter( 'woocommerce_product_tabs', array( $this, 'compatibility_tab' ) );

		// Checkout: Kenya-friendly fields. No postal / ZIP code, and the phone
		// number is mandatory (orders are confirmed by phone / M-Pesa).
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'address_fields' ) );

		// Show the M-Pesa Pay Bill instructions on the checkout, above the
		// payment methods, so customers can pay even without an automated
		// gateway. Note: this hook fires on the classic (shortcode) checkout.
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'checkout_paybill_notice' ) );
	}

	/**
	 * Remove the postal / ZIP code from checkout and make the phone number a
	 * required field. Kenyan customers rarely use postcodes and orders are
	 * confirmed by phone / M-Pesa, so a mandatory phone matters more.
	 *
	 * @param array $fields Checkout fields, grouped by section.
	 * @return array
	 */
	public function checkout_fields( $fields ): array {
		if ( isset( $fields['billing']['billing_postcode'] ) ) {
			unset( $fields['billing']['billing_postcode'] );
		}
		if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
			unset( $fields['shipping']['shipping_postcode'] );
		}
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['required'] = true;
		}
		return $fields;
	}

	/**
	 * Drop the postal / ZIP code from the shared address template so it also
	 * disappears from the My Account edit-address forms and the cart shipping
	 * calculator, and never blocks validation.
	 *
	 * @param array $fields Default address fields.
	 * @return array
	 */
	public function address_fields( $fields ): array {
		if ( isset( $fields['postcode'] ) ) {
			unset( $fields['postcode'] );
		}
		return $fields;
	}

	/**
	 * Show the M-Pesa Pay Bill instructions on the checkout, above the payment
	 * methods. Fires on the classic (shortcode) checkout via
	 * woocommerce_review_order_before_payment.
	 */
	public function checkout_paybill_notice(): void {
		$out  = '<div class="mkae-checkout-paybill" style="margin:0 0 16px;padding:14px 16px;border:1px solid #e3e6ea;border-radius:12px;background:#f7f8f9;">';
		$out .= '<strong>' . esc_html__( 'Pay with M-Pesa (Lipa na M-Pesa)', 'mk-auto-elite' ) . '</strong>';
		$out .= '<ol style="margin:8px 0 0;padding-left:18px;line-height:1.6;">';
		$out .= '<li>' . esc_html__( 'M-Pesa menu, then Lipa na M-Pesa, then Pay Bill.', 'mk-auto-elite' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Business Number: 529914', 'mk-auto-elite' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Account Number: 4268', 'mk-auto-elite' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Enter the order total, confirm, then share the M-Pesa code with us on WhatsApp.', 'mk-auto-elite' ) . '</li>';
		$out .= '</ol></div>';
		echo wp_kses_post( apply_filters( 'mkae_checkout_paybill_notice', $out ) );
	}

	/**
	 * Declare HPOS and Cart/Checkout Blocks compatibility.
	 */
	public function declare_compatibility(): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'mk-auto-elite', true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', 'mk-auto-elite', true );
	}

	/**
	 * Enable WooCommerce gallery zoom, lightbox and slider for the premium
	 * single-product experience.
	 */
	public function gallery_support(): void {
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}

	/**
	 * Shop grid columns.
	 *
	 * @param int $columns Default columns.
	 */
	public function loop_columns( $columns ): int {
		return 4;
	}

	/**
	 * Serve the theme's clean branded placeholder for products with no image,
	 * so the framed gallery and product grid stay consistent.
	 *
	 * @param string $src Default placeholder URL.
	 */
	public function placeholder_img_src( $src ): string {
		return file_exists( MK_AUTO_ELITE_DIR . '/assets/images/placeholder.jpg' )
			? MK_AUTO_ELITE_URI . '/assets/images/placeholder.jpg'
			: $src;
	}

	/**
	 * Replace an empty price with a "Price on request" label.
	 *
	 * @param string      $html    Price HTML.
	 * @param \WC_Product $product Product.
	 */
	public function price_html( $html, $product ): string {
		if ( 'yes' !== Customizer::get( 'quote_default', 'yes' ) ) {
			return $html;
		}
		$price = $product->get_price();
		if ( '' === $price || null === $price || (float) $price <= 0 ) {
			return '<span class="mkae-price-ask">' . esc_html__( 'Price on request', 'mk-auto-elite' ) . '</span>';
		}
		return $html;
	}

	/**
	 * Whether a product should use the "Request price" WhatsApp variant.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function needs_quote( $product ): bool {
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}
		if ( 'yes' !== Customizer::get( 'quote_default', 'yes' ) ) {
			return false;
		}
		$price = $product->get_price();
		return ( '' === $price || null === $price || (float) $price <= 0 );
	}

	/**
	 * Build a WhatsApp deep link for a product.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function whatsapp_url( $product ): string {
		if ( ! $product instanceof \WC_Product ) {
			return '#';
		}
		$number  = preg_replace( '/\D+/', '', Customizer::get( 'whatsapp', '254700000000' ) );
		$sku     = $product->get_sku();
		$message = sprintf(
			/* translators: 1: product name, 2: reference, 3: URL. */
			__( "Hello MK Auto Spares, I'd like a price and availability for: %1\$s (Ref: %2\$s) %3\$s", 'mk-auto-elite' ),
			wp_strip_all_tags( $product->get_name() ),
			$sku ? $sku : ( 'ID-' . $product->get_id() ),
			get_permalink( $product->get_id() )
		);
		return 'https://wa.me/' . rawurlencode( $number ) . '?text=' . rawurlencode( $message );
	}

	/**
	 * Trust / reassurance badges shown just above the ordering buttons.
	 */
	public function trust_badges(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$delivery = Customizer::get( 'delivery_note', 'Fast countrywide delivery across Kenya' );
		$condition = self::condition( $product );
		if ( 'used' === $condition ) {
			$quality = array( '&#128737;', __( 'Used original part', 'mk-auto-elite' ), __( 'Inspected before dispatch', 'mk-auto-elite' ) );
		} elseif ( 'refurbished' === $condition ) {
			$quality = array( '&#128737;', __( 'Refurbished part', 'mk-auto-elite' ), __( 'Inspected before dispatch', 'mk-auto-elite' ) );
		} else {
			$quality = array( '&#128737;', __( 'Genuine & quality-checked', 'mk-auto-elite' ), __( 'Sourced and inspected before dispatch', 'mk-auto-elite' ) );
		}
		$badges   = array(
			array( '&#128666;', __( 'Delivery', 'mk-auto-elite' ), $delivery ),
			$quality,
			array( '&#128179;', __( 'Flexible payment', 'mk-auto-elite' ), __( 'M-Pesa or cash on delivery', 'mk-auto-elite' ) ),
			array( '&#128172;', __( 'Talk to a person', 'mk-auto-elite' ), __( 'Confirm fitment on WhatsApp before you buy', 'mk-auto-elite' ) ),
		);
		echo '<ul class="mkae-ps-trust">';
		foreach ( $badges as $b ) {
			echo '<li class="mkae-ps-trust__item"><span class="mkae-ps-trust__icon" aria-hidden="true">' . $b[0] . '</span><span class="mkae-ps-trust__text"><strong>' . esc_html( $b[1] ) . '</strong><span>' . esc_html( $b[2] ) . '</span></span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul>';
	}

	/**
	 * WhatsApp button on the single product page (works with or without price).
	 */
	public function single_whatsapp_button(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$quote = self::needs_quote( $product );
		printf(
			'<a class="mkae-btn mkae-btn--whatsapp mkae-btn--block" href="%s" target="_blank" rel="nofollow noopener"%s>%s %s</a>',
			esc_url( self::whatsapp_url( $product ) ),
			WaOrder::attr( $product ), // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in attr().
			WaOrder::icon( 20 ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $quote ? __( 'Request price on WhatsApp', 'mk-auto-elite' ) : __( 'Order on WhatsApp', 'mk-auto-elite' ) )
		);
	}

	/**
	 * Collect fitment / reference rows for a product.
	 *
	 * @param int $id Product ID.
	 * @return array<string,string>
	 */
	public static function fitment_rows( int $id ): array {
		global $product;
		$sku  = ( $product instanceof \WC_Product && $product->get_id() === $id ) ? $product->get_sku() : get_post_meta( $id, '_sku', true );
		$rows = array(
			__( 'Make', 'mk-auto-elite' )       => get_post_meta( $id, '_mk_make', true ),
			__( 'Model', 'mk-auto-elite' )      => get_post_meta( $id, '_mk_model', true ),
			__( 'Year', 'mk-auto-elite' )       => self::year_range( $id ),
			__( 'Engine', 'mk-auto-elite' )     => get_post_meta( $id, '_mk_engine', true ),
			__( 'OEM number', 'mk-auto-elite' ) => get_post_meta( $id, '_mk_oem', true ),
			__( 'Part/SKU', 'mk-auto-elite' )   => $sku,
		);
		return array_filter( $rows, static fn( $v ) => '' !== (string) $v );
	}

	/**
	 * Render OEM / part-number / fitment meta beneath the product summary.
	 */
	public function fitment_panel(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$rows = self::fitment_rows( $product->get_id() );
		if ( empty( $rows ) ) {
			return;
		}
		echo '<div class="mkae-fitment"><h3 class="mkae-fitment__title">' . esc_html__( 'Fitment & Reference', 'mk-auto-elite' ) . '</h3><dl class="mkae-fitment__list">';
		foreach ( $rows as $label => $value ) {
			echo '<div class="mkae-fitment__row"><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
		}
		echo '</dl></div>';
	}

	/**
	 * Add a "Compatibility & Fitment" tab to the single product page.
	 *
	 * @param array<string,array> $tabs Existing tabs.
	 * @return array<string,array>
	 */
	public function compatibility_tab( $tabs ) {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return $tabs;
		}
		if ( empty( self::fitment_rows( $product->get_id() ) ) ) {
			return $tabs;
		}
		$tabs['mkae_compat'] = array(
			'title'    => __( 'Compatibility & Fitment', 'mk-auto-elite' ),
			'priority' => 15,
			'callback' => array( $this, 'compatibility_tab_content' ),
		);
		return $tabs;
	}

	/**
	 * Render the compatibility tab body.
	 */
	public function compatibility_tab_content(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$rows = self::fitment_rows( $product->get_id() );
		echo '<h2>' . esc_html__( 'Compatibility & Fitment', 'mk-auto-elite' ) . '</h2>';
		echo '<table class="mkae-compat-table"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		printf(
			'<p class="mkae-compat-note">%s</p>',
			esc_html__( 'Vehicle years and variants can differ. If you are not certain this part fits your car, send us your chassis or registration number on WhatsApp and we will confirm before you order.', 'mk-auto-elite' )
		);
		printf(
			'<a class="mkae-btn mkae-btn--whatsapp" href="%s" target="_blank" rel="nofollow noopener">%s %s</a>',
			esc_url( self::whatsapp_url( $product ) ),
			WaOrder::icon( 18 ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html__( 'Confirm fitment on WhatsApp', 'mk-auto-elite' )
		);
	}

	/**
	 * Compose a display year range from meta.
	 */
	public static function year_range( int $id ): string {
		$from = (string) get_post_meta( $id, '_mk_year_from', true );
		$to   = (string) get_post_meta( $id, '_mk_year_to', true );
		if ( '' === $from ) {
			return '';
		}
		return ( '' !== $to && $to !== $from ) ? $from . ' - ' . $to : $from;
	}

	/**
	 * Short fitment line for product cards, e.g. "Toyota Prado 2010-2015".
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function fitment_line( $product ): string {
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}
		$id    = $product->get_id();
		$parts = array_filter(
			array(
				(string) get_post_meta( $id, '_mk_make', true ),
				(string) get_post_meta( $id, '_mk_model', true ),
				self::year_range( $id ),
			),
			static fn( $v ) => '' !== $v
		);
		return implode( ' ', $parts );
	}

	/**
	 * Build the product-category mega-menu HTML (auto from product_cat).
	 * Works without any manual menu configuration.
	 */
	public static function category_menu(): string {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return '';
		}
		$parents = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'parent'     => 0,
				'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
				'number'     => 30,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $parents ) || empty( $parents ) ) {
			return '';
		}
		$out = '<ul class="mkae-catmenu__list">';
		foreach ( $parents as $term ) {
			$children = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'parent'     => $term->term_id,
					'number'     => 12,
				)
			);
			$out .= '<li class="mkae-catmenu__item"><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				$out .= '<ul class="mkae-catmenu__sub">';
				foreach ( $children as $child ) {
					$out .= '<li><a href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a></li>';
				}
				$out .= '</ul>';
			}
			$out .= '</li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * Top product categories as [name => url], for the footer.
	 *
	 * @param int $limit Max categories.
	 * @return array<string,string>
	 */
	public static function top_categories( int $limit = 6 ): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		$out = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[ $t->name ] = (string) get_term_link( $t );
			}
		}
		return $out;
	}

	/**
	 * Top vehicle makes as [name => url], for the footer.
	 * Reads the pa_make attribute taxonomy first, then the product_make taxonomy.
	 *
	 * @param int $limit Max makes.
	 * @return array<string,string>
	 */
	public static function top_makes( int $limit = 8 ): array {
		foreach ( array( 'pa_make', 'product_make' ) as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => true,
					'number'     => $limit,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			$out = array();
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $t ) {
					$out[ $t->name ] = (string) get_term_link( $t );
				}
				return $out;
			}
		}
		return array();
	}

	/**
	 * Allowed values for the _mk_condition product field.
	 *
	 * @return array<string,string>
	 */
	public static function condition_options(): array {
		return array(
			'new-genuine'     => __( 'New (genuine / OEM)', 'mk-auto-elite' ),
			'new-aftermarket' => __( 'New (aftermarket)', 'mk-auto-elite' ),
			'used'            => __( 'Used / ex-Japan', 'mk-auto-elite' ),
			'refurbished'     => __( 'Refurbished', 'mk-auto-elite' ),
		);
	}

	/**
	 * Product condition key. The admin field wins; otherwise a product name
	 * that says used / ex-Japan / second-hand marks it used. '' when unknown.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function condition( \WC_Product $product ): string {
		$set     = (string) $product->get_meta( '_mk_condition', true );
		$options = self::condition_options();
		if ( isset( $options[ $set ] ) ) {
			return $set;
		}
		if ( preg_match( '/\b(used|ex[\s-]?japan|second[\s-]?hand|pre[\s-]?owned)\b/i', $product->get_name() ) ) {
			return 'used';
		}
		return '';
	}

	/**
	 * Visible condition line under the price (only when the condition is known).
	 */
	public function condition_label(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$key = self::condition( $product );
		if ( '' === $key ) {
			return;
		}
		$labels = array(
			'new-genuine'     => __( 'New (genuine / OEM)', 'mk-auto-elite' ),
			'new-aftermarket' => __( 'New (aftermarket)', 'mk-auto-elite' ),
			'used'            => preg_match( '/ex[\s-]?japan/i', $product->get_name() ) ? __( 'Used (ex-Japan)', 'mk-auto-elite' ) : __( 'Used', 'mk-auto-elite' ),
			'refurbished'     => __( 'Refurbished', 'mk-auto-elite' ),
		);
		printf(
			'<p class="mkae-ps-condition mkae-ps-condition--%1$s"><strong>%2$s</strong> %3$s</p>',
			esc_attr( $key ),
			esc_html__( 'Condition:', 'mk-auto-elite' ),
			esc_html( $labels[ $key ] )
		);
	}

	/**
	 * Condition dropdown in the product edit screen (General tab).
	 */
	public function condition_field(): void {
		if ( ! function_exists( 'woocommerce_wp_select' ) ) {
			return;
		}
		woocommerce_wp_select(
			array(
				'id'          => '_mk_condition',
				'label'       => __( 'Condition', 'mk-auto-elite' ),
				'options'     => array( '' => __( 'Not set (detect from product name)', 'mk-auto-elite' ) ) + self::condition_options(),
				'desc_tip'    => true,
				'description' => __( 'Shown on the product page and sent to Google. Leave unset to detect used or ex-Japan from the product name.', 'mk-auto-elite' ),
			)
		);
	}

	/**
	 * Save the condition field. WooCommerce has already verified the nonce.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public function save_condition_field( $product ): void {
		if ( ! $product instanceof \WC_Product || ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce.
		$value   = isset( $_POST['_mk_condition'] ) ? sanitize_key( wp_unslash( $_POST['_mk_condition'] ) ) : '';
		$options = self::condition_options();
		if ( isset( $options[ $value ] ) ) {
			$product->update_meta_data( '_mk_condition', $value );
		} else {
			$product->delete_meta_data( '_mk_condition' );
		}
	}
}
