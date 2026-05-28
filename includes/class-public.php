<?php
/**
 * Public-facing user portal.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_Public
 */
class ProcessFlow_Public {

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

	public function enqueue_styles() {
		wp_enqueue_style(
			'processflow-public',
			PROCESSFLOW_PLUGIN_URL . 'public/css/processflow-public.css',
			array(),
			PROCESSFLOW_VERSION
		);
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			'processflow-public',
			PROCESSFLOW_PLUGIN_URL . 'public/js/processflow-public.js',
			array( 'jquery' ),
			PROCESSFLOW_VERSION,
			true
		);
		wp_localize_script(
			'processflow-public',
			'processflowPublic',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'processflow_public_nonce' ),
				'strings'  => array(
					'loading'      => __( 'Loading…', 'processflow-manager' ),
					'error'        => __( 'An error occurred. Please try again.', 'processflow-manager' ),
					'not_found'    => __( 'Order not found. Please check your details and try again.', 'processflow-manager' ),
					'copied'       => __( 'Copied!', 'processflow-manager' ),
				),
			)
		);
	}

	// ------------------------------------------------------------------ //
	// Shortcode                                                            //
	// ------------------------------------------------------------------ //

	/**
	 * Render the [processflow_user_portal] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML.
	 */
	public function user_portal_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title' => $this->settings->get_setting( 'portal_title', __( 'Track Your Order', 'processflow-manager' ) ),
			),
			$atts,
			'processflow_user_portal'
		);

		ob_start();
		$settings = $this->settings;
		$db       = $this->db;
		require_once PROCESSFLOW_PLUGIN_DIR . 'public/partials/user-portal.php';
		return ob_get_clean();
	}

	/**
	 * Render the [processflow_qr_scan] shortcode.
	 *
	 * Reads `order_id` and `hash` from the current URL query string, validates
	 * the QR code, and displays the order status page with a WhatsApp button.
	 *
	 * @param array $atts Shortcode attributes (unused – reserved for future use).
	 * @return string HTML.
	 */
	public function qr_scan_shortcode( $atts ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$hash     = isset( $_GET['hash'] ) ? sanitize_text_field( wp_unslash( $_GET['hash'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		ob_start();

		if ( ! $order_id || ! $hash ) {
			$error_msg = __( 'No order information found. Please scan a valid QR code.', 'processflow-manager' );
			require PROCESSFLOW_PLUGIN_DIR . 'public/partials/qr-scan.php';
			return ob_get_clean();
		}

		$data = $this->qr_engine->get_scan_page_data( $order_id, $hash );

		if ( is_wp_error( $data ) ) {
			$error_msg = $data->get_error_message();
			require PROCESSFLOW_PLUGIN_DIR . 'public/partials/qr-scan.php';
			return ob_get_clean();
		}

		$error_msg = '';
		$order     = $data['order'];
		$stage     = $data['stage'];
		$stages    = $data['stages'];
		$history   = $data['history'];
		$wa_url    = $data['wa_url'];

		require PROCESSFLOW_PLUGIN_DIR . 'public/partials/qr-scan.php';
		return ob_get_clean();
	}

	// ------------------------------------------------------------------ //
	// AJAX handlers                                                        //
	// ------------------------------------------------------------------ //

	/**
	 * Register all public-facing AJAX handlers.
	 */
	public function handle_ajax_requests() {
		add_action( 'wp_ajax_nopriv_processflow_portal_login', array( $this, 'ajax_portal_login' ) );
		add_action( 'wp_ajax_processflow_portal_login', array( $this, 'ajax_portal_login' ) );
		add_action( 'wp_ajax_nopriv_processflow_lookup_order', array( $this, 'ajax_lookup_order' ) );
		add_action( 'wp_ajax_processflow_lookup_order', array( $this, 'ajax_lookup_order' ) );
		add_action( 'wp_ajax_nopriv_processflow_qr_update_stage', array( $this, 'ajax_qr_update_stage' ) );
		add_action( 'wp_ajax_processflow_qr_update_stage', array( $this, 'ajax_qr_update_stage' ) );
	}

	/**
	 * Validate portal login (legacy endpoint kept for backwards compatibility).
	 */
	public function ajax_portal_login() {
		check_ajax_referer( 'processflow_public_nonce', 'nonce' );

		$order_ref   = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';

		if ( '' === $order_ref ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid order number.', 'processflow-manager' ) ) );
		}

		$order = $this->db->get_order_by_invoice_number( $order_ref );
		if ( ! $order && ctype_digit( $order_ref ) ) {
			$order = $this->db->get_order( absint( $order_ref ) );
		}
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'processflow-manager' ) ) );
		}

		// Issue a short-lived transient session.
		$token = wp_generate_password( 32, false );
		set_transient( 'processflow_portal_session_' . $token, (int) $order->id, HOUR_IN_SECONDS * 2 );

		wp_send_json_success( array(
			'token'    => $token,
			'order_id' => (int) $order->id,
		) );
	}

	/**
	 * Update the stage for an order via the QR scan landing page.
	 *
	 * Authentication is done by validating the QR code hash — anyone who
	 * has physically scanned the QR label can update the stage.  No admin
	 * login is required, which is intentional for workshop-floor use.
	 */
	public function ajax_qr_update_stage() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$hash     = isset( $_POST['hash'] )     ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';
		$stage_id = isset( $_POST['stage_id'] ) ? absint( $_POST['stage_id'] ) : 0;
		// phpcs:enable

		if ( ! $order_id || ! $hash ) {
			wp_send_json_error( array( 'message' => __( 'Missing order information.', 'processflow-manager' ) ) );
		}

		$order = $this->db->get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'processflow-manager' ) ) );
		}

		// Validate the QR code hash — this is the only auth check needed here.
		if ( ! hash_equals( (string) $order->qr_code_hash, $hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid QR code.', 'processflow-manager' ) ) );
		}

		$result = $this->db->update_order( $order_id, array( 'current_stage' => $stage_id ?: null ) );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update stage.', 'processflow-manager' ) ) );
		}

		$stage  = $stage_id ? $this->db->get_stage( $stage_id ) : null;
		$wa_url = $stage_id ? $this->whatsapp->get_whatsapp_link( $order_id, $stage_id ) : '#';

		wp_send_json_success( array(
			'stage_id'   => $stage_id,
			'stage_name' => $stage ? $stage->name : '',
			'stage_color'=> $stage ? $stage->color : '#aaa',
			'wa_url'     => $wa_url,
			'message'    => __( 'Stage updated successfully.', 'processflow-manager' ),
		) );
	}

	/**
	 * Return order status for public tracking by invoice/order number.
	 *
	 * Anyone can see queue/progress. Admin-authenticated viewers can also see
	 * internal details such as notes and full order metadata.
	 */
	public function ajax_lookup_order() {
		check_ajax_referer( 'processflow_public_nonce', 'nonce' );

		$order_ref = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
		$token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$order_id = 0;

		if ( '' !== $order_ref ) {
			$order = $this->db->get_order_by_invoice_number( $order_ref );
			if ( ! $order && ctype_digit( $order_ref ) ) {
				$order = $this->db->get_order( absint( $order_ref ) );
			}
			if ( ! $order ) {
				wp_send_json_error( array( 'message' => __( 'Order not found.', 'processflow-manager' ) ) );
			}
			$order_id = (int) $order->id;
		} elseif ( '' !== $token ) {
			// Backward-compatible token flow for older portal JS.
			$order_id = (int) get_transient( 'processflow_portal_session_' . $token );
		}

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your invoice/order number.', 'processflow-manager' ) ) );
		}

		$order = $this->order_manager->get_order_with_stage( $order_id );
		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => $order->get_error_message() ) );
		}

		$stages         = $this->db->get_stages();
		$is_admin_view  = $this->can_view_admin_tracking_details();
		$order_payload  = array(
			'id'             => (int) $order->id,
			'invoice_number' => isset( $order->invoice_number ) ? $order->invoice_number : '',
			'current_stage'  => (int) $order->current_stage,
			'stage_name'     => isset( $order->stage_name ) ? $order->stage_name : '',
			'stage_color'    => isset( $order->stage_color ) ? $order->stage_color : '#666',
			'created_at'     => isset( $order->created_at ) ? $order->created_at : '',
			'updated_at'     => isset( $order->updated_at ) ? $order->updated_at : '',
		);

		if ( $is_admin_view ) {
			$order_payload['customer_name'] = isset( $order->customer_name ) ? $order->customer_name : '';
			$order_payload['business_name'] = isset( $order->business_name ) ? $order->business_name : '';
			$order_payload['whatsapp']      = isset( $order->whatsapp ) ? $order->whatsapp : '';
			$order_payload['job_details']   = isset( $order->job_details ) ? $order->job_details : '';
		}

		$response = array(
			'is_admin_view' => $is_admin_view,
			'order'         => $order_payload,
			'stages'        => $stages,
			'history'       => array(),
			'wa_url'        => '',
		);

		if ( $is_admin_view ) {
			$response['history'] = $this->db->get_stage_history( $order_id );
			$response['wa_url']  = $this->whatsapp->get_whatsapp_link( $order_id, (int) $order->current_stage );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Determine whether the current viewer may access admin-only tracking details.
	 *
	 * @return bool
	 */
	private function can_view_admin_tracking_details(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$token = isset( $_COOKIE['pf_admin_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pf_admin_token'] ) ) : '';
		if ( '' === $token ) {
			return false;
		}

		$session = get_transient( 'processflow_admin_session_' . $token );
		if ( is_array( $session ) && isset( $session['role'] ) ) {
			return 'admin' === $session['role'];
		}

		// Legacy sessions stored as a truthy scalar are treated as admin.
		return (bool) $session;
	}
}
