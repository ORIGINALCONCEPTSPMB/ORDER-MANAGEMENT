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
<div style="max-width:400px;margin:40px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
	<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:30px;box-shadow:0 1px 3px rgba(0,0,0,.1);">
		<h2 style="text-align:center;margin:0 0 20px;font-size:20px;color:#2271b1;">
			&#128274; <?php esc_html_e( 'ProcessFlow Admin', 'processflow-manager' ); ?>
		</h2>
		<form method="post">
			<?php wp_nonce_field( 'processflow_shortcode_login', 'processflow_login_nonce' ); ?>
			<input type="hidden" name="processflow_admin_login" value="1">
			<div style="margin-bottom:16px;">
				<label style="display:block;font-weight:600;font-size:13px;margin-bottom:5px;">
					<?php esc_html_e( 'Password', 'processflow-manager' ); ?>
				</label>
				<input
					type="password"
					name="processflow_password"
					required
					autocomplete="current-password"
					style="width:100%;padding:9px 12px;border:1px solid #c3c4c7;border-radius:4px;font-size:14px;box-sizing:border-box;"
				>
			</div>
			<button type="submit"
				style="width:100%;padding:10px;background:#2271b1;color:#fff;border:none;border-radius:4px;font-size:14px;font-weight:600;cursor:pointer;">
				<?php esc_html_e( 'Sign In', 'processflow-manager' ); ?>
			</button>
		</form>
	</div>
</div>
