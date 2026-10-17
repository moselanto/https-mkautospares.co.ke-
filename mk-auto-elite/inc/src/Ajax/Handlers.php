<?php
/**
 * Secure AJAX endpoints for the finder, predictive search and mini-cart.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers nonce-verified AJAX handlers. All output is escaped and all input
 * is sanitized before use. Term/search lookups are cached in transients so the
 * finder and search feel instant; caches are cleared whenever products or
 * fitment terms change (see clear_caches()).
 */
class Handlers {

	/**
	 * Allowed fitment taxonomies for term queries.
	 *
	 * @var string[]
	 */
	private array $allowed_tax = array( 'mk_make', 'mk_model', 'mk_year', 'mk_engine', 'mk_fuel', 'mk_transmission' );

	/**
	 * Transient TTLs.
	 */
	private const TERMS_TTL  = 12 * HOUR_IN_SECONDS;
	private const SEARCH_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'wp_ajax_mkae_get_terms', array( $this, 'get_terms' ) );
		add_action( 'wp_ajax_nopriv_mkae_get_terms', array( $this, 'get_terms' ) );

		add_action( 'wp_ajax_mkae_search_suggest', array( $this, 'search_suggest' ) );
		add_action( 'wp_ajax_nopriv_mkae_search_suggest', array( $this, 'search_suggest' ) );

		// Guided Parts Finder: vehicle -> matching parts by category.
		add_action( 'wp_ajax_mkae_vehicle_parts', array( $this, 'vehicle_parts' ) );
		add_action( 'wp_ajax_nopriv_mkae_vehicle_parts', array( $this, 'vehicle_parts' ) );

		// Part Compatibility Checker: does this product fit a chosen vehicle?
		add_action( 'wp_ajax_mkae_check_fit', array( $this, 'check_fit' ) );
		add_action( 'wp_ajax_nopriv_mkae_check_fit', array( $this, 'check_fit' ) );

		// Service booking form submission.
		add_action( 'wp_ajax_mkae_submit_booking', array( $this, 'submit_booking' ) );
		add_action( 'wp_ajax_nopriv_mkae_submit_booking', array( $this, 'submit_booking' ) );

		// AI chat assistant (Google Gemini proxy).
		add_action( 'wp_ajax_mkae_chat', array( $this, 'chat' ) );
		add_action( 'wp_ajax_nopriv_mkae_chat', array( $this, 'chat' ) );

		// Mini-cart runs through WooCommerce's wc-ajax pipeline so the cart
		// session and cookies are written correctly for logged-out shoppers.
		// A single wc_ajax_{action} hook serves both logged-in and guests.
		add_action( 'wc_ajax_mkae_add_to_cart', array( $this, 'add_to_cart' ) );
		add_action( 'wc_ajax_mkae_get_cart', array( $this, 'get_cart' ) );
		add_action( 'wc_ajax_mkae_remove_item', array( $this, 'remove_item' ) );

		// Bust the finder/search caches when the catalogue or fitment changes.
		add_action( 'save_post_product', array( __CLASS__, 'clear_caches' ) );
		add_action( 'woocommerce_product_import_inserted_product_object', array( __CLASS__, 'clear_caches' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'clear_caches' ) );
		foreach ( $this->allowed_tax as $tax ) {
			add_action( "created_{$tax}", array( __CLASS__, 'clear_caches' ) );
			add_action( "edited_{$tax}", array( __CLASS__, 'clear_caches' ) );
			add_action( "delete_{$tax}", array( __CLASS__, 'clear_caches' ) );
		}
	}

	/**
	 * Verify the shared nonce or die with a 403.
	 */
	private function guard(): void {
		if ( ! check_ajax_referer( 'mkae_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mk-auto-elite' ) ), 403 );
		}
		// Per-IP ceiling on all public lookups (search, fitment, terms) so they
		// cannot be used to scrape the catalogue or load the server.
		$rk = 'mkae_ajax_rl_' . md5( \MKAutoElite\Security::client_ip() );
		$n  = (int) get_transient( $rk ) + 1;
		set_transient( $rk, $n, 5 * MINUTE_IN_SECONDS );
		if ( $n > 300 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'mk-auto-elite' ) ), 429 );
		}
	}

	/**
	 * Store a service booking and notify the shop + customer by email.
	 */
	public function submit_booking(): void {
		$this->guard();

		// Bot traps: the honeypot must stay empty, and a real person needs a
		// realistic moment to fill the form. Respond with a fake success so
		// bots do not learn they were blocked.
		$hp = isset( $_POST['mkae_hp'] ) ? trim( (string) wp_unslash( $_POST['mkae_hp'] ) ) : '';
		$ts = isset( $_POST['mkae_ts'] ) ? absint( $_POST['mkae_ts'] ) : 0;
		if ( '' !== $hp || ( $ts > 0 && ( time() - $ts ) < 3 ) ) {
			wp_send_json_success( array( 'message' => __( 'Thank you! We have received your booking and will contact you shortly to confirm.', 'mk-auto-elite' ) ) );
		}

		// Per-IP rate limit to stop flooding (max 8 bookings per hour per IP).
		$ip = \MKAutoElite\Security::client_ip();
		$rk = 'mkae_book_rl_' . md5( $ip );
		if ( (int) get_transient( $rk ) > 8 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests right now. Please WhatsApp us to book.', 'mk-auto-elite' ) ), 429 );
		}
		set_transient( $rk, (int) get_transient( $rk ) + 1, HOUR_IN_SECONDS );

		$name    = sanitize_text_field( wp_unslash( $_POST['mkae_name'] ?? '' ) );
		$phone   = sanitize_text_field( wp_unslash( $_POST['mkae_phone'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['mkae_email'] ?? '' ) );
		$vehicle = sanitize_text_field( wp_unslash( $_POST['mkae_vehicle'] ?? '' ) );
		$service = sanitize_text_field( wp_unslash( $_POST['mkae_service'] ?? '' ) );
		$date    = sanitize_text_field( wp_unslash( $_POST['mkae_date'] ?? '' ) );
		$time    = sanitize_text_field( wp_unslash( $_POST['mkae_time'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['mkae_message'] ?? '' ) );

		if ( '' === $name || '' === $phone || '' === $service ) {
			wp_send_json_error( array( 'message' => __( 'Please provide your name, phone and the service needed.', 'mk-auto-elite' ) ), 400 );
		}

		$title   = $name . ' - ' . $service;
		$summary = implode(
			"\n",
			array(
				__( 'Customer', 'mk-auto-elite' ) . ': ' . $name,
				__( 'Phone', 'mk-auto-elite' ) . ': ' . $phone,
				__( 'Email', 'mk-auto-elite' ) . ': ' . ( '' !== $email ? $email : '-' ),
				__( 'Vehicle', 'mk-auto-elite' ) . ': ' . ( '' !== $vehicle ? $vehicle : '-' ),
				__( 'Service', 'mk-auto-elite' ) . ': ' . $service,
				__( 'Preferred', 'mk-auto-elite' ) . ': ' . trim( $date . ' ' . $time ),
				__( 'Notes', 'mk-auto-elite' ) . ': ' . ( '' !== $message ? $message : '-' ),
			)
		);

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'mkae_booking',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $summary,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save your booking. Please try again or WhatsApp us.', 'mk-auto-elite' ) ), 500 );
		}

		update_post_meta( $post_id, '_mkae_name', $name );
		update_post_meta( $post_id, '_mkae_phone', $phone );
		update_post_meta( $post_id, '_mkae_email', $email );
		update_post_meta( $post_id, '_mkae_vehicle', $vehicle );
		update_post_meta( $post_id, '_mkae_service', $service );
		update_post_meta( $post_id, '_mkae_date', $date );
		update_post_meta( $post_id, '_mkae_time', $time );
		update_post_meta( $post_id, '_mkae_message', $message );
		update_post_meta( $post_id, '_mkae_status', 'new' );

		$site  = get_bloginfo( 'name' );
		$admin = get_option( 'admin_email' );

		// Notify the shop.
		wp_mail( $admin, __( 'New service booking: ', 'mk-auto-elite' ) . $title, $summary );

		// Confirm to the customer.
		if ( '' !== $email && is_email( $email ) ) {
			$cust_body = __( 'Hi', 'mk-auto-elite' ) . ' ' . $name . ",\n\n"
				. __( 'Thank you for your booking request. Our team will contact you shortly to confirm.', 'mk-auto-elite' ) . "\n\n"
				. __( 'Your request:', 'mk-auto-elite' ) . "\n" . $summary . "\n\n" . $site;
			wp_mail( $email, __( 'We received your booking - ', 'mk-auto-elite' ) . $site, $cust_body );
		}

		wp_send_json_success( array( 'message' => __( 'Thank you! We have received your booking and will contact you shortly to confirm.', 'mk-auto-elite' ) ) );
	}

	/**
	 * Proxy a chat turn to Google Gemini using the server-side API key and
	 * return the assistant reply. The key is never exposed to the browser.
	 */
	public function chat(): void {
		$this->guard();

		// The key is read from the server environment / wp-config.php first
		// (GEMINI_API_KEY or MKAE_GEMINI_API_KEY) so it never has to live in the
		// database; the Customizer field remains as a fallback.
		$key = \MKAutoElite\Security::secret( array( 'GEMINI_API_KEY', 'MKAE_GEMINI_API_KEY' ) );
		if ( '' === $key ) {
			$key = trim( \MKAutoElite\Customizer::get( 'gemini_api_key', '' ) );
		}
		if ( '' === $key ) {
			wp_send_json_error( array( 'message' => __( 'The AI assistant is not configured yet. Please continue on WhatsApp.', 'mk-auto-elite' ) ), 200 );
		}

		// Basic per-IP rate limit to protect the free quota.
		$ip   = \MKAutoElite\Security::client_ip();
		$rk   = 'mkae_chat_rl_' . md5( $ip );
		$hits = (int) get_transient( $rk );
		if ( $hits > 30 ) {
			wp_send_json_error( array( 'message' => __( 'Too many messages right now. Please continue on WhatsApp.', 'mk-auto-elite' ) ), 429 );
		}
		set_transient( $rk, $hits + 1, 5 * MINUTE_IN_SECONDS );

		$raw = isset( $_POST['messages'] ) ? wp_unslash( $_POST['messages'] ) : '[]';
		$in  = json_decode( (string) $raw, true );
		if ( ! is_array( $in ) ) {
			$in = array();
		}

		$contents = array();
		$in       = array_slice( $in, -14 );
		foreach ( $in as $m ) {
			$role = ( isset( $m['role'] ) && 'model' === $m['role'] ) ? 'model' : 'user';
			$text = isset( $m['text'] ) ? sanitize_textarea_field( (string) $m['text'] ) : '';
			if ( '' === $text ) {
				continue;
			}
			if ( strlen( $text ) > 1500 ) {
				$text = substr( $text, 0, 1500 );
			}
			$contents[] = array(
				'role'  => $role,
				'parts' => array( array( 'text' => $text ) ),
			);
		}
		if ( empty( $contents ) ) {
			wp_send_json_error( array( 'message' => __( 'Please type a message.', 'mk-auto-elite' ) ), 400 );
		}

		$primary = apply_filters( 'mkae_chat_model', 'gemini-2.0-flash' );
		$models  = array_values( array_unique( array_filter( array( $primary, 'gemini-1.5-flash' ) ) ) );

		$payload = array(
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $this->chat_system_prompt() ) ),
			),
			'contents'          => $contents,
			'generationConfig'  => array(
				'temperature'     => 0.4,
				'maxOutputTokens' => 500,
			),
		);

		$code = 0;
		$data = null;
		$body = '';
		foreach ( $models as $try_model ) {
			$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $try_model ) . ':generateContent';

			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 25,
					// Key sent as a header, not in the URL, so it never appears in proxy or server logs.
					'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $key ),
					'body'    => wp_json_encode( $payload ),
				)
			);

			if ( is_wp_error( $response ) ) {
				$code = 0;
				$body = $response->get_error_message();
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
				break;
			}
			// Only fall back to the next model when the model itself was rejected.
			if ( 404 !== $code ) {
				break;
			}
		}

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			error_log( 'MKAE Gemini error ' . $code . ': ' . substr( (string) $body, 0, 500 ) );
			$busy = __( 'The assistant is busy right now. Please continue on WhatsApp.', 'mk-auto-elite' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$busy = 'AI error (HTTP ' . $code . '). See the server debug log for details. You can continue on WhatsApp.';
			}
			wp_send_json_error( array( 'message' => $busy ), 200 );
		}

		$reply = '';
		if ( isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$reply .= $part['text'];
				}
			}
		}
		$reply = trim( $reply );
		if ( '' === $reply ) {
			wp_send_json_error( array( 'message' => __( 'I did not catch that. Please continue on WhatsApp.', 'mk-auto-elite' ) ), 200 );
		}

		wp_send_json_success( array( 'reply' => $reply ) );
	}

	/**
	 * System instruction that shapes the shop assistant's behaviour.
	 */
	private function chat_system_prompt(): string {
		$site = get_bloginfo( 'name' );
		$wa   = \MKAutoElite\Customizer::get( 'whatsapp', '254702343302' );

		return implode(
			' ',
			array(
				'You are the friendly online assistant for ' . $site . ', a car spare-parts and service shop in Nairobi, Kenya.',
				'Help customers find the right spare part, check whether a part fits their car (ask for make, model and year when relevant), explain options, and give practical guidance on service and repairs.',
				'Prices may be shown as "on request"; when unsure of a price or stock level, say the team will confirm on WhatsApp.',
				'Keep answers short, warm and practical, and use Kenyan context (prices in KES; payment by M-Pesa Paybill 529914, Account 4268).',
				'Never invent prices, stock levels or part numbers you are not sure of.',
				'When the customer is ready to buy, book a service, or wants a human, tell them to tap "Continue on WhatsApp" (' . $wa . ').',
				'Politely stay on topics about the shop, vehicles, parts and service only.',
			)
		);
	}

	/**
	 * Bump the cache namespace so all previously stored term/search transients
	 * are ignored. Cheaper and more reliable than tracking individual keys.
	 */
	public static function clear_caches(): void {
		update_option( 'mkae_cache_ns', (string) time(), false );
	}

	/**
	 * Current cache namespace token.
	 */
	private function ns(): string {
		return (string) get_option( 'mkae_cache_ns', '1' );
	}

	/**
	 * Return term options for a given taxonomy (cascading finder). Cached.
	 */
	public function get_terms(): void {
		$this->guard();

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		if ( ! in_array( $taxonomy, $this->allowed_tax, true ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown taxonomy.', 'mk-auto-elite' ) ), 400 );
		}

		// Parent selections make the cascade real: asking for mk_model with
		// parent mk_make=lexus returns only models attached to products that
		// are also tagged Lexus. Without this, every step listed the whole
		// catalogue and users could build impossible make/model pairs.
		$parents = array();
		if ( isset( $_POST['parents'] ) && is_array( $_POST['parents'] ) ) {
			foreach ( wp_unslash( $_POST['parents'] ) as $ptax => $pslug ) {
				$ptax  = sanitize_key( $ptax );
				$pslug = sanitize_title( $pslug );
				if ( $ptax !== $taxonomy && '' !== $pslug && in_array( $ptax, $this->allowed_tax, true ) && taxonomy_exists( $ptax ) ) {
					$parents[ $ptax ] = $pslug;
				}
			}
		}
		ksort( $parents );

		// Serve from cache when we can. Key includes the cache namespace so a
		// catalogue change invalidates every stored combination at once.
		$cache_key = 'mkae_terms2_' . md5( $this->ns() . '|' . $taxonomy . '|' . wp_json_encode( $parents ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( array( 'options' => $cached, 'cached' => true ) );
		}

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'name',
		);

		if ( ! empty( $parents ) ) {
			$tax_query = array( 'relation' => 'AND' );
			foreach ( $parents as $ptax => $pslug ) {
				$tax_query[] = array(
					'taxonomy' => $ptax,
					'field'    => 'slug',
					'terms'    => $pslug,
				);
			}
			$ids = get_posts(
				array(
					'post_type'      => post_type_exists( 'product' ) ? 'product' : 'post',
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			if ( empty( $ids ) ) {
				set_transient( $cache_key, array(), self::TERMS_TTL );
				wp_send_json_success( array( 'options' => array() ) );
			}
			$args['object_ids'] = $ids;
		} else {
			$args['number'] = 500;
		}

		$terms = get_terms( $args );

		// v2.23.1: when earlier steps are chosen, count only products matching every selection.
		$scoped = array();
		if ( ! empty( $args['object_ids'] ) ) {
			$rel = wp_get_object_terms( $args['object_ids'], $taxonomy, array( 'fields' => 'all_with_object_id' ) );
			if ( ! is_wp_error( $rel ) ) {
				foreach ( $rel as $r ) {
					$scoped[ (int) $r->term_id ][ (int) $r->object_id ] = true;
				}
			}
		}

		$options = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'value' => $term->slug,
					'label' => $term->name,
					'count' => $scoped ? count( $scoped[ (int) $term->term_id ] ?? array() ) : (int) $term->count,
				);
			}
		}

		set_transient( $cache_key, $options, self::TERMS_TTL );
		wp_send_json_success( array( 'options' => $options ) );
	}

	/**
	 * Predictive product search suggestions. Matches product titles AND SKUs so
	 * shoppers can type a part number. Cached for a short window.
	 */
	public function search_suggest(): void {
		$this->guard();

		$term = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$cache_key = 'mkae_sugg_' . md5( $this->ns() . '|' . strtolower( $term ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( array( 'results' => $cached, 'cached' => true ) );
		}

		$post_type = post_type_exists( 'product' ) ? 'product' : 'post';
		$limit     = 8;

		// 1) Title/content relevance search.
		$query = new \WP_Query(
			array(
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => $limit,
				's'                   => $term,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);
		$ids = wp_list_pluck( $query->posts, 'ID' );
		wp_reset_postdata();

		// 2) SKU match (part numbers) to top up results, if room remains.
		if ( count( $ids ) < $limit && 'product' === $post_type ) {
			$sku_q = new \WP_Query(
				array(
					'post_type'           => 'product',
					'post_status'         => 'publish',
					'posts_per_page'      => $limit - count( $ids ),
					'post__not_in'        => $ids ? $ids : array( 0 ),
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'          => array(
						array(
							'key'     => '_sku',
							'value'   => $term,
							'compare' => 'LIKE',
						),
					),
				)
			);
			$ids = array_merge( $ids, wp_list_pluck( $sku_q->posts, 'ID' ) );
			wp_reset_postdata();
		}

		$results = array();
		foreach ( array_unique( $ids ) as $pid ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
			$results[] = array(
				'title' => wp_strip_all_tags( get_the_title( $pid ) ),
				'url'   => get_permalink( $pid ),
				'thumb' => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '',
				'price' => $product ? wp_strip_all_tags( $product->get_price_html() ) : '',
			);
		}

		set_transient( $cache_key, $results, self::SEARCH_TTL );
		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Guided Parts Finder: given a vehicle (fitment slugs), return the count of
	 * matching parts and a category breakdown. Relaxes the sparsest filters
	 * (engine, then year) when a combination has no matches, so shoppers never
	 * hit a dead end. Cached per vehicle in the shared cache namespace.
	 */
	public function vehicle_parts(): void {
		$this->guard();

		$vehicle = array();
		if ( isset( $_POST['vehicle'] ) && is_array( $_POST['vehicle'] ) ) {
			foreach ( wp_unslash( $_POST['vehicle'] ) as $tax => $slug ) {
				$tax  = sanitize_key( $tax );
				$slug = sanitize_title( $slug );
				if ( '' !== $slug && in_array( $tax, $this->allowed_tax, true ) && taxonomy_exists( $tax ) ) {
					$vehicle[ $tax ] = $slug;
				}
			}
		}
		if ( empty( $vehicle['mk_make'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a make.', 'mk-auto-elite' ) ), 400 );
		}
		ksort( $vehicle );

		$cache_key = 'mkae_vp_' . md5( $this->ns() . '|' . wp_json_encode( $vehicle ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		$post_type = post_type_exists( 'product' ) ? 'product' : 'post';

		// Try the full combination, then progressively drop the sparse filters.
		$active    = $vehicle;
		$relaxed   = array();
		$droppable = array( 'mk_engine', 'mk_year' );
		$ids       = array();
		while ( true ) {
			$ids = $this->fitment_ids( $active, $post_type );
			if ( ! empty( $ids ) ) {
				break;
			}
			$dropped = false;
			foreach ( $droppable as $d ) {
				if ( isset( $active[ $d ] ) ) {
					unset( $active[ $d ] );
					$relaxed[] = $d;
					$dropped   = true;
					break;
				}
			}
			if ( ! $dropped ) {
				break;
			}
		}

		$payload = array(
			'total'      => count( $ids ),
			'relaxed'    => array_values( $relaxed ),
			'categories' => empty( $ids ) ? array() : $this->category_breakdown( $ids ),
			'shopQuery'  => $active,
		);
		set_transient( $cache_key, $payload, self::TERMS_TTL );
		wp_send_json_success( $payload );
	}

	/**
	 * Product IDs matching every provided fitment term (AND).
	 *
	 * @param array<string,string> $vehicle   tax => slug.
	 * @param string               $post_type Post type to query.
	 * @return int[]
	 */
	private function fitment_ids( array $vehicle, string $post_type ): array {
		if ( empty( $vehicle ) ) {
			return array();
		}
		$tax_query = array( 'relation' => 'AND' );
		foreach ( $vehicle as $tax => $slug ) {
			$tax_query[] = array(
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $slug,
			);
		}
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
	}

	/**
	 * Roll matching products up to their top-level product_cat parents, counting
	 * each product once per top-level category. One relations query, no per-post
	 * loops. Sorted by count descending.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array<int,array<string,mixed>>
	 */
	private function category_breakdown( array $ids ): array {
		if ( empty( $ids ) || ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$all = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_wp_error( $all ) || empty( $all ) ) {
			return array();
		}
		$by_id = array();
		foreach ( $all as $t ) {
			$by_id[ (int) $t->term_id ] = $t;
		}
		$top_of = static function ( int $tid ) use ( $by_id ): int {
			$guard = 0;
			while ( isset( $by_id[ $tid ] ) && (int) $by_id[ $tid ]->parent > 0 && $guard < 12 ) {
				$tid = (int) $by_id[ $tid ]->parent;
				++$guard;
			}
			return $tid;
		};

		$rels = wp_get_object_terms( $ids, 'product_cat', array( 'fields' => 'all_with_object_id' ) );
		if ( is_wp_error( $rels ) ) {
			return array();
		}
		$per_product = array();
		foreach ( $rels as $r ) {
			$per_product[ (int) $r->object_id ][ $top_of( (int) $r->term_id ) ] = true;
		}
		$counts = array();
		foreach ( $per_product as $tops ) {
			foreach ( array_keys( $tops ) as $tid ) {
				$counts[ $tid ] = ( $counts[ $tid ] ?? 0 ) + 1;
			}
		}
		$out = array();
		foreach ( $counts as $tid => $n ) {
			if ( ! isset( $by_id[ $tid ] ) ) {
				continue;
			}
			$link  = get_term_link( $by_id[ $tid ] );
			$out[] = array(
				'name'  => $by_id[ $tid ]->name,
				'slug'  => $by_id[ $tid ]->slug,
				'count' => (int) $n,
				'url'   => is_wp_error( $link ) ? '' : $link,
			);
		}
		usort( $out, static fn( $a, $b ) => $b['count'] <=> $a['count'] );
		return $out;
	}

	/**
	 * Part Compatibility Checker: verdict for one product against a chosen
	 * vehicle. Matches on both the fitment taxonomies and the _mk_* meta so it
	 * is accurate (real year range) and works even before the fitment sync runs.
	 */
	public function check_fit(): void {
		$this->guard();

		$pid     = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$product = ( $pid && function_exists( 'wc_get_product' ) ) ? wc_get_product( $pid ) : null;
		if ( ! $product instanceof \WC_Product ) {
			wp_send_json_error( array( 'message' => __( 'Unknown product.', 'mk-auto-elite' ) ), 400 );
		}

		$make_slug   = isset( $_POST['make'] ) ? sanitize_title( wp_unslash( $_POST['make'] ) ) : '';
		$make_label  = isset( $_POST['make_label'] ) ? sanitize_text_field( wp_unslash( $_POST['make_label'] ) ) : '';
		$model_slug  = isset( $_POST['model'] ) ? sanitize_title( wp_unslash( $_POST['model'] ) ) : '';
		$model_label = isset( $_POST['model_label'] ) ? sanitize_text_field( wp_unslash( $_POST['model_label'] ) ) : '';
		$year        = isset( $_POST['year'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['year'] ) ) : '';

		if ( '' === $make_slug && '' === $make_label ) {
			wp_send_json_error( array( 'message' => __( 'Please choose your make.', 'mk-auto-elite' ) ), 400 );
		}

		$p_make  = $this->fit_values( $pid, 'mk_make', '_mk_make' );
		$p_model = $this->fit_values( $pid, 'mk_model', '_mk_model' );
		$yfrom   = (int) get_post_meta( $pid, '_mk_year_from', true );
		$yto     = (int) get_post_meta( $pid, '_mk_year_to', true );

		$listed    = \MKAutoElite\Woo\WooCommerce::fitment_line( $product );
		$wa        = \MKAutoElite\Woo\WooCommerce::whatsapp_url( $product );
		$sel_make  = '' !== $make_label ? $make_label : $make_slug;
		$sel_model = '' !== $model_label ? $model_label : $model_slug;

		$make_known     = ! empty( $p_make );
		$make_match     = $this->in_set( $make_slug, $make_label, $p_make );
		$model_known    = ! empty( $p_model );
		$user_has_model = ( '' !== $model_slug || '' !== $model_label );

		if ( ! $make_known ) {
			$status = 'universal';
			$title  = __( 'Fitment not listed', 'mk-auto-elite' );
			$detail = __( 'We have not tagged a vehicle for this part yet. Send us your car or chassis number on WhatsApp and we will confirm it fits before you order.', 'mk-auto-elite' );
		} elseif ( ! $make_match ) {
			$status = 'unlikely';
			$detail = sprintf(
				/* translators: 1: listed fitment, 2: chosen vehicle. */
				__( 'This part is listed for %1$s. It may not fit your %2$s - check with us on WhatsApp if you believe it is compatible.', 'mk-auto-elite' ),
				'' !== $listed ? $listed : implode( ', ', $p_make ),
				$sel_make
			);
			$title = __( 'Probably not a match', 'mk-auto-elite' );
		} elseif ( $model_known && $user_has_model && ! $this->in_set( $model_slug, $model_label, $p_model ) ) {
			$status = 'maybe';
			$title  = __( 'Might fit - please confirm', 'mk-auto-elite' );
			$detail = sprintf(
				/* translators: 1: listed fitment, 2: chosen vehicle. */
				__( 'This is listed for %1$s. It may still suit your %2$s, but confirm on WhatsApp before ordering.', 'mk-auto-elite' ),
				'' !== $listed ? $listed : $sel_make,
				trim( $sel_make . ' ' . $sel_model )
			);
		} elseif ( $model_known && ! $user_has_model ) {
			$status = 'maybe';
			$title  = __( 'Choose your model', 'mk-auto-elite' );
			$detail = sprintf(
				/* translators: %s: listed fitment. */
				__( 'This part is listed for %s. Select your model above to confirm it matches.', 'mk-auto-elite' ),
				'' !== $listed ? $listed : $sel_make
			);
		} else {
			$in_years = ( '' !== $year && $yfrom > 0 )
				? ( (int) $year >= $yfrom && (int) $year <= ( $yto > 0 ? $yto : $yfrom ) )
				: true;
			if ( $in_years ) {
				$status = 'fits';
				$title  = __( 'This fits your car', 'mk-auto-elite' );
				$detail = '' !== $listed
					? sprintf(
						/* translators: %s: listed fitment. */
						__( 'Listed fitment: %s. Vehicle years and variants can still differ - if in doubt, confirm on WhatsApp.', 'mk-auto-elite' ),
						$listed
					)
					: __( 'This part matches your vehicle. If your variant differs, confirm on WhatsApp.', 'mk-auto-elite' );
			} else {
				$status = 'maybe';
				$title  = __( 'Check the year', 'mk-auto-elite' );
				$detail = sprintf(
					/* translators: 1: listed fitment, 2: chosen year. */
					__( 'This is listed for %1$s. Your year (%2$s) is outside that range, so confirm on WhatsApp before ordering.', 'mk-auto-elite' ),
					'' !== $listed ? $listed : $sel_make,
					$year
				);
			}
		}

		wp_send_json_success(
			array(
				'status'   => $status,
				'title'    => $title,
				'detail'   => $detail,
				'listed'   => $listed,
				'whatsapp' => $wa,
			)
		);
	}

	/**
	 * Collect a product's fitment values for a field as normalized tokens
	 * (taxonomy term slugs + names plus the _mk_* meta value).
	 *
	 * @param int    $pid      Product ID.
	 * @param string $taxonomy Fitment taxonomy.
	 * @param string $meta_key Fallback meta key.
	 * @return string[] Normalized lowercase tokens.
	 */
	private function fit_values( int $pid, string $taxonomy, string $meta_key ): array {
		$vals = array();
		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = wp_get_post_terms( $pid, $taxonomy );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$vals[] = $t->slug;
					$vals[] = $t->name;
				}
			}
		}
		$meta = trim( (string) get_post_meta( $pid, $meta_key, true ) );
		if ( '' !== $meta ) {
			$vals[] = $meta;
		}
		$out = array();
		foreach ( $vals as $v ) {
			$n = $this->norm( (string) $v );
			if ( '' !== $n ) {
				$out[ $n ] = $n;
			}
		}
		return array_values( $out );
	}

	/**
	 * Normalize a fitment token for comparison (lowercase, alphanumeric only).
	 */
	private function norm( string $s ): string {
		return (string) preg_replace( '/[^a-z0-9]+/', '', strtolower( $s ) );
	}

	/**
	 * Is the selected slug or label present in a normalized value set?
	 *
	 * @param string   $slug  Selected term slug.
	 * @param string   $label Selected term label.
	 * @param string[] $set   Normalized product values.
	 */
	private function in_set( string $slug, string $label, array $set ): bool {
		foreach ( array( $slug, $label ) as $needle ) {
			$n = $this->norm( $needle );
			if ( '' !== $n && in_array( $n, $set, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ensure the WooCommerce cart/session are loaded in the AJAX context.
	 */
	private function boot_cart(): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		return null !== WC()->cart;
	}

	/**
	 * Build the JSON payload describing the current cart for the drawer.
	 *
	 * @return array<string,mixed>
	 */
	private function cart_payload(): array {
		$items = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$product = $item['data'];
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$thumb_id = $product->get_image_id();
			$items[]  = array(
				'key'   => $key,
				'name'  => wp_strip_all_tags( $product->get_name() ),
				'qty'   => (int) $item['quantity'],
				'url'   => get_permalink( $product->get_id() ),
				'thumb' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
				'line'  => html_entity_decode( wp_strip_all_tags( wc_price( (float) $item['line_total'] + (float) $item['line_tax'] ) ), ENT_QUOTES, 'UTF-8' ),
			);
		}
		return array(
			'count'        => WC()->cart->get_cart_contents_count(),
			'subtotal'     => WC()->cart->get_cart_subtotal(),
			'items'        => $items,
			'cart_url'     => wc_get_cart_url(),
			'checkout_url' => wc_get_checkout_url(),
		);
	}

	/**
	 * Add a product to the cart and return the refreshed cart payload.
	 */
	public function add_to_cart(): void {
		$this->guard();
		if ( ! $this->boot_cart() ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'mk-auto-elite' ) ), 400 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$qty        = isset( $_POST['qty'] ) ? max( 1, absint( wp_unslash( $_POST['qty'] ) ) ) : 1;
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'This item cannot be added to the cart. Please order it on WhatsApp.', 'mk-auto-elite' ) ), 400 );
		}

		$added = WC()->cart->add_to_cart( $product_id, $qty );
		if ( ! $added ) {
			$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
			$message = ! empty( $notices ) ? wp_strip_all_tags( $notices[0]['notice'] ) : __( 'Could not add to cart.', 'mk-auto-elite' );
			if ( function_exists( 'wc_clear_notices' ) ) {
				wc_clear_notices();
			}
			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		$payload           = $this->cart_payload();
		$payload['added']  = wp_strip_all_tags( $product->get_name() );
		wp_send_json_success( $payload );
	}

	/**
	 * Return the current cart payload (drawer open / page load sync).
	 */
	public function get_cart(): void {
		$this->guard();
		if ( ! $this->boot_cart() ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'mk-auto-elite' ) ), 400 );
		}
		wp_send_json_success( $this->cart_payload() );
	}

	/**
	 * Remove a single line item and return the refreshed cart payload.
	 */
	public function remove_item(): void {
		$this->guard();
		if ( ! $this->boot_cart() ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'mk-auto-elite' ) ), 400 );
		}
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( '' !== $key ) {
			WC()->cart->remove_cart_item( $key );
		}
		wp_send_json_success( $this->cart_payload() );
	}
}
