<?php
/**
 * UddoktaPay Gateway Integration Block for WooCommerce
 *
 * This file defines the payment method integration for UddoktaPay within WooCommerce.
 * It includes methods for initializing, checking if the payment method is active,
 * and getting payment method data.
 *
 * @package UddoktaPay\UddoktaPayGateway\Blocks
 */

declare(strict_types=1);

namespace UddoktaPay\UddoktaPayGateway\Blocks;

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit( 'Direct access is not allowed.' );

/**
 * Class LocalBlocks
 *
 * This class integrates UddoktaPay as a payment method with WooCommerce.
 * It defines methods to initialize the payment method, check if it's active,
 * and retrieve payment method data.
 *
 * @package UddoktaPay\UddoktaPayGateway\Blocks
 */
class LocalBlocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug
	 *
	 * @var string
	 */
	protected $name = 'uddoktapay';

	/**
	 * Settings Array
	 *
	 * @var array
	 */
	private $gateway_settings = array();

	/**
	 * Initialize payment method
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings         = get_option( 'woocommerce_uddoktapay_settings', array() );
		$this->gateway_settings = array(
			'title'       => $this->settings['title'] ?? __( 'Mobile Banking', 'uddoktapay-gateway' ),
			'description' => $this->settings['description'] ?? __( 'Pay with bKash, Rocket, Nagad, Upay', 'uddoktapay-gateway' ),
			'icon'        => UDDOKTAPAY_URL . 'assets/images/uddoktapay.png',
			'show_icon'   => 'yes' === $this->settings['show_icon'],
			'supports'    => array(
				'products',
			),
		);
	}

	/**
	 * Check if payment method is active
	 *
	 * @return bool
	 */
	public function is_active() {
		return 'yes' === $this->settings['enabled'] && ! empty( $this->settings['enabled'] );
	}

	/**
	 * Get payment method data
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return $this->gateway_settings;
	}
}
