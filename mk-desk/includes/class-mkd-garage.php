<?php
/**
 * MKauto Desk - public Garage & Service page (MKauto Desk 1.2.0).
 *
 * Shortcode [mkd_garage] renders the customer-facing service page: services,
 * how it works, and a booking form. A booking is saved straight into
 * MKauto Desk > Garage & service (status "Awaiting confirmation") and the
 * customer is then sent to WhatsApp with the booking already written, so the
 * shop confirms in the same chat.
 *
 * The page /garage-service/ is created once and added to the primary menu.
 *
 * @package MK_Desk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKD_Garage {

	const SLUG = 'garage-service';
	const OPT  = 'mkd_garage_page';

	public static function hooks() {
		add_shortcode( 'mkd_garage', array( __CLASS__, 'render' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_page' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 40 );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ), 1 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_ajax_mkd_garage_book', array( __CLASS__, 'ajax_book' ) );
		add_action( 'wp_ajax_nopriv_mkd_garage_book', array( __CLASS__, 'ajax_book' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Page + menu                                                         */
	/* ------------------------------------------------------------------ */

	/** Create the page once and add it to the primary menu. Never repeats after the owner edits or removes it. */
	public static function ensure_page() {
		if ( get_option( self::OPT ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = get_page_by_path( self::SLUG );
		$id   = $page ? (int) $page->ID : 0;
		if ( ! $id ) {
			$id = (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => self::SLUG,
					'post_title'   => 'Garage & Service',
					'post_content' => '<!-- wp:shortcode -->[mkd_garage]<!-- /wp:shortcode -->',
					'post_excerpt' => 'Book a car service, diagnosis, brakes, suspension or part fitting at MK Auto Spares on Kirinyaga Road, Nairobi. Genuine parts on hand and a clear quote before any work starts.',
				)
			);
		}
		if ( $id ) {
			self::add_to_menu( $id );
		}
		update_option( self::OPT, $id ? $id : 1, false );
	}

	private static function add_to_menu( $page_id ) {
		$locations = get_nav_menu_locations();
		$menu_id   = isset( $locations['primary'] ) ? (int) $locations['primary'] : 0;
		if ( ! $menu_id ) {
			return;
		}
		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
			if ( 'page' === $item->object && (int) $item->object_id === (int) $page_id ) {
				return;
			}
		}
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'Garage & Service',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => (int) $page_id,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
	}

	private static function is_page() {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}
		$post = get_post();
		return $post && has_shortcode( (string) $post->post_content, 'mkd_garage' );
	}

	/** The form carries a nonce, so the page must never be served from a page cache. */
	public static function no_cache() {
		if ( ! self::is_page() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'mkd garage booking form' );
		nocache_headers();
	}

	public static function body_class( $c ) {
		if ( self::is_page() ) {
			$c[] = 'mkd-garage-page';
		}
		return $c;
	}

	public static function assets() {
		if ( ! self::is_page() ) {
			return;
		}
		wp_enqueue_style( 'mkd-garage', MKD_URL . 'assets/css/mk-garage.css', array(), MKD_VERSION );
		wp_enqueue_script( 'mkd-garage', MKD_URL . 'assets/js/mk-garage.js', array(), MKD_VERSION, true );
		wp_localize_script(
			'mkd-garage',
			'MKD_GARAGE',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'slots' => esc_url_raw( rest_url( 'mkd/v1/slots' ) ),
				'nonce' => wp_create_nonce( 'mkd_garage' ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Content                                                             */
	/* ------------------------------------------------------------------ */

	private static function brand( $key, $default ) {
		$v = get_theme_mod( 'mkae_' . $key, $default );
		return is_string( $v ) && '' !== trim( $v ) ? trim( $v ) : $default;
	}

	/** Service cards: key => [title, line]. Keys match MKD_Desk_Appts::services(). */
	public static function cards() {
		return (array) apply_filters(
			'mkd_garage_cards',
			array(
				'service'    => array( 'Full & minor service', 'Engine oil, filters, plugs, fluids and a multi-point inspection, to your manufacturer schedule.' ),
				'diagnosis'  => array( 'Diagnosis & check-up', 'Computer diagnosis, warning lights, strange noises and pre-purchase inspections.' ),
				'brakes'     => array( 'Brakes & suspension', 'Pads, discs, shocks, bushes, ball joints and steering, tested before you drive off.' ),
				'engine'     => array( 'Engine & gearbox', 'Timing belts and chains, gaskets, mounts, clutch and gearbox repairs.' ),
				'electrical' => array( 'Electrical & battery', 'Battery testing and fitting, alternators, starters, lights and wiring faults.' ),
				'cooling'    => array( 'Cooling & AC', 'Radiators, water pumps, thermostats, hoses and air-conditioning service.' ),
				'alignment'  => array( 'Alignment & tyres', 'Wheel alignment, balancing and tyre fitting for a straight, even drive.' ),
				'fitting'    => array( 'Part fitting', 'Bought a part from us? Our mechanics fit it properly, the same day where possible.' ),
			)
		);
	}

	/**
	 * Garage terms as array( array( heading, text ), ... ). Edited in Desk > Settings.
	 * Filter: mkd_garage_terms.
	 */
	public static function terms() {
		$s   = MKD_Desk::settings();
		$raw = trim( (string) ( isset( $s['garage_terms'] ) ? $s['garage_terms'] : '' ) );
		if ( '' === $raw ) {
			$raw = MKD_Desk::defaults()['garage_terms'];
		}
		$out = array();
		foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$p     = strpos( $line, ':' );
			$out[] = ( false !== $p && $p < 60 ) ? array( trim( substr( $line, 0, $p ) ), trim( substr( $line, $p + 1 ) ) ) : array( '', $line );
		}
		return apply_filters( 'mkd_garage_terms', $out );
	}

	/** Short version tag of the current terms, saved with each booking. */
	public static function terms_version() {
		return substr( md5( wp_json_encode( self::terms() ) ), 0, 8 );
	}

	private static function terms_list( $terms ) {
		$h = '<ol class="mkg-terms__list">';
		foreach ( $terms as $tm ) {
			$h .= '<li>' . ( '' !== $tm[0] ? '<strong>' . esc_html( $tm[0] ) . '.</strong> ' : '' ) . esc_html( $tm[1] ) . '</li>';
		}
		return $h . '</ol>';
	}

	public static function render() {
		$services = MKD_Desk_Appts::services();
		$cards    = array_intersect_key( self::cards(), $services );
		$phone    = self::brand( 'phone', '+254 702343302' );
		$hours    = self::brand( 'hours', 'Mon - Sat: 8:00am - 6:00pm' );
		$address  = self::brand( 'address', 'Along Kirinyaga Road, Nairobi' );
		$map      = self::brand( 'map_link', '' );
		$wa       = class_exists( 'MKD_WA_Order' ) ? MKD_WA_Order::whatsapp_number() : '254702343302';
		$wa_hi    = 'https://wa.me/' . $wa . '?text=' . rawurlencode( 'Hello MK Auto Spares, I would like to ask about a garage service.' );
		$tel      = 'tel:' . preg_replace( '/[^\d+]/', '', $phone );
		$today    = current_time( 'Y-m-d' );
		$max      = gmdate( 'Y-m-d', strtotime( $today . ' +60 days' ) );
		$icons    = array(
			'service'    => '<path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1M5.6 18.4l2.1-2.1m8.6-8.6 2.1-2.1"/><circle cx="12" cy="12" r="3.5"/>',
			'diagnosis'  => '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
			'brakes'     => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 4v2M20 12h-2"/>',
			'engine'     => '<path d="M4 10h3l2-3h6v3h3l2 2v4h-2v2H9l-2-2H4z"/>',
			'electrical' => '<path d="M13 2 5 14h6l-1 8 8-12h-6z"/>',
			'cooling'    => '<path d="M12 2v20M4.9 6.9l14.2 10.2M19.1 6.9 4.9 17.1"/>',
			'alignment'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2"/><path d="M12 3v7M12 14v7M3 12h7M14 12h7"/>',
			'fitting'    => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/>',
		);
		$terms    = self::terms();
		ob_start();
		?>
		<div class="mkg" id="mkg">
			<section class="mkg-top">
				<div class="mkg-intro mkg-intro--head">
					<p class="mkg-eyebrow">MK Auto Spares &middot; Garage &amp; Service</p>
					<h2 class="mkg-title">Book your car service in one minute.</h2>
					<p class="mkg-lead">Choose a service and a time. We confirm on WhatsApp and agree the price with you before any work starts.</p>
				</div>

				<div class="mkg-form-card" id="mkg-book">
					<header class="mkg-form-card__h">
						<h2>Book a service</h2>
						<p>Fields marked <i>*</i> are required</p>
					</header>

					<form class="mkg-form" data-mkg-form novalidate>
						<fieldset>
							<legend><span>1</span> Vehicle &amp; service</legend>
							<label class="mkg-f"><span>Make, model &amp; year <i>*</i></span><input type="text" name="vehicle" maxlength="120" required placeholder="e.g. Toyota Fielder 2014" autocomplete="off"></label>
							<label class="mkg-f"><span>Service needed <i>*</i></span>
								<select name="service" required data-mkg-service>
									<option value="">Choose a service</option>
									<?php foreach ( $services as $k => $l ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<div class="mkg-row">
								<label class="mkg-f"><span>Registration no.</span><input type="text" name="plate" maxlength="20" placeholder="KDA 123X" class="mkg-upper" autocomplete="off"></label>
								<label class="mkg-f"><span>Mileage (km)</span><input type="text" name="mileage" maxlength="20" inputmode="numeric" placeholder="98,500" autocomplete="off"></label>
							</div>
							<div class="mkg-choice" role="radiogroup" aria-label="Where">
								<label><input type="radio" name="type" value="studio" checked><span><strong>At the garage</strong><small>Kirinyaga Road</small></span></label>
								<label><input type="radio" name="type" value="video"><span><strong>Mobile mechanic</strong><small>We come to you</small></span></label>
							</div>
							<label class="mkg-f" data-mkg-loc hidden><span>Your location <i>*</i></span><input type="text" name="location" maxlength="120" placeholder="Estate, road or landmark"></label>
						</fieldset>

						<fieldset>
							<legend><span>2</span> Date &amp; time</legend>
							<label class="mkg-f mkg-f--date"><span>Preferred date <i>*</i></span><input type="date" name="date" required min="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( $max ); ?>" data-mkg-date></label>
							<div class="mkg-slots" data-mkg-slots aria-live="polite"><p class="mkg-hint">Pick a date to see available times.</p></div>
							<input type="hidden" name="time" data-mkg-time>
						</fieldset>

						<fieldset>
							<legend><span>3</span> Your details</legend>
							<div class="mkg-row">
								<label class="mkg-f"><span>Full name <i>*</i></span><input type="text" name="name" maxlength="120" required autocomplete="name"></label>
								<label class="mkg-f"><span>Phone / WhatsApp <i>*</i></span><input type="tel" name="phone" maxlength="20" required placeholder="0712 345 678" autocomplete="tel"></label>
							</div>
							<label class="mkg-f"><span>Describe the problem <em>(optional)</em></span><textarea name="notes" rows="2" maxlength="600" placeholder="e.g. Squeaking when braking, engine light on"></textarea></label>
						</fieldset>

						<label class="mkg-agree" data-mkg-agree>
							<input type="checkbox" name="terms" value="1" required>
							<span>I have read and agree to the <a href="#mkg-terms" data-mkg-terms-open>Garage &amp; service terms</a>, including that the price is agreed before work starts. <i>*</i></span>
						</label>
						<div class="mkg-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
						<p class="mkg-error" data-mkg-error role="alert" hidden></p>
						<button type="submit" class="mkg-btn mkg-btn--red mkg-btn--block" data-mkg-submit>
							<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l2 .9c.3.2.5.2.5.4.1.1.1.7-.1 1.3z"/></svg>
							<span>Book &amp; confirm on WhatsApp</span>
						</button>
						<p class="mkg-fine">Free to book. Your details are used only for this booking. <a href="#mkg-terms" data-mkg-terms-open>Terms</a></p>
					</form>

					<div class="mkg-done" data-mkg-done hidden>
						<span class="mkg-done__tick" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
						<h3>Booking received</h3>
						<p class="mkg-done__ref">Reference <strong data-mkg-ref></strong></p>
						<p data-mkg-summary></p>
						<p>WhatsApp should open with your booking written. <strong>Tap send</strong> and we will confirm your slot shortly.</p>
						<a class="mkg-btn mkg-btn--wa" data-mkg-wa href="#" target="_blank" rel="noopener">Open WhatsApp</a>
					</div>
				</div>

				<div class="mkg-intro mkg-intro--more">
					<ul class="mkg-ticks">
						<li><strong>Price agreed first.</strong> No work starts without your approval.</li>
						<li><strong>Genuine parts on hand.</strong> From our own counter on Kirinyaga Road.</li>
						<li><strong>Garage or mobile mechanic.</strong> We can come to you in Nairobi.</li>
					</ul>
					<ol class="mkg-flow">
						<li><b>1</b>Book online</li>
						<li><b>2</b>Confirm on WhatsApp</li>
						<li><b>3</b>Drive away</li>
					</ol>
					<dl class="mkg-contact">
						<div><dt>Garage</dt><dd><?php echo esc_html( $address ); ?><?php if ( $map ) : ?> <a href="<?php echo esc_url( $map ); ?>" target="_blank" rel="noopener">Directions</a><?php endif; ?></dd></div>
						<div><dt>Hours</dt><dd><?php echo esc_html( $hours ); ?></dd></div>
						<div><dt>Call</dt><dd><a href="<?php echo esc_url( $tel ); ?>"><?php echo esc_html( $phone ); ?></a></dd></div>
					</dl>
					<a class="mkg-btn mkg-btn--wa" href="<?php echo esc_url( $wa_hi ); ?>" target="_blank" rel="noopener">Prefer to chat? WhatsApp us</a>
				</div>
			</section>

			<section class="mkg-sec">
				<header class="mkg-sec__h">
					<h2>Workshop services</h2>
					<p>Tap a service to book it.</p>
				</header>
				<div class="mkg-cards">
					<?php foreach ( $cards as $key => $c ) : ?>
						<a class="mkg-card" href="#mkg-book" data-mkg-pick="<?php echo esc_attr( $key ); ?>">
							<span class="mkg-card__ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?php echo isset( $icons[ $key ] ) ? $icons[ $key ] : $icons['service']; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG paths. ?></svg></span>
							<span class="mkg-card__t"><strong><?php echo esc_html( $c[0] ); ?></strong><small><?php echo esc_html( $c[1] ); ?></small></span>
							<span class="mkg-card__go" aria-hidden="true">&rarr;</span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="mkg-sec mkg-faq">
				<header class="mkg-sec__h">
					<h2>Questions</h2>
				</header>
				<div class="mkg-faq__list">
					<details><summary>Will I know the price before the work starts?</summary><p>Yes. We inspect the vehicle first and agree the full price with you. No work starts without your approval.</p></details>
					<details><summary>Do you use genuine parts?</summary><p>We fit parts from our own counter and trusted suppliers on Kirinyaga Road, and explain the genuine, OEM and ex-Japan options so you choose what suits your budget.</p></details>
					<details><summary>Can I bring my own parts?</summary><p>Yes. Bring them along and our mechanics will fit them. Labour is quoted separately.</p></details>
					<details><summary>How long does a service take?</summary><p>A routine service usually takes one to two hours. For bigger repairs we give you a time when we quote.</p></details>
					<details><summary>How do I pay?</summary><p>M-Pesa, cash or card. You receive an itemised receipt on WhatsApp or e-mail.</p></details>
				</div>
			</section>

			<section class="mkg-sec mkg-terms" id="mkg-terms">
				<header class="mkg-sec__h">
					<h2>Garage &amp; service terms</h2>
					<p>Please read before booking.</p>
				</header>
				<?php echo self::terms_list( $terms ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in terms_list(). ?>
			</section>

			<dialog class="mkg-dlg" data-mkg-terms aria-labelledby="mkg-dlg-h">
				<div class="mkg-dlg__box">
					<header class="mkg-dlg__h"><h2 id="mkg-dlg-h">Garage &amp; service terms</h2><button type="button" class="mkg-dlg__x" data-mkg-terms-close aria-label="Close">&times;</button></header>
					<div class="mkg-dlg__body"><?php echo self::terms_list( $terms ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in terms_list(). ?></div>
					<footer class="mkg-dlg__f"><button type="button" class="mkg-btn mkg-btn--red mkg-btn--block" data-mkg-terms-accept>I agree</button></footer>
				</div>
			</dialog>

			<a class="mkg-sticky" href="#mkg-book" data-mkg-sticky hidden>Book a service</a>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Booking                                                             */
	/* ------------------------------------------------------------------ */

	private static function field( $key, $len ) {
		$v = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_book().
		return mb_substr( trim( $v ), 0, $len );
	}

	public static function ajax_book() {
		if ( ! check_ajax_referer( 'mkd_garage', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'This page has expired. Please refresh it and try again.' ), 403 );
		}
		// Bots: honeypot and impossibly fast submissions get a silent "success".
		if ( '' !== self::field( 'website', 50 ) || (int) self::field( 'elapsed', 10 ) < 4 ) {
			wp_send_json_success( array( 'ref' => 'APT', 'wa' => '', 'summary' => '' ) );
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'mkd_gb_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 6 ) {
			wp_send_json_error( array( 'message' => 'Too many bookings from this connection. Please message us on WhatsApp instead.' ), 429 );
		}

		$services = MKD_Desk_Appts::services();
		$name     = self::field( 'name', 120 );
		$phone    = MKD_Desk::phone( self::field( 'phone', 20 ) );
		$vehicle  = self::field( 'vehicle', 120 );
		$plate    = mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9 ]/', '', self::field( 'plate', 20 ) ) ), 0, 20 );
		$mileage  = preg_replace( '/[^0-9,. ]/', '', self::field( 'mileage', 20 ) );
		$service  = self::field( 'service', 40 );
		$type     = 'video' === self::field( 'type', 10 ) ? 'video' : 'studio';
		$location = self::field( 'location', 120 );
		$date     = MKD_Desk::valid_date( self::field( 'date', 10 ) );
		$time     = self::field( 'time', 5 );
		$notes    = isset( $_POST['notes'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ), 0, 600 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$err = '';
		if ( mb_strlen( $vehicle ) < 2 ) {
			$err = 'Please enter the make, model and year of your vehicle.';
		} elseif ( ! array_key_exists( $service, $services ) ) {
			$err = 'Please choose a service.';
		} elseif ( 'video' === $type && mb_strlen( $location ) < 3 ) {
			$err = 'Please tell us where the mobile mechanic should come.';
		} elseif ( '' === $date || $date < current_time( 'Y-m-d' ) ) {
			$err = 'Please choose a date from today onwards.';
		} elseif ( ! in_array( $time, MKD_Desk_Appts::slots( $date ), true ) ) {
			$err = 'Please choose one of the available times.';
		} elseif ( $date === current_time( 'Y-m-d' ) && $time <= current_time( 'H:i' ) ) {
			$err = 'That time has already passed today. Please choose a later time.';
		} elseif ( MKD_Desk_Appts::is_full( $date, $time ) ) {
			$err = 'Sorry, that time has just been booked. Please choose another time.';
		} elseif ( mb_strlen( $name ) < 2 ) {
			$err = 'Please enter your name.';
		} elseif ( '' === $phone || strlen( $phone ) < 11 ) {
			$err = 'Please enter a valid phone number, e.g. 0712 345 678.';
		} elseif ( '1' !== self::field( 'terms', 1 ) ) {
			$err = 'Please tick the box to agree to the Garage & service terms.';
		}
		if ( $err ) {
			wp_send_json_error( array( 'message' => $err ), 400 );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		global $wpdb;
		$t    = MKD_Desk::t_appts();
		$s    = MKD_Desk::settings();
		$ref  = MKD_Desk::new_ref( 'APT', $t );
		$now  = current_time( 'mysql' );
		$u    = wp_get_current_user();
		$note = trim( ( 'video' === $type ? 'Mobile mechanic at: ' . $location . "\n" : '' ) . $notes );
		$ok   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$t,
			array(
				'ref'           => $ref,
				'status'        => 'request',
				'service'       => $service,
				'type'          => $type,
				'appt_date'     => $date,
				'appt_time'     => $time,
				'duration'      => (int) $s['appt_duration'],
				'customer_name' => $name,
				'phone'         => $phone,
				'email'         => ( $u && $u->ID ) ? $u->user_email : '',
				'user_id'       => ( $u && $u->ID ) ? (int) $u->ID : 0,
				'vehicle'       => $vehicle,
				'plate'         => $plate,
				'mileage'       => $mileage,
				'notes'         => $note,
				'source'        => 'website',
				'log'           => wp_json_encode( array( array( 't' => $now, 'u' => 'Website', 'm' => 'Booked on the Garage & Service page, sent to WhatsApp. Customer agreed to the Garage & service terms (version ' . self::terms_version() . ').' ) ) ),
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => 'We could not save your booking. Please message us on WhatsApp.' ), 500 );
		}
		MKD_Desk::bust();

		$when  = MKD_Desk::date_label( $date, 'l j F Y' ) . ' at ' . MKD_Desk::time_label( $time );
		$where = 'video' === $type ? 'Mobile mechanic - ' . $location : 'At your garage, Kirinyaga Road';
		$lines = array(
			'Hello MK Auto Spares, I would like to book a garage service.',
			'',
			'*Booking ref:* ' . $ref,
			'*Service:* ' . $services[ $service ],
			'*Vehicle:* ' . $vehicle . ( $plate ? ' (' . $plate . ')' : '' ) . ( $mileage ? ' - ' . $mileage . ' km' : '' ),
			'*When:* ' . $when,
			'*Where:* ' . $where,
			'*Name:* ' . $name,
			'*Phone:* ' . MKD_Desk::phone_label( $phone ),
		);
		if ( '' !== $notes ) {
			$lines[] = '*Notes:* ' . $notes;
		}
		$lines[] = '*Terms:* I have read and agree to the Garage & service terms.';
		$lines[] = '';
		$lines[] = 'Please confirm my slot. Thank you.';
		$wa      = 'https://wa.me/' . ( class_exists( 'MKD_WA_Order' ) ? MKD_WA_Order::whatsapp_number() : '254702343302' ) . '?text=' . rawurlencode( implode( "\n", $lines ) );

		$admin = get_option( 'admin_email' );
		if ( $admin ) {
			wp_mail( $admin, 'New garage booking ' . $ref . ' - ' . $services[ $service ], str_replace( '*', '', implode( "\n", $lines ) ) . "\n\nOpen: " . MKD_Desk::url( MKD_Desk::P_APPTS ) );
		}
		if ( class_exists( 'MKD_Desk_Mkt' ) && method_exists( 'MKD_Desk_Mkt', 'upsert' ) ) {
			MKD_Desk_Mkt::upsert( array( 'name' => $name, 'phone' => $phone, 'email' => '', 'user_id' => 0, 'source' => 'appointment', 'consent' => false, 'spent' => 0 ) );
		}

		wp_send_json_success(
			array(
				'ref'     => $ref,
				'wa'      => $wa,
				'summary' => $services[ $service ] . ' - ' . $when . '. ' . $where . '.',
			)
		);
	}
}
