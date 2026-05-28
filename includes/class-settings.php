<?php
/**
 * Plugin settings helper.
 *
 * Wraps WordPress options with a 'processflow_' prefix and provides
 * password hashing for the standalone admin portal.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProcessFlow_Settings
 */
class ProcessFlow_Settings {

	/** Options key prefix. */
	const PREFIX = 'processflow_';

	// ------------------------------------------------------------------ //
	// Generic settings                                                     //
	// ------------------------------------------------------------------ //

	/**
	 * Get a plugin option.
	 *
	 * @param string $key     Option key (without prefix).
	 * @param mixed  $default Default value if option is not set.
	 * @return mixed
	 */
	public function get_setting( string $key, $default = false ) {
		return get_option( self::PREFIX . $key, $default );
	}

	/**
	 * Persist a plugin option.
	 *
	 * @param string $key   Option key (without prefix).
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public function update_setting( string $key, $value ): bool {
		return update_option( self::PREFIX . $key, $value );
	}

	/**
	 * Return all default plugin settings.
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		return array(
			'company_name'       => get_bloginfo( 'name' ),
			'company_phone'      => '',
			'company_email'      => get_option( 'admin_email' ),
			'portal_title'       => __( 'Track Your Order', 'processflow-manager' ),
			'portal_intro'       => __( 'Enter your invoice/order number to check your order status.', 'processflow-manager' ),
			'orders_per_page'    => 20,
			'enable_whatsapp'    => 1,
			'order_form_business_name' => 1,
			'order_form_stage'         => 1,
			'order_form_product_lines' => 1,
			'order_form_job_details'   => 1,
		);
	}

	// ------------------------------------------------------------------ //
	// Admin portal password                                               //
	// ------------------------------------------------------------------ //

	/**
	 * Get the stored hashed admin password for the front-end portal.
	 *
	 * @return string|false
	 */
	public function get_admin_password() {
		return $this->get_setting( 'portal_password_hash', false );
	}

	/**
	 * Hash and store a new admin password.
	 *
	 * @param string $password Plain-text password.
	 * @return bool
	 */
	public function set_admin_password( string $password ): bool {
		$hash = wp_hash_password( $password );
		return $this->update_setting( 'portal_password_hash', $hash );
	}

	/**
	 * Verify a plain-text password against the stored hash.
	 *
	 * @param string $password Plain-text candidate password.
	 * @return bool
	 */
	public function verify_admin_password( string $password ): bool {
		$stored = $this->get_admin_password();
		if ( ! $stored ) {
			// No password set yet – allow access so the admin can set one.
			return true;
		}
		return wp_check_password( $password, $stored );
	}
}
