<?php
/**
 * Plugin Name: ProcessFlow Manager
 * Plugin URI:  https://example.com/processflow-manager
 * Description: Order management with QR code workflow tracking and WhatsApp notifications.
 * Version:     1.0.0
 * Author:      ProcessFlow Team
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: processflow-manager
 * Domain Path: /languages
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'PROCESSFLOW_VERSION', '1.0.0' );
define( 'PROCESSFLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROCESSFLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load activator/deactivator immediately so the activation hook callback is
// available even before plugins_loaded fires (which is the case during the
// very first activation request).
require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-activator.php';
require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-deactivator.php';

/**
 * Main plugin orchestrator class.
 *
 * Loads all dependencies, wires up hooks, and holds singleton instances of
 * every sub-system so they are only instantiated once.
 */
class ProcessFlow_Manager {

	/** @var ProcessFlow_Database */
	private $db;

	/** @var ProcessFlow_Order_Manager */
	private $order_manager;

	/** @var ProcessFlow_QR_Engine */
	private $qr_engine;

	/** @var ProcessFlow_WhatsApp */
	private $whatsapp;

	/** @var ProcessFlow_Settings */
	private $settings;

	/** @var ProcessFlow_Admin */
	private $admin;

	/** @var ProcessFlow_Public */
	private $public;

	/**
	 * Boot the plugin.
	 */
	public function run() {
		$this->load_dependencies();
		$this->init();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Require all class files.
	 */
	private function load_dependencies() {
		require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-database.php';
		require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-order-manager.php';
		require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-qr-engine.php';
		require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-whatsapp.php';
		require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-settings.php';
		require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-admin.php';
		require_once PROCESSFLOW_PLUGIN_DIR . 'includes/class-public.php';
	}

	/**
	 * Instantiate all sub-system objects.
	 */
	private function init() {
		$this->settings      = new ProcessFlow_Settings();
		$this->db            = new ProcessFlow_Database();
		$this->order_manager = new ProcessFlow_Order_Manager( $this->db );
		$this->qr_engine     = new ProcessFlow_QR_Engine( $this->db );
		$this->whatsapp      = new ProcessFlow_WhatsApp( $this->db );
		$this->admin         = new ProcessFlow_Admin(
			$this->db,
			$this->order_manager,
			$this->qr_engine,
			$this->whatsapp,
			$this->settings
		);
		$this->public        = new ProcessFlow_Public(
			$this->db,
			$this->order_manager,
			$this->qr_engine,
			$this->whatsapp,
			$this->settings
		);
	}

	/**
	 * Register all hooks that power the WP admin.
	 */
	private function define_admin_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this->admin, 'add_menu_pages' ) );
		$this->admin->handle_ajax_requests();

		// Admin-dashboard shortcode is available on the front-end too.
		add_shortcode( 'processflow_admin_dashboard', array( $this->admin, 'admin_dashboard_shortcode' ) );
	}

	/**
	 * Register all hooks that power the public-facing portal.
	 */
	private function define_public_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this->public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this->public, 'enqueue_scripts' ) );
		add_shortcode( 'processflow_user_portal', array( $this->public, 'user_portal_shortcode' ) );
		$this->public->handle_ajax_requests();

		// QR scan endpoint – hooked during init so query vars are registered.
		$this->qr_engine->register_scan_endpoint();
	}
}

// Activation / deactivation hooks must be registered before the plugin runs.
register_activation_hook( __FILE__, array( 'ProcessFlow_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ProcessFlow_Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin and return the singleton.
 *
 * @return ProcessFlow_Manager
 */
function run_processflow() {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new ProcessFlow_Manager();
		$plugin->run();
	}
	return $plugin;
}

add_action( 'plugins_loaded', 'run_processflow' );
