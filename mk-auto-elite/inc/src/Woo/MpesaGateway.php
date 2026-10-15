<?php
/**
 * Lipa na M-Pesa (Paybill) offline payment gateway.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer pays to Paybill; the order is placed On hold until the payment is
 * confirmed by the shop.
 */
class MpesaGateway extends \WC_Payment_Gateway {

	/**
	 * Setup.
	 */
	public function __construct() {
		$this->id                 = 'mkae_mpesa';
		$this->has_fields         = false;
		$this->method_title       = __( 'Lipa na M-Pesa (Paybill)', 'mk-auto-elite' );
		$this->method_description = __( 'Customers pay to your M-Pesa Paybill. Orders are placed On hold until you confirm the payment.', 'mk-auto-elite' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->paybill      = $this->get_option( 'paybill' );
		$this->account      = $this->get_option( 'account' );
		$this->enabled      = $this->get_option( 'enabled' );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email' ), 10, 3 );
	}

	/** @var string */
	public $paybill = '';
	/** @var string */
	public $account = '';

	/**
	 * Settings.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable', 'mk-auto-elite' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Lipa na M-Pesa (Paybill)', 'mk-auto-elite' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'mk-auto-elite' ),
				'type'    => 'text',
				'default' => __( 'M-Pesa (Paybill 529914)', 'mk-auto-elite' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'mk-auto-elite' ),
				'type'    => 'textarea',
				'default' => __( 'Pay with Lipa na M-Pesa after placing your order. We will confirm your payment and dispatch.', 'mk-auto-elite' ),
			),
			'paybill'     => array(
				'title'   => __( 'Paybill (business number)', 'mk-auto-elite' ),
				'type'    => 'text',
				'default' => '529914',
			),
			'account'     => array(
				'title'   => __( 'Account number', 'mk-auto-elite' ),
				'type'    => 'text',
				'default' => '4268',
			),
		);
	}

	/**
	 * Step-by-step instructions.
	 *
	 * @param string $total Formatted total (plain text).
	 */
	public function steps( string $total = '' ): string {
		$out  = '<ol class="mkae-mpesa-steps">';
		$out .= '<li>' . esc_html__( 'Go to M-Pesa, then Lipa na M-Pesa, then Pay Bill.', 'mk-auto-elite' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Business number:', 'mk-auto-elite' ) . ' <strong>' . esc_html( $this->paybill ) . '</strong></li>';
		$out .= '<li>' . esc_html__( 'Account number:', 'mk-auto-elite' ) . ' <strong>' . esc_html( $this->account ) . '</strong></li>';
		$out .= '<li>' . ( $total ? esc_html__( 'Amount:', 'mk-auto-elite' ) . ' <strong>' . esc_html( $total ) . '</strong>' : esc_html__( 'Enter your order total.', 'mk-auto-elite' ) ) . '</li>';
		$out .= '<li>' . esc_html__( 'Send us the M-Pesa confirmation message on WhatsApp with your order number.', 'mk-auto-elite' ) . '</li>';
		$out .= '</ol>';
		return $out;
	}

	/**
	 * Checkout description.
	 */
	public function payment_fields(): void {
		echo '<p>' . esc_html( $this->description ) . '</p>' . $this->steps(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Place the order On hold.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		$order->update_status( 'on-hold', __( 'Awaiting M-Pesa Paybill payment.', 'mk-auto-elite' ) );
		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Plain total for instructions.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function plain_total( $order ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Thank-you page instructions.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$wa  = preg_replace( '/\D+/', '', (string) \MKAutoElite\Customizer::get( 'whatsapp' ) );
		$msg = sprintf( 'Hello MK Auto Spares, I have paid for order #%s via M-Pesa. Code: ', $order->get_order_number() );
		echo '<section class="mkae-mpesa-box"><h2>' . esc_html__( 'Complete your M-Pesa payment', 'mk-auto-elite' ) . '</h2>';
		echo $this->steps( $this->plain_total( $order ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p><a class="mkae-btn mkae-btn--whatsapp" target="_blank" rel="nofollow noopener" href="' . esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $msg ) ) . '">' . esc_html__( 'Send M-Pesa confirmation on WhatsApp', 'mk-auto-elite' ) . '</a></p></section>';
	}

	/**
	 * Email instructions.
	 *
	 * @param \WC_Order $order Order.
	 * @param bool      $sent_to_admin Admin email.
	 * @param bool      $plain_text Plain.
	 */
	public function email( $order, $sent_to_admin, $plain_text = false ): void {
		if ( $sent_to_admin || ! $order instanceof \WC_Order || $this->id !== $order->get_payment_method() || ! $order->has_status( 'on-hold' ) ) {
			return;
		}
		if ( $plain_text ) {
			echo esc_html( sprintf( "Pay via Lipa na M-Pesa: Paybill %s, Account %s, Amount %s. Then send us the M-Pesa message on WhatsApp.\n\n", $this->paybill, $this->account, $this->plain_total( $order ) ) );
			return;
		}
		echo '<h2>' . esc_html__( 'How to pay with M-Pesa', 'mk-auto-elite' ) . '</h2>' . $this->steps( $this->plain_total( $order ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
