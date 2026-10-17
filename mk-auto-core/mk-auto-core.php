<?php
/**
 * Plugin Name:       MK Auto Core
 * Plugin URI:        https://mkautospares.co.ke/
 * Description:       Site-owned features for MK Auto Spares that must survive a theme change: the branded staff sign-in screen and login hardening (lockout, bot traps, password policy, staff session expiry).
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Pimofy Digital
 * Author URI:        https://github.com/moselanto
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mk-auto-core
 *
 * @package MK_Auto_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MKAC_VERSION', '1.1.0' );
define( 'MKAC_FILE', __FILE__ );
define( 'MKAC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MKAC_URL', plugin_dir_url( __FILE__ ) );

/*
 * Why a plugin and not the theme: the sign-in screen and the login guard are
 * business features. If the store is ever re-themed they must keep working,
 * so they live here. The plugin reads contact details from the MK Auto Elite
 * Customizer (theme mods mkae_*) when that theme is active and falls back to
 * the built-in defaults below when it is not.
 */
require_once MKAC_DIR . 'includes/class-mkac-brand.php';
require_once MKAC_DIR . 'includes/class-mkac-login-brand.php';
require_once MKAC_DIR . 'includes/class-mkac-login-guard.php';

add_action(
	'plugins_loaded',
	static function () {
		new MKAC_Login_Brand();
		new MKAC_Login_Guard();
	}
);
