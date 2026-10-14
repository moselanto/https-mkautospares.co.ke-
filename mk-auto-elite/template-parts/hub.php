<?php
/**
 * Shared hub template for brand (mk_make) and model (mk_model) taxonomy
 * archives. Renders an editorial intro (term description), the vehicle finder,
 * the product grid for the term, and internal links across the silo.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$mkae_term     = get_queried_object();
$mkae_is_model = ( $mkae_term instanceof WP_Term && 'mk_model' === $mkae_term->taxonomy );
$mkae_name     = ( $mkae_term instanceof WP_Term ) ? $mkae_term->name : wp_strip_all_tags( get_the_archive_title() );
$mkae_heading  = $mkae_is_model
	/* translators: %s: vehicle model name. */
	? sprintf( __( '%s Spare Parts', 'mk-auto-elite' ), $mkae_name )
	/* translators: %s: vehicle make name. */
	: sprintf( __( '%s Spare Parts in Kenya', 'mk-auto-elite' ), $mkae_name );
?>
<main id="primary" class="mkae-hub">
	<div class="mkae-container">
		<header class="mkae-hub__head">
			<h1 class="mkae-hub__title"><?php echo esc_html( $mkae_heading ); ?></h1>
			<?php
			$mkae_desc = ( $mkae_term instanceof WP_Term ) ? term_description( $mkae_term ) : '';
			if ( $mkae_desc ) :
				?>
				<div class="mkae-hub__intro"><?php echo wp_kses_post( $mkae_desc ); ?></div>
			<?php endif; ?>
		</header>

		<section class="mkae-hub__finder">
			<?php echo do_shortcode( '[mk_vehicle_finder]' ); ?>
		</section>

		<section class="mkae-hub__products">
			<?php
			if ( have_posts() && function_exists( 'woocommerce_product_loop_start' ) ) {
				woocommerce_product_loop_start();
				while ( have_posts() ) {
					the_post();
					wc_get_template_part( 'content', 'product' );
				}
				woocommerce_product_loop_end();
				the_posts_pagination();
			} elseif ( have_posts() ) {
				echo '<ul class="products">';
				while ( have_posts() ) {
					the_post();
					printf(
						'<li class="product"><a href="%s">%s</a></li>',
						esc_url( get_permalink() ),
						esc_html( get_the_title() )
					);
				}
				echo '</ul>';
			} else {
				echo '<p class="mkae-hub__empty">' . esc_html__( 'No parts are listed for this vehicle yet. Message us on WhatsApp for a fast quote and availability.', 'mk-auto-elite' ) . '</p>';
			}
			?>
		</section>

		<section class="mkae-hub__links">
			<?php
			if ( $mkae_is_model ) {
				$mkae_makes = get_terms(
					array(
						'taxonomy'   => 'mk_make',
						'hide_empty' => false,
						'number'     => 12,
					)
				);
				if ( ! is_wp_error( $mkae_makes ) && ! empty( $mkae_makes ) ) {
					echo '<h2>' . esc_html__( 'Browse by make', 'mk-auto-elite' ) . '</h2><ul class="mkae-hub__chips">';
					foreach ( $mkae_makes as $mkae_make ) {
						printf( '<li><a href="%s">%s</a></li>', esc_url( get_term_link( $mkae_make ) ), esc_html( $mkae_make->name ) );
					}
					echo '</ul>';
				}
			} else {
				$mkae_models = get_terms(
					array(
						'taxonomy'   => 'mk_model',
						'hide_empty' => false,
						'number'     => 24,
					)
				);
				if ( ! is_wp_error( $mkae_models ) && ! empty( $mkae_models ) ) {
					echo '<h2>' . esc_html__( 'Popular models', 'mk-auto-elite' ) . '</h2><ul class="mkae-hub__chips">';
					foreach ( $mkae_models as $mkae_model ) {
						printf( '<li><a href="%s">%s</a></li>', esc_url( get_term_link( $mkae_model ) ), esc_html( $mkae_model->name ) );
					}
					echo '</ul>';
				}
			}
			?>
		</section>
	</div>
</main>
<?php
get_footer();
