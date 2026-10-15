<?php
/**
 * Mobile navigation (v2.19.9): slide-in drawer with account strip, quick
 * actions, categories (expandable), popular car makes, help links and a
 * contact card. The labelled "Menu" button in the header and the bottom tab
 * bar open it. Desktop navigation is unchanged.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the mobile drawer and loads its script.
 */
class MobileNav {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
	}

	/**
	 * Enqueue the drawer script.
	 */
	public function assets(): void {
		if ( is_admin() ) {
			return;
		}
		$rel = '/assets/js/nav.js';
		if ( ! file_exists( MK_AUTO_ELITE_DIR . $rel ) ) {
			return;
		}
		wp_enqueue_script(
			'mkae-nav',
			MK_AUTO_ELITE_URI . $rel,
			array(),
			(string) filemtime( MK_AUTO_ELITE_DIR . $rel ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Small stroke icon set (24px grid).
	 *
	 * @param string $name Icon key.
	 * @return string SVG markup.
	 */
	public static function icon( string $name ): string {
		$p = array(
			'home'       => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
			'shop'       => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
			'search'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'cart'       => '<circle cx="9" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/><path d="M2 3h3l2.3 12.1a1.5 1.5 0 0 0 1.5 1.2h8.1a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/>',
			'menu'       => '<path d="M4 7h16M4 12h16M4 17h16"/>',
			'close'      => '<path d="M6 6l12 12M18 6 6 18"/>',
			'user'       => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
			'car'        => '<path d="M3 13l2-5a2 2 0 0 1 1.9-1.3h10.2A2 2 0 0 1 19 8l2 5v4h-2.2M5.2 17H3v-4h18"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/>',
			'truck'      => '<path d="M2 6h11v10H2zM13 9h4.5l3.5 3.5V16h-8"/><circle cx="6" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
			'chev'       => '<path d="m6 9 6 6 6-6"/>',
			'arrow'      => '<path d="m9 6 6 6-6 6"/>',
			'phone'      => '<path d="M5 3h3.5l1.6 4.2-2.2 1.4a11 11 0 0 0 5.5 5.5l1.4-2.2L19 13.5V17a2 2 0 0 1-2 2A15 15 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
			'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'pin'        => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'wallet'     => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 14.5h2"/>',
			'wrench'     => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
			'calendar'   => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
			'book'       => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7"/>',
			'heart'      => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
			'return'     => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
			'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
			'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
			'engine'     => '<path d="M4 10h2V8h4V6h4v2h3l2 2h1.5v5H19l-2 2H9l-2-2H4z"/><path d="M2 11v3"/>',
			'brake'      => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3"/><path d="M12 3.5v3M20.5 12h-3"/>',
			'battery'    => '<rect x="3" y="7" width="18" height="12" rx="2"/><path d="M7 7V5h3v2M14 7V5h3v2M6.5 13h3M15.5 11.5v3M14 13h3"/>',
			'bolt'       => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
			'snow'       => '<path d="M12 2v20M3.5 7l17 10M3.5 17l17-10"/>',
			'thermo'     => '<path d="M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0z"/>',
			'spring'     => '<path d="M7 3h10M7 21h10M8 6l8 2-8 2 8 2-8 2 8 2"/>',
			'bulb'       => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5V16h8v-2.5A6 6 0 0 0 12 3z"/>',
			'drop'       => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
			'gears'      => '<circle cx="6" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="18" cy="5" r="2"/><circle cx="6" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><path d="M6 7v10M12 7v10M18 7v5H6"/>',
			'star'       => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
			'steer'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M3.5 10.5h6M14.5 10.5h6M12 14.5V21"/>',
			'exhaust'    => '<path d="M2 14h12a3 3 0 0 0 0-6H8M18 9h2M18 13h4M18 17h2"/>',
			'wheel'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M12 3v6M12 15v6M3 12h6M15 12h6"/>',
			'filter'     => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
		);
		$d = $p[ $name ] ?? $p['wrench'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
	}

	/**
	 * Pick an icon for a product category by its name.
	 *
	 * @param string $name Category name.
	 */
	public static function category_icon( string $name ): string {
		$n   = strtolower( $name );
		$map = array(
			'air con'    => 'snow',
			'a/c'        => 'snow',
			'brake'      => 'brake',
			'batter'     => 'battery',
			'electric'   => 'bolt',
			'sensor'     => 'bolt',
			'engine'     => 'engine',
			'cool'       => 'thermo',
			'radiator'   => 'thermo',
			'suspension' => 'spring',
			'shock'      => 'spring',
			'light'      => 'bulb',
			'lamp'       => 'bulb',
			'oil'        => 'drop',
			'lubric'     => 'drop',
			'fluid'      => 'drop',
			'filter'     => 'filter',
			'transmis'   => 'gears',
			'gearbox'    => 'gears',
			'clutch'     => 'gears',
			'steer'      => 'steer',
			'exhaust'    => 'exhaust',
			'tyre'       => 'wheel',
			'tire'       => 'wheel',
			'wheel'      => 'wheel',
			'body'       => 'car',
			'nose cut'   => 'car',
			'accessor'   => 'star',
		);
		foreach ( $map as $needle => $icon ) {
			if ( false !== strpos( $n, $needle ) ) {
				return $icon;
			}
		}
		return 'wrench';
	}

	/**
	 * First existing page permalink among the given slugs.
	 *
	 * @param string[] $slugs Page paths.
	 */
	private static function page_url( array $slugs ): string {
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				return (string) get_permalink( $page );
			}
		}
		return '';
	}

	/**
	 * Which tab-bar item matches the current request.
	 */
	public static function active_tab(): string {
		if ( is_front_page() ) {
			return 'home';
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			return 'cart';
		}
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return 'shop';
		}
		if ( is_tax( array( 'mk_make', 'mk_model' ) ) ) {
			return 'shop';
		}
		return '';
	}

	/**
	 * Product categories for the drawer.
	 *
	 * @return array<int,array{name:string,url:string,count:int,children:array}>
	 */
	private static function categories(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$parents = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
				'number'     => 30,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $parents ) || empty( $parents ) ) {
			return array();
		}
		$out = array();
		foreach ( $parents as $term ) {
			$kids     = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'parent'     => $term->term_id,
					'number'     => 15,
					'orderby'    => 'name',
				)
			);
			$children = array();
			if ( ! is_wp_error( $kids ) ) {
				foreach ( $kids as $kid ) {
					$link = get_term_link( $kid );
					if ( ! is_wp_error( $link ) ) {
						$children[] = array(
							'name' => $kid->name,
							'url'  => $link,
						);
					}
				}
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$out[] = array(
				'name'     => $term->name,
				'url'      => $link,
				'count'    => (int) $term->count,
				'children' => $children,
			);
		}
		return $out;
	}

	/**
	 * Popular vehicle makes.
	 *
	 * @return array<string,string> name => url
	 */
	private static function makes(): array {
		if ( ! taxonomy_exists( 'mk_make' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'mk_make',
				'hide_empty' => true,
				'parent'     => 0,
				'number'     => 12,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$link = get_term_link( $t );
			if ( ! is_wp_error( $link ) ) {
				$out[ $t->name ] = $link;
			}
		}
		return $out;
	}

	/**
	 * Output the drawer.
	 */
	public function render(): void {
		if ( is_admin() ) {
			return;
		}
		$woo      = function_exists( 'wc_get_page_permalink' );
		$shop     = $woo ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$account  = $woo ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		$orders   = $woo && function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : $account;
		$phone    = (string) Customizer::get( 'phone' );
		$wa       = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
		$wa_url   = 'https://wa.me/' . $wa . '?text=' . rawurlencode( __( 'Hello MK Auto Spares, I need help finding a part.', 'mk-auto-elite' ) );
		$cats     = self::categories();
		$makes    = self::makes();
		$current  = trailingslashit( strtok( (string) home_url( add_query_arg( array() ) ), '?' ) );

		$explore = array();
		$explore[] = array( __( 'Shop all parts', 'mk-auto-elite' ), $shop, 'shop' );
		$book = self::page_url( array( 'book-a-service' ) );
		if ( $book ) {
			$explore[] = array( __( 'Book a service', 'mk-auto-elite' ), $book, 'calendar' );
		}
		if ( post_type_exists( 'mkae_guide' ) ) {
			$gc = wp_count_posts( 'mkae_guide' );
			if ( $gc && (int) $gc->publish > 0 ) {
				$glink = get_post_type_archive_link( 'mkae_guide' );
				if ( $glink ) {
					$explore[] = array( __( 'Car parts guides', 'mk-auto-elite' ), $glink, 'book' );
				}
			}
		}
		$wish = self::page_url( array( 'wishlist' ) );
		if ( $wish ) {
			$explore[] = array( __( 'Wishlist', 'mk-auto-elite' ), $wish, 'heart' );
		}
		$help = array();
		$returns = self::page_url( array( 'return-refund', 'returns', 'refund_returns' ) );
		if ( $returns ) {
			$help[] = array( __( 'Delivery & returns', 'mk-auto-elite' ), $returns, 'return' );
		}
		$about = self::page_url( array( 'about-us', 'about' ) );
		if ( $about ) {
			$help[] = array( __( 'About us', 'mk-auto-elite' ), $about, 'info' );
		}
		$contact = self::page_url( array( 'contact-us', 'contact' ) );
		if ( $contact ) {
			$help[] = array( __( 'Contact us', 'mk-auto-elite' ), $contact, 'mail' );
		}

		$link = static function ( array $item ) use ( $current ): string {
			$is = trailingslashit( strtok( $item[1], '?' ) ) === $current;
			return '<li><a class="mkd__link' . ( $is ? ' is-current' : '' ) . '" href="' . esc_url( $item[1] ) . '"' . ( $is ? ' aria-current="page"' : '' ) . '><span class="mkd__ico">' . self::icon( $item[2] ) . '</span><span class="mkd__txt">' . esc_html( $item[0] ) . '</span><span class="mkd__go">' . self::icon( 'arrow' ) . '</span></a></li>';
		};
		?>
<div class="mkd" id="mkd" data-mkd hidden>
	<div class="mkd__scrim" data-mkd-close></div>
	<aside class="mkd__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Main menu', 'mk-auto-elite' ); ?>" tabindex="-1">
		<header class="mkd__head">
			<a class="mkd__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php
				$logo_id = (int) get_theme_mod( 'custom_logo' );
				if ( $logo_id ) {
					echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'alt' => get_bloginfo( 'name' ), 'loading' => 'lazy', 'decoding' => 'async' ) );
				} else {
					echo '<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>';
				}
				?>
			</a>
			<button type="button" class="mkd__close" data-mkd-close aria-label="<?php esc_attr_e( 'Close menu', 'mk-auto-elite' ); ?>">
				<?php echo self::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span><?php esc_html_e( 'Close', 'mk-auto-elite' ); ?></span>
			</button>
		</header>

		<div class="mkd__body">
			<div class="mkd__account">
				<span class="mkd__avatar"><?php echo self::icon( 'user' ); // phpcs:ignore ?></span>
				<?php if ( is_user_logged_in() ) : ?>
					<?php $u = wp_get_current_user(); $first = $u->first_name ? $u->first_name : $u->display_name; ?>
					<span class="mkd__hello"><strong><?php echo esc_html( sprintf( /* translators: %s: first name */ __( 'Hi, %s', 'mk-auto-elite' ), $first ) ); ?></strong><span><?php esc_html_e( 'Your orders and saved details', 'mk-auto-elite' ); ?></span></span>
					<a class="mkd__account-btn" href="<?php echo esc_url( $account ); ?>"><?php esc_html_e( 'My account', 'mk-auto-elite' ); ?></a>
				<?php else : ?>
					<span class="mkd__hello"><strong><?php esc_html_e( 'Welcome to MK Auto Spares', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Sign in to track orders faster', 'mk-auto-elite' ); ?></span></span>
					<a class="mkd__account-btn" href="<?php echo esc_url( $account ); ?>"><?php esc_html_e( 'Sign in', 'mk-auto-elite' ); ?></a>
				<?php endif; ?>
			</div>

			<nav class="mkd__quick" aria-label="<?php esc_attr_e( 'Quick links', 'mk-auto-elite' ); ?>">
				<a class="mkd__q" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo self::icon( 'home' ); // phpcs:ignore ?><span><?php esc_html_e( 'Home', 'mk-auto-elite' ); ?></span></a>
				<a class="mkd__q" href="<?php echo esc_url( $shop ); ?>"><?php echo self::icon( 'shop' ); // phpcs:ignore ?><span><?php esc_html_e( 'All parts', 'mk-auto-elite' ); ?></span></a>
				<a class="mkd__q" href="<?php echo esc_url( home_url( '/#find-parts' ) ); ?>" data-mkd-finder><?php echo self::icon( 'car' ); // phpcs:ignore ?><span><?php esc_html_e( 'Find by car', 'mk-auto-elite' ); ?></span></a>
				<a class="mkd__q" href="<?php echo esc_url( $orders ); ?>"><?php echo self::icon( 'truck' ); // phpcs:ignore ?><span><?php esc_html_e( 'My orders', 'mk-auto-elite' ); ?></span></a>
			</nav>

			<?php if ( $cats ) : ?>
			<section class="mkd__sec" aria-labelledby="mkd-cats">
				<h2 class="mkd__h" id="mkd-cats"><?php esc_html_e( 'Shop by category', 'mk-auto-elite' ); ?></h2>
				<ul class="mkd__list">
					<?php foreach ( $cats as $i => $c ) : ?>
						<?php $is = trailingslashit( strtok( $c['url'], '?' ) ) === $current; ?>
						<li>
							<div class="mkd__row">
								<a class="mkd__link<?php echo $is ? ' is-current' : ''; ?>" href="<?php echo esc_url( $c['url'] ); ?>"<?php echo $is ? ' aria-current="page"' : ''; ?>>
									<span class="mkd__ico"><?php echo self::icon( self::category_icon( $c['name'] ) ); // phpcs:ignore ?></span>
									<span class="mkd__txt"><?php echo esc_html( $c['name'] ); ?></span>
									<?php if ( empty( $c['children'] ) ) : ?>
										<span class="mkd__count"><?php echo esc_html( number_format_i18n( $c['count'] ) ); ?></span>
									<?php endif; ?>
								</a>
								<?php if ( ! empty( $c['children'] ) ) : ?>
									<button type="button" class="mkd__acc" aria-expanded="false" aria-controls="mkd-sub-<?php echo (int) $i; ?>" data-mkd-acc>
										<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'Show %s types', 'mk-auto-elite' ), $c['name'] ) ); ?></span>
										<?php echo self::icon( 'chev' ); // phpcs:ignore ?>
									</button>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $c['children'] ) ) : ?>
								<ul class="mkd__sub" id="mkd-sub-<?php echo (int) $i; ?>" hidden>
									<li><a href="<?php echo esc_url( $c['url'] ); ?>"><strong><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'All %s', 'mk-auto-elite' ), $c['name'] ) ); ?></strong></a></li>
									<?php foreach ( $c['children'] as $k ) : ?>
										<li><a href="<?php echo esc_url( $k['url'] ); ?>"><?php echo esc_html( $k['name'] ); ?></a></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php endif; ?>

			<?php if ( $makes ) : ?>
			<section class="mkd__sec" aria-labelledby="mkd-makes">
				<h2 class="mkd__h" id="mkd-makes"><?php esc_html_e( 'Shop by car make', 'mk-auto-elite' ); ?></h2>
				<div class="mkd__makes">
					<?php foreach ( $makes as $name => $url ) : ?>
						<a class="mkd__make" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<section class="mkd__sec" aria-labelledby="mkd-explore">
				<h2 class="mkd__h" id="mkd-explore"><?php esc_html_e( 'Explore', 'mk-auto-elite' ); ?></h2>
				<ul class="mkd__list">
					<?php
					foreach ( array_merge( $explore, $help ) as $item ) {
						echo $link( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in closure.
					}
					?>
				</ul>
			</section>

			<div class="mkd__help">
				<p class="mkd__help-title"><?php esc_html_e( 'Not sure which part fits?', 'mk-auto-elite' ); ?></p>
				<p class="mkd__help-sub"><?php esc_html_e( 'Send us your car model or chassis number and a specialist will confirm the right part.', 'mk-auto-elite' ); ?></p>
				<div class="mkd__cta">
					<a class="mkd__btn mkd__btn--wa" href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="nofollow noopener">
						<?php echo class_exists( '\MKAutoElite\Woo\WaOrder' ) ? \MKAutoElite\Woo\WaOrder::icon( 18 ) : ''; // phpcs:ignore ?>
						<?php esc_html_e( 'WhatsApp', 'mk-auto-elite' ); ?>
					</a>
					<a class="mkd__btn mkd__btn--call" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>">
						<?php echo self::icon( 'phone' ); // phpcs:ignore ?>
						<?php esc_html_e( 'Call us', 'mk-auto-elite' ); ?>
					</a>
				</div>
				<ul class="mkd__meta">
					<li><?php echo self::icon( 'clock' ); // phpcs:ignore ?><span><?php echo esc_html( (string) Customizer::get( 'hours' ) ); ?></span></li>
					<li><?php echo self::icon( 'pin' ); // phpcs:ignore ?><span><?php echo esc_html( (string) Customizer::get( 'location' ) ); ?></span></li>
					<?php if ( Customizer::get( 'mpesa' ) ) : ?>
						<li><?php echo self::icon( 'wallet' ); // phpcs:ignore ?><span><?php echo esc_html( (string) Customizer::get( 'mpesa' ) ); ?></span></li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</aside>
</div>
		<?php
	}
}
