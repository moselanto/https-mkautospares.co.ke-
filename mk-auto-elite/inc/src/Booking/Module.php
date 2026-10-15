<?php
/**
 * Service / repair booking module.
 *
 * Registers a private "Bookings" post type (stored in wp-admin), a
 * [mk_booking_form] shortcode for the front-end form, admin list columns and a
 * read-only details box. The AJAX submit handler lives in Ajax\Handlers
 * (mkae_submit_booking) and creates the booking + emails the shop and customer.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking custom post type, form shortcode and admin presentation.
 */
class Module {

	const POST_TYPE = 'mkae_booking';

	/**
	 * Meta keys stored on each booking.
	 *
	 * @var array<string,string>
	 */
	private array $meta_fields = array(
		'_mkae_name'    => 'Customer',
		'_mkae_phone'   => 'Phone',
		'_mkae_email'   => 'Email',
		'_mkae_vehicle' => 'Vehicle',
		'_mkae_service' => 'Service',
		'_mkae_date'    => 'Preferred date',
		'_mkae_time'    => 'Preferred time',
		'_mkae_message' => 'Notes',
	);

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_shortcode( 'mk_booking_form', array( $this, 'shortcode_form' ) );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'details_box' ) );
	}

	/**
	 * Default service options. Filterable so the list can be tuned without code.
	 *
	 * @return string[]
	 */
	public static function services(): array {
		$services = array(
			__( 'General Service', 'mk-auto-elite' ),
			__( 'Engine Repair', 'mk-auto-elite' ),
			__( 'Suspension & Steering', 'mk-auto-elite' ),
			__( 'Brakes', 'mk-auto-elite' ),
			__( 'Diagnostics', 'mk-auto-elite' ),
			__( 'Electrical', 'mk-auto-elite' ),
			__( 'Bodywork', 'mk-auto-elite' ),
			__( 'Other', 'mk-auto-elite' ),
		);
		/**
		 * Filter the bookable service list.
		 *
		 * @param string[] $services Service labels.
		 */
		return (array) apply_filters( 'mkae_booking_services', $services );
	}

	/**
	 * Register the private Bookings post type (admin storage only).
	 */
	public function register_cpt(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Bookings', 'mk-auto-elite' ),
					'singular_name' => __( 'Booking', 'mk-auto-elite' ),
					'menu_name'     => __( 'Bookings', 'mk-auto-elite' ),
					'all_items'     => __( 'All Bookings', 'mk-auto-elite' ),
					'search_items'  => __( 'Search Bookings', 'mk-auto-elite' ),
					'not_found'     => __( 'No bookings yet.', 'mk-auto-elite' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-calendar-alt',
				'menu_position'   => 58,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
			)
		);
	}

	/**
	 * Render the booking form. Submission is handled by booking.js -> the
	 * mkae_submit_booking AJAX endpoint (nonce-verified server-side).
	 */
	public function shortcode_form(): string {
		$services = self::services();

		ob_start();
		?>
		<div class="mkae-booking">
			<ol class="mkae-steps" aria-label="<?php esc_attr_e( 'How booking works', 'mk-auto-elite' ); ?>">
				<li><strong><?php esc_html_e( 'Send a request', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Tell us your car, the service and a preferred time.', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'We confirm', 'mk-auto-elite' ); ?></strong><span><?php esc_html_e( 'Our team calls or WhatsApps you to confirm the slot and any parts needed.', 'mk-auto-elite' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Visit the workshop', 'mk-auto-elite' ); ?></strong><span><?php echo esc_html( \MKAutoElite\Customizer::get( 'location' ) . ' - ' . \MKAutoElite\Customizer::get( 'hours' ) ); ?></span></li>
			</ol>
			<p class="mkae-booking__note"><?php esc_html_e( 'This is a booking request. Your appointment is confirmed once we contact you.', 'mk-auto-elite' ); ?></p>
			<form class="mkae-booking__form" data-mkae-booking novalidate>
				<div class="mkae-booking__row">
					<label class="mkae-field">
						<span class="mkae-field__label"><?php esc_html_e( 'Your name', 'mk-auto-elite' ); ?> *</span>
						<input type="text" name="mkae_name" required autocomplete="name">
					</label>
					<label class="mkae-field">
						<span class="mkae-field__label"><?php esc_html_e( 'Phone / WhatsApp', 'mk-auto-elite' ); ?> *</span>
						<input type="tel" name="mkae_phone" required autocomplete="tel" inputmode="tel">
					</label>
				</div>

				<div class="mkae-booking__row">
					<label class="mkae-field">
						<span class="mkae-field__label"><?php esc_html_e( 'Email', 'mk-auto-elite' ); ?></span>
						<input type="email" name="mkae_email" autocomplete="email">
					</label>
					<label class="mkae-field">
						<span class="mkae-field__label"><?php esc_html_e( 'Service needed', 'mk-auto-elite' ); ?> *</span>
						<select name="mkae_service" required>
							<option value=""><?php esc_html_e( 'Select a service', 'mk-auto-elite' ); ?></option>
							<?php foreach ( $services as $service ) : ?>
								<option value="<?php echo esc_attr( $service ); ?>"><?php echo esc_html( $service ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>

				<div class="mkae-booking__row">
					<label class="mkae-field">
						<span class="mkae-field__label"><?php esc_html_e( 'Vehicle (make, model, year)', 'mk-auto-elite' ); ?></span>
						<input type="text" name="mkae_vehicle" placeholder="<?php esc_attr_e( 'e.g. Toyota Fielder 2015', 'mk-auto-elite' ); ?>">
					</label>
				</div>

				<div class="mkae-booking__row">
					<label class="mkae-field">
						<span class="mkae-field__label"><?php esc_html_e( 'Preferred date', 'mk-auto-elite' ); ?></span>
						<input type="date" name="mkae_date">
					</label>
					<label class="mkae-field">
						<span class="mkae-field__label"><?php esc_html_e( 'Preferred time', 'mk-auto-elite' ); ?></span>
						<input type="time" name="mkae_time">
					</label>
				</div>

				<label class="mkae-field">
					<span class="mkae-field__label"><?php esc_html_e( 'Describe the problem or request', 'mk-auto-elite' ); ?></span>
					<textarea name="mkae_message" rows="4"></textarea>
				</label>

				<div class="mkae-hp" aria-hidden="true" hidden>
					<input type="text" name="mkae_hp" tabindex="-1" autocomplete="off" value="" aria-hidden="true">
				</div>
				<input type="hidden" name="mkae_ts" value="<?php echo esc_attr( (string) time() ); ?>">

				<div class="mkae-booking__actions">
					<button type="submit" class="mkae-btn mkae-btn--primary" data-mkae-booking-submit><?php esc_html_e( 'Request booking', 'mk-auto-elite' ); ?></button>
					<span class="mkae-booking__status" data-mkae-booking-status role="status" aria-live="polite"></span>
				</div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Admin list columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function columns( $columns ): array {
		$new = array();
		$new['cb']            = $columns['cb'] ?? '';
		$new['title']         = __( 'Booking', 'mk-auto-elite' );
		$new['mkae_service']  = __( 'Service', 'mk-auto-elite' );
		$new['mkae_phone']    = __( 'Phone', 'mk-auto-elite' );
		$new['mkae_when']     = __( 'Preferred', 'mk-auto-elite' );
		$new['date']          = $columns['date'] ?? __( 'Received', 'mk-auto-elite' );
		return $new;
	}

	/**
	 * Admin column output.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ): void {
		if ( 'mkae_service' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_mkae_service', true ) );
		} elseif ( 'mkae_phone' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_mkae_phone', true ) );
		} elseif ( 'mkae_when' === $column ) {
			$date = (string) get_post_meta( $post_id, '_mkae_date', true );
			$time = (string) get_post_meta( $post_id, '_mkae_time', true );
			echo esc_html( trim( $date . ' ' . $time ) );
		}
	}

	/**
	 * Add the read-only details box on the booking edit screen.
	 */
	public function details_box(): void {
		add_meta_box(
			'mkae_booking_details',
			__( 'Booking details', 'mk-auto-elite' ),
			array( $this, 'render_details_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render stored booking meta as a read-only table.
	 *
	 * @param \WP_Post $post Booking post.
	 */
	public function render_details_box( $post ): void {
		echo '<table class="widefat striped"><tbody>';
		foreach ( $this->meta_fields as $key => $label ) {
			$value = (string) get_post_meta( $post->ID, $key, true );
			if ( '' === $value ) {
				continue;
			}
			printf(
				'<tr><th style="width:180px;text-align:left">%s</th><td>%s</td></tr>',
				esc_html( $label ),
				nl2br( esc_html( $value ) )
			);
		}
		echo '</tbody></table>';
	}
}
