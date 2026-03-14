<?php
/**
 * WhatsApp messaging utilities.
 *
 * Builds wa.me deep-links and processes message templates.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_WhatsApp
 */
class ProcessFlow_WhatsApp {

	/** @var ProcessFlow_Database */
	private $db;

	/**
	 * @param ProcessFlow_Database $db Database instance.
	 */
	public function __construct( ProcessFlow_Database $db ) {
		$this->db = $db;
	}

	// ------------------------------------------------------------------ //
	// Public API                                                           //
	// ------------------------------------------------------------------ //

	/**
	 * Generate a wa.me deep-link for an order / stage combination.
	 *
	 * @param int $order_id Order ID.
	 * @param int $stage_id Stage ID.
	 * @return string wa.me URL (falls back gracefully if data is missing).
	 */
	public function get_whatsapp_link( int $order_id, int $stage_id ): string {
		$order = $this->db->get_order( $order_id );
		$stage = $this->db->get_stage( $stage_id );

		if ( ! $order || ! $stage ) {
			return '#';
		}

		$template = $stage->whatsapp_template ?: "Hi {customer_name}, your order #{order_id} is now at stage: {stage_name}.";
		$message  = $this->parse_template( $template, $order, $stage );
		$phone    = $this->format_whatsapp_number( $order->whatsapp );

		return $this->get_whatsapp_url( $phone, $message );
	}

	/**
	 * Replace all merge tags in a template string.
	 *
	 * Supported tags: {customer_name}, {business_name}, {stage_name},
	 *                 {order_id}, {date}, {time}.
	 *
	 * @param string    $template  Raw template with merge tags.
	 * @param object    $order_data Order row object.
	 * @param object    $stage_data Stage row object.
	 * @return string
	 */
	public function parse_template( string $template, $order_data, $stage_data ): string {
		$replacements = array(
			'{customer_name}' => isset( $order_data->customer_name ) ? $order_data->customer_name : '',
			'{business_name}' => isset( $order_data->business_name ) ? $order_data->business_name : '',
			'{stage_name}'    => isset( $stage_data->name ) ? $stage_data->name : '',
			'{order_id}'      => isset( $order_data->id ) ? (string) $order_data->id : '',
			'{date}'          => current_time( 'd/m/Y' ),
			'{time}'          => current_time( 'H:i' ),
		);

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$template
		);
	}

	/**
	 * Validate that a phone number is in international format.
	 *
	 * @param string $number Raw phone number.
	 * @return bool
	 */
	public function validate_whatsapp_number( string $number ): bool {
		$clean = preg_replace( '/[^0-9+]/', '', $number );
		return (bool) preg_match( '/^\+[1-9]\d{6,14}$/', $clean );
	}

	/**
	 * Strip non-digit characters and ensure the number starts with '+'.
	 *
	 * @param string $number Raw phone number.
	 * @return string Normalised number.
	 */
	public function format_whatsapp_number( string $number ): string {
		// Remove everything except digits and the leading '+'.
		$stripped = preg_replace( '/[^0-9+]/', '', $number );

		// If the number begins with 00, convert to '+'.
		if ( substr( $stripped, 0, 2 ) === '00' ) {
			$stripped = '+' . substr( $stripped, 2 );
		}

		// Ensure it has a '+' prefix.
		if ( substr( $stripped, 0, 1 ) !== '+' ) {
			$stripped = '+' . $stripped;
		}

		return $stripped;
	}

	/**
	 * Log a stage notification and mark it in the DB.
	 *
	 * @param int $order_id Order ID.
	 * @param int $stage_id Stage ID.
	 */
	public function send_stage_notification( int $order_id, int $stage_id ) {
		$this->db->mark_notification_sent( $order_id );

		// Log to the WordPress debug log when WP_DEBUG_LOG is enabled.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'ProcessFlow: notification sent for order #%d → stage #%d', $order_id, $stage_id ) );
		}
	}

	/**
	 * Build the full wa.me redirect URL.
	 *
	 * @param string $phone   International phone number (with +).
	 * @param string $message Plain-text message.
	 * @return string
	 */
	public function get_whatsapp_url( string $phone, string $message ): string {
		// wa.me expects the number without '+'.
		$clean_phone = ltrim( $phone, '+' );
		return 'https://wa.me/' . rawurlencode( $clean_phone ) . '?text=' . rawurlencode( $message );
	}
}
