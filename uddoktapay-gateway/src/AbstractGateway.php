<?php
/**
 * UddoktaPay Payment Gateway Abstract Gateway Class.
 *
 * Shared implementation for the local and international UddoktaPay gateways.
 *
 * @package UddoktaPayGateway
 * @since 2.6.4
 */

declare (strict_types = 1);

namespace UddoktaPay\UddoktaPayGateway;

use UddoktaPay\UddoktaPayGateway\Enums\OrderStatus;

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit( 'Direct access is not allowed.' );

/**
 * Abstract Gateway Class
 *
 * @since 2.6.4
 */
abstract class AbstractGateway extends \WC_Payment_Gateway {

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
	 * Pending payment redirect URL
	 *
	 * @var string
	 */
	protected $pending_payment_redirect_url = '';

	/**
	 * Base currency the gateway charges in.
	 *
	 * @return string
	 */
	abstract protected function get_base_currency();

	/**
	 * Order meta key used to store the payment data.
	 *
	 * @return string
	 */
	abstract protected function get_payment_data_meta_key();

	/**
	 * Create a payment through the API for the given order.
	 *
	 * @param \WC_Order $order The order object.
	 * @param array     $metadata Payment metadata.
	 * @return object
	 */
	abstract protected function create_payment_for_order( $order, $metadata );

	/**
	 * Setup gateway-specific properties (id, icon, titles).
	 *
	 * @return void
	 */
	abstract protected function setup_properties();

	/**
	 * Default checkout title.
	 *
	 * @return string
	 */
	abstract protected function get_default_title();

	/**
	 * Default checkout description.
	 *
	 * @return string
	 */
	abstract protected function get_default_description();

	/**
	 * Enable/disable field label.
	 *
	 * @return string
	 */
	abstract protected function get_enable_label();

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->setup_properties();

		$this->init_form_fields();
		$this->init_settings();

		$this->title                        = (string) $this->get_option( 'title', $this->get_default_title() );
		$this->description                  = (string) $this->get_option( 'description', $this->get_default_description() );
		$this->api_key                      = (string) $this->get_option( 'api_key', '' );
		$this->api_url                      = (string) $this->get_option( 'api_url', '' );
		$this->exchange_rate                = (float) $this->get_option( 'exchange_rate', '120' );
		$this->pending_payment_redirect_url = (string) $this->get_option( 'pending_payment_redirect_url', '' );
		$this->debug                        = 'yes' === $this->get_option( 'debug' );

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

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
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
		$currency      = get_woocommerce_currency();
		$base_currency = $this->get_base_currency();

		$base_fields = array(
			'enabled'                     => array(
				'title'   => __( 'Enable/Disable', 'uddoktapay-gateway' ),
				'type'    => 'checkbox',
				'label'   => $this->get_enable_label(),
				'default' => 'no',
			),
			'show_icon'                   => array(
				'title'   => __( 'Show Icon', 'uddoktapay-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display icons on checkout page.', 'uddoktapay-gateway' ),
				'default' => 'no',
			),
			'title'                       => array(
				'title'       => __( 'Title', 'uddoktapay-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'uddoktapay-gateway' ),
				'default'     => $this->get_default_title(),
				'desc_tip'    => true,
			),
			'description'                 => array(
				'title'       => __( 'Description', 'uddoktapay-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'uddoktapay-gateway' ),
				'default'     => $this->get_default_description(),
				'desc_tip'    => true,
			),
			'api_key'                     => array(
				'title'       => __( 'API Key', 'uddoktapay-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API key from UddoktaPay Panel → Brand Settings.', 'uddoktapay-gateway' ),
			),
			'api_url'                     => array(
				'title'       => __( 'API URL', 'uddoktapay-gateway' ),
				'type'        => 'url',
				'description' => __( 'Get your API URL from UddoktaPay Panel → Brand Settings.', 'uddoktapay-gateway' ),
			),
			'physical_product_status'     => array(
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
			'virtual_product_status'      => array(
				'title'       => __( 'Virtual Product Status', 'uddoktapay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select status for virtual product orders after successful payment.', 'uddoktapay-gateway' ),
				'default'     => OrderStatus::COMPLETED,
				'options'     => array(
					OrderStatus::ON_HOLD    => __( 'On Hold', 'uddoktapay-gateway' ),
					OrderStatus::PROCESSING => __( 'Processing', 'uddoktapay-gateway' ),
					OrderStatus::COMPLETED  => __( 'Completed', 'uddoktapay-gateway' ),
				),
			),
			'downloadable_product_status' => array(
				'title'       => __( 'Downloadable Product Status', 'uddoktapay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select status for downloadable product orders after successful payment.', 'uddoktapay-gateway' ),
				'default'     => OrderStatus::COMPLETED,
				'options'     => array(
					OrderStatus::ON_HOLD    => __( 'On Hold', 'uddoktapay-gateway' ),
					OrderStatus::PROCESSING => __( 'Processing', 'uddoktapay-gateway' ),
					OrderStatus::COMPLETED  => __( 'Completed', 'uddoktapay-gateway' ),
				),
			),
			'backorder_product_status'    => array(
				'title'       => __( 'Backorder Product Status', 'uddoktapay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select status for backorder product orders after successful payment.', 'uddoktapay-gateway' ),
				'default'     => OrderStatus::ON_HOLD,
				'options'     => array(
					OrderStatus::ON_HOLD    => __( 'On Hold', 'uddoktapay-gateway' ),
					OrderStatus::PROCESSING => __( 'Processing', 'uddoktapay-gateway' ),
					OrderStatus::COMPLETED  => __( 'Completed', 'uddoktapay-gateway' ),
				),
			),
		);

		if ( $base_currency !== $currency ) {
			$base_fields['exchange_rate'] = array(
				'title'             => sprintf(
					/* translators: %1$s: Order currency code, %2$s: Gateway base currency code */
					__( '%1$s to %2$s Exchange Rate', 'uddoktapay-gateway' ),
					$currency,
					$base_currency
				),
				'type'              => 'text',
				'desc_tip'          => true,
				'description'       => sprintf(
					/* translators: %s: Gateway base currency code */
					__( 'This rate will be applied to convert the total amount to %s.', 'uddoktapay-gateway' ),
					$base_currency
				),
				'default'           => '0',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			);
		}

		$base_fields['pending_payment_redirect_url'] = array(
			'title'       => __( 'Pending Payment Redirect URL', 'uddoktapay-gateway' ),
			'type'        => 'url',
			'description' => __( 'URL to redirect customers to when the payment is pending.', 'uddoktapay-gateway' ),
			'default'     => '',
		);

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

			$result = $this->create_payment_for_order(
				$order,
				array( 'order_id' => $order->get_id() )
			);

			if ( empty( $result->payment_url ) ) {
				throw new \Exception( $result->message ?? esc_html__( 'Payment URL not received', 'uddoktapay-gateway' ) );
			}

			$order->update_meta_data(
				'_uddoktapay_charge_amount',
				APIHandler::convert_amount( $order->get_total(), $order->get_currency(), $this->exchange_rate, $this->get_base_currency() )
			);

			$order->update_status(
				OrderStatus::PENDING,
				__( 'Awaiting UddoktaPay payment', 'uddoktapay-gateway' )
			);

			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $result->payment_url,
			);
		} catch ( \Exception $e ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'An error occurred: %s', 'uddoktapay-gateway' ),
					esc_html( $e->getMessage() )
				)
			);
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
			wp_die(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Error processing webhook: %s', 'uddoktapay-gateway' ),
					esc_html( $e->getMessage() )
				),
				'UddoktaPay Webhook Error',
				array( 'response' => 500 )
			);
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

		if ( 'COMPLETED' !== $result->status && ! empty( $this->pending_payment_redirect_url ) ) {
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			wp_redirect( esc_url_raw( $this->pending_payment_redirect_url ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( esc_url_raw( $this->get_return_url( $order ) ) );
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

		$payload_data = json_decode( $payload );

		if ( ! isset( $payload_data->invoice_id ) ) {
			throw new \Exception( esc_html__( 'Invoice ID not found in webhook data', 'uddoktapay-gateway' ) );
		}

		$data = $this->get_api()->verify_payment( sanitize_text_field( $payload_data->invoice_id ) );

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
		if ( empty( $this->api_key ) ) {
			return false;
		}

		$provided_key = isset( $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] ) ) : '';

		if ( empty( $provided_key ) ) {
			return false;
		}

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

		$order->update_meta_data( $this->get_payment_data_meta_key(), $data );

		if ( ! $this->is_payment_amount_valid( $order, $data ) ) {
			$order->update_status(
				OrderStatus::ON_HOLD,
				__( 'Payment amount does not match the order total. Please verify manually.', 'uddoktapay-gateway' )
			);
			$order->save();
			return;
		}

		if ( 'COMPLETED' === ( $data->status ?? '' ) ) {
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
	 * Validate the paid amount against the amount charged for the order.
	 *
	 * @param \WC_Order $order The order object.
	 * @param object    $data  The payment data.
	 * @return bool
	 */
	protected function is_payment_amount_valid( $order, $data ) {
		if ( ! isset( $data->amount ) ) {
			return false;
		}

		$expected = (float) $order->get_meta( '_uddoktapay_charge_amount' );

		if ( $expected <= 0 ) {
			return true;
		}

		return ( (float) $data->amount ) + 0.01 >= $expected;
	}

	/**
	 * Get order status based on product type and stock availability.
	 *
	 * @param \WC_Order $order The order object.
	 * @return string
	 */
	protected function get_order_status_by_type( $order ) {
		$all_virtual_and_downloadable = true;
		$all_virtual_only             = true;
		$all_downloadable_only        = true;
		$has_physical                 = false;
		$has_backorder                = false;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$is_virtual      = $product->is_virtual();
			$is_downloadable = $product->is_downloadable();

			if ( $product->is_on_backorder() ) {
				$has_backorder = true;
			}

			if ( ! $product->is_in_stock() && $product->backorders_allowed() ) {
				$has_backorder = true;
			}

			if ( ! $is_virtual && ! $is_downloadable ) {
				$has_physical                 = true;
				$all_virtual_and_downloadable = false;
				$all_virtual_only             = false;
				$all_downloadable_only        = false;
			}

			if ( ! ( $is_virtual && $is_downloadable ) ) {
				$all_virtual_and_downloadable = false;
			}

			if ( ! ( $is_virtual && ! $is_downloadable ) ) {
				$all_virtual_only = false;
			}

			if ( ! ( $is_downloadable && ! $is_virtual ) ) {
				$all_downloadable_only = false;
			}
		}

		if ( $has_backorder ) {
			return $this->get_option( 'backorder_product_status', OrderStatus::ON_HOLD );
		}

		if ( $has_physical ) {
			return $this->get_option( 'physical_product_status', OrderStatus::PROCESSING );
		}

		if ( $all_virtual_and_downloadable ) {
			return $this->get_option( 'downloadable_product_status', OrderStatus::COMPLETED );
		}

		if ( $all_virtual_only ) {
			return $this->get_option( 'virtual_product_status', OrderStatus::COMPLETED );
		}

		if ( $all_downloadable_only ) {
			return $this->get_option( 'downloadable_product_status', OrderStatus::COMPLETED );
		}

		return $this->get_option( 'virtual_product_status', OrderStatus::COMPLETED );
	}

	/**
	 * Handle completed payment with proper backorder consideration.
	 *
	 * @param \WC_Order $order The order object.
	 * @param object    $data Payment data containing transaction details.
	 * @return void
	 */
	protected function handle_completed_payment( $order, $data ) {
		$note = sprintf(
			/* translators: %1$s: Payment method, %2$s: Amount, %3$s: Transaction ID */
			__( 'Payment via %1$s. Amount: %2$s, Transaction ID: %3$s', 'uddoktapay-gateway' ),
			$data->payment_method ?? '',
			$data->amount ?? '',
			$data->transaction_id ?? ''
		);

		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'filter_payment_complete_order_status' ), 10, 3 );

		$order->payment_complete( $data->transaction_id ?? '' );

		remove_filter( 'woocommerce_payment_complete_order_status', array( $this, 'filter_payment_complete_order_status' ), 10 );

		$order->add_order_note( $note );

		if ( $this->has_backorder_items( $order ) ) {
			$order->add_order_note( __( 'Order contains backordered items. Items will be shipped when stock becomes available.', 'uddoktapay-gateway' ) );
		}
	}

	/**
	 * Filter the order status set by payment_complete based on product types and stock.
	 *
	 * @param string    $status   Default status (processing or completed).
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    Order object.
	 * @return string
	 */
	public function filter_payment_complete_order_status( $status, $order_id, $order ) {
		$custom_status = $this->get_order_status_by_type( $order );

		if ( $custom_status && $custom_status !== $status ) {
			return $custom_status;
		}

		return $status;
	}

	/**
	 * Check if order has any backordered items.
	 *
	 * @param \WC_Order $order The order object.
	 * @return bool
	 */
	protected function has_backorder_items( $order ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			if ( $product->is_on_backorder() ||
			( ! $product->is_in_stock() && $product->backorders_allowed() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Display transaction data in admin order page.
	 *
	 * @param \WC_Order $order The order object.
	 * @return void
	 */
	public function display_transaction_data( $order ) {
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		$payment_data = $order->get_meta( $this->get_payment_data_meta_key() );
		if ( empty( $payment_data ) ) {
			return;
		}

		$this->display_payment_info_html( $payment_data );
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
