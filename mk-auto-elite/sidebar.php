<?php
/**
 * Sidebar (shop / blog).
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_active_sidebar( 'mkae-shop' ) ) {
	return;
}
?>
<aside id="secondary" class="mkae-sidebar" aria-label="<?php esc_attr_e( 'Filters', 'mk-auto-elite' ); ?>">
	<?php dynamic_sidebar( 'mkae-shop' ); ?>
</aside>
