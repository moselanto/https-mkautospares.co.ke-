<?php
/**
 * Login guard: authentication, session and header hardening.
 *
 * Ported from the guard used on nurabeauty.co.ke so every Pimofy Digital build
 * behaves the same way. All thresholds are filterable; nothing core is removed.
 *
 *  1. Brute-force lockout per IP (5 failures / 15 min) and per username
 *     (10 failures / hour); a lockout lasts 30 minutes. Covers wp-login.php,
 *     WooCommerce My Account and anything else that calls wp_authenticate().
 *  2. Bot traps on login, registration and lost-password forms: a hidden
 *     honeypot field plus a minimum fill-time check. Forms without the fields
 *     (REST, mobile apps, application passwords) are not affected.
 *  3. Rate limits: 3 registrations and 5 password-reset requests per IP per hour.
 *  4. Password policy: new or reset passwords need 10+ characters and cannot be
 *     the username or email. Passwords are never stored or logged here.
 *  5. Staff sessions (edit_posts and up) expire after 8 hours, or 3 days with
 *     "Remember me". Customers keep the WordPress defaults.
 *  6. Security headers (and HSTS on HTTPS) on wp-login.php and wp-admin, which
 *     the theme's front-end header hook does not cover.
 *  7. Application passwords limited to administrators.
 *
 * The theme (MKAutoElite\Security) replaces every login error with one generic
 * sentence so usernames cannot be probed. That would also hide the lockout
 * notice, so this class re-applies the lockout wording after the theme filter,
 * and only when this request was actually refused for a lockout.
 *
 * Client IP: REMOTE_ADDR. Behind Cloudflare or another proxy, define
 * MKAC_TRUSTED_PROXY_HEADER (e.g. 'HTTP_CF_CONNECTING_IP') in wp-config.php,
 * otherwise every visitor shares the proxy's IP and one lockout hits everyone.
 *
 * @package MK_Auto_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKAC_Login_Guard {

	const IP_MAX           = 5;
	const USER_MAX         = 10;
	const WINDOW           = 900;
	const LOCKOUT          = 1800;
	const MIN_FILL_SECONDS = 1;
	const MIN_PASSWORD     = 10;

	/** Set when this request was refused because of a lockout. */
	private static bool $locked_now = false;

	public function __construct() {
		add_filter( 'authenticate', array( $this, 'block_if_locked' ), 30, 3 );
		add_action( 'wp_login_failed', array( $this, 'record_failure' ), 10, 1 );
		add_action( 'wp_login', array( $this, 'clear_failures' ), 10, 1 );
		add_filter( 'login_errors', array( $this, 'lockout_message' ), 99 );

		foreach ( array( 'login_form', 'register_form', 'lostpassword_form', 'woocommerce_login_form', 'woocommerce_register_form', 'woocommerce_lostpassword_form' ) as $hook ) {
			add_action( $hook, array( $this, 'trap_fields' ) );
		}
		add_filter( 'authenticate', array( $this, 'check_trap_on_login' ), 20, 3 );

		add_filter( 'registration_errors', array( $this, 'guard_registration' ), 10, 1 );
		add_filter( 'woocommerce_process_registration_errors', array( $this, 'guard_registration' ), 10, 1 );
		add_action( 'lostpassword_post', array( $this, 'guard_lostpassword' ), 10, 1 );

		add_action( 'validate_password_reset', array( $this, 'check_password_reset' ), 10, 2 );
		add_action( 'user_profile_update_errors', array( $this, 'check_profile_password' ), 10, 3 );
		add_action( 'woocommerce_save_account_details_errors', array( $this, 'check_account_password' ), 10, 2 );

		add_filter( 'auth_cookie_expiration', array( $this, 'session_length' ), 10, 3 );

		add_action( 'login_init', array( $this, 'security_headers' ) );
		add_action( 'admin_init', array( $this, 'security_headers' ) );

		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'app_passwords_for_admins' ), 10, 2 );
	}

	/* ------------------------------------------------------------ helpers */

	public static function ip(): string {
		$ip = '';
		if ( defined( 'MKAC_TRUSTED_PROXY_HEADER' ) && ! empty( $_SERVER[ MKAC_TRUSTED_PROXY_HEADER ] ) ) {
			$raw = explode( ',', (string) wp_unslash( $_SERVER[ MKAC_TRUSTED_PROXY_HEADER ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
			$ip  = trim( $raw[0] );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	private static function bump( string $key, int $ttl ): int {
		$n = (int) get_transient( $key ) + 1;
		set_transient( $key, $n, $ttl );
		return $n;
	}

	private static function ip_key( string $suffix ): string {
		return 'mkac_lg_' . $suffix . '_' . md5( self::ip() );
	}

	private static function user_key( string $suffix, $username ): string {
		return 'mkac_lg_' . $suffix . '_' . md5( strtolower( trim( (string) $username ) ) );
	}

	/* --------------------------------------------------- 1. lockout */

	public function block_if_locked( $user, $username, $password ) {
		if ( get_transient( self::ip_key( 'lock' ) ) || ( $username && get_transient( self::user_key( 'ulock', $username ) ) ) ) {
			self::$locked_now = true;
			return new WP_Error( 'mkac_locked', self::locked_text() );
		}
		return $user;
	}

	private static function locked_text(): string {
		return __( 'Too many failed sign-in attempts. Please wait 30 minutes or reset your password.', 'mk-auto-core' );
	}

	public function record_failure( $username ): void {
		if ( self::$locked_now ) {
			return; // Do not extend a lockout with attempts made while locked.
		}
		if ( self::bump( self::ip_key( 'fail' ), self::WINDOW ) >= (int) apply_filters( 'mkac_login_ip_max', self::IP_MAX ) ) {
			set_transient( self::ip_key( 'lock' ), 1, self::LOCKOUT );
		}
		if ( $username && self::bump( self::user_key( 'ufail', $username ), HOUR_IN_SECONDS ) >= (int) apply_filters( 'mkac_login_user_max', self::USER_MAX ) ) {
			set_transient( self::user_key( 'ulock', $username ), 1, self::LOCKOUT );
		}
	}

	public function clear_failures( $username ): void {
		delete_transient( self::ip_key( 'fail' ) );
		delete_transient( self::user_key( 'ufail', $username ) );
	}

	/** Show the lockout wording even though the theme genericises errors. */
	public function lockout_message( $error ) {
		return self::$locked_now ? esc_html( self::locked_text() ) : $error;
	}

	/* ------------------------------------------------- 2. bot traps */

	public function trap_fields(): void {
		echo '<p class="mkac-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
			. '<label>' . esc_html__( 'Leave this field empty', 'mk-auto-core' ) . '<input type="text" name="mkac_hp" value="" tabindex="-1" autocomplete="off"></label></p>'
			. '<input type="hidden" name="mkac_ts" value="' . esc_attr( (string) time() ) . '">';
	}

	private static function trap_tripped(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method ) {
			return false;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- anti-bot fields, not state changes.
		if ( ! empty( $_POST['mkac_hp'] ) ) {
			return true;
		}
		if ( isset( $_POST['mkac_ts'] ) ) {
			$ts = absint( $_POST['mkac_ts'] );
			if ( $ts && ( time() - $ts ) < self::MIN_FILL_SECONDS ) {
				return true;
			}
		}
		// phpcs:enable
		return false;
	}

	public function check_trap_on_login( $user, $username, $password ) {
		if ( self::trap_tripped() ) {
			return new WP_Error( 'mkac_bot', __( 'Invalid login details.', 'mk-auto-core' ) );
		}
		return $user;
	}

	/* ------------------------------- 3. registration + reset limits */

	public function guard_registration( $errors ) {
		if ( self::trap_tripped() ) {
			$errors->add( 'mkac_bot', __( 'Registration could not be completed. Please try again.', 'mk-auto-core' ) );
			return $errors;
		}
		$max = (int) apply_filters( 'mkac_registrations_per_hour', 3 );
		if ( self::bump( self::ip_key( 'reg' ), HOUR_IN_SECONDS ) > $max ) {
			$errors->add( 'mkac_rate', __( 'Too many accounts created from this connection. Please try again later.', 'mk-auto-core' ) );
		}
		return $errors;
	}

	public function guard_lostpassword( $errors ): void {
		if ( ! is_wp_error( $errors ) ) {
			return;
		}
		if ( self::trap_tripped() ) {
			$errors->add( 'mkac_bot', __( 'Please try again.', 'mk-auto-core' ) );
			return;
		}
		$max = (int) apply_filters( 'mkac_password_resets_per_hour', 5 );
		if ( self::bump( self::ip_key( 'lost' ), HOUR_IN_SECONDS ) > $max ) {
			$errors->add( 'mkac_rate', __( 'Too many reset requests. Please try again in an hour.', 'mk-auto-core' ) );
		}
	}

	/* --------------------------------------------- 4. password policy */

	private static function password_problem( $pass, $user = null ): string {
		$pass = (string) $pass;
		if ( '' === $pass ) {
			return '';
		}
		if ( strlen( $pass ) < self::MIN_PASSWORD ) {
			/* translators: %d: minimum length */
			return sprintf( __( 'Please use a password of at least %d characters.', 'mk-auto-core' ), self::MIN_PASSWORD );
		}
		if ( $user instanceof WP_User ) {
			$lower = strtolower( $pass );
			if ( strtolower( $user->user_login ) === $lower || strtolower( $user->user_email ) === $lower ) {
				return __( 'Your password cannot be your username or email address.', 'mk-auto-core' );
			}
		}
		return '';
	}

	public function check_password_reset( $errors, $user ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- core verifies the reset key; passwords must not be altered.
		$pass = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$msg  = self::password_problem( $pass, $user instanceof WP_User ? $user : null );
		if ( $msg ) {
			$errors->add( 'mkac_weak_password', $msg );
		}
	}

	public function check_profile_password( $errors, $update, $user ): void {
		$pass = isset( $user->user_pass ) ? $user->user_pass : '';
		$wpu  = ! empty( $user->ID ) ? get_userdata( $user->ID ) : null;
		$msg  = self::password_problem( $pass, $wpu ? $wpu : null );
		if ( $msg ) {
			$errors->add( 'mkac_weak_password', $msg );
		}
	}

	public function check_account_password( $errors, $user ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verifies the nonce; passwords must not be altered.
		$pass = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
		$wpu  = ( is_object( $user ) && ! empty( $user->ID ) ) ? get_userdata( $user->ID ) : null;
		$msg  = self::password_problem( $pass, $wpu ? $wpu : null );
		if ( $msg ) {
			$errors->add( 'mkac_weak_password', $msg );
		}
	}

	/* ---------------------------------------------- 5. staff sessions */

	public function session_length( $length, $user_id, $remember ) {
		if ( $user_id && user_can( $user_id, 'edit_posts' ) ) {
			return $remember
				? (int) apply_filters( 'mkac_staff_session_remember', 3 * DAY_IN_SECONDS )
				: (int) apply_filters( 'mkac_staff_session', 8 * HOUR_IN_SECONDS );
		}
		return $length;
	}

	/* ------------------------------------------------------ 6. headers */

	public function security_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}
	}

	/* ------------------------------------- 7. application passwords */

	public function app_passwords_for_admins( $available, $user ) {
		return $available && $user instanceof WP_User && user_can( $user, 'manage_options' );
	}
}
