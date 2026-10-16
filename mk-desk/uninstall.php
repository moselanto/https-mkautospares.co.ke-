<?php
/**
 * Uninstall: business records (sales, plans, bookings, supplier ledger) are
 * deliberately kept. Delete the mkd_* tables by hand only after an export.
 *
 * @package MK_Desk
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
