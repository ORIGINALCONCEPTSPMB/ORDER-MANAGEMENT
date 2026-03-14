/* global jQuery, processflowAdmin */
/**
 * ProcessFlow Manager – Admin JavaScript
 *
 * Handles AJAX CRUD for orders/stages, drag-drop reordering, QR display,
 * modal dialogs, tab navigation and PDF label generation.
 */
(function ($) {
	'use strict';

	// Defensive guard: ensure processflowAdmin is always an object.
	// wp_localize_script outputs it just before this file, but if that somehow
	// failed, the inline fallback in frontend-admin.php handles it.  As a final
	// belt-and-braces measure we never let the IIFE crash on a missing global.
	if (typeof processflowAdmin === 'undefined') {
		window.processflowAdmin = {
			ajax_url:                '',
			processflow_ajax_nonce:  '',
			confirm_delete:          'Are you sure you want to delete this item?',
			strings: {
				saving:  'Saving\u2026',
				saved:   'Saved!',
				error:   'An error occurred. Please try again.',
				loading: 'Loading\u2026',
			},
		};
	}

	const PF = {
		nonce:    processflowAdmin.processflow_ajax_nonce,
		ajaxUrl:  processflowAdmin.ajax_url,
		strings:  processflowAdmin.strings,
		confirm:  processflowAdmin.confirm_delete,

		// -------------------------------------------------------------- //
		// Bootstrap                                                        //
		// -------------------------------------------------------------- //
		init() {
			this.bindTabs();
			this.bindOrderForm();
			this.bindOrderActions();
			this.bindStageForm();
			this.bindStageActions();
			this.bindStageDragDrop();
			this.bindQR();
			this.bindSettingsForm();
			this.bindCustomFieldForm();
			this.bindSearch();
			this.bindColorPickers();
			this.bindWhatsAppSend();
		},

		// -------------------------------------------------------------- //
		// Utilities                                                        //
		// -------------------------------------------------------------- //
		notice(msg, type = 'success', container = '#pf-notice-area') {
			const $area = $(container);
			if (!$area.length) return;
			$area.html(
				`<div class="pf-notice pf-notice--${type}">${msg}</div>`
			);
			setTimeout(() => $area.find('.pf-notice').fadeOut(400, function () {
				$(this).remove();
			}), 4000);
		},

		post(action, data = {}) {
			return $.post(this.ajaxUrl, {
				action,
				nonce: this.nonce,
				...data,
			});
		},

		openModal(html) {
			$('#pf-modal-overlay').remove();
			$('body').append(
				`<div id="pf-modal-overlay" class="pf-modal-overlay">${html}</div>`
			);
			$('#pf-modal-overlay').on('click', (e) => {
				if ($(e.target).is('#pf-modal-overlay')) this.closeModal();
			});
		},

		closeModal() {
			$('#pf-modal-overlay').fadeOut(200, function () {
				$(this).remove();
			});
		},

		// -------------------------------------------------------------- //
		// Tabs                                                             //
		// -------------------------------------------------------------- //
		bindTabs() {
			$(document).on('click', '.pf-tab', function (e) {
				e.preventDefault();
				const target = $(this).data('target');
				$('.pf-tab').removeClass('active');
				$('.pf-tab-content').removeClass('active');
				$(this).addClass('active');
				$('#' + target).addClass('active');
			});
		},

		// -------------------------------------------------------------- //
		// Orders                                                           //
		// -------------------------------------------------------------- //
		bindOrderForm() {
			$(document).on('submit', '#pf-order-form', (e) => {
				e.preventDefault();
				const $form   = $(e.currentTarget);
				const orderId = $form.data('order-id');
				const action  = orderId ? 'processflow_update_order' : 'processflow_create_order';
				const data    = {
					customer_name:  $form.find('[name="customer_name"]').val(),
					business_name:  $form.find('[name="business_name"]').val(),
					whatsapp:       $form.find('[name="whatsapp"]').val(),
					job_details:    $form.find('[name="job_details"]').val(),
					current_stage:  $form.find('[name="current_stage"]').val(),
				};
				if (orderId) data.order_id = orderId;

				const $btn = $form.find('[type="submit"]').prop('disabled', true).text(this.strings.saving);

				this.post(action, data).done((res) => {
					if (res.success) {
						this.notice(res.data.message);
						this.closeModal();
						this.reloadOrderTable();
					} else {
						this.notice(res.data.message, 'error');
					}
				}).fail(() => {
					this.notice(this.strings.error, 'error');
				}).always(() => {
					$btn.prop('disabled', false).text(orderId ? 'Update Order' : 'Create Order');
				});
			});
		},

		bindOrderActions() {
			// Open "Add Order" modal.
			$(document).on('click', '#pf-btn-add-order', () => {
				this.openOrderModal();
			});

			// Edit order.
			$(document).on('click', '.pf-edit-order', (e) => {
				const id = $(e.currentTarget).data('id');
				this.openOrderModal(id);
			});

			// Delete order.
			$(document).on('click', '.pf-delete-order', (e) => {
				if (!confirm(this.confirm)) return;
				const id = $(e.currentTarget).data('id');
				this.post('processflow_delete_order', { order_id: id }).done((res) => {
					res.success
						? (this.notice(res.data.message), this.reloadOrderTable())
						: this.notice(res.data.message, 'error');
				});
			});

			// Advance stage.
			$(document).on('click', '.pf-advance-stage', (e) => {
				const id   = $(e.currentTarget).data('id');
				const $btn = $(e.currentTarget).prop('disabled', true);
				this.post('processflow_advance_stage', { order_id: id }).done((res) => {
					if (res.success) {
						this.notice(res.data.message);
						this.reloadOrderTable();
					} else {
						this.notice(res.data.message, 'error');
					}
				}).always(() => $btn.prop('disabled', false));
			});
		},

		openOrderModal(orderId = null) {
			const stages = window.pfStages || [];
			let stageOptions = stages.map(s =>
				`<option value="${s.id}">${s.name}</option>`
			).join('');

			let html = `
			<div class="pf-modal">
				<div class="pf-modal__header">
					<h3 class="pf-modal__title">${orderId ? 'Edit Order' : 'New Order'}</h3>
					<button class="pf-modal__close" onclick="PFAdmin.closeModal()">&times;</button>
				</div>
				<div class="pf-modal__body">
					<div id="pf-modal-notice"></div>
					<form id="pf-order-form" ${orderId ? `data-order-id="${orderId}"` : ''}>
						<div class="pf-form-group">
							<label>Customer Name *</label>
							<input type="text" name="customer_name" required>
						</div>
						<div class="pf-form-group">
							<label>Business Name</label>
							<input type="text" name="business_name">
						</div>
						<div class="pf-form-group">
							<label>WhatsApp Number * <span style="font-weight:normal;color:#666">(e.g. +27821234567)</span></label>
							<input type="text" name="whatsapp" placeholder="+27821234567" required>
						</div>
						<div class="pf-form-group">
							<label>Job Details</label>
							<textarea name="job_details" rows="3"></textarea>
						</div>
						<div class="pf-form-group">
							<label>Stage</label>
							<select name="current_stage">${stageOptions}</select>
						</div>
					</form>
				</div>
				<div class="pf-modal__footer">
					<button class="pf-btn pf-btn--outline" onclick="PFAdmin.closeModal()">Cancel</button>
					<button class="pf-btn pf-btn--primary" onclick="jQuery('#pf-order-form').trigger('submit')">${orderId ? 'Update Order' : 'Create Order'}</button>
				</div>
			</div>`;

			this.openModal(html);

			// Populate edit form.
			if (orderId) {
				this.post('processflow_get_order_data', { order_id: orderId }).done((res) => {
					if (res.success) {
						const o = res.data;
						$('#pf-order-form [name="customer_name"]').val(o.customer_name);
						$('#pf-order-form [name="business_name"]').val(o.business_name);
						$('#pf-order-form [name="whatsapp"]').val(o.whatsapp);
						$('#pf-order-form [name="job_details"]').val(o.job_details);
						$('#pf-order-form [name="current_stage"]').val(o.current_stage);
					}
				});
			}
		},

		reloadOrderTable() {
			const $wrap = $('#pf-order-table-wrap');
			if (!$wrap.length) return;
			const search   = $('#pf-search').val() || '';
			const stage    = $('#pf-filter-stage').val() || '';
			const page     = parseInt($wrap.data('page'), 10) || 1;
			const $tbody   = $wrap.find('tbody');
			const $counter = $wrap.find('.pf-orders-counter');

			$tbody.html(`<tr><td colspan="8" style="text-align:center;padding:30px;">${this.strings.loading}</td></tr>`);

			this.post('processflow_get_orders_json', { search, stage, page, per_page: 20 }).done((res) => {
				if (!res.success) { return; }
				const orders = res.data.items;
				if ($counter.length) $counter.text(res.data.total + ' orders total');

				if (!orders.length) {
					$tbody.html('<tr><td colspan="8" style="text-align:center;padding:30px;color:#787c82;">No orders found.</td></tr>');
					return;
				}

				const rows = orders.map(o => {
					const stageBadge = o.stage_name
						? `<span class="pf-badge" style="background:${o.stage_color}">${this.esc(o.stage_name)}</span>`
						: '<span class="pf-badge" style="background:#aaa">N/A</span>';
					const waSentBadge = o.whatsapp_sent_stage
						? `<span class="pf-badge" style="background:${this.esc(o.whatsapp_sent_color)}">${this.esc(o.whatsapp_sent_stage)}</span>`
						: '<span style="color:#aaa;">&mdash;</span>';
					const waNum = o.whatsapp ? o.whatsapp.replace(/^\+/, '') : '';
					const waLink = waNum
						? `<a href="https://wa.me/${waNum}" target="_blank" rel="noopener noreferrer">${this.esc(o.whatsapp)}</a>`
						: '';
					return `<tr id="pf-order-row-${o.id}">
						<td>#${o.id}</td>
						<td>${this.esc(o.customer_name)}</td>
						<td>${this.esc(o.business_name)}</td>
						<td>${waLink}</td>
						<td>${stageBadge}</td>
						<td>${waSentBadge}</td>
						<td>${this.esc(o.created_at)}</td>
						<td>
							<div style="display:flex;gap:5px;flex-wrap:wrap;">
								<button class="pf-btn pf-btn--outline pf-btn--sm pf-edit-order" data-id="${o.id}" title="Edit">✏</button>
								<button class="pf-btn pf-btn--outline pf-btn--sm pf-show-qr" data-id="${o.id}" title="QR Code">⊙</button>
								<button class="pf-btn pf-btn--success pf-btn--sm pf-advance-stage" data-id="${o.id}" title="Advance Stage">▶</button>
								<button class="pf-btn pf-btn--sm pf-send-whatsapp" data-id="${o.id}" title="Send WhatsApp" style="background:#25d366;color:#fff;border-color:#25d366;">&#128172;</button>
								<button class="pf-btn pf-btn--danger pf-btn--sm pf-delete-order" data-id="${o.id}" title="Delete">✕</button>
							</div>
						</td>
					</tr>`;
				});
				$tbody.html(rows.join(''));
			}).fail(() => {
				$tbody.html('<tr><td colspan="8" style="text-align:center;padding:20px;color:#e74c3c;">Failed to load orders.</td></tr>');
			});
		},

		/** HTML-escape a string for safe insertion. */
		esc(str) {
			return String(str || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		},

		// -------------------------------------------------------------- //
		// Stages                                                           //
		// -------------------------------------------------------------- //
		bindStageForm() {
			$(document).on('submit', '#pf-stage-form', (e) => {
				e.preventDefault();
				const $form  = $(e.currentTarget);
				const stageId = $form.data('stage-id');
				const action  = stageId ? 'processflow_update_stage' : 'processflow_create_stage';
				const data    = {
					name:               $form.find('[name="stage_name"]').val(),
					color:              $form.find('[name="stage_color"]').val(),
					whatsapp_template:  $form.find('[name="whatsapp_template"]').val(),
				};
				if (stageId) data.stage_id = stageId;

				this.post(action, data).done((res) => {
					if (res.success) {
						this.notice(res.data.message);
						location.reload();
					} else {
						this.notice(res.data.message, 'error', '#pf-stage-notice');
					}
				});
			});
		},

		bindStageActions() {
			$(document).on('click', '.pf-edit-stage', (e) => {
				const $item = $(e.currentTarget).closest('.pf-stage-item');
				$('#pf-stage-form').data('stage-id', $item.data('id'));
				$('#pf-stage-form [name="stage_name"]').val($item.data('name'));
				$('#pf-stage-form [name="stage_color"]').val($item.data('color')).trigger('change');
				$('#pf-stage-form [name="whatsapp_template"]').val($item.data('template'));
				$('#pf-stage-form [type="submit"]').text('Update Stage');
				$('#pf-stage-form-heading').text('Edit Stage');
				$('html,body').animate({ scrollTop: $('#pf-stage-form').offset().top - 50 }, 300);
			});

			$(document).on('click', '.pf-delete-stage', (e) => {
				if (!confirm(this.confirm)) return;
				const id = $(e.currentTarget).closest('.pf-stage-item').data('id');
				this.post('processflow_delete_stage', { stage_id: id }).done((res) => {
					res.success ? location.reload() : this.notice(res.data.message, 'error');
				});
			});

			// Reset form.
			$(document).on('click', '#pf-stage-form-reset', () => {
				$('#pf-stage-form').removeData('stage-id').trigger('reset');
				$('#pf-stage-form [type="submit"]').text('Add Stage');
				$('#pf-stage-form-heading').text('Add New Stage');
			});
		},

		bindStageDragDrop() {
			// Guard: $.fn.sortable requires jQuery UI – not always loaded on frontend.
			if (typeof $.fn.sortable !== 'function') { return; }
			$('.pf-stage-list').sortable({
				handle: '.pf-stage-item__handle',
				axis:   'y',
				update: () => {
					const order = $('.pf-stage-list .pf-stage-item').map(function () {
						return $(this).data('id');
					}).get();
					PF.post('processflow_reorder_stages', { order }).done((res) => {
						res.success && PF.notice(res.data.message);
					});
				},
			});
		},

		// -------------------------------------------------------------- //
		// QR Codes                                                         //
		// -------------------------------------------------------------- //
		bindQR() {
			$(document).on('click', '.pf-show-qr', (e) => {
				const id = $(e.currentTarget).data('id');
				this.post('processflow_get_qr', { order_id: id }).done((res) => {
					if (res.success) {
						this.openModal(`
						<div class="pf-modal">
							<div class="pf-modal__header">
								<h3 class="pf-modal__title">QR Code – Order #${id}</h3>
								<button class="pf-modal__close" onclick="PFAdmin.closeModal()">&times;</button>
							</div>
							<div class="pf-modal__body" style="text-align:center;">
								<img src="${res.data.qr_url}" alt="QR Code" style="max-width:300px;width:100%;">
								<p><a href="${res.data.qr_url}" download="qr-order-${id}.png" class="pf-btn pf-btn--outline pf-btn--sm" style="margin-top:10px;">&#8595; Download PNG</a></p>
							</div>
						</div>`);
					} else {
						this.notice(res.data.message, 'error');
					}
				});
			});

			// Print PDF labels.
			$(document).on('click', '#pf-btn-print-labels', () => {
				const ids = $('.pf-qr-checkbox:checked').map(function () {
					return $(this).val();
				}).get();
				if (!ids.length) {
					this.notice('Please select at least one order.', 'info');
					return;
				}
				this.post('processflow_get_labels_html', { order_ids: ids }).done((res) => {
					if (res.success) {
						const win = window.open('', '_blank');
						win.document.write(res.data.html);
						win.document.close();
					}
				});
			});

			// Select-all checkbox for QR page.
			$(document).on('change', '#pf-qr-select-all', function () {
				$('.pf-qr-checkbox').prop('checked', $(this).is(':checked'));
			});
		},

		// -------------------------------------------------------------- //
		// Settings – wire ALL #pf-settings-form instances                //
		// -------------------------------------------------------------- //
		bindSettingsForm() {
			$(document).on('submit', '.pf-settings-form', (e) => {
				e.preventDefault();
				const $form   = $(e.currentTarget);
				const notice  = $form.data('notice') || '#pf-settings-notice';
				const data    = {};
				$form.serializeArray().forEach(({ name, value }) => { data[name] = value; });
				const $btn = $form.find('[type="submit"]').prop('disabled', true);
				this.post('processflow_save_settings', data).done((res) => {
					res.success
						? this.notice(res.data.message, 'success', notice)
						: this.notice(res.data.message, 'error', notice);
				}).always(() => $btn.prop('disabled', false));
			});
		},

		// -------------------------------------------------------------- //
		// Custom Fields                                                    //
		// -------------------------------------------------------------- //
		bindCustomFieldForm() {
			$(document).on('submit', '#pf-custom-field-form', (e) => {
				e.preventDefault();
				const data = {
					field_label: $('[name="field_label"]').val(),
					field_type:  $('[name="field_type"]').val(),
					is_required: $('[name="is_required"]').is(':checked') ? 1 : 0,
				};
				this.post('processflow_create_custom_field', data).done((res) => {
					res.success ? location.reload() : this.notice(res.data.message, 'error');
				});
			});

			$(document).on('click', '.pf-delete-custom-field', (e) => {
				if (!confirm(this.confirm)) return;
				const id = $(e.currentTarget).data('id');
				this.post('processflow_delete_custom_field', { field_id: id }).done((res) => {
					res.success ? location.reload() : this.notice(res.data.message, 'error');
				});
			});
		},

		// -------------------------------------------------------------- //
		// Search / Filter                                                  //
		// -------------------------------------------------------------- //
		bindSearch() {
			let timer;
			$(document).on('input', '#pf-search', () => {
				clearTimeout(timer);
				timer = setTimeout(() => this.reloadOrderTable(), 400);
			});
			$(document).on('change', '#pf-filter-stage', () => this.reloadOrderTable());
		},

		// -------------------------------------------------------------- //
		// WhatsApp send notification                                       //
		// -------------------------------------------------------------- //
		bindWhatsAppSend() {
			$(document).on('click', '.pf-send-whatsapp', (e) => {
				const $btn = $(e.currentTarget).prop('disabled', true);
				const id   = $btn.data('id');
				this.post('processflow_send_whatsapp_notification', { order_id: id }).done((res) => {
					if (res.success) {
						// Open the WhatsApp deep-link in a new tab.
						if (res.data.wa_url && res.data.wa_url !== '#') {
							window.open(res.data.wa_url, '_blank', 'noopener,noreferrer');
						}
						this.notice(res.data.message);
						this.reloadOrderTable();
					} else {
						this.notice(res.data.message, 'error');
					}
				}).fail(() => {
					this.notice(this.strings.error, 'error');
				}).always(() => {
					$btn.prop('disabled', false);
				});
			});
		},

		// -------------------------------------------------------------- //
		// Color pickers                                                    //
		// -------------------------------------------------------------- //
		bindColorPickers() {
			// wpColorPicker is an admin script; it may not be loaded on frontend pages.
			if (typeof $.fn.wpColorPicker === 'function') {
				$('.pf-color-picker').wpColorPicker();
			} else {
				// Fallback: convert to native <input type="color"> so color selection still works.
				$('.pf-color-picker').each(function () {
					$(this).attr('type', 'color').css({ height: '36px', padding: '2px 4px', width: '60px', cursor: 'pointer' });
				});
			}
		},
	};

	// Expose to inline onclick attributes.
	window.PFAdmin = PF;

	$(document).ready(() => PF.init());
}(jQuery));

// ================================================================
// FRONTEND ADMIN EXTENSIONS
// (Users, CSV Import, Invoice Ninja, frontend tab switching)
// ================================================================
(function ($) {
'use strict';

const PFExt = {

init() {
this.bindFrontendTabs();
this.bindUserActions();
this.bindCsvImport();
this.bindInvoiceNinja();
this.bindDashboardButtons();
},

post(action, data) {
// Always use processflowAdmin (set by wp_localize_script or the inline fallback);
// never rely on the WP-admin-only `ajaxurl` global.
const cfg = window.processflowAdmin || {};
return $.post(cfg.ajax_url || '', {
action,
nonce: cfg.processflow_ajax_nonce || '',
...data,
});
},

notice(msg, type, container) {
container = container || '#pf-notice-area';
const $area = $(container);
if (!$area.length) return;
$area.html(`<div class="pf-notice pf-notice--${type || 'success'}">${msg}</div>`);
setTimeout(() => $area.find('.pf-notice').fadeOut(400, function () { $(this).remove(); }), 5000);
},

// ----------------------------------------------------------
// Frontend tabs
// ----------------------------------------------------------
bindFrontendTabs() {
$(document).on('click', '.pf-fnav__tab', function () {
const tab = $(this).data('tab');
$('.pf-fnav__tab').removeClass('active');
$(this).addClass('active');
$('.pf-ftab').removeClass('active');
$('#pf-tab-' + tab).addClass('active');
});

// Switch tab buttons inside content (e.g. Dashboard "Add Order")
$(document).on('click', '.pf-switch-tab', function (e) {
e.preventDefault();
const tab = $(this).data('tab');
$('.pf-fnav__tab').removeClass('active');
$('.pf-fnav__tab[data-tab="' + tab + '"]').addClass('active');
$('.pf-ftab').removeClass('active');
$('#pf-tab-' + tab).addClass('active');
// Also trigger add-order modal if arriving at orders from dashboard button
if (tab === 'orders' && $(this).is('#pf-dash-btn-add-order')) {
$('#pf-btn-add-order').trigger('click');
}
});
},

// ----------------------------------------------------------
// Dashboard action buttons
// ----------------------------------------------------------
bindDashboardButtons() {
// Dashboard "Add Order" button
$(document).on('click', '#pf-dash-btn-add-order', function () {
$('.pf-fnav__tab').removeClass('active');
$('.pf-fnav__tab[data-tab="orders"]').addClass('active');
$('.pf-ftab').removeClass('active');
$('#pf-tab-orders').addClass('active');
setTimeout(() => $('#pf-btn-add-order').trigger('click'), 100);
});
},

// ----------------------------------------------------------
// Users management
// ----------------------------------------------------------
bindUserActions() {
// Add user button
$(document).on('click', '#pf-btn-add-user', () => {
this.openUserModal(null);
});

// Edit user
$(document).on('click', '.pf-edit-user', (e) => {
const $btn = $(e.currentTarget);
this.openUserModal({
id:        $btn.data('id'),
username:  $btn.data('username'),
email:     $btn.data('email'),
role:      $btn.data('role'),
is_active: $btn.data('is-active'),
});
});

// Delete user
$(document).on('click', '.pf-delete-user', (e) => {
if (!confirm(window.processflowAdmin ? processflowAdmin.confirm_delete : 'Delete this user?')) return;
const id = $(e.currentTarget).data('id');
this.post('processflow_delete_pf_user', { user_id: id }).done((res) => {
if (res.success) {
$('#pf-user-row-' + id).remove();
this.notice(res.data.message, 'success');
} else {
this.notice(res.data.message, 'error');
}
});
});

// Submit user form
$(document).on('submit', '#pf-user-form', (e) => {
e.preventDefault();
const $form  = $(e.currentTarget);
const userId = $form.data('user-id');
const action = userId ? 'processflow_update_pf_user' : 'processflow_create_pf_user';
const data   = {
username:  $form.find('[name="username"]').val(),
email:     $form.find('[name="email"]').val(),
password:  $form.find('[name="password"]').val(),
role:      $form.find('[name="role"]').val(),
is_active: $form.find('[name="is_active"]').is(':checked') ? 1 : 0,
};
if (userId) data.user_id = userId;

const $btn = $form.find('[type="submit"]').prop('disabled', true);
this.post(action, data).done((res) => {
if (res.success) {
this.notice(res.data.message, 'success');
if (window.PFAdmin) PFAdmin.closeModal();
location.reload();
} else {
this.notice(res.data.message, 'error', '#pf-user-modal-notice');
}
}).always(() => $btn.prop('disabled', false));
});
},

openUserModal(user) {
const isEdit = !!user;
const html = `
<div class="pf-modal">
<div class="pf-modal__header">
<h3 class="pf-modal__title">${isEdit ? 'Edit User' : 'Add User'}</h3>
<button class="pf-modal__close" onclick="PFAdmin.closeModal()">&times;</button>
</div>
<div class="pf-modal__body">
<div id="pf-user-modal-notice"></div>
<form id="pf-user-form" ${isEdit ? `data-user-id="${user.id}"` : ''}>
<div class="pf-form-group">
<label>Username *</label>
<input type="text" name="username" value="${isEdit ? user.username : ''}" required>
</div>
<div class="pf-form-group">
<label>Email</label>
<input type="email" name="email" value="${isEdit ? user.email : ''}">
</div>
<div class="pf-form-group">
<label>Password ${isEdit ? '(leave blank to keep current)' : '*'}</label>
<input type="password" name="password" ${!isEdit ? 'required' : ''} autocomplete="new-password">
</div>
<div class="pf-form-group">
<label>Role</label>
<select name="role">
<option value="operator" ${isEdit && user.role === 'operator' ? 'selected' : ''}>Operator</option>
<option value="admin" ${isEdit && user.role === 'admin' ? 'selected' : ''}>Admin</option>
</select>
</div>
<div class="pf-form-group">
<label>
<input type="checkbox" name="is_active" value="1" ${!isEdit || user.is_active ? 'checked' : ''}>
Active
</label>
</div>
</form>
</div>
<div class="pf-modal__footer">
<button class="pf-btn pf-btn--outline" onclick="PFAdmin.closeModal()">Cancel</button>
<button class="pf-btn pf-btn--primary" onclick="jQuery('#pf-user-form').trigger('submit')">${isEdit ? 'Update User' : 'Create User'}</button>
</div>
</div>`;

if (window.PFAdmin) PFAdmin.openModal(html);
},

// ----------------------------------------------------------
// CSV Import
// ----------------------------------------------------------
bindCsvImport() {
// File name display
$(document).on('change', '#pf-csv-file', function () {
const name = this.files[0] ? this.files[0].name : 'No file selected';
$('#pf-file-name').text(name);
});

// Drag-over styling
$(document).on('dragover', '#pf-file-drop', function (e) {
e.preventDefault();
$(this).addClass('drag-over');
});
$(document).on('dragleave drop', '#pf-file-drop', function () {
$(this).removeClass('drag-over');
});

// Import form submit
$(document).on('submit', '#pf-import-csv-form', (e) => {
e.preventDefault();
const $form   = $(e.currentTarget);
const fileInput = document.getElementById('pf-csv-file');
if (!fileInput || !fileInput.files[0]) {
this.notice('Please select a CSV file.', 'error', '#pf-import-notice');
return;
}

const formData = new FormData();
formData.append('action', 'processflow_import_csv');
formData.append('nonce', processflowAdmin.processflow_ajax_nonce);
formData.append('csv_file', fileInput.files[0]);

const $btn = $('#pf-import-submit').prop('disabled', true).text('Importing…');
$('#pf-import-result').hide();

$.ajax({
url:         processflowAdmin.ajax_url,
type:        'POST',
data:        formData,
processData: false,
contentType: false,
}).done((res) => {
if (res.success) {
$('#pf-import-result').show().html(
`<div class="pf-notice pf-notice--success">${res.data.message}</div>`
);
$form[0].reset();
$('#pf-file-name').text('No file selected');
} else {
$('#pf-import-result').show().html(
`<div class="pf-notice pf-notice--error">${res.data.message}</div>`
);
}
}).fail(() => {
$('#pf-import-result').show().html(
'<div class="pf-notice pf-notice--error">Upload failed. Please try again.</div>'
);
}).always(() => {
$btn.prop('disabled', false).text('⬆ Import Orders');
});
});

// Download CSV template
$(document).on('click', '#pf-btn-csv-template', () => {
const url = processflowAdmin.ajax_url +
'?action=processflow_csv_template' +
'&nonce=' + processflowAdmin.processflow_ajax_nonce;
const a = document.createElement('a');
a.href = url;
a.download = 'processflow-import-template.csv';
document.body.appendChild(a);
a.click();
document.body.removeChild(a);
});
},

// ----------------------------------------------------------
// Invoice Ninja Sync
// ----------------------------------------------------------
bindInvoiceNinja() {
$(document).on('click', '#pf-btn-sync-ninja', (e) => {
e.preventDefault();
const $btn = $(e.currentTarget).prop('disabled', true).text('Syncing…');
$('#pf-in-sync-result').hide();
this.post('processflow_sync_invoice_ninja', {}).done((res) => {
if (res.success) {
$('#pf-in-sync-result').show().html(
`<div class="pf-notice pf-notice--success">${res.data.message}</div>`
);
} else {
$('#pf-in-sync-result').show().html(
`<div class="pf-notice pf-notice--error">${res.data.message}</div>`
);
}
}).fail(() => {
$('#pf-in-sync-result').show().html(
'<div class="pf-notice pf-notice--error">Sync failed. Check your connection settings.</div>'
);
}).always(() => {
$btn.prop('disabled', false).text('↺ Sync Now');
});
});
},
};

$(document).ready(() => PFExt.init());
}(jQuery));
