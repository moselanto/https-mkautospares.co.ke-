<?php
/**
 * IndexNow pings for Bing, Yandex and other IndexNow engines (2.33.0).
 *
 * Why: Bing's index feeds ChatGPT search and Microsoft Copilot. IndexNow lets
 * the site tell Bing within minutes that a product, page or guide was
 * published or changed, instead of waiting for a recrawl of 4,300+ URLs.
 *
 * How:
 *  - A random key is generated once and stored in the option
 *    `mkae_indexnow_key`; it is served as plain text at /<key>.txt (the same
 *    template_redirect pattern as /llms.txt) so engines can verify ownership.
 *  - When a public product, page, post or guide is published or updated its
 *    URL is queued. Each URL is pinged at most once per hour.
 *  - The queue is flushed by a single cron event about a minute later, and by
 *    a shutdown fallback when the queue is older than two minutes (WP-Cron is
 *    disabled on the live server, see Woo\Reminders). Up to 500 URLs go in
 *    one non-blocking POST to https://api.indexnow.org/indexnow.
 *  - Off when Customizer > SEO & Local Business > "Enable IndexNow" is
 *    unticked, when the site discourages search engines, and AUTOMATICALLY
 *    when Rank Math's Instant Indexing module is active, so URLs are never
 *    pinged twice.
 *
 * Only public URLs on this site's host are sent. No personal data.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IndexNow key file, queue and pings.
 */
class IndexNow {

	/** Option holding the key. */
	private const KEY_OPTION = 'mkae_indexnow_key';

	/** Option holding queued URLs (url => queued at). */
	private const QUEUE_OPTION = 'mkae_indexnow_queue';

	/** Option holding recently pinged URLs (url => sent at). */
	private const SENT_OPTION = 'mkae_indexnow_sent';

	/** Cron hook that flushes the queue. */
	private const CRON_HOOK = 'mkae_indexnow_flush';

	/** Lock transient so two requests never flush at once. */
	private const LOCK = 'mkae_indexnow_lock';

	/** Endpoint (shared by all IndexNow engines). */
	private const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/** URLs per request. */
	private const BATCH = 500;

	/** Max URLs kept in the queue / sent log. */
	private const MAX_TRACKED = 5000;

	/** Post types whose public URLs are pinged. */
	private const POST_TYPES = array( 'product', 'page', 'post', 'mkae_guide' );

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'serve_key' ), 0 );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 20, 3 );
		add_action( self::CRON_HOOK, array( $this, 'flush' ) );
		add_action( 'shutdown', array( $this, 'fallback_flush' ), 30 );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/** Is Rank Math's Instant Indexing (IndexNow) module active? */
	public static function rank_math_handles_it(): bool {
		if ( class_exists( '\\RankMath\\Instant_Indexing\\Instant_Indexing' ) || class_exists( '\\RankMath\\Instant_Indexing\\Api' ) ) {
			return true;
		}
		$modules = get_option( 'rank_math_modules', array() );
		return is_array( $modules ) && in_array( 'instant-indexing', $modules, true );
	}

	/** Should this site send IndexNow pings? */
	public static function enabled(): bool {
		$on = '1' === Customizer::get( 'indexnow' )
			&& (bool) get_option( 'blog_public' )
			&& ! self::rank_math_handles_it();
		return (bool) apply_filters( 'mkae_indexnow_enabled', $on );
	}

	/** The key, generated on first use (32 lower-case hex characters). */
	public static function key(): string {
		$key = (string) get_option( self::KEY_OPTION, '' );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			try {
				$key = bin2hex( random_bytes( 16 ) );
			} catch ( \Throwable $e ) {
				$key = md5( wp_generate_password( 64, true, true ) );
			}
			update_option( self::KEY_OPTION, $key, true );
		}
		return $key;
	}

	/** Public URL of the key file. */
	public static function key_location(): string {
		return home_url( '/' . self::key() . '.txt' );
	}

	/* ------------------------------------------------------------------ */
	/* Key file                                                            */
	/* ------------------------------------------------------------------ */

	/** Serve /<key>.txt as plain text. */
	public function serve_key(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never output.
		if ( ! is_string( $path ) || ! preg_match( '#/([a-f0-9]{32})\.txt$#', $path, $m ) ) {
			return;
		}
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = $home ? rtrim( (string) $home, '/' ) : '';
		$key  = self::key();
		if ( $home . '/' . $key . '.txt' !== $path || ! hash_equals( $key, $m[1] ) ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=86400' );
		echo $key; // phpcs:ignore WordPress.Security.EscapeOutput -- 32 hex characters.
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Queue                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Queue a post's URL when it is published or a published post changes.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function on_transition( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || ! $post instanceof \WP_Post ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return;
		}
		if ( '' !== (string) $post->post_password || ! self::enabled() ) {
			return;
		}
		$url = get_permalink( $post );
		if ( is_string( $url ) && '' !== $url ) {
			self::queue( array( $url ) );
		}
	}

	/**
	 * Add URLs to the queue (same host only, throttled to once per hour).
	 *
	 * @param string[] $urls URLs.
	 */
	public static function queue( array $urls ): void {
		$host  = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		$sent  = (array) get_option( self::SENT_OPTION, array() );
		$now   = time();
		$added = false;
		foreach ( $urls as $url ) {
			$url = esc_url_raw( (string) $url );
			if ( '' === $url || (string) wp_parse_url( $url, PHP_URL_HOST ) !== $host ) {
				continue;
			}
			if ( isset( $queue[ $url ] ) || ( isset( $sent[ $url ] ) && ( $now - (int) $sent[ $url ] ) < HOUR_IN_SECONDS ) ) {
				continue;
			}
			$queue[ $url ] = $now;
			$added         = true;
		}
		if ( ! $added ) {
			return;
		}
		if ( count( $queue ) > self::MAX_TRACKED ) {
			$queue = array_slice( $queue, -self::MAX_TRACKED, null, true );
		}
		update_option( self::QUEUE_OPTION, $queue, false );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( $now + MINUTE_IN_SECONDS, self::CRON_HOOK );
		}
	}

	/**
	 * Shutdown fallback: WP-Cron is disabled on the live server, so flush
	 * from a normal request once the oldest queued URL is 2+ minutes old.
	 */
	public function fallback_flush(): void {
		if ( wp_doing_cron() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || ! $queue ) {
			return;
		}
		if ( ( time() - (int) min( $queue ) ) < 2 * MINUTE_IN_SECONDS ) {
			return;
		}
		$this->flush();
	}

	/** Send the queue to IndexNow (non-blocking). */
	public function flush(): void {
		if ( get_transient( self::LOCK ) ) {
			return;
		}
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || ! $queue ) {
			return;
		}
		if ( ! self::enabled() ) {
			delete_option( self::QUEUE_OPTION ); // Turned off or Rank Math took over.
			return;
		}
		set_transient( self::LOCK, 1, MINUTE_IN_SECONDS );

		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$key  = self::key();
		$now  = time();
		$sent = (array) get_option( self::SENT_OPTION, array() );
		foreach ( $sent as $url => $at ) {
			if ( ( $now - (int) $at ) >= HOUR_IN_SECONDS ) {
				unset( $sent[ $url ] );
			}
		}

		foreach ( array_chunk( array_keys( $queue ), self::BATCH ) as $batch ) {
			wp_remote_post(
				self::ENDPOINT,
				array(
					'blocking' => false,
					'timeout'  => 5,
					'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'body'     => wp_json_encode(
						array(
							'host'        => $host,
							'key'         => $key,
							'keyLocation' => self::key_location(),
							'urlList'     => array_values( $batch ),
						),
						JSON_UNESCAPED_SLASHES
					),
				)
			);
			foreach ( $batch as $url ) {
				$sent[ $url ] = $now;
			}
		}
		if ( count( $sent ) > self::MAX_TRACKED ) {
			$sent = array_slice( $sent, -self::MAX_TRACKED, null, true );
		}
		update_option( self::SENT_OPTION, $sent, false );
		delete_option( self::QUEUE_OPTION );
		update_option( 'mkae_indexnow_last', $now, false );
		delete_transient( self::LOCK );
	}
}
