<?php
/**
 * Public partial: QR Scan landing page.
 *
 * Variables injected by ProcessFlow_Public::qr_scan_shortcode():
 *   $error_msg  - non-empty string when the QR code is invalid or missing
 *   $order      - order row object (present when $error_msg is empty)
 *   $stage      - current stage row object or null
 *   $stages     - array of all active stage row objects
 *   $history    - array of stage history row objects
 *   $wa_url     - pre-filled WhatsApp deep-link URL
 *
 * @package ProcessFlow_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="pf-qr-scan-page">

	<?php if ( ! empty( $error_msg ) ) : ?>

		<div class="pf-qr-scan-error">
			<div class="pf-qr-scan-error__icon">&#10060;</div>
			<h2 class="pf-qr-scan-error__title">
				<?php esc_html_e( 'Invalid QR Code', 'processflow-manager' ); ?>
			</h2>
			<p class="pf-qr-scan-error__msg"><?php echo esc_html( $error_msg ); ?></p>
		</div>

	<?php else : ?>

		<?php
		// Work out how far through the workflow this order is.
		$total_stages  = count( $stages );
		$current_pos   = -1;
		foreach ( $stages as $idx => $s ) {
			if ( $stage && (int) $s->id === (int) $stage->id ) {
				$current_pos = $idx;
				break;
			}
		}
		$progress_pct = $total_stages > 0
			? (int) round( ( ( $current_pos + 1 ) / $total_stages ) * 100 )
			: 0;
		?>

		<!-- Header bar -->
		<div class="pf-qr-scan-header">
			<div class="pf-qr-scan-header__left">
				<div class="pf-qr-scan-header__order-id">
					<?php
					/* translators: %d = order ID */
					printf( esc_html__( 'Order #%d', 'processflow-manager' ), (int) $order->id );
					?>
				</div>
				<div class="pf-qr-scan-header__customer">
					<?php echo esc_html( $order->customer_name ); ?>
					<?php if ( $order->business_name ) : ?>
						&ndash; <?php echo esc_html( $order->business_name ); ?>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $stage ) : ?>
				<span class="pf-pub-badge pf-qr-scan-header__badge"
					style="background:<?php echo esc_attr( $stage->color ); ?>">
					<?php echo esc_html( $stage->name ); ?>
				</span>
			<?php endif; ?>
		</div>

		<!-- Job details -->
		<?php if ( ! empty( $order->job_details ) ) : ?>
			<div class="pf-qr-scan-meta">
				<span class="pf-qr-scan-meta__label">
					<?php esc_html_e( 'Job Details', 'processflow-manager' ); ?>
				</span>
				<span class="pf-qr-scan-meta__value">
					<?php echo esc_html( $order->job_details ); ?>
				</span>
			</div>
		<?php endif; ?>

		<!-- Progress bar -->
		<?php if ( $total_stages > 0 ) : ?>
			<div class="pf-progress pf-qr-scan-progress">
				<div class="pf-progress__label">
					<?php
					/* translators: %d = percentage complete */
					printf( esc_html__( 'Progress: %d%%', 'processflow-manager' ), $progress_pct );
					?>
				</div>
				<div class="pf-progress__track">
					<div class="pf-progress__bar"
						style="width:<?php echo esc_attr( $progress_pct ); ?>%;background:<?php echo esc_attr( $stage ? $stage->color : '#2271b1' ); ?>">
					</div>
				</div>
			</div>

			<!-- Stage steps -->
			<div class="pf-stage-steps">
				<?php foreach ( $stages as $idx => $s ) :
					$cls = '';
					if ( $idx < $current_pos )  { $cls = 'completed'; }
					if ( $idx === $current_pos ) { $cls = 'active'; }
				?>
					<div class="pf-stage-step <?php echo esc_attr( $cls ); ?>">
						<div class="pf-stage-step__dot"
							<?php if ( $idx <= $current_pos ) : ?>
								style="background:<?php echo esc_attr( $s->color ); ?>;border-color:<?php echo esc_attr( $s->color ); ?>"
							<?php endif; ?>>
							<?php echo $idx < $current_pos ? '&#10003;' : esc_html( (string) ( $idx + 1 ) ); ?>
						</div>
						<span class="pf-stage-step__name"><?php echo esc_html( $s->name ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Stage selector – lets a staff member update the order stage on-scan -->
		<?php if ( ! empty( $stages ) ) : ?>
			<div class="pf-qr-scan-stage-update" style="background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin:20px 0;">
				<h3 style="margin:0 0 12px;font-size:15px;">
					<?php esc_html_e( 'Update Process Stage', 'processflow-manager' ); ?>
				</h3>
				<p style="margin:0 0 12px;color:#555;font-size:13px;">
					<?php esc_html_e( 'Select the current stage for this job and tap "Update Stage".', 'processflow-manager' ); ?>
				</p>
				<div id="pf-qr-stage-notice" style="margin-bottom:10px;"></div>
				<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
					<select id="pf-qr-stage-select" style="flex:1;min-width:180px;padding:8px;border:1px solid #d0d5dd;border-radius:6px;font-size:14px;">
						<option value="0"><?php esc_html_e( '— Select stage —', 'processflow-manager' ); ?></option>
						<?php foreach ( $stages as $s ) : ?>
							<option value="<?php echo esc_attr( $s->id ); ?>"
								<?php if ( $stage && (int) $s->id === (int) $stage->id ) : ?>selected<?php endif; ?>>
								<?php echo esc_html( $s->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button id="pf-qr-update-stage-btn"
						style="padding:8px 18px;background:#2271b1;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;white-space:nowrap;">
						<?php esc_html_e( 'Update Stage', 'processflow-manager' ); ?>
					</button>
				</div>
			</div>
			<script>
			(function() {
				var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var orderId = <?php echo (int) $order->id; ?>;
				var hash    = <?php echo wp_json_encode( $order->qr_code_hash ); ?>;

				document.getElementById('pf-qr-update-stage-btn').addEventListener('click', function() {
					var stageId = document.getElementById('pf-qr-stage-select').value;
					var btn     = this;
					var notice  = document.getElementById('pf-qr-stage-notice');

					btn.disabled = true;
					btn.textContent = '<?php echo esc_js( __( 'Saving…', 'processflow-manager' ) ); ?>';
					notice.innerHTML = '';

					var fd = new FormData();
					fd.append('action',   'processflow_qr_update_stage');
					fd.append('order_id', orderId);
					fd.append('hash',     hash);
					fd.append('stage_id', stageId);

					fetch(ajaxUrl, { method: 'POST', body: fd })
						.then(function(r) { return r.json(); })
						.then(function(res) {
							if (res.success) {
								notice.innerHTML = '<p style="color:#27ae60;margin:0;">' +
									'<?php echo esc_js( __( '✓ Stage updated.', 'processflow-manager' ) ); ?>' +
									(res.data.stage_name ? ' ' + res.data.stage_name : '') +
									'</p>';
								// Update the badge in the header.
								var badge = document.querySelector('.pf-qr-scan-header__badge');
								if (badge && res.data.stage_name) {
									badge.textContent = res.data.stage_name;
									if (res.data.stage_color) badge.style.background = res.data.stage_color;
								}
								// Show new WhatsApp button if a wa_url was returned.
								if (res.data.wa_url && res.data.wa_url !== '#') {
									var existingWa = document.querySelector('.pf-qr-scan-cta__btn');
									if (existingWa) {
										existingWa.href = res.data.wa_url;
									}
								}
							} else {
								notice.innerHTML = '<p style="color:#e74c3c;margin:0;">' +
									(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'An error occurred.', 'processflow-manager' ) ); ?>') +
									'</p>';
							}
						})
						.catch(function() {
							notice.innerHTML = '<p style="color:#e74c3c;margin:0;"><?php echo esc_js( __( 'Network error. Please try again.', 'processflow-manager' ) ); ?></p>';
						})
						.finally(function() {
							btn.disabled = false;
							btn.textContent = '<?php echo esc_js( __( 'Update Stage', 'processflow-manager' ) ); ?>';
						});
				});
			}());
			</script>
		<?php endif; ?>

		<!-- WhatsApp CTA -->
		<div class="pf-qr-scan-cta">
			<?php if ( $wa_url && '#' !== $wa_url ) : ?>
				<a href="<?php echo esc_url( $wa_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					class="pf-pub-btn pf-pub-btn--whatsapp pf-qr-scan-cta__btn">
					&#128172;
					<?php esc_html_e( 'Send WhatsApp Update to Customer', 'processflow-manager' ); ?>
				</a>
				<p class="pf-qr-scan-cta__hint">
					<?php esc_html_e( 'Tapping the button above opens WhatsApp with the stage update message pre-filled.', 'processflow-manager' ); ?>
				</p>
			<?php else : ?>
				<p class="pf-qr-scan-cta__no-wa">
					<?php esc_html_e( 'No WhatsApp number on file for this order.', 'processflow-manager' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<!-- Stage history -->
		<?php if ( ! empty( $history ) ) : ?>
			<div class="pf-qr-scan-history">
				<h3 class="pf-qr-scan-history__title">
					<?php esc_html_e( 'Stage History', 'processflow-manager' ); ?>
				</h3>
				<div class="pf-timeline">
					<?php foreach ( $history as $entry ) : ?>
						<div class="pf-timeline-item">
							<div class="pf-timeline-item__stage">
								<span class="pf-pub-badge"
									style="background:<?php echo esc_attr( $entry->stage_color ?: '#666' ); ?>">
									<?php echo esc_html( $entry->stage_name ?: __( 'Unknown stage', 'processflow-manager' ) ); ?>
								</span>
							</div>
							<div class="pf-timeline-item__date">
								<?php
								/* translators: %s = date/time string */
								printf( esc_html__( 'Entered: %s', 'processflow-manager' ), esc_html( $entry->entered_at ) );
								if ( $entry->completed_at ) {
									echo ' &nbsp;|&nbsp; ';
									/* translators: %s = date/time string */
									printf( esc_html__( 'Completed: %s', 'processflow-manager' ), esc_html( $entry->completed_at ) );
								} else {
									echo ' <em>(' . esc_html__( 'current', 'processflow-manager' ) . ')</em>';
								}
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; ?>

</div>
