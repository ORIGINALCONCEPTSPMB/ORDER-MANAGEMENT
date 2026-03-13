<?php
/**
 * Fired during plugin deactivation.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_Deactivator
 *
 * Handles cleanup on plugin deactivation.  Tables are intentionally preserved
 * so that re-activating the plugin restores all previously-entered data.
 */
class ProcessFlow_Deactivator {

	/**
	 * Plugin deactivation hook callback.
	 *
	 * Clears any scheduled events and transients created by the plugin.
	 * Does NOT drop database tables (data preservation by design).
	 */
	public static function deactivate() {
		// Remove all ProcessFlow transients.
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_processflow_%'
			    OR option_name LIKE '_transient_timeout_processflow_%'"
		);

		// Remove any cron events registered by the plugin.
		$timestamp = wp_next_scheduled( 'processflow_cleanup_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'processflow_cleanup_event' );
		}
	}
}
