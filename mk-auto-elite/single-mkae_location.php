<?php
/**
 * Single location / county page.
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="mkae-article mkae-location">
	<div class="mkae-container mkae-article__container">
		<?php
		while ( have_posts() ) {
			the_post();
			?>
			<article <?php post_class( 'mkae-article__body' ); ?>>
				<header class="mkae-article__head">
					<h1 class="mkae-article__title"><?php the_title(); ?></h1>
				</header>
				<div class="mkae-article__content">
					<?php the_content(); ?>
				</div>
			</article>
			<aside class="mkae-article__cta">
				<h2><?php esc_html_e( 'Order parts to your area', 'mk-auto-elite' ); ?></h2>
				<p><?php esc_html_e( 'Tell us your vehicle and we will confirm the right parts, price and delivery.', 'mk-auto-elite' ); ?></p>
				<?php echo do_shortcode( '[mk_vehicle_finder]' ); ?>
				<?php echo do_shortcode( '[mkae_directions]' ); ?>
			</aside>
			<?php
		}
		?>
	</div>
</main>
<?php
get_footer();
