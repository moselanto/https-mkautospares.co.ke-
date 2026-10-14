<?php
/**
 * Fallback template for all archives and the blog index.
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
		<?php if ( have_posts() ) : ?>
			<header class="mkae-page-head">
				<h1 class="mkae-page-title"><?php echo esc_html( wp_get_document_title() ); ?></h1>
			</header>
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
