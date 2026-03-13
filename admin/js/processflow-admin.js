/* global jQuery, processflowAdmin */
/**
 * ProcessFlow Manager – Admin JavaScript
 *
 * Handles AJAX CRUD for orders/stages, drag-drop reordering, QR display,
 * modal dialogs, tab navigation and PDF label generation.
 */
(function ($) {
	'use strict';

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
					<button class="pf-btn pf-btn--primary" onclick="$('#pf-order-form').submit()">${orderId ? 'Update Order' : 'Create Order'}</button>
				</div>
			</div>`;

			this.openModal(html);

			// Populate edit form.
			if (orderId) {
				$.get(this.ajaxUrl, {
					action: 'processflow_get_order_data',
					nonce:  this.nonce,
					order_id: orderId,
				}).done((res) => {
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
			const $table = $('#pf-order-table-wrap');
			if (!$table.length) return;
			const search = $('#pf-search').val() || '';
			const stage  = $('#pf-filter-stage').val() || '';
			const page   = $table.data('page') || 1;
			$table.html('<p style="padding:20px;text-align:center;">' + this.strings.loading + '</p>');
			$.get(window.location.href, { pf_search: search, pf_stage: stage, pf_page: page }, (html) => {
				const $new = $(html).find('#pf-order-table-wrap');
				if ($new.length) $table.replaceWith($new);
			});
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
		// Settings                                                         //
		// -------------------------------------------------------------- //
		bindSettingsForm() {
			$(document).on('submit', '#pf-settings-form', (e) => {
				e.preventDefault();
				const data = {};
				$(e.currentTarget).serializeArray().forEach(({ name, value }) => {
					data[name] = value;
				});
				this.post('processflow_save_settings', data).done((res) => {
					res.success
						? this.notice(res.data.message, 'success', '#pf-settings-notice')
						: this.notice(res.data.message, 'error', '#pf-settings-notice');
				});
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
		// Color pickers                                                    //
		// -------------------------------------------------------------- //
		bindColorPickers() {
			$('.pf-color-picker').wpColorPicker();
		},
	};

	// Expose to inline onclick attributes.
	window.PFAdmin = PF;

	$(document).ready(() => PF.init());
}(jQuery));
