<?php
/**
 * Front-end and editor asset loading with performance defaults.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues styles and scripts, self-hosted fonts, and preloads; and trims
 * unnecessary front-end assets for speed.
 */
class Assets {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'optimize_assets' ), 99 );
		add_action( 'wp_head', array( $this, 'resource_hints' ), 1 );
		add_action( 'wp_head', array( $this, 'critical_css' ), 5 );
		add_action( 'init', array( $this, 'perf_cleanup' ) );
		add_action( 'wp_default_scripts', array( $this, 'trim_jquery_migrate' ) );
		// NOTE: main.css is intentionally loaded render-blocking (see enqueue()).
		// The earlier print-media swap made the header/menu flash unstyled on
		// reload (FOUC); a ~38KB stylesheet blocks first paint negligibly, so we
		// prefer a correct, stable first paint over shaving a few milliseconds.
	}

	/**
	 * Version helper based on file modification time for cache busting.
	 */
	private function ver( string $relative ): string {
		$file = MK_AUTO_ELITE_DIR . $relative;
		return file_exists( $file ) ? (string) filemtime( $file ) : MK_AUTO_ELITE_VERSION;
	}

	/**
	 * Prefer a minified build artifact (main.min.css / main.min.js) when the
	 * build produced one; otherwise fall back to the readable source file.
	 */
	private function asset( string $relative ): string {
		$min = preg_replace( '/\.(css|js)$/', '.min.$1', $relative );
		return file_exists( MK_AUTO_ELITE_DIR . $min ) ? $min : $relative;
	}

	/**
	 * Whether the fitment-tools script is needed on this request: product pages
	 * (the auto-injected fit checker) or any singular view whose content uses
	 * one of the fitment tool shortcodes.
	 */
	private function needs_fitment_tools(): bool {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof \WP_Post ) {
				foreach ( array( 'mk_parts_finder', 'mk_symptom_wizard', 'mk_fit_checker' ) as $sc ) {
					if ( has_shortcode( (string) $post->post_content, $sc ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Whether the booking form script is needed: any singular view whose
	 * content uses the [mk_booking_form] shortcode.
	 */
	private function needs_booking_form(): bool {
		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'mk_booking_form' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Enqueue CSS and JS.
	 */
	public function enqueue(): void {
		// Design system.
		wp_enqueue_style(
			'mkae-main',
			MK_AUTO_ELITE_URI . $this->asset( '/assets/css/main.css' ),
			array(),
			$this->ver( $this->asset( '/assets/css/main.css' ) )
		);

		// Deferred, dependency-free front-end behaviour.
		wp_enqueue_script(
			'mkae-main',
			MK_AUTO_ELITE_URI . $this->asset( '/assets/js/main.js' ),
			array(),
			$this->ver( $this->asset( '/assets/js/main.js' ) ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_enqueue_script(
			'mkae-vehicle-finder',
			MK_AUTO_ELITE_URI . $this->asset( '/assets/js/vehicle-finder.js' ),
			array( 'mkae-main' ),
			$this->ver( $this->asset( '/assets/js/vehicle-finder.js' ) ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Fitment tools (Parts Finder, Symptom wizard, Fit checker) - only on
		// product pages or pages that use one of the tool shortcodes. The
		// Fitment module localizes its MKAEF config onto this handle at prio 20.
		if ( $this->needs_fitment_tools() ) {
			wp_enqueue_script(
				'mkae-fitment',
				MK_AUTO_ELITE_URI . $this->asset( '/assets/js/fitment-tools.js' ),
				array( 'mkae-main' ),
				$this->ver( $this->asset( '/assets/js/fitment-tools.js' ) ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		// Service booking form: only where the [mk_booking_form] shortcode is used.
		if ( $this->needs_booking_form() ) {
			wp_enqueue_script(
				'mkae-booking',
				MK_AUTO_ELITE_URI . $this->asset( '/assets/js/booking.js' ),
				array( 'mkae-main' ),
				$this->ver( $this->asset( '/assets/js/booking.js' ) ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		// AI chat assistant: site-wide when enabled. The Chat module localizes
		// its MKAEChat config onto this handle at priority 20.
		if ( 'yes' === Customizer::get( 'chat_enabled', 'yes' ) ) {
			wp_enqueue_script(
				'mkae-chat',
				MK_AUTO_ELITE_URI . $this->asset( '/assets/js/chat.js' ),
				array( 'mkae-main' ),
				$this->ver( $this->asset( '/assets/js/chat.js' ) ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		wp_localize_script(
			'mkae-main',
			'MKAE',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'homeUrl'  => home_url( '/' ),
				'restUrl'  => esc_url_raw( rest_url( 'mkae/v1/' ) ),
				'nonce'    => wp_create_nonce( 'mkae_nonce' ),
				'whatsapp' => Customizer::get( 'whatsapp', '254700000000' ),
				'i18n'     => array(
					'selectMake'  => __( 'Select make', 'mk-auto-elite' ),
					'selectModel' => __( 'Select model', 'mk-auto-elite' ),
					'loading'     => __( 'Loading...', 'mk-auto-elite' ),
					'noResults'   => __( 'No matches found', 'mk-auto-elite' ),
					'added'       => __( 'Added to cart', 'mk-auto-elite' ),
					'adding'      => __( 'Adding...', 'mk-auto-elite' ),
					'emptyCart'   => __( 'Your cart is empty.', 'mk-auto-elite' ),
					'cartError'   => __( 'Something went wrong. Please try again.', 'mk-auto-elite' ),
				),
			)
		);

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}

	/**
	 * Trim front-end assets that hurt speed without benefiting this theme.
	 * Runs late (priority 99) so it can dequeue what WooCommerce/core enqueued.
	 */
	public function optimize_assets(): void {
		if ( is_admin() ) {
			return;
		}

		// WooCommerce cart fragments fire an admin-ajax request on every page
		// load. The header cart count is rendered server-side, so this is only
		// needed on cart/checkout. Removing it elsewhere is a big real-world win.
		if ( function_exists( 'is_cart' ) && ! is_cart() && ! is_checkout() ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}

		// The homepage template is fully custom (no blocks), so the block-editor
		// stylesheets are dead weight on the most important (LCP) page.
		if ( is_front_page() ) {
			foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'wc-blocks-style', 'global-styles', 'classic-theme-styles' ) as $handle ) {
				wp_dequeue_style( $handle );
			}
		}

		// Dashicons are only used by the admin bar; drop them for logged-out users.
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	}

	/**
	 * Preconnect / preload critical resources.
	 */
	public function resource_hints(): void {
		$fonts = array(
			'/assets/fonts/inter-variable.woff2',
			'/assets/fonts/sora-variable.woff2',
		);
		foreach ( $fonts as $font ) {
			if ( file_exists( MK_AUTO_ELITE_DIR . $font ) ) {
				printf(
					'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
					esc_url( MK_AUTO_ELITE_URI . $font )
				);
			}
		}

		// Preload the homepage hero image as the likely LCP element so it paints
		// as early as possible.
		if ( is_front_page() ) {
			$hero = Customizer::get( 'hero_image', '' );
			if ( '' === $hero ) {
				$hero = MK_AUTO_ELITE_URI . '/assets/images/hero.jpg';
			}
			printf(
				'<link rel="preload" href="%s" as="image" fetchpriority="high">' . "\n",
				esc_url( $hero )
			);
		}
	}

	/**
	 * Strip front-end bloat WordPress prints by default: the emoji detection
	 * script and styles (unneeded with modern browsers and our own fonts).
	 */
	public function perf_cleanup(): void {
		if ( is_admin() ) {
			return;
		}
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * Drop jQuery Migrate from the front-end jQuery bundle. This theme's scripts
	 * are dependency-free, so the legacy shim is dead weight.
	 *
	 * @param \WP_Scripts $scripts Scripts registry.
	 */
	public function trim_jquery_migrate( $scripts ): void {
		if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
			return;
		}
		$deps = $scripts->registered['jquery']->deps;
		if ( ! empty( $deps ) ) {
			$scripts->registered['jquery']->deps = array_diff( $deps, array( 'jquery-migrate' ) );
		}
	}

	/**
	 * Inline an above-the-fold critical-CSS payload so the header + hero paint
	 * correctly before the deferred main stylesheet loads (protects LCP/CLS).
	 */
	public function critical_css(): void {
		$css = ":root{--mk-red:#CA1D24;--mk-red-600:#A81820;--mk-black:#0B0B0B;--mk-carbon:#16181B;--mk-line:#E5E7EB;--mk-ink-900:#111214;--mk-container:1200px}"
			. "*{box-sizing:border-box}"
			. "body{margin:0;font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:var(--mk-ink-900);background:#fff;line-height:1.55}"
			. "img{max-width:100%;height:auto}a{color:inherit;text-decoration:none}"
			. "h1,h2,h3{font-family:'Sora','Inter',system-ui,sans-serif;margin:0 0 .5em;line-height:1.15}"
			. ".mkae-skip-link,.screen-reader-text{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}"
			. ".mkae-container{width:100%;max-width:var(--mk-container);margin:0 auto;padding:0 20px}"
			. ".mkae-topbar{background:var(--mk-carbon);color:#d6d9dd;font-size:.82rem}"
			. ".mkae-topbar__inner{display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;padding-block:8px}"
			. ".mkae-topbar a{color:#fff}"
			. ".mkae-header{position:sticky;top:0;z-index:100;background:#fff;box-shadow:0 1px 3px rgba(11,11,11,.06)}"
			. ".mkae-header__inner{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:24px;min-height:76px}"
			. ".mkae-header .custom-logo{max-height:52px;width:auto}"
			. ".mkae-nav{border-top:1px solid var(--mk-line);background:#fff}"
			. ".mkae-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:600;border-radius:8px;padding:12px 20px;border:0;cursor:pointer;line-height:1.1}"
			. ".mkae-btn--primary{background:var(--mk-red);color:#fff}"
			. ".mkae-hero{position:relative;overflow:hidden;isolation:isolate;background:var(--mk-black);color:#fff;padding:64px 0;min-height:clamp(420px,58vh,620px);display:flex;align-items:center}"
			. ".mkae-hero__media{position:absolute;inset:0;z-index:0}"
			. ".mkae-hero__video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}"
			. ".mkae-hero__media::before{content:'';position:absolute;inset:0;background-image:var(--mkae-hero-img);background-size:cover;background-position:center;z-index:0}"
			. ".mkae-hero__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1}"
			. ".mkae-hero__overlay{position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,rgba(11,11,11,.58),rgba(11,11,11,.14))}"
			. ".mkae-hero__inner{position:relative;z-index:2;width:100%;display:grid;gap:40px;align-items:center}"
			. ".mkae-hero__title{color:#fff;font-size:clamp(1.8rem,4vw,3rem);text-shadow:0 2px 14px rgba(0,0,0,.6),0 1px 3px rgba(0,0,0,.5)}"
			. ".mkae-hero__sub{color:#eef1f4;font-size:1.1rem;text-shadow:0 1px 8px rgba(0,0,0,.6)}"
			. "@media(min-width:1024px){.mkae-hero__inner{grid-template-columns:1.1fr 1fr}}";
		echo '<style id="mkae-critical">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
