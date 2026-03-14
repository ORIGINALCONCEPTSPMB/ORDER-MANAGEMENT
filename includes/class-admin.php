<?php
/**
 * WordPress admin interface for ProcessFlow Manager.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_Admin
 */
class ProcessFlow_Admin {

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

	/**
	 * @param ProcessFlow_Database      $db            DB instance.
	 * @param ProcessFlow_Order_Manager $order_manager Order manager.
	 * @param ProcessFlow_QR_Engine     $qr_engine     QR engine.
	 * @param ProcessFlow_WhatsApp      $whatsapp      WhatsApp helper.
	 * @param ProcessFlow_Settings      $settings      Settings helper.
	 */
	public function __construct(
		ProcessFlow_Database $db,
		ProcessFlow_Order_Manager $order_manager,
		ProcessFlow_QR_Engine $qr_engine,
		ProcessFlow_WhatsApp $whatsapp,
		ProcessFlow_Settings $settings
	) {
		$this->db            = $db;
		$this->order_manager = $order_manager;
		$this->qr_engine     = $qr_engine;
		$this->whatsapp      = $whatsapp;
		$this->settings      = $settings;
	}

	// ------------------------------------------------------------------ //
	// Asset enqueueing                                                     //
	// ------------------------------------------------------------------ //

	/**
	 * Enqueue admin assets on front-end pages that contain the shortcode.
	 */
	public function enqueue_scripts_frontend() {
		// Detection method 1: global $post (most common).
		global $post;
		$has_sc = $post && has_shortcode( $post->post_content, 'processflow_admin_dashboard' );

		// Detection method 2: queried object (handles sub-directory / some page-builder setups
		// where $post is set late or content is stored differently).
		if ( ! $has_sc ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post ) {
				$has_sc = has_shortcode( $queried->post_content, 'processflow_admin_dashboard' );
			}
		}

		// Detection method 3: session cookie present → user is already logged in to the
		// dashboard, so always load scripts (safe because they are lightweight).
		if ( ! $has_sc ) {
			$token = isset( $_COOKIE['pf_admin_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pf_admin_token'] ) ) : '';
			if ( $token && get_transient( 'processflow_admin_session_' . $token ) ) {
				$has_sc = true;
			}
		}

		if ( ! $has_sc ) {
			return;
		}

		$this->do_frontend_enqueue();
	}

	/**
	 * Actually enqueue the admin CSS/JS for the frontend shortcode page.
	 * Extracted so it can be called from both the hook and inline from the shortcode.
	 */
	private function do_frontend_enqueue() {
		if ( wp_script_is( 'processflow-admin', 'enqueued' ) ) {
			return; // Already done.
		}
		wp_enqueue_style(
			'processflow-admin',
			PROCESSFLOW_PLUGIN_URL . 'admin/css/processflow-admin.css',
			array(),
			PROCESSFLOW_VERSION
		);
		// wp-color-picker is an admin script; load it only when available on the frontend.
		if ( wp_script_is( 'wp-color-picker', 'registered' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
		wp_enqueue_script(
			'processflow-admin',
			PROCESSFLOW_PLUGIN_URL . 'admin/js/processflow-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			PROCESSFLOW_VERSION,
			true
		);
		wp_localize_script(
			'processflow-admin',
			'processflowAdmin',
			$this->get_frontend_script_data()
		);
	}

	/**
	 * Build the processflowAdmin JS config object.
	 *
	 * @return array
	 */
	private function get_frontend_script_data(): array {
		return array(
			'ajax_url'               => admin_url( 'admin-ajax.php' ),
			'processflow_ajax_nonce' => wp_create_nonce( 'processflow_admin_nonce' ),
			'confirm_delete'         => __( 'Are you sure you want to delete this item? This cannot be undone.', 'processflow-manager' ),
			'strings'                => array(
				'saving'  => __( 'Saving…', 'processflow-manager' ),
				'saved'   => __( 'Saved!', 'processflow-manager' ),
				'error'   => __( 'An error occurred. Please try again.', 'processflow-manager' ),
				'loading' => __( 'Loading…', 'processflow-manager' ),
			),
		);
	}


	/**
	 * Enqueue admin stylesheets on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( string $hook ) {
		if ( false === strpos( $hook, 'processflow' ) ) {
			return;
		}
		wp_enqueue_style(
			'processflow-admin',
			PROCESSFLOW_PLUGIN_URL . 'admin/css/processflow-admin.css',
			array(),
			PROCESSFLOW_VERSION
		);
		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Enqueue admin scripts on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ) {
		if ( false === strpos( $hook, 'processflow' ) ) {
			return;
		}
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script(
			'processflow-admin',
			PROCESSFLOW_PLUGIN_URL . 'admin/js/processflow-admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
			PROCESSFLOW_VERSION,
			true
		);
		wp_localize_script(
			'processflow-admin',
			'processflowAdmin',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'processflow_ajax_nonce' => wp_create_nonce( 'processflow_admin_nonce' ),
				'confirm_delete'        => __( 'Are you sure you want to delete this item? This cannot be undone.', 'processflow-manager' ),
				'strings'               => array(
					'saving'  => __( 'Saving…', 'processflow-manager' ),
					'saved'   => __( 'Saved!', 'processflow-manager' ),
					'error'   => __( 'An error occurred. Please try again.', 'processflow-manager' ),
					'loading' => __( 'Loading…', 'processflow-manager' ),
				),
			)
		);
	}

	// ------------------------------------------------------------------ //
	// Admin menu                                                           //
	// ------------------------------------------------------------------ //

	/**
	 * Register the plugin admin menu and sub-pages.
	 */
	public function add_menu_pages() {
		add_menu_page(
			__( 'ProcessFlow', 'processflow-manager' ),
			__( 'ProcessFlow', 'processflow-manager' ),
			'manage_options',
			'processflow-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-networking',
			25
		);

		add_submenu_page(
			'processflow-dashboard',
			__( 'Dashboard', 'processflow-manager' ),
			__( 'Dashboard', 'processflow-manager' ),
			'manage_options',
			'processflow-dashboard',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'processflow-dashboard',
			__( 'Orders', 'processflow-manager' ),
			__( 'Orders', 'processflow-manager' ),
			'manage_options',
			'processflow-orders',
			array( $this, 'render_orders' )
		);

		add_submenu_page(
			'processflow-dashboard',
			__( 'Stages', 'processflow-manager' ),
			__( 'Stages', 'processflow-manager' ),
			'manage_options',
			'processflow-stages',
			array( $this, 'render_stages' )
		);

		add_submenu_page(
			'processflow-dashboard',
			__( 'QR Codes', 'processflow-manager' ),
			__( 'QR Codes', 'processflow-manager' ),
			'manage_options',
			'processflow-qr-codes',
			array( $this, 'render_qr_codes' )
		);

		add_submenu_page(
			'processflow-dashboard',
			__( 'Settings', 'processflow-manager' ),
			__( 'Settings', 'processflow-manager' ),
			'manage_options',
			'processflow-settings',
			array( $this, 'render_settings' )
		);
	}

	// ------------------------------------------------------------------ //
	// Page renderers                                                       //
	// ------------------------------------------------------------------ //

	/** Render the dashboard overview page. */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'processflow-manager' ) );
		}
		require_once PROCESSFLOW_PLUGIN_DIR . 'admin/partials/dashboard.php';
	}

	/** Render the orders management page. */
	public function render_orders() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'processflow-manager' ) );
		}
		require_once PROCESSFLOW_PLUGIN_DIR . 'admin/partials/orders.php';
	}

	/** Render the stages management page. */
	public function render_stages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'processflow-manager' ) );
		}
		require_once PROCESSFLOW_PLUGIN_DIR . 'admin/partials/stages.php';
	}

	/** Render the QR codes page. */
	public function render_qr_codes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'processflow-manager' ) );
		}
		require_once PROCESSFLOW_PLUGIN_DIR . 'admin/partials/qr-codes.php';
	}

	/** Render the settings page. */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'processflow-manager' ) );
		}
		require_once PROCESSFLOW_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ------------------------------------------------------------------ //
	// AJAX handlers                                                        //
	// ------------------------------------------------------------------ //

	/**
	 * Register all AJAX action hooks for admin operations.
	 */
	public function handle_ajax_requests() {
		$actions = array(
			'processflow_create_order',
			'processflow_update_order',
			'processflow_delete_order',
			'processflow_get_order_data',
			'processflow_get_orders_json',
			'processflow_update_stage',
			'processflow_create_stage',
			'processflow_delete_stage',
			'processflow_advance_stage',
			'processflow_get_qr',
			'processflow_save_settings',
			'processflow_create_custom_field',
			'processflow_delete_custom_field',
			'processflow_get_labels_html',
			'processflow_reorder_stages',
			'processflow_create_pf_user',
			'processflow_update_pf_user',
			'processflow_delete_pf_user',
			'processflow_get_pf_users',
			'processflow_sync_invoice_ninja',
			'processflow_import_csv',
			'processflow_csv_template',
			'processflow_send_whatsapp_notification',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'dispatch_ajax' ) );
		}
		// These are also available to non-WP-logged-in users authenticated via shortcode session.
		add_action( 'wp_ajax_nopriv_processflow_send_whatsapp_notification', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_csv_template', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_get_orders_json', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_create_order', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_update_order', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_delete_order', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_get_order_data', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_advance_stage', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_get_qr', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_get_labels_html', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_import_csv', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_save_settings', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_create_stage', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_update_stage', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_delete_stage', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_reorder_stages', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_create_pf_user', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_update_pf_user', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_delete_pf_user', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_sync_invoice_ninja', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_create_custom_field', array( $this, 'dispatch_ajax' ) );
		add_action( 'wp_ajax_nopriv_processflow_delete_custom_field', array( $this, 'dispatch_ajax' ) );
	}

	/**
	 * Central AJAX dispatcher – verifies nonce then calls the right method.
	 */
	public function dispatch_ajax() {
		// CSV template is public (nonce still required).
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

		// Nonce check.
		check_ajax_referer( 'processflow_admin_nonce', 'nonce' );

		// Capability check (WP admin OR shortcode session).
		if ( ! current_user_can( 'manage_options' ) && ! $this->check_shortcode_session() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'processflow-manager' ) ), 403 );
		}

		// Operator role: restrict to order actions only.
		$operator_only_actions = array(
			'processflow_create_order',
			'processflow_update_order',
			'processflow_delete_order',
			'processflow_get_order_data',
			'processflow_get_orders_json',
			'processflow_advance_stage',
			'processflow_get_qr',
			'processflow_get_labels_html',
			'processflow_csv_template',
			'processflow_import_csv',
			'processflow_send_whatsapp_notification',
		);
		$admin_only_actions = array(
			'processflow_create_stage',
			'processflow_update_stage',
			'processflow_delete_stage',
			'processflow_reorder_stages',
			'processflow_save_settings',
			'processflow_create_custom_field',
			'processflow_delete_custom_field',
			'processflow_create_pf_user',
			'processflow_update_pf_user',
			'processflow_delete_pf_user',
			'processflow_get_pf_users',
			'processflow_sync_invoice_ninja',
		);

		if ( in_array( $action, $admin_only_actions, true )
			&& ! current_user_can( 'manage_options' )
			&& 'admin' !== $this->get_session_role()
		) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'processflow-manager' ) ), 403 );
		}

		switch ( $action ) {
			case 'processflow_create_order':
				$this->ajax_create_order();
				break;
			case 'processflow_update_order':
				$this->ajax_update_order();
				break;
			case 'processflow_delete_order':
				$this->ajax_delete_order();
				break;
			case 'processflow_get_order_data':
				$this->ajax_get_order_data();
				break;
			case 'processflow_get_orders_json':
				$this->ajax_get_orders_json();
				break;
			case 'processflow_create_stage':
				$this->ajax_create_stage();
				break;
			case 'processflow_update_stage':
				$this->ajax_update_stage();
				break;
			case 'processflow_delete_stage':
				$this->ajax_delete_stage();
				break;
			case 'processflow_advance_stage':
				$this->ajax_advance_stage();
				break;
			case 'processflow_get_qr':
				$this->ajax_get_qr();
				break;
			case 'processflow_save_settings':
				$this->ajax_save_settings();
				break;
			case 'processflow_create_custom_field':
				$this->ajax_create_custom_field();
				break;
			case 'processflow_delete_custom_field':
				$this->ajax_delete_custom_field();
				break;
			case 'processflow_get_labels_html':
				$this->ajax_get_labels_html();
				break;
			case 'processflow_reorder_stages':
				$this->ajax_reorder_stages();
				break;
			case 'processflow_create_pf_user':
				$this->ajax_create_pf_user();
				break;
			case 'processflow_update_pf_user':
				$this->ajax_update_pf_user();
				break;
			case 'processflow_delete_pf_user':
				$this->ajax_delete_pf_user();
				break;
			case 'processflow_get_pf_users':
				$this->ajax_get_pf_users();
				break;
			case 'processflow_sync_invoice_ninja':
				$this->ajax_sync_invoice_ninja();
				break;
			case 'processflow_import_csv':
				$this->ajax_import_csv();
				break;
			case 'processflow_csv_template':
				$this->ajax_csv_template();
				break;
			case 'processflow_send_whatsapp_notification':
				$this->ajax_send_whatsapp_notification();
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'processflow-manager' ) ) );
		}
	}

	// ------------------------------------------------------------------ //
	// Individual AJAX handlers                                             //
	// ------------------------------------------------------------------ //

	private function ajax_create_order() {
		$data = array(
			'customer_name' => isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '',
			'business_name' => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
			'whatsapp'      => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
			'job_details'   => isset( $_POST['job_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['job_details'] ) ) : '',
			'current_stage' => isset( $_POST['current_stage'] ) ? absint( $_POST['current_stage'] ) : 0,
		);

		$result = $this->order_manager->create_order( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'order_id' => $result,
			'message'  => __( 'Order created successfully.', 'processflow-manager' ),
		) );
	}

	private function ajax_update_order() {
		$id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$data = array(
			'customer_name' => isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '',
			'business_name' => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
			'whatsapp'      => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
			'job_details'   => isset( $_POST['job_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['job_details'] ) ) : '',
			'current_stage' => isset( $_POST['current_stage'] ) ? absint( $_POST['current_stage'] ) : 0,
		);

		$result = $this->order_manager->update_order( $id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Order updated successfully.', 'processflow-manager' ) ) );
	}

	private function ajax_delete_order() {
		$id     = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$result = $this->order_manager->delete_order( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Order deleted.', 'processflow-manager' ) ) );
	}

	private function ajax_get_order_data() {
		$id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order = $this->db->get_order( $id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'processflow-manager' ) ) );
		}

		wp_send_json_success( array(
			'id'            => (int) $order->id,
			'customer_name' => $order->customer_name,
			'business_name' => $order->business_name,
			'whatsapp'      => $order->whatsapp,
			'job_details'   => $order->job_details,
			'current_stage' => $order->current_stage,
		) );
	}

	/**
	 * Return a paginated, filtered list of orders as JSON.
	 * Used by the frontend admin JS to rebuild the order table without a full
	 * page reload (avoids cookie/session issues on sub-directory installs).
	 */
	private function ajax_get_orders_json() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$search   = isset( $_POST['search'] )   ? sanitize_text_field( wp_unslash( $_POST['search'] ) )   : '';
		$stage    = isset( $_POST['stage'] )    ? absint( $_POST['stage'] )    : 0;
		$page     = isset( $_POST['page'] )     ? max( 1, absint( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		// phpcs:enable

		$result = $this->db->get_orders( array(
			'search'   => $search,
			'stage'    => $stage,
			'per_page' => $per_page,
			'page'     => $page,
		) );

		// Build a stage ID → {name, color} map for the response.
		$stages     = $this->db->get_stages();
		$stage_map  = array();
		foreach ( $stages as $s ) {
			$stage_map[ (int) $s->id ] = array( 'name' => $s->name, 'color' => $s->color );
		}

		// Build a map of last-notified stage per order.
		$order_ids     = array_map( function ( $o ) { return (int) $o->id; }, $result['items'] );
		$last_notified = $this->db->get_last_notified_stages( $order_ids );

		$items = array();
		foreach ( $result['items'] as $order ) {
			$sid           = (int) $order->current_stage;
			$stage_info    = isset( $stage_map[ $sid ] ) ? $stage_map[ $sid ] : null;
			$notified_info = isset( $last_notified[ (int) $order->id ] ) ? $last_notified[ (int) $order->id ] : null;
			$items[]       = array(
				'id'                   => (int) $order->id,
				'customer_name'        => $order->customer_name,
				'business_name'        => $order->business_name,
				'whatsapp'             => $order->whatsapp,
				'job_details'          => $order->job_details,
				'current_stage'        => $sid,
				'stage_name'           => $stage_info ? $stage_info['name'] : '',
				'stage_color'          => $stage_info ? $stage_info['color'] : '#aaa',
				'created_at'           => $order->created_at,
				'whatsapp_sent_stage'  => $notified_info ? $notified_info['stage_name'] : '',
				'whatsapp_sent_color'  => $notified_info ? $notified_info['stage_color'] : '',
			);
		}

		wp_send_json_success( array(
			'items'      => $items,
			'total'      => (int) $result['total'],
			'page'       => $page,
			'per_page'   => $per_page,
			'total_pages'=> (int) ceil( $result['total'] / max( 1, $per_page ) ),
		) );
	}

	private function ajax_create_stage() {
		$data = array(
			'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'color'             => isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#000000',
			'whatsapp_template' => isset( $_POST['whatsapp_template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['whatsapp_template'] ) ) : '',
		);

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Stage name is required.', 'processflow-manager' ) ) );
		}

		$result = $this->db->create_stage( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'stage_id' => $result,
			'message'  => __( 'Stage created.', 'processflow-manager' ),
		) );
	}

	private function ajax_update_stage() {
		$id   = isset( $_POST['stage_id'] ) ? absint( $_POST['stage_id'] ) : 0;
		$data = array(
			'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'color'             => isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#000000',
			'whatsapp_template' => isset( $_POST['whatsapp_template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['whatsapp_template'] ) ) : '',
		);

		$result = $this->db->update_stage( $id, $data );
		$result
			? wp_send_json_success( array( 'message' => __( 'Stage updated.', 'processflow-manager' ) ) )
			: wp_send_json_error( array( 'message' => __( 'Failed to update stage.', 'processflow-manager' ) ) );
	}

	private function ajax_delete_stage() {
		$id     = isset( $_POST['stage_id'] ) ? absint( $_POST['stage_id'] ) : 0;
		$result = $this->db->delete_stage( $id );

		$result
			? wp_send_json_success( array( 'message' => __( 'Stage deleted.', 'processflow-manager' ) ) )
			: wp_send_json_error( array( 'message' => __( 'Failed to delete stage.', 'processflow-manager' ) ) );
	}

	private function ajax_advance_stage() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$result   = $this->order_manager->advance_stage( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$stage = $this->db->get_stage( $result );
		wp_send_json_success( array(
			'new_stage_id'   => $result,
			'new_stage_name' => $stage ? $stage->name : '',
			'message'        => __( 'Stage advanced.', 'processflow-manager' ),
		) );
	}

	private function ajax_get_qr() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$url      = $this->qr_engine->get_qr_image_url( $order_id );

		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'message' => $url->get_error_message() ) );
		}

		wp_send_json_success( array( 'qr_url' => $url ) );
	}

	private function ajax_send_whatsapp_notification() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'processflow-manager' ) ) );
		}

		$order = $this->db->get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'processflow-manager' ) ) );
		}

		$stage_id = (int) $order->current_stage;
		if ( ! $stage_id ) {
			wp_send_json_error( array( 'message' => __( 'Order has no stage assigned.', 'processflow-manager' ) ) );
		}

		$wa_url = $this->whatsapp->get_whatsapp_link( $order_id, $stage_id );

		// Mark notification as sent in the stage history.
		$this->whatsapp->send_stage_notification( $order_id, $stage_id );

		$stage = $this->db->get_stage( $stage_id );
		wp_send_json_success( array(
			'wa_url'      => $wa_url,
			'stage_name'  => $stage ? $stage->name : '',
			'stage_color' => $stage ? $stage->color : '#27ae60',
			'message'     => __( 'WhatsApp notification marked as sent.', 'processflow-manager' ),
		) );
	}

	private function ajax_save_settings() {
		$allowed = array(
			'company_name',
			'company_phone',
			'company_email',
			'portal_title',
			'portal_intro',
			'orders_per_page',
			'enable_whatsapp',
			'invoiceninja_url',
			'invoiceninja_token',
		);

		foreach ( $allowed as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				if ( 'invoiceninja_url' === $key ) {
					$value = esc_url_raw( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				}
				$this->settings->update_setting( $key, $value );
			}
		}

		// Handle password change separately.
		if ( ! empty( $_POST['new_password'] ) ) {
			$this->settings->set_admin_password( sanitize_text_field( wp_unslash( $_POST['new_password'] ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'processflow-manager' ) ) );
	}

	private function ajax_create_custom_field() {
		$data = array(
			'field_label' => isset( $_POST['field_label'] ) ? sanitize_text_field( wp_unslash( $_POST['field_label'] ) ) : '',
			'field_type'  => isset( $_POST['field_type'] ) ? sanitize_key( wp_unslash( $_POST['field_type'] ) ) : 'text',
			'is_required' => isset( $_POST['is_required'] ) ? absint( $_POST['is_required'] ) : 0,
		);

		if ( empty( $data['field_label'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Field label is required.', 'processflow-manager' ) ) );
		}

		$result = $this->db->create_custom_field( $data );

		is_wp_error( $result )
			? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
			: wp_send_json_success( array( 'field_id' => $result ) );
	}

	private function ajax_delete_custom_field() {
		$id     = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
		$result = $this->db->delete_custom_field( $id );

		$result
			? wp_send_json_success( array( 'message' => __( 'Custom field deleted.', 'processflow-manager' ) ) )
			: wp_send_json_error( array( 'message' => __( 'Failed to delete custom field.', 'processflow-manager' ) ) );
	}

	private function ajax_get_labels_html() {
		$raw_ids  = isset( $_POST['order_ids'] ) ? wp_unslash( $_POST['order_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$order_ids = is_array( $raw_ids ) ? array_map( 'absint', $raw_ids ) : array( absint( $raw_ids ) );

		$html = $this->qr_engine->generate_pdf_labels( $order_ids );
		wp_send_json_success( array( 'html' => $html ) );
	}

	private function ajax_reorder_stages() {
		$raw_order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_array( $raw_order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'processflow-manager' ) ) );
		}

		foreach ( $raw_order as $position => $stage_id ) {
			$this->db->update_stage( absint( $stage_id ), array( 'order_position' => absint( $position ) + 1 ) );
		}

		wp_send_json_success( array( 'message' => __( 'Order saved.', 'processflow-manager' ) ) );
	}

	// ------------------------------------------------------------------ //
	// ProcessFlow Users AJAX handlers                                      //
	// ------------------------------------------------------------------ //

	private function ajax_create_pf_user() {
		$data = array(
			'username'  => isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '',
			'email'     => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'password'  => isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'role'      => isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'operator',
			'is_active' => isset( $_POST['is_active'] ) ? absint( $_POST['is_active'] ) : 1,
		);

		if ( empty( $data['username'] ) || empty( $data['password'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Username and password are required.', 'processflow-manager' ) ) );
		}

		$result = $this->db->create_pf_user( $data );

		is_wp_error( $result )
			? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
			: wp_send_json_success( array( 'user_id' => $result, 'message' => __( 'User created successfully.', 'processflow-manager' ) ) );
	}

	private function ajax_update_pf_user() {
		$id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'processflow-manager' ) ) );
		}

		$data = array();
		if ( isset( $_POST['username'] ) ) {
			$data['username'] = sanitize_user( wp_unslash( $_POST['username'] ) );
		}
		if ( isset( $_POST['email'] ) ) {
			$data['email'] = sanitize_email( wp_unslash( $_POST['email'] ) );
		}
		if ( ! empty( $_POST['password'] ) ) {
			$data['password'] = wp_unslash( $_POST['password'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		if ( isset( $_POST['role'] ) ) {
			$data['role'] = sanitize_key( wp_unslash( $_POST['role'] ) );
		}
		if ( isset( $_POST['is_active'] ) ) {
			$data['is_active'] = absint( $_POST['is_active'] );
		}

		$result = $this->db->update_pf_user( $id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'User updated successfully.', 'processflow-manager' ) ) );
	}

	private function ajax_delete_pf_user() {
		$id     = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$result = $this->db->delete_pf_user( $id );

		$result
			? wp_send_json_success( array( 'message' => __( 'User deleted.', 'processflow-manager' ) ) )
			: wp_send_json_error( array( 'message' => __( 'Failed to delete user.', 'processflow-manager' ) ) );
	}

	private function ajax_get_pf_users() {
		$users = $this->db->get_pf_users();
		wp_send_json_success( array( 'users' => $users ) );
	}

	// ------------------------------------------------------------------ //
	// Invoice Ninja AJAX handler                                           //
	// ------------------------------------------------------------------ //

	private function ajax_sync_invoice_ninja() {
		$url   = $this->settings->get_setting( 'invoiceninja_url', '' );
		$token = $this->settings->get_setting( 'invoiceninja_token', '' );

		$ninja    = new ProcessFlow_InvoiceNinja();
		$invoices = $ninja->sync_invoices( $url, $token );

		if ( is_wp_error( $invoices ) ) {
			wp_send_json_error( array( 'message' => $invoices->get_error_message() ) );
		}

		$created = 0;
		$errors  = 0;
		foreach ( $invoices as $invoice_data ) {
			$result = $this->db->create_order( array(
				'customer_name' => $invoice_data['customer_name'],
				'business_name' => $invoice_data['business_name'],
				'whatsapp'      => $invoice_data['whatsapp'],
				'job_details'   => $invoice_data['job_details'],
				'current_stage' => null,
			) );
			is_wp_error( $result ) ? $errors++ : $created++;
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: 1: created count, 2: error count */
				__( 'Sync complete: %1$d orders created, %2$d errors.', 'processflow-manager' ),
				$created,
				$errors
			),
			'created' => $created,
			'errors'  => $errors,
			'total'   => count( $invoices ),
		) );
	}

	// ------------------------------------------------------------------ //
	// CSV Import / Template AJAX handlers                                  //
	// ------------------------------------------------------------------ //

	/**
	 * Stream a pre-filled CSV import template to the browser.
	 *
	 * This handler uses native PHP file operations instead of the WordPress
	 * Filesystem API because the output must be streamed directly to the HTTP
	 * response via `php://output` — the WP filesystem abstraction does not
	 * support in-memory stream targets and would produce an unwanted temp file.
	 */
	private function ajax_csv_template() {
		// Output CSV file directly.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="processflow-import-template.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'customer_name', 'business_name', 'whatsapp', 'job_details', 'stage_name' ) );
		fputcsv( $out, array( 'John Smith', 'Smith & Co', '+27821234567', 'Business cards x500', 'In Design' ) );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private function ajax_import_csv() {
		if ( empty( $_FILES['csv_file'] ) || ! isset( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'processflow-manager' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tmp_path = $_FILES['csv_file']['tmp_name'];
		if ( ! is_uploaded_file( $tmp_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file upload.', 'processflow-manager' ) ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $tmp_path, 'r' );
		if ( ! $handle ) {
			wp_send_json_error( array( 'message' => __( 'Could not read the uploaded file.', 'processflow-manager' ) ) );
		}

		// Read and validate header row.
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_send_json_error( array( 'message' => __( 'File is empty or not a valid CSV.', 'processflow-manager' ) ) );
		}
		$headers = array_map( 'strtolower', array_map( 'trim', $headers ) );
		$required = array( 'customer_name', 'business_name', 'whatsapp', 'job_details' );
		foreach ( $required as $col ) {
			if ( ! in_array( $col, $headers, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				wp_send_json_error( array(
					'message' => sprintf(
						/* translators: %s = missing column name */
						__( 'Missing required column: %s', 'processflow-manager' ),
						$col
					),
				) );
			}
		}

		// Build a stage name → ID lookup.
		$all_stages = $this->db->get_stages();
		$stage_map  = array();
		foreach ( $all_stages as $s ) {
			$stage_map[ strtolower( $s->name ) ] = (int) $s->id;
		}

		$created = 0;
		$skipped = 0;
		$row_num = 1;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;
			$row_data = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );

			$customer_name = sanitize_text_field( trim( $row_data['customer_name'] ?? '' ) );
			if ( empty( $customer_name ) ) {
				$skipped++;
				continue;
			}

			$stage_name  = strtolower( trim( $row_data['stage_name'] ?? '' ) );
			$stage_id    = isset( $stage_map[ $stage_name ] ) ? $stage_map[ $stage_name ] : null;

			$result = $this->db->create_order( array(
				'customer_name' => $customer_name,
				'business_name' => sanitize_text_field( trim( $row_data['business_name'] ?? '' ) ),
				'whatsapp'      => sanitize_text_field( trim( $row_data['whatsapp'] ?? '' ) ),
				'job_details'   => sanitize_textarea_field( trim( $row_data['job_details'] ?? '' ) ),
				'current_stage' => $stage_id,
			) );

			is_wp_error( $result ) ? $skipped++ : $created++;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: 1: created count, 2: skipped count */
				__( 'Import complete: %1$d orders created, %2$d rows skipped.', 'processflow-manager' ),
				$created,
				$skipped
			),
			'created' => $created,
			'skipped' => $skipped,
		) );
	}

	// ------------------------------------------------------------------ //
	// Shortcode – front-end admin dashboard                               //
	// ------------------------------------------------------------------ //

	/**
	 * Render the [processflow_admin_dashboard] shortcode.
	 *
	 * Uses a transient-based session with a standalone password (not WP users).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function admin_dashboard_shortcode( $atts ): string {
		$atts = shortcode_atts( array(), $atts, 'processflow_admin_dashboard' );

		// Ensure scripts/styles are enqueued even if the wp_enqueue_scripts hook
		// already fired and the post-content check missed this page.
		$this->do_frontend_enqueue();

		ob_start();

		// Handle logout before session check so cookie is cleared immediately.
		if ( isset( $_POST['processflow_admin_logout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->handle_shortcode_logout();
		}

		if ( ! $this->check_shortcode_session() ) {
			// Handle login form submission.
			if ( isset( $_POST['processflow_admin_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->handle_shortcode_login();
			} else {
				require_once PROCESSFLOW_PLUGIN_DIR . 'templates/admin-dashboard.php';
			}
		} else {
			$this->render_shortcode_dashboard();
		}

		return ob_get_clean();
	}

	/**
	 * Process the shortcode logout form.
	 */
	private function handle_shortcode_logout() {
		// Verify nonce before destroying the session.
		if ( ! isset( $_POST['processflow_logout_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['processflow_logout_nonce'] ) ), 'processflow_admin_logout' )
		) {
			return; // Invalid request – ignore silently.
		}

		$token = isset( $_COOKIE['pf_admin_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pf_admin_token'] ) ) : '';
		if ( $token ) {
			delete_transient( 'processflow_admin_session_' . $token );
		}
		setcookie( 'pf_admin_token', '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE['pf_admin_token'] );
	}

	/**
	 * Process the shortcode login form.
	 */
	private function handle_shortcode_login() {
		if ( ! isset( $_POST['processflow_login_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['processflow_login_nonce'] ) ), 'processflow_shortcode_login' )
		) {
			echo '<p class="pf-login-error">' . esc_html__( 'Security check failed.', 'processflow-manager' ) . '</p>';
			require_once PROCESSFLOW_PLUGIN_DIR . 'templates/admin-dashboard.php';
			return;
		}

		$username = isset( $_POST['processflow_username'] ) ? sanitize_user( wp_unslash( $_POST['processflow_username'] ) ) : '';
		$password = isset( $_POST['processflow_password'] ) ? wp_unslash( $_POST['processflow_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$role = 'admin';

		// First: try processflow_users table if username supplied.
		if ( ! empty( $username ) ) {
			$pf_user = $this->db->get_pf_user_by_username( $username );
			if ( $pf_user && wp_check_password( $password, $pf_user->password_hash ) ) {
				$role = $pf_user->role;
				$this->set_shortcode_session( $role );
				$this->render_shortcode_dashboard();
				return;
			}
		}

		// Fallback: legacy single-password mode (any username or blank).
		if ( $this->settings->verify_admin_password( $password ) ) {
			$this->set_shortcode_session( 'admin' );
			$this->render_shortcode_dashboard();
		} else {
			echo '<p class="pf-login-error">' . esc_html__( 'Incorrect username or password.', 'processflow-manager' ) . '</p>';
			require_once PROCESSFLOW_PLUGIN_DIR . 'templates/admin-dashboard.php';
		}
	}

	/**
	 * Store a new shortcode session token in a transient + cookie.
	 *
	 * @param string $role 'admin' or 'operator'.
	 */
	private function set_shortcode_session( string $role ) {
		$token = wp_generate_password( 32, false );
		set_transient( 'processflow_admin_session_' . $token, array( 'role' => $role ), HOUR_IN_SECONDS * 4 );
		setcookie( 'pf_admin_token', $token, time() + HOUR_IN_SECONDS * 4, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE['pf_admin_token'] = $token;
	}

	/**
	 * Render the full dashboard inside the shortcode context.
	 */
	private function render_shortcode_dashboard() {
		$db            = $this->db;
		$order_manager = $this->order_manager;
		$qr_engine     = $this->qr_engine;
		$whatsapp      = $this->whatsapp;
		$settings      = $this->settings;
		$session_role  = $this->get_session_role();

		require_once PROCESSFLOW_PLUGIN_DIR . 'templates/frontend-admin.php';
	}

	/**
	 * Check whether the current visitor has a valid shortcode admin session.
	 *
	 * @return bool
	 */
	private function check_shortcode_session(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$token = isset( $_COOKIE['pf_admin_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pf_admin_token'] ) ) : '';
		if ( empty( $token ) ) {
			return false;
		}
		return (bool) get_transient( 'processflow_admin_session_' . $token );
	}

	/**
	 * Return the role stored in the current session ('admin' or 'operator').
	 *
	 * Falls back to 'admin' for WP admins and legacy sessions.
	 *
	 * @return string
	 */
	private function get_session_role(): string {
		if ( current_user_can( 'manage_options' ) ) {
			return 'admin';
		}
		$token = isset( $_COOKIE['pf_admin_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pf_admin_token'] ) ) : '';
		if ( empty( $token ) ) {
			return 'operator';
		}
		$session = get_transient( 'processflow_admin_session_' . $token );
		if ( is_array( $session ) && isset( $session['role'] ) ) {
			return $session['role'];
		}
		// Legacy sessions (stored as 1) are treated as admin.
		return $session ? 'admin' : 'operator';
	}
}
