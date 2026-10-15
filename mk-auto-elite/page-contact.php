<?php
/**
 * Contact page (slug: contact). Designed contact hub with a WhatsApp enquiry
 * form, contact cards, live opening status, map and directions.
 *
 * @package MK_Auto_Elite
 */

use MKAutoElite\Customizer;
use MKAutoElite\MobileNav;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mkc_rel = '/assets/js/contact.js';
wp_enqueue_script( 'mkae-contact', MK_AUTO_ELITE_URI . $mkc_rel, array(), (string) filemtime( MK_AUTO_ELITE_DIR . $mkc_rel ), array( 'in_footer' => true, 'strategy' => 'defer' ) );

$mkc_phone   = (string) Customizer::get( 'phone' );
$mkc_tel     = preg_replace( '/[^\d+]/', '', $mkc_phone );
$mkc_wa      = preg_replace( '/\D+/', '', (string) Customizer::get( 'whatsapp' ) );
$mkc_email   = (string) Customizer::get( 'email' );
$mkc_addr    = (string) Customizer::get( 'address' );
$mkc_hours   = (string) Customizer::get( 'hours' );
$mkc_mpesa   = (string) Customizer::get( 'mpesa' );
$mkc_query   = 'MK Auto Spares, Kirinyaga Road, Nairobi';
$mkc_dir     = Customizer::get( 'map_link' ) ? (string) Customizer::get( 'map_link' ) : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $mkc_query );
$mkc_embed   = Customizer::get( 'map_embed' ) ? (string) Customizer::get( 'map_embed' ) : 'https://maps.google.com/maps?q=' . rawurlencode( $mkc_query ) . '&z=16&output=embed';
$mkc_makes   = taxonomy_exists( 'mk_make' ) ? get_terms( array( 'taxonomy' => 'mk_make', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'name' ) ) : array();
$mkc_makes   = is_wp_error( $mkc_makes ) ? array() : $mkc_makes;
$mkc_returns = function_exists( 'mkae_page_url' ) ? mkae_page_url( 'return-refund' ) : home_url( '/return-refund/' );
$mkc_ship    = function_exists( 'mkae_page_url' ) ? mkae_page_url( 'shipping-delivery' ) : home_url( '/shipping-delivery/' );
$mkc_book    = home_url( '/book-a-service/' );

get_header();
?>
<main id="primary" class="mkc">
	<section class="mkc-hero">
		<div class="mkae-container mkc-hero__inner">
			<p class="mkc-eyebrow"><?php esc_html_e( 'Contact MK Auto Spares', 'mk-auto-elite' ); ?></p>
			<h1 class="mkc-hero__title"><?php esc_html_e( 'Talk to a parts specialist', 'mk-auto-elite' ); ?></h1>
			<p class="mkc-hero__sub"><?php esc_html_e( 'Prices, stock, fitment checks, delivery across Kenya or a workshop booking. Send your enquiry and we reply on WhatsApp.', 'mk-auto-elite' ); ?></p>
			<p class="mkc-status" data-mkc-status data-open="8" data-close="18" data-days="1,2,3,4,5,6"><span class="mkc-status__dot"></span><span data-mkc-status-text><?php echo esc_html( $mkc_hours ); ?></span></p>
		</div>
	</section>

	<div class="mkae-container">
		<section class="mkc-cards" aria-label="<?php esc_attr_e( 'Ways to reach us', 'mk-auto-elite' ); ?>">
			<a class="mkc-card mkc-card--wa" href="<?php echo esc_url( 'https://wa.me/' . $mkc_wa ); ?>" target="_blank" rel="nofollow noopener">
				<span class="mkc-card__ico"><?php echo \MKAutoElite\Woo\WaOrder::icon( 24 ); // phpcs:ignore ?></span>
				<span class="mkc-card__label"><?php esc_html_e( 'WhatsApp', 'mk-auto-elite' ); ?></span>
				<strong class="mkc-card__value"><?php echo esc_html( $mkc_phone ); ?></strong>
				<span class="mkc-card__note"><?php esc_html_e( 'Fastest reply', 'mk-auto-elite' ); ?></span>
			</a>
			<a class="mkc-card" href="<?php echo esc_url( 'tel:' . $mkc_tel ); ?>">
				<span class="mkc-card__ico"><?php echo MobileNav::icon( 'phone' ); // phpcs:ignore ?></span>
				<span class="mkc-card__label"><?php esc_html_e( 'Call us', 'mk-auto-elite' ); ?></span>
				<strong class="mkc-card__value"><?php echo esc_html( $mkc_phone ); ?></strong>
				<span class="mkc-card__note"><?php echo esc_html( $mkc_hours ); ?></span>
			</a>
			<a class="mkc-card" href="<?php echo esc_url( 'mailto:' . $mkc_email ); ?>">
				<span class="mkc-card__ico"><?php echo MobileNav::icon( 'mail' ); // phpcs:ignore ?></span>
				<span class="mkc-card__label"><?php esc_html_e( 'Email', 'mk-auto-elite' ); ?></span>
				<strong class="mkc-card__value"><?php echo esc_html( $mkc_email ); ?></strong>
				<span class="mkc-card__note"><?php esc_html_e( 'Reply within one business day', 'mk-auto-elite' ); ?></span>
			</a>
			<a class="mkc-card" href="<?php echo esc_url( $mkc_dir ); ?>" target="_blank" rel="noopener">
				<span class="mkc-card__ico"><?php echo MobileNav::icon( 'pin' ); // phpcs:ignore ?></span>
				<span class="mkc-card__label"><?php esc_html_e( 'Visit the shop', 'mk-auto-elite' ); ?></span>
				<strong class="mkc-card__value"><?php esc_html_e( 'Kirinyaga Road, Nairobi', 'mk-auto-elite' ); ?></strong>
				<span class="mkc-card__note"><?php esc_html_e( 'Get directions', 'mk-auto-elite' ); ?> &rarr;</span>
			</a>
		</section>

		<div class="mkc-grid">
			<section class="mkc-form-wrap" aria-labelledby="mkc-form-title">
				<h2 id="mkc-form-title" class="mkc-h2"><?php esc_html_e( 'Send an enquiry', 'mk-auto-elite' ); ?></h2>
				<p class="mkc-muted"><?php esc_html_e( 'Fill in the details below. We open WhatsApp with your message ready to send, so nothing gets lost.', 'mk-auto-elite' ); ?></p>
				<form class="mkc-form" data-mkc-form data-wa="<?php echo esc_attr( $mkc_wa ); ?>" data-email="<?php echo esc_attr( $mkc_email ); ?>" novalidate>
					<fieldset class="mkc-topics">
						<legend class="mkc-label"><?php esc_html_e( 'How can we help?', 'mk-auto-elite' ); ?></legend>
						<?php
						$mkc_topics = array(
							'part'    => __( 'Price & availability', 'mk-auto-elite' ),
							'fit'     => __( 'Check a part fits', 'mk-auto-elite' ),
							'order'   => __( 'Existing order', 'mk-auto-elite' ),
							'service' => __( 'Workshop / service', 'mk-auto-elite' ),
							'return'  => __( 'Return or warranty', 'mk-auto-elite' ),
							'other'   => __( 'Something else', 'mk-auto-elite' ),
						);
						$mkc_first = true;
						foreach ( $mkc_topics as $mkc_k => $mkc_v ) :
							?>
							<label class="mkc-topic"><input type="radio" name="topic" value="<?php echo esc_attr( $mkc_v ); ?>" data-key="<?php echo esc_attr( $mkc_k ); ?>"<?php checked( $mkc_first ); ?>><span><?php echo esc_html( $mkc_v ); ?></span></label>
							<?php
							$mkc_first = false;
						endforeach;
						?>
					</fieldset>

					<div class="mkc-row">
						<label class="mkc-field"><span class="mkc-label"><?php esc_html_e( 'Your name', 'mk-auto-elite' ); ?> <em>*</em></span>
							<input type="text" name="name" autocomplete="name" required maxlength="80"></label>
						<label class="mkc-field"><span class="mkc-label"><?php esc_html_e( 'Phone / WhatsApp', 'mk-auto-elite' ); ?> <em>*</em></span>
							<input type="tel" name="phone" autocomplete="tel" inputmode="tel" placeholder="07XX XXX XXX" required maxlength="20"></label>
					</div>

					<div class="mkc-vehicle" data-mkc-vehicle>
						<div class="mkc-row mkc-row--3">
							<label class="mkc-field"><span class="mkc-label"><?php esc_html_e( 'Car make', 'mk-auto-elite' ); ?></span>
								<select name="make">
									<option value=""><?php esc_html_e( 'Select make', 'mk-auto-elite' ); ?></option>
									<?php foreach ( $mkc_makes as $mkc_m ) : ?>
										<option value="<?php echo esc_attr( $mkc_m->name ); ?>"><?php echo esc_html( $mkc_m->name ); ?></option>
									<?php endforeach; ?>
									<option value="Other"><?php esc_html_e( 'Other', 'mk-auto-elite' ); ?></option>
								</select></label>
							<label class="mkc-field"><span class="mkc-label"><?php esc_html_e( 'Model', 'mk-auto-elite' ); ?></span>
								<input type="text" name="model" placeholder="<?php esc_attr_e( 'e.g. Prado 150', 'mk-auto-elite' ); ?>" maxlength="60"></label>
							<label class="mkc-field"><span class="mkc-label"><?php esc_html_e( 'Year', 'mk-auto-elite' ); ?></span>
								<input type="text" name="year" inputmode="numeric" placeholder="2015" maxlength="4"></label>
						</div>
						<label class="mkc-field"><span class="mkc-label"><?php esc_html_e( 'Chassis / VIN number', 'mk-auto-elite' ); ?> <small><?php esc_html_e( '(optional, guarantees the right fit)', 'mk-auto-elite' ); ?></small></span>
							<input type="text" name="chassis" maxlength="30" autocapitalize="characters"></label>
					</div>

					<label class="mkc-field mkc-field--order" data-mkc-order hidden><span class="mkc-label"><?php esc_html_e( 'Order number', 'mk-auto-elite' ); ?></span>
						<input type="text" name="order" maxlength="30"></label>

					<label class="mkc-field"><span class="mkc-label"><?php esc_html_e( 'Your message', 'mk-auto-elite' ); ?> <em>*</em></span>
						<textarea name="message" rows="4" required maxlength="1000" placeholder="<?php esc_attr_e( 'Which part do you need, or how can we help?', 'mk-auto-elite' ); ?>"></textarea></label>

					<div class="mkc-hp" aria-hidden="true" hidden><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
					<p class="mkc-error" data-mkc-error role="alert" hidden></p>

					<div class="mkc-actions">
						<button type="submit" class="mkc-submit">
							<?php echo \MKAutoElite\Woo\WaOrder::icon( 20 ); // phpcs:ignore ?>
							<span><?php esc_html_e( 'Send on WhatsApp', 'mk-auto-elite' ); ?></span>
						</button>
						<button type="button" class="mkc-alt" data-mkc-email><?php esc_html_e( 'Send by email instead', 'mk-auto-elite' ); ?></button>
					</div>
					<p class="mkc-fine"><?php esc_html_e( 'We only use your details to answer this enquiry.', 'mk-auto-elite' ); ?></p>
				</form>
			</section>

			<aside class="mkc-side">
				<div class="mkc-panel">
					<h2 class="mkc-h3"><?php esc_html_e( 'Shop & service centre', 'mk-auto-elite' ); ?></h2>
					<ul class="mkc-list">
						<li><?php echo MobileNav::icon( 'pin' ); // phpcs:ignore ?><span><?php echo esc_html( $mkc_addr ); ?></span></li>
						<li><?php echo MobileNav::icon( 'clock' ); // phpcs:ignore ?><span><?php echo esc_html( $mkc_hours ); ?><br><small><?php esc_html_e( 'Closed Sundays and public holidays', 'mk-auto-elite' ); ?></small></span></li>
						<?php if ( $mkc_mpesa ) : ?>
							<li><?php echo MobileNav::icon( 'wallet' ); // phpcs:ignore ?><span><?php echo esc_html( $mkc_mpesa ); ?></span></li>
						<?php endif; ?>
						<li><?php echo MobileNav::icon( 'truck' ); // phpcs:ignore ?><span><?php esc_html_e( 'Countrywide delivery or free pickup from the shop', 'mk-auto-elite' ); ?></span></li>
					</ul>
					<div class="mkc-map"><iframe src="<?php echo esc_url( $mkc_embed ); ?>" title="<?php esc_attr_e( 'MK Auto Spares on Google Maps', 'mk-auto-elite' ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
					<a class="mkae-btn mkae-btn--outline mkc-dir" href="<?php echo esc_url( $mkc_dir ); ?>" target="_blank" rel="noopener"><?php echo MobileNav::icon( 'pin' ); // phpcs:ignore ?><?php esc_html_e( 'Get directions', 'mk-auto-elite' ); ?></a>
				</div>
			</aside>
		</div>

		<section class="mkc-steps-sec" aria-labelledby="mkc-how">
			<h2 id="mkc-how" class="mkc-h2"><?php esc_html_e( 'How ordering works', 'mk-auto-elite' ); ?></h2>
			<ol class="mkae-steps">
				<li><strong><?php esc_html_e( 'Find or ask', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Use the vehicle finder, or send us your car and the part you need.', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'We confirm', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'A specialist confirms fitment, price and delivery time.', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Pay & receive', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Pay by M-Pesa or cash on delivery. We dispatch countrywide or you pick up.', 'mk-auto-elite' ); ?></span></li>
			</ol>
		</section>

		<section class="mkc-help" aria-label="<?php esc_attr_e( 'Helpful links', 'mk-auto-elite' ); ?>">
			<a class="mkc-help__item" href="<?php echo esc_url( $mkc_ship ); ?>"><?php echo MobileNav::icon( 'truck' ); // phpcs:ignore ?><span><strong><?php esc_html_e( 'Shipping & delivery', 'mk-auto-elite' ); ?></strong><small><?php esc_html_e( 'Costs and timelines', 'mk-auto-elite' ); ?></small></span></a>
			<a class="mkc-help__item" href="<?php echo esc_url( $mkc_returns ); ?>"><?php echo MobileNav::icon( 'return' ); // phpcs:ignore ?><span><strong><?php esc_html_e( 'Returns & refunds', 'mk-auto-elite' ); ?></strong><small><?php esc_html_e( 'How to return a part', 'mk-auto-elite' ); ?></small></span></a>
			<a class="mkc-help__item" href="<?php echo esc_url( $mkc_book ); ?>"><?php echo MobileNav::icon( 'calendar' ); // phpcs:ignore ?><span><strong><?php esc_html_e( 'Book a service', 'mk-auto-elite' ); ?></strong><small><?php esc_html_e( 'Fitting and repairs', 'mk-auto-elite' ); ?></small></span></a>
		</section>
	</div>
</main>
<?php
get_footer();
