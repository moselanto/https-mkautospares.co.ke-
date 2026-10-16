<?php
/**
 * Plugin Name:       MKauto Desk
 * Plugin URI:        https://mkautospares.co.ke/
 * Description:       The back office for MK Auto Spares: point of sale with neighbour sourcing, WhatsApp cart orders, payment plans, garage and service bookings with a public booking page, reminders, follow-ups, marketing, social catalogue, team permissions with an activity log, encrypted customer data and branded Excel reports. Requires WooCommerce.
 * Version:           1.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 * Author:            Pimofy Digital
 * Author URI:        https://github.com/moselanto
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mk-desk
 *
 * @package MK_Desk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MKD_VERSION', '1.3.0' );
define( 'MKD_FILE', __FILE__ );
define( 'MKD_DIR', plugin_dir_path( __FILE__ ) );
define( 'MKD_URL', plugin_dir_url( __FILE__ ) );

/*
 * Why a plugin and not the theme: sales, plans, bookings and the supplier
 * ledger are business records. They must survive a re-theme, so they live
 * here, next to MK Auto Core. Brand values (phone, WhatsApp, accent) are read
 * from the MK Auto Elite Customizer when it is active.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-warning"><p><strong>MKauto Desk</strong> needs WooCommerce to be active.</p></div>';
				}
			);
			return;
		}
		foreach ( array( 'whatsapp', 'xlsx', 'vault', 'staff', 'wa-order', 'followup', 'desk', 'desk-plans', 'desk-appts', 'desk-orders', 'desk-remind', 'desk-pos', 'desk-source', 'desk-mkt', 'desk-social', 'desk-subscribe', 'garage' ) as $mkd_file ) {
			require_once MKD_DIR . 'includes/class-mkd-' . $mkd_file . '.php';
		}
		new MKD_WA_Order();
		new MKD_Desk();
		MKD_Garage::hooks();
		MKD_Followup::instance()->boot();
	},
	20
);
