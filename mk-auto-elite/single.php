<?php
/**
 * Single post template.
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
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'mkae-single' ); ?>>
				<header class="mkae-page-head">
					<h1 class="mkae-page-title"><?php the_title(); ?></h1>
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="mkae-single__thumb"><?php the_post_thumbnail( 'mkae-hero' ); ?></figure>
				<?php endif; ?>
				<div class="mkae-entry"><?php the_content(); ?></div>
			</article>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
