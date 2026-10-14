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
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="mkae-article__media"><?php the_post_thumbnail( 'mkae-hero' ); ?></figure>
				<?php endif; ?>
				<div class="mkae-article__content">
					<?php the_content(); ?>
				</div>
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
