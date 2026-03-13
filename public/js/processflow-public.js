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

		// Auth token stored in sessionStorage.
		token:   sessionStorage.getItem('pf_portal_token') || '',

		// -------------------------------------------------------------- //
		// Bootstrap                                                        //
		// -------------------------------------------------------------- //
		init() {
			this.bindLogin();
			this.bindCopyOrderId();
			this.bindRefresh();

			// Auto-load if we already have a token.
			if (this.token) {
				this.loadOrder();
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
		// Login                                                            //
		// -------------------------------------------------------------- //
		bindLogin() {
			$(document).on('submit', '#pf-portal-login-form', (e) => {
				e.preventDefault();
				this.clearNotice();

				const orderId = $('#pf-portal-order-id').val().trim();
				const wa4     = $('#pf-portal-wa-last4').val().trim();

				if (!orderId || wa4.length !== 4 || !/^\d{4}$/.test(wa4)) {
					this.notice(this.strings.not_found);
					return;
				}

				const $btn = $('#pf-portal-login-btn').prop('disabled', true).text(this.strings.loading);

				this.post('processflow_portal_login', {
					order_id: orderId,
					wa_last4: wa4,
				}).done((res) => {
					if (res.success) {
						this.token = res.data.token;
						sessionStorage.setItem('pf_portal_token', this.token);
						$('#pf-portal-login-section').hide();
						this.loadOrder();
					} else {
						this.notice(res.data.message);
					}
				}).fail(() => {
					this.notice(this.strings.error);
				}).always(() => {
					$btn.prop('disabled', false).text('Track My Order');
				});
			});

			// Logout.
			$(document).on('click', '#pf-portal-logout', () => {
				sessionStorage.removeItem('pf_portal_token');
				this.token = '';
				$('#pf-portal-result').empty();
				$('#pf-portal-login-section').show();
				this.clearNotice();
			});
		},

		// -------------------------------------------------------------- //
		// Order lookup & rendering                                         //
		// -------------------------------------------------------------- //
		loadOrder() {
			$('#pf-portal-result').html(
				`<div class="pf-spinner">${this.strings.loading}</div>`
			);

			this.post('processflow_lookup_order', { token: this.token }).done((res) => {
				if (res.success) {
					$('#pf-portal-login-section').hide();
					this.renderOrder(res.data);
				} else {
					// Session expired.
					sessionStorage.removeItem('pf_portal_token');
					this.token = '';
					$('#pf-portal-result').empty();
					$('#pf-portal-login-section').show();
					this.notice(res.data.message);
				}
			}).fail(() => {
				$('#pf-portal-result').empty();
				this.notice(this.strings.error);
			});
		},

		renderOrder(data) {
			const { order, history, stages, wa_url } = data;

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
			const historyHtml = history.length
				? history.map(h => `
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
						<div class="pf-order-card__id">Order #${this.escHtml(String(order.id))}
							<button class="pf-pub-btn pf-pub-btn--outline pf-pub-btn--sm pf-copy-btn" data-copy="${order.id}" style="margin-left:8px;vertical-align:middle;">&#128203; Copy</button>
						</div>
						<div class="pf-order-card__customer">${this.escHtml(order.customer_name)} – ${this.escHtml(order.business_name)}</div>
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
						<div class="pf-order-meta__item" style="grid-column:span 2">
							<div class="pf-order-meta__label">Job Details</div>
							<div class="pf-order-meta__value" style="font-weight:400">${this.escHtml(order.job_details || '—')}</div>
						</div>
					</div>

					<div class="pf-progress">
						<div class="pf-progress__label">Progress: ${progressPct}%</div>
						<div class="pf-progress__track">
							<div class="pf-progress__bar" style="width:${progressPct}%;background:${this.escHtml(order.stage_color || '#2271b1')}"></div>
						</div>
					</div>

					<div class="pf-stage-steps">${stepsHtml}</div>

					<h3 style="font-size:15px;margin:24px 0 12px;">History</h3>
					<div class="pf-timeline">${historyHtml}</div>

					<div class="pf-order-actions">
						${wa_url && wa_url !== '#' ? `<a href="${wa_url}" target="_blank" class="pf-pub-btn pf-pub-btn--whatsapp">&#128172; Contact via WhatsApp</a>` : ''}
						<button id="pf-portal-refresh" class="pf-pub-btn pf-pub-btn--outline">&#8635; Refresh</button>
						<button id="pf-portal-logout" class="pf-pub-btn pf-pub-btn--outline">&#128682; Log Out</button>
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
			$(document).on('click', '#pf-portal-refresh', () => this.loadOrder());
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
