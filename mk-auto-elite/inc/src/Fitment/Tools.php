<?php
/**
 * Fitment tools: guided Parts Finder, Symptom -> Part wizard, and the
 * product-page "Will this fit your car?" compatibility checker.
 *
 * All three build on the existing fitment data (mk_make / mk_model / mk_year
 * taxonomies plus the _mk_* meta). The finder and checker reuse the cascading
 * mkae_get_terms endpoint; results and verdicts come from the two AJAX
 * endpoints added in Ajax\Handlers (mkae_vehicle_parts, mkae_check_fit).
 *
 * The symptom wizard is a rules-based decision tree today; the data shape
 * (groups -> options -> causes) is ready to be produced by an LLM later
 * without touching the UI.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Fitment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the three fitment tools and their front-end config.
 */
class Tools {

	/**
	 * Cascade steps used by the finder and checker: slug => label.
	 *
	 * @var array<string,string>
	 */
	private array $steps = array();

	/**
	 * Hook in. Modules only register hooks in their constructor.
	 */
	public function __construct() {
		$this->steps = array(
			'mk_make'   => __( 'Make', 'mk-auto-elite' ),
			'mk_model'  => __( 'Model', 'mk-auto-elite' ),
			'mk_year'   => __( 'Year', 'mk-auto-elite' ),
			'mk_engine' => __( 'Engine', 'mk-auto-elite' ),
		);

		add_shortcode( 'mk_parts_finder', array( $this, 'parts_finder' ) );
		add_shortcode( 'mk_symptom_wizard', array( $this, 'symptom_wizard' ) );
		add_shortcode( 'mk_fit_checker', array( $this, 'fit_checker_shortcode' ) );

		// Auto-inject the fit checker between the trust badges (29) and the
		// WhatsApp button (35) on single product pages.
		add_action( 'woocommerce_single_product_summary', array( $this, 'product_fit_checker' ), 32 );

		// Localize config after Assets has registered the script (priority 10).
		add_action( 'wp_enqueue_scripts', array( $this, 'localize' ), 20 );
	}

	/**
	 * Build the make/model/year/engine cascade fields shared by finder + checker.
	 *
	 * @param string $context Extra class suffix, e.g. 'pf' or 'fit'.
	 * @param array  $only    Restrict to these step slugs (empty = all).
	 */
	private function cascade_fields( string $context, array $only = array() ): string {
		$out = '';
		foreach ( $this->steps as $slug => $label ) {
			if ( ! empty( $only ) && ! in_array( $slug, $only, true ) ) {
				continue;
			}
			$optional = in_array( $slug, array( 'mk_year', 'mk_engine' ), true );
			$shown    = $optional ? $label . ' ' . __( '(optional)', 'mk-auto-elite' ) : $label;
			$is_make  = ( 'mk_make' === $slug );
			$out     .= '<label class="mkae-' . esc_attr( $context ) . '__field">';
			$out     .= '<span class="mkae-' . esc_attr( $context ) . '__label">' . esc_html( $shown ) . '</span>';
			$out     .= '<select class="mkae-' . esc_attr( $context ) . '__select" data-tax="' . esc_attr( $slug ) . '"' . ( $is_make ? '' : ' disabled' ) . '>';
			$out     .= '<option value="">' . esc_html( $is_make ? __( 'Select make', 'mk-auto-elite' ) : $label ) . '</option>';
			$out     .= '</select></label>';
		}
		return $out;
	}

	/**
	 * [mk_parts_finder] — guided make/model/year -> parts.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function parts_finder( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'    => __( 'Find Parts for Your Car', 'mk-auto-elite' ),
				'subtitle' => __( 'Choose your vehicle and we will show the parts that fit, grouped by category.', 'mk-auto-elite' ),
			),
			(array) $atts,
			'mk_parts_finder'
		);

		$html  = '<div class="mkae-pf" data-mkae-parts-finder>';
		$html .= '<h2 class="mkae-pf__title">' . esc_html( (string) $atts['title'] ) . '</h2>';
		$html .= '<p class="mkae-pf__sub">' . esc_html( (string) $atts['subtitle'] ) . '</p>';
		$html .= '<div class="mkae-pf__grid">' . $this->cascade_fields( 'pf' ) . '</div>';
		$html .= '<button type="button" class="mkae-btn mkae-btn--primary mkae-pf__go" data-mkae-pf-go disabled>' . esc_html__( 'Show parts', 'mk-auto-elite' ) . '</button>';
		$html .= '<div class="mkae-pf__results" data-mkae-pf-results hidden></div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * [mk_symptom_wizard] — symptom -> likely part decision tree.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function symptom_wizard( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'    => __( 'Which Part Do I Need?', 'mk-auto-elite' ),
				'subtitle' => __( 'Describe what your car is doing and we will point you to the parts to check.', 'mk-auto-elite' ),
			),
			(array) $atts,
			'mk_symptom_wizard'
		);

		$html  = '<div class="mkae-sw" data-mkae-symptom-wizard>';
		$html .= '<h2 class="mkae-sw__title">' . esc_html( (string) $atts['title'] ) . '</h2>';
		$html .= '<p class="mkae-sw__sub">' . esc_html( (string) $atts['subtitle'] ) . '</p>';
		$html .= '<div class="mkae-sw__stage" data-mkae-sw-stage></div>';
		$html .= '<noscript><p>' . esc_html__( 'Please enable JavaScript to use the guide, or message us on WhatsApp and we will help.', 'mk-auto-elite' ) . '</p></noscript>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render the compatibility checker widget for a product.
	 *
	 * @param int $product_id Product ID.
	 */
	private function render_fit_checker( int $product_id ): string {
		if ( $product_id <= 0 ) {
			return '';
		}
		$html  = '<div class="mkae-fit" data-mkae-fit-checker data-product-id="' . esc_attr( (string) $product_id ) . '">';
		$html .= '<h3 class="mkae-fit__title">' . esc_html__( 'Will this fit your car?', 'mk-auto-elite' ) . '</h3>';
		$html .= '<div class="mkae-fit__grid">' . $this->cascade_fields( 'fit', array( 'mk_make', 'mk_model', 'mk_year' ) ) . '</div>';
		$html .= '<button type="button" class="mkae-btn mkae-btn--outline mkae-fit__go" data-mkae-fit-go disabled>' . esc_html__( 'Check fit', 'mk-auto-elite' ) . '</button>';
		$html .= '<div class="mkae-fit__result" data-mkae-fit-result role="status" aria-live="polite" hidden></div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Product-page hook: auto-inject the fit checker.
	 */
	public function product_fit_checker(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		echo $this->render_fit_checker( (int) $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * [mk_fit_checker id="123"] — standalone checker (defaults to current product).
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function fit_checker_shortcode( $atts ): string {
		global $product;
		$default = ( $product instanceof \WC_Product ) ? (int) $product->get_id() : 0;
		$atts    = shortcode_atts( array( 'id' => $default ), (array) $atts, 'mk_fit_checker' );
		return $this->render_fit_checker( absint( $atts['id'] ) );
	}

	/**
	 * Resolve a product category name to its archive URL, falling back to a
	 * product search when the category does not exist.
	 */
	private function cat_url( string $name ): string {
		if ( taxonomy_exists( 'product_cat' ) ) {
			$term = get_term_by( 'name', $name, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					return (string) $link;
				}
			}
		}
		return add_query_arg(
			array(
				'post_type' => 'product',
				's'         => $name,
			),
			home_url( '/' )
		);
	}

	/**
	 * Build one "likely cause" row with a resolved category link.
	 */
	private function cause( string $part, string $why ): array {
		return array(
			'part' => $part,
			'why'  => $why,
			'url'  => $this->cat_url( $part ),
		);
	}

	/**
	 * The symptom decision tree. Rules today; LLM-ready shape for later.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function symptom_tree(): array {
		return array(
			array(
				'id'      => 'noise',
				'label'   => __( 'Noises (knocking, squealing, grinding)', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Knocking or clunking over bumps', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Shock Absorbers & Struts', __( 'Worn shocks or struts let the suspension bang over bumps.', 'mk-auto-elite' ) ),
							$this->cause( 'Bushings & Mounts', __( 'Perished bushes and mounts cause knocking and play.', 'mk-auto-elite' ) ),
							$this->cause( 'Ball Joints', __( 'A worn ball joint knocks and affects steering.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Squealing when braking', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Brakes', __( 'Worn brake pads squeal to warn you they need replacing.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Grinding when braking', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Brakes', __( 'Metal-on-metal grinding usually means pads and discs are worn out.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Clicking when turning', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Transmission & Gearbox', __( 'A clicking CV joint or drive shaft is common on front-wheel-drive cars.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Humming or whining that rises with speed', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Wheels', __( 'A failing wheel bearing hums and gets louder with speed.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'braking',
				'label'   => __( 'Braking problems', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Soft or spongy brake pedal', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Brakes', __( 'Air or a leak in the brake lines, or a failing master cylinder.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Car pulls to one side when braking', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Brakes', __( 'A sticking caliper or uneven pads/discs pulls the car under braking.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Vibration through the pedal', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Brakes', __( 'Warped brake discs shudder through the pedal when you stop.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'ride',
				'label'   => __( 'Ride and handling', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Bouncy or floaty ride', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Shock Absorbers & Struts', __( 'Tired shocks let the car keep bouncing after a bump.', 'mk-auto-elite' ) ),
							$this->cause( 'Coil Springs', __( 'Weak or broken springs change the ride height and comfort.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Pulls to one side while driving', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Tie Rod & Rack Ends', __( 'Worn tie rod / rack ends throw out the alignment.', 'mk-auto-elite' ) ),
							$this->cause( 'Steering', __( 'Steering and alignment components can cause a steady pull.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Uneven tyre wear', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Control Arms', __( 'Worn control arms and bushes upset wheel angles and wear tyres.', 'mk-auto-elite' ) ),
							$this->cause( 'Tie Rod & Rack Ends', __( 'Bad alignment from worn ends scrubs the tyre edges.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'One corner sits low', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Coil Springs', __( 'A sagging or broken coil spring drops one corner.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'steering',
				'label'   => __( 'Steering issues', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Hard to turn the wheel', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Steering', __( 'Low power-steering fluid or a failing pump/rack makes steering heavy.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Play or looseness in the wheel', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Tie Rod & Rack Ends', __( 'Worn tie rod / rack ends create slack in the steering.', 'mk-auto-elite' ) ),
							$this->cause( 'Ball Joints', __( 'A loose ball joint adds play and knocking.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'starting',
				'label'   => __( 'Will not start / electrical', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Clicks but will not start', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Batteries', __( 'A flat or weak battery is the most common cause of a no-start click.', 'mk-auto-elite' ) ),
							$this->cause( 'Electrical & Sensors', __( 'A worn starter motor can also click without cranking.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Battery keeps going flat', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Electrical & Sensors', __( 'A failing alternator stops charging the battery while you drive.', 'mk-auto-elite' ) ),
							$this->cause( 'Batteries', __( 'An old battery no longer holds charge.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Dim or flickering lights', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Electrical & Sensors', __( 'A weak alternator or bad connection dims the lights.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Warning light on the dash', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Electrical & Sensors', __( 'Dash warnings are often a sensor fault - have the code read first.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'cooling',
				'label'   => __( 'Overheating and cooling', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Temperature gauge runs high', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Cooling', __( 'Radiator, thermostat, water pump or low coolant can all cause overheating.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Coolant leak or puddle', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Cooling', __( 'Check the radiator, hoses and water pump for leaks.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Heater not blowing warm', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Cooling', __( 'A stuck thermostat or low coolant stops the heater warming up.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'engine',
				'label'   => __( 'Engine running rough', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Rough idle or misfire', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Engine & Components', __( 'Worn spark plugs or ignition coils cause a rough idle and misfire.', 'mk-auto-elite' ) ),
							$this->cause( 'Electrical & Sensors', __( 'A faulty sensor can upset the fuel/air mix.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Loss of power', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Fuel System', __( 'A blocked fuel filter or weak pump starves the engine.', 'mk-auto-elite' ) ),
							$this->cause( 'Engine & Components', __( 'Air and fuel delivery parts affect power.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Excess exhaust smoke', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Engine & Components', __( 'Blue, white or black smoke each point to different engine issues.', 'mk-auto-elite' ) ),
							$this->cause( 'Exhaust & Emissions', __( 'Emissions parts can also cause smoke and warning lights.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'ac',
				'label'   => __( 'Air conditioning', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'AC not blowing cold', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Air Conditioning', __( 'Low refrigerant or a failing compressor/condenser stops the cold air.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'AC noisy when switched on', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Air Conditioning', __( 'A worn AC compressor rattles or squeals when it engages.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
			array(
				'id'      => 'leaks',
				'label'   => __( 'Leaks and fluids', 'mk-auto-elite' ),
				'options' => array(
					array(
						'label'  => __( 'Oil spots under the car', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Engine & Components', __( 'Worn gaskets and seals are the usual source of oil leaks.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Coolant leak', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Cooling', __( 'Radiator, hoses or the water pump are common coolant leak points.', 'mk-auto-elite' ) ),
						),
					),
					array(
						'label'  => __( 'Power steering fluid leak', 'mk-auto-elite' ),
						'causes' => array(
							$this->cause( 'Steering', __( 'The pump, rack or hoses can leak power-steering fluid.', 'mk-auto-elite' ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * Expose front-end config to the fitment-tools script. Only runs when that
	 * script is actually enqueued on the page (see Assets::enqueue()).
	 */
	public function localize(): void {
		if ( ! wp_script_is( 'mkae-fitment', 'enqueued' ) ) {
			return;
		}
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		wp_localize_script(
			'mkae-fitment',
			'MKAEF',
			array(
				'shopUrl'  => $shop,
				'whatsapp' => preg_replace( '/\D+/', '', (string) \MKAutoElite\Customizer::get( 'whatsapp', '254700000000' ) ),
				'order'    => array_keys( $this->steps ),
				'symptoms' => $this->symptom_tree(),
				'i18n'     => array(
					'selectMake'  => __( 'Select make', 'mk-auto-elite' ),
					'loading'     => __( 'Loading...', 'mk-auto-elite' ),
					'showParts'   => __( 'Show parts', 'mk-auto-elite' ),
					'searching'   => __( 'Searching...', 'mk-auto-elite' ),
					'noParts'     => __( 'No parts are listed for this vehicle yet. Message us on WhatsApp and we will source it for you.', 'mk-auto-elite' ),
					'partsFound'  => __( 'parts found for your', 'mk-auto-elite' ),
					'browseAll'   => __( 'Browse all parts for this car', 'mk-auto-elite' ),
					'relaxedYear' => __( 'Showing all years - no year-specific parts are listed for this selection.', 'mk-auto-elite' ),
					'checkFit'    => __( 'Check fit', 'mk-auto-elite' ),
					'checking'    => __( 'Checking...', 'mk-auto-elite' ),
					'back'        => __( 'Back', 'mk-auto-elite' ),
					'startOver'   => __( 'Start over', 'mk-auto-elite' ),
					'likely'      => __( 'Likely parts to check', 'mk-auto-elite' ),
					'askExpert'   => __( 'Not sure? Ask our team on WhatsApp', 'mk-auto-elite' ),
					'shopFor'     => __( 'Shop', 'mk-auto-elite' ),
				),
			)
		);
	}
}
