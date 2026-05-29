<?php
/**
 * Admin partial: Orders list.
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $db ) ) {
	$db = $this->db;
}

/* ---- Filters from query string -------------------------------- */
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$search   = isset( $_GET['pf_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_search'] ) ) : '';
$stage_f  = isset( $_GET['pf_stage'] )  ? absint( $_GET['pf_stage'] )  : 0;
$page_num = isset( $_GET['pf_page'] )   ? max( 1, absint( $_GET['pf_page'] ) ) : 1;
// phpcs:enable

$per_page = 20;
$results  = $db->get_orders( array(
	'search'   => $search,
	'stage'    => $stage_f,
	'per_page' => $per_page,
	'page'     => $page_num,
) );

$orders     = $results['items'];
$total      = $results['total'];
$total_pages = (int) ceil( $total / $per_page );

$stages = $db->get_stages();

// Pre-fetch the last WhatsApp-notified stage for all orders on this page.
$order_ids     = ! empty( $orders ) ? array_map( function ( $o ) { return (int) $o->id; }, $orders ) : array();
$last_notified = $db->get_last_notified_stages( $order_ids );

// Pass stages data to JS.
$stages_json = wp_json_encode(
	array_map( function ( $s ) {
		return array( 'id' => $s->id, 'name' => $s->name, 'color' => $s->color );
	}, $stages )
);
?>
<script>window.pfStages = <?php echo $stages_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;</script>

<div class="wrap pf-wrap">
	<h1><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Orders', 'processflow-manager' ); ?></h1>

	<div id="pf-notice-area"></div>

	<!-- Toolbar -->
	<div class="pf-toolbar">
		<input
			type="search"
			id="pf-search"
			class="pf-search"
			placeholder="<?php esc_attr_e( 'Search orders…', 'processflow-manager' ); ?>"
			value="<?php echo esc_attr( $search ); ?>"
		>
		<select id="pf-filter-stage">
			<option value=""><?php esc_html_e( 'All Stages', 'processflow-manager' ); ?></option>
			<?php foreach ( $stages as $s ) : ?>
				<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $stage_f, $s->id ); ?>>
					<?php echo esc_html( $s->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button id="pf-btn-add-order" class="pf-btn pf-btn--primary">
			&#43; <?php esc_html_e( 'Add Order', 'processflow-manager' ); ?>
		</button>
	</div>

	<div id="pf-order-table-wrap" data-page="<?php echo esc_attr( $page_num ); ?>">
		<div class="pf-card">
			<div class="pf-card__body" style="padding:0;">
				<div class="pf-table-wrap">
					<table class="pf-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'processflow-manager' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'processflow-manager' ); ?></th>
								<th><?php esc_html_e( 'Business', 'processflow-manager' ); ?></th>
								<th><?php esc_html_e( 'WhatsApp', 'processflow-manager' ); ?></th>
								<th><?php esc_html_e( 'Stage', 'processflow-manager' ); ?></th>
								<th><?php esc_html_e( 'WA Sent', 'processflow-manager' ); ?></th>
								<th><?php esc_html_e( 'Created', 'processflow-manager' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'processflow-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $orders ) ) : ?>
								<?php foreach ( $orders as $order ) :
									// Find current stage.
									$stage_obj = null;
									foreach ( $stages as $s ) {
										if ( (int) $s->id === (int) $order->current_stage ) {
											$stage_obj = $s;
											break;
										}
									}
									$notified_info = isset( $last_notified[ (int) $order->id ] ) ? $last_notified[ (int) $order->id ] : null;
								?>
								<tr id="pf-order-row-<?php echo esc_attr( $order->id ); ?>">
									<td>#<?php echo esc_html( $order->id ); ?></td>
									<td><?php echo esc_html( $order->customer_name ); ?></td>
									<td><?php echo esc_html( $order->business_name ); ?></td>
									<td>
										<a href="https://wa.me/<?php echo esc_attr( ltrim( $order->whatsapp, '+' ) ); ?>" target="_blank">
											<?php echo esc_html( $order->whatsapp ); ?>
										</a>
									</td>
									<td>
										<?php if ( $stage_obj ) : ?>
											<span class="pf-badge" style="background:<?php echo esc_attr( $stage_obj->color ); ?>">
												<?php echo esc_html( $stage_obj->name ); ?>
											</span>
										<?php else : ?>
											<span class="pf-badge" style="background:#aaa"><?php esc_html_e( 'N/A', 'processflow-manager' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $notified_info ) : ?>
											<span class="pf-badge" style="background:<?php echo esc_attr( $notified_info['stage_color'] ); ?>">
												<?php echo esc_html( $notified_info['stage_name'] ); ?>
											</span>
										<?php else : ?>
											<span style="color:#aaa;">&mdash;</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $order->created_at ); ?></td>
									<td>
										<div style="display:flex;gap:6px;flex-wrap:wrap;">
											<button class="pf-btn pf-btn--outline pf-btn--sm pf-edit-order" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'Edit', 'processflow-manager' ); ?>">
												&#9998;
											</button>
											<button class="pf-btn pf-btn--outline pf-btn--sm pf-show-qr" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'QR Code', 'processflow-manager' ); ?>">
												&#9000;
											</button>
											<button class="pf-btn pf-btn--success pf-btn--sm pf-advance-stage" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'Advance Stage', 'processflow-manager' ); ?>">
												&#9654;
											</button>
											<button class="pf-btn pf-btn--sm pf-send-whatsapp" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'Send WhatsApp', 'processflow-manager' ); ?>" style="background:#25d366;color:#fff;border-color:#25d366;">
												&#128172;
											</button>
											<button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-order" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'Delete', 'processflow-manager' ); ?>">
												&#10005;
											</button>
										</div>
									</td>
								</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr>
									<td colspan="8" style="text-align:center;padding:30px;color:#787c82;">
										<?php esc_html_e( 'No orders found.', 'processflow-manager' ); ?>
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
		<div class="pf-pagination">
			<?php if ( $page_num > 1 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'pf_page', $page_num - 1 ) ); ?>">&laquo;</a>
			<?php endif; ?>
			<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
				<?php if ( $i === $page_num ) : ?>
					<span class="current"><?php echo esc_html( $i ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( add_query_arg( 'pf_page', $i ) ); ?>"><?php echo esc_html( $i ); ?></a>
				<?php endif; ?>
			<?php endfor; ?>
			<?php if ( $page_num < $total_pages ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'pf_page', $page_num + 1 ) ); ?>">&raquo;</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<p style="color:#787c82;font-size:12px;margin-top:8px;">
			<?php
			printf(
				/* translators: %1$d = total orders */
				esc_html__( '%1$d orders total', 'processflow-manager' ),
				esc_html( $total )
			);
			?>
		</p>
	</div><!-- #pf-order-table-wrap -->
</div>
