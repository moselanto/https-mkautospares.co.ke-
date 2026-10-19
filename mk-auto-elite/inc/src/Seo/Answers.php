<?php
/**
 * Answer-engine (AEO) and generative-engine (GEO) visibility layer.
 *
 * Added in 2.30.0 (Oct 2026 SEO/GEO/AEO programme, see docs/SEO-STRATEGY-2026-10.md).
 *
 * What it does, and why:
 *
 *  - Homepage FAQ. One source of truth for the questions Kenyan buyers ask
 *    (where to buy in Nairobi, chassis-number orders, genuine vs OEM vs
 *    aftermarket vs ex-Japan, delivery, payment, returns). front-page.php
 *    renders it and Seo\Schema prints the same text as FAQPage, so the
 *    markup always matches what visitors see. Every answer is built from the
 *    Customizer values and the published policy pages - no invented claims.
 *
 *  - Archive answers. Product category, vehicle make and vehicle model
 *    archives get a short, data-driven FAQ under the product grid: the live
 *    number of listed parts, the real price range of priced items, the top
 *    categories (or makes) inside the archive and how to confirm fitment.
 *    Figures come from the catalogue and are cached for 12 hours, so the
 *    pages are unique and accurate rather than thin keyword copies. Shown on
 *    page 1 only and never on filtered views.
 *
 *  - Product meta. When Rank Math has no per-product title or description,
 *    products get a purchase-intent title ("... Price in Kenya") when it fits
 *    in 60 characters and a description built from verified fields (price,
 *    stock, fitment, OEM number, condition, delivery). Anything typed into
 *    Rank Math for the product always wins.
 *
 *  - Faceted URLs. Shop filters (?mk_make, ?min_price, ?stock, ?filter_*,
 *    ?orderby ...) and on-site search results are noindex,follow so the 4,300+
 *    product catalogue does not spawn thousands of near-duplicate pages.
 *
 *  - /llms.txt. A plain-text summary of the business, catalogue, policies and
 *    key URLs for AI assistants and answer engines. It does not guarantee
 *    inclusion in any AI product; it makes accurate facts easy to retrieve.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FAQ content, archive answers, product meta, facet robots and llms.txt.
 */
class Answers {

	/** Cache lifetime for catalogue figures. */
	private const TTL = 12 * HOUR_IN_SECONDS;

	/** Query args that create filtered / sorted duplicates of an archive. */
	private const FACET_ARGS = array( 'mk_make', 'mk_model', 'min_price', 'max_price', 'stock', 'orderby', 'order', 'query_type', 'rating_filter', 'product_tag' );

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ), 0 );
		add_action( 'woocommerce_after_shop_loop', array( __CLASS__, 'render_archive_faq' ), 40 );
		add_action( 'mkae_hub_after_products', array( __CLASS__, 'render_archive_faq' ) );
		add_action( 'woocommerce_archive_description', array( __CLASS__, 'render_fallback_intro' ), 11 );

		add_filter( 'rank_math/frontend/title', array( $this, 'product_title' ), 21 );
		add_filter( 'rank_math/frontend/description', array( $this, 'product_description' ), 21 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'rank_math_facet_robots' ), 30 );
		add_filter( 'wp_robots', array( $this, 'core_facet_robots' ), 30 );

		// Bust cached figures when the catalogue changes.
		add_action( 'save_post_product', array( __CLASS__, 'flush' ) );
		add_action( 'edited_term', array( __CLASS__, 'flush' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Shared facts                                                        */
	/* ------------------------------------------------------------------ */

	/** Bump the cache namespace so every cached figure is rebuilt. */
	public static function flush(): void {
		update_option( 'mkae_answers_ns', (string) time(), false );
	}

	/**
	 * Namespaced transient key.
	 *
	 * @param string $key Key.
	 */
	private static function key( string $key ): string {
		return 'mkae_ans_' . md5( (string) get_option( 'mkae_answers_ns', '1' ) . '|' . $key );
	}

	/** Shop brand name. */
	private static function brand(): string {
		return Customizer::get( 'brand_short' );
	}

	/** Published product count (cached). */
	public static function product_count(): int {
		$key   = self::key( 'count' );
		$count = get_transient( $key );
		if ( false === $count ) {
			$count = (int) wp_count_posts( 'product' )->publish;
			set_transient( $key, $count, self::TTL );
		}
		return (int) $count;
	}

	/**
	 * Most-stocked terms of a taxonomy: name => [ url, count ].
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $limit    Max terms.
	 * @return array<string,array{0:string,1:int}>
	 */
	public static function top_terms( string $taxonomy, int $limit = 10 ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$key = self::key( 'top|' . $taxonomy . '|' . $limit );
		$out = get_transient( $key );
		if ( is_array( $out ) ) {
			return $out;
		}
		$out   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'parent'     => in_array( $taxonomy, array( 'mk_make', 'product_cat' ), true ) ? 0 : '',
				'exclude'    => 'product_cat' === $taxonomy ? array( (int) get_option( 'default_product_cat' ) ) : array(),
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$out[ $term->name ] = array( (string) $link, (int) $term->count );
				}
			}
		}
		set_transient( $key, $out, self::TTL );
		return $out;
	}

	/**
	 * Term IDs of a term and its descendants.
	 *
	 * @param \WP_Term $term Term.
	 * @return int[]
	 */
	private static function term_family( \WP_Term $term ): array {
		$ids      = array( (int) $term->term_id );
		$children = get_term_children( $term->term_id, $term->taxonomy );
		if ( ! is_wp_error( $children ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $children ) );
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Live catalogue figures for an archive term: listed parts, priced parts,
	 * lowest and highest current price.
	 *
	 * @param \WP_Term $term Term.
	 * @return array{count:int,priced:int,min:float,max:float}
	 */
	public static function term_stats( \WP_Term $term ): array {
		$key   = self::key( 'stats|' . $term->term_id );
		$stats = get_transient( $key );
		if ( is_array( $stats ) ) {
			return $stats;
		}
		global $wpdb;
		$ids = self::term_family( $term );
		$in  = implode( ',', array_map( 'intval', $ids ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is a list of cast integers.
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tt.term_id IN ($in) AND p.post_type = 'product' AND p.post_status = 'publish'"
		);
		$row   = $wpdb->get_row(
			"SELECT COUNT(DISTINCT p.ID) AS priced, MIN(CAST(pm.meta_value AS DECIMAL(14,2))) AS lo, MAX(CAST(pm.meta_value AS DECIMAL(14,2))) AS hi
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_price'
			WHERE tt.term_id IN ($in) AND p.post_type = 'product' AND p.post_status = 'publish'
			AND pm.meta_value <> '' AND CAST(pm.meta_value AS DECIMAL(14,2)) > 0"
		);
		// phpcs:enable

		$stats = array(
			'count'  => $count,
			'priced' => $row ? (int) $row->priced : 0,
			'min'    => $row ? (float) $row->lo : 0.0,
			'max'    => $row ? (float) $row->hi : 0.0,
		);
		set_transient( $key, $stats, self::TTL );
		return $stats;
	}

	/**
	 * Most common terms of another taxonomy among the products of a term
	 * (top categories inside a make, top makes inside a category).
	 *
	 * @param \WP_Term $term      Archive term.
	 * @param string   $taxonomy  Taxonomy to count.
	 * @param int      $limit     Max terms.
	 * @return array<string,string> name => url
	 */
	public static function co_terms( \WP_Term $term, string $taxonomy, int $limit = 5 ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$key = self::key( 'co|' . $term->term_id . '|' . $taxonomy . '|' . $limit );
		$out = get_transient( $key );
		if ( is_array( $out ) ) {
			return $out;
		}
		global $wpdb;
		$in      = implode( ',', array_map( 'intval', self::term_family( $term ) ) );
		$exclude = (int) get_option( 'default_product_cat' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t2.term_id, COUNT(DISTINCT tr2.object_id) AS n
				FROM {$wpdb->term_relationships} tr1
				INNER JOIN {$wpdb->term_taxonomy} tt1 ON tt1.term_taxonomy_id = tr1.term_taxonomy_id AND tt1.term_id IN ($in)
				INNER JOIN {$wpdb->term_relationships} tr2 ON tr2.object_id = tr1.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id AND tt2.taxonomy = %s
				INNER JOIN {$wpdb->terms} t2 ON t2.term_id = tt2.term_id
				INNER JOIN {$wpdb->posts} p ON p.ID = tr1.object_id AND p.post_status = 'publish' AND p.post_type = 'product'
				WHERE t2.term_id <> %d AND tt2.parent = 0
				GROUP BY t2.term_id ORDER BY n DESC LIMIT %d",
				$taxonomy,
				$exclude,
				$limit
			)
		);
		// phpcs:enable
		$out = array();
		foreach ( (array) $rows as $row ) {
			$t = get_term( (int) $row->term_id, $taxonomy );
			if ( $t instanceof \WP_Term ) {
				$link = get_term_link( $t );
				if ( ! is_wp_error( $link ) ) {
					$out[ $t->name ] = (string) $link;
				}
			}
		}
		set_transient( $key, $out, self::TTL );
		return $out;
	}

	/**
	 * Format a shilling amount.
	 *
	 * @param float $amount Amount.
	 */
	private static function ksh( float $amount ): string {
		return 'KSh ' . number_format_i18n( $amount, 0 );
	}

	/**
	 * Human list: "a, b and c".
	 *
	 * @param string[] $items Items.
	 */
	private static function and_list( array $items ): string {
		$items = array_values( array_filter( array_map( 'strval', $items ) ) );
		if ( count( $items ) < 2 ) {
			return implode( '', $items );
		}
		$last = array_pop( $items );
		/* translators: 1: comma list, 2: last item. */
		return sprintf( __( '%1$s and %2$s', 'mk-auto-elite' ), implode( ', ', $items ), $last );
	}

	/** URL of a published page by one of several slugs, or ''. */
	private static function page_url( string ...$slugs ): string {
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				return (string) get_permalink( $page );
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Homepage FAQ                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Homepage questions and answers (plain text). Rendered by front-page.php
	 * and mirrored as FAQPage by Seo\Schema.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function home_faqs(): array {
		$brand   = self::brand();
		$phone   = Customizer::get( 'phone' );
		$address = Customizer::get( 'address' );
		$hours   = Customizer::get( 'hours' );
		$mpesa   = Customizer::get( 'mpesa' );
		$days    = absint( Customizer::get( 'return_days' ) );
		$fee     = absint( Customizer::get( 'delivery_fee' ) );
		$free    = absint( Customizer::get( 'free_delivery_from' ) );
		$makes   = array_keys( self::top_terms( 'mk_make', 10 ) );
		$count   = self::product_count();

		$faqs = array();

		$faqs[] = array(
			__( 'Where can I buy car spare parts in Nairobi?', 'mk-auto-elite' ),
			/* translators: 1: brand, 2: address, 3: hours, 4: phone. */
			sprintf( __( '%1$s is on %2$s, open %3$s. You can walk in, order online on this website, or send the part you need on WhatsApp (%4$s) and collect it from the shop or have it delivered anywhere in Kenya.', 'mk-auto-elite' ), $brand, $address, $hours, $phone ),
		);

		if ( $makes && $count > 0 ) {
			$faqs[] = array(
				__( 'Which vehicle makes do you stock spare parts for?', 'mk-auto-elite' ),
				/* translators: 1: number of parts, 2: list of makes. */
				sprintf( __( 'We list %1$s parts online for vehicles common on Kenyan roads, including %2$s, plus trucks and other makes. Choose your make in the vehicle finder or the Shop by Make links to see the parts that fit.', 'mk-auto-elite' ), number_format_i18n( $count ), self::and_list( $makes ) ),
			);
		}

		$faqs[] = array(
			__( 'How do I find the right part for my car?', 'mk-auto-elite' ),
			__( 'Use the vehicle finder above: select your make, model and year, then browse compatible parts. You can also search by part name, OEM part number or SKU. Each product shows the vehicle, years and chassis codes it fits where we have them.', 'mk-auto-elite' ),
		);

		$faqs[] = array(
			__( 'Can I order a spare part using my chassis number?', 'mk-auto-elite' ),
			/* translators: %s: phone. */
			sprintf( __( 'Yes. Send your chassis (frame or VIN) number, or a photo of your logbook and the old part, on WhatsApp (%s). We confirm the correct part, price and availability before you pay.', 'mk-auto-elite' ), $phone ),
		);

		$faqs[] = array(
			__( 'What is the difference between genuine, OEM, aftermarket and ex-Japan parts?', 'mk-auto-elite' ),
			__( 'Genuine parts are supplied by the vehicle maker in its own packaging. OEM parts are made by the companies that supply the vehicle maker, sold under their own brand. Aftermarket parts are made by other manufacturers to fit and work like the original, and quality depends on the brand. Ex-Japan parts are used parts removed from vehicles in Japan. Each product page shows the part brand and, where recorded, its condition.', 'mk-auto-elite' ),
		);

		$faqs[] = array(
			__( 'Can I order on WhatsApp?', 'mk-auto-elite' ),
			__( 'Yes. Every product has a WhatsApp button that pre-fills the part details so we can confirm price and availability fast.', 'mk-auto-elite' ),
		);

		$delivery = __( 'Yes. We deliver across Kenya by courier and parcel services. Nairobi orders usually arrive in 1-2 business days, major towns in 1-3 and other areas in 2-5 business days after dispatch.', 'mk-auto-elite' );
		if ( $fee > 0 ) {
			$delivery .= ' ' . ( $free > 0
				/* translators: 1: fee, 2: free-delivery threshold. */
				? sprintf( __( 'Delivery costs %1$s per order and is free on orders of %2$s or more.', 'mk-auto-elite' ), self::ksh( $fee ), self::ksh( $free ) )
				/* translators: %s: fee. */
				: sprintf( __( 'Delivery costs %s per order.', 'mk-auto-elite' ), self::ksh( $fee ) ) );
		}
		$delivery .= ' ' . __( 'Pickup from the shop is free.', 'mk-auto-elite' );
		$faqs[]    = array( __( 'Do you deliver outside Nairobi?', 'mk-auto-elite' ), $delivery );

		$faqs[] = array(
			__( 'How can I pay?', 'mk-auto-elite' ),
			/* translators: %s: M-Pesa instruction. */
			sprintf( __( 'By M-Pesa (%s), cash at the shop, or bank transfer for larger or special-order items. Cash on delivery is available in selected areas of Nairobi, subject to confirmation.', 'mk-auto-elite' ), $mpesa ),
		);

		$faqs[] = array(
			__( 'What if the part I need is not listed online?', 'mk-auto-elite' ),
			__( 'Send the part name, your vehicle model and chassis number on WhatsApp. We check our stock and trusted suppliers and confirm the price and availability before you pay.', 'mk-auto-elite' ),
		);

		if ( $days > 0 ) {
			$faqs[] = array(
				__( 'Can I return a part?', 'mk-auto-elite' ),
				/* translators: %d: days. */
				sprintf( __( 'Yes, within %d days of receiving it if it is faulty, wrong or not as described, unused and in its original packaging. Installed electrical parts cannot be returned unless they were faulty on arrival. See our Return and Refund Policy for full terms.', 'mk-auto-elite' ), $days ),
			);
		}

		return (array) apply_filters( 'mkae_home_faqs', $faqs );
	}

	/* ------------------------------------------------------------------ */
	/* Archive answers                                                     */
	/* ------------------------------------------------------------------ */

	/** Is this an unfiltered first page of an archive? */
	private static function clean_first_page(): bool {
		if ( (int) get_query_var( 'paged' ) > 1 || is_search() ) {
			return false;
		}
		return ! self::is_faceted();
	}

	/** Does the request carry filter / sort parameters? */
	public static function is_faceted(): bool {
		foreach ( array_keys( $_GET ) as $arg ) { // phpcs:ignore WordPress.Security.NonceVerification
			$arg = (string) $arg;
			if ( in_array( $arg, self::FACET_ARGS, true ) || 0 === strpos( $arg, 'filter_' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Data-driven FAQ for the current product category / make / model
	 * archive. Empty when not applicable.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function archive_faqs(): array {
		if ( ! is_tax( array( 'product_cat', 'mk_make', 'mk_model' ) ) || ! self::clean_first_page() ) {
			return array();
		}
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return array();
		}
		$stats = self::term_stats( $term );
		if ( $stats['count'] < 1 ) {
			return array();
		}
		$brand = self::brand();
		$where = Customizer::get( 'location' );
		$phone = Customizer::get( 'phone' );
		$faqs  = array();

		if ( 'product_cat' === $term->taxonomy ) {
			$label = preg_match( '/\bparts\s*$/i', $term->name ) ? $term->name : $term->name . ' ' . __( 'parts', 'mk-auto-elite' );
			$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
			$makes = array_keys( self::co_terms( $term, 'mk_make', 6 ) );

			$faqs[] = array(
				/* translators: %s: category label. */
				sprintf( __( 'Where can I buy %s in Kenya?', 'mk-auto-elite' ), $lower ),
				/* translators: 1: brand, 2: location, 3: count, 4: label, 5: phone. */
				sprintf( __( '%1$s on %2$s lists %3$s %4$s online. Order on this website, on WhatsApp (%5$s) or collect from the shop; we deliver across Kenya.', 'mk-auto-elite' ), $brand, $where, number_format_i18n( $stats['count'] ), $lower, $phone ),
			);
			if ( $makes ) {
				$faqs[] = array(
					/* translators: %s: category label. */
					sprintf( __( 'Which vehicles do you have %s for?', 'mk-auto-elite' ), $lower ),
					/* translators: 1: list of makes. */
					sprintf( __( 'The most-listed makes in this category are %s. Use the vehicle finder or the make filter to see only the parts for your vehicle.', 'mk-auto-elite' ), self::and_list( $makes ) ),
				);
			}
			$subject = $lower;
		} else {
			$name  = $term->name;
			if ( $term->parent ) {
				$parent = get_term( $term->parent, $term->taxonomy );
				if ( $parent instanceof \WP_Term && 0 !== stripos( $name, $parent->name ) ) {
					$name = $parent->name . ' ' . $name;
				}
			}
			$cats = array_keys( self::co_terms( $term, 'product_cat', 5 ) );
			$cats = array_map( static fn( $c ) => function_exists( 'mb_strtolower' ) ? mb_strtolower( $c ) : strtolower( $c ), $cats );

			$answer = sprintf(
				/* translators: 1: brand, 2: location, 3: count, 4: vehicle. */
				__( '%1$s on %2$s lists %3$s %4$s spare parts online', 'mk-auto-elite' ),
				$brand,
				$where,
				number_format_i18n( $stats['count'] ),
				$name
			);
			if ( $cats ) {
				/* translators: %s: list of categories. */
				$answer .= sprintf( __( ', including %s', 'mk-auto-elite' ), self::and_list( $cats ) );
			}
			/* translators: %s: phone. */
			$answer .= sprintf( __( '. Order on this website, on WhatsApp (%s) or collect from the shop; we deliver across Kenya.', 'mk-auto-elite' ), $phone );

			$faqs[] = array(
				/* translators: %s: vehicle. */
				sprintf( __( 'Where can I buy %s spare parts in Kenya?', 'mk-auto-elite' ), $name ),
				$answer,
			);
			$faqs[] = array(
				/* translators: %s: vehicle. */
				sprintf( __( 'How do I confirm a %s part fits my car?', 'mk-auto-elite' ), $name ),
				__( 'Check the model, years and chassis code in the product title and the Compatibility tab, and match the OEM part number on your old part where shown. If you are unsure, send your chassis number on WhatsApp and we confirm fitment before you pay.', 'mk-auto-elite' ),
			);
			$subject = $name . ' ' . __( 'spare parts', 'mk-auto-elite' );
		}

		if ( $stats['priced'] > 0 && $stats['max'] > 0 ) {
			$range = ( $stats['min'] === $stats['max'] )
				? self::ksh( $stats['min'] )
				/* translators: 1: low price, 2: high price. */
				: sprintf( __( '%1$s to %2$s', 'mk-auto-elite' ), self::ksh( $stats['min'] ), self::ksh( $stats['max'] ) );
			$faqs[] = array(
				/* translators: %s: subject. */
				sprintf( __( 'How much do %s cost in Kenya?', 'mk-auto-elite' ), $subject ),
				/* translators: 1: count of priced items, 2: price range. */
				sprintf( __( 'Our %1$s priced listings currently range from %2$s, depending on the part, position and brand. Prices on this page are live website prices in Kenya shillings; items marked Price on request are confirmed on WhatsApp.', 'mk-auto-elite' ), number_format_i18n( $stats['priced'] ), $range ),
			);
		}

		return (array) apply_filters( 'mkae_archive_faqs', $faqs, $term );
	}

	/** Render the archive FAQ below the product grid. */
	public static function render_archive_faq(): void {
		$faqs = self::archive_faqs();
		if ( ! $faqs ) {
			return;
		}
		echo '<section class="mkae-section mkae-faq mkae-faq--archive"><h2 class="mkae-section__title">' . esc_html__( 'Questions about these parts', 'mk-auto-elite' ) . '</h2><div class="mkae-accordion">';
		foreach ( $faqs as $i => $faq ) {
			printf(
				'<details class="mkae-accordion__item"%s><summary class="mkae-accordion__q">%s</summary><div class="mkae-accordion__a">%s</div></details>',
				0 === $i ? ' open' : '',
				esc_html( $faq[0] ),
				esc_html( $faq[1] )
			);
		}
		echo '</div></section>';
	}

	/* ------------------------------------------------------------------ */
	/* Fallback archive intros (2.31.0)                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Buying guidance per part family, matched on the category name. General,
	 * factual advice only - no stock, price or fitment claims.
	 *
	 * @return array<string,string> regex => sentence
	 */
	private static function category_tips(): array {
		return array(
			'/brake/i'                 => __( 'Brake pads, discs, shoes, calipers and cylinders must match your exact model and axle (front or rear). Replace pads and discs in axle pairs and check the OEM number on the old part.', 'mk-auto-elite' ),
			'/engine/i'                => __( 'Engine parts such as gaskets, mounts, belts, pistons and timing components are specific to the engine code (for example 1NZ, 2KD, QR25 or EJ20). Quote the engine code or chassis number when you order.', 'mk-auto-elite' ),
			'/electric|sensor/i'       => __( 'Sensors, switches, coils and modules are often specific to the year and engine. Match the connector and part number, and note that installed electrical parts are only returnable if faulty on arrival.', 'mk-auto-elite' ),
			'/transmission|gearbox|clutch/i' => __( 'Gearbox, CV and clutch parts differ between manual, automatic and CVT versions and between 2WD and 4WD. Confirm the transmission type and chassis code before ordering.', 'mk-auto-elite' ),
			'/cool|radiator/i'         => __( 'Radiators, fans, water pumps, thermostats and hoses keep the engine from overheating in Kenyan traffic. Match the engine size and whether the car is manual or automatic, as radiators often differ.', 'mk-auto-elite' ),
			'/light|lamp/i'            => __( 'Head lamps, tail lights and corner lamps are side-specific (LHS or RHS) and change with facelifts. Check the side and model years in the product title.', 'mk-auto-elite' ),
			'/body|mirror|bumper/i'    => __( 'Body parts such as mirrors, bumpers, grilles and mounts are side- and year-specific. Check LHS or RHS and the model years, or send a photo of the old part on WhatsApp.', 'mk-auto-elite' ),
			'/fuel|inject|pump/i'      => __( 'Fuel pumps, filters and injectors must match the engine and fuel type (petrol or diesel). Quote the engine code or chassis number to get the right part.', 'mk-auto-elite' ),
			'/steer/i'                 => __( 'Steering racks, rack ends, tie rod ends and pumps are model-specific and safety-critical. Have them fitted by a qualified mechanic and check wheel alignment afterwards.', 'mk-auto-elite' ),
			'/suspension|shock/i'      => __( 'Shock absorbers, control arms, ball joints, links and bushes are often side- and position-specific. Use your chassis code for the safest match.', 'mk-auto-elite' ),
			'/accessor/i'              => __( 'Accessories are listed with the vehicles they suit; check the model and years in the title before ordering.', 'mk-auto-elite' ),
		);
	}

	/**
	 * Data-driven intro used only when the term has no description of its own.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function fallback_intro( \WP_Term $term ): string {
		$stats = self::term_stats( $term );
		if ( $stats['count'] < 1 ) {
			return '';
		}
		$brand = self::brand();
		$where = Customizer::get( 'location' );
		$phone = Customizer::get( 'phone' );
		$out   = array();

		if ( 'product_cat' === $term->taxonomy ) {
			$label = preg_match( '/\bparts\s*$/i', $term->name ) ? $term->name : $term->name . ' ' . __( 'parts', 'mk-auto-elite' );
			$makes = array_keys( self::co_terms( $term, 'mk_make', 6 ) );
			/* translators: 1: count, 2: label, 3: brand, 4: location. */
			$p = sprintf( __( 'Shop %1$s %2$s at %3$s, %4$s', 'mk-auto-elite' ), number_format_i18n( $stats['count'] ), mb_strtolower( $label ), $brand, $where );
			if ( $makes ) {
				/* translators: %s: makes. */
				$p .= sprintf( __( ', for vehicles including %s', 'mk-auto-elite' ), self::and_list( $makes ) );
			}
			$out[] = $p . '. ' . __( 'Each listing shows the vehicle it fits and, where available, the OEM part number.', 'mk-auto-elite' );
			foreach ( self::category_tips() as $re => $tip ) {
				if ( preg_match( $re, $term->name ) ) {
					$out[] = $tip;
					break;
				}
			}
		} else {
			$name = $term->name;
			if ( $term->parent ) {
				$parent = get_term( $term->parent, $term->taxonomy );
				if ( $parent instanceof \WP_Term && 0 !== stripos( $name, $parent->name ) ) {
					$name = $parent->name . ' ' . $name;
				}
			}
			$cats = array_map( 'mb_strtolower', array_keys( self::co_terms( $term, 'product_cat', 5 ) ) );
			/* translators: 1: count, 2: vehicle, 3: brand, 4: location. */
			$p = sprintf( __( 'Find %1$s %2$s spare parts at %3$s, %4$s', 'mk-auto-elite' ), number_format_i18n( $stats['count'] ), $name, $brand, $where );
			if ( $cats ) {
				/* translators: %s: categories. */
				$p .= sprintf( __( ', including %s', 'mk-auto-elite' ), self::and_list( $cats ) );
			}
			$out[] = $p . '.';
			/* translators: %s: vehicle. */
			$out[] = sprintf( __( 'Parts often change between generations of the same %s model, so the chassis code on your logbook is the safest way to match the right part.', 'mk-auto-elite' ), $name );
		}
		/* translators: %s: phone. */
		$out[] = sprintf( __( 'Not sure it fits? Send your chassis number or a photo of the old part on WhatsApp (%s) and we confirm before you pay. Pay by M-Pesa, collect from the shop or get delivery across Kenya.', 'mk-auto-elite' ), $phone );

		return '<p>' . implode( '</p><p>', array_map( 'esc_html', $out ) ) . '</p>';
	}

	/** WooCommerce category archives: print the fallback intro when the term has none. */
	public static function render_fallback_intro(): void {
		if ( ! is_tax( 'product_cat' ) || (int) get_query_var( 'paged' ) > 1 ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term || '' !== trim( wp_strip_all_tags( (string) $term->description ) ) ) {
			return;
		}
		$html = self::fallback_intro( $term );
		if ( '' !== $html ) {
			echo '<div class="term-description mkae-term-intro">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in fallback_intro().
		}
	}

	/* ------------------------------------------------------------------ */
	/* Product meta fallbacks                                              */
	/* ------------------------------------------------------------------ */

	/** Current product, or null. */
	private static function product(): ?\WC_Product {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return null;
		}
		$product = wc_get_product( get_queried_object_id() );
		return $product instanceof \WC_Product ? $product : null;
	}

	/** Has an editor typed this Rank Math field for the product? */
	private static function overridden( int $id, string $key ): bool {
		return '' !== trim( (string) get_post_meta( $id, $key, true ) );
	}

	/**
	 * "<name> Price in Kenya | MK Auto Spares" when it fits in 60 chars;
	 * "<name> | MK Auto Spares" when that fits; otherwise Rank Math's own.
	 *
	 * @param mixed $title Title.
	 * @return mixed
	 */
	public function product_title( $title ) {
		$product = self::product();
		if ( ! $product || self::overridden( $product->get_id(), 'rank_math_title' ) ) {
			return $title;
		}
		$name  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $product->get_name() ) ) );
		$brand = self::brand();
		$long  = $name . ' ' . __( 'Price in Kenya', 'mk-auto-elite' ) . ' | ' . $brand;
		if ( mb_strlen( $long ) <= 60 ) {
			return $long;
		}
		$short = $name . ' | ' . $brand;
		return mb_strlen( $short ) <= 65 ? $short : $title;
	}

	/**
	 * Description from verified product fields when Rank Math has none set.
	 *
	 * @param mixed $description Description.
	 * @return mixed
	 */
	public function product_description( $description ) {
		$product = self::product();
		if ( ! $product || self::overridden( $product->get_id(), 'rank_math_description' ) ) {
			return $description;
		}
		$id    = $product->get_id();
		$name  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $product->get_name() ) ) );
		$bits  = array();
		$price = $product->get_price();
		if ( '' !== (string) $price && (float) $price > 0 ) {
			$stock  = $product->is_in_stock() ? __( 'in stock', 'mk-auto-elite' ) : __( 'currently out of stock', 'mk-auto-elite' );
			/* translators: 1: name, 2: price, 3: stock. */
			$bits[] = sprintf( __( '%1$s: %2$s, %3$s.', 'mk-auto-elite' ), $name, self::ksh( (float) $price ), $stock );
		} else {
			/* translators: %s: name. */
			$bits[] = sprintf( __( '%s: price on request.', 'mk-auto-elite' ), $name );
		}
		$oem = trim( (string) get_post_meta( $id, '_mk_oem', true ) );
		if ( '' !== $oem ) {
			/* translators: %s: OEM number. */
			$bits[] = sprintf( __( 'OEM %s.', 'mk-auto-elite' ), $oem );
		}
		/* translators: %s: location. */
		$bits[] = sprintf( __( 'Fitment confirmed on WhatsApp. Pay by M-Pesa, collect on %s or get delivery across Kenya.', 'mk-auto-elite' ), Customizer::get( 'location' ) );

		$text = implode( ' ', $bits );
		if ( mb_strlen( $text ) > 160 ) {
			$cut  = mb_substr( $text, 0, 157 );
			$text = rtrim( mb_substr( $cut, 0, (int) mb_strrpos( $cut, ' ' ) ), ' ,.;:' ) . '...';
		}
		return $text;
	}

	/* ------------------------------------------------------------------ */
	/* Robots for faceted URLs and search                                  */
	/* ------------------------------------------------------------------ */

	/** Should this request be noindex,follow? */
	private static function thin_variant(): bool {
		if ( is_search() ) {
			return true;
		}
		$archive = is_tax() || is_category() || is_tag() || is_post_type_archive() || ( function_exists( 'is_shop' ) && is_shop() );
		return $archive && self::is_faceted();
	}

	/**
	 * Rank Math robots.
	 *
	 * @param mixed $robots Robots.
	 * @return mixed
	 */
	public function rank_math_facet_robots( $robots ) {
		if ( is_array( $robots ) && self::thin_variant() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/**
	 * Core robots (no Rank Math).
	 *
	 * @param array<string,mixed> $robots Robots.
	 * @return array<string,mixed>
	 */
	public function core_facet_robots( $robots ) {
		if ( ! defined( 'RANK_MATH_VERSION' ) && self::thin_variant() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/* ------------------------------------------------------------------ */
	/* llms.txt                                                            */
	/* ------------------------------------------------------------------ */

	/** Serve /llms.txt (text/plain). */
	public function serve_llms_txt(): void {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = $home ? rtrim( (string) $home, '/' ) : '';
		if ( $home . '/llms.txt' !== $path || ! get_option( 'blog_public' ) ) {
			return;
		}
		$key  = self::key( 'llms' );
		$body = get_transient( $key );
		if ( ! is_string( $body ) || '' === $body ) {
			$body = $this->llms_body();
			set_transient( $key, $body, self::TTL );
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=3600' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- plain text built from sanitised site data.
		exit;
	}

	/** Build the llms.txt body. */
	private function llms_body(): string {
		$brand = self::brand();
		$l     = array();
		$clean = static fn( $s ) => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES ) ) ) );

		$l[] = '# ' . $brand;
		$l[] = '';
		/* translators: 1: brand, 2: location, 3: count. */
		$l[] = '> ' . sprintf( __( '%1$s is a car, truck and motorcycle spare-parts shop on %2$s, Kenya, selling %3$s listed parts online with fitment checks on WhatsApp, M-Pesa payment and delivery across Kenya.', 'mk-auto-elite' ), $brand, Customizer::get( 'location' ), number_format_i18n( self::product_count() ) );
		$l[] = '';
		$l[] = '## Business facts';
		$l[] = '- Website: ' . home_url( '/' );
		$l[] = '- Address: ' . $clean( Customizer::get( 'address' ) );
		$l[] = '- Opening hours: ' . $clean( Customizer::get( 'hours' ) );
		$l[] = '- Phone and WhatsApp: ' . $clean( Customizer::get( 'phone' ) );
		$l[] = '- Email: ' . $clean( Customizer::get( 'email' ) );
		$l[] = '- Payment: M-Pesa (' . $clean( Customizer::get( 'mpesa' ) ) . '), cash at the shop, bank transfer for larger orders';
		$l[] = '- Delivery: countrywide across Kenya by courier and parcel services; free pickup from the shop';
		$days = absint( Customizer::get( 'return_days' ) );
		if ( $days > 0 ) {
			/* translators: %d: days. */
			$l[] = '- ' . sprintf( __( 'Returns: %d days for faulty, wrong or not-as-described parts (unused, original packaging)', 'mk-auto-elite' ), $days );
		}
		$l[] = '- Part types sold: genuine, OEM, aftermarket and ex-Japan (used) parts; condition is shown on each product where recorded';
		$l[] = '- Fitment help: customers can send a chassis number or a photo of the old part on WhatsApp to confirm the right part before paying';
		$l[] = '';

		$cats = self::top_terms( 'product_cat', 20 );
		if ( $cats ) {
			$l[] = '## Part categories';
			foreach ( $cats as $name => $row ) {
				$l[] = '- [' . $clean( $name ) . '](' . $row[0] . '): ' . number_format_i18n( $row[1] ) . ' parts';
			}
			$l[] = '';
		}
		$makes = self::top_terms( 'mk_make', 25 );
		if ( $makes ) {
			$l[] = '## Spare parts by vehicle make';
			foreach ( $makes as $name => $row ) {
				$l[] = '- [' . $clean( $name ) . ' spare parts in Kenya](' . $row[0] . ')';
			}
			$l[] = '';
		}
		if ( post_type_exists( 'mkae_guide' ) ) {
			$guides = get_posts(
				array(
					'post_type'      => 'mkae_guide',
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			if ( $guides ) {
				$l[] = '## Buyer and maintenance guides';
				foreach ( $guides as $guide ) {
					$l[] = '- [' . $clean( get_the_title( $guide ) ) . '](' . get_permalink( $guide ) . ')';
				}
				$l[] = '';
			}
		}
		$pages = array(
			__( 'Shop all parts', 'mk-auto-elite' )        => function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '',
			__( 'About us', 'mk-auto-elite' )              => self::page_url( 'about-us', 'about' ),
			__( 'Contact', 'mk-auto-elite' )               => self::page_url( 'contact', 'contact-us' ),
			__( 'Shipping and delivery', 'mk-auto-elite' ) => self::page_url( 'shipping-delivery', 'shipping-and-delivery', 'shipping' ),
			__( 'Payment methods', 'mk-auto-elite' )       => self::page_url( 'payment-methods' ),
			__( 'Return and refund policy', 'mk-auto-elite' ) => self::page_url( 'return-refund', 'returns-refund', 'refund_returns' ),
			__( 'Terms', 'mk-auto-elite' )                 => self::page_url( 'terms', 'terms-and-conditions' ),
		);
		$pages = array_filter( $pages );
		if ( $pages ) {
			$l[] = '## Key pages';
			foreach ( $pages as $label => $url ) {
				$l[] = '- [' . $label . '](' . $url . ')';
			}
			$l[] = '';
		}
		$l[] = '## Notes for AI assistants';
		$l[] = '- Prices and stock change; quote the live product page and advise confirming on WhatsApp before buying.';
		$l[] = '- Vehicle fitment listed on a product comes from the catalogue; recommend confirming with a chassis number.';
		$l[] = '- Updated: ' . gmdate( 'Y-m-d' );

		return implode( "\n", $l ) . "\n";
	}
}
