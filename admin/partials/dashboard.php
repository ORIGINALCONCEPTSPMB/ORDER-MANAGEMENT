<?php
/**
 * Admin partial: Dashboard overview.
 *
 * Variables available from the caller:
 *  $this->db  (inside class-admin.php render method)
 *  For shortcode context: $db, $order_manager, $qr_engine, $whatsapp, $settings
 *
 * We use a local reference so this file works in both contexts.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Resolve $db whether called from WP admin or shortcode context. */
if ( ! isset( $db ) ) {
	// Called via render_dashboard() inside the admin class – use $this.
	$db = $this->db;
}

global $wpdb;

/* ---- Stats ---------------------------------------------------- */
$total_orders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}processflow_orders" ); // phpcs:ignore

$today = current_time( 'Y-m-d' );
$today_orders = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
	"SELECT COUNT(*) FROM {$wpdb->prefix}processflow_orders WHERE DATE(created_at) = %s",
	$today
) );

$stages        = $db->get_stages();
$stage_counts  = array();
foreach ( $stages as $stage ) {
	$cnt = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
		"SELECT COUNT(*) FROM {$wpdb->prefix}processflow_orders WHERE current_stage = %d",
		$stage->id
	) );
	$stage_counts[ $stage->id ] = array(
		'name'  => $stage->name,
		'color' => $stage->color,
		'count' => $cnt,
	);
}

/* ---- Recent orders ------------------------------------------- */
$recent = $db->get_orders( array( 'per_page' => 5, 'page' => 1 ) );
?>
<div class="wrap pf-wrap">
	<h1><span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'ProcessFlow – Dashboard', 'processflow-manager' ); ?></h1>

	<div id="pf-notice-area"></div>

	<!-- Stats -->
	<div class="pf-stats">
		<div class="pf-stat-card">
			<div class="pf-stat-card__number"><?php echo esc_html( $total_orders ); ?></div>
			<div class="pf-stat-card__label"><?php esc_html_e( 'Total Orders', 'processflow-manager' ); ?></div>
		</div>
		<div class="pf-stat-card">
			<div class="pf-stat-card__number"><?php echo esc_html( $today_orders ); ?></div>
			<div class="pf-stat-card__label"><?php esc_html_e( "Today's Orders", 'processflow-manager' ); ?></div>
		</div>
		<?php foreach ( $stage_counts as $sc ) : ?>
		<div class="pf-stat-card">
			<div class="pf-stat-card__number" style="color:<?php echo esc_attr( $sc['color'] ); ?>">
				<?php echo esc_html( $sc['count'] ); ?>
			</div>
			<div class="pf-stat-card__label"><?php echo esc_html( $sc['name'] ); ?></div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Quick actions -->
	<div style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=processflow-orders' ) ); ?>" class="pf-btn pf-btn--primary">
			&#43; <?php esc_html_e( 'Add Order', 'processflow-manager' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=processflow-stages' ) ); ?>" class="pf-btn pf-btn--outline">
			<?php esc_html_e( 'Manage Stages', 'processflow-manager' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=processflow-qr-codes' ) ); ?>" class="pf-btn pf-btn--outline">
			<?php esc_html_e( 'QR Codes', 'processflow-manager' ); ?>
		</a>
	</div>

	<!-- Recent orders -->
	<div class="pf-card">
		<div class="pf-card__header">
			<h3 class="pf-card__title"><?php esc_html_e( 'Recent Orders', 'processflow-manager' ); ?></h3>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=processflow-orders' ) ); ?>" class="pf-btn pf-btn--outline pf-btn--sm">
				<?php esc_html_e( 'View All', 'processflow-manager' ); ?>
			</a>
		</div>
		<div class="pf-card__body" style="padding:0;">
			<div class="pf-table-wrap">
				<table class="pf-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'processflow-manager' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'processflow-manager' ); ?></th>
							<th><?php esc_html_e( 'Business', 'processflow-manager' ); ?></th>
							<th><?php esc_html_e( 'Stage', 'processflow-manager' ); ?></th>
							<th><?php esc_html_e( 'Created', 'processflow-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $recent['items'] ) ) : ?>
							<?php foreach ( $recent['items'] as $order ) :
								$stage_info = isset( $stage_counts[ $order->current_stage ] ) ? $stage_counts[ $order->current_stage ] : null;
							?>
							<tr>
								<td>#<?php echo esc_html( $order->id ); ?></td>
								<td><?php echo esc_html( $order->customer_name ); ?></td>
								<td><?php echo esc_html( $order->business_name ); ?></td>
								<td>
									<?php if ( $stage_info ) : ?>
										<span class="pf-badge" style="background:<?php echo esc_attr( $stage_info['color'] ); ?>">
											<?php echo esc_html( $stage_info['name'] ); ?>
										</span>
									<?php else : ?>
										<span class="pf-badge" style="background:#aaa"><?php esc_html_e( 'N/A', 'processflow-manager' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $order->created_at ); ?></td>
							</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="5" style="text-align:center;padding:30px;color:#787c82;">
									<?php esc_html_e( 'No orders yet. Create your first order!', 'processflow-manager' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
