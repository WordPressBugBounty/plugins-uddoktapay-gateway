<?php
/**
 * Plugin Name:    UddoktaPay
 * Plugin URI:     https://uddoktapay.com
 * Description:    Accept payments via bKash, Rocket, Nagad, Upay and International methods through UddoktaPay
 * Version:        2.5.2
 * Author:         UddoktaPay
 * Author URI:     https://uddoktapay.com
 * License:        GPL v2 or later
 * License URI:    https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:    uddoktapay-gateway
 * Domain Path:    /languages
 *
 * @package UddoktaPay
 */

defined( 'ABSPATH' ) || exit( 'Direct access is not allowed.' );

// Define constants.
define( 'UDDOKTAPAY_VERSION', '2.5.2' );
define( 'UDDOKTAPAY_FILE', __FILE__ );
define( 'UDDOKTAPAY_PATH', plugin_dir_path( UDDOKTAPAY_FILE ) );
define( 'UDDOKTAPAY_URL', plugin_dir_url( UDDOKTAPAY_FILE ) );

// Load Composer autoload.
require_once UDDOKTAPAY_PATH . '/vendor/autoload.php';

/**
 * Main plugin class.
 */
final class UddoktaPay_Plugin {


	/**
	 * Plugin instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Payment gateways list.
	 *
	 * @var array
	 */
	public $gateways = array();

	/**
	 * Get instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'init_blocks_support' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_blocks_compatibility' ) );
	}

	/**
	 * Initialize plugin features.
	 */
	public function init_plugin() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->init_gateways();
		$this->init_admin();
		$this->init_frontend();
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'uddoktapay-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Show WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice() {
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			sprintf(
				// Translators: %1$s is the plugin name, %2$s is the required plugin name.
				esc_html__( '%1$s requires %2$s to be installed and activated.', 'uddoktapay-gateway' ),
				'<strong>UddoktaPay Gateway</strong>',
				'<strong>WooCommerce</strong>'
			)
		);
	}

	/**
	 * Initialize gateways.
	 */
	private function init_gateways() {
		$this->gateways = array(
			new UddoktaPay\UddoktaPayGateway\LocalGateway(),
			new UddoktaPay\UddoktaPayGateway\InternationalGateway(),
		);

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
	}

	/**
	 * Add gateways to WooCommerce.
	 *
	 * @param array $gateways Gateways array.
	 * @return array
	 */
	public function add_gateways( $gateways ) {
		foreach ( $this->gateways as $gateway ) {
			$gateways[] = $gateway;
		}

		return $gateways;
	}

	/**
	 * Initialize admin.
	 */
	private function init_admin() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'plugin_action_links_' . plugin_basename( UDDOKTAPAY_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Initialize frontend.
	 */
	private function init_frontend() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function frontend_assets() {
		wp_enqueue_style(
			'uddoktapay-gateway',
			UDDOKTAPAY_URL . 'assets/css/uddoktapay.css',
			array(),
			UDDOKTAPAY_VERSION
		);

		wp_enqueue_script(
			'uddoktapay-gateway',
			UDDOKTAPAY_URL . 'assets/js/uddoktapay.js',
			array( 'jquery', 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			UDDOKTAPAY_VERSION,
			true// Load in footer.
		);
	}

	/**
	 * Add plugin action links in plugin list.
	 *
	 * @param array $links Default plugin links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=uddoktapay' ),
				esc_html__( 'BD Methods Settings', 'uddoktapay-gateway' )
			),
			sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=uddoktapayinternational' ),
				esc_html__( 'Global Methods Settings', 'uddoktapay-gateway' )
			),
			sprintf(
				'<a href="%s"><strong style="color: green;">%s</strong></a>',
				'https://uddoktapay.com',
				esc_html__( 'Purchase License', 'uddoktapay-gateway' )
			),
		);

		return array_merge( $links, $plugin_links );
	}

	/**
	 * Register WooCommerce blocks support.
	 */
	public function init_blocks_support() {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new UddoktaPay\UddoktaPayGateway\Blocks\LocalBlocks() );
				$registry->register( new UddoktaPay\UddoktaPayGateway\Blocks\InternationalBlocks() );
			}
		);
	}

	/**
	 * Declare compatibility with WooCommerce cart and checkout blocks.
	 */
	public function declare_blocks_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', UDDOKTAPAY_FILE, true );
		}
	}
}

UddoktaPay_Plugin::instance();
