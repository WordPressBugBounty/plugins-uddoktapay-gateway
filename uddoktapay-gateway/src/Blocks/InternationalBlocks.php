<?php

declare (strict_types = 1);

namespace UddoktaPay\UddoktaPayGateway\Blocks;

// If this file is called directly, abort!!!
defined('ABSPATH') || die('Direct access is not allowed.');

class InternationalBlocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    /**
     * Payment method name/id/slug
     *
     * @var string
     */
    protected $name = 'uddoktapayinternational';

    /**
     * Settings Array
     *
     * @var array
     */
    private $gateway_settings = [];

    /**
     * Initialize payment method
     *
     * @return void
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_uddoktapayinternational_settings', []);
        $this->gateway_settings = [
            'title' => $this->settings['title'] ?? __('International Payment', 'uddoktapay-gateway'),
            'description' => $this->settings['description'] ?? __('Pay with PayPal, Stripe, Paddle, Perfect Money', 'uddoktapay-gateway'),
            'supports' => [
                'products',
            ],
        ];
    }

    /**
     * Check if payment method is active
     *
     * @return boolean
     */
    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Get payment method script handles
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'uddoktapay-international-blocks-integration',
            UDDOKTAPAY_URL  . 'assets/js/blocks-integration-international.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'],
            UDDOKTAPAY_VERSION,
            true
        );
        return ['uddoktapay-international-blocks-integration'];
    }

    /**
     * Get payment method data
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return $this->gateway_settings;
    }
}
