<?php
/**
 * Front-end security hardening.
 *
 * Theme-level defences: XML-RPC/pingback off, author-enumeration blocked,
 * REST user endpoints hidden from guests, information-leaking head tags removed,
 * generic login errors, security response headers, reduced comment/ping spam
 * surface, and a theme-served robots.txt. Server-level rules (.htaccess bad-bot
 * shield, HSTS, gzip/cache) complement these and ship in the theme readme.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers hardening hooks. The constructor only wires hooks (side-effect free
 * until WordPress fires them).
 */
class Security {

	/**
	 * Hook in.
	 */
	public function __construct() {
		// XML-RPC + pingback (defence in depth; the host may already block it).
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
		add_filter( 'xmlrpc_methods', array( $this, 'remove_pingback_methods' ) );
		add_action( 'init', array( $this, 'block_xmlrpc_request' ), 1 );

		// Author / user enumeration.
		add_action( 'template_redirect', array( $this, 'block_author_enumeration' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'remove_users_sitemap' ), 10, 2 );
		add_filter( 'rest_endpoints', array( $this, 'lock_rest_users' ) );

		// Head clean-up: hide version/paths and drop unused discovery links.
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		add_filter( 'the_generator', '__return_empty_string' );

		// Login hardening: never reveal whether a username exists.
		add_filter( 'login_errors', array( $this, 'generic_login_error' ) );

		// Security response headers on front-end requests.
		add_action( 'send_headers', array( $this, 'security_headers' ) );

		// Comment / ping spam surface: keep WooCommerce reviews, close the rest.
		add_filter( 'comments_open', array( $this, 'restrict_comments' ), 10, 2 );
		add_filter( 'pings_open', '__return_false' );

		// Theme-served robots.txt (used only when no physical file exists).
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 10, 2 );
	}

	/**
	 * Remove the X-Pingback response header.
	 *
	 * @param array<string,string> $headers Headers.
	 * @return array<string,string>
	 */
	public function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Strip pingback methods from the XML-RPC interface.
	 *
	 * @param array<string,mixed> $methods XML-RPC methods.
	 * @return array<string,mixed>
	 */
	public function remove_pingback_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Refuse direct xmlrpc.php requests with a 403, even if the server has not
	 * blocked the file.
	 */
	public function block_xmlrpc_request(): void {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			exit;
		}
	}

	/**
	 * Block front-end author enumeration (?author=N and /author/ archives) for
	 * logged-out visitors by redirecting home with a 301.
	 */
	public function block_author_enumeration(): void {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		$scan = is_author();
		if ( ! $scan && isset( $_GET['author'] ) && '' !== $_GET['author'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$scan = true;
		}
		if ( $scan ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Remove the users provider from the core sitemap so authors are not listed.
	 *
	 * @param mixed  $provider Provider instance.
	 * @param string $name     Provider name.
	 * @return mixed False for the users provider, unchanged otherwise.
	 */
	public function remove_users_sitemap( $provider, $name ) {
		return ( 'users' === $name ) ? false : $provider;
	}

	/**
	 * Hide REST user endpoints from logged-out visitors (blocks
	 * /wp-json/wp/v2/users enumeration) while leaving the rest of the REST API
	 * and authenticated access intact.
	 *
	 * @param array<string,mixed> $endpoints Registered REST endpoints.
	 * @return array<string,mixed>
	 */
	public function lock_rest_users( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $route ) {
			if ( isset( $endpoints[ $route ] ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Generic login error (no username/password disclosure).
	 */
	public function generic_login_error(): string {
		return __( 'The username or password you entered is incorrect.', 'mk-auto-elite' );
	}

	/**
	 * Send hardening response headers on front-end requests.
	 */
	public function security_headers(): void {
		if ( is_admin() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	}

	/**
	 * Keep comments open only for WooCommerce product reviews; close everywhere
	 * else to remove the blog/page comment-spam surface.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function restrict_comments( $open, $post_id ) {
		return ( 'product' === get_post_type( $post_id ) ) ? $open : false;
	}

	/**
	 * Theme-served robots.txt. WordPress uses this only when no physical
	 * robots.txt exists in the web root. Blocks noisy AI/scraper bots and keeps
	 * all crawlers out of cart/checkout/account and faceted (filter) URLs.
	 *
	 * @param string $output Default robots output.
	 * @param bool   $public Whether the site is set to public.
	 * @return string
	 */
	public function robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output; // Respect the "discourage search engines" setting.
		}
		$lines = array(
			'# AI / scraper bots',
			'User-agent: GPTBot',
			'Disallow: /',
			'',
			'User-agent: ClaudeBot',
			'Disallow: /',
			'',
			'User-agent: CCBot',
			'Disallow: /',
			'',
			'User-agent: Bytespider',
			'Disallow: /',
			'',
			'User-agent: PetalBot',
			'Disallow: /',
			'',
			'User-agent: meta-externalagent',
			'Disallow: /',
			'',
			'# All other crawlers',
			'User-agent: *',
			'Disallow: /dev/',
			'Disallow: /cart/',
			'Disallow: /checkout/',
			'Disallow: /my-account/',
			'Disallow: /*?add-to-cart=',
			'Disallow: /*?add_to_wishlist=',
			'Disallow: /*?add_to_compare=',
			'Disallow: /*?orderby=',
			'',
			'Sitemap: ' . home_url( '/wp-sitemap.xml' ),
		);
		return implode( "\n", $lines ) . "\n";
	}
}
