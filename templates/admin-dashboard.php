<?php
/**
 * Template: Admin dashboard shortcode – login form.
 *
 * Shown when a visitor reaches [processflow_admin_dashboard] and is
 * not yet authenticated.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="pf-login-wrap">
	<div class="pf-login-card">
		<div class="pf-login-logo">
			<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" width="48" height="48">
				<circle cx="20" cy="20" r="20" fill="url(#lg)"/>
				<path d="M12 20h16M20 12l8 8-8 8" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
				<defs><linearGradient id="lg" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse"><stop stop-color="#2271b1"/><stop offset="1" stop-color="#135e96"/></linearGradient></defs>
			</svg>
		</div>
		<h2 class="pf-login-title"><?php esc_html_e( 'ProcessFlow Admin', 'processflow-manager' ); ?></h2>
		<p class="pf-login-subtitle"><?php esc_html_e( 'Sign in to manage your orders', 'processflow-manager' ); ?></p>

		<form method="post" class="pf-login-form">
			<?php wp_nonce_field( 'processflow_shortcode_login', 'processflow_login_nonce' ); ?>
			<input type="hidden" name="processflow_admin_login" value="1">

			<div class="pf-login-field">
				<label class="pf-login-label" for="pf-login-username">
					<svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M10 10a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 1114 0H3z"/></svg>
					<?php esc_html_e( 'Username', 'processflow-manager' ); ?>
				</label>
				<input
					type="text"
					id="pf-login-username"
					name="processflow_username"
					class="pf-login-input"
					placeholder="<?php esc_attr_e( 'Enter your username', 'processflow-manager' ); ?>"
					autocomplete="username"
					required
				>
			</div>

			<div class="pf-login-field">
				<label class="pf-login-label" for="pf-login-password">
					<svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
					<?php esc_html_e( 'Password', 'processflow-manager' ); ?>
				</label>
				<input
					type="password"
					id="pf-login-password"
					name="processflow_password"
					class="pf-login-input"
					placeholder="<?php esc_attr_e( 'Enter your password', 'processflow-manager' ); ?>"
					autocomplete="current-password"
					required
				>
			</div>

			<button type="submit" class="pf-login-btn">
				<?php esc_html_e( 'Sign In', 'processflow-manager' ); ?>
				<svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
			</button>
		</form>
	</div>
</div>
