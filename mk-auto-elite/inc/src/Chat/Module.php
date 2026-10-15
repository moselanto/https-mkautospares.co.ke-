<?php
/**
 * AI chat assistant (Google Gemini) with WhatsApp hand-off.
 *
 * Renders a floating chat widget and localizes its config. The actual model
 * call is proxied server-side by Ajax\Handlers::chat() so the Gemini API key
 * (stored in the Customizer) is never exposed to the browser. When AI is not
 * configured, the widget still works as a one-tap WhatsApp hand-off.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Chat;

use MKAutoElite\Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chat widget UI + configuration. All model access is server-side.
 */
class Module {

	/**
	 * Hook in.
	 */
	public function __construct() {
		// Localize onto the handle Assets enqueues (priority 20, after enqueue).
		add_action( 'wp_enqueue_scripts', array( $this, 'localize' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
		add_action( 'customize_register', array( $this, 'customize' ), 20 );
	}

	/**
	 * Register the "AI Chat Assistant" Customizer section under the MK Auto
	 * Elite panel. Priority 20 ensures the panel already exists.
	 *
	 * @param \WP_Customize_Manager $wp_customize Manager.
	 */
	public function customize( $wp_customize ): void {
		$wp_customize->add_section(
			'mkae_chat',
			array(
				'title'       => __( 'AI Chat Assistant', 'mk-auto-elite' ),
				'description' => __( 'A free chatbot powered by Google Gemini, with a WhatsApp hand-off. Create a free API key at aistudio.google.com/apikey (no credit card needed) and paste it below. Leave the key blank to run the widget as a WhatsApp hand-off only.', 'mk-auto-elite' ),
				'panel'       => 'mkae_panel',
			)
		);

		$wp_customize->add_setting(
			'mkae_chat_enabled',
			array(
				'default'           => 'yes',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		$wp_customize->add_control(
			'mkae_chat_enabled',
			array(
				'label'   => __( 'Show the chat widget', 'mk-auto-elite' ),
				'section' => 'mkae_chat',
				'type'    => 'select',
				'choices' => array(
					'yes' => __( 'Yes', 'mk-auto-elite' ),
					'no'  => __( 'No', 'mk-auto-elite' ),
				),
			)
		);

		$wp_customize->add_setting(
			'mkae_gemini_api_key',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'mkae_gemini_api_key',
			array(
				'label'       => __( 'Google Gemini API key (free)', 'mk-auto-elite' ),
				'description' => __( 'From aistudio.google.com/apikey. Stored on your server; never shown to visitors.', 'mk-auto-elite' ),
				'section'     => 'mkae_chat',
				'type'        => 'text',
			)
		);

		$wp_customize->add_setting(
			'mkae_chat_intro',
			array(
				'default'           => __( 'Hi! I can help you find the right part, check fitment or get a price. What are you looking for?', 'mk-auto-elite' ),
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'mkae_chat_intro',
			array(
				'label'   => __( 'Welcome message', 'mk-auto-elite' ),
				'section' => 'mkae_chat',
				'type'    => 'textarea',
			)
		);
	}

	/**
	 * Sanitize a yes/no value.
	 *
	 * @param string $value Raw value.
	 */
	public function sanitize_yes_no( $value ): string {
		return 'no' === $value ? 'no' : 'yes';
	}

	/**
	 * Whether the chat widget is enabled.
	 */
	public static function enabled(): bool {
		return 'yes' === Customizer::get( 'chat_enabled', 'yes' );
	}

	/**
	 * Whether a Gemini API key is configured (server-side check only).
	 */
	public static function ai_ready(): bool {
		return '' !== trim( Customizer::get( 'gemini_api_key', '' ) );
	}

	/**
	 * Pass config to chat.js. The key itself is never sent to the browser.
	 */
	public function localize(): void {
		if ( ! self::enabled() ) {
			return;
		}
		if ( ! wp_script_is( 'mkae-chat', 'enqueued' ) ) {
			return;
		}
		$whatsapp = preg_replace( '/[^0-9]/', '', Customizer::get( 'whatsapp', '254702343302' ) );
		$context  = '';
		if ( function_exists( 'is_product' ) && is_product() ) {
			$context = wp_strip_all_tags( get_the_title() );
		}

		wp_localize_script(
			'mkae-chat',
			'MKAEChat',
			array(
				'enabled'  => true,
				'aiReady'  => self::ai_ready(),
				'whatsapp' => $whatsapp,
				'intro'    => Customizer::get( 'chat_intro', __( 'Hi! I can help you find the right part, check fitment or get a price. What are you looking for?', 'mk-auto-elite' ) ),
				'brand'    => 'MK Auto Spares',
				'context'  => $context,
				'pageUrl'  => is_singular() ? get_permalink() : home_url( add_query_arg( array() ) ),
				// Business hours in Africa/Nairobi: Monday (1) to Saturday (6), 08:00-18:00.
				'schedule' => apply_filters(
					'mkae_chat_schedule',
					array(
						'days'  => array( 1, 2, 3, 4, 5, 6 ),
						'open'  => 8,
						'close' => 18,
						'tz'    => 'Africa/Nairobi',
					)
				),
				'i18n'     => array(
					'thinking' => __( 'Typing...', 'mk-auto-elite' ),
					'error'    => __( 'Sorry, I could not respond just now. Please continue on WhatsApp.', 'mk-auto-elite' ),
				),
			)
		);
	}

	/**
	 * Quick topics shown in the widget: label => message sent to WhatsApp.
	 *
	 * @return array<int,array{label:string,hint:string,msg:string}>
	 */
	private static function topics(): array {
		return apply_filters(
			'mkae_chat_topics',
			array(
				array(
					'label' => __( 'Find a part for my car', 'mk-auto-elite' ),
					'hint'  => __( 'Send make, model, year or chassis', 'mk-auto-elite' ),
					'msg'   => 'Hello MK Auto Spares, I am looking for a part for my car. Vehicle (make, model, year): ',
				),
				array(
					'label' => __( 'Price and availability', 'mk-auto-elite' ),
					'hint'  => __( 'Get a quote in minutes', 'mk-auto-elite' ),
					'msg'   => 'Hello MK Auto Spares, please send me the price and availability for: ',
				),
				array(
					'label' => __( 'Check if a part fits', 'mk-auto-elite' ),
					'hint'  => __( 'We confirm before you pay', 'mk-auto-elite' ),
					'msg'   => 'Hello MK Auto Spares, can you confirm this part fits my vehicle? My chassis/VIN is: ',
				),
				array(
					'label' => __( 'Track my order', 'mk-auto-elite' ),
					'hint'  => __( 'Delivery updates', 'mk-auto-elite' ),
					'msg'   => 'Hello MK Auto Spares, I would like an update on my order. Order ref / name: ',
				),
			)
		);
	}

	/**
	 * Render the floating widget shell. chat.js wires the behaviour.
	 */
	public function render_widget(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$icon = class_exists( '\MKAutoElite\Woo\WaOrder' ) ? \MKAutoElite\Woo\WaOrder::icon( 30 ) : '';
		$logo = get_site_icon_url( 96 );
		?>
		<div class="mkw" data-mkae-chat hidden>
			<div class="mkw__teaser" data-mkw-teaser hidden>
				<button type="button" class="mkw__teaser-x" data-mkw-teaser-close aria-label="<?php esc_attr_e( 'Dismiss', 'mk-auto-elite' ); ?>">&times;</button>
				<button type="button" class="mkw__teaser-body" data-mkae-chat-toggle>
					<strong><?php esc_html_e( 'Need the right part?', 'mk-auto-elite' ); ?></strong>
					<span><?php esc_html_e( 'Chat with a parts specialist on WhatsApp. We reply in minutes.', 'mk-auto-elite' ); ?></span>
				</button>
			</div>

			<button type="button" class="mkw__launcher" data-mkae-chat-toggle aria-expanded="false" aria-controls="mkw-panel" aria-label="<?php esc_attr_e( 'Chat with us on WhatsApp', 'mk-auto-elite' ); ?>">
				<span class="mkw__launcher-ico mkw__launcher-ico--wa"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<span class="mkw__launcher-ico mkw__launcher-ico--x" aria-hidden="true">&times;</span>
				<span class="mkw__badge" data-mkw-badge aria-hidden="true">1</span>
			</button>

			<section class="mkw__panel" id="mkw-panel" data-mkae-chat-panel role="dialog" aria-modal="false" aria-labelledby="mkw-title" hidden>
				<header class="mkw__head">
					<span class="mkw__avatar">
						<?php if ( $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="" width="44" height="44">
						<?php else : ?>
							<span>MK</span>
						<?php endif; ?>
						<i class="mkw__dot" data-mkw-dot></i>
					</span>
					<span class="mkw__head-text">
						<strong id="mkw-title">MK Auto Spares</strong>
						<small data-mkw-status><?php esc_html_e( 'Parts specialists', 'mk-auto-elite' ); ?></small>
					</span>
					<button type="button" class="mkw__x" data-mkae-chat-toggle aria-label="<?php esc_attr_e( 'Close chat', 'mk-auto-elite' ); ?>">&times;</button>
				</header>

				<div class="mkw__body" data-mkae-chat-log aria-live="polite">
					<div class="mkw__msg mkw__msg--bot" data-mkw-intro>
						<p data-mkw-intro-text></p>
						<time data-mkw-time></time>
					</div>
					<div class="mkw__context" data-mkw-context hidden>
						<small><?php esc_html_e( 'You are viewing', 'mk-auto-elite' ); ?></small>
						<span data-mkw-context-name></span>
					</div>
					<div class="mkw__topics" data-mkw-topics>
						<?php foreach ( self::topics() as $t ) : ?>
							<a class="mkw__topic" href="#" data-mkw-topic="<?php echo esc_attr( $t['msg'] ); ?>" target="_blank" rel="nofollow noopener">
								<span><strong><?php echo esc_html( $t['label'] ); ?></strong><small><?php echo esc_html( $t['hint'] ); ?></small></span>
								<i aria-hidden="true">&rsaquo;</i>
							</a>
						<?php endforeach; ?>
					</div>
				</div>

				<a class="mkw__handoff" href="#" data-mkae-chat-whatsapp target="_blank" rel="nofollow noopener" hidden>
					<?php echo class_exists( '\MKAutoElite\Woo\WaOrder' ) ? \MKAutoElite\Woo\WaOrder::icon( 16 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php esc_html_e( 'Continue this chat on WhatsApp', 'mk-auto-elite' ); ?>
				</a>

				<form class="mkw__form" data-mkae-chat-form>
					<label class="screen-reader-text" for="mkw-input"><?php esc_html_e( 'Your message', 'mk-auto-elite' ); ?></label>
					<input type="text" id="mkw-input" class="mkw__input" data-mkae-chat-input placeholder="<?php esc_attr_e( 'Type your message...', 'mk-auto-elite' ); ?>" autocomplete="off" maxlength="500">
					<button type="submit" class="mkw__send" aria-label="<?php esc_attr_e( 'Send', 'mk-auto-elite' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
					</button>
				</form>
				<p class="mkw__foot"><?php esc_html_e( 'Mon-Sat 8am-6pm EAT. Messages outside hours are answered next morning.', 'mk-auto-elite' ); ?></p>
			</section>
		</div>
		<?php
	}
}
