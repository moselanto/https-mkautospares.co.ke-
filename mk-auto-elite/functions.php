<?php
/**
 * MK Auto Elite - theme bootstrap.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

// Minimum environment guard. Fails gracefully instead of white-screening.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'MK Auto Elite requires PHP 8.1 or higher. Please update PHP to activate all features.', 'mk-auto-elite' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'MK_AUTO_ELITE_VERSION', '2.19.3' );
define( 'MK_AUTO_ELITE_DIR', get_template_directory() );
define( 'MK_AUTO_ELITE_URI', get_template_directory_uri() );

// PSR-4 autoloader for the MKAutoElite namespace.
require_once MK_AUTO_ELITE_DIR . '/inc/autoload.php';

/**
 * Boot the theme.
 *
 * @return \MKAutoElite\Theme
 */
function mk_auto_elite() {
	return \MKAutoElite\Theme::instance();
}
mk_auto_elite();
