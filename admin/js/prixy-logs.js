// Módulo de Logs
(function ($) {
    'use strict';

    window.DPUWOO_Logs = {
        init: function() {
            this.loadLogs();
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.prixy-view-run',    this.viewRunDetails.bind(this));
            $(document).on('click', '.prixy-revert-run',  this.revertRun.bind(this));
            $(document).on('click', '#prixy-close-details-modal, #prixy-close-details-modal-2',
                this.closeDetailsModal.bind(this));
        },

        loadLogs: function() {
            this.showLogsLoading();
            $.ajax({
                url:      prixy_ajax.ajax_url,
                type:     'POST',
                data:     { action: 'prixy_get_runs', nonce: prixy_ajax.nonce },
                dataType: 'json',
                success:  function (res) {
                    res.success ? this.displayLogs(res.data)
                                : this.showLogsError('Error al cargar el historial: ' + (res.data || 'Error desconocido'));
                }.bind(this),
                error: function (xhr, status, error) {
                    this.showLogsError('Error de conexión: ' + error);
                }.bind(this)
            });
        },

        showLogsLoading: function() {
            $('#prixy-log-table').html(`
                <div class="dpu-log-state">
                    <div class="dpu-log-spinner"></div>
                    <p class="dpu-log-state__msg">Cargando historial…</p>
                </div>
            `);
        },

        showLogsError: function(message) {
            $('#prixy-log-table').html(`
                <div class="dpu-log-state">
                    <div class="dpu-log-state__icon dpu-log-state__icon--err">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <p class="dpu-log-state__msg">${message}</p>
                    <button onclick="DPUWOO_Logs.loadLogs()" class="dpu-btn dpu-btn--ghost">Reintentar</button>
                </div>
            `);
        },

        displayLogs: function(logs) {
            if (!logs || logs.length === 0) {
                $('#prixy-log-table').html(`
                    <div class="dpu-log-state">
                        <div class="dpu-log-state__icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="dpu-log-state__msg">Sin registros</p>
                        <p class="dpu-log-state__sub">Los registros aparecerán aquí después de ejecutar actualizaciones reales.</p>
                    </div>
                `);
                return;
            }

            let html = `
                <table class="dpu-log-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Tasa aplicada</th>
                            <th>Variación</th>
                            <th>Productos</th>
                            <th>Tipo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>`;

            logs.forEach(function(log) {
                const date    = new Date(log.date);
                const dateStr = date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
                const timeStr = date.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
                const pct     = parseFloat(log.percentage_change || 0);
                const pctSign = pct > 0 ? '+' : '';
                const pctClass = pct > 0 ? 'dpu-log-pct--up' : pct < 0 ? 'dpu-log-pct--down' : 'dpu-log-pct--neutral';
                const typeKey  = (log.dollar_type || 'oficial').toLowerCase();
                const typeLabel = typeKey === 'oficial' ? 'Oficial' : typeKey.charAt(0).toUpperCase() + typeKey.slice(1);

                html += `
                    <tr class="dpu-log-row">
                        <td class="dpu-log-cell dpu-log-cell--id">#${log.id}</td>
                        <td class="dpu-log-cell dpu-log-cell--date">
                            <span class="dpu-log-date">${dateStr}</span>
                            <span class="dpu-log-time">${timeStr}</span>
                        </td>
                        <td class="dpu-log-cell dpu-log-cell--rate">
                            $${parseFloat(log.dollar_value).toFixed(2)}
                        </td>
                        <td class="dpu-log-cell dpu-log-cell--pct">
                            <span class="dpu-log-pct ${pctClass}">
                                ${pct !== 0 ? pctSign + pct.toFixed(2) + '%' : '—'}
                            </span>
                        </td>
                        <td class="dpu-log-cell dpu-log-cell--products">${log.total_products || 0}</td>
                        <td class="dpu-log-cell">
                            <span class="dpu-log-badge dpu-log-badge--${typeKey}">${typeLabel}</span>
                        </td>
                        <td class="dpu-log-cell dpu-log-cell--actions">
                            <button class="dpu-btn dpu-btn--ghost dpu-btn--xs prixy-view-run" data-run="${log.id}">
                                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Ver
                            </button>
                            <button class="dpu-btn dpu-btn--danger dpu-btn--xs prixy-revert-run" data-run="${log.id}">
                                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                                Revertir
                            </button>
                        </td>
                    </tr>`;
            });

            html += `</tbody></table>`;
            $('#prixy-log-table').html(html);
        },

        viewRunDetails: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const runId = $btn.data('run');

            DPUWOO_Utils.btnLoading($btn, 'Cargando…');
            $('#prixy-run-details-modal').removeClass('hidden');
            $('#prixy-run-details-content').html(`
                <div class="dpu-log-state dpu-log-state--modal">
                    <div class="dpu-log-spinner"></div>
                    <p class="dpu-log-state__msg">Cargando detalles…</p>
                </div>
            `);

            $.ajax({
                url:      prixy_ajax.ajax_url,
                type:     'POST',
                data:     { action: 'prixy_get_run_items', run_id: runId, nonce: prixy_ajax.nonce },
                dataType: 'json',
                success: function (res) {
                    DPUWOO_Utils.btnReset($btn);
                    if (!res.success) {
                        $('#prixy-run-details-content').html(`
                            <div class="dpu-log-state dpu-log-state--modal">
                                <div class="dpu-log-state__icon dpu-log-state__icon--err">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                </div>
                                <p class="dpu-log-state__msg">Error al cargar los detalles</p>
                            </div>
                        `);
                        return;
                    }
                    this.displayRunDetails(res.data, runId);
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.btnReset($btn);
                    $('#prixy-run-details-content').html(`
                        <div class="dpu-log-state dpu-log-state--modal">
                            <p class="dpu-log-state__msg">Error de conexión: ${error}</p>
                        </div>
                    `);
                }
            });
        },

        displayRunDetails: function(items, runId) {
            if (!items || items.length === 0) {
                $('#prixy-run-details-content').html(`
                    <div class="dpu-log-state dpu-log-state--modal">
                        <p class="dpu-log-state__msg">Sin detalles para esta ejecución</p>
                    </div>
                `);
                return;
            }

            // Stats summary
            const updated  = items.filter(i => i.status === 'updated').length;
            const skipped  = items.filter(i => i.status === 'skipped').length;
            const errors   = items.filter(i => i.status === 'error').length;

            let html = `
                <div class="dpu-detail-stats">
                    <div class="dpu-detail-stat">
                        <span class="dpu-detail-stat__num dpu-detail-stat__num--ok">${updated}</span>
                        <span class="dpu-detail-stat__label">Actualizados</span>
                    </div>
                    <div class="dpu-detail-stat">
                        <span class="dpu-detail-stat__num dpu-detail-stat__num--skip">${skipped}</span>
                        <span class="dpu-detail-stat__label">Sin cambios</span>
                    </div>
                    <div class="dpu-detail-stat">
                        <span class="dpu-detail-stat__num ${errors > 0 ? 'dpu-detail-stat__num--err' : 'dpu-detail-stat__num--zero'}">${errors}</span>
                        <span class="dpu-detail-stat__label">Errores</span>
                    </div>
                    <div class="dpu-detail-stat dpu-detail-stat--total">
                        <span class="dpu-detail-stat__num">${items.length}</span>
                        <span class="dpu-detail-stat__label">Total</span>
                    </div>
                </div>
                <div class="dpu-detail-table-wrap">
                    <table class="dpu-detail-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio regular</th>
                                <th>Precio oferta</th>
                                <th>Var. %</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>`;

            items.forEach(function(item) {
                const pct      = parseFloat(item.percentage_change || 0);
                const pctSign  = pct > 0 ? '+' : '';
                const pctClass = pct > 0 ? 'dpu-tbl-pct--up' : pct < 0 ? 'dpu-tbl-pct--down' : '';

                html += `
                    <tr class="dpu-detail-row">
                        <td class="dpu-detail-cell dpu-detail-cell--product">
                            <div class="dpu-tbl-product-name">${item.product_name || 'N/A'}</div>
                            <div class="dpu-tbl-product-meta">ID ${item.product_id}</div>
                        </td>
                        <td class="dpu-detail-cell dpu-tbl-price">
                            ${DPUWOO_Utils.formatPriceChange(item.old_regular_price, item.new_regular_price)}
                        </td>
                        <td class="dpu-detail-cell dpu-tbl-price">
                            ${DPUWOO_Utils.formatPriceChange(item.old_sale_price, item.new_sale_price)}
                        </td>
                        <td class="dpu-detail-cell dpu-tbl-pct ${pctClass}">
                            ${pct !== 0 ? pctSign + pct.toFixed(2) + '%' : '—'}
                        </td>
                        <td class="dpu-detail-cell">
                            <span class="${DPUWOO_Utils.getStatusClass(item.status)}">
                                ${DPUWOO_Utils.getStatusText(item.status)}
                            </span>
                        </td>
                    </tr>`;
            });

            html += `</tbody></table></div>`;
            $('#prixy-run-details-content').html(html);
        },

        revertRun: function(e) {
            e.preventDefault();
            const $btn    = $(e.currentTarget);
            const runId   = $btn.data('run');

            if (!confirm('¿Revertir esta ejecución? Los precios volverán al valor anterior. Esta acción no se puede deshacer.')) return;

            const origHtml = $btn.html();
            $btn.prop('disabled', true).html(`
                <svg class="dpu-btn-spin" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
                Revirtiendo…
            `);

            $.ajax({
                url:      prixy_ajax.ajax_url,
                type:     'POST',
                data:     { action: 'prixy_revert_run', run_id: runId, nonce: prixy_ajax.nonce },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        this.loadLogs();
                        Swal.fire({
                            icon: 'success',
                            title: 'Ejecución revertida',
                            text: 'Los precios han vuelto a su estado anterior.',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        $btn.prop('disabled', false).html(origHtml);
                        Swal.fire({ icon: 'error', title: 'Error', text: res.data || 'No se pudo revertir la ejecución.' });
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    $btn.prop('disabled', false).html(origHtml);
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: error });
                }
            });
        },

        closeDetailsModal: function(e) {
            e.preventDefault();
            $('#prixy-run-details-modal').addClass('hidden');
        },

        refresh: function() {
            this.loadLogs();
        }
    };

})(jQuery);
