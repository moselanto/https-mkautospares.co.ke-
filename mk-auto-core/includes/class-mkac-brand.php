<?php
/**
 * Brand values shared by every MK Auto Core screen.
 *
 * Single source of truth: the MK Auto Elite Customizer. Nothing editable is
 * hard-coded beyond fallbacks that match the theme's own defaults, so changing
 * the phone number under Appearance > Customize > MK Auto Elite also changes it
 * on the sign-in screen.
 *
 * @package MK_Auto_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKAC_Brand {

	/**
	 * Fallbacks, kept identical to MKAutoElite\Customizer::$defaults.
	 *
	 * @var array<string,string>
	 */
	private const DEFAULTS = array(
		'name'     => 'MK Auto Spares',
		'phone'    => '+254 702343302',
		'whatsapp' => '254702343302',
		'email'    => 'info@mkautospares.co.ke',
		'accent'   => '#CA1D24',
	);

	/**
	 * Read one brand value.
	 *
	 * @param string $key Brand key (name, phone, whatsapp, email, accent).
	 */
	public static function get( string $key ): string {
		$default = self::DEFAULTS[ $key ] ?? '';
		if ( 'name' === $key ) {
			$value = get_bloginfo( 'name' );
		} else {
			$value = get_theme_mod( 'mkae_' . $key, $default );
		}
		$value = is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : $default;
		return (string) apply_filters( 'mkac_brand_' . $key, $value );
	}

	/** Accent colour, validated so a bad theme mod cannot break the CSS. */
	public static function accent(): string {
		$hex = sanitize_hex_color( self::get( 'accent' ) );
		return $hex ? $hex : self::DEFAULTS['accent'];
	}

	/** wa.me link with an optional pre-filled message. */
	public static function whatsapp_url( string $text = '' ): string {
		$digits = preg_replace( '/\D+/', '', self::get( 'whatsapp' ) );
		if ( '' === $digits ) {
			return '';
		}
		$url = 'https://wa.me/' . $digits;
		if ( '' !== $text ) {
			$url .= '?text=' . rawurlencode( $text );
		}
		return $url;
	}

	/** tel: link for the phone number. */
	public static function tel_url(): string {
		$digits = preg_replace( '/[^\d+]/', '', self::get( 'phone' ) );
		return '' === $digits ? '' : 'tel:' . $digits;
	}

	/** Bare domain for the footer line, e.g. mkautospares.co.ke. */
	public static function domain(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Logo URL. Order: filter > Site Logo (if it is set) > bundled MK logo.
	 * The bundled logo has dark lettering on a transparent background, so it is
	 * only ever placed on the light form side of the sign-in screen.
	 */
	public static function logo_url(): string {
		$url    = MKAC_URL . 'assets/images/mk-logo.png';
		$custom = (int) get_theme_mod( 'custom_logo' );
		if ( $custom ) {
			$src = wp_get_attachment_image_url( $custom, 'medium' );
			if ( $src ) {
				$url = $src;
			}
		}
		return (string) apply_filters( 'mkac_logo_url', $url );
	}
}
