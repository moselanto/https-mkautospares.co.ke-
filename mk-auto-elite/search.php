<?php
/**
 * Search results template.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="mkae-container mkae-layout">
	<main id="primary" class="mkae-main">
		<header class="mkae-page-head">
			<h1 class="mkae-page-title">
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Results for: %s', 'mk-auto-elite' ),
					'<span>' . esc_html( get_search_query() ) . '</span>'
				);
				?>
			</h1>
		</header>
		<?php if ( have_posts() ) : ?>
			<div class="mkae-post-list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'mkae-post-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="mkae-post-card__thumb" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'mkae-card' ); ?></a>
						<?php endif; ?>
						<h2 class="mkae-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div class="mkae-post-card__excerpt"><?php the_excerpt(); ?></div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<?php the_posts_pagination( array( 'mid_size' => 2 ) ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No results. Try the vehicle finder or a different keyword.', 'mk-auto-elite' ); ?></p>
			<?php get_search_form(); ?>
		<?php endif; ?>
	</main>
</div>
<?php
get_footer();
