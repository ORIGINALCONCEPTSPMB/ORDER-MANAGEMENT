<?php
/**
 * Database abstraction layer.
 *
 * All SQL is parameterised via $wpdb->prepare() and never interpolates raw
 * user-supplied values.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_Database
 */
class ProcessFlow_Database {

	// ------------------------------------------------------------------ //
	// Orders                                                               //
	// ------------------------------------------------------------------ //

	/**
	 * Insert a new order row and generate its QR hash.
	 *
	 * @param array $data Associative array of column => value pairs.
	 * @return int|WP_Error New order ID or WP_Error on failure.
	 */
	public function create_order( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_orders',
			array(
				'customer_name' => $data['customer_name'],
				'business_name' => $data['business_name'],
				'whatsapp'      => $data['whatsapp'],
				'job_details'   => $data['job_details'],
				'current_stage' => isset( $data['current_stage'] ) ? absint( $data['current_stage'] ) : null,
				'custom_fields' => isset( $data['custom_fields'] ) ? wp_json_encode( $data['custom_fields'] ) : null,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
				'qr_code_hash'  => '',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create order.', 'processflow-manager' ) );
		}

		$order_id = (int) $wpdb->insert_id;

		// Generate and persist the QR hash using the real auto-increment ID.
		$qr_hash = md5( $order_id . time() . wp_generate_password( 12, false ) );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_orders',
			array( 'qr_code_hash' => $qr_hash ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $order_id;
	}

	/**
	 * Retrieve a single order by its primary key.
	 *
	 * @param int $id Order ID.
	 * @return object|null
	 */
	public function get_order( int $id ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}processflow_orders WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Retrieve a paginated, filtered list of orders.
	 *
	 * @param array $args {
	 *   @type string $search   Fulltext search string.
	 *   @type int    $stage    Filter by stage ID.
	 *   @type int    $per_page Rows per page (default 20).
	 *   @type int    $page     Current page (default 1).
	 * }
	 * @return array { items: object[], total: int }
	 */
	public function get_orders( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'stage'    => 0,
			'per_page' => 20,
			'page'     => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '( customer_name LIKE %s OR business_name LIKE %s OR id LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['stage'] ) ) {
			$where[]  = 'current_stage = %d';
			$params[] = absint( $args['stage'] );
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, absint( $args['per_page'] ) );
		$offset    = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}processflow_orders WHERE $where_sql";
		$data_sql  = "SELECT * FROM {$wpdb->prefix}processflow_orders WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) );
		}

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Update an existing order.
	 *
	 * @param int   $id   Order ID.
	 * @param array $data Column => value pairs to update.
	 * @return bool
	 */
	public function update_order( int $id, array $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		if ( isset( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
			$data['custom_fields'] = wp_json_encode( $data['custom_fields'] );
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_orders',
			$data,
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Delete an order and all related history rows.
	 *
	 * @param int $id Order ID.
	 * @return bool
	 */
	public function delete_order( int $id ) {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_stage_history',
			array( 'order_id' => $id ),
			array( '%d' )
		);

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_orders',
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	// ------------------------------------------------------------------ //
	// Stages                                                               //
	// ------------------------------------------------------------------ //

	/**
	 * Get all active stages ordered by position.
	 *
	 * @return object[]
	 */
	public function get_stages() {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}processflow_stages WHERE is_active = 1 ORDER BY order_position ASC"
		) ?: array();
	}

	/**
	 * Get a single stage by ID (active or inactive).
	 *
	 * @param int $id Stage ID.
	 * @return object|null
	 */
	public function get_stage( int $id ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}processflow_stages WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Create a new workflow stage.
	 *
	 * @param array $data Stage data.
	 * @return int|WP_Error
	 */
	public function create_stage( array $data ) {
		global $wpdb;

		$max_pos = (int) $wpdb->get_var( "SELECT MAX(order_position) FROM {$wpdb->prefix}processflow_stages" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_stages',
			array(
				'name'             => sanitize_text_field( $data['name'] ),
				'order_position'   => isset( $data['order_position'] ) ? absint( $data['order_position'] ) : $max_pos + 1,
				'color'            => sanitize_hex_color( $data['color'] ?? '#000000' ),
				'whatsapp_template' => sanitize_textarea_field( $data['whatsapp_template'] ?? '' ),
				'is_active'        => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create stage.', 'processflow-manager' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a workflow stage.
	 *
	 * @param int   $id   Stage ID.
	 * @param array $data Column => value pairs.
	 * @return bool
	 */
	public function update_stage( int $id, array $data ) {
		global $wpdb;

		$clean = array();
		if ( isset( $data['name'] ) ) {
			$clean['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['order_position'] ) ) {
			$clean['order_position'] = absint( $data['order_position'] );
		}
		if ( isset( $data['color'] ) ) {
			$clean['color'] = sanitize_hex_color( $data['color'] );
		}
		if ( isset( $data['whatsapp_template'] ) ) {
			$clean['whatsapp_template'] = sanitize_textarea_field( $data['whatsapp_template'] );
		}
		if ( isset( $data['is_active'] ) ) {
			$clean['is_active'] = absint( $data['is_active'] );
		}

		if ( empty( $clean ) ) {
			return false;
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_stages',
			$clean,
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Soft-delete a stage (set is_active = 0).
	 *
	 * @param int $id Stage ID.
	 * @return bool
	 */
	public function delete_stage( int $id ) {
		global $wpdb;

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_stages',
			array( 'is_active' => 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	// ------------------------------------------------------------------ //
	// Stage history                                                        //
	// ------------------------------------------------------------------ //

	/**
	 * Record that an order entered a new stage.
	 * The previous open history row is marked as completed.
	 *
	 * @param int $order_id Order ID.
	 * @param int $stage_id Stage ID.
	 * @return int|WP_Error New history row ID or WP_Error.
	 */
	public function add_stage_history( int $order_id, int $stage_id ) {
		global $wpdb;

		// Close any previous open history row.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}processflow_stage_history
				 SET completed_at = %s
				 WHERE order_id = %d AND completed_at IS NULL",
				current_time( 'mysql' ),
				$order_id
			)
		);

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_stage_history',
			array(
				'order_id'          => $order_id,
				'stage_id'          => $stage_id,
				'entered_at'        => current_time( 'mysql' ),
				'completed_at'      => null,
				'notification_sent' => 0,
			),
			array( '%d', '%d', '%s', null, '%d' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to add stage history.', 'processflow-manager' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get the full stage history for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return object[]
	 */
	public function get_stage_history( int $order_id ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT h.*, s.name AS stage_name, s.color AS stage_color
				 FROM {$wpdb->prefix}processflow_stage_history h
				 LEFT JOIN {$wpdb->prefix}processflow_stages s ON h.stage_id = s.id
				 WHERE h.order_id = %d
				 ORDER BY h.entered_at ASC",
				$order_id
			)
		) ?: array();
	}

	/**
	 * Mark the most recent history row for an order as notification-sent.
	 *
	 * @param int $order_id Order ID.
	 */
	public function mark_notification_sent( int $order_id ) {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}processflow_stage_history
				 SET notification_sent = 1
				 WHERE order_id = %d
				 ORDER BY entered_at DESC
				 LIMIT 1",
				$order_id
			)
		);
	}

	// ------------------------------------------------------------------ //
	// Custom fields                                                        //
	// ------------------------------------------------------------------ //

	/**
	 * Get all custom fields ordered by field_order.
	 *
	 * @return object[]
	 */
	public function get_custom_fields() {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}processflow_custom_fields ORDER BY field_order ASC"
		) ?: array();
	}

	/**
	 * Create a custom field.
	 *
	 * @param array $data Field data.
	 * @return int|WP_Error
	 */
	public function create_custom_field( array $data ) {
		global $wpdb;

		$max_order = (int) $wpdb->get_var( "SELECT MAX(field_order) FROM {$wpdb->prefix}processflow_custom_fields" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_custom_fields',
			array(
				'field_label' => sanitize_text_field( $data['field_label'] ),
				'field_type'  => sanitize_key( $data['field_type'] ?? 'text' ),
				'is_required' => isset( $data['is_required'] ) ? absint( $data['is_required'] ) : 0,
				'field_order' => isset( $data['field_order'] ) ? absint( $data['field_order'] ) : $max_order + 1,
			),
			array( '%s', '%s', '%d', '%d' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create custom field.', 'processflow-manager' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a custom field.
	 *
	 * @param int   $id   Field ID.
	 * @param array $data Column => value pairs.
	 * @return bool
	 */
	public function update_custom_field( int $id, array $data ) {
		global $wpdb;

		$clean = array();
		if ( isset( $data['field_label'] ) ) {
			$clean['field_label'] = sanitize_text_field( $data['field_label'] );
		}
		if ( isset( $data['field_type'] ) ) {
			$clean['field_type'] = sanitize_key( $data['field_type'] );
		}
		if ( isset( $data['is_required'] ) ) {
			$clean['is_required'] = absint( $data['is_required'] );
		}
		if ( isset( $data['field_order'] ) ) {
			$clean['field_order'] = absint( $data['field_order'] );
		}

		if ( empty( $clean ) ) {
			return false;
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_custom_fields',
			$clean,
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Delete a custom field.
	 *
	 * @param int $id Field ID.
	 * @return bool
	 */
	public function delete_custom_field( int $id ) {
		global $wpdb;

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_custom_fields',
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	// ------------------------------------------------------------------ //
	// ProcessFlow Platform Users                                           //
	// ------------------------------------------------------------------ //

	/**
	 * Create a new platform user.
	 *
	 * @param array $data { username, email, password (plain-text), role }
	 * @return int|WP_Error New user ID or error.
	 */
	public function create_pf_user( array $data ) {
		global $wpdb;

		if ( empty( $data['username'] ) || empty( $data['password'] ) ) {
			return new WP_Error( 'missing_data', __( 'Username and password are required.', 'processflow-manager' ) );
		}

		// Check for duplicate username.
		$exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}processflow_users WHERE username = %s",
			sanitize_user( $data['username'] )
		) );
		if ( $exists ) {
			return new WP_Error( 'duplicate_username', __( 'Username already exists.', 'processflow-manager' ) );
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_users',
			array(
				'username'      => sanitize_user( $data['username'] ),
				'email'         => sanitize_email( $data['email'] ?? '' ),
				'password_hash' => wp_hash_password( $data['password'] ),
				'role'          => in_array( $data['role'] ?? 'operator', array( 'admin', 'operator' ), true ) ? $data['role'] : 'operator',
				'is_active'     => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create user.', 'processflow-manager' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all platform users.
	 *
	 * @return object[]
	 */
	public function get_pf_users(): array {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, username, email, role, is_active, created_at FROM {$wpdb->prefix}processflow_users ORDER BY created_at DESC"
		) ?: array();
	}

	/**
	 * Get a single platform user by ID.
	 *
	 * @param int $id User ID.
	 * @return object|null
	 */
	public function get_pf_user( int $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, username, email, role, is_active, created_at FROM {$wpdb->prefix}processflow_users WHERE id = %d",
			$id
		) );
	}

	/**
	 * Find a platform user by username (includes password_hash for auth).
	 *
	 * @param string $username Username to look up.
	 * @return object|null
	 */
	public function get_pf_user_by_username( string $username ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}processflow_users WHERE username = %s AND is_active = 1",
			sanitize_user( $username )
		) );
	}

	/**
	 * Update an existing platform user.
	 *
	 * @param int   $id   User ID.
	 * @param array $data Fields to update (username, email, password, role, is_active).
	 * @return bool|WP_Error
	 */
	public function update_pf_user( int $id, array $data ) {
		global $wpdb;

		$clean = array();

		if ( isset( $data['username'] ) ) {
			// Check duplicate username (excluding this user).
			$exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id FROM {$wpdb->prefix}processflow_users WHERE username = %s AND id != %d",
				sanitize_user( $data['username'] ),
				$id
			) );
			if ( $exists ) {
				return new WP_Error( 'duplicate_username', __( 'Username already exists.', 'processflow-manager' ) );
			}
			$clean['username'] = sanitize_user( $data['username'] );
		}
		if ( isset( $data['email'] ) ) {
			$clean['email'] = sanitize_email( $data['email'] );
		}
		if ( ! empty( $data['password'] ) ) {
			$clean['password_hash'] = wp_hash_password( $data['password'] );
		}
		if ( isset( $data['role'] ) ) {
			$clean['role'] = in_array( $data['role'], array( 'admin', 'operator' ), true ) ? $data['role'] : 'operator';
		}
		if ( isset( $data['is_active'] ) ) {
			$clean['is_active'] = (int) $data['is_active'];
		}

		if ( empty( $clean ) ) {
			return false;
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_users',
			$clean,
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Delete a platform user.
	 *
	 * @param int $id User ID.
	 * @return bool
	 */
	public function delete_pf_user( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'processflow_users',
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}
}
