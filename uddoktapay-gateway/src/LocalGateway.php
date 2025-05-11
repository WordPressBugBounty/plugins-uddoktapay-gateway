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

declare(strict_types=1);

namespace UddoktaPay\UddoktaPayGateway;

use UddoktaPay\UddoktaPayGateway\Enums\OrderStatus;

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit( 'Direct access is not allowed.' );

/**
 * Local Gateway Class
 *
 * Extends the WooCommerce Payment Gateway class to provide Bangladeshi
 * payment options through UddoktaPay's local payment processors.
 *
 * @since 1.0.0
 */
class LocalGateway extends \WC_Payment_Gateway {

	/**
	 * API Handler instance
	 *
	 * @var APIHandler|null
	 */
	protected $api = null;

	/**
	 * Debug mode
	 *
	 * @var bool
	 */
	protected $debug = false;

	/**
	 * API URL
	 *
	 * @var string
	 */
	protected $api_url = '';

	/**
	 * API Key
	 *
	 * @var string
	 */
	protected $api_key = '';

	/**
	 * Webhook URL
	 *
	 * @var string
	 */
	protected $webhook_url;

	/**
	 * Exchange rate
	 *
	 * @var float
	 */
	protected $exchange_rate = 120.0;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings with type coercion.
		$this->title         = (string) $this->get_option( 'title', __( 'Mobile Banking', 'uddoktapay-gateway' ) );
		$this->description   = (string) $this->get_option( 'description', __( 'Pay securely via Bangladeshi payment methods.', 'uddoktapay-gateway' ) );
		$this->api_key       = (string) $this->get_option( 'api_key', '' );
		$this->api_url       = (string) $this->get_option( 'api_url', '' );
		$this->exchange_rate = (float) $this->get_option( 'exchange_rate', '120' );
		$this->debug         = 'yes' === $this->get_option( 'debug' );

		// Actions.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);

		add_action(
			'woocommerce_api_' . $this->id,
			array( $this, 'handle_webhook' )
		);

		add_action(
			'woocommerce_admin_order_data_after_billing_address',
			array( $this, 'display_transaction_data' )
		);

		// Validation.
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
	}

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

		// Translators: %s: URL for UddoktaPay website.
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
	 * Check if gateway is valid for use.
	 *
	 * @return bool
	 */
	protected function is_valid_for_use() {
		if ( empty( $this->api_key ) || empty( $this->api_url ) ) {
			$this->add_error( __( 'UddoktaPay requires API Key and API URL to be configured.', 'uddoktapay-gateway' ) );

			return false;
		}

		return true;
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$currency = get_woocommerce_currency();

		$base_fields = array(
			'enabled'                 => array(
				'title'   => __( 'Enable/Disable', 'uddoktapay-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable UddoktaPay', 'uddoktapay-gateway' ),
				'default' => 'no',
			),
			'show_icon'               => array(
				'title'   => __( 'Show Icon', 'uddoktapay-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display icons on checkout page.', 'uddoktapay-gateway' ),
				'default' => 'no',
			),
			'title'                   => array(
				'title'       => __( 'Title', 'uddoktapay-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'uddoktapay-gateway' ),
				'default'     => __( 'Bangladeshi Payment', 'uddoktapay-gateway' ),
				'desc_tip'    => true,
			),
			'description'             => array(
				'title'       => __( 'Description', 'uddoktapay-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'uddoktapay-gateway' ),
				'default'     => __( 'Pay securely via Bangladeshi payment methods.', 'uddoktapay-gateway' ),
				'desc_tip'    => true,
			),
			'api_key'                 => array(
				'title'       => __( 'API Key', 'uddoktapay-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API key from UddoktaPay Panel → Brand Settings.', 'uddoktapay-gateway' ),
			),
			'api_url'                 => array(
				'title'       => __( 'API URL', 'uddoktapay-gateway' ),
				'type'        => 'url',
				'description' => __( 'Get your API URL from UddoktaPay Panel → Brand Settings.', 'uddoktapay-gateway' ),
			),
			'physical_product_status' => array(
				'title'       => __( 'Physical Product Status', 'uddoktapay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select status for physical product orders after successful payment.', 'uddoktapay-gateway' ),
				'default'     => OrderStatus::PROCESSING,
				'options'     => array(
					OrderStatus::ON_HOLD    => __( 'On Hold', 'uddoktapay-gateway' ),
					OrderStatus::PROCESSING => __( 'Processing', 'uddoktapay-gateway' ),
					OrderStatus::COMPLETED  => __( 'Completed', 'uddoktapay-gateway' ),
				),
			),
			'digital_product_status'  => array(
				'title'       => __( 'Digital Product Status', 'uddoktapay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select status for digital/downloadable product orders after successful payment.', 'uddoktapay-gateway' ),
				'default'     => OrderStatus::COMPLETED,
				'options'     => array(
					OrderStatus::ON_HOLD    => __( 'On Hold', 'uddoktapay-gateway' ),
					OrderStatus::PROCESSING => __( 'Processing', 'uddoktapay-gateway' ),
					OrderStatus::COMPLETED  => __( 'Completed', 'uddoktapay-gateway' ),
				),
			),
		);

		if ( 'BDT' !== $currency ) {
			// Translators: %s: Currency code.
			$base_fields['exchange_rate'] = array(
				'title'             => sprintf(
					/* translators: %s: Currency code */
					__( '%s to BDT Exchange Rate', 'uddoktapay-gateway' ),
					$currency
				),
				'type'              => 'text',
				'desc_tip'          => true,
				'description'       => __( 'This rate will be applied to convert the total amount to BDT.', 'uddoktapay-gateway' ),
				'default'           => '0',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			);
		}

		// Translators: %s: Log file path.
		$base_fields['debug'] = array(
			'title'       => __( 'Debug Log', 'uddoktapay-gateway' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable logging', 'uddoktapay-gateway' ),
			'default'     => 'no',
			'description' => sprintf(
				/* translators: %s: Log file path */
				__( 'Log gateway events inside %s', 'uddoktapay-gateway' ),
				'<code>' . \WC_Log_Handler_File::get_log_file_path( 'uddoktapay' ) . '</code>'
			),
		);

		$this->form_fields = $base_fields;
	}

	/**
	 * Get the payment gateway icon for checkout.
	 *
	 * @return string
	 */
	public function get_icon() {
		if ( 'no' === $this->get_option( 'show_icon', 'no' ) ) {
			return '';
		}

		$icon = $this->icon ? '<img src="' . esc_url( \WC_HTTPS::force_https_url( $this->icon ) ) . '" alt="' . esc_attr( $this->get_title() ) . '" />' : '';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Get the API Handler instance.
	 *
	 * @return APIHandler
	 */
	protected function get_api() {
		if ( null === $this->api ) {
			APIHandler::$debug   = $this->debug;
			APIHandler::$api_url = $this->api_url;
			APIHandler::$api_key = $this->api_key;

			$this->api = new APIHandler();
		}

		return $this->api;
	}

	/**
	 * Process Payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 * @throws \Exception When payment processing fails.
	 */
	public function process_payment( $order_id ) {
		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new \Exception( esc_html__( 'Invalid order', 'uddoktapay-gateway' ) );
			}

			$metadata = array(
				'order_id'     => $order->get_id(),
				'redirect_url' => $this->get_return_url( $order ),
			);

			$result = $this->get_api()->create_payment(
				$order->get_total(),
				$order->get_currency(),
				$order->get_billing_first_name(),
				$order->get_billing_email(),
				$metadata,
				$this->webhook_url,
				$order->get_cancel_order_url_raw(),
				$this->webhook_url,
				$this->exchange_rate
			);

			if ( empty( $result->payment_url ) ) {
				throw new \Exception( $result->message ?? esc_html__( 'Payment URL not received', 'uddoktapay-gateway' ) );
			}

			// Mark as pending payment.
			$order->update_status(
				OrderStatus::PENDING,
				__( 'Awaiting UddoktaPay payment', 'uddoktapay-gateway' )
			);

			// Empty cart.
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $result->payment_url,
			);

		} catch ( \Exception ) {
			throw new \Exception( esc_html__( 'Something went wrong', 'uddoktapay-gateway' ) );
		}
	}

	/**
	 * Handle Webhook.
	 *
	 * @return void
	 */
	public function handle_webhook() {
		try {
			// Verify nonce is not applicable here as this is an external API callback.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$invoice_id = isset( $_GET['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice_id'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( ! empty( $invoice_id ) ) {
				$this->handle_redirect_verification( $invoice_id );
			} else {
				$this->handle_webhook_notification();
			}
		} catch ( \Exception $e ) {
			wp_die( esc_html( __( 'Something went wrong', 'uddoktapay-gateway' ) ), 'UddoktaPay Webhook Error', array( 'response' => 500 ) );
		}
	}

	/**
	 * Handle redirect verification.
	 *
	 * @param string $invoice_id The invoice ID to verify.
	 * @return void
	 * @throws \Exception If verification fails.
	 */
	protected function handle_redirect_verification( $invoice_id ) {
		$result = $this->get_api()->verify_payment( $invoice_id );

		if ( ! isset( $result->metadata->order_id ) ) {
			throw new \Exception( esc_html__( 'Invalid order data received', 'uddoktapay-gateway' ) );
		}

		$order = wc_get_order( $result->metadata->order_id );
		if ( ! $order ) {
			throw new \Exception( esc_html__( 'Order not found', 'uddoktapay-gateway' ) );
		}

		$this->process_order_status( $order, $result );

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $result->metadata->redirect_url );
		exit;
	}

	/**
	 * Handle webhook notification.
	 *
	 * @return void
	 * @throws \Exception If webhook validation fails.
	 */
	protected function handle_webhook_notification() {
		$payload = file_get_contents( 'php://input' );

		if ( empty( $payload ) ) {
			throw new \Exception( esc_html__( 'Empty webhook payload', 'uddoktapay-gateway' ) );
		}

		if ( ! $this->validate_webhook_signature() ) {
			throw new \Exception( esc_html__( 'Invalid webhook signature', 'uddoktapay-gateway' ) );
		}

		$data = json_decode( $payload );

		if ( ! isset( $data->metadata->order_id ) ) {
			throw new \Exception( esc_html__( 'Order ID not found in webhook data', 'uddoktapay-gateway' ) );
		}

		$order = wc_get_order( $data->metadata->order_id );
		if ( ! $order ) {
			throw new \Exception( esc_html__( 'Order not found', 'uddoktapay-gateway' ) );
		}

		$this->process_order_status( $order, $data );
	}

	/**
	 * Validate webhook signature.
	 *
	 * @return bool
	 */
	protected function validate_webhook_signature() {
		$provided_key = isset( $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] ) ) : '';

		return hash_equals( $this->api_key, $provided_key );
	}

	/**
	 * Process order status.
	 *
	 * @param \WC_Order $order The order object.
	 * @param object    $data  The payment data.
	 * @return void
	 */
	protected function process_order_status( $order, $data ) {
		if ( OrderStatus::COMPLETED === $order->get_status() ) {
			return;
		}

		$order->update_meta_data( 'uddoktapay_payment_data', $data );

		if ( 'COMPLETED' === $data->status ) {
			$this->handle_completed_payment( $order, $data );
		} else {
			$order->update_status(
				OrderStatus::ON_HOLD,
				__( 'Payment is on hold. Please check manually.', 'uddoktapay-gateway' )
			);
		}

		$order->save();
	}

	/**
	 * Handle completed payment.
	 *
	 * @param \WC_Order $order The order object.
	 * @param object    $data  The payment data.
	 * @return void
	 */
	protected function handle_completed_payment( $order, $data ) {
		$status = $this->is_order_virtual( $order ) ? $this->get_option( 'digital_product_status', OrderStatus::COMPLETED ) : $this->get_option( 'physical_product_status', OrderStatus::PROCESSING );

		// Translators: %1$s: Payment method, %2$s: Amount, %3$s: Transaction ID.
		$note = sprintf(
			/* translators: %1$s: Payment method, %2$s: Amount, %3$s: Transaction ID */
			__( 'Payment via %1$s. Amount: %2$s, Transaction ID: %3$s', 'uddoktapay-gateway' ),
			$data->payment_method,
			$data->amount,
			$data->transaction_id
		);

		$order->payment_complete( $data->transaction_id );
		$order->update_status( $status, $note );
	}

	/**
	 * Check if order is virtual.
	 *
	 * @param \WC_Order $order The order object.
	 * @return bool
	 */
	protected function is_order_virtual( $order ) {
		$virtual = false;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && ( $product->is_virtual() || $product->is_downloadable() ) ) {
				$virtual = true;
				break;
			}
		}

		return $virtual;
	}

	/**
	 * Display transaction data in admin order page.
	 *
	 * @param \WC_Order $order The order object.
	 * @return void
	 */
	public function display_transaction_data( $order ) {
		// Check if we've already displayed this data.
		if ( defined( 'UDDOKTAPAY_ADMIN_DATA_DISPLAYED' ) ) {
			return;
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		$payment_data = $order->get_meta( 'uddoktapay_payment_data' );
		if ( empty( $payment_data ) ) {
			return;
		}

		$this->display_payment_info_html( $payment_data );
		// Mark as displayed.
		define( 'UDDOKTAPAY_ADMIN_DATA_DISPLAYED', true );
	}

	/**
	 * Display payment information HTML.
	 *
	 * @param object $data Payment data.
	 * @return void
	 */
	protected function display_payment_info_html( $data ) {
		$payment_method = esc_html( ucfirst( $data->payment_method ?? '' ) );
		$sender_number  = esc_html( $data->sender_number ?? '' );
		$transaction_id = esc_html( $data->transaction_id ?? '' );
		$amount         = esc_html( $data->amount ?? '' );

		echo wp_kses_post(
			'<div class="form-field form-field-wide uddoktapay-admin-data">
            <table class="wp-list-table widefat striped posts">
                <tbody>
                    <tr>
                        <th>
                            <strong>' . esc_html__( 'Payment Method', 'uddoktapay-gateway' ) . '</strong>
                        </th>
                        <td>' . $payment_method . '</td>
                    </tr>
                    <tr>
                        <th>
                            <strong>' . esc_html__( 'Sender Number', 'uddoktapay-gateway' ) . '</strong>
                        </th>
                        <td>' . $sender_number . '</td>
                    </tr>
                    <tr>
                        <th>
                            <strong>' . esc_html__( 'Transaction ID', 'uddoktapay-gateway' ) . '</strong>
                        </th>
                        <td>' . $transaction_id . '</td>
                    </tr>
                    <tr>
                        <th>
                            <strong>' . esc_html__( 'Amount', 'uddoktapay-gateway' ) . '</strong>
                        </th>
                        <td>' . $amount . '</td>
                    </tr>
                </tbody>
            </table>
        </div>'
		);
	}
}
