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

		// v2.23.0: one cached make -> model map so the first two steps fill instantly.
		add_filter( 'template_include', array( $this, 'hub_template' ), 50 );
		add_action( 'wp_ajax_mkae_finder_map', array( $this, 'ajax_map' ) );
		add_action( 'wp_ajax_nopriv_mkae_finder_map', array( $this, 'ajax_map' ) );
		add_action( 'save_post_product', array( __CLASS__, 'flush_map' ) );
		add_action( 'set_object_terms', array( $this, 'maybe_flush_map' ), 10, 4 );
		add_action( 'edited_mk_make', array( __CLASS__, 'flush_map' ) );
		add_action( 'edited_mk_model', array( __CLASS__, 'flush_map' ) );
		add_action( 'delete_mk_model', array( __CLASS__, 'flush_map' ) );
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
	 * Use the theme hub template for vehicle make and model archives
	 * (WooCommerce's template loader otherwise takes them over).
	 *
	 * @param string $template Template path.
	 */
	public function hub_template( $template ) {
		if ( is_tax( array( 'mk_make', 'mk_model' ) ) ) {
			$hub = locate_template( array( 'template-parts/hub.php' ) );
			if ( $hub ) {
				return $hub;
			}
		}
		return $template;
	}

	/**
	 * Clear the cached finder map (catalogue changed).
	 */
	public static function flush_map(): void {
		delete_transient( 'mkae_finder_map' );
		update_option( 'mkae_finder_map_v', (string) time(), false );
	}

	/**
	 * Flush when make/model terms on a product change.
	 *
	 * @param int    $object_id Object.
	 * @param array  $terms Terms.
	 * @param array  $tt_ids TT ids.
	 * @param string $taxonomy Taxonomy.
	 */
	public function maybe_flush_map( $object_id, $terms, $tt_ids, $taxonomy ): void {
		if ( in_array( $taxonomy, array( 'mk_make', 'mk_model' ), true ) ) {
			self::flush_map();
		}
	}

	/**
	 * Build the make -> model map from published products (two queries).
	 *
	 * @return array{v:string,makes:array}
	 */
	public static function build_map(): array {
		$cached = get_transient( 'mkae_finder_map' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$sql   = "SELECT tr.object_id AS pid, t.slug, t.name FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'product' AND p.post_status = 'publish'";
		$makes = $wpdb->get_results( $wpdb->prepare( $sql, 'mk_make' ) ); // phpcs:ignore WordPress.DB
		$mods  = $wpdb->get_results( $wpdb->prepare( $sql, 'mk_model' ) ); // phpcs:ignore WordPress.DB
		$p_mod = array();
		foreach ( (array) $mods as $r ) {
			$p_mod[ (int) $r->pid ][ $r->slug ] = $r->name;
		}
		$map = array();
		foreach ( (array) $makes as $r ) {
			$k = $r->slug;
			if ( ! isset( $map[ $k ] ) ) {
				$map[ $k ] = array( 'n' => $r->name, 'c' => 0, 'm' => array() );
			}
			$map[ $k ]['c']++;
			foreach ( $p_mod[ (int) $r->pid ] ?? array() as $ms => $mn ) {
				if ( ! isset( $map[ $k ]['m'][ $ms ] ) ) {
					$map[ $k ]['m'][ $ms ] = array( $mn, 0 );
				}
				$map[ $k ]['m'][ $ms ][1]++;
			}
		}
		uasort( $map, static fn( $a, $b ) => strcasecmp( $a['n'], $b['n'] ) );
		$out = array();
		foreach ( $map as $slug => $m ) {
			uasort( $m['m'], static fn( $a, $b ) => strcasecmp( $a[0], $b[0] ) );
			$models = array();
			foreach ( $m['m'] as $ms => $mv ) {
				$models[] = array( $ms, $mv[0], $mv[1] );
			}
			$out[] = array( 's' => $slug, 'n' => $m['n'], 'c' => $m['c'], 'm' => $models );
		}
		$data = array(
			'v'     => (string) get_option( 'mkae_finder_map_v', '1' ),
			'makes' => $out,
		);
		set_transient( 'mkae_finder_map', $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Public, cacheable JSON map.
	 */
	public function ajax_map(): void {
		$data = self::build_map();
		nocache_headers();
		header_remove( 'Cache-Control' );
		header_remove( 'Expires' );
		header_remove( 'Pragma' );
		header( 'Cache-Control: public, max-age=21600' );
		wp_send_json_success( $data );
	}

	/**
	 * Most-stocked makes for the quick-pick chips.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	private static function popular_makes(): array {
		$out   = array();
		$terms = taxonomy_exists( 'mk_make' ) ? get_terms( array( 'taxonomy' => 'mk_make', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 8 ) ) : array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array( $t->slug, $t->name );
			}
		}
		return $out;
	}

	/**
	 * Render the vehicle finder (v2.23.0): Make, Model and Year up front, the
	 * rest under "More options"; quick-pick makes; live part count.
	 *
	 * @param string $title Heading.
	 */
	public function render( string $title = '' ): void {
		$title   = $title ? $title : __( 'Find Parts for Your Vehicle', 'mk-auto-elite' );
		$primary = array(
			'mk_make'  => array( __( 'Make', 'mk-auto-elite' ), __( 'Select make', 'mk-auto-elite' ) ),
			'mk_model' => array( __( 'Model', 'mk-auto-elite' ), __( 'Select model', 'mk-auto-elite' ) ),
			'mk_year'  => array( __( 'Year', 'mk-auto-elite' ), __( 'Any year', 'mk-auto-elite' ) ),
		);
		$more    = array(
			'mk_engine'       => array( __( 'Engine', 'mk-auto-elite' ), __( 'Any engine', 'mk-auto-elite' ) ),
			'mk_fuel'         => array( __( 'Fuel', 'mk-auto-elite' ), __( 'Any fuel', 'mk-auto-elite' ) ),
			'mk_transmission' => array( __( 'Gearbox', 'mk-auto-elite' ), __( 'Any gearbox', 'mk-auto-elite' ) ),
		);
		$preset  = '';
		$q       = get_queried_object();
		if ( $q instanceof \WP_Term && 'mk_make' === $q->taxonomy ) {
			$preset = $q->slug;
		}
		$uid  = wp_unique_id( 'mkvf-' );
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$field = static function ( string $slug, array $l, string $uid ) {
			$id = $uid . '-' . $slug;
			?>
			<div class="mkae-finder__field mkae-finder__field--<?php echo esc_attr( $slug ); ?>">
				<label class="mkae-finder__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $l[0] ); ?></label>
				<div class="mkae-finder__control">
					<select id="<?php echo esc_attr( $id ); ?>" class="mkae-finder__select" name="<?php echo esc_attr( $slug ); ?>" data-tax="<?php echo esc_attr( $slug ); ?>" data-placeholder="<?php echo esc_attr( $l[1] ); ?>" <?php echo 'mk_make' === $slug ? '' : 'disabled'; ?>>
						<option value=""><?php echo esc_html( 'mk_make' === $slug ? __( 'Loading makes...', 'mk-auto-elite' ) : $l[1] ); ?></option>
					</select>
				</div>
			</div>
			<?php
		};
		?>
		<form class="mkae-finder" role="search" aria-label="<?php echo esc_attr( $title ); ?>" action="<?php echo esc_url( $shop ); ?>" method="get" data-map-v="<?php echo esc_attr( (string) get_option( 'mkae_finder_map_v', '1' ) ); ?>"<?php echo $preset ? ' data-preset-make="' . esc_attr( $preset ) . '"' : ''; ?>>
			<script>window.MKAE=window.MKAE||{};window.MKAE.finderV=<?php echo wp_json_encode( (string) get_option( 'mkae_finder_map_v', '1' ) ); ?>;</script>
			<div class="mkae-finder__head">
				<span class="mkae-finder__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13l2-5a2 2 0 0 1 1.9-1.3h10.2A2 2 0 0 1 19 8l2 5v4h-2.2M5.2 17H3v-4h18"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/></svg></span>
				<div>
					<h2 class="mkae-finder__title"><?php echo esc_html( $title ); ?></h2>
					<p class="mkae-finder__sub"><?php esc_html_e( 'Choose your car to see only the parts that fit.', 'mk-auto-elite' ); ?></p>
				</div>
			</div>

			<div class="mkae-finder__saved" data-mkvf-saved hidden>
				<span><?php esc_html_e( 'Your car:', 'mk-auto-elite' ); ?> <strong data-mkvf-saved-label></strong></span>
				<a class="mkae-finder__saved-go" href="#" data-mkvf-saved-go><?php esc_html_e( 'Shop parts', 'mk-auto-elite' ); ?> &rarr;</a>
				<button type="button" class="mkae-finder__clear" data-mkae-clear-vehicle><?php esc_html_e( 'Change', 'mk-auto-elite' ); ?></button>
			</div>

			<?php $pop = self::popular_makes(); ?>
			<?php if ( $pop && ! $preset ) : ?>
				<div class="mkae-finder__chips" role="group" aria-label="<?php esc_attr_e( 'Popular makes', 'mk-auto-elite' ); ?>">
					<?php foreach ( $pop as $m ) : ?>
						<button type="button" class="mkae-finder__chip" data-make="<?php echo esc_attr( $m[0] ); ?>"><?php echo esc_html( $m[1] ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="mkae-finder__grid">
				<?php
				foreach ( $primary as $slug => $l ) {
					$field( $slug, $l, $uid );
				}
				?>
			</div>

			<details class="mkae-finder__more">
				<summary><?php esc_html_e( 'More options: engine, fuel, gearbox', 'mk-auto-elite' ); ?></summary>
				<div class="mkae-finder__grid mkae-finder__grid--more">
					<?php
					foreach ( $more as $slug => $l ) {
						$field( $slug, $l, $uid );
					}
					?>
				</div>
			</details>

			<button type="submit" class="mkae-btn mkae-btn--primary mkae-finder__submit" data-mkvf-submit>
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
				<span data-mkvf-submit-label><?php esc_html_e( 'Find Parts', 'mk-auto-elite' ); ?></span>
			</button>
			<p class="mkae-finder__hint"><?php esc_html_e( 'Not listed? Send your chassis number on WhatsApp and we will find it.', 'mk-auto-elite' ); ?></p>
		</form>
		<?php
	}
}
