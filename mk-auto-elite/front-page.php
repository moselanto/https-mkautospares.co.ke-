<?php
/**
 * Homepage: hero + vehicle finder, categories, brands, featured products,
 * trust band, FAQ, newsletter. Fully dynamic (not a static page).
 *
 * @package MK_Auto_Elite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$finder = function_exists( 'mk_auto_elite' ) ? mk_auto_elite()->module( 'finder' ) : null;
$is_woo = function_exists( 'wc_get_page_permalink' );

// Hero background media. A bundled mechanic/spare-parts image is used by
// default (with a subtle motion effect); the user can override with their own
// image/GIF or a looping video in Customizer -> MK Auto Elite -> Homepage Hero.
$mkae_hero_video  = \MKAutoElite\Customizer::get( 'hero_video', '' );
$mkae_hero_motion = \MKAutoElite\Customizer::get( 'hero_motion', 'yes' );
$mkae_hero_image  = \MKAutoElite\Customizer::get( 'hero_image', '' );

$mkae_tpl_dir = get_template_directory();
$mkae_tpl_uri = get_template_directory_uri();

// A universally-supported still JPEG backs the hero as a CSS background layer
// (via --mkae-hero-img). It paints instantly and guarantees the hero can never
// render as a black box, even if the foreground image element fails to load.
$mkae_hero_still = ( '' !== $mkae_hero_image )
	? $mkae_hero_image
	: ( file_exists( $mkae_tpl_dir . '/assets/images/hero.jpg' )
		? $mkae_tpl_uri . '/assets/images/hero.jpg'
		: $mkae_tpl_uri . '/assets/images/hero.webp' );

// Foreground hero image. With motion on we prefer a LIGHT animated WebP: it
// plays on every phone (unlike autoplay MP4, which is blocked or shows black on
// mobile) and is a small fraction of a GIF's weight. Falls back to a still
// WebP, then JPEG. A store owner can override this with their own image in
// Customizer -> MK Auto Elite -> Homepage Hero. A looping MP4 is used ONLY if a
// video URL is pasted there.
if ( '' === $mkae_hero_image ) {
	if ( 'yes' === $mkae_hero_motion && file_exists( $mkae_tpl_dir . '/assets/images/hero-anim.webp' ) ) {
		$mkae_hero_image = $mkae_tpl_uri . '/assets/images/hero-anim.webp';
	} elseif ( file_exists( $mkae_tpl_dir . '/assets/images/hero.webp' ) ) {
		$mkae_hero_image = $mkae_tpl_uri . '/assets/images/hero.webp';
	} else {
		$mkae_hero_image = $mkae_tpl_uri . '/assets/images/hero.jpg';
	}
}
$mkae_hero_static = ( 'yes' === $mkae_hero_motion ) ? '' : ' mkae-hero--static';
?>

<section id="primary" class="mkae-hero<?php echo esc_attr( $mkae_hero_static ); ?>" style="--mkae-hero-img:url('<?php echo esc_url( $mkae_hero_still ); ?>');">
	<div class="mkae-hero__media" aria-hidden="true">
		<?php if ( '' !== $mkae_hero_video ) : ?>
			<video class="mkae-hero__video" autoplay muted loop playsinline preload="metadata" poster="<?php echo esc_url( $mkae_hero_still ); ?>">
				<source src="<?php echo esc_url( $mkae_hero_video ); ?>" type="video/mp4">
			</video>
		<?php else : ?>
			<img class="mkae-hero__img" src="<?php echo esc_url( $mkae_hero_image ); ?>" alt="" width="1600" height="900" decoding="async" fetchpriority="high">
		<?php endif; ?>
	</div>
	<div class="mkae-hero__overlay" aria-hidden="true"></div>
	<div class="mkae-container mkae-hero__inner">
		<div class="mkae-hero__copy">
			<span class="mkae-hero__eyebrow"><?php esc_html_e( 'Trusted spare-parts supplier in Nairobi', 'mk-auto-elite' ); ?></span>
			<h1 class="mkae-hero__title"><?php esc_html_e( 'Find the right part in under 10 seconds', 'mk-auto-elite' ); ?></h1>
			<p class="mkae-hero__sub"><?php esc_html_e( 'Genuine and quality spare parts for your vehicle. Trusted supply, fast delivery across Kenya.', 'mk-auto-elite' ); ?></p>
			<ul class="mkae-hero__trust" aria-label="<?php esc_attr_e( 'Why shop with us', 'mk-auto-elite' ); ?>">
				<li><?php esc_html_e( 'Countrywide delivery', 'mk-auto-elite' ); ?></li>
				<li><?php esc_html_e( 'Quality-checked parts', 'mk-auto-elite' ); ?></li>
				<li><?php esc_html_e( 'Order on WhatsApp', 'mk-auto-elite' ); ?></li>
			</ul>
		</div>
		<div class="mkae-hero__finder" id="vehicle-finder">
			<?php
			if ( $finder ) {
				$finder->render();
			}
			?>
		</div>
	</div>
</section>

<section class="mkae-section mkae-categories">
	<div class="mkae-container">
		<h2 class="mkae-section__title"><?php esc_html_e( 'Shop by Category', 'mk-auto-elite' ); ?></h2>
		<div class="mkae-cat-grid">
			<?php
			if ( $is_woo && taxonomy_exists( 'product_cat' ) ) {
				$default_cat = (int) get_option( 'default_product_cat', 0 );
				$cats        = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'hide_empty' => false,
						'number'     => 12,
						'orderby'    => 'count',
						'order'      => 'DESC',
						'exclude'    => array_filter( array( $default_cat ) ),
					)
				);
				if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
					foreach ( $cats as $cat ) {
						$thumb_id = (int) get_term_meta( $cat->term_id, 'thumbnail_id', true );
						$img      = $thumb_id ? wp_get_attachment_image( $thumb_id, 'mkae-brand', false, array( 'loading' => 'lazy' ) ) : '';
						printf(
							'<a class="mkae-cat-tile" href="%s">%s<span class="mkae-cat-tile__name">%s</span><span class="mkae-cat-tile__count">%s</span></a>',
							esc_url( get_term_link( $cat ) ),
							$img ? '<span class="mkae-cat-tile__img">' . wp_kses_post( $img ) . '</span>' : '',
							esc_html( $cat->name ),
							esc_html( sprintf( _n( '%d item', '%d items', (int) $cat->count, 'mk-auto-elite' ), (int) $cat->count ) )
						);
					}
				} else {
					echo '<p class="mkae-empty">' . esc_html__( 'Categories will appear here once products are imported.', 'mk-auto-elite' ) . '</p>';
				}
			} else {
				echo '<p class="mkae-empty">' . esc_html__( 'Activate WooCommerce and import products to populate categories.', 'mk-auto-elite' ) . '</p>';
			}
			?>
		</div>
	</div>
</section>

<section class="mkae-section mkae-brands" id="shop-by-make">
	<div class="mkae-container">
		<h2 class="mkae-section__title"><?php esc_html_e( 'Popular Vehicle Brands', 'mk-auto-elite' ); ?></h2>
		<div class="mkae-brand-scroller" data-mkae-scroller>
			<button type="button" class="mkae-brand-nav mkae-brand-nav--prev" data-mkae-scroll="-1" aria-label="<?php esc_attr_e( 'Scroll left', 'mk-auto-elite' ); ?>">&#8249;</button>
			<div class="mkae-brand-row" data-mkae-scroll-row>
			<?php
			$printed = false;
			if ( taxonomy_exists( 'mk_make' ) ) {
				$makes = get_terms( array( 'taxonomy' => 'mk_make', 'hide_empty' => true, 'number' => 16 ) );
				if ( ! is_wp_error( $makes ) && ! empty( $makes ) ) {
					foreach ( $makes as $make ) {
						printf( '<a class="mkae-brand-chip" href="%s">%s</a>', esc_url( get_term_link( $make ) ), esc_html( $make->name ) );
					}
					$printed = true;
				}
			}
			if ( ! $printed ) {
				foreach ( array( 'Toyota', 'Nissan', 'Mazda', 'Subaru', 'Honda', 'Mitsubishi', 'Volkswagen', 'BMW' ) as $m ) {
					printf( '<span class="mkae-brand-chip">%s</span>', esc_html( $m ) );
				}
			}
			?>
			</div>
			<button type="button" class="mkae-brand-nav mkae-brand-nav--next" data-mkae-scroll="1" aria-label="<?php esc_attr_e( 'Scroll right', 'mk-auto-elite' ); ?>">&#8250;</button>
		</div>
	</div>
</section>

<?php if ( $is_woo ) : ?>
<section class="mkae-section mkae-featured">
	<div class="mkae-container">
		<h2 class="mkae-section__title"><?php esc_html_e( 'Featured Parts', 'mk-auto-elite' ); ?></h2>
		<?php // Randomised on each load so returning visitors see fresh stock. Note: a host-level page cache may serve a cached order until it refreshes. ?>
		<?php echo do_shortcode( '[products limit="8" columns="4" orderby="rand" visibility="visible"]' ); ?>
	</div>
</section>

<?php
// Category showcases: a few real categories, in a random order each load, each
// with six randomised products and a link through to the full category. Keeps
// the homepage full and fresh with zero manual curation. (A host-level page
// cache may hold a given random order until it refreshes.)
if ( taxonomy_exists( 'product_cat' ) ) {
	$mkae_sc_default = (int) get_option( 'default_product_cat', 0 );
	$mkae_sc_cats    = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'exclude'    => array_filter( array( $mkae_sc_default ) ),
			'number'     => 12,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( ! is_wp_error( $mkae_sc_cats ) && ! empty( $mkae_sc_cats ) ) {
		shuffle( $mkae_sc_cats );
		foreach ( array_slice( $mkae_sc_cats, 0, 3 ) as $mkae_sc ) {
			printf(
				'<section class="mkae-section mkae-cat-showcase"><div class="mkae-container"><div class="mkae-section__head"><h2 class="mkae-section__title">%1$s</h2><a class="mkae-section__more" href="%2$s">%3$s &rarr;</a></div>%4$s</div></section>',
				esc_html( $mkae_sc->name ),
				esc_url( get_term_link( $mkae_sc ) ),
				esc_html__( 'View all', 'mk-auto-elite' ),
				do_shortcode( sprintf( '[products limit="4" columns="4" orderby="rand" category="%s" visibility="visible"]', esc_attr( $mkae_sc->slug ) ) )
			);
		}
	}
}
?>
<?php endif; ?>

<section class="mkae-section mkae-trust">
	<div class="mkae-container mkae-trust__grid">
		<?php
		$trust = array(
			array( __( 'Genuine & Quality Parts', 'mk-auto-elite' ), __( 'Sourced from trusted suppliers.', 'mk-auto-elite' ) ),
			array( __( 'Warranty', 'mk-auto-elite' ), __( 'Cover on eligible parts.', 'mk-auto-elite' ) ),
			array( __( 'Fast Delivery', 'mk-auto-elite' ), __( 'Countrywide across Kenya.', 'mk-auto-elite' ) ),
			array( __( 'Expert Support', 'mk-auto-elite' ), __( 'Talk to a real specialist on WhatsApp.', 'mk-auto-elite' ) ),
		);
		foreach ( $trust as $t ) {
			printf( '<div class="mkae-trust__item"><h3>%s</h3><p>%s</p></div>', esc_html( $t[0] ), esc_html( $t[1] ) );
		}
		?>
	</div>
</section>

<section class="mkae-section mkae-faq">
	<div class="mkae-container">
		<h2 class="mkae-section__title"><?php esc_html_e( 'Frequently Asked Questions', 'mk-auto-elite' ); ?></h2>
		<div class="mkae-accordion">
			<?php
			// Single source shared with the FAQPage schema (Seo\Answers::home_faqs).
			$faqs = \MKAutoElite\Seo\Answers::home_faqs();
			foreach ( $faqs as $i => $faq ) {
				printf(
					'<details class="mkae-accordion__item"%s><summary class="mkae-accordion__q">%s</summary><div class="mkae-accordion__a">%s</div></details>',
					0 === $i ? ' open' : '',
					esc_html( $faq[0] ),
					esc_html( $faq[1] )
				);
			}
			?>
		</div>
	</div>
</section>

<?php
get_footer();
