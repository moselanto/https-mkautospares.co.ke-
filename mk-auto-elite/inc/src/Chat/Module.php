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

		wp_localize_script(
			'mkae-chat',
			'MKAEChat',
			array(
				'enabled'  => true,
				'aiReady'  => self::ai_ready(),
				'whatsapp' => $whatsapp,
				'intro'    => Customizer::get( 'chat_intro', __( 'Hi! I can help you find the right part, check fitment or get a price. What are you looking for?', 'mk-auto-elite' ) ),
				'brand'    => get_bloginfo( 'name' ),
				'i18n'     => array(
					'title'       => __( 'Chat with us', 'mk-auto-elite' ),
					'open'        => __( 'Chat with us', 'mk-auto-elite' ),
					'close'       => __( 'Close chat', 'mk-auto-elite' ),
					'placeholder' => __( 'Type your message...', 'mk-auto-elite' ),
					'send'        => __( 'Send', 'mk-auto-elite' ),
					'whatsapp'    => __( 'Continue on WhatsApp', 'mk-auto-elite' ),
					'thinking'    => __( 'Typing...', 'mk-auto-elite' ),
					'error'       => __( 'Sorry, I could not respond just now. Please continue on WhatsApp.', 'mk-auto-elite' ),
				),
			)
		);
	}

	/**
	 * Render the floating widget shell. chat.js fills in the conversation.
	 */
	public function render_widget(): void {
		if ( ! self::enabled() ) {
			return;
		}
		?>
		<div class="mkae-chat" data-mkae-chat hidden>
			<button type="button" class="mkae-chat__launcher" data-mkae-chat-toggle aria-expanded="false" aria-label="<?php esc_attr_e( 'Chat with us', 'mk-auto-elite' ); ?>">
				<span class="mkae-chat__launcher-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.9-.9L3 21l1.9-5.6A8.5 8.5 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5z"></path></svg>
				</span>
				<span class="mkae-chat__launcher-label"><?php esc_html_e( 'Chat', 'mk-auto-elite' ); ?></span>
			</button>

			<section class="mkae-chat__panel" data-mkae-chat-panel role="dialog" aria-label="<?php esc_attr_e( 'Chat with us', 'mk-auto-elite' ); ?>" hidden>
				<header class="mkae-chat__head">
					<span class="mkae-chat__head-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
					<button type="button" class="mkae-chat__x" data-mkae-chat-toggle aria-label="<?php esc_attr_e( 'Close chat', 'mk-auto-elite' ); ?>">&times;</button>
				</header>
				<div class="mkae-chat__log" data-mkae-chat-log aria-live="polite"></div>
				<div class="mkae-chat__handoff">
					<a href="#" class="mkae-chat__wa" data-mkae-chat-whatsapp target="_blank" rel="noopener">
						<?php esc_html_e( 'Continue on WhatsApp', 'mk-auto-elite' ); ?>
					</a>
				</div>
				<form class="mkae-chat__form" data-mkae-chat-form>
					<input type="text" class="mkae-chat__input" data-mkae-chat-input placeholder="<?php esc_attr_e( 'Type your message...', 'mk-auto-elite' ); ?>" autocomplete="off">
					<button type="submit" class="mkae-chat__send" aria-label="<?php esc_attr_e( 'Send', 'mk-auto-elite' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
					</button>
				</form>
			</section>
		</div>
		<?php
	}
}
