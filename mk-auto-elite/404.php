<?php
/**
 * 404 template.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="mkae-container mkae-404">
	<h1 class="mkae-404__code">404</h1>
	<h2 class="mkae-404__title"><?php esc_html_e( 'Part not found', 'mk-auto-elite' ); ?></h2>
	<p><?php esc_html_e( 'The page you are looking for has moved or no longer exists. Try searching for the part instead.', 'mk-auto-elite' ); ?></p>
	<?php get_search_form(); ?>
	<p><a class="mkae-btn mkae-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'mk-auto-elite' ); ?></a></p>
</div>
<?php
get_footer();
