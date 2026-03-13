<?php
/**
 * Admin partial: QR Codes page.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $db ) ) {
	$db = $this->db;
}
if ( ! isset( $qr_engine ) ) {
	$qr_engine = $this->qr_engine;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$search   = isset( $_GET['pf_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_search'] ) ) : '';
$stage_f  = isset( $_GET['pf_stage'] )  ? absint( $_GET['pf_stage'] ) : 0;
// phpcs:enable

$results = $db->get_orders( array(
	'search'   => $search,
	'stage'    => $stage_f,
	'per_page' => 50,
	'page'     => 1,
) );
$orders = $results['items'];
$stages = $db->get_stages();
?>
<div class="wrap pf-wrap">
	<h1><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'QR Codes', 'processflow-manager' ); ?></h1>

	<div id="pf-notice-area"></div>

	<!-- Toolbar -->
	<div class="pf-toolbar" style="margin-bottom:20px;">
		<input type="search" id="pf-search" class="pf-search"
			placeholder="<?php esc_attr_e( 'Search orders…', 'processflow-manager' ); ?>"
			value="<?php echo esc_attr( $search ); ?>">
		<select id="pf-filter-stage">
			<option value=""><?php esc_html_e( 'All Stages', 'processflow-manager' ); ?></option>
			<?php foreach ( $stages as $s ) : ?>
				<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $stage_f, $s->id ); ?>>
					<?php echo esc_html( $s->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<div style="margin-left:auto;display:flex;gap:10px;align-items:center;">
			<label style="font-size:13px;">
				<input type="checkbox" id="pf-qr-select-all"> <?php esc_html_e( 'Select All', 'processflow-manager' ); ?>
			</label>
			<button id="pf-btn-print-labels" class="pf-btn pf-btn--primary">
				&#128438; <?php esc_html_e( 'Print PDF Labels', 'processflow-manager' ); ?>
			</button>
		</div>
	</div>

	<?php if ( ! empty( $orders ) ) : ?>
		<div class="pf-qr-grid">
			<?php foreach ( $orders as $order ) :
				$qr_url = $qr_engine->get_qr_image_url( (int) $order->id );
				$qr_url = is_wp_error( $qr_url ) ? '' : $qr_url;
				// Find stage name.
				$stage_name = '';
				foreach ( $stages as $s ) {
					if ( (int) $s->id === (int) $order->current_stage ) {
						$stage_name = $s->name;
						break;
					}
				}
			?>
			<div class="pf-qr-card">
				<label>
					<input type="checkbox" class="pf-qr-checkbox" value="<?php echo esc_attr( $order->id ); ?>">
				</label>
				<?php if ( $qr_url ) : ?>
					<img src="<?php echo esc_url( $qr_url ); ?>" alt="QR #<?php echo esc_attr( $order->id ); ?>">
				<?php endif; ?>
				<div class="pf-qr-card__id">
					<strong>#<?php echo esc_html( $order->id ); ?></strong><br>
					<?php echo esc_html( $order->customer_name ); ?>
					<?php if ( $stage_name ) : ?>
						<br><span style="font-size:11px;color:#787c82;"><?php echo esc_html( $stage_name ); ?></span>
					<?php endif; ?>
				</div>
				<div style="margin-top:8px;display:flex;justify-content:center;gap:6px;">
					<?php if ( $qr_url ) : ?>
						<a href="<?php echo esc_url( $qr_url ); ?>" download="qr-<?php echo esc_attr( $order->id ); ?>.png"
							class="pf-btn pf-btn--outline pf-btn--sm" title="<?php esc_attr_e( 'Download PNG', 'processflow-manager' ); ?>">
							&#8595; PNG
						</a>
					<?php endif; ?>
					<button class="pf-btn pf-btn--outline pf-btn--sm pf-show-qr" data-id="<?php echo esc_attr( $order->id ); ?>">
						<?php esc_html_e( 'Preview', 'processflow-manager' ); ?>
					</button>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="pf-card">
			<div class="pf-card__body" style="text-align:center;padding:40px;color:#787c82;">
				<?php esc_html_e( 'No orders found.', 'processflow-manager' ); ?>
			</div>
		</div>
	<?php endif; ?>
</div>
