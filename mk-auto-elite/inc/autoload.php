<?php
/**
 * PSR-4 autoloader.
 *
 * Maps the MKAutoElite\ namespace to /inc/src/.
 * Example: MKAutoElite\Woo\WooCommerce -> /inc/src/Woo/WooCommerce.php
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'MKAutoElite\\';
		$length = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $length ) ) {
			return;
		}

		$relative = substr( $class, $length );
		$path     = MK_AUTO_ELITE_DIR . '/inc/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);
