<?php
/**
 * QR Code generation and scan handling.
 *
 * Uses the Google Charts API for free, serverless QR image generation.
 * When a QR is scanned the order stage is advanced and the browser is
 * redirected to the relevant WhatsApp deep-link.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_QR_Engine
 */
class ProcessFlow_QR_Engine {

	/** @var ProcessFlow_Database */
	private $db;

	/**
	 * @param ProcessFlow_Database $db Database instance.
	 */
	public function __construct( ProcessFlow_Database $db ) {
		$this->db = $db;
	}

	// ------------------------------------------------------------------ //
	// URL helpers                                                          //
	// ------------------------------------------------------------------ //

	/**
	 * Build the URL that will be encoded inside the QR code.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $qr_hash  QR hash stored on the order row.
	 * @return string
	 */
	public function get_scan_url( int $order_id, string $qr_hash ): string {
		return add_query_arg(
			array(
				'processflow_scan' => '1',
				'order_id'         => $order_id,
				'hash'             => rawurlencode( $qr_hash ),
			),
			site_url( '/' )
		);
	}

	/**
	 * Return the Google Charts API URL that renders the QR PNG.
	 *
	 * @param int $order_id Order ID.
	 * @return string|WP_Error
	 */
	public function get_qr_image_url( int $order_id ) {
		$order = $this->db->get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'processflow-manager' ) );
		}

		$scan_url = $this->get_scan_url( $order_id, $order->qr_code_hash );

		return 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . rawurlencode( $scan_url ) . '&choe=UTF-8';
	}

	/**
	 * Alias kept for external callers.
	 *
	 * @param int $order_id Order ID.
	 * @return string|WP_Error
	 */
	public function generate_qr( int $order_id ) {
		return $this->get_qr_image_url( $order_id );
	}

	// ------------------------------------------------------------------ //
	// Scan endpoint                                                        //
	// ------------------------------------------------------------------ //

	/**
	 * Register the custom query variable and attach the handler.
	 * Called during the `init` action.
	 */
	public function register_scan_endpoint() {
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'init', array( $this, 'handle_scan_request' ) );
	}

	/**
	 * Add plugin-specific query variables to WordPress.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'processflow_scan';
		$vars[] = 'order_id';
		$vars[] = 'hash';
		return $vars;
	}

	/**
	 * Handle incoming QR scan requests.
	 *
	 * Advances the stage then redirects the browser to WhatsApp.
	 */
	public function handle_scan_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['processflow_scan'] ) ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$hash     = isset( $_GET['hash'] ) ? sanitize_text_field( wp_unslash( $_GET['hash'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id || ! $hash ) {
			wp_die( esc_html__( 'Invalid QR code.', 'processflow-manager' ), '', array( 'response' => 400 ) );
		}

		$result = $this->process_scan( $order_id, $hash );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		// $result is the WhatsApp URL – redirect the browser.
		wp_redirect( $result ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Validate the scan, advance the stage and return the WhatsApp redirect URL.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $hash     QR hash from query string.
	 * @return string|WP_Error WhatsApp URL on success.
	 */
	public function process_scan( int $order_id, string $hash ) {
		$order = $this->db->get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'processflow-manager' ) );
		}

		if ( ! hash_equals( $order->qr_code_hash, $hash ) ) {
			return new WP_Error( 'invalid_hash', __( 'Invalid QR code hash.', 'processflow-manager' ) );
		}

		// Advance to the next stage.
		$order_manager = new ProcessFlow_Order_Manager( $this->db );
		$stage_result  = $order_manager->advance_stage( $order_id );

		// Determine which stage is now active (even if already at last stage).
		$current_stage_id = is_wp_error( $stage_result ) ? (int) $order->current_stage : (int) $stage_result;

		// Build the WhatsApp notification link.
		$whatsapp = new ProcessFlow_WhatsApp( $this->db );
		$wa_url   = $whatsapp->get_whatsapp_link( $order_id, $current_stage_id );

		// Log the notification.
		$whatsapp->send_stage_notification( $order_id, $current_stage_id );

		return $wa_url;
	}

	// ------------------------------------------------------------------ //
	// PDF / print labels                                                   //
	// ------------------------------------------------------------------ //

	/**
	 * Generate an HTML page containing QR code labels for the given order IDs.
	 *
	 * The returned string can be written to a response or opened in a new
	 * window for browser printing.
	 *
	 * @param int[] $order_ids List of order IDs.
	 * @return string HTML string.
	 */
	public function generate_pdf_labels( array $order_ids ): string {
		$labels_html = '';

		foreach ( $order_ids as $raw_id ) {
			$order_id = absint( $raw_id );
			$order    = $this->db->get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$qr_url   = $this->get_qr_image_url( $order_id );
			$qr_url   = is_wp_error( $qr_url ) ? '' : esc_url( $qr_url );
			$job_info = esc_html( $order->customer_name ) . ' – ' . esc_html( $order->business_name );

			$labels_html .= sprintf(
				'<div class="pf-label">
					<div class="pf-label__qr">
						<img src="%s" alt="QR #%d" width="180" height="180">
					</div>
					<div class="pf-label__info">
						<p class="pf-label__order-id"><strong>Order #%d</strong></p>
						<p class="pf-label__name">%s</p>
						<p class="pf-label__job">%s</p>
					</div>
				</div>',
				$qr_url,
				$order_id,
				$order_id,
				$job_info,
				esc_html( $order->job_details )
			);
		}

		return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>' . esc_html__( 'QR Code Labels – ProcessFlow', 'processflow-manager' ) . '</title>
<style>
  body { font-family: Arial, sans-serif; margin: 0; padding: 10px; }
  .pf-label {
    display: inline-block;
    width: 200px;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 10px;
    margin: 8px;
    vertical-align: top;
    page-break-inside: avoid;
  }
  .pf-label__qr { text-align: center; }
  .pf-label__info { margin-top: 8px; font-size: 12px; }
  .pf-label__order-id { font-size: 14px; margin: 0 0 4px; }
  .pf-label__name, .pf-label__job { margin: 2px 0; color: #555; }
  @media print {
    body { margin: 0; padding: 0; }
    .pf-label { border-color: #999; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
<p class="no-print" style="text-align:center;">
  <button onclick="window.print()" style="padding:8px 20px;font-size:14px;cursor:pointer;">
    &#128438; ' . esc_html__( 'Print Labels', 'processflow-manager' ) . '
  </button>
</p>
' . $labels_html . '
</body>
</html>';
	}
}
