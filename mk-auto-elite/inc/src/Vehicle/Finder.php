<?php
/**
 * Vehicle fitment engine: taxonomies, session vehicle, finder UI.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Vehicle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers fitment taxonomies and renders the "Find Parts for Your Vehicle" UI.
 */
class Finder {

	private const COOKIE = 'mkae_vehicle';

	/**
	 * Fitment taxonomies: slug => singular label.
	 *
	 * @var array<string,string>
	 */
	private array $taxonomies = array();

	/**
	 * Translated labels. Built on demand (after init) so the text domain is
	 * not loaded too early (WordPress 6.7 notice).
	 *
	 * @return array<string,string>
	 */
	private function taxonomies_labels(): array {
		return array(
			'mk_make' => __( 'Make', 'mk-auto-elite' ),
			'mk_model' => __( 'Model', 'mk-auto-elite' ),
			'mk_year' => __( 'Year', 'mk-auto-elite' ),
			'mk_engine' => __( 'Engine', 'mk-auto-elite' ),
			'mk_fuel' => __( 'Fuel Type', 'mk-auto-elite' ),
			'mk_transmission' => __( 'Transmission', 'mk-auto-elite' ),
		);
	}

	/**
	 * Hook in.
	 */
	public function __construct() {
		$this->taxonomies = array(
			'mk_make'         => 'Make',
			'mk_model'        => 'Model',
			'mk_year'         => 'Year',
			'mk_engine'       => 'Engine',
			'mk_fuel'         => 'Fuel Type',
			'mk_transmission' => 'Transmission',
		);

		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_shortcode( 'mk_vehicle_finder', array( $this, 'shortcode' ) );
	}

	/**
	 * Register all fitment taxonomies against the product post type.
	 */
	public function register_taxonomies(): void {
		$object = post_type_exists( 'product' ) ? 'product' : 'post';

		foreach ( $this->taxonomies_labels() as $slug => $label ) {
			register_taxonomy(
				$slug,
				$object,
				array(
					'labels'            => array(
						'name'          => $label,
						'singular_name' => $label,
						'menu_name'     => $label,
					),
					'hierarchical'      => in_array( $slug, array( 'mk_make', 'mk_model' ), true ),
					'public'            => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'query_var'         => true,
					'rewrite'           => array(
						'slug' => match ( $slug ) {
							'mk_make'  => 'brands',
							'mk_model' => 'models',
							default    => str_replace( 'mk_', 'vehicle-', $slug ),
						},
					),
				)
			);
		}
	}

	/**
	 * List taxonomy slugs.
	 *
	 * @return string[]
	 */
	public function taxonomy_slugs(): array {
		return array_keys( $this->taxonomies );
	}

	/**
	 * Read the active vehicle from the session cookie.
	 *
	 * @return array<string,string>
	 */
	public static function active_vehicle(): array {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		$raw     = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? array_map( 'sanitize_text_field', $decoded ) : array();
	}

	/**
	 * Shortcode wrapper.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array( 'title' => __( 'Find Parts for Your Vehicle', 'mk-auto-elite' ) ),
			(array) $atts,
			'mk_vehicle_finder'
		);
		ob_start();
		$this->render( (string) $atts['title'] );
		return (string) ob_get_clean();
	}

	/**
	 * Render the cascading vehicle finder. Options are loaded via AJAX.
	 */
	public function render( string $title = '' ): void {
		$title  = $title ? $title : __( 'Find Parts for Your Vehicle', 'mk-auto-elite' );
		$active = self::active_vehicle();
		$steps  = array(
			'mk_make'         => __( 'Make', 'mk-auto-elite' ),
			'mk_model'        => __( 'Model', 'mk-auto-elite' ),
			'mk_year'         => __( 'Year', 'mk-auto-elite' ),
			'mk_engine'       => __( 'Engine', 'mk-auto-elite' ),
			'mk_fuel'         => __( 'Fuel', 'mk-auto-elite' ),
			'mk_transmission' => __( 'Transmission', 'mk-auto-elite' ),
		);
		?>
		<form class="mkae-finder" role="search" aria-label="<?php echo esc_attr( $title ); ?>" action="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>" method="get">
			<h2 class="mkae-finder__title"><?php echo esc_html( $title ); ?></h2>
			<div class="mkae-finder__grid">
				<?php foreach ( $steps as $slug => $label ) : ?>
					<label class="mkae-finder__field">
						<span class="mkae-finder__label"><?php echo esc_html( $label ); ?></span>
						<select
							class="mkae-finder__select"
							name="<?php echo esc_attr( $slug ); ?>"
							data-tax="<?php echo esc_attr( $slug ); ?>"
							<?php echo ( 'mk_make' === $slug ) ? '' : 'disabled'; ?>>
							<option value=""><?php echo esc_html( 'mk_make' === $slug ? __( 'Select make', 'mk-auto-elite' ) : $label ); ?></option>
						</select>
					</label>
				<?php endforeach; ?>
				<button type="submit" class="mkae-btn mkae-btn--primary mkae-finder__submit">
					<?php esc_html_e( 'Find Parts', 'mk-auto-elite' ); ?>
				</button>
			</div>
			<?php if ( ! is_front_page() && ! empty( $active['label'] ) ) : ?>
				<p class="mkae-finder__active">
					<?php esc_html_e( 'Shopping for:', 'mk-auto-elite' ); ?>
					<strong><?php echo esc_html( $active['label'] ); ?></strong>
					<button type="button" class="mkae-finder__clear" data-mkae-clear-vehicle><?php esc_html_e( 'Change', 'mk-auto-elite' ); ?></button>
				</p>
			<?php endif; ?>
		</form>
		<?php
	}
}
