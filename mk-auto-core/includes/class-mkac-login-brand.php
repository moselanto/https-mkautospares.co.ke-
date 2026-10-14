<?php
/**
 * Branded staff sign-in screen (wp-login.php).
 *
 * Split layout, matching the sign-in screens used on the other Pimofy Digital
 * builds: a dark brand panel on the left (headline, what staff can do once
 * signed in, contact line) and the WordPress form on the right under the MK
 * logo. Only presentation changes here: the form, its field names, nonces,
 * redirects and every core login action are left exactly as WordPress ships
 * them, so password managers, 2FA plugins and WooCommerce keep working.
 *
 * The panel is skipped for the interim (re-authentication) pop-up inside
 * wp-admin, which is too small for it.
 *
 * @package MK_Auto_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKAC_Login_Brand {

	public function __construct() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'login_headerurl', array( $this, 'logo_link' ) );
		add_filter( 'login_headertext', array( $this, 'logo_text' ) );
		add_filter( 'login_title', array( $this, 'document_title' ), 10, 2 );
		add_filter( 'login_body_class', array( $this, 'body_class' ) );
		add_action( 'login_header', array( $this, 'brand_panel' ) );
		add_filter( 'login_message', array( $this, 'heading' ) );
		add_action( 'login_footer', array( $this, 'help_block' ), 5 );
	}

	/** Current wp-login.php action (login, lostpassword, rp, register ...). */
	private static function action(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, picks a heading.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		return '' === $action ? 'login' : $action;
	}

	private static function is_interim(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only layout switch.
		return isset( $_REQUEST['interim-login'] );
	}

	public function enqueue(): void {
		wp_enqueue_style( 'mkac-login', MKAC_URL . 'assets/css/login.css', array(), MKAC_VERSION );

		$css = ':root{--mkac-accent:' . MKAC_Brand::accent() . ';--mkac-logo:url("' . esc_url_raw( MKAC_Brand::logo_url() ) . '");';

		// Reuse the theme's photography and self-hosted fonts when MK Auto Elite
		// is installed; fall back to a gradient and system fonts when it is not.
		$theme_dir = get_template_directory();
		$theme_uri = get_template_directory_uri();
		$hero      = '';
		foreach ( array( 'hero.webp', 'hero.jpg' ) as $file ) {
			if ( is_readable( $theme_dir . '/assets/images/' . $file ) ) {
				$hero = $theme_uri . '/assets/images/' . $file;
				break;
			}
		}
		$hero = (string) apply_filters( 'mkac_login_hero_image', $hero );
		if ( '' !== $hero ) {
			$css .= '--mkac-hero:url("' . esc_url_raw( $hero ) . '");';
		}
		$css .= '}';

		$fonts = array(
			'Inter' => 'inter-variable.woff2',
			'Sora'  => 'sora-variable.woff2',
		);
		foreach ( $fonts as $family => $file ) {
			if ( is_readable( $theme_dir . '/assets/fonts/' . $file ) ) {
				$css .= '@font-face{font-family:"' . $family . '";src:url("' . esc_url_raw( $theme_uri . '/assets/fonts/' . $file ) . '") format("woff2");font-weight:100 900;font-display:swap;}';
			}
		}

		wp_add_inline_style( 'mkac-login', $css );
	}

	public function logo_link(): string {
		return home_url( '/' );
	}

	public function logo_text(): string {
		/* translators: %s: site name */
		return sprintf( __( 'Go to %s', 'mk-auto-core' ), MKAC_Brand::get( 'name' ) );
	}

	/**
	 * Cleaner browser tab: "Sign in | MK Auto Spares" instead of
	 * "Log In &lsaquo; Site &#8212; WordPress".
	 *
	 * @param string $login_title Full title.
	 * @param string $title       Screen title (Log In, Lost Password ...).
	 */
	public function document_title( $login_title, $title ): string {
		$screen = ( 'Log In' === $title || __( 'Log In' ) === $title ) ? __( 'Sign in', 'mk-auto-core' ) : wp_strip_all_tags( (string) $title );
		return $screen . ' | ' . MKAC_Brand::get( 'name' );
	}

	/**
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public function body_class( $classes ): array {
		$classes   = (array) $classes;
		$classes[] = self::is_interim() ? 'mkac-interim' : 'mkac-login';
		return $classes;
	}

	/** Left-hand brand panel, printed straight after <body>. */
	public function brand_panel(): void {
		if ( self::is_interim() ) {
			return;
		}
		$name   = MKAC_Brand::get( 'name' );
		$points = (array) apply_filters(
			'mkac_login_points',
			array(
				__( 'Orders, M-Pesa Paybill payments and WhatsApp enquiries in one place', 'mk-auto-core' ),
				__( 'Update parts, vehicle fitment, OEM numbers, photos, prices and stock', 'mk-auto-core' ),
				__( 'Publish Knowledge Centre guides and fitment pages with SEO built in', 'mk-auto-core' ),
			)
		);
		$tel   = MKAC_Brand::tel_url();
		$phone = MKAC_Brand::get( 'phone' );
		?>
		<aside class="mkac-brand" aria-label="<?php echo esc_attr( $name ); ?>">
			<div class="mkac-brand__top">
				<span class="mkac-wordmark"><span class="mkac-wordmark__mk">MK</span> <?php esc_html_e( 'Auto Spares', 'mk-auto-core' ); ?></span>
				<span class="mkac-eyebrow"><?php esc_html_e( 'Genuine parts. Trusted fitment.', 'mk-auto-core' ); ?></span>
			</div>
			<div class="mkac-brand__body">
				<h2 class="mkac-brand__title"><?php echo esc_html( apply_filters( 'mkac_login_headline', __( 'Genuine spare parts, professionally managed.', 'mk-auto-core' ) ) ); ?></h2>
				<p class="mkac-brand__lead">
					<?php
					/* translators: %s: site name */
					echo esc_html( sprintf( __( 'Sign in to manage orders, parts, vehicle fitment, service bookings and Knowledge Centre guides for %s.', 'mk-auto-core' ), $name ) );
					?>
				</p>
				<ul class="mkac-brand__points">
					<?php foreach ( $points as $point ) : ?>
						<li><?php echo esc_html( (string) $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="mkac-brand__foot">
				<?php if ( $tel ) : ?>
					<a href="<?php echo esc_url( $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( MKAC_Brand::domain() ); ?></a>
				<span>&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . $name ); ?></span>
			</div>
		</aside>
		<?php
	}

	/**
	 * Heading above the form, worded for the current screen. Any message core
	 * or another plugin already set (e.g. "check your email") is kept below it.
	 *
	 * @param string $message Existing login message HTML.
	 */
	public function heading( $message ): string {
		if ( self::is_interim() ) {
			return (string) $message;
		}
		$name = MKAC_Brand::get( 'name' );
		switch ( self::action() ) {
			case 'lostpassword':
			case 'retrievepassword':
				$title = __( 'Reset your password', 'mk-auto-core' );
				$sub   = __( 'Enter your username or email address and we will send you a link to choose a new password.', 'mk-auto-core' );
				break;
			case 'rp':
			case 'resetpass':
				$title = __( 'Choose a new password', 'mk-auto-core' );
				$sub   = __( 'Use at least 10 characters. A passphrase is easiest to remember.', 'mk-auto-core' );
				break;
			case 'register':
				$title = __( 'Create an account', 'mk-auto-core' );
				/* translators: %s: site name */
				$sub   = sprintf( __( 'Register for a %s account.', 'mk-auto-core' ), $name );
				break;
			default:
				$title = __( 'Welcome back', 'mk-auto-core' );
				/* translators: %s: site name */
				$sub   = sprintf( __( 'Sign in to your %s account', 'mk-auto-core' ), $name );
		}
		$html = '<div class="mkac-heading"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $sub ) . '</p></div>';
		return $html . (string) $message;
	}

	/** Secure-connection note and WhatsApp help under the form. */
	public function help_block(): void {
		if ( self::is_interim() ) {
			return;
		}
		$wa = MKAC_Brand::whatsapp_url( __( 'Hello, I need help signing in to the website dashboard.', 'mk-auto-core' ) );
		?>
		<div class="mkac-help">
			<?php if ( is_ssl() ) : ?>
				<p class="mkac-secure"><span class="mkac-secure__dot" aria-hidden="true"></span><?php esc_html_e( 'Secure, encrypted sign-in', 'mk-auto-core' ); ?></p>
			<?php endif; ?>
			<?php if ( $wa ) : ?>
				<p class="mkac-wa"><?php esc_html_e( 'Need help signing in?', 'mk-auto-core' ); ?> <a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Chat with us on WhatsApp', 'mk-auto-core' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
