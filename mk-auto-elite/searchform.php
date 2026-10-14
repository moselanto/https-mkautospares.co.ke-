<?php
/**
 * Predictive search form. JS in main.js enhances this with AJAX autocomplete.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mkae_id = 'mkae-search-' . wp_unique_id();
?>
<form role="search" method="get" class="mkae-search" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-mkae-search>
	<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
		<input type="hidden" name="post_type" value="product">
	<?php endif; ?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $mkae_id ); ?>"><?php esc_html_e( 'Search for parts', 'mk-auto-elite' ); ?></label>
	<input
		id="<?php echo esc_attr( $mkae_id ); ?>"
		type="search"
		role="combobox"
		aria-haspopup="listbox"
		class="mkae-search__input"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search part name, SKU, OEM or vehicle...', 'mk-auto-elite' ); ?>"
		autocomplete="off"
		aria-autocomplete="list"
		aria-expanded="false"
		aria-controls="<?php echo esc_attr( $mkae_id ); ?>-results">
	<button type="submit" class="mkae-search__submit"><?php esc_html_e( 'Search', 'mk-auto-elite' ); ?></button>
	<div id="<?php echo esc_attr( $mkae_id ); ?>-results" class="mkae-search__results" role="listbox" hidden></div>
</form>
