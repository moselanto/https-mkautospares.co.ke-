<?php
/**
 * Programmatic fitment landing pages (model x part), e.g.
 * /fitment/toyota-prado-brake-pads/.
 *
 * These are REAL, editable posts (not virtual/auto-rendered pages) so each can
 * carry unique content — failure symptoms, OEM/part numbers, price guidance and
 * an FAQ — which is what Google's helpful-content system rewards. A one-click
 * generator scaffolds the matrix as DRAFTS so nothing goes live until a human
 * fills it in and publishes, in waves.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the fitment landing post type, its editor meta box, the draft
 * generator, and a product-matching helper used by the single template.
 */
class Landing {

	private const META_MAKE     = '_mkae_l_make';
	private const META_MODEL    = '_mkae_l_model';
	private const META_PART     = '_mkae_l_part';
	private const META_SYMPTOMS = '_mkae_l_symptoms';
	private const META_OEM      = '_mkae_l_oem';
	private const META_PRICE    = '_mkae_l_price';
	private const META_FAQ      = '_mkae_l_faq';

	private const GEN_LIMIT = 120;

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'after_switch_theme', array( $this, 'flush' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'save_post_mkae_landing', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'generator_menu' ) );
		add_action( 'admin_post_mkae_generate_landings', array( $this, 'handle_generate' ) );
	}

	/**
	 * Register the fitment landing post type.
	 */
	public function register(): void {
		register_post_type(
			'mkae_landing',
			array(
				'labels'       => array(
					'name'          => __( 'Fitment Pages', 'mk-auto-elite' ),
					'singular_name' => __( 'Fitment Page', 'mk-auto-elite' ),
					'menu_name'     => __( 'Fitment Pages', 'mk-auto-elite' ),
				),
				'public'       => true,
				'has_archive'  => 'fitment',
				'menu_icon'    => 'dashicons-search',
				'rewrite'      => array(
					'slug'       => 'fitment',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Flush rewrite rules on activation.
	 */
	public function flush(): void {
		$this->register();
		flush_rewrite_rules();
	}

	/**
	 * Typed getter for a landing field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Short key (make|model|part|oem|price|symptoms|faq).
	 * @param string $fallback Default when empty.
	 * @return string
	 */
	public static function get( int $post_id, string $key, string $fallback = '' ): string {
		$map = array(
			'make'     => self::META_MAKE,
			'model'    => self::META_MODEL,
			'part'     => self::META_PART,
			'oem'      => self::META_OEM,
			'price'    => self::META_PRICE,
			'symptoms' => self::META_SYMPTOMS,
			'faq'      => self::META_FAQ,
		);
		if ( ! isset( $map[ $key ] ) ) {
			return $fallback;
		}
		$val = get_post_meta( $post_id, $map[ $key ], true );
		return ( '' === $val || null === $val ) ? $fallback : (string) $val;
	}

	/**
	 * Add the fitment details meta box.
	 */
	public function meta_box(): void {
		add_meta_box( 'mkae_landing_fields', __( 'Fitment details', 'mk-auto-elite' ), array( $this, 'render_meta_box' ), 'mkae_landing', 'normal', 'high' );
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( $post ): void {
		wp_nonce_field( 'mkae_landing_meta', 'mkae_landing_nonce' );
		$fields = array(
			self::META_MAKE     => array( __( 'Make', 'mk-auto-elite' ), 'text' ),
			self::META_MODEL    => array( __( 'Model', 'mk-auto-elite' ), 'text' ),
			self::META_PART     => array( __( 'Part label', 'mk-auto-elite' ), 'text' ),
			self::META_OEM      => array( __( 'OEM / part numbers', 'mk-auto-elite' ), 'text' ),
			self::META_PRICE    => array( __( 'Price from (e.g. KES 3,500)', 'mk-auto-elite' ), 'text' ),
			self::META_SYMPTOMS => array( __( 'Symptoms of failure (one per line)', 'mk-auto-elite' ), 'textarea' ),
			self::META_FAQ      => array( __( 'FAQ (one per line, format: Question | Answer)', 'mk-auto-elite' ), 'textarea' ),
		);
		echo '<div class="mkae-landing-meta">';
		foreach ( $fields as $key => $conf ) {
			$val = get_post_meta( $post->ID, $key, true );
			printf( '<p><label style="display:block;font-weight:600;margin-bottom:4px;">%s</label>', esc_html( $conf[0] ) );
			if ( 'textarea' === $conf[1] ) {
				printf( '<textarea name="%s" rows="4" style="width:100%%;">%s</textarea>', esc_attr( $key ), esc_textarea( (string) $val ) );
			} else {
				printf( '<input type="text" name="%s" value="%s" style="width:100%%;" />', esc_attr( $key ), esc_attr( (string) $val ) );
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * Save meta box values.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_meta( $post_id, $post ): void {
		if ( ! isset( $_POST['mkae_landing_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mkae_landing_nonce'] ) ), 'mkae_landing_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$text_keys = array( self::META_MAKE, self::META_MODEL, self::META_PART, self::META_OEM, self::META_PRICE );
		foreach ( $text_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		$area_keys = array( self::META_SYMPTOMS, self::META_FAQ );
		foreach ( $area_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}

	/**
	 * Add the generator page under the Fitment Pages menu.
	 */
	public function generator_menu(): void {
		add_submenu_page(
			'edit.php?post_type=mkae_landing',
			__( 'Generate Fitment Pages', 'mk-auto-elite' ),
			__( 'Generate Pages', 'mk-auto-elite' ),
			'manage_options',
			'mkae-generate-landings',
			array( $this, 'generator_page' )
		);
	}

	/**
	 * Render the generator admin page.
	 */
	public function generator_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$done = isset( $_GET['mkae_done'] ) ? (int) $_GET['mkae_done'] : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>' . esc_html__( 'Generate Fitment Pages', 'mk-auto-elite' ) . '</h1>';
		if ( $done >= 0 ) {
			/* translators: %d: number of draft pages created. */
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( sprintf( __( 'Created %d draft fitment pages. Fill in the details, then publish in waves. Click again to continue if more remain.', 'mk-auto-elite' ), $done ) ) );
		}
		echo '<p>' . esc_html__( 'This scaffolds model x part landing pages as DRAFTS. Nothing goes live until you add unique content and publish it. Re-running skips pages that already exist.', 'mk-auto-elite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mkae_generate_landings', 'mkae_gen_nonce' );
		echo '<input type="hidden" name="action" value="mkae_generate_landings" />';
		echo '<p><button class="button button-primary">' . esc_html__( 'Generate draft pages', 'mk-auto-elite' ) . '</button></p>';
		echo '</div>';
	}

	/**
	 * Handle the generator form submission.
	 */
	public function handle_generate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mk-auto-elite' ) );
		}
		if ( ! isset( $_POST['mkae_gen_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mkae_gen_nonce'] ) ), 'mkae_generate_landings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'mk-auto-elite' ) );
		}
		$created = $this->generate();
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'mkae_landing',
					'page'      => 'mkae-generate-landings',
					'mkae_done' => $created,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * The model x part matrix used to scaffold pages.
	 *
	 * @return array{models:array<string,string[]>,parts:string[]}
	 */
	private function matrix(): array {
		return array(
			'models' => array(
				'Toyota'     => array( 'Prado', 'Land Cruiser', 'Hilux', 'Axio', 'Fielder', 'Premio', 'Harrier', 'Vitz', 'Probox', 'Noah', 'Voxy', 'Mark X' ),
				'Nissan'     => array( 'X-Trail', 'Navara', 'Juke', 'Note', 'Tiida', 'Wingroad' ),
				'Mazda'      => array( 'Demio', 'Axela', 'Atenza', 'CX-5', 'Premacy' ),
				'Subaru'     => array( 'Forester', 'XV', 'Legacy', 'Impreza', 'Outback' ),
				'Honda'      => array( 'Fit', 'CR-V', 'Vezel', 'Stream' ),
				'Mitsubishi' => array( 'Outlander', 'Lancer', 'Pajero' ),
			),
			'parts'  => array( 'Brake Pads', 'Shock Absorbers', 'Radiator', 'Air Filter', 'Alternator', 'Control Arms', 'CV Joint', 'Timing Belt', 'Headlight' ),
		);
	}

	/**
	 * Create draft landing pages up to the per-run limit. Idempotent.
	 *
	 * @return int Number of pages created this run.
	 */
	private function generate(): int {
		$matrix  = $this->matrix();
		$created = 0;
		foreach ( $matrix['models'] as $make => $models ) {
			foreach ( $models as $model ) {
				foreach ( $matrix['parts'] as $part ) {
					if ( $created >= self::GEN_LIMIT ) {
						return $created;
					}
					$title = trim( $make . ' ' . $model . ' ' . $part );
					$slug  = sanitize_title( $make . '-' . $model . '-' . $part );
					if ( get_page_by_path( $slug, OBJECT, 'mkae_landing' ) ) {
						continue;
					}
					/* translators: %s: full part title. */
					$excerpt = sprintf( __( 'Buy %s in Kenya. Genuine and ex-Japan options, fitment help and fast delivery.', 'mk-auto-elite' ), $title );
					$post_id = wp_insert_post(
						array(
							'post_type'    => 'mkae_landing',
							'post_status'  => 'draft',
							'post_title'   => $title,
							'post_name'    => $slug,
							'post_excerpt' => $excerpt,
						),
						true
					);
					if ( is_wp_error( $post_id ) || ! $post_id ) {
						continue;
					}
					update_post_meta( $post_id, self::META_MAKE, $make );
					update_post_meta( $post_id, self::META_MODEL, $model );
					update_post_meta( $post_id, self::META_PART, $part );
					$created++;
				}
			}
		}
		return $created;
	}

	/**
	 * Build the matching-products HTML for a landing page. Only lists products
	 * when at least one fitment/category term matches, so a page never falls
	 * back to listing the whole catalogue.
	 *
	 * @param string $make  Make name.
	 * @param string $model Model name.
	 * @param string $part  Part label (matched against product categories).
	 * @return string
	 */
	public static function products_html( string $make, string $model, string $part ): string {
		$empty = '<p class="mkae-hub__empty">' . esc_html__( 'No live listings yet for this exact fit. Message us on WhatsApp and we will source it fast.', 'mk-auto-elite' ) . '</p>';
		if ( ! function_exists( 'woocommerce_product_loop_start' ) ) {
			return $empty;
		}

		$tax_query = array( 'relation' => 'AND' );
		$make_term = $make ? get_term_by( 'name', $make, 'mk_make' ) : false;
		if ( $make_term ) {
			$tax_query[] = array(
				'taxonomy' => 'mk_make',
				'field'    => 'term_id',
				'terms'    => $make_term->term_id,
			);
		}
		$model_term = $model ? get_term_by( 'name', $model, 'mk_model' ) : false;
		if ( $model_term ) {
			$tax_query[] = array(
				'taxonomy' => 'mk_model',
				'field'    => 'term_id',
				'terms'    => $model_term->term_id,
			);
		}
		$cat_term = $part ? get_term_by( 'name', $part, 'product_cat' ) : false;
		if ( $cat_term ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $cat_term->term_id,
			);
		}
		if ( count( $tax_query ) <= 1 ) {
			return $empty;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return $empty;
		}

		ob_start();
		woocommerce_product_loop_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
		wp_reset_postdata();
		return (string) ob_get_clean();
	}
}
