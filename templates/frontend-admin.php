<?php
/**
 * Template: Frontend admin dashboard – full tabbed management interface.
 *
 * Variables injected by render_shortcode_dashboard():
 *   $db           ProcessFlow_Database
 *   $order_manager ProcessFlow_Order_Manager
 *   $qr_engine    ProcessFlow_QR_Engine
 *   $settings     ProcessFlow_Settings
 *   $session_role 'admin' | 'operator'
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_admin = ( 'admin' === $session_role );

/* ---- Gather data for Dashboard tab -------------------------------- */
global $wpdb;
$total_orders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}processflow_orders" ); // phpcs:ignore
$today        = current_time( 'Y-m-d' );
$today_orders = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
	"SELECT COUNT(*) FROM {$wpdb->prefix}processflow_orders WHERE DATE(created_at) = %s",
	$today
) );

$all_stages   = $db->get_stages();
$stage_counts = array();
foreach ( $all_stages as $stage ) {
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
$recent_orders = $db->get_orders( array( 'per_page' => 5, 'page' => 1 ) );

/* ---- Gather data for Orders tab ----------------------------------- */
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$pf_search   = isset( $_GET['pf_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_search'] ) ) : '';
$pf_stage_f  = isset( $_GET['pf_stage'] )  ? absint( $_GET['pf_stage'] ) : 0;
$pf_page_num = isset( $_GET['pf_page'] )   ? max( 1, absint( $_GET['pf_page'] ) ) : 1;
$active_tab  = isset( $_GET['pf_tab'] )    ? sanitize_key( wp_unslash( $_GET['pf_tab'] ) ) : 'dashboard';
// phpcs:enable
$pf_per_page     = 20;
$orders_result   = $db->get_orders( array(
	'search'   => $pf_search,
	'stage'    => $pf_stage_f,
	'per_page' => $pf_per_page,
	'page'     => $pf_page_num,
) );
$orders       = $orders_result['items'];
$orders_total = $orders_result['total'];
$orders_pages = (int) ceil( $orders_total / $pf_per_page );

/* ---- Gather data for Users tab ------------------------------------ */
$pf_users = $is_admin ? $db->get_pf_users() : array();

/* ---- Settings for Invoice Ninja tab ------------------------------- */
$in_url   = $settings->get_setting( 'invoiceninja_url', '' );
$in_token = $settings->get_setting( 'invoiceninja_token', '' );

/* ---- Stages JSON for JS modals ----------------------------------- */
$stages_json = wp_json_encode(
	array_map( function ( $s ) {
		return array( 'id' => (int) $s->id, 'name' => $s->name, 'color' => $s->color );
	}, $all_stages )
);

/* ---- Allowed tabs ------------------------------------------------ */
$allowed_tabs = $is_admin
	? array( 'dashboard', 'orders', 'stages', 'qr-codes', 'users', 'import', 'invoiceninja', 'settings' )
	: array( 'dashboard', 'orders', 'qr-codes', 'import' );

if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
	$active_tab = 'dashboard';
}
?>
<div class="pf-frontend-wrap">

	<!-- ============================================================ -->
	<!-- Header                                                        -->
	<!-- ============================================================ -->
	<header class="pf-frontend-header">
		<div class="pf-frontend-header__brand">
			<svg viewBox="0 0 32 32" fill="none" width="28" height="28">
				<circle cx="16" cy="16" r="16" fill="url(#pfhg)"/>
				<path d="M9 16h14M16 9l7 7-7 7" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				<defs><linearGradient id="pfhg" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse"><stop stop-color="#2271b1"/><stop offset="1" stop-color="#0d3b71"/></linearGradient></defs>
			</svg>
			<span><?php esc_html_e( 'ProcessFlow', 'processflow-manager' ); ?></span>
		</div>
		<nav class="pf-frontend-nav" id="pf-frontend-nav">
			<button class="pf-fnav__tab <?php echo 'dashboard' === $active_tab ? 'active' : ''; ?>" data-tab="dashboard">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"/><path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"/></svg>
				<?php esc_html_e( 'Dashboard', 'processflow-manager' ); ?>
			</button>
			<button class="pf-fnav__tab <?php echo 'orders' === $active_tab ? 'active' : ''; ?>" data-tab="orders">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Orders', 'processflow-manager' ); ?>
			</button>
			<?php if ( $is_admin ) : ?>
			<button class="pf-fnav__tab <?php echo 'stages' === $active_tab ? 'active' : ''; ?>" data-tab="stages">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/></svg>
				<?php esc_html_e( 'Stages', 'processflow-manager' ); ?>
			</button>
			<?php endif; ?>
			<button class="pf-fnav__tab <?php echo 'qr-codes' === $active_tab ? 'active' : ''; ?>" data-tab="qr-codes">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1zM11 13a1 1 0 112 0v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1zm2-8a1 1 0 10-2 0v.01a1 1 0 102 0V5zm-4 8a1 1 0 10-2 0v.01a1 1 0 102 0V13z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'QR Codes', 'processflow-manager' ); ?>
			</button>
			<?php if ( $is_admin ) : ?>
			<button class="pf-fnav__tab <?php echo 'users' === $active_tab ? 'active' : ''; ?>" data-tab="users">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
				<?php esc_html_e( 'Users', 'processflow-manager' ); ?>
			</button>
			<?php endif; ?>
			<button class="pf-fnav__tab <?php echo 'import' === $active_tab ? 'active' : ''; ?>" data-tab="import">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Import', 'processflow-manager' ); ?>
			</button>
			<?php if ( $is_admin ) : ?>
			<button class="pf-fnav__tab <?php echo 'invoiceninja' === $active_tab ? 'active' : ''; ?>" data-tab="invoiceninja">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Invoice Ninja', 'processflow-manager' ); ?>
			</button>
			<button class="pf-fnav__tab <?php echo 'settings' === $active_tab ? 'active' : ''; ?>" data-tab="settings">
				<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Settings', 'processflow-manager' ); ?>
			</button>
			<?php endif; ?>
		</nav>
		<div class="pf-frontend-header__actions">
			<span class="pf-session-role pf-session-role--<?php echo esc_attr( $session_role ); ?>">
				<?php echo esc_html( ucfirst( $session_role ) ); ?>
			</span>
			<form method="post" style="display:inline;">
				<?php wp_nonce_field( 'processflow_shortcode_login', 'processflow_logout_nonce' ); ?>
				<input type="hidden" name="processflow_admin_logout" value="1">
				<button type="submit" class="pf-btn pf-btn--outline pf-btn--sm">
					<?php esc_html_e( 'Log Out', 'processflow-manager' ); ?>
				</button>
			</form>
		</div>
	</header>

	<?php
	// Build the JS config object.
	$pf_js_config = array(
		'ajax_url'               => admin_url( 'admin-ajax.php' ),
		'processflow_ajax_nonce' => wp_create_nonce( 'processflow_admin_nonce' ),
		'confirm_delete'         => __( 'Are you sure you want to delete this item? This cannot be undone.', 'processflow-manager' ),
		'strings'                => array(
			'saving'  => __( 'Saving…', 'processflow-manager' ),
			'saved'   => __( 'Saved!', 'processflow-manager' ),
			'error'   => __( 'An error occurred. Please try again.', 'processflow-manager' ),
			'loading' => __( 'Loading…', 'processflow-manager' ),
		),
	);
	?>
	<script>
	/* Inline fallback – ensures processflowAdmin is defined even if wp_localize_script
	   did not run (e.g. when script enqueue detection failed on this install). */
	window.processflowAdmin = window.processflowAdmin || <?php echo wp_json_encode( $pf_js_config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	window.pfStages = <?php echo $stages_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	</script>

	<div id="pf-notice-area"></div>

	<!-- ============================================================ -->
	<!-- Tab panels                                                    -->
	<!-- ============================================================ -->
	<main class="pf-frontend-main">

		<!-- ==================== DASHBOARD ========================= -->
		<section id="pf-tab-dashboard" class="pf-ftab <?php echo 'dashboard' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'Dashboard', 'processflow-manager' ); ?></h2>
			</div>

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

			<div style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;">
				<button class="pf-btn pf-btn--primary pf-switch-tab" data-tab="orders" id="pf-dash-btn-add-order">
					&#43; <?php esc_html_e( 'Add Order', 'processflow-manager' ); ?>
				</button>
				<?php if ( $is_admin ) : ?>
				<button class="pf-btn pf-btn--outline pf-switch-tab" data-tab="stages">
					<?php esc_html_e( 'Manage Stages', 'processflow-manager' ); ?>
				</button>
				<button class="pf-btn pf-btn--outline pf-switch-tab" data-tab="qr-codes">
					<?php esc_html_e( 'QR Codes', 'processflow-manager' ); ?>
				</button>
				<?php endif; ?>
			</div>

			<div class="pf-card">
				<div class="pf-card__header">
					<h3 class="pf-card__title"><?php esc_html_e( 'Recent Orders', 'processflow-manager' ); ?></h3>
					<button class="pf-btn pf-btn--outline pf-btn--sm pf-switch-tab" data-tab="orders">
						<?php esc_html_e( 'View All', 'processflow-manager' ); ?>
					</button>
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
								<?php if ( ! empty( $recent_orders['items'] ) ) : ?>
									<?php foreach ( $recent_orders['items'] as $o ) :
										$sc_info = isset( $stage_counts[ $o->current_stage ] ) ? $stage_counts[ $o->current_stage ] : null;
									?>
									<tr>
										<td>#<?php echo esc_html( $o->id ); ?></td>
										<td><?php echo esc_html( $o->customer_name ); ?></td>
										<td><?php echo esc_html( $o->business_name ); ?></td>
										<td>
											<?php if ( $sc_info ) : ?>
												<span class="pf-badge" style="background:<?php echo esc_attr( $sc_info['color'] ); ?>">
													<?php echo esc_html( $sc_info['name'] ); ?>
												</span>
											<?php else : ?>
												<span class="pf-badge" style="background:#aaa"><?php esc_html_e( 'N/A', 'processflow-manager' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $o->created_at ); ?></td>
									</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr><td colspan="5" style="text-align:center;padding:30px;color:#787c82;">
										<?php esc_html_e( 'No orders yet.', 'processflow-manager' ); ?>
									</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</section>

		<!-- ====================== ORDERS ========================== -->
		<section id="pf-tab-orders" class="pf-ftab <?php echo 'orders' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'Orders', 'processflow-manager' ); ?></h2>
			</div>

			<div class="pf-toolbar">
				<input type="search" id="pf-search" class="pf-search"
					placeholder="<?php esc_attr_e( 'Search orders…', 'processflow-manager' ); ?>"
					value="<?php echo esc_attr( $pf_search ); ?>">
				<select id="pf-filter-stage">
					<option value=""><?php esc_html_e( 'All Stages', 'processflow-manager' ); ?></option>
					<?php foreach ( $all_stages as $s ) : ?>
						<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $pf_stage_f, $s->id ); ?>>
							<?php echo esc_html( $s->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button id="pf-btn-add-order" class="pf-btn pf-btn--primary">
					&#43; <?php esc_html_e( 'Add Order', 'processflow-manager' ); ?>
				</button>
			</div>

			<div id="pf-order-table-wrap" data-page="<?php echo esc_attr( $pf_page_num ); ?>">
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
										<th><?php esc_html_e( 'Created', 'processflow-manager' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'processflow-manager' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( ! empty( $orders ) ) : ?>
										<?php foreach ( $orders as $order ) :
											$stage_obj = null;
											foreach ( $all_stages as $s ) {
												if ( (int) $s->id === (int) $order->current_stage ) {
													$stage_obj = $s;
													break;
												}
											}
										?>
										<tr id="pf-order-row-<?php echo esc_attr( $order->id ); ?>">
											<td>#<?php echo esc_html( $order->id ); ?></td>
											<td><?php echo esc_html( $order->customer_name ); ?></td>
											<td><?php echo esc_html( $order->business_name ); ?></td>
											<td>
												<a href="https://wa.me/<?php echo esc_attr( ltrim( $order->whatsapp, '+' ) ); ?>" target="_blank" rel="noopener noreferrer">
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
											<td><?php echo esc_html( $order->created_at ); ?></td>
											<td>
												<div style="display:flex;gap:5px;flex-wrap:wrap;">
													<button class="pf-btn pf-btn--outline pf-btn--sm pf-edit-order" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'Edit', 'processflow-manager' ); ?>">✏</button>
													<button class="pf-btn pf-btn--outline pf-btn--sm pf-show-qr" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'QR Code', 'processflow-manager' ); ?>">⊙</button>
													<button class="pf-btn pf-btn--success pf-btn--sm pf-advance-stage" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'Advance Stage', 'processflow-manager' ); ?>">▶</button>
													<button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-order" data-id="<?php echo esc_attr( $order->id ); ?>" title="<?php esc_attr_e( 'Delete', 'processflow-manager' ); ?>">✕</button>
												</div>
											</td>
										</tr>
										<?php endforeach; ?>
									<?php else : ?>
										<tr>
											<td colspan="7" style="text-align:center;padding:30px;color:#787c82;">
												<?php esc_html_e( 'No orders found.', 'processflow-manager' ); ?>
											</td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<?php if ( $orders_pages > 1 ) : ?>
				<div class="pf-pagination">
					<?php if ( $pf_page_num > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'pf_page' => $pf_page_num - 1, 'pf_tab' => 'orders' ) ) ); ?>">&laquo;</a>
					<?php endif; ?>
					<?php for ( $i = 1; $i <= $orders_pages; $i++ ) : ?>
						<?php if ( $i === $pf_page_num ) : ?>
							<span class="current"><?php echo esc_html( $i ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'pf_page' => $i, 'pf_tab' => 'orders' ) ) ); ?>"><?php echo esc_html( $i ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
					<?php if ( $pf_page_num < $orders_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'pf_page' => $pf_page_num + 1, 'pf_tab' => 'orders' ) ) ); ?>">&raquo;</a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
				<p class="pf-orders-counter" style="color:#787c82;font-size:12px;margin-top:8px;">
					<?php printf( esc_html__( '%d orders total', 'processflow-manager' ), esc_html( $orders_total ) ); ?>
				</p>
			</div>
		</section>

		<!-- ====================== STAGES ========================== -->
		<?php if ( $is_admin ) : ?>
		<section id="pf-tab-stages" class="pf-ftab <?php echo 'stages' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'Workflow Stages', 'processflow-manager' ); ?></h2>
			</div>

			<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;">
				<div class="pf-card">
					<div class="pf-card__header">
						<h3 class="pf-card__title"><?php esc_html_e( 'Current Stages', 'processflow-manager' ); ?></h3>
						<small style="color:#787c82;"><?php esc_html_e( 'Drag to reorder', 'processflow-manager' ); ?></small>
					</div>
					<div class="pf-card__body">
						<div id="pf-stage-notice"></div>
						<?php if ( ! empty( $all_stages ) ) : ?>
							<ul class="pf-stage-list" id="pf-stage-sortable">
								<?php foreach ( $all_stages as $stage ) : ?>
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
											<button class="pf-btn pf-btn--outline pf-btn--sm pf-edit-stage"><?php esc_html_e( 'Edit', 'processflow-manager' ); ?></button>
											<button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-stage"><?php esc_html_e( 'Delete', 'processflow-manager' ); ?></button>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p style="color:#787c82;"><?php esc_html_e( 'No stages yet.', 'processflow-manager' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="pf-card" style="position:sticky;top:80px;">
					<div class="pf-card__header">
						<h3 class="pf-card__title" id="pf-stage-form-heading"><?php esc_html_e( 'Add New Stage', 'processflow-manager' ); ?></h3>
					</div>
					<div class="pf-card__body">
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
								<textarea id="pf-stage-template" name="whatsapp_template" rows="4"></textarea>
								<p class="pf-hint"><?php esc_html_e( 'Merge tags:', 'processflow-manager' ); ?> <code>{customer_name}</code> <code>{business_name}</code> <code>{stage_name}</code> <code>{order_id}</code></p>
							</div>
							<div style="display:flex;gap:10px;flex-wrap:wrap;">
								<button type="submit" class="pf-btn pf-btn--primary"><?php esc_html_e( 'Add Stage', 'processflow-manager' ); ?></button>
								<button type="button" id="pf-stage-form-reset" class="pf-btn pf-btn--outline"><?php esc_html_e( 'Reset', 'processflow-manager' ); ?></button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</section>
		<?php endif; ?>

		<!-- ===================== QR CODES ========================= -->
		<section id="pf-tab-qr-codes" class="pf-ftab <?php echo 'qr-codes' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'QR Codes', 'processflow-manager' ); ?></h2>
			</div>

			<div class="pf-toolbar" style="margin-bottom:20px;">
				<div style="margin-left:auto;display:flex;gap:10px;align-items:center;">
					<label style="font-size:13px;">
						<input type="checkbox" id="pf-qr-select-all"> <?php esc_html_e( 'Select All', 'processflow-manager' ); ?>
					</label>
					<button id="pf-btn-print-labels" class="pf-btn pf-btn--primary">
						&#128438; <?php esc_html_e( 'Print PDF Labels', 'processflow-manager' ); ?>
					</button>
				</div>
			</div>

			<?php
			$qr_results = $db->get_orders( array( 'per_page' => 50, 'page' => 1 ) );
			$qr_orders  = $qr_results['items'];
			?>
			<?php if ( ! empty( $qr_orders ) ) : ?>
				<div class="pf-qr-grid">
					<?php foreach ( $qr_orders as $qr_order ) :
						$qr_url = $qr_engine->get_qr_image_url( (int) $qr_order->id );
						$qr_url = is_wp_error( $qr_url ) ? '' : $qr_url;
						$qr_stage_name = '';
						foreach ( $all_stages as $s ) {
							if ( (int) $s->id === (int) $qr_order->current_stage ) {
								$qr_stage_name = $s->name;
								break;
							}
						}
					?>
					<div class="pf-qr-card">
						<label>
							<input type="checkbox" class="pf-qr-checkbox" value="<?php echo esc_attr( $qr_order->id ); ?>">
						</label>
						<?php if ( $qr_url ) : ?>
							<img src="<?php echo esc_url( $qr_url ); ?>" alt="QR #<?php echo esc_attr( $qr_order->id ); ?>" loading="lazy">
						<?php endif; ?>
						<div class="pf-qr-card__id">
							<strong>#<?php echo esc_html( $qr_order->id ); ?></strong><br>
							<?php echo esc_html( $qr_order->customer_name ); ?>
							<?php if ( $qr_stage_name ) : ?>
								<br><span style="font-size:11px;color:#787c82;"><?php echo esc_html( $qr_stage_name ); ?></span>
							<?php endif; ?>
						</div>
						<div style="margin-top:8px;display:flex;justify-content:center;gap:6px;">
							<?php if ( $qr_url ) : ?>
								<a href="<?php echo esc_url( $qr_url ); ?>" download="qr-<?php echo esc_attr( $qr_order->id ); ?>.png"
									class="pf-btn pf-btn--outline pf-btn--sm">&#8595; PNG</a>
							<?php endif; ?>
							<button class="pf-btn pf-btn--outline pf-btn--sm pf-show-qr" data-id="<?php echo esc_attr( $qr_order->id ); ?>">
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
		</section>

		<!-- ======================= USERS ========================== -->
		<?php if ( $is_admin ) : ?>
		<section id="pf-tab-users" class="pf-ftab <?php echo 'users' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'Platform Users', 'processflow-manager' ); ?></h2>
				<button id="pf-btn-add-user" class="pf-btn pf-btn--primary">
					&#43; <?php esc_html_e( 'Add User', 'processflow-manager' ); ?>
				</button>
			</div>

			<div class="pf-card">
				<div class="pf-card__body" style="padding:0;">
					<div class="pf-table-wrap">
						<table class="pf-table" id="pf-users-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Username', 'processflow-manager' ); ?></th>
									<th><?php esc_html_e( 'Email', 'processflow-manager' ); ?></th>
									<th><?php esc_html_e( 'Role', 'processflow-manager' ); ?></th>
									<th><?php esc_html_e( 'Status', 'processflow-manager' ); ?></th>
									<th><?php esc_html_e( 'Created', 'processflow-manager' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'processflow-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! empty( $pf_users ) ) : ?>
									<?php foreach ( $pf_users as $pf_user ) : ?>
									<tr id="pf-user-row-<?php echo esc_attr( $pf_user->id ); ?>">
										<td><?php echo esc_html( $pf_user->username ); ?></td>
										<td><?php echo esc_html( $pf_user->email ); ?></td>
										<td>
											<span class="pf-badge pf-badge--role-<?php echo esc_attr( $pf_user->role ); ?>">
												<?php echo esc_html( ucfirst( $pf_user->role ) ); ?>
											</span>
										</td>
										<td>
											<?php if ( $pf_user->is_active ) : ?>
												<span class="pf-badge" style="background:#27ae60"><?php esc_html_e( 'Active', 'processflow-manager' ); ?></span>
											<?php else : ?>
												<span class="pf-badge" style="background:#aaa"><?php esc_html_e( 'Inactive', 'processflow-manager' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $pf_user->created_at ); ?></td>
										<td>
											<div style="display:flex;gap:5px;">
												<button class="pf-btn pf-btn--outline pf-btn--sm pf-edit-user"
													data-id="<?php echo esc_attr( $pf_user->id ); ?>"
													data-username="<?php echo esc_attr( $pf_user->username ); ?>"
													data-email="<?php echo esc_attr( $pf_user->email ); ?>"
													data-role="<?php echo esc_attr( $pf_user->role ); ?>"
													data-is-active="<?php echo esc_attr( $pf_user->is_active ); ?>">
													✏
												</button>
												<button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-user" data-id="<?php echo esc_attr( $pf_user->id ); ?>">✕</button>
											</div>
										</td>
									</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="6" style="text-align:center;padding:30px;color:#787c82;">
											<?php esc_html_e( 'No platform users yet. Add one above.', 'processflow-manager' ); ?>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</section>
		<?php endif; ?>

		<!-- ======================= IMPORT ========================= -->
		<section id="pf-tab-import" class="pf-ftab <?php echo 'import' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'Import Orders', 'processflow-manager' ); ?></h2>
			</div>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
				<div class="pf-card">
					<div class="pf-card__header">
						<h3 class="pf-card__title"><?php esc_html_e( 'CSV Import', 'processflow-manager' ); ?></h3>
					</div>
					<div class="pf-card__body">
						<p style="color:#555;margin-top:0;">
							<?php esc_html_e( 'Upload a CSV file to bulk import orders. Required columns: customer_name, business_name, whatsapp, job_details. Optional: stage_name.', 'processflow-manager' ); ?>
						</p>

						<div id="pf-import-notice"></div>

						<form id="pf-import-csv-form" enctype="multipart/form-data">
							<div class="pf-form-group">
								<label><?php esc_html_e( 'Select CSV File', 'processflow-manager' ); ?></label>
								<div class="pf-file-drop" id="pf-file-drop">
									<input type="file" name="csv_file" id="pf-csv-file" accept=".csv,text/csv" required class="pf-file-input">
									<div class="pf-file-drop__text">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="36" height="36"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
										<span><?php esc_html_e( 'Click or drag CSV here', 'processflow-manager' ); ?></span>
										<small id="pf-file-name" style="color:#787c82;"><?php esc_html_e( 'No file selected', 'processflow-manager' ); ?></small>
									</div>
								</div>
							</div>
							<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
								<button type="submit" class="pf-btn pf-btn--primary" id="pf-import-submit">
									&#8679; <?php esc_html_e( 'Import Orders', 'processflow-manager' ); ?>
								</button>
							</div>
						</form>

						<div id="pf-import-result" style="display:none;margin-top:16px;"></div>
					</div>
				</div>

				<div class="pf-card">
					<div class="pf-card__header">
						<h3 class="pf-card__title"><?php esc_html_e( 'CSV Template', 'processflow-manager' ); ?></h3>
					</div>
					<div class="pf-card__body">
						<p style="color:#555;margin-top:0;"><?php esc_html_e( 'Download a CSV template file pre-filled with example data to see the expected format.', 'processflow-manager' ); ?></p>
						<div class="pf-template-cols">
							<div class="pf-template-col-item"><code>customer_name</code></div>
							<div class="pf-template-col-item"><code>business_name</code></div>
							<div class="pf-template-col-item"><code>whatsapp</code></div>
							<div class="pf-template-col-item"><code>job_details</code></div>
							<div class="pf-template-col-item pf-template-col-item--optional"><code>stage_name</code> <span><?php esc_html_e( 'optional', 'processflow-manager' ); ?></span></div>
						</div>
						<button id="pf-btn-csv-template" class="pf-btn pf-btn--outline" style="margin-top:20px;">
							&#8595; <?php esc_html_e( 'Download Template', 'processflow-manager' ); ?>
						</button>
					</div>
				</div>
			</div>
		</section>

		<!-- ================== INVOICE NINJA ======================= -->
		<?php if ( $is_admin ) : ?>
		<section id="pf-tab-invoiceninja" class="pf-ftab <?php echo 'invoiceninja' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'Invoice Ninja Integration', 'processflow-manager' ); ?></h2>
			</div>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
				<div class="pf-card">
					<div class="pf-card__header">
						<h3 class="pf-card__title"><?php esc_html_e( 'Connection Settings', 'processflow-manager' ); ?></h3>
					</div>
					<div class="pf-card__body">
						<p style="color:#555;margin-top:0;">
							<?php esc_html_e( 'Configure your Invoice Ninja connection. The API token is found in Invoice Ninja under Settings → API Tokens.', 'processflow-manager' ); ?>
						</p>
						<div id="pf-in-settings-notice"></div>
						<form class="pf-settings-form" data-notice="#pf-in-settings-notice">
							<div class="pf-form-group">
								<label><?php esc_html_e( 'Invoice Ninja URL', 'processflow-manager' ); ?></label>
								<input type="url" name="invoiceninja_url" value="<?php echo esc_attr( $in_url ); ?>" placeholder="https://your-invoiceninja.com">
							</div>
							<div class="pf-form-group">
								<label><?php esc_html_e( 'API Token', 'processflow-manager' ); ?></label>
								<input type="text" name="invoiceninja_token" value="<?php echo esc_attr( $in_token ); ?>" placeholder="<?php esc_attr_e( 'Paste your API token here', 'processflow-manager' ); ?>" autocomplete="off">
							</div>
							<button type="submit" class="pf-btn pf-btn--primary">
								<?php esc_html_e( 'Save Connection', 'processflow-manager' ); ?>
							</button>
						</form>
					</div>
				</div>

				<div class="pf-card">
					<div class="pf-card__header">
						<h3 class="pf-card__title"><?php esc_html_e( 'Sync Invoices', 'processflow-manager' ); ?></h3>
					</div>
					<div class="pf-card__body">
						<p style="color:#555;margin-top:0;">
							<?php esc_html_e( 'Pull all invoices from Invoice Ninja and create them as orders in ProcessFlow.', 'processflow-manager' ); ?>
						</p>
						<?php if ( $in_url && $in_token ) : ?>
							<div class="pf-in-status pf-in-status--connected">
								<svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
								<?php esc_html_e( 'Connected', 'processflow-manager' ); ?>
							</div>
						<?php else : ?>
							<div class="pf-in-status pf-in-status--disconnected">
								<svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
								<?php esc_html_e( 'Not configured – fill in connection settings first.', 'processflow-manager' ); ?>
							</div>
						<?php endif; ?>
						<div id="pf-in-sync-notice" style="margin:12px 0;"></div>
						<button id="pf-btn-sync-ninja" class="pf-btn pf-btn--primary" <?php echo ( ! $in_url || ! $in_token ) ? 'disabled' : ''; ?>>
							&#8635; <?php esc_html_e( 'Sync Now', 'processflow-manager' ); ?>
						</button>
						<div id="pf-in-sync-result" style="display:none;margin-top:16px;"></div>
					</div>
				</div>
			</div>
		</section>
		<?php endif; ?>

		<!-- ===================== SETTINGS ========================= -->
		<?php if ( $is_admin ) : ?>
		<section id="pf-tab-settings" class="pf-ftab <?php echo 'settings' === $active_tab ? 'active' : ''; ?>">
			<div class="pf-ftab__header">
				<h2><?php esc_html_e( 'Settings', 'processflow-manager' ); ?></h2>
			</div>

			<div id="pf-settings-notice"></div>

			<!-- Sub-tabs -->
			<div class="pf-tabs">
				<button class="pf-tab active" data-target="pf-stab-general"><?php esc_html_e( 'General', 'processflow-manager' ); ?></button>
				<button class="pf-tab" data-target="pf-stab-security"><?php esc_html_e( 'Security', 'processflow-manager' ); ?></button>
				<button class="pf-tab" data-target="pf-stab-fields"><?php esc_html_e( 'Custom Fields', 'processflow-manager' ); ?></button>
			</div>

			<div id="pf-stab-general" class="pf-tab-content active">
				<div class="pf-card">
					<div class="pf-card__header"><h3 class="pf-card__title"><?php esc_html_e( 'Company Details', 'processflow-manager' ); ?></h3></div>
					<div class="pf-card__body">
						<?php $defaults = $settings->get_default_settings(); ?>
						<form class="pf-settings-form" data-notice="#pf-settings-notice">
							<div class="pf-form-group">
								<label><?php esc_html_e( 'Company Name', 'processflow-manager' ); ?></label>
								<input type="text" name="company_name" value="<?php echo esc_attr( $settings->get_setting( 'company_name', $defaults['company_name'] ) ); ?>">
							</div>
							<div class="pf-form-group">
								<label><?php esc_html_e( 'Company Phone', 'processflow-manager' ); ?></label>
								<input type="text" name="company_phone" value="<?php echo esc_attr( $settings->get_setting( 'company_phone', '' ) ); ?>">
							</div>
							<div class="pf-form-group">
								<label><?php esc_html_e( 'Company Email', 'processflow-manager' ); ?></label>
								<input type="email" name="company_email" value="<?php echo esc_attr( $settings->get_setting( 'company_email', $defaults['company_email'] ) ); ?>">
							</div>
							<hr style="margin:20px 0;">
							<div class="pf-form-group">
								<label><?php esc_html_e( 'User Portal Title', 'processflow-manager' ); ?></label>
								<input type="text" name="portal_title" value="<?php echo esc_attr( $settings->get_setting( 'portal_title', $defaults['portal_title'] ) ); ?>">
							</div>
							<div class="pf-form-group">
								<label><?php esc_html_e( 'User Portal Intro Text', 'processflow-manager' ); ?></label>
								<textarea name="portal_intro" rows="3"><?php echo esc_textarea( $settings->get_setting( 'portal_intro', $defaults['portal_intro'] ) ); ?></textarea>
							</div>
							<div class="pf-form-group">
								<label><?php esc_html_e( 'Orders Per Page', 'processflow-manager' ); ?></label>
								<input type="number" name="orders_per_page" min="5" max="100" value="<?php echo esc_attr( $settings->get_setting( 'orders_per_page', 20 ) ); ?>" style="max-width:100px;">
							</div>
							<button type="submit" class="pf-btn pf-btn--primary"><?php esc_html_e( 'Save Settings', 'processflow-manager' ); ?></button>
						</form>
					</div>
				</div>
			</div>

			<div id="pf-stab-security" class="pf-tab-content">
				<div class="pf-card">
					<div class="pf-card__header"><h3 class="pf-card__title"><?php esc_html_e( 'Admin Portal Password', 'processflow-manager' ); ?></h3></div>
					<div class="pf-card__body">
						<p style="color:#555;margin-top:0;"><?php esc_html_e( 'This password is the fallback for the front-end admin login when no username is found in the platform users table.', 'processflow-manager' ); ?></p>
						<form class="pf-settings-form" data-notice="#pf-settings-notice">
							<div class="pf-form-group">
								<label><?php esc_html_e( 'New Password', 'processflow-manager' ); ?></label>
								<input type="password" name="new_password" autocomplete="new-password">
							</div>
							<div class="pf-form-group">
								<label><?php esc_html_e( 'Confirm New Password', 'processflow-manager' ); ?></label>
								<input type="password" name="confirm_password" autocomplete="new-password">
							</div>
							<button type="submit" class="pf-btn pf-btn--primary"><?php esc_html_e( 'Update Password', 'processflow-manager' ); ?></button>
						</form>
					</div>
				</div>
			</div>

			<div id="pf-stab-fields" class="pf-tab-content">
				<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;">
					<div class="pf-card">
						<div class="pf-card__header"><h3 class="pf-card__title"><?php esc_html_e( 'Custom Fields', 'processflow-manager' ); ?></h3></div>
						<div class="pf-card__body" style="padding:0;">
							<?php $custom_fields = $db->get_custom_fields(); ?>
							<?php if ( ! empty( $custom_fields ) ) : ?>
								<table class="pf-table">
									<thead><tr>
										<th><?php esc_html_e( 'Label', 'processflow-manager' ); ?></th>
										<th><?php esc_html_e( 'Type', 'processflow-manager' ); ?></th>
										<th><?php esc_html_e( 'Required', 'processflow-manager' ); ?></th>
										<th></th>
									</tr></thead>
									<tbody>
										<?php foreach ( $custom_fields as $cf ) : ?>
											<tr>
												<td><?php echo esc_html( $cf->field_label ); ?></td>
												<td><?php echo esc_html( $cf->field_type ); ?></td>
												<td><?php echo $cf->is_required ? esc_html__( 'Yes', 'processflow-manager' ) : esc_html__( 'No', 'processflow-manager' ); ?></td>
												<td><button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-custom-field" data-id="<?php echo esc_attr( $cf->id ); ?>"><?php esc_html_e( 'Delete', 'processflow-manager' ); ?></button></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php else : ?>
								<p style="padding:20px;color:#787c82;"><?php esc_html_e( 'No custom fields yet.', 'processflow-manager' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<div class="pf-card">
						<div class="pf-card__header"><h3 class="pf-card__title"><?php esc_html_e( 'Add Custom Field', 'processflow-manager' ); ?></h3></div>
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
									<label><input type="checkbox" name="is_required" value="1"> <?php esc_html_e( 'Required field', 'processflow-manager' ); ?></label>
								</div>
								<button type="submit" class="pf-btn pf-btn--primary"><?php esc_html_e( 'Add Field', 'processflow-manager' ); ?></button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php endif; ?>

	</main><!-- .pf-frontend-main -->
</div><!-- .pf-frontend-wrap -->
