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
