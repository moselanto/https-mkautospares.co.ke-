<?php
/**
 * Archive template (fallback for non-WooCommerce archives).
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
			<?php the_archive_title( '<h1 class="mkae-page-title">', '</h1>' ); ?>
			<?php the_archive_description( '<div class="mkae-archive-desc">', '</div>' ); ?>
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
			<p><?php esc_html_e( 'Nothing found.', 'mk-auto-elite' ); ?></p>
		<?php endif; ?>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
