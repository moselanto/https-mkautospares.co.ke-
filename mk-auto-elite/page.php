<?php
/**
 * Single page template (About, Contact, Policies, etc.).
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
			<article <?php post_class( 'mkae-page' ); ?>>
				<header class="mkae-page-head">
					<h1 class="mkae-page-title"><?php the_title(); ?></h1>
				</header>
				<div class="mkae-entry">
					<?php
					the_content();
					wp_link_pages( array( 'before' => '<nav class="mkae-pagelinks">', 'after' => '</nav>' ) );
					?>
				</div>
			</article>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</main>
</div>
<?php
get_footer();
