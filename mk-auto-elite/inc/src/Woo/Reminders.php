<?php
/**
 * Abandoned cart & browse reminders (v2.27.0).
 *
 * - Logged-in shoppers: products they view and their cart are remembered.
 * - Guests: captured only when they tick "Email me a reminder" at checkout.
 * - Hourly job emails a branded reminder (cart after 1 hour and 24 hours,
 *   viewed products after 24 hours) with similar parts and a WhatsApp button.
 * - Staff get a WooCommerce > Cart Reminders list with one-tap WhatsApp
 *   follow-up messages (automatic WhatsApp needs the paid WhatsApp Business API).
 * - Every email has an unsubscribe link; shoppers can opt out in My Account.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Woo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reminder engine.
 */
class Reminders {

	public const CPT  = 'mkae_recover';
	public const HOOK = 'mkae_reminders_run';
	private const CART_FIRST  = HOUR_IN_SECONDS;
	private const CART_SECOND = DAY_IN_SECONDS;
	private const BROWSE      = DAY_IN_SECONDS;
	private const GAP         = 3 * DAY_IN_SECONDS;

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'template_redirect', array( $this, 'track_view' ) );
		add_action( 'template_redirect', array( $this, 'unsubscribe' ), 1 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_cart' ), 99 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'track_cart' ), 99 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'track_cart' ), 99 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'converted' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'converted' ), 10, 1 );
		add_action( 'wp_ajax_mkae_capture', array( $this, 'ajax_capture' ) );
		add_action( 'wp_ajax_nopriv_mkae_capture', array( $this, 'ajax_capture' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'consent_box' ), 4 );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'consent_box_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 40 );
		add_action( 'woocommerce_edit_account_form', array( $this, 'account_field' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_account_field' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Storage                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Private post type for reminder records (admin list only).
	 */
	public function register(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Cart Reminders', 'mk-auto-elite' ),
					'singular_name' => __( 'Cart Reminder', 'mk-auto-elite' ),
					'menu_name'     => __( 'Cart Reminders', 'mk-auto-elite' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => class_exists( 'WooCommerce' ) ? 'woocommerce' : true,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Hourly job (runs through the server cron that calls wp-cron.php).
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK );
		}
	}

	/**
	 * Find the record for a user or email.
	 *
	 * @param int    $user_id User.
	 * @param string $email Email.
	 */
	private function find( int $user_id, string $email ): int {
		$mq = $user_id ? array( array( 'key' => '_user', 'value' => $user_id ) ) : array( array( 'key' => '_email', 'value' => $email ) );
		$q  = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => $mq, // phpcs:ignore WordPress.DB.SlowDBQuery
				'no_found_rows'  => true,
			)
		);
		return $q ? (int) $q[0] : 0;
	}

	/**
	 * Create or update a record.
	 *
	 * @param array $d user, email, phone, name, type, items.
	 */
	private function upsert( array $d ): void {
		$user  = (int) ( $d['user'] ?? 0 );
		$email = sanitize_email( (string) ( $d['email'] ?? '' ) );
		if ( ! $user && ! is_email( $email ) ) {
			return;
		}
		if ( $this->opted_out( $user, $email ) ) {
			return;
		}
		$id = $this->find( $user, $email );
		if ( ! $id ) {
			$id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'publish',
					'post_title'  => $email ? $email : ( 'User #' . $user ),
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				return;
			}
			update_post_meta( $id, '_token', wp_generate_password( 24, false ) );
			update_post_meta( $id, '_sent', 0 );
		}
		$type = (string) ( $d['type'] ?? 'browse' );
		$old  = (string) get_post_meta( $id, '_type', true );
		// A cart outranks browsing; an empty cart falls back to browsing.
		if ( 'browse' === $type && 'cart' === $old ) {
			$items = (array) get_post_meta( $id, '_items', true );
			if ( $items ) {
				$this->add_viewed( $id, (array) ( $d['items'] ?? array() ) );
				return;
			}
		}
		foreach ( array( 'user' => $user, 'email' => $email, 'phone' => (string) ( $d['phone'] ?? '' ), 'name' => (string) ( $d['name'] ?? '' ) ) as $k => $v ) {
			if ( '' !== (string) $v && 0 !== $v ) {
				update_post_meta( $id, '_' . $k, $v );
			}
		}
		if ( 'browse' === $type ) {
			$this->add_viewed( $id, (array) ( $d['items'] ?? array() ) );
		} else {
			update_post_meta( $id, '_items', (array) ( $d['items'] ?? array() ) );
		}
		$changed = $type !== $old;
		update_post_meta( $id, '_type', $type );
		update_post_meta( $id, '_last', time() );
		if ( $changed || 'converted' === get_post_meta( $id, '_status', true ) ) {
			update_post_meta( $id, '_sent', 0 );
		}
		update_post_meta( $id, '_status', 'open' );
	}

	/**
	 * Keep the last 6 viewed product IDs.
	 *
	 * @param int   $id Record.
	 * @param array $items Product IDs.
	 */
	private function add_viewed( int $id, array $items ): void {
		$v = (array) get_post_meta( $id, '_viewed', true );
		foreach ( $items as $pid ) {
			$v = array_values( array_diff( $v, array( (int) $pid ) ) );
			array_unshift( $v, (int) $pid );
		}
		update_post_meta( $id, '_viewed', array_slice( array_filter( $v ), 0, 6 ) );
	}

	/**
	 * Opted out?
	 *
	 * @param int    $user User.
	 * @param string $email Email.
	 */
	private function opted_out( int $user, string $email ): bool {
		if ( $user && get_user_meta( $user, '_mkae_no_remind', true ) ) {
			return true;
		}
		$list = (array) get_option( 'mkae_remind_optout', array() );
		return $email && in_array( strtolower( $email ), $list, true );
	}

	/* ------------------------------------------------------------------ */
	/* Tracking                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Logged-in shopper viewed a product.
	 */
	public function track_view(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'is_product' ) || ! is_product() || current_user_can( 'edit_products' ) ) {
			return;
		}
		$u = wp_get_current_user();
		$this->upsert(
			array(
				'user'  => $u->ID,
				'email' => $u->user_email,
				'name'  => $u->first_name ? $u->first_name : $u->display_name,
				'phone' => (string) get_user_meta( $u->ID, 'billing_phone', true ),
				'type'  => 'browse',
				'items' => array( get_queried_object_id() ),
			)
		);
	}

	/**
	 * Current cart as [product_id => qty].
	 */
	private function cart_items(): array {
		$out = array();
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $it ) {
				$pid         = (int) ( $it['variation_id'] ? $it['variation_id'] : $it['product_id'] );
				$out[ $pid ] = (int) $it['quantity'];
			}
		}
		return $out;
	}

	/**
	 * Logged-in shopper changed their cart.
	 */
	public function track_cart(): void {
		if ( ! is_user_logged_in() || current_user_can( 'edit_products' ) ) {
			return;
		}
		$u     = wp_get_current_user();
		$items = $this->cart_items();
		$this->upsert(
			array(
				'user'  => $u->ID,
				'email' => $u->user_email,
				'name'  => $u->first_name ? $u->first_name : $u->display_name,
				'phone' => (string) get_user_meta( $u->ID, 'billing_phone', true ),
				'type'  => $items ? 'cart' : 'browse',
				'items' => $items ? $items : array(),
			)
		);
	}

	/**
	 * Guest consented at checkout: save email/phone with the current cart.
	 */
	public function ajax_capture(): void {
		check_ajax_referer( 'mkae_capture', 'nonce' );
		// phpcs:disable WordPress.Security.NonceVerification
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$consent = ! empty( $_POST['consent'] );
		// phpcs:enable
		if ( ! $consent || ! is_email( $email ) ) {
			wp_send_json_error();
		}
		$items = $this->cart_items();
		if ( ! $items ) {
			wp_send_json_error();
		}
		$this->upsert(
			array(
				'user'  => get_current_user_id(),
				'email' => $email,
				'phone' => $phone,
				'name'  => $name,
				'type'  => 'cart',
				'items' => $items,
			)
		);
		wp_send_json_success();
	}

	/**
	 * Order placed: stop reminders for this shopper.
	 *
	 * @param mixed $order Order or ID.
	 */
	public function converted( $order ): void {
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return;
		}
		$id = $this->find( (int) $order->get_customer_id(), (string) $order->get_billing_email() );
		if ( ! $id && $order->get_customer_id() ) {
			$id = $this->find( 0, (string) $order->get_billing_email() );
		}
		if ( $id ) {
			update_post_meta( $id, '_status', 'converted' );
			update_post_meta( $id, '_items', array() );
			update_post_meta( $id, '_order', $order->get_id() );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Checkout consent + My Account                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Consent box markup.
	 */
	private function consent_html(): string {
		if ( is_user_logged_in() ) {
			return '';
		}
		return '<div class="mkrm" data-mkrm><label class="mkrm__label"><input type="checkbox" data-mkrm-consent> <span><strong>' . esc_html__( 'Email me a reminder of my cart', 'mk-auto-elite' ) . '</strong> ' . esc_html__( 'if I do not finish my order. One or two emails at most, unsubscribe any time.', 'mk-auto-elite' ) . '</span></label></div>';
	}

	/**
	 * Classic checkout.
	 */
	public function consent_box(): void {
		echo $this->consent_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Block checkout.
	 *
	 * @param string $html Block HTML.
	 */
	public function consent_box_block( $html ): string {
		return $this->consent_html() . (string) $html;
	}

	/**
	 * Checkout capture script (guests only).
	 */
	public function assets(): void {
		if ( is_user_logged_in() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$rel = '/assets/js/reminders.js';
		wp_enqueue_script( 'mkae-reminders', MK_AUTO_ELITE_URI . $rel, array(), (string) filemtime( MK_AUTO_ELITE_DIR . $rel ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'mkae-reminders',
			'MKAERemind',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mkae_capture' ),
			)
		);
	}

	/**
	 * My Account > Account details: opt out.
	 */
	public function account_field(): void {
		$off = (bool) get_user_meta( get_current_user_id(), '_mkae_no_remind', true );
		?>
		<p class="woocommerce-form-row form-row form-row-wide">
			<label><input type="checkbox" name="mkae_reminders" value="1" <?php checked( ! $off ); ?>> <?php esc_html_e( 'Send me reminders about parts I viewed or left in my cart', 'mk-auto-elite' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Save opt-out.
	 *
	 * @param int $user_id User.
	 */
	public function save_account_field( $user_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the account form nonce.
		if ( empty( $_POST['mkae_reminders'] ) ) {
			update_user_meta( $user_id, '_mkae_no_remind', 1 );
		} else {
			delete_user_meta( $user_id, '_mkae_no_remind' );
		}
	}

	/**
	 * One-click unsubscribe from the email link.
	 */
	public function unsubscribe(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_GET['mkae_unsub'] ) || empty( $_GET['r'] ) ) {
			return;
		}
		$id    = absint( $_GET['r'] );
		$token = sanitize_text_field( wp_unslash( $_GET['mkae_unsub'] ) );
		// phpcs:enable
		if ( $id && hash_equals( (string) get_post_meta( $id, '_token', true ), $token ) ) {
			update_post_meta( $id, '_status', 'unsub' );
			$email = strtolower( (string) get_post_meta( $id, '_email', true ) );
			$list  = (array) get_option( 'mkae_remind_optout', array() );
			if ( $email && ! in_array( $email, $list, true ) ) {
				$list[] = $email;
				update_option( 'mkae_remind_optout', $list, false );
			}
			$user = (int) get_post_meta( $id, '_user', true );
			if ( $user ) {
				update_user_meta( $user, '_mkae_no_remind', 1 );
			}
			wp_die(
				esc_html__( 'You have been unsubscribed from MK Auto Spares reminders. You will not receive these emails again.', 'mk-auto-elite' ),
				esc_html__( 'Unsubscribed', 'mk-auto-elite' ),
				array( 'response' => 200, 'link_url' => home_url( '/' ), 'link_text' => __( 'Back to MK Auto Spares', 'mk-auto-elite' ) )
			);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Sending                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Has this shopper ordered since the given time?
	 *
	 * @param int    $user User.
	 * @param string $email Email.
	 * @param int    $since Timestamp.
	 */
	private function ordered_since( int $user, string $email, int $since ): bool {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$args = array(
			'limit'        => 1,
			'return'       => 'ids',
			'date_created' => '>' . $since,
			'status'       => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ),
		);
		$args += $user ? array( 'customer_id' => $user ) : array( 'billing_email' => $email );
		return (bool) wc_get_orders( $args );
	}

	/**
	 * Hourly run.
	 */
	public function run(): void {
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 40,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_status', 'value' => array( 'open', 'reminded' ), 'compare' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$now = time();
		foreach ( $ids as $id ) {
			$last  = (int) get_post_meta( $id, '_last', true );
			$sent  = (int) get_post_meta( $id, '_sent', true );
			$type  = (string) get_post_meta( $id, '_type', true );
			$user  = (int) get_post_meta( $id, '_user', true );
			$email = (string) get_post_meta( $id, '_email', true );
			$at    = (int) get_post_meta( $id, '_sent_at', true );
			if ( ! $last || ! is_email( $email ) || $this->opted_out( $user, $email ) ) {
				continue;
			}
			if ( $this->ordered_since( $user, $email, $last ) ) {
				update_post_meta( $id, '_status', 'converted' );
				continue;
			}
			$age = $now - $last;
			$due = '';
			if ( 'cart' === $type ) {
				if ( 0 === $sent && $age >= self::CART_FIRST ) {
					$due = 'cart1';
				} elseif ( 1 === $sent && $age >= self::CART_SECOND ) {
					$due = 'cart2';
				}
			} elseif ( 0 === $sent && $age >= self::BROWSE && ( $now - $at ) >= self::GAP && $age < 7 * DAY_IN_SECONDS ) {
				$due = 'browse';
			}
			if ( ! $due ) {
				continue;
			}
			if ( $this->send( $id, $due ) ) {
				update_post_meta( $id, '_sent', $sent + 1 );
				update_post_meta( $id, '_sent_at', $now );
				update_post_meta( $id, '_status', 'reminded' );
			}
		}
	}

	/**
	 * Product rows for the email.
	 *
	 * @param array $ids Product IDs.
	 */
	private function product_rows( array $ids ): string {
		$out = '';
		foreach ( $ids as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p || ! $p->is_visible() ) {
				continue;
			}
			$img   = wp_get_attachment_image_url( (int) $p->get_image_id(), 'woocommerce_thumbnail' );
			$price = $p->get_price() ? html_entity_decode( wp_strip_all_tags( wc_price( (float) $p->get_price() ) ), ENT_QUOTES, 'UTF-8' ) : __( 'Price on request', 'mk-auto-elite' );
			$out  .= '<tr><td style="padding:10px 0;border-bottom:1px solid #EEF0F3;width:84px;vertical-align:top;">'
				. ( $img ? '<img src="' . esc_url( $img ) . '" width="72" height="72" alt="" style="display:block;width:72px;height:72px;object-fit:contain;border-radius:8px;background:#F6F7F9;">' : '' )
				. '</td><td style="padding:10px 0 10px 12px;border-bottom:1px solid #EEF0F3;vertical-align:top;font-family:Arial,sans-serif;">'
				. '<a href="' . esc_url( get_permalink( $p->get_id() ) ) . '" style="color:#111214;font-weight:bold;font-size:14px;text-decoration:none;">' . esc_html( $p->get_name() ) . '</a>'
				. '<div style="color:#CA1D24;font-weight:bold;font-size:14px;margin-top:4px;">' . esc_html( $price ) . '</div></td></tr>';
		}
		return $out;
	}

	/**
	 * Similar parts (same vehicle first, via the theme's related logic).
	 *
	 * @param array $ids Seed product IDs.
	 */
	private function similar( array $ids ): array {
		$seed = (int) reset( $ids );
		if ( ! $seed || ! function_exists( 'wc_get_related_products' ) ) {
			return array();
		}
		return array_slice( array_diff( wc_get_related_products( $seed, 6, $ids ), $ids ), 0, 3 );
	}

	/**
	 * Build and send one reminder.
	 *
	 * @param int    $id Record.
	 * @param string $kind cart1|cart2|browse.
	 */
	private function send( int $id, string $kind ): bool {
		$email = (string) get_post_meta( $id, '_email', true );
		$name  = (string) get_post_meta( $id, '_name', true );
		$items = 'browse' === $kind ? (array) get_post_meta( $id, '_viewed', true ) : array_keys( (array) get_post_meta( $id, '_items', true ) );
		$items = array_values( array_filter( array_map( 'intval', $items ) ) );
		if ( ! $items ) {
			return false;
		}
		$first = wc_get_product( $items[0] );
		if ( ! $first ) {
			return false;
		}
		$wa    = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
		$wa_tx = sprintf( 'Hello MK Auto Spares, I am interested in %s: %s', $first->get_name(), get_permalink( $first->get_id() ) );
		$cta   = 'browse' === $kind ? get_permalink( $first->get_id() ) : wc_get_cart_url();
		$unsub = add_query_arg( array( 'mkae_unsub' => get_post_meta( $id, '_token', true ), 'r' => $id ), home_url( '/' ) );
		$hi    = $name ? sprintf( __( 'Hi %s,', 'mk-auto-elite' ), $name ) : __( 'Hello,', 'mk-auto-elite' );

		if ( 'cart1' === $kind ) {
			$subject = __( 'You left something in your cart', 'mk-auto-elite' );
			$intro   = __( 'Your parts are still waiting for you. Complete your order in a minute, pay with M-Pesa or cash on delivery, and we will dispatch countrywide.', 'mk-auto-elite' );
			$btn     = __( 'Complete my order', 'mk-auto-elite' );
		} elseif ( 'cart2' === $kind ) {
			$subject = sprintf( __( 'Still need the %s?', 'mk-auto-elite' ), $first->get_name() );
			$intro   = __( 'Stock moves fast. If you are not sure the part fits, reply on WhatsApp with your chassis number and a specialist will confirm before you pay.', 'mk-auto-elite' );
			$btn     = __( 'Return to my cart', 'mk-auto-elite' );
		} else {
			$subject = sprintf( __( 'Still looking for the %s?', 'mk-auto-elite' ), $first->get_name() );
			$intro   = __( 'Here are the parts you were looking at, plus a few similar ones for your car. Not sure which fits? Send us your chassis number on WhatsApp.', 'mk-auto-elite' );
			$btn     = __( 'View the part', 'mk-auto-elite' );
		}
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$sim     = $this->product_rows( $this->similar( $items ) );

		$html  = '<div style="background:#F3F4F6;padding:24px 12px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;font-family:Arial,sans-serif;color:#111214;">';
		$html .= '<tr><td style="padding:20px 24px;border-bottom:3px solid #CA1D24;">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="MK Auto Spares" style="max-height:44px;width:auto;display:block;">' : '<strong style="font-size:18px;">MK Auto Spares</strong>' ) . '</td></tr>';
		$html .= '<tr><td style="padding:24px;"><p style="margin:0 0 6px;font-size:15px;">' . esc_html( $hi ) . '</p><h1 style="margin:0 0 10px;font-size:22px;line-height:1.25;">' . esc_html( $subject ) . '</h1><p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#4B5563;">' . esc_html( $intro ) . '</p>';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $this->product_rows( array_slice( $items, 0, 4 ) ) . '</table>';
		$html .= '<p style="margin:20px 0 8px;"><a href="' . esc_url( $cta ) . '" style="display:inline-block;background:#CA1D24;color:#ffffff;font-weight:bold;font-size:15px;padding:13px 22px;border-radius:10px;text-decoration:none;">' . esc_html( $btn ) . '</a> &nbsp; <a href="' . esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $wa_tx ) ) . '" style="display:inline-block;background:#25D366;color:#ffffff;font-weight:bold;font-size:15px;padding:13px 22px;border-radius:10px;text-decoration:none;">' . esc_html__( 'Ask on WhatsApp', 'mk-auto-elite' ) . '</a></p>';
		if ( $sim ) {
			$html .= '<h2 style="margin:26px 0 4px;font-size:16px;">' . esc_html__( 'Similar parts for your car', 'mk-auto-elite' ) . '</h2><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $sim . '</table>';
		}
		$html .= '</td></tr><tr><td style="padding:18px 24px;background:#16181B;color:#C8CCD2;font-size:12px;line-height:1.6;">MK Auto Spares &middot; ' . esc_html( (string) Customizer::get( 'location' ) ) . ' &middot; ' . esc_html( (string) Customizer::get( 'phone' ) ) . '<br>' . esc_html__( 'Pay with M-Pesa or cash on delivery. Countrywide delivery.', 'mk-auto-elite' ) . '<br><a href="' . esc_url( $unsub ) . '" style="color:#C8CCD2;">' . esc_html__( 'Unsubscribe from reminders', 'mk-auto-elite' ) . '</a></td></tr></table></div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'List-Unsubscribe: <' . esc_url_raw( $unsub ) . '>' );
		$ok      = wp_mail( $email, $subject, $html, $headers );
		if ( $ok ) {
			$log   = (array) get_post_meta( $id, '_log', true );
			$log[] = gmdate( 'Y-m-d H:i' ) . ' ' . $kind;
			update_post_meta( $id, '_log', array_slice( $log, -10 ) );
		}
		return (bool) $ok;
	}

	/* ------------------------------------------------------------------ */
	/* Admin list                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * WhatsApp follow-up link for staff.
	 *
	 * @param int $id Record.
	 */
	public static function whatsapp_link( int $id ): string {
		$phone = preg_replace( '/\D+/', '', (string) get_post_meta( $id, '_phone', true ) );
		if ( '' === $phone ) {
			return '';
		}
		if ( 0 === strpos( $phone, '0' ) ) {
			$phone = '254' . substr( $phone, 1 );
		} elseif ( 9 === strlen( $phone ) ) {
			$phone = '254' . $phone;
		}
		$type  = (string) get_post_meta( $id, '_type', true );
		$items = 'cart' === $type ? array_keys( (array) get_post_meta( $id, '_items', true ) ) : (array) get_post_meta( $id, '_viewed', true );
		$p     = $items ? wc_get_product( (int) reset( $items ) ) : null;
		$name  = (string) get_post_meta( $id, '_name', true );
		$msg   = sprintf(
			'Hi %s, this is MK Auto Spares. %s Would you like us to confirm it fits your car and reserve it for you? %s',
			$name ? $name : 'there',
			$p ? ( 'cart' === $type ? 'You left the ' . $p->get_name() . ' in your cart.' : 'We saw you were looking at the ' . $p->get_name() . '.' ) : 'Thanks for visiting our shop.',
			$p ? get_permalink( $p->get_id() ) : home_url( '/' )
		);
		return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $msg );
	}

	/**
	 * Admin columns.
	 *
	 * @param array $cols Columns.
	 */
	public function columns( $cols ): array {
		return array(
			'cb'        => $cols['cb'] ?? '',
			'title'     => __( 'Shopper', 'mk-auto-elite' ),
			'mk_type'   => __( 'Type', 'mk-auto-elite' ),
			'mk_items'  => __( 'Parts', 'mk-auto-elite' ),
			'mk_phone'  => __( 'Phone', 'mk-auto-elite' ),
			'mk_status' => __( 'Status', 'mk-auto-elite' ),
			'mk_last'   => __( 'Last activity', 'mk-auto-elite' ),
			'mk_wa'     => __( 'Follow up', 'mk-auto-elite' ),
		);
	}

	/**
	 * Column values.
	 *
	 * @param string $col Column.
	 * @param int    $id Post.
	 */
	public function column( $col, $id ): void {
		switch ( $col ) {
			case 'mk_type':
				echo esc_html( 'cart' === get_post_meta( $id, '_type', true ) ? __( 'Cart', 'mk-auto-elite' ) : __( 'Viewed', 'mk-auto-elite' ) );
				break;
			case 'mk_items':
				$type  = get_post_meta( $id, '_type', true );
				$items = 'cart' === $type ? array_keys( (array) get_post_meta( $id, '_items', true ) ) : (array) get_post_meta( $id, '_viewed', true );
				$names = array();
				foreach ( array_slice( $items, 0, 3 ) as $pid ) {
					$p = wc_get_product( (int) $pid );
					if ( $p ) {
						$names[] = '<a href="' . esc_url( get_permalink( $p->get_id() ) ) . '" target="_blank">' . esc_html( $p->get_name() ) . '</a>';
					}
				}
				echo wp_kses_post( implode( '<br>', $names ) );
				break;
			case 'mk_phone':
				echo esc_html( (string) get_post_meta( $id, '_phone', true ) );
				break;
			case 'mk_status':
				$s    = (string) get_post_meta( $id, '_status', true );
				$map  = array( 'open' => __( 'Waiting', 'mk-auto-elite' ), 'reminded' => __( 'Reminded', 'mk-auto-elite' ), 'converted' => __( 'Ordered', 'mk-auto-elite' ), 'unsub' => __( 'Unsubscribed', 'mk-auto-elite' ) );
				$sent = (int) get_post_meta( $id, '_sent', true );
				echo esc_html( ( $map[ $s ] ?? $s ) . ( $sent ? ' (' . $sent . ' email' . ( $sent > 1 ? 's' : '' ) . ')' : '' ) );
				break;
			case 'mk_last':
				$t = (int) get_post_meta( $id, '_last', true );
				echo $t ? esc_html( human_time_diff( $t ) . ' ' . __( 'ago', 'mk-auto-elite' ) ) : '';
				break;
			case 'mk_wa':
				$l = self::whatsapp_link( (int) $id );
				if ( $l && 'converted' !== get_post_meta( $id, '_status', true ) ) {
					echo '<a class="button" style="background:#25D366;border-color:#25D366;color:#fff;" href="' . esc_url( $l ) . '" target="_blank" rel="noopener">' . esc_html__( 'Send on WhatsApp', 'mk-auto-elite' ) . '</a>';
				}
				break;
		}
	}

	/**
	 * Keep row actions minimal.
	 *
	 * @param array    $actions Actions.
	 * @param \WP_Post $post Post.
	 */
	public function row_actions( $actions, $post ): array {
		if ( self::CPT === $post->post_type ) {
			return array_intersect_key( (array) $actions, array( 'trash' => 1 ) );
		}
		return (array) $actions;
	}
}
