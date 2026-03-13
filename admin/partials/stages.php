<?php
/**
 * Admin partial: Stage management.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $db ) ) {
	$db = $this->db;
}

$stages = $db->get_stages();
?>
<div class="wrap pf-wrap">
	<h1><span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Workflow Stages', 'processflow-manager' ); ?></h1>

	<div id="pf-notice-area"></div>

	<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

		<!-- Stage list -->
		<div>
			<div class="pf-card">
				<div class="pf-card__header">
					<h3 class="pf-card__title"><?php esc_html_e( 'Current Stages', 'processflow-manager' ); ?></h3>
					<small style="color:#787c82;"><?php esc_html_e( 'Drag to reorder', 'processflow-manager' ); ?></small>
				</div>
				<div class="pf-card__body">
					<?php if ( ! empty( $stages ) ) : ?>
						<ul class="pf-stage-list" id="pf-stage-sortable">
							<?php foreach ( $stages as $stage ) : ?>
								<li class="pf-stage-item"
									data-id="<?php echo esc_attr( $stage->id ); ?>"
									data-name="<?php echo esc_attr( $stage->name ); ?>"
									data-color="<?php echo esc_attr( $stage->color ); ?>"
									data-template="<?php echo esc_attr( $stage->whatsapp_template ); ?>"
								>
									<span class="pf-stage-item__handle dashicons dashicons-menu"></span>
									<span class="pf-stage-item__color" style="background:<?php echo esc_attr( $stage->color ); ?>;"></span>
									<span class="pf-stage-item__name"><?php echo esc_html( $stage->name ); ?></span>
									<div class="pf-stage-item__actions">
										<button class="pf-btn pf-btn--outline pf-btn--sm pf-edit-stage">
											<?php esc_html_e( 'Edit', 'processflow-manager' ); ?>
										</button>
										<button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-stage">
											<?php esc_html_e( 'Delete', 'processflow-manager' ); ?>
										</button>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p style="color:#787c82;"><?php esc_html_e( 'No stages yet. Add one using the form.', 'processflow-manager' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Add / Edit form -->
		<div class="pf-card" style="position:sticky;top:32px;">
			<div class="pf-card__header">
				<h3 class="pf-card__title" id="pf-stage-form-heading"><?php esc_html_e( 'Add New Stage', 'processflow-manager' ); ?></h3>
			</div>
			<div class="pf-card__body">
				<div id="pf-stage-notice"></div>
				<form id="pf-stage-form">
					<div class="pf-form-group">
						<label for="pf-stage-name"><?php esc_html_e( 'Stage Name *', 'processflow-manager' ); ?></label>
						<input type="text" id="pf-stage-name" name="stage_name" required maxlength="100">
					</div>

					<div class="pf-form-group">
						<label for="pf-stage-color"><?php esc_html_e( 'Color', 'processflow-manager' ); ?></label>
						<input type="text" id="pf-stage-color" name="stage_color" class="pf-color-picker" value="#3498db" data-default-color="#3498db">
					</div>

					<div class="pf-form-group">
						<label for="pf-stage-template"><?php esc_html_e( 'WhatsApp Template', 'processflow-manager' ); ?></label>
						<textarea id="pf-stage-template" name="whatsapp_template" rows="5"></textarea>
						<p class="pf-hint">
							<?php esc_html_e( 'Available merge tags:', 'processflow-manager' ); ?>
							<code>{customer_name}</code> <code>{business_name}</code>
							<code>{stage_name}</code> <code>{order_id}</code>
							<code>{date}</code> <code>{time}</code>
						</p>
					</div>

					<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
						<button type="submit" class="pf-btn pf-btn--primary">
							<?php esc_html_e( 'Add Stage', 'processflow-manager' ); ?>
						</button>
						<button type="button" id="pf-stage-form-reset" class="pf-btn pf-btn--outline">
							<?php esc_html_e( 'Reset', 'processflow-manager' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

	</div><!-- grid -->
</div>
