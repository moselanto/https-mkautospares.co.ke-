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
