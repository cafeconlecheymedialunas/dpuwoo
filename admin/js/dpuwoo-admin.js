(function ($) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */


		$(document).ready(function(){

			// Pass nonce and ajax_url from wp_localize_script as dpuwoo_ajax.nonce / ajax_url

			function buildTableRows(items) {
				var html = '';
				if (!items || items.length === 0) {
					html += '<tr><td colspan="7">No hay cambios.</td></tr>';
					return html;
				}
				items.forEach(function (item) {
					var pct = item.percentage_change !== null ? parseFloat(item.percentage_change).toFixed(2) + '%' : '-';
					var statusBadge = '<span class="dpu-status-' + item.status + '">' + item.status + '</span>';

					html += '<tr data-log="' + (item.log_id || item.id) + '">';
					html += '<td class="product_name">' + (item.product_name || '') + ' <small>#' + (item.product_id || '') + '</small></td>';
					html += '<td class="category">' + (item.category_name || '') + '</td>';
					html += '<td class="old">$' + (item.old_regular_price || '0') + '</td>';
					html += '<td class="new">$' + (item.new_regular_price || '-') + '</td>';
					html += '<td class="pct">' + pct + '</td>';
					html += '<td class="status">' + statusBadge + '</td>';
					html += '<td class="actions">';
					html += '<button class="button dpu-revert-item" data-log="' + (item.log_id || item.id) + '">Revertir</button>';
					html += '</td>';
					html += '</tr>';
				});
				return html;
			}

			$('#dpuwoo-update-now').on('click', function (e) {
				e.preventDefault();
				var $btn = $(this);
				$btn.prop('disabled', true).text('Actualizando...');
				$.post(dpuwoo_ajax.ajax_url, {
					action: 'dpuwoo_update_now',
					nonce: dpuwoo_ajax.nonce
				}, function (res) {
					if (!res.success) {
						alert('Error: ' + (res.data || 'unknown'));
						$btn.prop('disabled', false).text('Actualizar ahora');
						return;
					}
					var data = res.data;
					// Render header summary
					var header = '<div class="dpu-summary mb-4">';
					header += '<strong>Tipo:</strong> ' + data.dollar_type + ' &nbsp; ';
					header += '<strong>Valor:</strong> $' + data.rate + ' &nbsp; ';
					header += '<strong>Run ID:</strong> ' + data.run_id;
					header += '</div>';
					$('#dpuwoo-sim-results').html(header);

					// Table
					var tableHtml = '<table class="widefat striped"><thead><tr><th>Producto</th><th>Categoria</th><th>Antes</th><th>Ahora</th><th>%</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
					tableHtml += buildTableRows(data.changes);
					tableHtml += '</tbody></table>';
					$('#dpuwoo-sim-results').append(tableHtml);

					$btn.prop('disabled', false).text('Actualizar ahora');
				}, 'json').fail(function () {
					alert('Error comunicándose con el servidor');
					$btn.prop('disabled', false).text('Actualizar ahora');
				});
			});

			// Simulate
			$('#dpuwoo-simulate').on('click', function (e) {
				e.preventDefault();
				var $btn = $(this);
				$btn.prop('disabled', true).text('Simulando...');
				$.post(dpuwoo_ajax.ajax_url, {
					action: 'dpuwoo_simulate',
					nonce: dpuwoo_ajax.nonce
				}, function (res) {
					if (!res.success) {
						alert('Error: ' + (res.data || 'unknown'));
						$btn.prop('disabled', false).text('Simular impacto');
						return;
					}
					var data = res.data;
					var header = '<div class="dpu-summary mb-4">';
					header += '<strong>Tipo:</strong> ' + data.dollar_type + ' &nbsp; ';
					header += '<strong>Valor:</strong> $' + data.rate + ' &nbsp; ';
					header += '<strong>Run ID:</strong> ' + data.run_id + ' (simulado)';
					header += '</div>';
					$('#dpuwoo-sim-results').html(header);

					var tableHtml = '<table class="widefat striped"><thead><tr><th>Producto</th><th>Categoria</th><th>Antes</th><th>Ahora</th><th>%</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
					tableHtml += buildTableRows(data.changes);
					tableHtml += '</tbody></table>';
					$('#dpuwoo-sim-results').append(tableHtml);

					$btn.prop('disabled', false).text('Simular impacto');
				}, 'json').fail(function () {
					alert('Error comunicándose con el servidor');
					$btn.prop('disabled', false).text('Simular impacto');
				});
			});

			// Delegate revert item
			$(document).on('click', '.dpu-revert-item', function (e) {
				e.preventDefault();
				var logId = $(this).data('log');
				if (!confirm('Revertir este cambio?')) return;
				var $btn = $(this);
				$btn.prop('disabled', true).text('Revirtiendo...');
				$.post(dpuwoo_ajax.ajax_url, {
					action: 'dpuwoo_revert_item',
					nonce: dpuwoo_ajax.nonce,
					log_id: logId
				}, function (res) {
					if (!res.success) {
						alert('Error: ' + (res.data || 'unknown'));
						$btn.prop('disabled', false).text('Revertir');
						return;
					}
					$btn.text('Revertido').prop('disabled', true);
					// Optionally update row status
					$btn.closest('tr').find('.status').text('reverted');
				}, 'json').fail(function () {
					alert('Error comunicándose con el servidor');
					$btn.prop('disabled', false).text('Revertir');
				});
			});

			// Revert run (optional): implement button and handler with action dpuwoo_revert_run
		});

	})(jQuery);

