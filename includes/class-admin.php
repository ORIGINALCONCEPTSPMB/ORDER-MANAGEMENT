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
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'dispatch_ajax' ) );
		}
	}

	/**
	 * Central AJAX dispatcher – verifies nonce then calls the right method.
	 */
	public function dispatch_ajax() {
		// Nonce check.
		check_ajax_referer( 'processflow_admin_nonce', 'nonce' );

		// Capability check (WP admin OR shortcode session).
		if ( ! current_user_can( 'manage_options' ) && ! $this->check_shortcode_session() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'processflow-manager' ) ), 403 );
		}

		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

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

	private function ajax_save_settings() {
		$allowed = array(
			'company_name',
			'company_phone',
			'company_email',
			'portal_title',
			'portal_intro',
			'orders_per_page',
			'enable_whatsapp',
		);

		foreach ( $allowed as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$this->settings->update_setting( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
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

		ob_start();

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
	 * Process the shortcode login form.
	 */
	private function handle_shortcode_login() {
		if ( ! isset( $_POST['processflow_login_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['processflow_login_nonce'] ) ), 'processflow_shortcode_login' )
		) {
			echo '<p class="pf-error">' . esc_html__( 'Security check failed.', 'processflow-manager' ) . '</p>';
			require_once PROCESSFLOW_PLUGIN_DIR . 'templates/admin-dashboard.php';
			return;
		}

		$password = isset( $_POST['processflow_password'] ) ? sanitize_text_field( wp_unslash( $_POST['processflow_password'] ) ) : '';

		if ( $this->settings->verify_admin_password( $password ) ) {
			$token = wp_generate_password( 32, false );
			set_transient( 'processflow_admin_session_' . $token, 1, HOUR_IN_SECONDS * 4 );
			// Store token in a cookie.
			setcookie( 'pf_admin_token', $token, time() + HOUR_IN_SECONDS * 4, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			$_COOKIE['pf_admin_token'] = $token;
			$this->render_shortcode_dashboard();
		} else {
			echo '<p class="pf-error">' . esc_html__( 'Incorrect password.', 'processflow-manager' ) . '</p>';
			require_once PROCESSFLOW_PLUGIN_DIR . 'templates/admin-dashboard.php';
		}
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

		require_once PROCESSFLOW_PLUGIN_DIR . 'admin/partials/dashboard.php';
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
}
