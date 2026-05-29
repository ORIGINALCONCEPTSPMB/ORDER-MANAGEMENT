<?php
/**
 * Admin partial: Plugin settings.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $settings ) ) {
	$settings = $this->settings;
}
if ( ! isset( $db ) ) {
	$db = $this->db;
}

$defaults = $settings->get_default_settings();

// Resolve created page URLs.
$admin_page_id    = (int) get_option( 'processflow_admin_page_id', 0 );
$portal_page_id   = (int) get_option( 'processflow_portal_page_id', 0 );
$qr_scan_page_id  = (int) get_option( 'processflow_qr_scan_page_id', 0 );
$admin_page_url   = ( $admin_page_id && get_post( $admin_page_id ) ) ? get_permalink( $admin_page_id ) : false;
$portal_page_url  = ( $portal_page_id && get_post( $portal_page_id ) ) ? get_permalink( $portal_page_id ) : false;
$qr_scan_page_url = ( $qr_scan_page_id && get_post( $qr_scan_page_id ) ) ? get_permalink( $qr_scan_page_id ) : false;
?>
<div class="wrap pf-wrap">
	<h1><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'processflow-manager' ); ?></h1>

	<div id="pf-settings-notice"></div>

	<!-- Tabs -->
	<div class="pf-tabs">
		<button class="pf-tab active" data-target="pf-tab-general"><?php esc_html_e( 'General', 'processflow-manager' ); ?></button>
		<button class="pf-tab" data-target="pf-tab-security"><?php esc_html_e( 'Security', 'processflow-manager' ); ?></button>
		<button class="pf-tab" data-target="pf-tab-fields"><?php esc_html_e( 'Custom Fields', 'processflow-manager' ); ?></button>
		<button class="pf-tab" data-target="pf-tab-backup"><?php esc_html_e( 'Backup & Restore', 'processflow-manager' ); ?></button>
	</div>

	<!-- General settings -->
	<div id="pf-tab-general" class="pf-tab-content active">
		<div class="pf-card">
			<div class="pf-card__header">
				<h3 class="pf-card__title"><?php esc_html_e( 'Company Details', 'processflow-manager' ); ?></h3>
			</div>
			<div class="pf-card__body">
				<form class="pf-settings-form" data-notice="#pf-settings-notice">
					<div class="pf-form-group">
						<label><?php esc_html_e( 'Company Name', 'processflow-manager' ); ?></label>
						<input type="text" name="company_name"
							value="<?php echo esc_attr( $settings->get_setting( 'company_name', $defaults['company_name'] ) ); ?>">
					</div>
					<div class="pf-form-group">
						<label><?php esc_html_e( 'Company Phone', 'processflow-manager' ); ?></label>
						<input type="text" name="company_phone"
							value="<?php echo esc_attr( $settings->get_setting( 'company_phone', '' ) ); ?>">
					</div>
					<div class="pf-form-group">
						<label><?php esc_html_e( 'Company Email', 'processflow-manager' ); ?></label>
						<input type="email" name="company_email"
							value="<?php echo esc_attr( $settings->get_setting( 'company_email', $defaults['company_email'] ) ); ?>">
					</div>

					<hr style="margin:20px 0;">

					<div class="pf-form-group">
						<label><?php esc_html_e( 'User Portal Title', 'processflow-manager' ); ?></label>
						<input type="text" name="portal_title"
							value="<?php echo esc_attr( $settings->get_setting( 'portal_title', $defaults['portal_title'] ) ); ?>">
					</div>
					<div class="pf-form-group">
						<label><?php esc_html_e( 'User Portal Intro Text', 'processflow-manager' ); ?></label>
						<textarea name="portal_intro" rows="3" style="max-width:480px;width:100%;"><?php echo esc_textarea( $settings->get_setting( 'portal_intro', $defaults['portal_intro'] ) ); ?></textarea>
					</div>
					<div class="pf-form-group">
						<label><?php esc_html_e( 'Orders Per Page', 'processflow-manager' ); ?></label>
						<input type="number" name="orders_per_page" min="5" max="100"
							value="<?php echo esc_attr( $settings->get_setting( 'orders_per_page', 20 ) ); ?>" style="max-width:100px;">
					</div>
					<hr style="margin:20px 0;">
					<div class="pf-form-group">
						<label style="display:block;"><?php esc_html_e( 'Order Form Features', 'processflow-manager' ); ?></label>
						<label style="display:block;margin:4px 0;">
							<input type="checkbox" name="order_form_business_name" value="1" <?php checked( (int) $settings->get_setting( 'order_form_business_name', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Show Business Name field', 'processflow-manager' ); ?>
						</label>
						<label style="display:block;margin:4px 0;">
							<input type="checkbox" name="order_form_stage" value="1" <?php checked( (int) $settings->get_setting( 'order_form_stage', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Show Stage selector', 'processflow-manager' ); ?>
						</label>
						<label style="display:block;margin:4px 0;">
							<input type="checkbox" name="order_form_product_lines" value="1" <?php checked( (int) $settings->get_setting( 'order_form_product_lines', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Show Product Lines section', 'processflow-manager' ); ?>
						</label>
						<label style="display:block;margin:4px 0;">
							<input type="checkbox" name="order_form_job_details" value="1" <?php checked( (int) $settings->get_setting( 'order_form_job_details', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Show Job Details field', 'processflow-manager' ); ?>
						</label>
					</div>

					<button type="submit" class="pf-btn pf-btn--primary">
						<?php esc_html_e( 'Save Settings', 'processflow-manager' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>

	<!-- Security -->
	<div id="pf-tab-security" class="pf-tab-content">
		<div class="pf-card">
			<div class="pf-card__header">
				<h3 class="pf-card__title"><?php esc_html_e( 'Admin Portal Password', 'processflow-manager' ); ?></h3>
			</div>
			<div class="pf-card__body">
				<p style="color:#555;margin-top:0;">
					<?php esc_html_e( 'This password is used to protect the front-end admin dashboard shortcode. It is separate from your WordPress user account.', 'processflow-manager' ); ?>
				</p>
				<form class="pf-settings-form" data-notice="#pf-settings-notice">
					<div class="pf-form-group">
						<label><?php esc_html_e( 'New Password', 'processflow-manager' ); ?></label>
						<input type="password" name="new_password" autocomplete="new-password">
					</div>
					<div class="pf-form-group">
						<label><?php esc_html_e( 'Confirm New Password', 'processflow-manager' ); ?></label>
						<input type="password" name="confirm_password" autocomplete="new-password">
					</div>
					<button type="submit" class="pf-btn pf-btn--primary">
						<?php esc_html_e( 'Update Password', 'processflow-manager' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>

	<!-- Custom fields -->
	<div id="pf-tab-fields" class="pf-tab-content">
		<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;">
			<!-- Existing fields -->
			<div class="pf-card">
				<div class="pf-card__header">
					<h3 class="pf-card__title"><?php esc_html_e( 'Custom Fields', 'processflow-manager' ); ?></h3>
				</div>
				<div class="pf-card__body" style="padding:0;">
					<?php $custom_fields = $db->get_custom_fields(); ?>
					<?php if ( ! empty( $custom_fields ) ) : ?>
						<table class="pf-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Label', 'processflow-manager' ); ?></th>
									<th><?php esc_html_e( 'Type', 'processflow-manager' ); ?></th>
									<th><?php esc_html_e( 'Required', 'processflow-manager' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $custom_fields as $cf ) : ?>
									<tr>
										<td><?php echo esc_html( $cf->field_label ); ?></td>
										<td><?php echo esc_html( $cf->field_type ); ?></td>
										<td><?php echo $cf->is_required ? esc_html__( 'Yes', 'processflow-manager' ) : esc_html__( 'No', 'processflow-manager' ); ?></td>
										<td>
											<button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-custom-field" data-id="<?php echo esc_attr( $cf->id ); ?>">
												<?php esc_html_e( 'Delete', 'processflow-manager' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p style="padding:20px;color:#787c82;"><?php esc_html_e( 'No custom fields yet.', 'processflow-manager' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Add field form -->
			<div class="pf-card">
				<div class="pf-card__header">
					<h3 class="pf-card__title"><?php esc_html_e( 'Add Custom Field', 'processflow-manager' ); ?></h3>
				</div>
				<div class="pf-card__body">
					<form id="pf-custom-field-form">
						<div class="pf-form-group">
							<label><?php esc_html_e( 'Field Label *', 'processflow-manager' ); ?></label>
							<input type="text" name="field_label" required>
						</div>
						<div class="pf-form-group">
							<label><?php esc_html_e( 'Field Type', 'processflow-manager' ); ?></label>
							<select name="field_type">
								<option value="text"><?php esc_html_e( 'Text', 'processflow-manager' ); ?></option>
								<option value="textarea"><?php esc_html_e( 'Textarea', 'processflow-manager' ); ?></option>
								<option value="number"><?php esc_html_e( 'Number', 'processflow-manager' ); ?></option>
								<option value="date"><?php esc_html_e( 'Date', 'processflow-manager' ); ?></option>
								<option value="checkbox"><?php esc_html_e( 'Checkbox', 'processflow-manager' ); ?></option>
							</select>
						</div>
						<div class="pf-form-group">
							<label>
								<input type="checkbox" name="is_required" value="1">
								<?php esc_html_e( 'Required field', 'processflow-manager' ); ?>
							</label>
						</div>
						<button type="submit" class="pf-btn pf-btn--primary">
							<?php esc_html_e( 'Add Field', 'processflow-manager' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Backup & Restore -->
	<div id="pf-tab-backup" class="pf-tab-content">
		<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
			<div class="pf-card">
				<div class="pf-card__header">
					<h3 class="pf-card__title"><?php esc_html_e( 'Export Backup', 'processflow-manager' ); ?></h3>
				</div>
				<div class="pf-card__body">
					<p style="color:#555;margin-top:0;">
						<?php esc_html_e( 'Download a complete JSON backup of all orders, stages, settings, custom fields and platform users. Store this file safely — it can be used to restore your data.', 'processflow-manager' ); ?>
					</p>
					<ul style="color:#555;margin:0 0 16px 16px;padding:0;">
						<li><?php esc_html_e( 'All orders and stage history', 'processflow-manager' ); ?></li>
						<li><?php esc_html_e( 'Workflow stages and templates', 'processflow-manager' ); ?></li>
						<li><?php esc_html_e( 'Platform users (passwords excluded)', 'processflow-manager' ); ?></li>
						<li><?php esc_html_e( 'Custom fields and settings', 'processflow-manager' ); ?></li>
					</ul>
					<button id="pf-btn-export-backup" class="pf-btn pf-btn--primary">
						&#8659; <?php esc_html_e( 'Download Backup', 'processflow-manager' ); ?>
					</button>
				</div>
			</div>
			<div class="pf-card">
				<div class="pf-card__header">
					<h3 class="pf-card__title"><?php esc_html_e( 'Restore Backup', 'processflow-manager' ); ?></h3>
				</div>
				<div class="pf-card__body">
					<div id="pf-backup-notice"></div>
					<p style="color:#555;margin-top:0;">
						<?php esc_html_e( 'Upload a JSON backup file to restore your data. Existing records are preserved — only records that do not already exist will be added.', 'processflow-manager' ); ?>
					</p>
					<p style="color:#c0392b;margin:0 0 16px;font-weight:600;font-size:13px;">
						&#9888; <?php esc_html_e( 'Note: User passwords are not included in the backup. Restored user accounts must have their passwords reset.', 'processflow-manager' ); ?>
					</p>
					<form id="pf-import-backup-form" enctype="multipart/form-data">
						<div class="pf-form-group">
							<label><?php esc_html_e( 'Select Backup File (.json)', 'processflow-manager' ); ?></label>
							<input type="file" name="backup_file" id="pf-backup-file" accept=".json,application/json" required>
							<small id="pf-backup-file-name" style="color:#787c82;"><?php esc_html_e( 'No file selected', 'processflow-manager' ); ?></small>
						</div>
						<button type="submit" class="pf-btn pf-btn--primary" id="pf-import-backup-submit">
							&#8679; <?php esc_html_e( 'Restore Backup', 'processflow-manager' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Auto-created pages -->
	<?php if ( $admin_page_url || $portal_page_url || $qr_scan_page_url ) : ?>
	<div class="pf-card" style="margin-top:24px;">
		<div class="pf-card__header">
			<h3 class="pf-card__title"><?php esc_html_e( 'Your Plugin Pages', 'processflow-manager' ); ?></h3>
		</div>
		<div class="pf-card__body">
			<p style="margin-top:0;color:#555;"><?php esc_html_e( 'These pages were automatically created during plugin activation and contain the plugin shortcodes.', 'processflow-manager' ); ?></p>
			<table class="pf-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Page', 'processflow-manager' ); ?></th>
						<th><?php esc_html_e( 'Shortcode', 'processflow-manager' ); ?></th>
						<th><?php esc_html_e( 'URL', 'processflow-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $admin_page_url ) : ?>
					<tr>
						<td><?php esc_html_e( 'Admin Dashboard', 'processflow-manager' ); ?></td>
						<td><code>[processflow_admin_dashboard]</code></td>
						<td><a href="<?php echo esc_url( $admin_page_url ); ?>" target="_blank"><?php echo esc_url( $admin_page_url ); ?></a></td>
					</tr>
					<?php endif; ?>
					<?php if ( $portal_page_url ) : ?>
					<tr>
						<td><?php esc_html_e( 'Order Tracking Portal', 'processflow-manager' ); ?></td>
						<td><code>[processflow_user_portal]</code></td>
						<td><a href="<?php echo esc_url( $portal_page_url ); ?>" target="_blank"><?php echo esc_url( $portal_page_url ); ?></a></td>
					</tr>
					<?php endif; ?>
					<?php if ( $qr_scan_page_url ) : ?>
					<tr>
						<td><?php esc_html_e( 'QR Code Scan / Order Update', 'processflow-manager' ); ?></td>
						<td><code>[processflow_qr_scan]</code></td>
						<td><a href="<?php echo esc_url( $qr_scan_page_url ); ?>" target="_blank"><?php echo esc_url( $qr_scan_page_url ); ?></a></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

</div>
