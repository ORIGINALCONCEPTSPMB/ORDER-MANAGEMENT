<?php
/**
 * Fired during plugin activation.
 *
 * Creates all required database tables and seeds default data.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_Activator
 */
class ProcessFlow_Activator {

	/**
	 * Run all setup tasks when the plugin is activated.
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ------------------------------------------------------------------ //
		// Orders table                                                         //
		// ------------------------------------------------------------------ //
		$sql_orders = "CREATE TABLE {$wpdb->prefix}processflow_orders (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_name VARCHAR(255)        NOT NULL DEFAULT '',
			business_name VARCHAR(255)        NOT NULL DEFAULT '',
			whatsapp      VARCHAR(50)         NOT NULL DEFAULT '',
			job_details   TEXT                NOT NULL,
			current_stage BIGINT(20) UNSIGNED          DEFAULT NULL,
			created_at    DATETIME            NOT NULL,
			updated_at    DATETIME            NOT NULL,
			qr_code_hash  VARCHAR(64)         NOT NULL DEFAULT '',
			custom_fields LONGTEXT,
			PRIMARY KEY  (id),
			KEY current_stage (current_stage),
			KEY qr_code_hash (qr_code_hash)
		) $charset_collate;";

		// ------------------------------------------------------------------ //
		// Stages table                                                         //
		// ------------------------------------------------------------------ //
		$sql_stages = "CREATE TABLE {$wpdb->prefix}processflow_stages (
			id                 BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name               VARCHAR(255)        NOT NULL DEFAULT '',
			order_position     INT(11)             NOT NULL DEFAULT 0,
			color              VARCHAR(20)         NOT NULL DEFAULT '#000000',
			whatsapp_template  TEXT                NOT NULL,
			is_active          TINYINT(1)          NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY order_position (order_position)
		) $charset_collate;";

		// ------------------------------------------------------------------ //
		// Stage history table                                                  //
		// ------------------------------------------------------------------ //
		$sql_history = "CREATE TABLE {$wpdb->prefix}processflow_stage_history (
			id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id          BIGINT(20) UNSIGNED NOT NULL,
			stage_id          BIGINT(20) UNSIGNED NOT NULL,
			entered_at        DATETIME            NOT NULL,
			completed_at      DATETIME                     DEFAULT NULL,
			notification_sent TINYINT(1)          NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY stage_id (stage_id)
		) $charset_collate;";

		// ------------------------------------------------------------------ //
		// Custom fields table                                                  //
		// ------------------------------------------------------------------ //
		$sql_custom_fields = "CREATE TABLE {$wpdb->prefix}processflow_custom_fields (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			field_label VARCHAR(255)        NOT NULL DEFAULT '',
			field_type  VARCHAR(50)         NOT NULL DEFAULT 'text',
			is_required TINYINT(1)          NOT NULL DEFAULT 0,
			field_order INT(11)             NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) $charset_collate;";

		dbDelta( $sql_orders );
		dbDelta( $sql_stages );
		dbDelta( $sql_history );
		dbDelta( $sql_custom_fields );

		// Seed default stages only when the table is freshly created.
		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}processflow_stages" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( '0' === $existing || 0 === (int) $existing ) {
			self::insert_default_stages();
		}

		update_option( 'processflow_version', PROCESSFLOW_VERSION );
	}

	/**
	 * Insert the four default workflow stages.
	 */
	private static function insert_default_stages() {
		global $wpdb;

		$default_stages = array(
			array(
				'name'              => 'In Design',
				'order_position'    => 1,
				'color'             => '#3498db',
				'whatsapp_template' => "Hi {customer_name}! Your order #{order_id} for {business_name} is currently *In Design*. Our team is working on your design. We'll notify you when it moves to the next stage. Thank you for your patience!",
				'is_active'         => 1,
			),
			array(
				'name'              => 'In Print Queue',
				'order_position'    => 2,
				'color'             => '#e67e22',
				'whatsapp_template' => "Hi {customer_name}! Great news! Your order #{order_id} for {business_name} has moved to *In Print Queue*. Your job is queued and will be printed shortly. Thank you for choosing us!",
				'is_active'         => 1,
			),
			array(
				'name'              => 'In Finishing',
				'order_position'    => 3,
				'color'             => '#9b59b6',
				'whatsapp_template' => "Hi {customer_name}! Your order #{order_id} for {business_name} is now *In Finishing*. We're applying the final touches to your print job. Almost ready!",
				'is_active'         => 1,
			),
			array(
				'name'              => 'Ready for Collection',
				'order_position'    => 4,
				'color'             => '#27ae60',
				'whatsapp_template' => "Hi {customer_name}! 🎉 Your order #{order_id} for {business_name} is *Ready for Collection*! Please come in to collect your order during business hours. See you soon!",
				'is_active'         => 1,
			),
		);

		foreach ( $default_stages as $stage ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'processflow_stages',
				$stage,
				array( '%s', '%d', '%s', '%s', '%d' )
			);
		}
	}
}
