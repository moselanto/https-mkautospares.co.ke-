<?php
/**
 * Theme Options via the WordPress Customizer.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers contact, brand, shop, footer and social settings, and exposes a
 * typed getter.
 */
class Customizer {

	/**
	 * Default values used across the theme.
	 *
	 * @var array<string,string>
	 */
	private static array $defaults = array(
		'phone'         => '+254 702343302',
		'whatsapp'      => '254702343302',
		'email'         => 'info@mkautospares.co.ke',
		'hours'         => 'Mon - Sat: 8:00am - 6:00pm',
		'delivery_note' => 'Fast countrywide delivery across Kenya',
		'location'      => 'Kirinyaga Road, Nairobi',
		'address'       => 'Along Kirinyaga Road, Nairobi (Glogon, behind Shell petrol station, near Kanju washrooms)',
		'mpesa'         => 'Lipa na M-Pesa: Paybill 529914, Account 4268',
		'accent'        => '#CA1D24',
		'map_embed'     => '',
		'map_link'      => '',
		'newsletter_action' => '',
		'quote_default' => 'yes',
		'ga4_id'        => '',
		'meta_pixel_id' => '',
		'track_events'  => 'yes',
		'facebook'      => '',
		'instagram'     => '',
		'x_twitter'     => '',
		'tiktok'        => '',
		'youtube'       => '',
		// SEO / structured data (Customizer > MK Auto Elite > SEO & Local Business).
		'brand_short'    => 'MK Auto Spares',
		'street_address' => 'Kirinyaga Road',
		'city'           => 'Nairobi',
		'region'         => 'Nairobi County',
		'opening_hours'  => 'Mo-Sa 08:00-18:00',
		'geo_lat'        => '',
		'geo_lng'        => '',
		'return_days'    => '7',
		'og_image'       => '',
	);

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'customize_register', array( $this, 'register' ) );
	}

	/**
	 * Typed option getter with sane fallback.
	 */
	public static function get( string $key, ?string $fallback = null ): string {
		$default = $fallback ?? ( self::$defaults[ $key ] ?? '' );
		$value   = get_theme_mod( 'mkae_' . $key, $default );
		return is_string( $value ) ? $value : $default;
	}

	/**
	 * Register Customizer controls.
	 *
	 * @param \WP_Customize_Manager $wp_customize Manager.
	 */
	public function register( $wp_customize ): void {
		$wp_customize->add_panel(
			'mkae_panel',
			array(
				'title'    => __( 'MK Auto Elite', 'mk-auto-elite' ),
				'priority' => 20,
			)
		);

		// Contact / utility bar.
		$wp_customize->add_section(
			'mkae_contact',
			array(
				'title' => __( 'Contact & Utility Bar', 'mk-auto-elite' ),
				'panel' => 'mkae_panel',
			)
		);

		$fields = array(
			'phone'         => array( __( 'Phone number', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'whatsapp'      => array( __( 'WhatsApp number (digits only, incl. country code)', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'email'         => array( __( 'Email address', 'mk-auto-elite' ), 'sanitize_email', 'text' ),
			'hours'         => array( __( 'Business hours', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'delivery_note' => array( __( 'Delivery notice', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'location'      => array( __( 'Location label', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'address'       => array( __( 'Full physical address', 'mk-auto-elite' ), 'sanitize_textarea_field', 'textarea' ),
			'mpesa'         => array( __( 'M-Pesa pay instruction (e.g. Send Money to 0702 343 302)', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'map_link'      => array( __( 'Google Maps link (Get directions button)', 'mk-auto-elite' ), 'esc_url_raw', 'url' ),
		);

		foreach ( $fields as $key => $meta ) {
			$wp_customize->add_setting(
				'mkae_' . $key,
				array(
					'default'           => self::$defaults[ $key ] ?? '',
					'sanitize_callback' => $meta[1],
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				'mkae_' . $key,
				array(
					'label'   => $meta[0],
					'section' => 'mkae_contact',
					'type'    => $meta[2],
				)
			);
		}

		// Brand / appearance.
		$wp_customize->add_section(
			'mkae_brand',
			array(
				'title' => __( 'Brand & Appearance', 'mk-auto-elite' ),
				'panel' => 'mkae_panel',
			)
		);
		$wp_customize->add_setting(
			'mkae_accent',
			array(
				'default'           => self::$defaults['accent'],
				'sanitize_callback' => 'sanitize_hex_color',
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			new \WP_Customize_Color_Control(
				$wp_customize,
				'mkae_accent',
				array(
					'label'   => __( 'Accent colour', 'mk-auto-elite' ),
					'section' => 'mkae_brand',
				)
			)
		);

		// Shop behaviour.
		$wp_customize->add_section(
			'mkae_shop',
			array(
				'title' => __( 'Shop & Ordering', 'mk-auto-elite' ),
				'panel' => 'mkae_panel',
			)
		);
		$wp_customize->add_setting(
			'mkae_quote_default',
			array(
				'default'           => self::$defaults['quote_default'],
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		$wp_customize->add_control(
			'mkae_quote_default',
			array(
				'label'       => __( 'Show WhatsApp "Request price" when a product has no price', 'mk-auto-elite' ),
				'description' => __( 'Recommended ON while your catalogue is still being priced.', 'mk-auto-elite' ),
				'section'     => 'mkae_shop',
				'type'        => 'select',
				'choices'     => array(
					'yes' => __( 'Yes', 'mk-auto-elite' ),
					'no'  => __( 'No', 'mk-auto-elite' ),
				),
			)
		);
		$wp_customize->add_setting(
			'mkae_map_embed',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			'mkae_map_embed',
			array(
				'label'   => __( 'Google Maps embed URL', 'mk-auto-elite' ),
				'section' => 'mkae_shop',
				'type'    => 'url',
			)
		);

		// Analytics & tracking.
		$wp_customize->add_section(
			'mkae_analytics',
			array(
				'title'       => __( 'Analytics & Tracking', 'mk-auto-elite' ),
				'description' => __( 'Paste your IDs to enable Google Analytics 4 and the Meta (Facebook) Pixel. The theme automatically fires events for add-to-cart, WhatsApp orders, vehicle-finder use and search. Nothing loads until an ID is entered.', 'mk-auto-elite' ),
				'panel'       => 'mkae_panel',
			)
		);
		$wp_customize->add_setting(
			'mkae_ga4_id',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'mkae_ga4_id',
			array(
				'label'       => __( 'Google Analytics 4 Measurement ID', 'mk-auto-elite' ),
				'description' => __( 'Looks like G-XXXXXXXXXX. Find it in GA4 -> Admin -> Data Streams -> your web stream.', 'mk-auto-elite' ),
				'section'     => 'mkae_analytics',
				'type'        => 'text',
			)
		);
		$wp_customize->add_setting(
			'mkae_meta_pixel_id',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'mkae_meta_pixel_id',
			array(
				'label'       => __( 'Meta (Facebook) Pixel ID', 'mk-auto-elite' ),
				'description' => __( 'A 15-16 digit number from Meta Events Manager. Leave blank to skip.', 'mk-auto-elite' ),
				'section'     => 'mkae_analytics',
				'type'        => 'text',
			)
		);
		$wp_customize->add_setting(
			'mkae_track_events',
			array(
				'default'           => 'yes',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		$wp_customize->add_control(
			'mkae_track_events',
			array(
				'label'       => __( 'Track store events (add to cart, WhatsApp, finder, search)', 'mk-auto-elite' ),
				'description' => __( 'Recommended ON. Turn off to send only page views.', 'mk-auto-elite' ),
				'section'     => 'mkae_analytics',
				'type'        => 'select',
				'choices'     => array(
					'yes' => __( 'Yes', 'mk-auto-elite' ),
					'no'  => __( 'No', 'mk-auto-elite' ),
				),
			)
		);

		// Homepage hero background.
		$wp_customize->add_section(
			'mkae_hero',
			array(
				'title' => __( 'Homepage Hero', 'mk-auto-elite' ),
				'panel' => 'mkae_panel',
			)
		);
		$wp_customize->add_setting(
			'mkae_hero_motion',
			array(
				'default'           => 'yes',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		$wp_customize->add_control(
			'mkae_hero_motion',
			array(
				'label'       => __( 'Show hero background video (motion)', 'mk-auto-elite' ),
				'description' => __( 'Uses the bundled mechanic clip so the hero moves. Turn off to show a still image with a subtle zoom instead. A custom video URL below always overrides.', 'mk-auto-elite' ),
				'section'     => 'mkae_hero',
				'type'        => 'select',
				'choices'     => array(
					'yes' => __( 'Yes', 'mk-auto-elite' ),
					'no'  => __( 'No', 'mk-auto-elite' ),
				),
			)
		);
		$wp_customize->add_setting(
			'mkae_hero_video',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			'mkae_hero_video',
			array(
				'label'       => __( 'Hero background video URL (.mp4 / .webm)', 'mk-auto-elite' ),
				'description' => __( 'Optional. A short, muted, looping clip of a mechanic / spare parts works best. Leave blank to use the bundled image.', 'mk-auto-elite' ),
				'section'     => 'mkae_hero',
				'type'        => 'url',
			)
		);
		$wp_customize->add_setting(
			'mkae_hero_image',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			'mkae_hero_image',
			array(
				'label'       => __( 'Hero background image / GIF URL', 'mk-auto-elite' ),
				'description' => __( 'Optional. Overrides the bundled hero image. Also used as the video poster.', 'mk-auto-elite' ),
				'section'     => 'mkae_hero',
				'type'        => 'url',
			)
		);

		// SEO & local business. Feeds Seo\Schema and Seo\Meta so structured
		// data and meta fallbacks never hard-code business details.
		$wp_customize->add_section(
			'mkae_seo',
			array(
				'title'       => __( 'SEO & Local Business', 'mk-auto-elite' ),
				'description' => __( 'Used in Google structured data (store address, hours, map pin, returns) and in fallback page titles and descriptions. Titles or descriptions typed into Rank Math on a page always win.', 'mk-auto-elite' ),
				'panel'       => 'mkae_panel',
			)
		);
		$seo_fields = array(
			'brand_short'    => array( __( 'Short brand name (used in page titles)', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'street_address' => array( __( 'Street address (schema)', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'city'           => array( __( 'Town / city (schema)', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'region'         => array( __( 'County (schema)', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'opening_hours'  => array( __( 'Opening hours in schema format, e.g. Mo-Sa 08:00-18:00', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'geo_lat'        => array( __( 'Shop latitude (e.g. -1.2833). Leave blank if unsure.', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'geo_lng'        => array( __( 'Shop longitude (e.g. 36.8285). Leave blank if unsure.', 'mk-auto-elite' ), 'sanitize_text_field', 'text' ),
			'return_days'    => array( __( 'Return window in days (must match the Returns & Refund page)', 'mk-auto-elite' ), 'absint', 'number' ),
			'og_image'       => array( __( 'Default social share image URL (1200x630). Blank uses the homepage hero.', 'mk-auto-elite' ), 'esc_url_raw', 'url' ),
		);
		foreach ( $seo_fields as $key => $meta ) {
			$wp_customize->add_setting(
				'mkae_' . $key,
				array(
					'default'           => self::$defaults[ $key ] ?? '',
					'sanitize_callback' => $meta[1],
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				'mkae_' . $key,
				array(
					'label'   => $meta[0],
					'section' => 'mkae_seo',
					'type'    => $meta[2],
				)
			);
		}

		// Footer & Social.
		$wp_customize->add_section(
			'mkae_footer',
			array(
				'title' => __( 'Footer & Social', 'mk-auto-elite' ),
				'panel' => 'mkae_panel',
			)
		);
		$wp_customize->add_setting(
			'mkae_newsletter_action',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'mkae_newsletter_action',
			array(
				'label'       => __( 'Newsletter form action URL', 'mk-auto-elite' ),
				'description' => __( 'Paste your Mailchimp (or other provider) subscribe form action URL. The email field is submitted as "EMAIL". Leave blank to keep the simple fallback form.', 'mk-auto-elite' ),
				'section'     => 'mkae_footer',
				'type'        => 'url',
			)
		);

		$socials = array(
			'facebook'  => __( 'Facebook URL', 'mk-auto-elite' ),
			'instagram' => __( 'Instagram URL', 'mk-auto-elite' ),
			'x_twitter' => __( 'X / Twitter URL', 'mk-auto-elite' ),
			'tiktok'    => __( 'TikTok URL', 'mk-auto-elite' ),
			'youtube'   => __( 'YouTube URL', 'mk-auto-elite' ),
		);
		foreach ( $socials as $key => $label ) {
			$wp_customize->add_setting(
				'mkae_' . $key,
				array(
					'default'           => '',
					'sanitize_callback' => 'esc_url_raw',
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				'mkae_' . $key,
				array(
					'label'   => $label,
					'section' => 'mkae_footer',
					'type'    => 'url',
				)
			);
		}
	}

	/**
	 * Social links that are actually set, as [key => url].
	 *
	 * @return array<string,string>
	 */
	public static function social_links(): array {
		$out = array();
		foreach ( array( 'facebook', 'instagram', 'x_twitter', 'tiktok', 'youtube' ) as $key ) {
			$url = self::get( $key, '' );
			if ( '' !== $url ) {
				$out[ $key ] = $url;
			}
		}
		return $out;
	}

	/**
	 * Sanitize a yes/no value.
	 */
	public function sanitize_yes_no( $value ): string {
		return ( 'yes' === $value ) ? 'yes' : 'no';
	}
}
