<?php

declare (strict_types = 1);

namespace UddoktaPay\UddoktaPayGateway;

use UddoktaPay\UddoktaPayGateway\APIHandler;
use UddoktaPay\UddoktaPayGateway\Enums\OrderStatus;

// If this file is called directly, abort!!!
defined('ABSPATH') || die('Direct access is not allowed.');

class LocalGateway extends \WC_Payment_Gateway
{
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
    public function __construct()
    {
        // Setup general properties
        $this->setup_properties();

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings with type coercion
        $this->title = (string) $this->get_option('title', __('Mobile Banking', 'uddoktapay-gateway'));
        $this->description = (string) $this->get_option('description', __('Pay securely via Bangladeshi payment methods.', 'uddoktapay-gateway'));
        $this->api_key = (string) $this->get_option('api_key', '');
        $this->api_url = (string) $this->get_option('api_url', '');
        $this->exchange_rate = (float) $this->get_option('exchange_rate', '120');
        $this->debug = $this->get_option('debug') === 'yes';

        // Actions
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );

        add_action(
            'woocommerce_api_' . $this->id,
            [$this, 'handle_webhook']
        );

        add_action('woocommerce_admin_order_data_after_billing_address',
            [$this, 'display_transaction_data']
        );

        // Validation
        if (!$this->is_valid_for_use()) {
            $this->enabled = 'no';
        }
    }

    /**
     * Setup general properties for the gateway
     *
     * @return void
     */
    protected function setup_properties()
    {
        $this->id = 'uddoktapay';
        $this->icon = (string) apply_filters('woocommerce_uddoktapay_icon', '');
        $this->has_fields = false;
        $this->method_title = __('UddoktaPay', 'uddoktapay-gateway');
        $this->method_description = sprintf(
            '%s<br/><a href="%s" target="_blank">%s</a>',
            __('Accept Bangladeshi payments via multiple gateways including bKash, Nagad, Rocket and more.', 'uddoktapay-gateway'),
            esc_url('https://uddoktapay.com'),
            __('Sign up for UddoktaPay account', 'uddoktapay-gateway')
        );
        $this->webhook_url = (string) add_query_arg('wc-api', $this->id, home_url('/'));
        $this->supports = [
            'products',
        ];
    }

    /**
     * Check if gateway is valid for use
     *
     * @return bool
     */
    protected function is_valid_for_use()
    {
        if (empty($this->api_key) || empty($this->api_url)) {
            $this->add_error(__('UddoktaPay requires API Key and API URL to be configured.', 'uddoktapay-gateway'));
            return false;
        }
        return true;
    }

    /**
     * Initialize Gateway Settings Form Fields
     *
     * @return void
     */
    public function init_form_fields()
    {
        $currency = get_woocommerce_currency();

        $base_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'uddoktapay-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable UddoktaPay', 'uddoktapay-gateway'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'uddoktapay-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'uddoktapay-gateway'),
                'default' => __('Bangladeshi Payment', 'uddoktapay-gateway'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'uddoktapay-gateway'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'uddoktapay-gateway'),
                'default' => __('Pay securely via Bangladeshi payment methods.', 'uddoktapay-gateway'),
                'desc_tip' => true,
            ],
            'api_key' => [
                'title' => __('API Key', 'uddoktapay-gateway'),
                'type' => 'password',
                'description' => __('Get your API key from UddoktaPay Panel → Brand Settings.', 'uddoktapay-gateway'),
            ],
            'api_url' => [
                'title' => __('API URL', 'uddoktapay-gateway'),
                'type' => 'url',
                'description' => __('Get your API URL from UddoktaPay Panel → Brand Settings.', 'uddoktapay-gateway'),
            ],
            'physical_product_status' => [
                'title' => __('Physical Product Status', 'uddoktapay-gateway'),
                'type' => 'select',
                'description' => __('Select status for physical product orders after successful payment.', 'uddoktapay-gateway'),
                'default' => OrderStatus::PROCESSING,
                'options' => [
                    OrderStatus::ON_HOLD => __('On Hold', 'uddoktapay-gateway'),
                    OrderStatus::PROCESSING => __('Processing', 'uddoktapay-gateway'),
                    OrderStatus::COMPLETED => __('Completed', 'uddoktapay-gateway'),
                ],
            ],
            'digital_product_status' => [
                'title' => __('Digital Product Status', 'uddoktapay-gateway'),
                'type' => 'select',
                'description' => __('Select status for digital/downloadable product orders after successful payment.', 'uddoktapay-gateway'),
                'default' => OrderStatus::COMPLETED,
                'options' => [
                    OrderStatus::ON_HOLD => __('On Hold', 'uddoktapay-gateway'),
                    OrderStatus::PROCESSING => __('Processing', 'uddoktapay-gateway'),
                    OrderStatus::COMPLETED => __('Completed', 'uddoktapay-gateway'),
                ],
            ],
        ];

        if ($currency !== 'BDT') {
            $base_fields['exchange_rate'] = [
                'title' => sprintf(__('%s to BDT Exchange Rate', 'uddoktapay-gateway'), $currency),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('This rate will be applied to convert the total amount to BDT', 'uddoktapay-gateway'),
                'default' => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0',
                ],
            ];
        }

        $base_fields['debug'] = [
            'title' => __('Debug Log', 'uddoktapay-gateway'),
            'type' => 'checkbox',
            'label' => __('Enable logging', 'uddoktapay-gateway'),
            'default' => 'no',
            'description' => sprintf(
                __('Log gateway events inside %s', 'uddoktapay-gateway'),
                '<code>' . \WC_Log_Handler_File::get_log_file_path('uddoktapay') . '</code>'
            ),
        ];

        $this->form_fields = $base_fields;
    }

    /**
     * Get the API Handler instance
     *
     * @return APIHandler
     */
    protected function get_api()
    {
        if ($this->api === null) {
            APIHandler::$debug = $this->debug;
            APIHandler::$api_url = $this->api_url;
            APIHandler::$api_key = $this->api_key;

            $this->api = new APIHandler();
        }
        return $this->api;
    }

    /**
     * Process Payment
     *
     * @param int $order_id Order ID
     * @return array|null
     */
    public function process_payment($order_id)
    {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \Exception(__('Invalid order', 'uddoktapay-gateway'));
            }

            $metadata = [
                'order_id' => $order->get_id(),
                'redirect_url' => $this->get_return_url($order),
            ];

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

            if (empty($result->payment_url)) {
                throw new \Exception($result->message ?? __('Payment URL not received', 'uddoktapay-gateway'));
            }

            // Mark as pending payment
            $order->update_status(
                OrderStatus::PENDING,
                __('Awaiting UddoktaPay payment', 'uddoktapay-gateway')
            );

            // Empty cart
            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $result->payment_url,
            ];

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Handle Webhook
     *
     * @return void
     */
    public function handle_webhook()
    {
        try {
            $invoice_id = isset($_GET['invoice_id']) ? sanitize_text_field($_GET['invoice_id']) : '';

            if (!empty($invoice_id)) {
                $this->handle_redirect_verification($invoice_id);
            } else {
                $this->handle_webhook_notification();
            }
        } catch (\Exception $e) {
            wp_die($e->getMessage(), 'UddoktaPay Webhook Error', ['response' => 500]);
        }
    }

    /**
     * Handle redirect verification
     *
     * @param string $invoice_id
     * @return void
     */
    protected function handle_redirect_verification($invoice_id)
    {
        $result = $this->get_api()->verify_payment($invoice_id);

        if (!isset($result->metadata->order_id)) {
            throw new \Exception(__('Invalid order data received', 'uddoktapay-gateway'));
        }

        $order = wc_get_order($result->metadata->order_id);
        if (!$order) {
            throw new \Exception(__('Order not found', 'uddoktapay-gateway'));
        }

        $this->process_order_status($order, $result);

        wp_redirect($result->metadata->redirect_url);
        exit;
    }

    /**
     * Handle webhook notification
     *
     * @return void
     */
    protected function handle_webhook_notification()
    {
        $payload = file_get_contents('php://input');

        if (empty($payload)) {
            throw new \Exception(__('Empty webhook payload', 'uddoktapay-gateway'));
        }

        if (!$this->validate_webhook_signature()) {
            throw new \Exception(__('Invalid webhook signature', 'uddoktapay-gateway'));
        }

        $data = json_decode($payload);

        if (!isset($data->metadata->order_id)) {
            throw new \Exception(__('Order ID not found in webhook data', 'uddoktapay-gateway'));
        }

        $order = wc_get_order($data->metadata->order_id);
        if (!$order) {
            throw new \Exception(__('Order not found', 'uddoktapay-gateway'));
        }

        $this->process_order_status($order, $data);
    }

    /**
     * Validate webhook signature
     *
     * @return bool
     */
    protected function validate_webhook_signature()
    {
        $provided_key = isset($_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY']) ? $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] : '';
        return hash_equals($this->api_key, $provided_key);
    }

    /**
     * Process order status
     *
     * @param WC_Order $order
     * @param object $data
     * @return void
     */
    protected function process_order_status($order, $data)
    {
        if ($order->get_status() === OrderStatus::COMPLETED) {
            return;
        }

        $order->update_meta_data('uddoktapay_payment_data', $data);

        if ($data->status === 'COMPLETED') {
            $this->handle_completed_payment($order, $data);
        } else {
            $order->update_status(
                OrderStatus::ON_HOLD,
                __('Payment is on hold. Please check manually.', 'uddoktapay-gateway')
            );
        }

        $order->save();
    }

    /**
     * Handle completed payment
     *
     * @param WC_Order $order
     * @param object $data
     * @return void
     */
    protected function handle_completed_payment($order, $data)
    {
        $status = $this->is_order_virtual($order) ? $this->get_option('digital_product_status', OrderStatus::COMPLETED) : $this->get_option('physical_product_status', OrderStatus::PROCESSING);
        $note = sprintf(
            __('Payment via %s. Amount: %s, Transaction ID: %s', 'uddoktapay-gateway'),
            $data->payment_method,
            $data->amount,
            $data->transaction_id
        );

        $order->payment_complete($data->transaction_id);
        $order->update_status($status, $note);
    }

    /**
     * Check if order is virtual
     *
     * @param WC_Order $order
     * @return bool
     */
    protected function is_order_virtual($order)
    {
        $virtual = false;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && ($product->is_virtual() || $product->is_downloadable())) {
                $virtual = true;
                break;
            }
        }

        return $virtual;
    }

    /**
     * Display transaction data in admin order page
     *
     * @param WC_Order $order
     */
    public function display_transaction_data($order)
    {
        // Check if we've already displayed this data
        if (defined('UDDOKTAPAY_ADMIN_DATA_DISPLAYED')) {
            return;
        }

        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $payment_data = $order->get_meta('uddoktapay_payment_data');
        if (empty($payment_data)) {
            return;
        }

        $this->display_payment_info_html($payment_data);
        // Mark as displayed
        define('UDDOKTAPAY_ADMIN_DATA_DISPLAYED', true);
    }

    /**
     * Display payment information HTML
     *
     * @param object $data Payment data
     */
    protected function display_payment_info_html($data)
    {
        $payment_method = esc_html(ucfirst($data->payment_method ?? ''));
        $sender_number = esc_html($data->sender_number ?? '');
        $transaction_id = esc_html($data->transaction_id ?? '');
        $amount = esc_html($data->amount ?? '');

        echo "<div class='form-field form-field-wide uddoktapay-admin-data'>

            <table class='wp-list-table widefat striped posts'>
                <tbody>
                    <tr>
                        <th>
                            <strong>Payment Method</strong>
                        </th>
                        <td>
                                {$payment_method}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <strong>Sender Number</strong>
                        </th>
                        <td>
                                {$sender_number}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <strong>Transaction ID</strong>
                        </th>
                        <td>
                                {$transaction_id}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <strong>Amount</strong>
                        </th>
                        <td>
                                {$amount}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>";
    }
}
