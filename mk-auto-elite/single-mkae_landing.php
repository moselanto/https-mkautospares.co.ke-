<?php
/**
 * Single fitment landing page (model x part).
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) {
	the_post();
	$mkae_id       = get_the_ID();
	$mkae_make     = \MKAutoElite\Landing::get( $mkae_id, 'make' );
	$mkae_model    = \MKAutoElite\Landing::get( $mkae_id, 'model' );
	$mkae_part     = \MKAutoElite\Landing::get( $mkae_id, 'part' );
	$mkae_oem      = \MKAutoElite\Landing::get( $mkae_id, 'oem' );
	$mkae_price    = \MKAutoElite\Landing::get( $mkae_id, 'price' );
	$mkae_symptoms = array_filter( array_map( 'trim', explode( "\n", \MKAutoElite\Landing::get( $mkae_id, 'symptoms' ) ) ) );
	$mkae_faq      = array_filter( array_map( 'trim', explode( "\n", \MKAutoElite\Landing::get( $mkae_id, 'faq' ) ) ) );
	?>
	<main id="primary" class="mkae-article mkae-landing">
		<div class="mkae-container mkae-article__container">
			<article class="mkae-article__body">
				<header class="mkae-article__head">
					<h1 class="mkae-article__title"><?php the_title(); ?></h1>
					<?php if ( '' !== $mkae_price ) : ?>
						<?php /* translators: %s: price, e.g. KES 3,500. */ ?>
						<p class="mkae-landing__price"><?php echo esc_html( sprintf( __( 'From %s', 'mk-auto-elite' ), $mkae_price ) ); ?></p>
					<?php endif; ?>
				</header>

				<div class="mkae-article__content">
					<?php the_content(); ?>
				</div>

				<?php if ( ! empty( $mkae_symptoms ) ) : ?>
					<section class="mkae-landing__symptoms">
						<?php /* translators: 1: model, 2: part label. */ ?>
						<h2><?php echo esc_html( sprintf( __( 'Signs your %1$s %2$s needs replacing', 'mk-auto-elite' ), $mkae_model, strtolower( $mkae_part ) ) ); ?></h2>
						<ul>
							<?php foreach ( $mkae_symptoms as $mkae_s ) : ?>
								<li><?php echo esc_html( $mkae_s ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( '' !== $mkae_oem ) : ?>
					<section class="mkae-landing__oem">
						<h2><?php esc_html_e( 'Part numbers and fitment', 'mk-auto-elite' ); ?></h2>
						<p><?php echo esc_html( $mkae_oem ); ?></p>
					</section>
				<?php endif; ?>

				<section class="mkae-landing__products">
					<h2><?php esc_html_e( 'Available parts', 'mk-auto-elite' ); ?></h2>
					<?php echo \MKAutoElite\Landing::products_html( $mkae_make, $mkae_model, $mkae_part ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>

				<?php if ( ! empty( $mkae_faq ) ) : ?>
					<section class="mkae-landing__faq">
						<h2><?php esc_html_e( 'Frequently asked questions', 'mk-auto-elite' ); ?></h2>
						<?php
						foreach ( $mkae_faq as $mkae_line ) {
							$mkae_pair = explode( '|', $mkae_line, 2 );
							if ( count( $mkae_pair ) < 2 ) {
								continue;
							}
							printf(
								'<details class="mkae-faq__item"><summary>%s</summary><p>%s</p></details>',
								esc_html( trim( $mkae_pair[0] ) ),
								esc_html( trim( $mkae_pair[1] ) )
							);
						}
						?>
					</section>
				<?php endif; ?>
			</article>

			<aside class="mkae-article__cta">
				<h2><?php esc_html_e( 'Get a fast quote', 'mk-auto-elite' ); ?></h2>
				<p><?php esc_html_e( 'Confirm fitment and price on WhatsApp, or use the finder below.', 'mk-auto-elite' ); ?></p>
				<?php echo do_shortcode( '[mk_vehicle_finder]' ); ?>
			</aside>
		</div>
	</main>
	<?php
}

get_footer();
