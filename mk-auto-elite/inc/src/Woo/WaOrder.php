<?php
/**
 * WhatsApp order form (added 2.19.8).
 *
 * Every "Order on WhatsApp" / "Ask price" button keeps its wa.me link (so it
 * still works without JavaScript) and carries a data-mkae-order payload.
 * wa-order.js intercepts the click and opens a checkout-style form:
 * quantity, vehicle / chassis for fitment, delivery method with the store's
 * delivery rule (KSh 500, free from KSh 10,000, free pick-up), payment
 * preference and a live order total. Submitting opens WhatsApp with a
 * formatted order (reference MK-YYMMDD-XXXX, items, subtotal, delivery,
 * total, customer and delivery details) and saves the request as a lead under
 * WooCommerce > WhatsApp Orders.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Woo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WhatsApp order modal, lead capture and admin list.
 */
class WaOrder {

	const CPT = 'mkae_wa_order';

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_action( 'wp_footer', array( $this, 'modal' ), 6 );
		add_action( 'wp_ajax_mkae_wa_order', array( $this, 'ajax_lead' ) );
		add_action( 'wp_ajax_nopriv_mkae_wa_order', array( $this, 'ajax_lead' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
	}

	/* ------------------------------------------------------------ helpers */

	/** WhatsApp number as digits with country code. */
	public static function number(): string {
		$digits = (string) preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp', '254702343302' ) );
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = '254' . substr( $digits, 1 );
		}
		return '' === $digits ? '254702343302' : $digits;
	}

	/** Delivery rule shared by the form, Merchant Center and the policy page. */
	public static function delivery(): array {
		return apply_filters(
			'mkae_wa_order_delivery',
			array(
				'fee'      => 500,
				'freeOver' => 10000,
			)
		);
	}

	/** Inline WhatsApp logo (used on buttons and the chat widget). */
	public static function icon( int $size = 18 ): string {
		return '<svg class="mkae-wa-ico" viewBox="0 0 32 32" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16.04 3C8.86 3 3.03 8.82 3.03 16c0 2.29.6 4.53 1.74 6.5L3 29l6.68-1.75A12.95 12.95 0 0 0 16.04 29C23.2 29 29.03 23.18 29.03 16S23.2 3 16.04 3zm0 23.62c-1.95 0-3.86-.52-5.53-1.52l-.4-.24-3.96 1.04 1.06-3.86-.26-.4a10.6 10.6 0 0 1-1.63-5.64c0-5.86 4.77-10.63 10.64-10.63 5.86 0 10.63 4.77 10.63 10.63 0 5.87-4.77 10.62-10.55 10.62zm5.83-7.96c-.32-.16-1.89-.93-2.18-1.04-.3-.1-.5-.16-.72.16-.21.32-.82 1.04-1.01 1.25-.18.21-.37.24-.69.08-.32-.16-1.35-.5-2.57-1.59-.95-.85-1.59-1.9-1.78-2.22-.18-.32-.02-.49.14-.65.14-.14.32-.37.48-.56.16-.18.21-.32.32-.53.1-.21.05-.4-.03-.56-.08-.16-.72-1.73-.98-2.37-.26-.62-.52-.54-.72-.55h-.61c-.21 0-.56.08-.85.4-.29.32-1.11 1.09-1.11 2.66 0 1.57 1.14 3.08 1.3 3.3.16.21 2.24 3.42 5.43 4.8.76.33 1.35.52 1.81.67.76.24 1.45.21 2 .13.61-.09 1.89-.77 2.16-1.52.26-.74.26-1.38.18-1.52-.08-.13-.29-.21-.61-.37z"/></svg>';
	}

	/** Data the form needs about one product. */
	public static function payload( \WC_Product $product ): array {
		$price = (float) wc_get_price_to_display( $product );
		$image = wp_get_attachment_image_url( (int) $product->get_image_id(), 'thumbnail' );
		$make  = taxonomy_exists( 'mk_make' ) ? wp_get_post_terms( $product->get_id(), 'mk_make', array( 'fields' => 'names' ) ) : array();
		$model = taxonomy_exists( 'mk_model' ) ? wp_get_post_terms( $product->get_id(), 'mk_model', array( 'fields' => 'names' ) ) : array();
		$cond  = WooCommerce::condition( $product );
		$label = WooCommerce::condition_options();
		return array(
			'id'        => $product->get_id(),
			'name'      => wp_strip_all_tags( $product->get_name() ),
			'sku'       => (string) $product->get_sku(),
			'price'     => $price > 0 ? $price : 0,
			'image'     => $image ? $image : '',
			'url'       => get_permalink( $product->get_id() ),
			'condition' => ( $cond && isset( $label[ $cond ] ) ) ? $label[ $cond ] : '',
			'inStock'   => $product->is_in_stock(),
			'quote'     => WooCommerce::needs_quote( $product ),
			'vehicle'   => trim( implode( ', ', is_wp_error( $make ) ? array() : $make ) . ' ' . implode( ', ', is_wp_error( $model ) ? array() : $model ) ),
		);
	}

	/** data-mkae-order attribute for a trigger. */
	public static function attr( \WC_Product $product ): string {
		return ' data-mkae-order="' . esc_attr( (string) wp_json_encode( self::payload( $product ) ) ) . '"';
	}

	/* ------------------------------------------------------------- assets */

	/**
	 * Enqueue the form script and its configuration.
	 */
	public function assets(): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}
		$rel = '/assets/js/wa-order.js';
		wp_enqueue_script(
			'mkae-wa-order',
			MK_AUTO_ELITE_URI . $rel,
			array(),
			(string) filemtime( MK_AUTO_ELITE_DIR . $rel ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		$d = self::delivery();
		wp_localize_script(
			'mkae-wa-order',
			'MKAEOrder',
			array(
				'number'   => self::number(),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'brand'    => 'MK Auto Spares',
				'currency' => 'KSh',
				'fee'      => (float) $d['fee'],
				'freeOver' => (float) $d['freeOver'],
				'mpesa'    => (string) \MKAutoElite\Customizer::get( 'mpesa' ),
				'pickup'   => (string) Customizer::get( 'location', 'Kirinyaga Road, Nairobi' ),
				'mpesa'    => 'Paybill 529914, Account 4268',
			)
		);
	}

	/* -------------------------------------------------------------- modal */

	/**
	 * Render the order form once per page.
	 */
	public function modal(): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}
		$d    = self::delivery();
		$free = number_format_i18n( (float) $d['freeOver'] );
		?>
		<div class="mkwo" data-mkwo hidden>
			<div class="mkwo__scrim" data-mkwo-close></div>
			<div class="mkwo__dialog" role="dialog" aria-modal="true" aria-labelledby="mkwo-title">
				<header class="mkwo__head">
					<span class="mkwo__head-ico"><?php echo self::icon( 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<div class="mkwo__head-text">
						<h2 class="mkwo__title" id="mkwo-title" data-mkwo-heading><?php esc_html_e( 'Order on WhatsApp', 'mk-auto-elite' ); ?></h2>
						<p class="mkwo__sub"><?php esc_html_e( 'Takes 30 seconds. We confirm fitment and stock before you pay.', 'mk-auto-elite' ); ?></p>
					</div>
					<button type="button" class="mkwo__x" data-mkwo-close aria-label="<?php esc_attr_e( 'Close', 'mk-auto-elite' ); ?>">&times;</button>
				</header>

				<form class="mkwo__form" data-mkwo-form novalidate>
					<div class="mkwo__scroll">
						<div class="mkwo__item">
							<img class="mkwo__img" data-mkwo-img alt="" width="72" height="72" hidden>
							<div class="mkwo__item-text">
								<p class="mkwo__name" data-mkwo-name></p>
								<p class="mkwo__meta" data-mkwo-meta></p>
								<p class="mkwo__unit" data-mkwo-unit></p>
							</div>
							<div class="mkwo__qty" role="group" aria-label="<?php esc_attr_e( 'Quantity', 'mk-auto-elite' ); ?>">
								<button type="button" class="mkwo__qty-btn" data-mkwo-qty="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'mk-auto-elite' ); ?>">&minus;</button>
								<input type="number" name="qty" class="mkwo__qty-input" value="1" min="1" max="50" inputmode="numeric" aria-label="<?php esc_attr_e( 'Quantity', 'mk-auto-elite' ); ?>">
								<button type="button" class="mkwo__qty-btn" data-mkwo-qty="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'mk-auto-elite' ); ?>">+</button>
							</div>
						</div>

						<fieldset class="mkwo__group">
							<legend class="mkwo__legend"><span>1</span><?php esc_html_e( 'Your details', 'mk-auto-elite' ); ?></legend>
							<div class="mkwo__grid">
								<label class="mkwo__field"><span><?php esc_html_e( 'Full name', 'mk-auto-elite' ); ?> *</span>
									<input type="text" name="name" autocomplete="name" maxlength="80" required>
								</label>
								<label class="mkwo__field"><span><?php esc_html_e( 'Phone / WhatsApp', 'mk-auto-elite' ); ?> *</span>
									<input type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="20" placeholder="07XX XXX XXX" required>
								</label>
							</div>
						</fieldset>

						<fieldset class="mkwo__group">
							<legend class="mkwo__legend"><span>2</span><?php esc_html_e( 'Your vehicle', 'mk-auto-elite' ); ?></legend>
							<div class="mkwo__grid">
								<label class="mkwo__field"><span><?php esc_html_e( 'Make, model and year', 'mk-auto-elite' ); ?></span>
									<input type="text" name="vehicle" maxlength="80" placeholder="<?php esc_attr_e( 'e.g. Toyota Fielder 2016', 'mk-auto-elite' ); ?>">
								</label>
								<label class="mkwo__field"><span><?php esc_html_e( 'Chassis / VIN (optional)', 'mk-auto-elite' ); ?></span>
									<input type="text" name="chassis" maxlength="40" autocapitalize="characters" placeholder="<?php esc_attr_e( 'Guarantees the right fit', 'mk-auto-elite' ); ?>">
								</label>
							</div>
						</fieldset>

						<fieldset class="mkwo__group">
							<legend class="mkwo__legend"><span>3</span><?php esc_html_e( 'Delivery', 'mk-auto-elite' ); ?></legend>
							<div class="mkwo__options">
								<label class="mkwo__opt"><input type="radio" name="delivery" value="nairobi" checked>
									<span class="mkwo__opt-body"><strong><?php esc_html_e( 'Delivery within Nairobi', 'mk-auto-elite' ); ?></strong><small><?php esc_html_e( 'Same or next business day', 'mk-auto-elite' ); ?></small></span>
									<span class="mkwo__opt-price" data-mkwo-fee-label></span>
								</label>
								<label class="mkwo__opt"><input type="radio" name="delivery" value="countrywide">
									<span class="mkwo__opt-body"><strong><?php esc_html_e( 'Countrywide delivery', 'mk-auto-elite' ); ?></strong><small><?php esc_html_e( 'Courier to your town, 1-5 business days', 'mk-auto-elite' ); ?></small></span>
									<span class="mkwo__opt-price" data-mkwo-fee-label></span>
								</label>
								<label class="mkwo__opt"><input type="radio" name="delivery" value="pickup">
									<span class="mkwo__opt-body"><strong><?php esc_html_e( 'Pick up from our shop', 'mk-auto-elite' ); ?></strong><small data-mkwo-pickup></small></span>
									<span class="mkwo__opt-price"><?php esc_html_e( 'Free', 'mk-auto-elite' ); ?></span>
								</label>
							</div>
							<label class="mkwo__field mkwo__field--full" data-mkwo-area><span><?php esc_html_e( 'Town / area and landmark', 'mk-auto-elite' ); ?> *</span>
								<input type="text" name="area" maxlength="120" autocomplete="street-address" placeholder="<?php esc_attr_e( 'e.g. Westlands, Sarit Centre', 'mk-auto-elite' ); ?>">
							</label>
							<?php /* translators: %s: amount. */ ?>
							<p class="mkwo__hint"><?php echo esc_html( sprintf( __( 'Free delivery on orders of KSh %s or more.', 'mk-auto-elite' ), $free ) ); ?></p>
						</fieldset>

						<fieldset class="mkwo__group">
							<legend class="mkwo__legend"><span>4</span><?php esc_html_e( 'Payment', 'mk-auto-elite' ); ?></legend>
							<div class="mkwo__chips">
								<label class="mkwo__chip"><input type="radio" name="payment" value="M-Pesa" checked><span><?php esc_html_e( 'M-Pesa', 'mk-auto-elite' ); ?></span></label>
								<label class="mkwo__chip"><input type="radio" name="payment" value="Cash on delivery"><span><?php esc_html_e( 'Cash on delivery', 'mk-auto-elite' ); ?></span></label>
							</div>
							<p class="mkwo__hint"><?php esc_html_e( 'Pay only after we confirm the part. M-Pesa Paybill 529914, Account 4268.', 'mk-auto-elite' ); ?></p>
							<label class="mkwo__field mkwo__field--full"><span><?php esc_html_e( 'Notes (optional)', 'mk-auto-elite' ); ?></span>
								<textarea name="notes" rows="2" maxlength="400" placeholder="<?php esc_attr_e( 'Side (left/right), engine code, urgency...', 'mk-auto-elite' ); ?>"></textarea>
							</label>
						</fieldset>
						<input type="text" name="website" class="mkae-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
					</div>

					<footer class="mkwo__foot">
						<dl class="mkwo__sum">
							<div><dt><?php esc_html_e( 'Subtotal', 'mk-auto-elite' ); ?></dt><dd data-mkwo-subtotal></dd></div>
							<div><dt><?php esc_html_e( 'Delivery', 'mk-auto-elite' ); ?></dt><dd data-mkwo-fee></dd></div>
							<div class="mkwo__total"><dt><?php esc_html_e( 'Total', 'mk-auto-elite' ); ?></dt><dd data-mkwo-total></dd></div>
						</dl>
						<p class="mkwo__error" data-mkwo-error role="alert" hidden></p>
						<button type="submit" class="mkwo__submit">
							<?php echo self::icon( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span data-mkwo-submit-label><?php esc_html_e( 'Send order on WhatsApp', 'mk-auto-elite' ); ?></span>
						</button>
						<ul class="mkwo__trust">
							<li><?php esc_html_e( 'No payment now', 'mk-auto-elite' ); ?></li>
							<li><?php esc_html_e( 'Fitment confirmed by a person', 'mk-auto-elite' ); ?></li>
							<li><?php esc_html_e( '7-day returns', 'mk-auto-elite' ); ?></li>
						</ul>
					</footer>
				</form>

				<div class="mkwo__done" data-mkwo-done hidden>
					<div class="mkwo__done-ico" aria-hidden="true">&#10003;</div>
					<h3><?php esc_html_e( 'Your order is ready in WhatsApp', 'mk-auto-elite' ); ?></h3>
					<p data-mkwo-done-text><?php esc_html_e( 'Tap Send in WhatsApp to place it. Our team replies within minutes during business hours.', 'mk-auto-elite' ); ?></p>
					<p class="mkwo__ref" data-mkwo-ref></p>
					<a class="mkwo__submit" data-mkwo-again href="#" target="_blank" rel="nofollow noopener"><?php echo self::icon( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Open WhatsApp again', 'mk-auto-elite' ); ?></span></a>
					<button type="button" class="mkwo__link" data-mkwo-close><?php esc_html_e( 'Continue shopping', 'mk-auto-elite' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/* -------------------------------------------------------------- leads */

	/**
	 * Private post type that stores each WhatsApp order request.
	 */
	public function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'WhatsApp Orders', 'mk-auto-elite' ),
					'singular_name' => __( 'WhatsApp Order', 'mk-auto-elite' ),
					'menu_name'     => __( 'WhatsApp Orders', 'mk-auto-elite' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'woocommerce',
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/** Visitor IP for rate limiting (hashed, never stored). */
	private static function ip_key(): string {
		$ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) : ( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '' );
		return 'mkae_wo_' . md5( $ip );
	}

	/** Text field from POST, trimmed to a length. */
	private static function field( string $key, int $len ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public lead form on cached pages; protected by honeypot, timing and rate limit.
		$raw = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $raw, 0, $len ) : substr( $raw, 0, $len );
	}

	/**
	 * Save an order request. Always answers 200 so WhatsApp hand-off is never blocked.
	 */
	public function ajax_lead(): void {
		if ( '' !== self::field( 'website', 50 ) ) {
			wp_send_json_success();
		}
		$elapsed = (int) self::field( 'elapsed', 10 );
		if ( $elapsed > 0 && $elapsed < 3 ) {
			wp_send_json_success();
		}
		$key   = self::ip_key();
		$count = (int) get_transient( $key );
		if ( $count >= 15 ) {
			wp_send_json_success();
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		$product = wc_get_product( (int) self::field( 'product', 12 ) );
		if ( ! $product instanceof \WC_Product ) {
			wp_send_json_success();
		}
		$qty      = max( 1, min( 50, (int) self::field( 'qty', 3 ) ) );
		$delivery = self::field( 'delivery', 20 );
		$unit     = (float) wc_get_price_to_display( $product );
		$subtotal = $unit * $qty;
		$rule     = self::delivery();
		$fee      = ( 'pickup' === $delivery || $unit <= 0 || $subtotal >= (float) $rule['freeOver'] ) ? 0 : (float) $rule['fee'];
		$data     = array(
			'ref'      => preg_replace( '/[^A-Z0-9-]/', '', strtoupper( self::field( 'ref', 24 ) ) ),
			'product'  => $product->get_id(),
			'item'     => $product->get_name(),
			'sku'      => $product->get_sku(),
			'qty'      => $qty,
			'unit'     => $unit,
			'subtotal' => $subtotal,
			'fee'      => $fee,
			'total'    => $unit > 0 ? $subtotal + $fee : 0,
			'name'     => self::field( 'name', 80 ),
			'phone'    => self::field( 'phone', 20 ),
			'vehicle'  => self::field( 'vehicle', 80 ),
			'chassis'  => self::field( 'chassis', 40 ),
			'delivery' => $delivery,
			'area'     => self::field( 'area', 120 ),
			'payment'  => self::field( 'payment', 30 ),
			'notes'    => self::field( 'notes', 400 ),
			'page'     => esc_url_raw( self::field( 'page', 300 ) ),
		);
		if ( '' === $data['name'] || '' === $data['phone'] ) {
			wp_send_json_success();
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'private',
				'post_title'  => trim( $data['ref'] . ' - ' . $data['name'] . ' - ' . $data['item'] ),
			),
			true
		);
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_mkae_order', $data );
		}
		wp_send_json_success();
	}

	/**
	 * Admin list columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public function columns( $cols ) {
		return array(
			'cb'         => $cols['cb'] ?? '',
			'title'      => __( 'Order request', 'mk-auto-elite' ),
			'mkwo_phone' => __( 'Phone', 'mk-auto-elite' ),
			'mkwo_total' => __( 'Total', 'mk-auto-elite' ),
			'mkwo_deliv' => __( 'Delivery', 'mk-auto-elite' ),
			'date'       => __( 'Date', 'mk-auto-elite' ),
		);
	}

	/**
	 * Admin column values.
	 *
	 * @param string $col Column.
	 * @param int    $id  Post ID.
	 */
	public function column( $col, $id ): void {
		$d = (array) get_post_meta( $id, '_mkae_order', true );
		if ( 'mkwo_phone' === $col && ! empty( $d['phone'] ) ) {
			$wa = preg_replace( '/\D+/', '', (string) $d['phone'] );
			if ( 0 === strpos( $wa, '0' ) ) {
				$wa = '254' . substr( $wa, 1 );
			}
			printf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( 'https://wa.me/' . $wa ), esc_html( (string) $d['phone'] ) );
		} elseif ( 'mkwo_total' === $col ) {
			echo ! empty( $d['total'] ) ? esc_html( 'KSh ' . number_format_i18n( (float) $d['total'] ) ) : esc_html__( 'Price on request', 'mk-auto-elite' );
		} elseif ( 'mkwo_deliv' === $col ) {
			echo esc_html( trim( (string) ( $d['delivery'] ?? '' ) . ' ' . (string) ( $d['area'] ?? '' ) ) );
		}
	}

	/**
	 * Details box on the single order-request screen.
	 */
	public function meta_box(): void {
		add_meta_box(
			'mkwo_details',
			__( 'Order details', 'mk-auto-elite' ),
			function ( $post ) {
				$d = (array) get_post_meta( $post->ID, '_mkae_order', true );
				echo '<table class="widefat striped"><tbody>';
				foreach ( $d as $k => $v ) {
					echo '<tr><th style="width:160px">' . esc_html( ucfirst( (string) $k ) ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
				}
				echo '</tbody></table>';
			},
			self::CPT,
			'normal',
			'high'
		);
	}
}
