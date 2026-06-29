<?php
/**
 * UddoktaPay Payment Gateway Local Gateway Class.
 *
 * This file contains the Local Gateway class for the UddoktaPay Payment Gateway,
 * handling Bangladeshi payment transactions through multiple payment providers.
 *
 * @package UddoktaPayGateway
 * @since 1.0.0
 */

declare (strict_types = 1);

namespace UddoktaPay\UddoktaPayGateway;

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit( 'Direct access is not allowed.' );

/**
 * Local Gateway Class
 *
 * Provides Bangladeshi payment options through UddoktaPay's local payment processors.
 *
 * @since 1.0.0
 */
class LocalGateway extends AbstractGateway {

	/**
	 * Setup general properties for the gateway.
	 *
	 * @return void
	 */
	protected function setup_properties() {
		$this->id           = 'uddoktapay';
		$this->icon         = (string) apply_filters( 'woocommerce_uddoktapay_icon', UDDOKTAPAY_URL . 'assets/images/uddoktapay.png' );
		$this->has_fields   = false;
		$this->method_title = __( 'UddoktaPay', 'uddoktapay-gateway' );

		$this->method_description = sprintf(
			/* translators: %s: URL for UddoktaPay website */
			'%s<br/><a href="%s" target="_blank">%s</a>',
			__( 'Accept Bangladeshi payments via multiple gateways including bKash, Nagad, Rocket and more.', 'uddoktapay-gateway' ),
			esc_url( 'https://uddoktapay.com' ),
			__( 'Sign up for UddoktaPay account', 'uddoktapay-gateway' )
		);
		$this->webhook_url = (string) add_query_arg( 'wc-api', $this->id, home_url( '/' ) );
		$this->supports    = array(
			'products',
		);
	}

	/**
	 * Base currency the gateway charges in.
	 *
	 * @return string
	 */
	protected function get_base_currency() {
		return 'BDT';
	}

	/**
	 * Order meta key used to store the payment data.
	 *
	 * @return string
	 */
	protected function get_payment_data_meta_key() {
		return 'uddoktapay_payment_data';
	}

	/**
	 * Default checkout title.
	 *
	 * @return string
	 */
	protected function get_default_title() {
		return __( 'Bangladeshi Payment', 'uddoktapay-gateway' );
	}

	/**
	 * Default checkout description.
	 *
	 * @return string
	 */
	protected function get_default_description() {
		return __( 'Pay securely via Bangladeshi payment methods.', 'uddoktapay-gateway' );
	}

	/**
	 * Enable/disable field label.
	 *
	 * @return string
	 */
	protected function get_enable_label() {
		return __( 'Enable UddoktaPay', 'uddoktapay-gateway' );
	}

	/**
	 * Create a payment through the API for the given order.
	 *
	 * @param \WC_Order $order The order object.
	 * @param array     $metadata Payment metadata.
	 * @return object
	 */
	protected function create_payment_for_order( $order, $metadata ) {
		return $this->get_api()->create_payment(
			$order->get_total(),
			$order->get_currency(),
			$order->get_billing_first_name(),
			$order->get_billing_email(),
			$order->get_billing_phone(),
			$metadata,
			$this->webhook_url,
			$order->get_cancel_order_url_raw(),
			$this->webhook_url,
			$this->exchange_rate
		);
	}
}
