<?php
/**
 * About Us page (slug: about-us). Editorial, SEO-led company page with
 * AboutPage + FAQPage structured data.
 *
 * @package MK_Auto_Elite
 */

use MKAutoElite\Customizer;
use MKAutoElite\MobileNav;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mka_wa    = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
$mka_phone = (string) Customizer::get( 'phone' );
$mka_addr  = (string) Customizer::get( 'address' );
$mka_hours = (string) Customizer::get( 'hours' );
$mka_shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$mka_prod  = (int) wp_count_posts( 'product' )->publish;
$mka_makes = taxonomy_exists( 'mk_make' ) ? get_terms( array( 'taxonomy' => 'mk_make', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) ) : array();
$mka_makes = is_wp_error( $mka_makes ) ? array() : $mka_makes;
$mka_cats  = class_exists( '\MKAutoElite\Woo\WooCommerce' ) ? \MKAutoElite\Woo\WooCommerce::top_categories( 12 ) : array();
$mka_url   = get_permalink();

$mka_title = 'About MK Auto Spares | Genuine Car Spare Parts Shop in Nairobi, Kenya';
$mka_desc  = 'MK Auto Spares is a car spare parts shop on Kirinyaga Road, Nairobi: genuine, OEM and ex-Japan parts for Toyota, Land Rover, Mercedes, BMW, Nissan and more. Countrywide delivery, M-Pesa.';
add_filter( 'rank_math/frontend/title', static function () use ( $mka_title ) { return $mka_title; }, 99 );
add_filter( 'rank_math/frontend/description', static function () use ( $mka_desc ) { return $mka_desc; }, 99 );
add_filter( 'rank_math/opengraph/facebook/og_title', static function () use ( $mka_title ) { return $mka_title; }, 99 );
add_filter( 'rank_math/opengraph/facebook/og_description', static function () use ( $mka_desc ) { return $mka_desc; }, 99 );
add_filter( 'pre_get_document_title', static function () use ( $mka_title ) { return $mka_title; }, 99 );
$mka_img_hero = MK_AUTO_ELITE_URI . '/assets/images/knowledge/parts-counter.jpg';
$mka_img_shop = MK_AUTO_ELITE_URI . '/assets/images/knowledge/workshop.jpg';
$mka_img_gen  = MK_AUTO_ELITE_URI . '/assets/images/knowledge/inspect-genuine.jpg';
$mka_towns    = array( 'Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika', 'Machakos', 'Kiambu', 'Nyeri', 'Meru', 'Naivasha', 'Kitale', 'Kakamega', 'Kericho', 'Malindi', 'Garissa' );

$mka_faq = array(
	array( __( 'Where is MK Auto Spares located?', 'mk-auto-elite' ), sprintf( __( 'Our shop and service centre is on Kirinyaga Road, Nairobi: %s. We are open %s.', 'mk-auto-elite' ), $mka_addr, $mka_hours ) ),
	array( __( 'Do you sell genuine car parts in Kenya?', 'mk-auto-elite' ), __( 'Yes. We stock genuine (car-maker boxed) parts, OEM parts from the factories that supply the car makers, quality aftermarket brands and inspected ex-Japan / ex-UK used parts. Every listing shows its condition so you know exactly what you are buying.', 'mk-auto-elite' ) ),
	array( __( 'Which car makes do you stock parts for?', 'mk-auto-elite' ), __( 'Toyota, Land Rover and Range Rover, BMW, Mercedes-Benz, Nissan, Volkswagen, Mitsubishi, Mazda, Subaru, Audi, Isuzu, Ford, Honda, Volvo, Suzuki, Lexus, Porsche and more.', 'mk-auto-elite' ) ),
	array( __( 'Do you deliver spare parts outside Nairobi?', 'mk-auto-elite' ), __( 'Yes. We deliver countrywide through trusted couriers, and you can collect from our Kirinyaga Road shop. Delivery cost is shown at checkout.', 'mk-auto-elite' ) ),
	array( __( 'How do I know a part will fit my car?', 'mk-auto-elite' ), __( 'Use the vehicle finder or the fit checker on each product, or send us your chassis (VIN) number on WhatsApp. A parts specialist confirms the correct part number before you pay.', 'mk-auto-elite' ) ),
	array( __( 'How can I pay?', 'mk-auto-elite' ), __( 'Lipa na M-Pesa (Paybill 529914, Account 4268) or cash on delivery.', 'mk-auto-elite' ) ),
	array( __( 'Can you fit the parts for me?', 'mk-auto-elite' ), __( 'Yes. Our in-house workshop handles fitting, servicing and repairs. Book online or message us on WhatsApp.', 'mk-auto-elite' ) ),
);

get_header();
?>
<main id="primary" class="mka">
	<nav class="mkae-container mkk-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'mk-auto-elite' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'mk-auto-elite' ); ?></a>
		<span aria-hidden="true">/</span>
		<span aria-current="page"><?php esc_html_e( 'About us', 'mk-auto-elite' ); ?></span>
	</nav>

	<section class="mkk-hero mka-hero" style="--mka-hero:url('<?php echo esc_url( $mka_img_hero ); ?>');">
		<div class="mkae-container mkk-hero__inner">
			<div>
				<p class="mkc-eyebrow"><?php esc_html_e( 'About MK Auto Spares', 'mk-auto-elite' ); ?></p>
				<h1 class="mkk-hero__title"><?php esc_html_e( 'Genuine car spare parts in Kenya, from a Nairobi shop you can visit', 'mk-auto-elite' ); ?></h1>
				<p class="mkk-hero__sub"><?php esc_html_e( 'MK Auto Spares is a Nairobi auto spare parts store and service centre on Kirinyaga Road. We supply genuine, OEM, quality aftermarket and ex-Japan parts for the cars Kenyans drive, check fitment by chassis number, deliver countrywide and fit parts in our own workshop.', 'mk-auto-elite' ); ?></p>
				<div class="mka-hero__cta">
					<a class="mkae-btn mkae-btn--primary" href="<?php echo esc_url( $mka_shop ); ?>"><?php echo MobileNav::icon( 'shop' ); // phpcs:ignore ?><?php esc_html_e( 'Shop spare parts', 'mk-auto-elite' ); ?></a>
					<a class="mkae-btn mkae-btn--whatsapp" href="<?php echo esc_url( 'https://wa.me/' . $mka_wa ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'Talk to a specialist', 'mk-auto-elite' ); ?></a>
				</div>
			</div>
			<ul class="mkk-stats mka-stats">
				<li><strong><?php echo esc_html( number_format_i18n( max( 4000, floor( $mka_prod / 100 ) * 100 ) ) ); ?>+</strong><span><?php esc_html_e( 'parts listed', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php echo esc_html( count( $mka_makes ) ? count( $mka_makes ) : 20 ); ?>+</strong><span><?php esc_html_e( 'car makes', 'mk-auto-elite' ); ?></span></li>
				<li><strong>47</strong><span><?php esc_html_e( 'counties served', 'mk-auto-elite' ); ?></span></li>
			</ul>
		</div>
	</section>

	<div class="mka-trust">
		<ul class="mkae-container mka-trust__list">
			<li><?php echo MobileNav::icon( 'star' ); // phpcs:ignore ?><span><strong><?php esc_html_e( 'Genuine & OEM', 'mk-auto-elite' ); ?></strong><?php esc_html_e( 'condition on every part', 'mk-auto-elite' ); ?></span></li>
			<li><?php echo MobileNav::icon( 'car' ); // phpcs:ignore ?><span><strong><?php esc_html_e( 'Fitment checked', 'mk-auto-elite' ); ?></strong><?php esc_html_e( 'by chassis number', 'mk-auto-elite' ); ?></span></li>
			<li><?php echo MobileNav::icon( 'wallet' ); // phpcs:ignore ?><span><strong><?php esc_html_e( 'M-Pesa', 'mk-auto-elite' ); ?></strong><?php esc_html_e( 'or cash on delivery', 'mk-auto-elite' ); ?></span></li>
			<li><?php echo MobileNav::icon( 'truck' ); // phpcs:ignore ?><span><strong><?php esc_html_e( 'Countrywide', 'mk-auto-elite' ); ?></strong><?php esc_html_e( 'delivery across Kenya', 'mk-auto-elite' ); ?></span></li>
			<li><?php echo MobileNav::icon( 'return' ); // phpcs:ignore ?><span><strong><?php esc_html_e( '7-day returns', 'mk-auto-elite' ); ?></strong><?php esc_html_e( 'on eligible parts', 'mk-auto-elite' ); ?></span></li>
		</ul>
	</div>

	<div class="mkae-container">
		<section class="mkk-sec mka-story">
			<div class="mka-story__copy">
				<h2 class="mkk-h2"><?php esc_html_e( 'Who we are', 'mk-auto-elite' ); ?></h2>
				<p><?php esc_html_e( 'Finding the right car part in Kenya should not mean a day of walking from shop to shop, guessing part numbers or paying for parts that do not fit. MK Auto Spares was built to fix that: one trusted spare parts shop in Nairobi, with a full online catalogue, honest condition labels and specialists who check every part against your vehicle.', 'mk-auto-elite' ); ?></p>
				<p><?php esc_html_e( 'From brake pads, filters and suspension to engine, gearbox, electrical and body parts, we stock the parts Kenyan drivers need most for Toyota, Land Rover, Range Rover, Mercedes-Benz, BMW, Nissan, Mazda, Subaru, Volkswagen and more, and we source what is not on the shelf.', 'mk-auto-elite' ); ?></p>
				<p><?php esc_html_e( 'Whether you are a private motorist, a fleet manager or a mechanic, you get the same promise: the right part, a fair price, and people who answer the phone.', 'mk-auto-elite' ); ?></p>
			</div>
			<div class="mka-story__side">
			<figure class="mka-photo"><img src="<?php echo esc_url( $mka_img_shop ); ?>" alt="<?php esc_attr_e( 'MK Auto Spares workshop and spare parts service centre in Nairobi', 'mk-auto-elite' ); ?>" loading="lazy" decoding="async" width="800" height="533"><figcaption><?php esc_html_e( 'Parts counter and in-house workshop, Kirinyaga Road, Nairobi', 'mk-auto-elite' ); ?></figcaption></figure>
			<ul class="mka-pillars">
				<li><span class="mka-pillars__ico"><?php echo MobileNav::icon( 'car' ); // phpcs:ignore ?></span><strong><?php esc_html_e( 'Fitment first', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Parts checked against your make, model, year and chassis number.', 'mk-auto-elite' ); ?></span></li>
				<li><span class="mka-pillars__ico"><?php echo MobileNav::icon( 'star' ); // phpcs:ignore ?></span><strong><?php esc_html_e( 'Honest condition', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Genuine, OEM, aftermarket or ex-Japan, clearly labelled on every part.', 'mk-auto-elite' ); ?></span></li>
				<li><span class="mka-pillars__ico"><?php echo MobileNav::icon( 'truck' ); // phpcs:ignore ?></span><strong><?php esc_html_e( 'Countrywide delivery', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Nairobi and all of Kenya, or collect from Kirinyaga Road.', 'mk-auto-elite' ); ?></span></li>
				<li><span class="mka-pillars__ico"><?php echo MobileNav::icon( 'wrench' ); // phpcs:ignore ?></span><strong><?php esc_html_e( 'In-house workshop', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Fitting, servicing and diagnostics by our own technicians.', 'mk-auto-elite' ); ?></span></li>
			</ul>
			</div>
		</section>

		<section class="mkk-sec" aria-labelledby="mka-types">
			<h2 id="mka-types" class="mkk-h2"><?php esc_html_e( 'What we sell: four kinds of parts, clearly labelled', 'mk-auto-elite' ); ?></h2>
			<p class="mkk-lead"><?php esc_html_e( 'Different budgets need different parts. We tell you exactly which kind you are buying, so there are no surprises.', 'mk-auto-elite' ); ?></p>
			<div class="mkk-types">
				<div class="mkk-type"><span class="mkk-type__badge"><?php esc_html_e( 'Genuine', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'Original parts in the car maker’s packaging. Exact fit for newer and premium vehicles.', 'mk-auto-elite' ); ?></p></div>
				<div class="mkk-type"><span class="mkk-type__badge mkk-type__badge--oem"><?php esc_html_e( 'OEM', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'From the factories that supply the car makers, such as Bosch, Denso, Aisin, Febi and Lemförder, at a lower price than dealer parts.', 'mk-auto-elite' ); ?></p></div>
				<div class="mkk-type"><span class="mkk-type__badge mkk-type__badge--after"><?php esc_html_e( 'Aftermarket', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'Recognised replacement brands for budget-friendly repairs on everyday cars.', 'mk-auto-elite' ); ?></p></div>
				<div class="mkk-type"><span class="mkk-type__badge mkk-type__badge--used"><?php esc_html_e( 'Ex-Japan / ex-UK', 'mk-auto-elite' ); ?></span><p><?php esc_html_e( 'Inspected used parts, including nose cuts, panels and major units, for great value on older models.', 'mk-auto-elite' ); ?></p></div>
			</div>
		</section>

		<?php if ( $mka_cats ) : ?>
			<section class="mkk-sec" aria-labelledby="mka-cats">
				<h2 id="mka-cats" class="mkk-h2"><?php esc_html_e( 'Spare parts categories we stock', 'mk-auto-elite' ); ?></h2>
				<div class="mka-cats">
					<?php foreach ( $mka_cats as $mka_n => $mka_u ) : ?>
						<a class="mkk-sign" href="<?php echo esc_url( $mka_u ); ?>"><span class="mkk-sign__ico"><?php echo MobileNav::icon( MobileNav::category_icon( $mka_n ) ); // phpcs:ignore ?></span><span><strong><?php echo esc_html( $mka_n ); ?></strong><small><?php esc_html_e( 'Shop parts', 'mk-auto-elite' ); ?> &rarr;</small></span></a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $mka_makes ) : ?>
			<section class="mkk-sec" aria-labelledby="mka-makes">
				<h2 id="mka-makes" class="mkk-h2"><?php esc_html_e( 'Car makes we cover in Kenya', 'mk-auto-elite' ); ?></h2>
				<div class="mkd__makes mkk-makes">
					<?php foreach ( $mka_makes as $mka_m ) : ?>
						<a class="mkd__make" href="<?php echo esc_url( get_term_link( $mka_m ) ); ?>"><?php echo esc_html( sprintf( __( '%s spare parts', 'mk-auto-elite' ), $mka_m->name ) ); ?></a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="mkk-sec mkk-sched" aria-labelledby="mka-how">
			<div class="mkk-sched__copy">
				<h2 id="mka-how" class="mkk-h2"><?php esc_html_e( 'How we work', 'mk-auto-elite' ); ?></h2>
				<p class="mkk-lead"><?php esc_html_e( 'Buy online with M-Pesa or cash on delivery, order on WhatsApp, or walk into the shop. Every route gets the same fitment check.', 'mk-auto-elite' ); ?></p>
			</div>
			<ol class="mkae-steps mka-steps">
				<li><strong><?php esc_html_e( 'Tell us your car', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Use the vehicle finder or send your chassis number.', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'We confirm the part', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Part number, condition, price and delivery time.', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Pay your way', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Lipa na M-Pesa or cash on delivery.', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Delivered or fitted', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Countrywide courier, shop pickup or fitting in our workshop.', 'mk-auto-elite' ); ?></span></li>
			</ol>
		</section>

		<section class="mkk-sec mka-areas" aria-labelledby="mka-areas">
			<div class="mka-areas__copy">
				<h2 id="mka-areas" class="mkk-h2"><?php esc_html_e( 'Car spare parts delivered across Kenya', 'mk-auto-elite' ); ?></h2>
				<p class="mkk-lead"><?php esc_html_e( 'Order online or on WhatsApp from anywhere in Kenya. We dispatch from our Nairobi shop through trusted couriers to every county, or you can collect in person on Kirinyaga Road.', 'mk-auto-elite' ); ?></p>
				<ul class="mka-towns">
					<?php foreach ( $mka_towns as $mka_t ) : ?><li><?php echo esc_html( sprintf( __( 'Spare parts %s', 'mk-auto-elite' ), $mka_t ) ); ?></li><?php endforeach; ?>
				</ul>
			</div>
			<figure class="mka-photo"><img src="<?php echo esc_url( $mka_img_gen ); ?>" alt="<?php esc_attr_e( 'Inspecting a genuine car part before dispatch in Kenya', 'mk-auto-elite' ); ?>" loading="lazy" decoding="async" width="800" height="533"></figure>
		</section>

		<section class="mkk-sec mka-visit" aria-labelledby="mka-visit">
			<div class="mkc-panel mka-visit__panel">
				<h2 id="mka-visit" class="mkk-h2"><?php esc_html_e( 'Visit our Nairobi spare parts shop', 'mk-auto-elite' ); ?></h2>
				<ul class="mkc-list">
					<li><?php echo MobileNav::icon( 'pin' ); // phpcs:ignore ?><span><?php echo esc_html( $mka_addr ); ?></span></li>
					<li><?php echo MobileNav::icon( 'clock' ); // phpcs:ignore ?><span><?php echo esc_html( $mka_hours ); ?></span></li>
					<li><?php echo MobileNav::icon( 'phone' ); // phpcs:ignore ?><span><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $mka_phone ) ); ?>"><?php echo esc_html( $mka_phone ); ?></a></span></li>
					<li><?php echo MobileNav::icon( 'wallet' ); // phpcs:ignore ?><span><?php echo esc_html( (string) Customizer::get( 'mpesa' ) ); ?></span></li>
				</ul>
				<div class="mka-visit__cta">
					<a class="mkae-btn mkae-btn--outline" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact & directions', 'mk-auto-elite' ); ?></a>
					<a class="mkae-btn mkae-btn--primary" href="<?php echo esc_url( home_url( '/book-a-service/' ) ); ?>"><?php esc_html_e( 'Book a service', 'mk-auto-elite' ); ?></a>
				</div>
			</div>
		</section>

		<section class="mkk-sec mkk-faq" aria-labelledby="mka-faq">
			<h2 id="mka-faq" class="mkk-h2"><?php esc_html_e( 'Frequently asked questions about MK Auto Spares', 'mk-auto-elite' ); ?></h2>
			<div class="mkk-faq__list">
				<?php foreach ( $mka_faq as $mka_q ) : ?>
					<details class="mkk-faq__item"><summary><?php echo esc_html( $mka_q[0] ); ?></summary><p><?php echo esc_html( $mka_q[1] ); ?></p></details>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="mkk-cta">
			<div>
				<h2><?php esc_html_e( 'Need a part today?', 'mk-auto-elite' ); ?></h2>
				<p><?php esc_html_e( 'Search 4,000+ car spare parts online, or send us your chassis number and we will find it for you.', 'mk-auto-elite' ); ?></p>
			</div>
			<div class="mkk-cta__actions">
				<a class="mkae-btn mkae-btn--whatsapp" href="<?php echo esc_url( 'https://wa.me/' . $mka_wa ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'WhatsApp us', 'mk-auto-elite' ); ?></a>
				<a class="mkae-btn mkk-cta__ghost" href="<?php echo esc_url( home_url( '/#find-parts' ) ); ?>"><?php esc_html_e( 'Find parts for my car', 'mk-auto-elite' ); ?></a>
			</div>
		</section>
	</div>
</main>
<?php
$mka_graph = array(
	array(
		'@type'       => 'AboutPage',
		'@id'         => $mka_url . '#aboutpage',
		'url'         => $mka_url,
		'name'        => 'About MK Auto Spares - genuine car spare parts in Kenya',
		'description' => 'MK Auto Spares is a Nairobi car spare parts shop and service centre on Kirinyaga Road supplying genuine, OEM, aftermarket and ex-Japan parts with countrywide delivery.',
		'inLanguage'  => 'en-KE',
		'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
		'about'       => array( '@id' => home_url( '/#organization' ) ),
		'mainEntity'  => array( '@id' => home_url( '/#organization' ) ),
	),
	array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
			array( '@type' => 'ListItem', 'position' => 2, 'name' => 'About us', 'item' => $mka_url ),
		),
	),
	array(
		'@type'      => 'FAQPage',
		'mainEntity' => array_map(
			static function ( $q ) {
				return array( '@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $q[1] ) );
			},
			$mka_faq
		),
	),
);
echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $mka_graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
get_footer();
