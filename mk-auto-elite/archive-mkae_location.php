<?php
/**
 * Locations archive (towns / counties we serve).
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="mkae-archive">
	<div class="mkae-container">
		<header class="mkae-archive__head">
			<h1 class="mkae-archive__title"><?php esc_html_e( 'Where We Deliver', 'mk-auto-elite' ); ?></h1>
			<p class="mkae-archive__intro"><?php esc_html_e( 'Genuine and ex-Japan car spare parts delivered across Kenya. Find your town below.', 'mk-auto-elite' ); ?></p>
		</header>
		<?php if ( have_posts() ) : ?>
			<div class="mkae-archive__grid">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'mkae-card mkae-card--article' ); ?>>
						<a class="mkae-card__link" href="<?php the_permalink(); ?>">
							<span class="mkae-card__title"><?php the_title(); ?></span>
							<span class="mkae-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></span>
						</a>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Location pages are coming soon.', 'mk-auto-elite' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
