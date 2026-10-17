<?php
/**
 * Single Knowledge Centre guide.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="mkae-article">
	<div class="mkae-container mkae-article__container">
		<?php
		while ( have_posts() ) {
			the_post();
			?>
			<article <?php post_class( 'mkae-article__body' ); ?>>
				<header class="mkae-article__head">
					<h1 class="mkae-article__title"><?php the_title(); ?></h1>
					<?php $mkae_words = str_word_count( wp_strip_all_tags( (string) get_the_content() ) ); ?>
					<p class="mkae-article__meta">
						<span><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Updated %s', 'mk-auto-elite' ), get_the_modified_date() ) ); ?></span>
						<span><?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min read', 'mk-auto-elite' ), max( 1, (int) ceil( $mkae_words / 220 ) ) ) ); ?></span>
						<span><?php esc_html_e( 'By the MK Auto Spares parts team', 'mk-auto-elite' ); ?></span>
					</p>
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="mkae-article__media"><?php the_post_thumbnail( 'mkae-hero' ); ?></figure>
				<?php endif; ?>
				<div class="mkae-article__content">
					<?php the_content(); ?>
				</div>
				<?php
				$mkae_terms   = wp_get_post_terms( get_the_ID(), 'mkae_guide_topic', array( 'fields' => 'ids' ) );
				$mkae_rel_arg = array(
					'post_type'           => 'mkae_guide',
					'posts_per_page'      => 4,
					'post__not_in'        => array( get_the_ID() ),
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'rand',
				);
				if ( ! is_wp_error( $mkae_terms ) && $mkae_terms ) {
					$mkae_rel_arg['tax_query'] = array( array( 'taxonomy' => 'mkae_guide_topic', 'field' => 'term_id', 'terms' => $mkae_terms ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				}
				$mkae_rel = new WP_Query( $mkae_rel_arg );
				if ( $mkae_rel->have_posts() ) :
					?>
					<nav class="mkae-related" aria-label="<?php esc_attr_e( 'Related guides', 'mk-auto-elite' ); ?>">
						<h2><?php esc_html_e( 'Related guides', 'mk-auto-elite' ); ?></h2>
						<ul>
							<?php
							while ( $mkae_rel->have_posts() ) :
								$mkae_rel->the_post();
								?>
								<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
							<?php endwhile; ?>
						</ul>
						<p><a class="mkae-related__all" href="<?php echo esc_url( get_post_type_archive_link( 'mkae_guide' ) ); ?>"><?php esc_html_e( 'All Knowledge Centre guides', 'mk-auto-elite' ); ?></a></p>
					</nav>
					<?php
				endif;
				wp_reset_postdata();
				?>
			</article>
			<aside class="mkae-article__cta">
				<h2><?php esc_html_e( 'Need this part?', 'mk-auto-elite' ); ?></h2>
				<p><?php esc_html_e( 'Find the right fit for your car, or get a quick WhatsApp quote.', 'mk-auto-elite' ); ?></p>
				<?php echo do_shortcode( '[mk_vehicle_finder]' ); ?>
			</aside>
			<?php
		}
		?>
	</div>
</main>
<?php
get_footer();
