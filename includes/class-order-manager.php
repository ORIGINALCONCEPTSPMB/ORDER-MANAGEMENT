<?php
/**
 * Order business-logic layer.
 *
 * Validation, sanitization and high-level workflow operations live here.
 * Raw DB access is delegated to ProcessFlow_Database.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_Order_Manager
 */
class ProcessFlow_Order_Manager {

	/** @var ProcessFlow_Database */
	private $db;

	/**
	 * @param ProcessFlow_Database $db Database instance.
	 */
	public function __construct( ProcessFlow_Database $db ) {
		$this->db = $db;
	}

	// ------------------------------------------------------------------ //
	// CRUD                                                                 //
	// ------------------------------------------------------------------ //

	/**
	 * Validate, sanitize and persist a new order.
	 *
	 * @param array $data Raw input data.
	 * @return int|WP_Error New order ID or validation/DB error.
	 */
	public function create_order( array $data ) {
		$sanitized = $this->sanitize_order_data( $data );
		$errors    = $this->validate_order_data( $sanitized );

		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		// Assign to the first active stage if not explicitly set.
		if ( empty( $sanitized['current_stage'] ) ) {
			$stages = $this->db->get_stages();
			if ( ! empty( $stages ) ) {
				$sanitized['current_stage'] = (int) $stages[0]->id;
			}
		}

		$order_id = $this->db->create_order( $sanitized );

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		// Record the initial stage in history.
		if ( ! empty( $sanitized['current_stage'] ) ) {
			$this->db->add_stage_history( $order_id, (int) $sanitized['current_stage'] );
		}

		return $order_id;
	}

	/**
	 * Validate, sanitize and update an existing order.
	 *
	 * @param int   $id   Order ID.
	 * @param array $data Raw input data.
	 * @return bool|WP_Error
	 */
	public function update_order( int $id, array $data ) {
		$sanitized = $this->sanitize_order_data( $data );
		$errors    = $this->validate_order_data( $sanitized );

		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		return $this->db->update_order( $id, $sanitized );
	}

	/**
	 * Advance an order to the next stage in sequence.
	 *
	 * @param int $order_id Order ID.
	 * @return int|WP_Error New stage ID or error.
	 */
	public function advance_stage( int $order_id ) {
		$order = $this->db->get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'processflow-manager' ) );
		}

		$stages         = $this->db->get_stages();
		$current_pos    = null;
		$next_stage     = null;

		foreach ( $stages as $index => $stage ) {
			if ( (int) $stage->id === (int) $order->current_stage ) {
				$current_pos = $index;
				break;
			}
		}

		if ( null !== $current_pos && isset( $stages[ $current_pos + 1 ] ) ) {
			$next_stage = $stages[ $current_pos + 1 ];
		}

		if ( ! $next_stage ) {
			return new WP_Error( 'no_next_stage', __( 'Already at the last stage.', 'processflow-manager' ) );
		}

		return $this->set_stage( $order_id, (int) $next_stage->id );
	}

	/**
	 * Set an order to a specific stage.
	 *
	 * @param int $order_id Order ID.
	 * @param int $stage_id Target stage ID.
	 * @return int|WP_Error The stage ID that was set, or WP_Error.
	 */
	public function set_stage( int $order_id, int $stage_id ) {
		$stage = $this->db->get_stage( $stage_id );
		if ( ! $stage ) {
			return new WP_Error( 'not_found', __( 'Stage not found.', 'processflow-manager' ) );
		}

		$updated = $this->db->update_order( $order_id, array( 'current_stage' => $stage_id ) );
		if ( ! $updated ) {
			return new WP_Error( 'db_error', __( 'Failed to update order stage.', 'processflow-manager' ) );
		}

		$this->db->add_stage_history( $order_id, $stage_id );

		return $stage_id;
	}

	/**
	 * Delete an order along with all its history records.
	 *
	 * @param int $id Order ID.
	 * @return bool|WP_Error
	 */
	public function delete_order( int $id ) {
		$order = $this->db->get_order( $id );
		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'processflow-manager' ) );
		}

		return $this->db->delete_order( $id );
	}

	/**
	 * Return an order row joined with its current stage data.
	 *
	 * @param int $order_id Order ID.
	 * @return object|WP_Error
	 */
	public function get_order_with_stage( int $order_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT o.*, s.name AS stage_name, s.color AS stage_color,
				        s.order_position AS stage_position,
				        s.whatsapp_template AS stage_template
				 FROM {$wpdb->prefix}processflow_orders o
				 LEFT JOIN {$wpdb->prefix}processflow_stages s ON o.current_stage = s.id
				 WHERE o.id = %d",
				$order_id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'processflow-manager' ) );
		}

		return $row;
	}

	// ------------------------------------------------------------------ //
	// Sanitize / Validate                                                  //
	// ------------------------------------------------------------------ //

	/**
	 * Sanitize all fields in the order data array.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	public function sanitize_order_data( array $data ): array {
		$clean = array();

		if ( isset( $data['customer_name'] ) ) {
			$clean['customer_name'] = sanitize_text_field( $data['customer_name'] );
		}
		if ( isset( $data['business_name'] ) ) {
			$clean['business_name'] = sanitize_text_field( $data['business_name'] );
		}
		if ( isset( $data['whatsapp'] ) ) {
			$clean['whatsapp'] = sanitize_text_field( $data['whatsapp'] );
		}
		if ( isset( $data['invoice_number'] ) ) {
			$clean['invoice_number'] = trim( sanitize_text_field( $data['invoice_number'] ) );
		}
		if ( isset( $data['job_details'] ) ) {
			$clean['job_details'] = sanitize_textarea_field( $data['job_details'] );
		}
		if ( isset( $data['product_lines'] ) ) {
			$clean['product_lines'] = is_string( $data['product_lines'] ) ? $data['product_lines'] : '';
		}
		if ( isset( $data['current_stage'] ) ) {
			$clean['current_stage'] = absint( $data['current_stage'] );
		}
		if ( isset( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
			$clean_cf = array();
			foreach ( $data['custom_fields'] as $key => $value ) {
				$clean_cf[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
			$clean['custom_fields'] = $clean_cf;
		}

		return $clean;
	}

	/**
	 * Validate required fields and WhatsApp number format.
	 *
	 * @param array $data Sanitized data.
	 * @return true|WP_Error
	 */
	public function validate_order_data( array $data ) {
		if ( empty( $data['customer_name'] ) ) {
			return new WP_Error( 'validation', __( 'Customer name is required.', 'processflow-manager' ) );
		}
		if ( empty( $data['whatsapp'] ) ) {
			return new WP_Error( 'validation', __( 'WhatsApp number is required.', 'processflow-manager' ) );
		}

		// Must start with + and contain only digits after that.
		$phone = preg_replace( '/[^0-9+]/', '', $data['whatsapp'] );
		if ( ! preg_match( '/^\+[1-9]\d{6,14}$/', $phone ) ) {
			return new WP_Error(
				'validation',
				__( 'WhatsApp number must be in international format (e.g. +27821234567).', 'processflow-manager' )
			);
		}

		return true;
	}
}
