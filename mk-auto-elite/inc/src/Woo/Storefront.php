<?php
/**
 * Storefront UX (v2.20.0): checkout fixes, shop filters, sticky cart bar,
 * empty-cart recovery, vehicle-relevant related products, quote checklist
 * and account benefits.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Woo;

use MKAutoElite\Customizer;
use MKAutoElite\MobileNav;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storefront hooks.
 */
class Storefront {

	/**
	 * Hook in.
	 */
	public function __construct() {
		// Checkout.
		add_filter( 'pre_option_woocommerce_checkout_phone_field', array( $this, 'phone_required' ) );
		add_filter( 'woocommerce_get_terms_page_id', array( $this, 'terms_page' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'gateways' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'blocks_gateway' ) );

		// Cart.
		add_action( 'wp_footer', array( $this, 'cart_bar' ), 4 );
		add_action( 'woocommerce_cart_is_empty', array( $this, 'empty_cart' ), 5 );
		add_filter( 'render_block_woocommerce/empty-cart-block', array( $this, 'empty_cart_block' ) );

		// Catalogue.
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'filter_bar' ), 15 );
		add_action( 'pre_get_posts', array( $this, 'apply_stock_filter' ) );
		add_filter( 'woocommerce_related_products', array( $this, 'related' ), 10, 3 );

		// Product + account.
		add_action( 'woocommerce_single_product_summary', array( $this, 'quote_checklist' ), 36 );
		add_action( 'woocommerce_before_customer_login_form', array( $this, 'account_benefits' ) );
	}

	/**
	 * Phone number required on both classic and block checkout.
	 */
	public function phone_required(): string {
		return 'required';
	}

	/**
	 * Fall back to a Terms page by slug when none is set in WooCommerce.
	 *
	 * @param int $id Page ID.
	 */
	public function terms_page( $id ): int {
		$id = (int) $id;
		if ( $id > 0 ) {
			return $id;
		}
		foreach ( array( 'terms', 'terms-and-conditions', 'terms-conditions' ) as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p && 'publish' === $p->post_status ) {
				return (int) $p->ID;
			}
		}
		return $id;
	}

	/**
	 * Register the Lipa na M-Pesa (Paybill) gateway.
	 *
	 * @param array $g Gateways.
	 */
	public function gateways( $g ): array {
		$g   = (array) $g;
		$g[] = MpesaGateway::class;
		return $g;
	}

	/**
	 * Register the gateway with the block checkout.
	 *
	 * @param object $registry Payment method registry.
	 */
	public function blocks_gateway( $registry ): void {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			$registry->register( new MpesaBlocks() );
		}
	}

	/**
	 * Sticky "Proceed to checkout" bar on the cart page so progression is
	 * always visible, whatever the cart block does.
	 */
	public function cart_bar(): void {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$count = (int) WC()->cart->get_cart_contents_count();
		?>
		<div class="mkcb" role="region" aria-label="<?php esc_attr_e( 'Checkout', 'mk-auto-elite' ); ?>">
			<div class="mkae-container mkcb__inner">
				<div class="mkcb__sum">
					<span class="mkcb__label"><?php echo esc_html( sprintf( /* translators: %d: items */ _n( 'Subtotal (%d item)', 'Subtotal (%d items)', $count, 'mk-auto-elite' ), $count ) ); ?></span>
					<strong class="mkcb__total"><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></strong>
				</div>
				<a class="mkae-btn mkae-btn--primary mkcb__btn" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
					<?php esc_html_e( 'Proceed to checkout', 'mk-auto-elite' ); ?>
					<?php echo MobileNav::icon( 'arrow' ); // phpcs:ignore ?>
				</a>
			</div>
			<p class="mkcb__note"><?php esc_html_e( 'Pay by M-Pesa or cash on delivery. Delivery is calculated at checkout.', 'mk-auto-elite' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Recovery panel HTML for an empty cart.
	 */
	private static function empty_html(): string {
		$shop = wc_get_page_permalink( 'shop' );
		$wa   = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
		$out  = '<div class="mkec">';
		$out .= '<span class="mkec__ico">' . MobileNav::icon( 'cart' ) . '</span>';
		$out .= '<h2 class="mkec__title">' . esc_html__( 'Your cart is empty', 'mk-auto-elite' ) . '</h2>';
		$out .= '<p class="mkec__sub">' . esc_html__( 'Find the right part for your car in a few taps, or ask a specialist on WhatsApp.', 'mk-auto-elite' ) . '</p>';
		$out .= '<div class="mkec__actions">';
		$out .= '<a class="mkae-btn mkae-btn--primary" href="' . esc_url( home_url( '/#find-parts' ) ) . '">' . esc_html__( 'Find parts for my car', 'mk-auto-elite' ) . '</a>';
		$out .= '<a class="mkae-btn mkae-btn--outline" href="' . esc_url( $shop ) . '">' . esc_html__( 'Continue shopping', 'mk-auto-elite' ) . '</a>';
		$out .= '<a class="mkae-btn mkae-btn--whatsapp" href="' . esc_url( 'https://wa.me/' . $wa ) . '" target="_blank" rel="nofollow noopener">' . esc_html__( 'Ask on WhatsApp', 'mk-auto-elite' ) . '</a>';
		$out .= '</div></div>';
		return $out;
	}

	/**
	 * Classic cart empty state.
	 */
	public function empty_cart(): void {
		echo self::empty_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Block cart empty state.
	 *
	 * @param string $html Block HTML.
	 */
	public function empty_cart_block( $html ): string {
		return self::empty_html() . (string) $html;
	}

	/**
	 * Hide out-of-stock items when ?stock=instock is set.
	 *
	 * @param \WP_Query $q Query.
	 */
	public function apply_stock_filter( $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( empty( $_GET['stock'] ) || 'instock' !== $_GET['stock'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$mq   = (array) $q->get( 'meta_query' );
		$mq[] = array(
			'key'   => '_stock_status',
			'value' => 'instock',
		);
		$q->set( 'meta_query', $mq );
	}

	/**
	 * Filter bar for product archives (shop, category, search, make/model hubs).
	 */
	public static function filter_bar(): void {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$get     = wp_unslash( $_GET );
		$current = get_queried_object();
		$is_cat  = $current instanceof \WP_Term && 'product_cat' === $current->taxonomy;
		$is_hub  = $current instanceof \WP_Term && in_array( $current->taxonomy, array( 'mk_make', 'mk_model' ), true );
		$action  = $current instanceof \WP_Term ? get_term_link( $current ) : ( is_search() ? home_url( '/' ) : wc_get_page_permalink( 'shop' ) );
		if ( is_wp_error( $action ) ) {
			$action = wc_get_page_permalink( 'shop' );
		}

		// Category chips: children of the current category, else top level.
		$parent = $is_cat ? (int) $current->term_id : 0;
		$chips  = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $parent,
				'hide_empty' => true,
				'number'     => 20,
				'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
			)
		);
		if ( $is_cat && ( is_wp_error( $chips ) || empty( $chips ) ) && $current->parent ) {
			$chips = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => (int) $current->parent,
					'hide_empty' => true,
					'number'     => 20,
				)
			);
		}

		$makes = array();
		if ( taxonomy_exists( 'mk_make' ) && ! $is_hub ) {
			$t = get_terms(
				array(
					'taxonomy'   => 'mk_make',
					'parent'     => 0,
					'hide_empty' => true,
					'orderby'    => 'name',
				)
			);
			$makes = is_wp_error( $t ) ? array() : $t;
		}

		$sel_make = isset( $get['mk_make'] ) ? sanitize_title( (string) $get['mk_make'] ) : '';
		$min      = isset( $get['min_price'] ) && '' !== $get['min_price'] ? absint( $get['min_price'] ) : '';
		$max      = isset( $get['max_price'] ) && '' !== $get['max_price'] ? absint( $get['max_price'] ) : '';
		$stock    = isset( $get['stock'] ) && 'instock' === $get['stock'];
		$orderby  = isset( $get['orderby'] ) ? sanitize_key( (string) $get['orderby'] ) : '';
		$s        = isset( $get['s'] ) ? sanitize_text_field( (string) $get['s'] ) : '';
		$active   = (int) ( '' !== $sel_make ) + (int) ( '' !== $min || '' !== $max ) + (int) $stock;
		// phpcs:enable

		$base_url = remove_query_arg( array( 'mk_make', 'min_price', 'max_price', 'stock', 'paged' ) );
		?>
		<div class="mkf" data-mkf>
			<?php if ( ! is_wp_error( $chips ) && ! empty( $chips ) ) : ?>
				<nav class="mkf__chips" aria-label="<?php esc_attr_e( 'Part types', 'mk-auto-elite' ); ?>">
					<?php if ( $is_cat ) : ?>
						<a class="mkf__chip is-on" href="<?php echo esc_url( get_term_link( $current ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'All %s', 'mk-auto-elite' ), $current->name ) ); ?></a>
					<?php endif; ?>
					<?php foreach ( $chips as $c ) : ?>
						<?php if ( $is_cat && (int) $c->term_id === (int) $current->term_id ) { continue; } ?>
						<a class="mkf__chip" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?> <span><?php echo esc_html( number_format_i18n( $c->count ) ); ?></span></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<details class="mkf__panel"<?php echo $active ? ' open' : ''; ?>>
				<summary class="mkf__toggle">
					<?php echo MobileNav::icon( 'filter' ); // phpcs:ignore ?>
					<span><?php esc_html_e( 'Filter results', 'mk-auto-elite' ); ?></span>
					<?php if ( $active ) : ?><span class="mkf__badge"><?php echo (int) $active; ?></span><?php endif; ?>
				</summary>
				<form class="mkf__form" method="get" action="<?php echo esc_url( $action ); ?>">
					<?php if ( is_search() ) : ?>
						<input type="hidden" name="s" value="<?php echo esc_attr( $s ); ?>">
						<input type="hidden" name="post_type" value="product">
					<?php endif; ?>
					<?php if ( $orderby ) : ?>
						<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
					<?php endif; ?>
					<?php if ( $makes ) : ?>
						<label class="mkf__field">
							<span><?php esc_html_e( 'Car make', 'mk-auto-elite' ); ?></span>
							<select name="mk_make">
								<option value=""><?php esc_html_e( 'All makes', 'mk-auto-elite' ); ?></option>
								<?php foreach ( $makes as $m ) : ?>
									<option value="<?php echo esc_attr( $m->slug ); ?>"<?php selected( $sel_make, $m->slug ); ?>><?php echo esc_html( $m->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>
					<div class="mkf__field mkf__field--price">
						<span><?php esc_html_e( 'Price (KSh)', 'mk-auto-elite' ); ?></span>
						<div class="mkf__range">
							<input type="number" name="min_price" min="0" step="100" inputmode="numeric" placeholder="<?php esc_attr_e( 'Min', 'mk-auto-elite' ); ?>" value="<?php echo esc_attr( (string) $min ); ?>" aria-label="<?php esc_attr_e( 'Minimum price', 'mk-auto-elite' ); ?>">
							<span aria-hidden="true">-</span>
							<input type="number" name="max_price" min="0" step="100" inputmode="numeric" placeholder="<?php esc_attr_e( 'Max', 'mk-auto-elite' ); ?>" value="<?php echo esc_attr( (string) $max ); ?>" aria-label="<?php esc_attr_e( 'Maximum price', 'mk-auto-elite' ); ?>">
						</div>
					</div>
					<label class="mkf__check">
						<input type="checkbox" name="stock" value="instock"<?php checked( $stock ); ?>>
						<span><?php esc_html_e( 'In stock only', 'mk-auto-elite' ); ?></span>
					</label>
					<div class="mkf__actions">
						<button type="submit" class="mkae-btn mkae-btn--primary"><?php esc_html_e( 'Show results', 'mk-auto-elite' ); ?></button>
						<?php if ( $active ) : ?>
							<a class="mkf__clear" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear all', 'mk-auto-elite' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</details>

			<?php if ( $active ) : ?>
				<div class="mkf__active" aria-label="<?php esc_attr_e( 'Active filters', 'mk-auto-elite' ); ?>">
					<?php if ( '' !== $sel_make ) : ?>
						<?php $mt = get_term_by( 'slug', $sel_make, 'mk_make' ); ?>
						<a class="mkf__pill" href="<?php echo esc_url( remove_query_arg( array( 'mk_make', 'paged' ) ) ); ?>"><?php echo esc_html( $mt ? $mt->name : $sel_make ); ?> <span aria-hidden="true">&times;</span><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'mk-auto-elite' ); ?></span></a>
					<?php endif; ?>
					<?php if ( '' !== $min || '' !== $max ) : ?>
						<a class="mkf__pill" href="<?php echo esc_url( remove_query_arg( array( 'min_price', 'max_price', 'paged' ) ) ); ?>"><?php echo esc_html( 'KSh ' . ( '' !== $min ? number_format_i18n( (float) $min ) : '0' ) . ' - ' . ( '' !== $max ? number_format_i18n( (float) $max ) : '+' ) ); ?> <span aria-hidden="true">&times;</span></a>
					<?php endif; ?>
					<?php if ( $stock ) : ?>
						<a class="mkf__pill" href="<?php echo esc_url( remove_query_arg( array( 'stock', 'paged' ) ) ); ?>"><?php esc_html_e( 'In stock', 'mk-auto-elite' ); ?> <span aria-hidden="true">&times;</span></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Related products: same vehicle model first, then same make, staying in
	 * the same part category where possible.
	 *
	 * @param int[] $related Default related IDs.
	 * @param int   $product_id Product ID.
	 * @param array $args Args (limit, excluded_ids).
	 * @return int[]
	 */
	public function related( $related, $product_id, $args ): array {
		$limit   = isset( $args['limit'] ) ? max( 4, (int) $args['limit'] ) : 8;
		$exclude = array_merge( array( (int) $product_id ), isset( $args['excluded_ids'] ) ? array_map( 'intval', (array) $args['excluded_ids'] ) : array() );
		$cats    = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$cats    = is_wp_error( $cats ) ? array() : $cats;
		$found   = array();

		foreach ( array( 'mk_model', 'mk_make' ) as $tax ) {
			if ( count( $found ) >= $limit || ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = wp_get_post_terms( $product_id, $tax, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			foreach ( array( true, false ) as $same_cat ) {
				if ( count( $found ) >= $limit ) {
					break;
				}
				$tq = array(
					array(
						'taxonomy' => $tax,
						'terms'    => $terms,
					),
				);
				if ( $same_cat && $cats ) {
					$tq[]             = array(
						'taxonomy' => 'product_cat',
						'terms'    => $cats,
					);
					$tq['relation'] = 'AND';
				}
				$ids   = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'fields'         => 'ids',
						'posts_per_page' => $limit - count( $found ),
						'post__not_in'   => array_merge( $exclude, $found ),
						'tax_query'      => $tq, // phpcs:ignore WordPress.DB.SlowDBQuery
						'orderby'        => 'rand',
						'no_found_rows'  => true,
					)
				);
				$found = array_merge( $found, array_map( 'intval', $ids ) );
			}
		}
		if ( count( $found ) < $limit ) {
			foreach ( (array) $related as $id ) {
				if ( count( $found ) >= $limit ) {
					break;
				}
				if ( ! in_array( (int) $id, $found, true ) && ! in_array( (int) $id, $exclude, true ) ) {
					$found[] = (int) $id;
				}
			}
		}
		return $found;
	}

	/**
	 * What to send for a price-on-request part.
	 */
	public function quote_checklist(): void {
		global $product;
		if ( ! $product instanceof \WC_Product || ! WooCommerce::needs_quote( $product ) ) {
			return;
		}
		?>
		<div class="mkq">
			<p class="mkq__title"><?php esc_html_e( 'To get an accurate price, send us:', 'mk-auto-elite' ); ?></p>
			<ul class="mkq__list">
				<li><?php esc_html_e( 'Your car make, model and year', 'mk-auto-elite' ); ?></li>
				<li><?php esc_html_e( 'Chassis (VIN) number, from the logbook or windscreen', 'mk-auto-elite' ); ?></li>
				<li><?php esc_html_e( 'A photo of your old part or its label / part number', 'mk-auto-elite' ); ?></li>
			</ul>
			<p class="mkq__note"><?php echo esc_html( sprintf( /* translators: %s: hours */ __( 'We reply on WhatsApp during business hours (%s).', 'mk-auto-elite' ), (string) Customizer::get( 'hours' ) ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Benefits panel above the login / register forms.
	 */
	public function account_benefits(): void {
		$wa = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
		?>
		<div class="mkacc">
			<div class="mkacc__copy">
				<h2 class="mkacc__title"><?php esc_html_e( 'Your MK Auto Spares account', 'mk-auto-elite' ); ?></h2>
				<p><?php esc_html_e( 'An account is optional: you can always check out as a guest.', 'mk-auto-elite' ); ?></p>
			</div>
			<ul class="mkacc__list">
				<li><?php echo MobileNav::icon( 'truck' ); // phpcs:ignore ?><span><?php esc_html_e( 'Track orders and delivery', 'mk-auto-elite' ); ?></span></li>
				<li><?php echo MobileNav::icon( 'car' ); // phpcs:ignore ?><span><?php esc_html_e( 'Faster checkout with saved details', 'mk-auto-elite' ); ?></span></li>
				<li><?php echo MobileNav::icon( 'return' ); // phpcs:ignore ?><span><?php esc_html_e( 'Easy returns and order history', 'mk-auto-elite' ); ?></span></li>
			</ul>
			<p class="mkacc__help"><?php esc_html_e( 'Ordered as a guest?', 'mk-auto-elite' ); ?> <a href="<?php echo esc_url( 'https://wa.me/' . $wa ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'Message us your order number on WhatsApp', 'mk-auto-elite' ); ?></a></p>
		</div>
		<?php
	}
}
