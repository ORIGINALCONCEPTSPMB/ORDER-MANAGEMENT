/* global jQuery, processflowPublic */
/**
 * ProcessFlow Manager – Public / User Portal JavaScript
 *
 * Handles AJAX portal login, order lookup, status refresh and copy-to-clipboard.
 */
(function ($) {
	'use strict';

	const Portal = {
		nonce:   processflowPublic.nonce,
		ajaxUrl: processflowPublic.ajax_url,
		strings: processflowPublic.strings,

		// Last looked-up invoice/order reference.
		orderRef: sessionStorage.getItem('pf_portal_order_ref') || '',

		// -------------------------------------------------------------- //
		// Bootstrap                                                        //
		// -------------------------------------------------------------- //
		init() {
			this.bindLogin();
			this.bindCopyOrderId();
			this.bindRefresh();

			// Auto-load if we already have a recent lookup.
			if (this.orderRef) {
				$('#pf-portal-order-id').val(this.orderRef);
				this.loadOrder(this.orderRef);
			}
		},

		// -------------------------------------------------------------- //
		// Utilities                                                        //
		// -------------------------------------------------------------- //
		notice(msg, type = 'error') {
			$('#pf-portal-notice').html(
				`<div class="pf-pub-notice pf-pub-notice--${type}">${msg}</div>`
			);
		},

		clearNotice() {
			$('#pf-portal-notice').empty();
		},

		post(action, data = {}) {
			return $.post(this.ajaxUrl, {
				action,
				nonce: this.nonce,
				...data,
			});
		},

		// -------------------------------------------------------------- //
		// Lookup                                                           //
		// -------------------------------------------------------------- //
		bindLogin() {
			$(document).on('submit', '#pf-portal-login-form', (e) => {
				e.preventDefault();
				this.clearNotice();

				const orderId = $('#pf-portal-order-id').val().trim();

				if (!orderId) {
					this.notice(this.strings.not_found);
					return;
				}
				this.orderRef = orderId;
				sessionStorage.setItem('pf_portal_order_ref', orderId);

				const $btn = $('#pf-portal-login-btn').prop('disabled', true).text(this.strings.loading);
				this.loadOrder(orderId).always(() => {
					$btn.prop('disabled', false).text('Track My Order');
				});
			});
		},

		// -------------------------------------------------------------- //
		// Order lookup & rendering                                         //
		// -------------------------------------------------------------- //
		loadOrder(orderRef = this.orderRef) {
			if (!orderRef) return $.Deferred().resolve();

			$('#pf-portal-result').html(
				`<div class="pf-spinner">${this.strings.loading}</div>`
			);

			return this.post('processflow_lookup_order', { order_id: orderRef }).done((res) => {
				if (res.success) {
					this.renderOrder(res.data);
				} else {
					$('#pf-portal-result').empty();
					this.notice(res.data.message);
				}
			}).fail(() => {
				$('#pf-portal-result').empty();
				this.notice(this.strings.error);
			});
		},

		renderOrder(data) {
			const { order, history, stages, wa_url, is_admin_view } = data;
			const displayOrderNumber = order.invoice_number ? String(order.invoice_number) : String(order.id);

			// ---- Progress ------------------------------------------- //
			const totalStages   = stages.length;
			const currentPos    = stages.findIndex(s => parseInt(s.id) === parseInt(order.current_stage));
			const progressPct   = totalStages > 0 ? Math.round(((currentPos + 1) / totalStages) * 100) : 0;

			// ---- Stage steps ---------------------------------------- //
			const stepsHtml = stages.map((s, idx) => {
				let cls = '';
				if (idx < currentPos)  cls = 'completed';
				if (idx === currentPos) cls = 'active';
				return `
				<div class="pf-stage-step ${cls}">
					<div class="pf-stage-step__dot" style="${idx <= currentPos ? `background:${s.color};border-color:${s.color}` : ''}">
						${idx < currentPos ? '✓' : idx + 1}
					</div>
					<span class="pf-stage-step__name">${this.escHtml(s.name)}</span>
				</div>`;
			}).join('');

			// ---- History -------------------------------------------- //
			const historyRows = Array.isArray(history) ? history : [];
			const historyHtml = historyRows.length
				? historyRows.map(h => `
				<div class="pf-timeline-item">
					<div class="pf-timeline-item__stage">
						<span class="pf-pub-badge" style="background:${this.escHtml(h.stage_color || '#666')}">${this.escHtml(h.stage_name)}</span>
					</div>
					<div class="pf-timeline-item__date">
						Entered: ${this.escHtml(h.entered_at)}
						${h.completed_at ? ' &nbsp;|&nbsp; Completed: ' + this.escHtml(h.completed_at) : ' <em>(current)</em>'}
					</div>
				</div>`).join('')
				: '<p style="color:#718096">No history yet.</p>';

			const html = `
			<div class="pf-order-card">
				<div class="pf-order-card__header">
					<div>
						<div class="pf-order-card__id">Order #${this.escHtml(displayOrderNumber)}
							<button class="pf-pub-btn pf-pub-btn--outline pf-pub-btn--sm pf-copy-btn" data-copy="${displayOrderNumber.replace(/"/g, '&quot;')}" style="margin-left:8px;vertical-align:middle;">&#128203; Copy</button>
						</div>
						<div class="pf-order-card__customer">${is_admin_view ? `${this.escHtml(order.customer_name)} – ${this.escHtml(order.business_name)}` : this.escHtml(order.stage_name || '')}</div>
					</div>
					<span class="pf-pub-badge" style="background:${this.escHtml(order.stage_color || '#666')}">${this.escHtml(order.stage_name || 'N/A')}</span>
				</div>
				<div class="pf-order-card__body">
					<div class="pf-order-meta">
						<div class="pf-order-meta__item">
							<div class="pf-order-meta__label">Ordered</div>
							<div class="pf-order-meta__value">${this.escHtml(order.created_at)}</div>
						</div>
						<div class="pf-order-meta__item">
							<div class="pf-order-meta__label">Last Updated</div>
							<div class="pf-order-meta__value">${this.escHtml(order.updated_at)}</div>
						</div>
						${is_admin_view ? `<div class="pf-order-meta__item" style="grid-column:span 2">
							<div class="pf-order-meta__label">Job Details</div>
							<div class="pf-order-meta__value" style="font-weight:400">${this.escHtml(order.job_details || '—')}</div>
						</div>` : ''}
					</div>

					<div class="pf-progress">
						<div class="pf-progress__label">Progress: ${progressPct}%</div>
						<div class="pf-progress__track">
							<div class="pf-progress__bar" style="width:${progressPct}%;background:${this.escHtml(order.stage_color || '#2271b1')}"></div>
						</div>
					</div>

					<div class="pf-stage-steps">${stepsHtml}</div>

					${is_admin_view ? `<h3 style="font-size:15px;margin:24px 0 12px;">History</h3>
					<div class="pf-timeline">${historyHtml}</div>` : ''}

					<div class="pf-order-actions">
						${is_admin_view && wa_url && wa_url !== '#' ? `<a href="${wa_url}" target="_blank" class="pf-pub-btn pf-pub-btn--whatsapp">&#128172; Contact via WhatsApp</a>` : ''}
						<button id="pf-portal-refresh" class="pf-pub-btn pf-pub-btn--outline">&#8635; Refresh</button>
					</div>
				</div>
			</div>`;

			$('#pf-portal-result').html(html);
		},

		escHtml(str) {
			return String(str)
				.replace(/&/g,  '&amp;')
				.replace(/</g,  '&lt;')
				.replace(/>/g,  '&gt;')
				.replace(/"/g,  '&quot;')
				.replace(/'/g,  '&#039;');
		},

		// -------------------------------------------------------------- //
		// Refresh                                                          //
		// -------------------------------------------------------------- //
		bindRefresh() {
			$(document).on('click', '#pf-portal-refresh', () => this.loadOrder(this.orderRef));
		},

		// -------------------------------------------------------------- //
		// Copy order ID                                                    //
		// -------------------------------------------------------------- //
		bindCopyOrderId() {
			$(document).on('click', '.pf-copy-btn', function () {
				const text = $(this).data('copy');
				if (navigator.clipboard) {
					navigator.clipboard.writeText(String(text)).then(() => {
						$(this).text(Portal.strings.copied).addClass('copied');
						setTimeout(() => $(this).text('&#128203; Copy').removeClass('copied'), 2000);
					});
				}
			});
		},
	};

	$(document).ready(() => Portal.init());

}(jQuery));
