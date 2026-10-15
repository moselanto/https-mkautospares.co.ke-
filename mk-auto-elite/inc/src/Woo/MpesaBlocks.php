<?php
/**
 * Block checkout integration for the M-Pesa Paybill gateway.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Woo;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the gateway to the Checkout block.
 */
final class MpesaBlocks extends AbstractPaymentMethodType {

	/** @var string */
	protected $name = 'mkae_mpesa';

	/**
	 * Load settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_mkae_mpesa_settings', array() );
	}

	/**
	 * Active unless disabled in settings.
	 */
	public function is_active() {
		return 'no' !== ( $this->settings['enabled'] ?? 'yes' );
	}

	/**
	 * Script handles.
	 */
	public function get_payment_method_script_handles() {
		$rel = '/assets/js/mpesa-blocks.js';
		wp_register_script(
			'mkae-mpesa-blocks',
			MK_AUTO_ELITE_URI . $rel,
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			(string) filemtime( MK_AUTO_ELITE_DIR . $rel ),
			true
		);
		return array( 'mkae-mpesa-blocks' );
	}

	/**
	 * Data for the script.
	 */
	public function get_payment_method_data() {
		$gw = new MpesaGateway();
		return array(
			'title'       => $gw->title,
			'description' => '<p>' . esc_html( $gw->description ) . '</p>' . $gw->steps(),
			'supports'    => array( 'products' ),
		);
	}
}
