<?php
/**
 * Knowledge Centre archive (v2.22.0): editorial hub with search, topics,
 * featured guide, guide grid, maintenance schedule, parts explainer, FAQ and
 * structured data (CollectionPage, ItemList, FAQPage, BreadcrumbList).
 *
 * @package MK_Auto_Elite
 */

use MKAutoElite\Customizer;
use MKAutoElite\MobileNav;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mkk_wa    = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
$mkk_shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$mkk_paged = max( 1, (int) get_query_var( 'paged' ) );
$mkk_first = 1 === $mkk_paged;
$mkk_url   = get_post_type_archive_link( 'mkae_guide' );
$mkk_total = (int) wp_count_posts( 'mkae_guide' )->publish;
$mkk_cat   = static function ( string $slug ) {
	$t = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $t && ! is_wp_error( $t ) ) {
		$l = get_term_link( $t );
		return is_wp_error( $l ) ? '' : $l;
	}
	return '';
};
$mkk_read  = static function ( $post_id ): int {
	$words = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
	return max( 1, (int) ceil( $words / 220 ) );
};
$mkk_topic = static function ( $post_id ): string {
	$t = get_the_terms( $post_id, 'mkae_guide_topic' );
	return ( $t && ! is_wp_error( $t ) ) ? $t[0]->name : '';
};
$mkk_topics = taxonomy_exists( 'mkae_guide_topic' ) ? get_terms( array( 'taxonomy' => 'mkae_guide_topic', 'hide_empty' => true ) ) : array();
$mkk_topics = is_wp_error( $mkk_topics ) ? array() : $mkk_topics;
$mkk_makes  = taxonomy_exists( 'mk_make' ) ? get_terms( array( 'taxonomy' => 'mk_make', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 14 ) ) : array();
$mkk_makes  = is_wp_error( $mkk_makes ) ? array() : $mkk_makes;

$mkk_faq = array(
	array(
		__( 'How often should I service my car in Kenya?', 'mk-auto-elite' ),
		__( 'Most petrol cars need an oil and filter change every 5,000 to 10,000 km, or every 6 months, whichever comes first. Dusty roads, heavy traffic, short trips and towing count as severe use, so stay at the shorter end. Always check your owner’s manual for your exact model.', 'mk-auto-elite' ),
	),
	array(
		__( 'What is the difference between genuine, OEM and aftermarket parts?', 'mk-auto-elite' ),
		__( 'Genuine parts come in the car maker’s own box. OEM parts are made by the same factory that supplies the car maker (for example Bosch, Denso or Aisin), usually for less money. Aftermarket parts are made by other brands to fit your car; quality varies, so buy recognised brands from a trusted shop.', 'mk-auto-elite' ),
	),
	array(
		__( 'Are ex-Japan (used) parts a good idea?', 'mk-auto-elite' ),
		__( 'Ex-Japan parts can be good value for body panels, electronics and some engine or gearbox units, especially for older models. For wear and safety items such as brake pads, discs, filters, belts and suspension bushes, new parts are the safer choice.', 'mk-auto-elite' ),
	),
	array(
		__( 'How do I make sure a part fits my car?', 'mk-auto-elite' ),
		__( 'Use your chassis (VIN) number. It is printed on your logbook and on a plate in the engine bay or door frame. Send it to us with the part you need and we confirm the exact part number before you pay. You can also use the vehicle finder or the fit checker on each product page.', 'mk-auto-elite' ),
	),
	array(
		__( 'What are the warning signs of worn brake pads?', 'mk-auto-elite' ),
		__( 'Squealing or grinding when braking, a longer stopping distance, the car pulling to one side, a vibrating pedal or a brake warning light on the dashboard. If you hear grinding, stop driving and have the brakes checked immediately.', 'mk-auto-elite' ),
	),
	array(
		__( 'Why is my car overheating?', 'mk-auto-elite' ),
		__( 'The most common causes are low or old coolant, a leaking hose or radiator, a stuck thermostat, a failing water pump or a radiator fan that is not switching on. Never open a hot radiator cap. Pull over, let the engine cool and check the coolant level.', 'mk-auto-elite' ),
	),
	array(
		__( 'Do you deliver car parts outside Nairobi?', 'mk-auto-elite' ),
		__( 'Yes. We deliver countrywide through trusted couriers, and you can also pick up from our shop on Kirinyaga Road, Nairobi. Delivery cost and timing are shown at checkout.', 'mk-auto-elite' ),
	),
	array(
		__( 'How can I pay for parts?', 'mk-auto-elite' ),
		__( 'You can pay by Lipa na M-Pesa (Paybill 529914, Account 4268) or cash on delivery. For price-on-request items, we confirm the price on WhatsApp before you pay.', 'mk-auto-elite' ),
	),
);

$mkk_schedule = array(
	array( __( 'Engine oil & oil filter', 'mk-auto-elite' ), __( '5,000–10,000 km / 6 months', 'mk-auto-elite' ), 'oil' ),
	array( __( 'Air filter', 'mk-auto-elite' ), __( '10,000–15,000 km (sooner on dusty roads)', 'mk-auto-elite' ), 'filters' ),
	array( __( 'Cabin (pollen) filter', 'mk-auto-elite' ), __( '15,000–20,000 km / yearly', 'mk-auto-elite' ), 'filters' ),
	array( __( 'Brake pads', 'mk-auto-elite' ), __( 'Inspect every service; replace at 3 mm', 'mk-auto-elite' ), 'brakes' ),
	array( __( 'Brake fluid', 'mk-auto-elite' ), __( 'Every 2 years', 'mk-auto-elite' ), 'brakes' ),
	array( __( 'Coolant', 'mk-auto-elite' ), __( 'Every 2–5 years (by coolant type)', 'mk-auto-elite' ), 'cooling' ),
	array( __( 'Spark plugs', 'mk-auto-elite' ), __( '20,000 km (copper) – 100,000 km (iridium)', 'mk-auto-elite' ), 'electrical-sensors' ),
	array( __( 'Timing belt (if fitted)', 'mk-auto-elite' ), __( '60,000–100,000 km, as per manual', 'mk-auto-elite' ), 'engine-components' ),
	array( __( 'Automatic gearbox / CVT fluid', 'mk-auto-elite' ), __( '40,000–60,000 km', 'mk-auto-elite' ), 'transmission-gearbox' ),
	array( __( 'Shock absorbers & bushes', 'mk-auto-elite' ), __( 'Inspect every 20,000 km', 'mk-auto-elite' ), 'suspension' ),
	array( __( 'Battery', 'mk-auto-elite' ), __( 'Test yearly; typical life 2–4 years', 'mk-auto-elite' ), 'batteries' ),
);

$mkk_signs = array(
	array( __( 'Squeal or grind when braking', 'mk-auto-elite' ), __( 'Worn brake pads or discs', 'mk-auto-elite' ), 'brakes', 'brake' ),
	array( __( 'Temperature gauge rising', 'mk-auto-elite' ), __( 'Coolant, thermostat, radiator or water pump', 'mk-auto-elite' ), 'cooling', 'thermo' ),
	array( __( 'Knocking over bumps', 'mk-auto-elite' ), __( 'Shocks, links, bushes or ball joints', 'mk-auto-elite' ), 'suspension', 'spring' ),
	array( __( 'Slow start or clicking', 'mk-auto-elite' ), __( 'Battery, alternator or starter', 'mk-auto-elite' ), 'batteries', 'battery' ),
	array( __( 'Check engine light', 'mk-auto-elite' ), __( 'Sensors, ignition coils or plugs', 'mk-auto-elite' ), 'electrical-sensors', 'bolt' ),
	array( __( 'Jerky or slipping gears', 'mk-auto-elite' ), __( 'Gearbox fluid, mounts or clutch', 'mk-auto-elite' ), 'transmission-gearbox', 'gears' ),
);

get_header();
?>
<main id="primary" class="mkk">
	<nav class="mkae-container mkk-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'mk-auto-elite' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'mk-auto-elite' ); ?></a>
		<span aria-hidden="true">/</span>
		<span aria-current="page"><?php esc_html_e( 'Knowledge Centre', 'mk-auto-elite' ); ?></span>
	</nav>

	<section class="mkk-hero">
		<div class="mkae-container mkk-hero__inner">
			<div>
				<p class="mkc-eyebrow"><?php esc_html_e( 'Car care guides for Kenyan drivers', 'mk-auto-elite' ); ?></p>
				<h1 class="mkk-hero__title"><?php esc_html_e( 'Knowledge Centre: car parts, maintenance and repair guides', 'mk-auto-elite' ); ?></h1>
				<p class="mkk-hero__sub"><?php esc_html_e( 'Practical advice from the MK Auto Spares parts team: when to replace parts, how to spot problems early, which parts fit your car, and how to save money on servicing in Kenya.', 'mk-auto-elite' ); ?></p>
				<form class="mkk-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="mkk-s"><?php esc_html_e( 'Search guides', 'mk-auto-elite' ); ?></label>
					<?php echo MobileNav::icon( 'search' ); // phpcs:ignore ?>
					<input id="mkk-s" type="search" name="s" placeholder="<?php esc_attr_e( 'Search guides, e.g. brake pads, overheating, X-Trail', 'mk-auto-elite' ); ?>" data-mkk-filter>
					<input type="hidden" name="post_type" value="mkae_guide">
					<button type="submit"><?php esc_html_e( 'Search', 'mk-auto-elite' ); ?></button>
				</form>
			</div>
			<ul class="mkk-stats">
				<li><strong><?php echo esc_html( number_format_i18n( $mkk_total ) ); ?></strong><span><?php esc_html_e( 'expert guides', 'mk-auto-elite' ); ?></span></li>
				<li><strong>4,000+</strong><span><?php esc_html_e( 'parts in stock', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Free', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'fitment checks', 'mk-auto-elite' ); ?></span></li>
			</ul>
		</div>
	</section>

	<div class="mkae-container">
		<?php if ( $mkk_topics ) : ?>
			<nav class="mkf__chips mkk-topics" aria-label="<?php esc_attr_e( 'Guide topics', 'mk-auto-elite' ); ?>">
				<a class="mkf__chip is-on" href="<?php echo esc_url( $mkk_url ); ?>"><?php esc_html_e( 'All guides', 'mk-auto-elite' ); ?></a>
				<?php foreach ( $mkk_topics as $mkk_t ) : ?>
					<a class="mkf__chip" href="<?php echo esc_url( get_term_link( $mkk_t ) ); ?>"><?php echo esc_html( $mkk_t->name ); ?> <span><?php echo (int) $mkk_t->count; ?></span></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<?php
			$mkk_items = array();
			$mkk_i     = 0;
			?>
			<section class="mkk-guides" aria-labelledby="mkk-latest">
				<h2 id="mkk-latest" class="mkk-h2"><?php echo $mkk_first ? esc_html__( 'Latest guides', 'mk-auto-elite' ) : esc_html( sprintf( /* translators: %d: page */ __( 'Guides - page %d', 'mk-auto-elite' ), $mkk_paged ) ); ?></h2>
				<div class="mkk-grid" data-mkk-grid>
					<?php
					while ( have_posts() ) :
						the_post();
						$mkk_i++;
						$mkk_items[] = get_permalink();
						$mkk_feat    = $mkk_first && 1 === $mkk_i;
						$mkk_tp      = $mkk_topic( get_the_ID() );
						?>
						<article <?php post_class( 'mkk-card' . ( $mkk_feat ? ' mkk-card--feat' : '' ) ); ?> data-mkk-card>
							<a class="mkk-card__link" href="<?php the_permalink(); ?>">
								<span class="mkk-card__media">
									<?php if ( has_post_thumbnail() ) : ?>
										<?php the_post_thumbnail( $mkk_feat ? 'large' : 'mkae-card', array( 'loading' => $mkk_feat ? 'eager' : 'lazy' ) ); ?>
									<?php else : ?>
										<span class="mkk-card__ph"><?php echo MobileNav::icon( MobileNav::category_icon( get_the_title() ) ); // phpcs:ignore ?></span>
									<?php endif; ?>
								</span>
								<span class="mkk-card__body">
									<span class="mkk-card__meta">
										<?php if ( $mkk_tp ) : ?><span class="mkk-card__tag"><?php echo esc_html( $mkk_tp ); ?></span><?php endif; ?>
										<span><?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min read', 'mk-auto-elite' ), $mkk_read( get_the_ID() ) ) ); ?></span>
									</span>
									<span class="mkk-card__title"><?php the_title(); ?></span>
									<span class="mkk-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), $mkk_feat ? 40 : 22 ) ); ?></span>
									<span class="mkk-card__more"><?php esc_html_e( 'Read guide', 'mk-auto-elite' ); ?> <?php echo MobileNav::icon( 'arrow' ); // phpcs:ignore ?></span>
								</span>
							</a>
						</article>
					<?php endwhile; ?>
				</div>
				<p class="mkk-nores" data-mkk-nores hidden><?php esc_html_e( 'No guides on this page match. Press Search to look through every guide.', 'mk-auto-elite' ); ?></p>
				<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
			</section>
		<?php else : ?>
			<p class="mkk-nores"><?php esc_html_e( 'New guides are coming soon.', 'mk-auto-elite' ); ?></p>
		<?php endif; ?>

		<?php if ( $mkk_first ) : ?>
			<section class="mkk-sec mkk-signs" aria-labelledby="mkk-signs-h">
				<h2 id="mkk-signs-h" class="mkk-h2"><?php esc_html_e( 'What is that noise? Common warning signs and the parts behind them', 'mk-auto-elite' ); ?></h2>
				<p class="mkk-lead"><?php esc_html_e( 'Catching a problem early is the cheapest repair. These are the symptoms Kenyan drivers ask us about most.', 'mk-auto-elite' ); ?></p>
				<div class="mkk-signs__grid">
					<?php foreach ( $mkk_signs as $mkk_s ) : ?>
						<?php $mkk_l = $mkk_cat( $mkk_s[2] ); ?>
						<a class="mkk-sign" href="<?php echo esc_url( $mkk_l ? $mkk_l : $mkk_shop ); ?>">
							<span class="mkk-sign__ico"><?php echo MobileNav::icon( $mkk_s[3] ); // phpcs:ignore ?></span>
							<span><strong><?php echo esc_html( $mkk_s[0] ); ?></strong><small><?php echo esc_html( $mkk_s[1] ); ?></small></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="mkk-sec mkk-sched" aria-labelledby="mkk-sched-h">
				<div class="mkk-sched__copy">
					<h2 id="mkk-sched-h" class="mkk-h2"><?php esc_html_e( 'Car maintenance schedule for Kenyan roads', 'mk-auto-elite' ); ?></h2>
					<p class="mkk-lead"><?php esc_html_e( 'Dust, potholes, heat and stop-start Nairobi traffic all count as “severe” driving. Use these typical intervals as a guide and follow your owner’s manual for your exact model.', 'mk-auto-elite' ); ?></p>
					<a class="mkae-btn mkae-btn--primary" href="<?php echo esc_url( home_url( '/book-a-service/' ) ); ?>"><?php echo MobileNav::icon( 'calendar' ); // phpcs:ignore ?><?php esc_html_e( 'Book a service', 'mk-auto-elite' ); ?></a>
				</div>
				<div class="mkk-table-wrap">
					<table class="mkk-table">
						<caption class="screen-reader-text"><?php esc_html_e( 'Typical service intervals', 'mk-auto-elite' ); ?></caption>
						<thead><tr><th scope="col"><?php esc_html_e( 'Part / fluid', 'mk-auto-elite' ); ?></th><th scope="col"><?php esc_html_e( 'Typical interval', 'mk-auto-elite' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $mkk_schedule as $mkk_r ) : ?>
								<?php $mkk_l = $mkk_cat( $mkk_r[2] ); ?>
								<tr><th scope="row"><?php if ( $mkk_l ) : ?><a href="<?php echo esc_url( $mkk_l ); ?>"><?php echo esc_html( $mkk_r[0] ); ?></a><?php else : ?><?php echo esc_html( $mkk_r[0] ); ?><?php endif; ?></th><td><?php echo esc_html( $mkk_r[1] ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="mkk-sec" aria-labelledby="mkk-types-h">
				<h2 id="mkk-types-h" class="mkk-h2"><?php esc_html_e( 'Genuine, OEM, aftermarket or ex-Japan: which parts should you buy?', 'mk-auto-elite' ); ?></h2>
				<div class="mkk-types">
					<div class="mkk-type"><span class="mkk-type__badge"><?php esc_html_e( 'Genuine', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'In the car maker’s own box (Toyota, Land Rover, Mercedes-Benz). Exact fit and the highest price.', 'mk-auto-elite' ); ?></p><small><?php esc_html_e( 'Best for: electronics, newer cars under warranty', 'mk-auto-elite' ); ?></small></div>
					<div class="mkk-type"><span class="mkk-type__badge mkk-type__badge--oem"><?php esc_html_e( 'OEM', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'Made by the factory that supplies the car maker, such as Bosch, Denso, Aisin, Febi or Lemförder. Same quality, lower price.', 'mk-auto-elite' ); ?></p><small><?php esc_html_e( 'Best for: filters, brakes, suspension, sensors', 'mk-auto-elite' ); ?></small></div>
					<div class="mkk-type"><span class="mkk-type__badge mkk-type__badge--after"><?php esc_html_e( 'Aftermarket', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'Made by other brands to fit your car. Quality ranges widely, so stick to known brands from a trusted supplier.', 'mk-auto-elite' ); ?></p><small><?php esc_html_e( 'Best for: budget repairs, older cars', 'mk-auto-elite' ); ?></small></div>
					<div class="mkk-type"><span class="mkk-type__badge mkk-type__badge--used"><?php esc_html_e( 'Ex-Japan / used', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'Quality used parts from Japan or the UK. Good value for body panels, nose cuts and some major units.', 'mk-auto-elite' ); ?></p><small><?php esc_html_e( 'Avoid for: brake pads, belts, filters', 'mk-auto-elite' ); ?></small></div>
				</div>
			</section>

			<?php if ( $mkk_makes ) : ?>
				<section class="mkk-sec" aria-labelledby="mkk-makes-h">
					<h2 id="mkk-makes-h" class="mkk-h2"><?php esc_html_e( 'Spare parts and guides by car make', 'mk-auto-elite' ); ?></h2>
					<div class="mkd__makes mkk-makes">
						<?php foreach ( $mkk_makes as $mkk_m ) : ?>
							<a class="mkd__make" href="<?php echo esc_url( get_term_link( $mkk_m ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: make */ __( '%s parts', 'mk-auto-elite' ), $mkk_m->name ) ); ?></a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="mkk-sec mkk-faq" aria-labelledby="mkk-faq-h">
				<h2 id="mkk-faq-h" class="mkk-h2"><?php esc_html_e( 'Car parts questions Kenyan drivers ask', 'mk-auto-elite' ); ?></h2>
				<div class="mkk-faq__list">
					<?php foreach ( $mkk_faq as $mkk_q ) : ?>
						<details class="mkk-faq__item">
							<summary><?php echo esc_html( $mkk_q[0] ); ?></summary>
							<p><?php echo esc_html( $mkk_q[1] ); ?></p>
						</details>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="mkk-cta">
			<div>
				<h2><?php esc_html_e( 'Not sure which part you need?', 'mk-auto-elite' ); ?></h2>
				<p><?php esc_html_e( 'Send us your car model or chassis number. A parts specialist will confirm the right part, price and delivery time.', 'mk-auto-elite' ); ?></p>
			</div>
			<div class="mkk-cta__actions">
				<a class="mkae-btn mkae-btn--whatsapp" href="<?php echo esc_url( 'https://wa.me/' . $mkk_wa . '?text=' . rawurlencode( __( 'Hello MK Auto Spares, I read your guide and need help with a part.', 'mk-auto-elite' ) ) ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'Ask on WhatsApp', 'mk-auto-elite' ); ?></a>
				<a class="mkae-btn mkk-cta__ghost" href="<?php echo esc_url( home_url( '/#find-parts' ) ); ?>"><?php esc_html_e( 'Find parts for my car', 'mk-auto-elite' ); ?></a>
			</div>
		</section>
	</div>
</main>
<?php
// Structured data.
$mkk_graph = array(
	array(
		'@type'       => 'CollectionPage',
		'@id'         => $mkk_url . '#collection',
		'url'         => $mkk_url,
		'name'        => 'Knowledge Centre: car parts, maintenance and repair guides',
		'description' => 'Car care, maintenance and spare parts guides for Kenyan drivers from MK Auto Spares, Nairobi.',
		'inLanguage'  => 'en-KE',
		'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
		'mainEntity'  => array(
			'@type'           => 'ItemList',
			'itemListElement' => array_map(
				static function ( $u, $i ) {
					return array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'url'      => $u,
					);
				},
				$mkk_items ?? array(),
				array_keys( $mkk_items ?? array() )
			),
		),
	),
	array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
			array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Knowledge Centre', 'item' => $mkk_url ),
		),
	),
);
if ( $mkk_first ) {
	$mkk_graph[] = array(
		'@type'      => 'FAQPage',
		'mainEntity' => array_map(
			static function ( $q ) {
				return array(
					'@type'          => 'Question',
					'name'           => $q[0],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $q[1] ),
				);
			},
			$mkk_faq
		),
	);
}
echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $mkk_graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
?>
<script>
(function(){var i=document.querySelector('[data-mkk-filter]');if(!i)return;var cards=document.querySelectorAll('[data-mkk-card]'),nr=document.querySelector('[data-mkk-nores]');i.addEventListener('input',function(){var q=i.value.trim().toLowerCase(),n=0;cards.forEach(function(c){var m=!q||c.textContent.toLowerCase().indexOf(q)>-1;c.hidden=!m;if(m)n++;});if(nr)nr.hidden=n>0;});})();
</script>
<?php
get_footer();
