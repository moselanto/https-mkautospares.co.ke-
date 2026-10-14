<?php
/**
 * Analytics: Google Analytics 4 (gtag) + Meta (Facebook) Pixel injection.
 *
 * IDs are entered in Customizer -> MK Auto Elite -> Analytics & Tracking.
 * When set, the base tags are printed in <head> and a small window.MKAEA
 * config is exposed so the front-end script (assets/js/main.js) can fire
 * store events: add_to_cart, whatsapp_click, vehicle_finder, search,
 * begin_checkout. Nothing is loaded until an ID is provided, so the theme
 * ships privacy-clean by default.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects analytics tags and the JS tracking config.
 */
class Analytics {

	/**
	 * Hook in. Priority 20 keeps these async tags after the LCP preloads.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'head_tags' ), 20 );
	}

	/**
	 * GA4 Measurement ID (e.g. G-XXXXXXXXXX), trimmed.
	 */
	private function ga4(): string {
		return trim( Customizer::get( 'ga4_id', '' ) );
	}

	/**
	 * Meta Pixel ID, trimmed.
	 */
	private function pixel(): string {
		return trim( Customizer::get( 'meta_pixel_id', '' ) );
	}

	/**
	 * Whether custom store events should fire (page views always do).
	 */
	private function events_enabled(): bool {
		return 'no' !== Customizer::get( 'track_events', 'yes' );
	}

	/**
	 * Print the config object and any configured analytics base tags.
	 */
	public function head_tags(): void {
		if ( is_admin() ) {
			return;
		}

		$ga    = $this->ga4();
		$px    = $this->pixel();
		$track = $this->events_enabled() ? 1 : 0;

		// Always expose the config so the front-end script knows whether to
		// emit custom events (and can no-op safely when no tag is present).
		echo '<script id="mkae-analytics-config">window.MKAEA={track:' . (int) $track . '};</script>' . "\n";

		if ( '' === $ga && '' === $px ) {
			return;
		}

		// Google Analytics 4 (gtag.js).
		if ( '' !== $ga ) {
			echo "\n<!-- MK Auto Elite: Google Analytics 4 -->\n";
			printf(
				'<script async src="https://www.googletagmanager.com/gtag/js?id=%s"></script>' . "\n",
				rawurlencode( $ga )
			);
			printf(
				"<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','%s');</script>\n",
				esc_js( $ga )
			);
		}

		// Meta (Facebook) Pixel.
		if ( '' !== $px ) {
			echo "\n<!-- MK Auto Elite: Meta Pixel -->\n";
			printf(
				"<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','%s');fbq('track','PageView');</script>\n",
				esc_js( $px )
			);
			printf(
				'<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=%s&ev=PageView&noscript=1" alt=""></noscript>' . "\n",
				rawurlencode( $px )
			);
		}
	}
}
