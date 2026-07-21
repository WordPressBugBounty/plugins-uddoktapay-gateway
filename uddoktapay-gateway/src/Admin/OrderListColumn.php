<?php
/**
 * UddoktaPay Payment Gateway Admin Order List Column.
 *
 * Adds transaction ID and sender number columns to the WooCommerce order list
 * for both the HPOS (wc-orders) and legacy (shop_order) admin screens.
 *
 * @package UddoktaPayGateway\Admin
 */

declare (strict_types = 1);

namespace UddoktaPay\UddoktaPayGateway\Admin;

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit( 'Direct access is not allowed.' );

/**
 * Order List Column
 *
 * @since 2.7.0
 */
class OrderListColumn {

	/**
	 * Transaction ID column key.
	 *
	 * @var string
	 */
	private $transaction_column_key = 'uddoktapay_transaction_id';

	/**
	 * Sender number column key.
	 *
	 * @var string
	 */
	private $sender_column_key = 'uddoktapay_sender_number';

	/**
	 * Gateway ID to payment-data meta key map.
	 *
	 * @var array<string, string>
	 */
	private $payment_meta_keys = array(
		'uddoktapay'              => 'uddoktapay_payment_data',
		'uddoktapayinternational' => 'uddoktapay_international_payment_data',
	);

	/**
	 * Register column hooks for HPOS and legacy order lists.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_column' ), 10, 2 );

		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_legacy_column' ), 10, 2 );
	}

	/**
	 * Insert the plugin columns after the status column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_columns( $columns ) {
		$plugin_columns = array(
			$this->transaction_column_key => __( 'UddoktaPay TrxID', 'uddoktapay-gateway' ),
			$this->sender_column_key      => __( 'UddoktaPay Sender', 'uddoktapay-gateway' ),
		);

		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'order_status' === $key ) {
				$new_columns += $plugin_columns;
			}
		}

		return $new_columns + $plugin_columns;
	}

	/**
	 * Render column content on the HPOS order list.
	 *
	 * @param string    $column Column key.
	 * @param \WC_Order $order  Order object.
	 * @return void
	 */
	public function render_hpos_column( $column, $order ) {
		$this->render_column( $column, $order );
	}

	/**
	 * Render column content on the legacy order list.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order post ID.
	 * @return void
	 */
	public function render_legacy_column( $column, $post_id ) {
		$this->render_column( $column, wc_get_order( $post_id ) );
	}

	/**
	 * Output the requested column value for a UddoktaPay order.
	 *
	 * @param string          $column Column key.
	 * @param \WC_Order|false $order  Order object.
	 * @return void
	 */
	private function render_column( $column, $order ) {
		if ( $this->transaction_column_key !== $column && $this->sender_column_key !== $column ) {
			return;
		}

		if ( ! $order instanceof \WC_Order || ! isset( $this->payment_meta_keys[ $order->get_payment_method() ] ) ) {
			echo '&mdash;';
			return;
		}

		$value = $this->transaction_column_key === $column
			? (string) $order->get_transaction_id()
			: $this->get_sender_number( $order );

		echo '' !== $value ? esc_html( $value ) : '&mdash;';
	}

	/**
	 * Resolve the sender number, falling back to the billing phone.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string
	 */
	private function get_sender_number( $order ) {
		$data = $order->get_meta( $this->payment_meta_keys[ $order->get_payment_method() ] );

		if ( is_object( $data ) && ! empty( $data->sender_number ) ) {
			return (string) $data->sender_number;
		}

		return (string) $order->get_billing_phone();
	}
}
