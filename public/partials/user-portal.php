<?php
/**
 * Public partial: User portal.
 *
 * Variables available from class-public.php user_portal_shortcode():
 *   $atts     - shortcode attributes
 *   $settings - ProcessFlow_Settings instance
 *   $db       - ProcessFlow_Database instance
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = $atts['title'] ?? __( 'Track Your Order', 'processflow-manager' );
$intro = $settings->get_setting( 'portal_intro', __( 'Enter your order number and the last 4 digits of your WhatsApp number to check your order status.', 'processflow-manager' ) );
?>
<div class="pf-portal">
	<h2 class="pf-portal__title"><?php echo esc_html( $title ); ?></h2>
	<p class="pf-portal__subtitle"><?php echo esc_html( $intro ); ?></p>

	<div id="pf-portal-notice"></div>

	<!-- Login form -->
	<div id="pf-portal-login-section" class="pf-login-card">
		<h3><?php esc_html_e( 'Look Up Your Order', 'processflow-manager' ); ?></h3>
		<form id="pf-portal-login-form" novalidate>
			<div class="pf-field">
				<label for="pf-portal-order-id">
					<?php esc_html_e( 'Order Number (Invoice #)', 'processflow-manager' ); ?> *
				</label>
				<input
					type="text"
					id="pf-portal-order-id"
					placeholder="<?php esc_attr_e( 'e.g. INV-001', 'processflow-manager' ); ?>"
					required
				>
			</div>
			<div class="pf-field">
				<label for="pf-portal-wa-last4">
					<?php esc_html_e( 'Last 4 Digits of Your WhatsApp Number', 'processflow-manager' ); ?> *
				</label>
				<input
					type="text"
					id="pf-portal-wa-last4"
					placeholder="<?php esc_attr_e( 'e.g. 4567', 'processflow-manager' ); ?>"
					maxlength="4"
					pattern="\d{4}"
					required
				>
			</div>
			<button type="submit" id="pf-portal-login-btn" class="pf-pub-btn pf-pub-btn--primary">
				<?php esc_html_e( 'Track My Order', 'processflow-manager' ); ?>
			</button>
		</form>
	</div>

	<!-- Order result (populated via JS) -->
	<div id="pf-portal-result"></div>
</div>
