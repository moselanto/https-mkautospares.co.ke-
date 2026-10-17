<?php
/**
 * One-click admin tools: fitment sync (meta -> taxonomies), business-page
 * creation, Knowledge Centre article import, and menu building.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers triggered from the setup wizard. All are capability + nonce guarded.
 */
class Tools {

	/**
	 * Business pages to create: slug => [title, content file].
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private array $pages = array(
		'about-us'          => array( 'About Us', 'about.html' ),
		'contact'           => array( 'Contact Us', 'contact.html' ),
		'return-refund'     => array( 'Return & Refund Policy', 'returns-refund.html' ),
		'shipping-delivery' => array( 'Shipping & Delivery Policy', 'shipping-delivery.html' ),
		'terms'             => array( 'Terms & Conditions', 'terms.html' ),
		'privacy'           => array( 'Privacy Policy', 'privacy.html' ),
		'payment-methods'   => array( 'Payment Methods', 'payment-methods.html' ),
		'track-order'       => array( 'Track Your Order', 'track-order.html' ),
	);

	/**
	 * Knowledge Centre articles to create: slug => [title, file, topic, image].
	 * Files live in /inc/content/knowledge/, images in /assets/images/knowledge/.
	 *
	 * @var array<string,array{0:string,1:string,2:string,3:string}>
	 */
	private array $articles = array(
		'genuine-vs-aftermarket-vs-ex-japan'     => array( 'Genuine vs Aftermarket vs Ex-Japan Car Parts in Kenya', 'genuine-vs-aftermarket-vs-ex-japan.html', 'Buying Guides', 'parts-counter.jpg' ),
		'identify-genuine-toyota-parts'          => array( 'How to Identify Genuine Toyota Parts (and Spot Fakes)', 'identify-genuine-toyota-parts.html', 'Buying Guides', 'inspect-genuine.jpg' ),
		'find-car-parts-by-chassis-number'       => array( 'How to Find the Right Car Part Using Your Chassis Number', 'find-car-parts-by-chassis-number.html', 'Buying Guides', 'chassis.jpg' ),
		'signs-suspension-needs-replacing'       => array( '6 Signs Your Car\'s Suspension Needs Replacing', 'signs-suspension-needs-replacing.html', 'Common Problems', 'suspension.jpg' ),
		'shock-absorbers-suspension-parts-kenya' => array( 'Shock Absorbers and Suspension Parts for Kenyan Roads', 'shock-absorbers-suspension-parts-kenya.html', 'Common Problems', 'shocks.jpg' ),
		'common-toyota-problems-and-parts'       => array( 'Common Toyota Problems and the Parts That Fix Them', 'common-toyota-problems-and-parts.html', 'Common Problems', 'workshop.jpg' ),
		'common-subaru-problems-and-parts'       => array( 'Common Subaru Problems and the Parts That Fix Them', 'common-subaru-problems-and-parts.html', 'Common Problems', 'workshop.jpg' ),
		'nissan-xtrail-common-problems'          => array( 'Nissan X-Trail: Common Problems and Parts in Kenya', 'nissan-xtrail-common-problems.html', 'Common Problems', 'workshop.jpg' ),
		'timing-belt-vs-timing-chain'            => array( 'Timing Belt vs Timing Chain: What Every Owner Should Know', 'timing-belt-vs-timing-chain.html', 'Maintenance', 'timing.jpg' ),
		'engine-oil-guide-kenya'                 => array( 'Choosing the Right Engine Oil for Kenyan Driving', 'engine-oil-guide-kenya.html', 'Maintenance', 'oil.jpg' ),
		'brake-pads-replacement-cost-kenya'      => array( 'Brake Pads in Kenya: When to Replace and What Affects the Cost', 'brake-pads-replacement-cost-kenya.html', 'Maintenance', 'brakes.jpg' ),
		'car-battery-guide-kenya'                => array( 'Car Battery Guide: Choosing, Testing and Replacing', 'car-battery-guide-kenya.html', 'Maintenance', 'battery.jpg' ),
		'alternator-charging-system-guide'       => array( 'Alternator and Charging System: A Simple Guide', 'alternator-charging-system-guide.html', 'Maintenance', 'battery.jpg' ),
		'radiator-cooling-system-guide'          => array( 'Radiator and Cooling System: Preventing Overheating', 'radiator-cooling-system-guide.html', 'Maintenance', 'radiator.jpg' ),
		'car-service-maintenance-schedule-kenya' => array( 'Car Service and Maintenance Schedule for Kenyan Drivers', 'car-service-maintenance-schedule-kenya.html', 'Maintenance', 'workshop.jpg' ),
		'hybrid-battery-price-kenya'            => array( 'Hybrid Battery Price in Kenya: Fielder, Axio, Aqua, Fit and Prius (2026)', 'hybrid-battery-price-kenya.html', 'Prices & Costs', 'battery.jpg' ),
		'car-spare-parts-prices-kenya'          => array( 'Car Spare Parts Prices in Kenya: A 2026 Buyer\'s Guide', 'car-spare-parts-prices-kenya.html', 'Prices & Costs', 'parts-counter.jpg' ),
		'ex-japan-engine-price-kenya'           => array( 'Ex-Japan Engines and Gearboxes in Kenya: Prices and How to Buy Safely', 'ex-japan-engine-price-kenya.html', 'Prices & Costs', 'timing.jpg' ),
		'kirinyaga-road-spare-parts-guide'      => array( 'Buying Spare Parts on Kirinyaga Road, Nairobi: A Smart Buyer\'s Guide', 'kirinyaga-road-spare-parts-guide.html', 'Buying Guides', 'parts-counter.jpg' ),
		'order-car-parts-online-kenya'          => array( 'How to Order Car Parts Online in Kenya: M-Pesa, WhatsApp and Delivery', 'order-car-parts-online-kenya.html', 'Buying Guides', 'parts-counter.jpg' ),
		'toyota-fielder-axio-common-problems'   => array( 'Toyota Fielder and Axio: Common Problems and the Parts That Fix Them', 'toyota-fielder-axio-common-problems.html', 'Model Guides', 'workshop.jpg' ),
		'mazda-demio-common-problems'           => array( 'Mazda Demio Common Problems in Kenya (and How to Fix Them)', 'mazda-demio-common-problems.html', 'Model Guides', 'workshop.jpg' ),
		'honda-fit-common-problems'             => array( 'Honda Fit and Fit Hybrid: Common Problems in Kenya', 'honda-fit-common-problems.html', 'Model Guides', 'workshop.jpg' ),
		'toyota-probox-succeed-parts-guide'     => array( 'Toyota Probox and Succeed Parts Guide for Owners and Fleets', 'toyota-probox-succeed-parts-guide.html', 'Model Guides', 'suspension.jpg' ),
		'check-engine-light-meaning'            => array( 'Check Engine Light On? Causes, Codes and What to Do', 'check-engine-light-meaning.html', 'Common Problems', 'workshop.jpg' ),
		'car-overheating-causes-kenya'          => array( 'Why Is My Car Overheating? Causes and Fixes for Kenyan Drivers', 'car-overheating-causes-kenya.html', 'Common Problems', 'radiator.jpg' ),
		'cvt-gearbox-problems-kenya'            => array( 'CVT Gearbox Problems: Signs, Causes and Care', 'cvt-gearbox-problems-kenya.html', 'Common Problems', 'workshop.jpg' ),
		'car-ac-not-cooling'                    => array( 'Car AC Not Cooling? The 7 Most Common Causes', 'car-ac-not-cooling.html', 'Common Problems', 'workshop.jpg' ),
		'ntsa-inspection-checklist-kenya'       => array( 'NTSA Motor Vehicle Inspection Checklist: How to Pass First Time', 'ntsa-inspection-checklist-kenya.html', 'Maintenance', 'brakes.jpg' ),
		'spark-plugs-ignition-coils-guide'      => array( 'Spark Plugs and Ignition Coils: When to Replace Them', 'spark-plugs-ignition-coils-guide.html', 'Maintenance', 'timing.jpg' ),
		'fuel-consumption-tips-kenya'           => array( 'High Fuel Consumption? Causes and Ways to Save Fuel in Kenya', 'fuel-consumption-tips-kenya.html', 'Maintenance', 'oil.jpg' ),
	);

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'admin_post_mkae_sync_fitment', array( $this, 'handle_sync_fitment' ) );
		add_action( 'admin_post_mkae_create_pages', array( $this, 'handle_create_pages' ) );
		add_action( 'admin_post_mkae_create_articles', array( $this, 'handle_create_articles' ) );
		add_action( 'admin_post_mkae_build_menus', array( $this, 'handle_build_menus' ) );
		add_action( 'admin_notices', array( $this, 'result_notice' ) );
	}

	/**
	 * Convert _mk_* product meta into the mk_* fitment taxonomies so the
	 * vehicle finder dropdowns populate. Idempotent.
	 */
	public function handle_sync_fitment(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mk-auto-elite' ) );
		}
		check_admin_referer( 'mkae_sync_fitment' );

		$map = array(
			'_mk_make'      => 'mk_make',
			'_mk_model'     => 'mk_model',
			'_mk_engine'    => 'mk_engine',
			'_mk_year_from' => 'mk_year',
		);

		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$updated    = 0;
		$touched_tax = array();
		foreach ( $query->posts as $pid ) {
			$did = false;
			foreach ( $map as $meta_key => $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$value = trim( (string) get_post_meta( $pid, $meta_key, true ) );
				if ( '' === $value ) {
					continue;
				}
				wp_set_object_terms( $pid, $value, $taxonomy, false );
				$touched_tax[ $taxonomy ] = true;
				$did = true;
			}
			$yto = trim( (string) get_post_meta( $pid, '_mk_year_to', true ) );
			if ( '' !== $yto && taxonomy_exists( 'mk_year' ) ) {
				wp_set_object_terms( $pid, $yto, 'mk_year', true );
			}
			if ( $did ) {
				$updated++;
			}
		}
		wp_reset_postdata();

		// Force correct term counts so hide_empty queries show them.
		foreach ( array_keys( $touched_tax ) as $taxonomy ) {
			$term_ids = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
			if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
				wp_update_term_count_now( $term_ids, $taxonomy );
			}
		}

		$this->redirect_back( 'fitment', $updated );
	}

	/**
	 * Create (or update) the core business pages from bundled HTML content.
	 */
	public function handle_create_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mk-auto-elite' ) );
		}
		check_admin_referer( 'mkae_create_pages' );

		$created = 0;
		foreach ( $this->pages as $slug => $meta ) {
			$file = get_template_directory() . '/inc/content/' . $meta[1];
			$body = is_readable( $file ) ? file_get_contents( $file ) : '';
			if ( '' === $body ) {
				continue;
			}
			$existing = get_page_by_path( $slug );
			$postarr  = array(
				'post_title'   => $meta[0],
				'post_name'    => $slug,
				'post_content' => $body,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			);
			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				wp_update_post( $postarr );
			} else {
				wp_insert_post( $postarr );
			}
			$created++;
		}

		// Fitment tool pages use shortcode content rather than bundled HTML.
		$tool_pages = array(
			'find-parts'     => array( __( 'Find Parts for Your Car', 'mk-auto-elite' ), '[mk_parts_finder]' ),
			'which-part'     => array( __( 'Which Part Do I Need?', 'mk-auto-elite' ), '[mk_symptom_wizard]' ),
			'book-a-service' => array( __( 'Book a Service', 'mk-auto-elite' ), '[mk_booking_form]' ),
		);
		foreach ( $tool_pages as $slug => $meta ) {
			$existing = get_page_by_path( $slug );
			$postarr  = array(
				'post_title'   => $meta[0],
				'post_name'    => $slug,
				'post_content' => $meta[1],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			);
			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				wp_update_post( $postarr );
			} else {
				wp_insert_post( $postarr );
			}
			++$created;
		}

		// Ensure the site uses a static Home page (front-page.php renders the
		// designed homepage) instead of the blog "latest posts" listing.
		$home    = get_page_by_path( 'home' );
		$home_id = $home ? (int) $home->ID : 0;
		if ( ! $home ) {
			$new_home = wp_insert_post(
				array(
					'post_title'   => __( 'Home', 'mk-auto-elite' ),
					'post_name'    => 'home',
					'post_content' => '',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
			if ( $new_home && ! is_wp_error( $new_home ) ) {
				$home_id = (int) $new_home;
				++$created;
			}
		}
		if ( $home_id > 0 ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
		}

		$this->redirect_back( 'pages', $created );
	}

	/**
	 * Create (or update) the Knowledge Centre articles from bundled HTML,
	 * assign each to its topic term, and attach a bundled featured image.
	 * Idempotent: existing articles are updated and images are only imported
	 * once (skipped when a featured image is already set).
	 */
	public function handle_create_articles(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mk-auto-elite' ) );
		}
		check_admin_referer( 'mkae_create_articles' );

		// Make sure the topic terms exist up front so the /knowledge/topic/ URLs
		// resolve as soon as the rewrite rules are flushed below.
		if ( taxonomy_exists( 'mkae_guide_topic' ) ) {
			foreach ( array( 'Buying Guides', 'Common Problems', 'Maintenance', 'Model Guides', 'Prices & Costs' ) as $mkae_topic ) {
				if ( ! term_exists( $mkae_topic, 'mkae_guide_topic' ) ) {
					wp_insert_term( $mkae_topic, 'mkae_guide_topic' );
				}
			}
		}

		$created = 0;
		foreach ( $this->articles as $slug => $meta ) {
			$file = get_template_directory() . '/inc/content/knowledge/' . $meta[1];
			$body = is_readable( $file ) ? file_get_contents( $file ) : '';
			if ( '' === $body ) {
				continue;
			}
			$excerpt  = wp_trim_words( wp_strip_all_tags( $body ), 40, '' );
			$existing = get_page_by_path( $slug, OBJECT, 'mkae_guide' );
			$postarr  = array(
				'post_title'   => $meta[0],
				'post_name'    => $slug,
				'post_content' => $body,
				'post_excerpt' => $excerpt,
				'post_status'  => 'publish',
				'post_type'    => 'mkae_guide',
			);
			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$post_id       = wp_update_post( $postarr );
			} else {
				$post_id = wp_insert_post( $postarr );
			}
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			if ( ! empty( $meta[2] ) && taxonomy_exists( 'mkae_guide_topic' ) ) {
				wp_set_object_terms( (int) $post_id, $meta[2], 'mkae_guide_topic', false );
			}
			if ( ! empty( $meta[3] ) ) {
				$this->sideload_theme_image( $meta[3], (int) $post_id );
			}
			$created++;
		}
		// Flush rewrite rules so the new /knowledge/ and topic URLs resolve
		// immediately, without needing a manual Settings > Permalinks save.
		flush_rewrite_rules();
		$this->redirect_back( 'articles', $created );
	}

	/**
	 * Import a bundled theme image into the media library and set it as the
	 * featured image for a post. No-op if the post already has a thumbnail.
	 */
	private function sideload_theme_image( string $filename, int $post_id ): bool {
		if ( has_post_thumbnail( $post_id ) ) {
			return false;
		}
		$path = get_template_directory() . '/assets/images/knowledge/' . $filename;
		if ( ! is_readable( $path ) ) {
			return false;
		}
		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			return false;
		}
		$upload = wp_upload_bits( 'mkae-' . $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return false;
		}
		$filetype   = wp_check_filetype( $upload['file'], null );
		$mime       = ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/jpeg';
		$attachment = array(
			'post_mime_type' => $mime,
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_meta );
		set_post_thumbnail( $post_id, $attach_id );
		return true;
	}

	/**
	 * Build and assign a Primary navigation menu (Home, Shop, About, Contact).
	 * The "Shop by Category" mega menu is generated automatically and needs no
	 * manual configuration.
	 */
	public function handle_build_menus(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mk-auto-elite' ) );
		}
		check_admin_referer( 'mkae_build_menus' );

		$menu_name = 'MK Primary Menu';
		$menu      = wp_get_nav_menu_object( $menu_name );
		$menu_id   = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $menu_name );
		if ( is_wp_error( $menu_id ) || ! $menu_id ) {
			$this->redirect_back( 'menus', 0 );
		}

		// Rebuild items cleanly.
		$existing = wp_get_nav_menu_items( $menu_id );
		if ( is_array( $existing ) ) {
			foreach ( $existing as $item ) {
				wp_delete_post( $item->ID, true );
			}
		}

		$count = 0;
		$add   = function ( $title, $url, $parent = 0 ) use ( $menu_id, &$count ) {
			$item_id = wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $title,
					'menu-item-url'       => $url,
					'menu-item-status'    => 'publish',
					'menu-item-type'      => 'custom',
					'menu-item-parent-id' => $parent,
				)
			);
			if ( is_wp_error( $item_id ) || ! $item_id ) {
				return 0;
			}
			$count++;
			return (int) $item_id;
		};

		$add( __( 'Home', 'mk-auto-elite' ), home_url( '/' ) );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$add( __( 'Shop', 'mk-auto-elite' ), wc_get_page_permalink( 'shop' ) );
		}
		$book = get_page_by_path( 'book-a-service' );
		if ( $book ) {
			$add( __( 'Book a Service', 'mk-auto-elite' ), get_permalink( $book ) );
		}
		// Add the Knowledge Centre link, but only once at least one article is
		// published so the menu never points at an empty archive. Uses home_url()
		// so it resolves correctly on both the live site and the /test/ staging.
		// Each guide topic that has published articles becomes a dropdown child.
		if ( post_type_exists( 'mkae_guide' ) ) {
			$guide_counts = wp_count_posts( 'mkae_guide' );
			if ( $guide_counts && (int) $guide_counts->publish > 0 ) {
				$kc_parent = $add( __( 'Knowledge Centre', 'mk-auto-elite' ), home_url( '/knowledge/' ) );
				if ( $kc_parent && taxonomy_exists( 'mkae_guide_topic' ) ) {
					$topics = get_terms(
						array(
							'taxonomy'   => 'mkae_guide_topic',
							'hide_empty' => true,
							'orderby'    => 'name',
						)
					);
					if ( ! is_wp_error( $topics ) ) {
						foreach ( $topics as $topic ) {
							$topic_link = get_term_link( $topic );
							if ( is_wp_error( $topic_link ) ) {
								continue;
							}
							$add( $topic->name, $topic_link, $kc_parent );
						}
					}
				}
			}
		}
		$about = get_page_by_path( 'about-us' );
		if ( $about ) {
			$add( __( 'About Us', 'mk-auto-elite' ), get_permalink( $about ) );
		}
		$contact = get_page_by_path( 'contact' );
		if ( $contact ) {
			$add( __( 'Contact', 'mk-auto-elite' ), get_permalink( $contact ) );
		}

		// Assign to the primary location.
		$locations            = (array) get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		$this->redirect_back( 'menus', $count );
	}

	/**
	 * Redirect back to the tools page with a result flag.
	 */
	private function redirect_back( string $what, int $count ): void {
		$url = add_query_arg(
			array(
				'page'       => 'mk-auto-elite',
				'mkae_done'  => $what,
				'mkae_count' => $count,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Show a success notice after a tool runs.
	 */
	public function result_notice(): void {
		if ( empty( $_GET['mkae_done'] ) ) {
			return;
		}
		$what  = sanitize_key( wp_unslash( $_GET['mkae_done'] ) );
		$count = isset( $_GET['mkae_count'] ) ? absint( $_GET['mkae_count'] ) : 0;
		if ( 'pages' === $what ) {
			$msg = sprintf( /* translators: %d: pages. */ __( '%d business pages created/updated.', 'mk-auto-elite' ), $count );
		} elseif ( 'articles' === $what ) {
			$msg = sprintf( /* translators: %d: articles. */ __( '%d Knowledge Centre articles created/updated with photos. Visit Settings > Permalinks and click Save once so the /knowledge/ URLs work.', 'mk-auto-elite' ), $count );
		} elseif ( 'menus' === $what ) {
			$msg = sprintf( /* translators: %d: menu items. */ __( 'Primary menu built and assigned (%d items).', 'mk-auto-elite' ), $count );
		} else {
			$msg = sprintf( /* translators: %d: products. */ __( 'Fitment synced for %d products. The vehicle finder is now populated.', 'mk-auto-elite' ), $count );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
}
