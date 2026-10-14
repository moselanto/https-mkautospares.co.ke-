<?php
/**
 * Recommended-plugin activation (lightweight, TGMPA-compatible).
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nudges the admin to install/activate required plugins after theme activation.
 * WooCommerce is required; the rest are recommended. This avoids bundling the
 * full TGMPA library while providing the same guided install experience.
 */
class PluginActivation {

	/**
	 * Recommended plugins: slug => [name, required].
	 *
	 * @var array<string,array{0:string,1:bool}>
	 */
	private array $plugins = array(
		'woocommerce'                    => array( 'WooCommerce', true ),
		'contact-form-7'                 => array( 'Contact Form 7', false ),
		'wordpress-seo'                  => array( 'Yoast SEO', false ),
	);

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Show an actionable notice when required plugins are missing.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		if ( class_exists( 'WooCommerce' ) ) {
			return; // Core requirement satisfied.
		}

		$install_url = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
			'install-plugin_woocommerce'
		);

		echo '<div class="notice notice-warning is-dismissible"><p><strong>';
		echo esc_html__( 'MK Auto Elite needs WooCommerce.', 'mk-auto-elite' );
		echo '</strong> ';
		echo esc_html__( 'Install and activate WooCommerce to enable the shop, vehicle finder, and WhatsApp ordering.', 'mk-auto-elite' );
		echo ' <a class="button button-primary" href="' . esc_url( $install_url ) . '">';
		echo esc_html__( 'Install WooCommerce', 'mk-auto-elite' );
		echo '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=mk-auto-elite' ) ) . '">';
		echo esc_html__( 'Open Setup Wizard', 'mk-auto-elite' );
		echo '</a></p></div>';
	}

	/**
	 * Expose the recommended plugin list (used by the setup wizard).
	 *
	 * @return array<string,array{0:string,1:bool}>
	 */
	public function plugins(): array {
		return $this->plugins;
	}
}
