(function ($) {
    'use strict';

    window.DPUWOO_Logs = {
        init: function() {
            this.loadLogs();
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.dpuwoo-view-run', this.viewRunDetails.bind(this));
            $(document).on('click', '.dpuwoo-revert-run', this.revertRun.bind(this));
            $('#dpuwoo-close-details-modal').on('click', this.closeDetailsModal.bind(this));
        },

        loadLogs: function() {
            console.log('DPUWOO: Loading logs...');
            this.showLogsLoading();
            
            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_get_runs',
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    console.log('DPUWOO: Logs loaded successfully', res);
                    if (res.success) {
                        this.displayLogs(res.data);
                    } else {
                        this.showLogsError('Error al cargar los logs: ' + (res.data || 'Error desconocido'));
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    console.error('DPUWOO: Error loading logs', xhr, status, error);
                    this.showLogsError('Error de conexión: ' + error);
                }.bind(this)
            });
        },

        showLogsLoading: function() {
            $('#dpuwoo-log-table').html(`
                <div class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    <span class="ml-3 text-gray-600">Cargando historial...</span>
                </div>
            `);
        },

        showLogsError: function(message) {
            $('#dpuwoo-log-table').html(`
                <div class="text-center py-8">
                    <div class="text-red-500 text-lg mb-2">❌</div>
                    <p class="text-red-600 mb-4">${message}</p>
                    <button onclick="DPUWOO_Logs.loadLogs()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        Reintentar
                    </button>
                </div>
            `);
        },

        displayLogs: function(logs) {
            if (!logs || logs.length === 0) {
                $('#dpuwoo-log-table').html(`
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-lg mb-2">📊</div>
                        <p class="text-gray-500">No hay registros en el historial</p>
                        <p class="text-sm text-gray-400 mt-1">Los registros aparecerán aquí después de ejecutar actualizaciones</p>
                    </div>
                `);
                return;
            }

            let tableHtml = `
                <table class="w-full text-left border-collapse border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3 border border-gray-200">ID</th>
                            <th class="p-3 border border-gray-200">Fecha</th>
                            <th class="p-3 border border-gray-200">Valor Dólar</th>
                            <th class="p-3 border border-gray-200">Tipo</th>
                            <th class="p-3 border border-gray-200">Productos</th>
                            <th class="p-3 border border-gray-200">Usuario</th>
                            <th class="p-3 border border-gray-200">Nota</th>
                            <th class="p-3 border border-gray-200">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            logs.forEach(function(log) {
                const date = new Date(log.date);
                const formattedDate = date.toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const dollarType = log.dollar_type || 'official';
                const dollarTypeText = dollarType === 'official' ? 'Oficial' : 'Blue';

                tableHtml += `
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="p-3 border border-gray-200 font-mono text-sm">#${log.id}</td>
                        <td class="p-3 border border-gray-200">${formattedDate}</td>
                        <td class="p-3 border border-gray-200 font-mono">$${parseFloat(log.dollar_value).toFixed(2)}</td>
                        <td class="p-3 border border-gray-200">
                            <span class="px-2 py-1 rounded text-xs ${dollarType === 'official' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
                                ${dollarTypeText}
                            </span>
                        </td>
                        <td class="p-3 border border-gray-200">${log.total_products || 0}</td>
                        <td class="p-3 border border-gray-200">${log.user_id || 'Sistema'}</td>
                        <td class="p-3 border border-gray-200 text-sm">${log.note || '-'}</td>
                        <td class="p-3 border border-gray-200">
                            <button class="dpuwoo-view-run px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition mr-2" 
                                    data-run="${log.id}">
                                Ver Detalles
                            </button>
                            <button class="dpuwoo-revert-run px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition" 
                                    data-run="${log.id}">
                                Revertir
                            </button>
                        </td>
                    </tr>
                `;
            });

            tableHtml += '</tbody></table>';
            $('#dpuwoo-log-table').html(tableHtml);
        },

        viewRunDetails: function(e) {
            e.preventDefault();
            const runId = $(e.target).data('run');
            
            $('#dpuwoo-run-details-modal').removeClass('hidden');
            $('#dpuwoo-run-details-content').html(`
                <div class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span class="ml-3 text-gray-600">Cargando detalles...</span>
                </div>
            `);

            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_get_run_items',
                    run_id: runId,
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) {
                        $('#dpuwoo-run-details-content').html(`
                            <div class="text-center py-8 text-red-600">
                                Error al cargar los detalles: ${res.data || 'Error desconocido'}
                            </div>
                        `);
                        return;
                    }
                    this.displayRunDetails(res.data, runId);
                }.bind(this),
                error: function (xhr, status, error) {
                    $('#dpuwoo-run-details-content').html(`
                        <div class="text-center py-8 text-red-600">
                            Error de conexión: ${error}
                        </div>
                    `);
                }
            });
        },

        displayRunDetails: function(items, runId) {
            if (!items || items.length === 0) {
                $('#dpuwoo-run-details-content').html(`
                    <div class="text-center py-8 text-gray-500">
                        No hay detalles disponibles para esta ejecución
                    </div>
                `);
                return;
            }

            let detailsHtml = `
                <div class="mb-4">
                    <h3 class="text-lg font-semibold">Detalles de la Ejecución #${runId}</h3>
                    <p class="text-sm text-gray-600">${items.length} productos procesados</p>
                </div>
                <div class="overflow-y-auto max-h-96">
                    <table class="w-full text-left border-collapse border border-gray-200 text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="p-2 border border-gray-200">Producto</th>
                                <th class="p-2 border border-gray-200">SKU</th>
                                <th class="p-2 border border-gray-200">Precio Regular Anterior</th>
                                <th class="p-2 border border-gray-200">Precio Regular Nuevo</th>
                                <th class="p-2 border border-gray-200">% Cambio</th>
                                <th class="p-2 border border-gray-200">Estado</th>
                                <th class="p-2 border border-gray-200">Razón</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            items.forEach(function(item) {
                const statusClass = DPUWOO_Utils.getStatusClass(item.status);
                const statusText = DPUWOO_Utils.getStatusText(item.status);
                const percentageChange = item.percentage_change ? 
                    parseFloat(item.percentage_change).toFixed(2) + '%' : '-';
                
                detailsHtml += `
                    <tr class="border-b border-gray-200">
                        <td class="p-2 border border-gray-200">
                            <div class="font-medium">${item.product_name || 'N/A'}</div>
                            <div class="text-xs text-gray-500">ID: ${item.product_id}</div>
                        </td>
                        <td class="p-2 border border-gray-200 font-mono text-xs">${item.product_sku || 'N/A'}</td>
                        <td class="p-2 border border-gray-200 font-mono">${DPUWOO_Utils.formatPrice(item.old_regular_price)}</td>
                        <td class="p-2 border border-gray-200 font-mono">${DPUWOO_Utils.formatPrice(item.new_regular_price)}</td>
                        <td class="p-2 border border-gray-200 font-mono ${item.percentage_change > 0 ? 'text-red-600' : 'text-green-600'}">
                            ${percentageChange}
                        </td>
                        <td class="p-2 border border-gray-200">
                            <span class="px-2 py-1 rounded-full text-xs ${statusClass}">${statusText}</span>
                        </td>
                        <td class="p-2 border border-gray-200 text-xs">${item.reason || '-'}</td>
                    </tr>
                `;
            });

            detailsHtml += '</tbody></table></div>';
            $('#dpuwoo-run-details-content').html(detailsHtml);
        },

        revertRun: function(e) {
            e.preventDefault();
            const runId = $(e.target).data('run');
            
            if (!confirm('¿Estás seguro de que deseas revertir esta ejecución completa? Esta acción no se puede deshacer.')) {
                return;
            }

            const $btn = $(e.target);
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Revirtiendo...');

            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_revert_run',
                    run_id: runId,
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $btn.text('✅ Revertido').prop('disabled', true);
                        this.loadLogs();
                        Swal.fire({
                            icon: 'success',
                            title: 'Ejecución revertida',
                            text: 'La ejecución ha sido revertida correctamente',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Error al revertir: ' + (res.data || 'Error desconocido'));
                        $btn.prop('disabled', false).text(originalText);
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    alert('Error de conexión: ' + error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        closeDetailsModal: function(e) {
            e.preventDefault();
            $('#dpuwoo-run-details-modal').addClass('hidden');
        },

        refresh: function() {
            this.loadLogs();
        }
    };

})(jQuery);