(function ($) {
    'use strict';

    window.DPUWOO_Update = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#dpuwoo-update-now').on('click', this.showDirectUpdateModal.bind(this));
            $('#dpuwoo-direct-proceed').on('click', this.confirmDirectUpdate.bind(this));
            $('#dpuwoo-direct-cancel').on('click', this.cancelDirectUpdate.bind(this));
            $('#dpuwoo-cancel-update').on('click', this.cancelUpdate.bind(this));
            $(document).on('click', '.dpu-revert-item', this.revertItem.bind(this));
        },

        showDirectUpdateModal: function(e) {
            e.preventDefault();
            if (DPUWOO_Globals.isProcessing) return;
            $('#dpuwoo-direct-update-modal').removeClass('hidden');
        },

        confirmDirectUpdate: function(e) {
            e.preventDefault();
            $('#dpuwoo-direct-update-modal').addClass('hidden');
            this.startDirectUpdate();
        },

        cancelDirectUpdate: function(e) {
            e.preventDefault();
            $('#dpuwoo-direct-update-modal').addClass('hidden');
        },

        startDirectUpdate: function() {
            DPUWOO_Utils.disableButtons();
            DPUWOO_Utils.resetAllSections();
            DPUWOO_Utils.showSection('dpuwoo-update-process');
            
            DPUWOO_Globals.cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };

            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_update_batch',
                    batch: 0,
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) {
                        DPUWOO_Utils.handleProcessError(res.data?.message || 'Error desconocido', 'update');
                        return;
                    }

                    const data = res.data;
                    const totalBatches = data.batch_info?.total_batches || 1;
                    
                    DPUWOO_Utils.updateProgressBar('update', 1, totalBatches, 'Actualizando...');
                    
                    if (data.batch_info) {
                        $('#dpuwoo-update-total-products').text(data.batch_info.total_products);
                    }
                    
                    DPUWOO_Utils.updateCumulativeResults(data, 'update');

                    if (totalBatches > 1) {
                        this.processBatch('dpuwoo_update_batch', 1, totalBatches, 'update', function(finalData) {
                            this.showFinalResults(finalData, false);
                            DPUWOO_Logs.refresh();
                        }.bind(this));
                    } else {
                        this.showFinalResults(data, false);
                        DPUWOO_Logs.refresh();
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.handleProcessError('Error comunicándose con el servidor: ' + error, 'update');
                }
            });
        },

        processBatch: function(action, batch, totalBatches, type, onComplete) {
            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    batch: batch,
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) {
                        DPUWOO_Utils.handleProcessError(res.data?.message || 'Error desconocido', type);
                        return;
                    }

                    const data = res.data;
                    const actionText = type === 'simulation' ? 'Simulando...' : 'Actualizando...';
                    
                    DPUWOO_Utils.updateProgressBar(type, batch + 1, totalBatches, actionText);
                    
                    if (data.batch_info) {
                        const totalProductsId = type === 'simulation' ? 'dpuwoo-sim-total-products' : 'dpuwoo-update-total-products';
                        $('#' + totalProductsId).text(data.batch_info.total_products);
                    }

                    DPUWOO_Utils.updateCumulativeResults(data, type);

                    if (batch < totalBatches - 1) {
                        this.processBatch(action, batch + 1, totalBatches, type, onComplete);
                    } else {
                        onComplete(data);
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.handleProcessError('Error de conexión: ' + error, type);
                }
            });
        },

        showFinalResults: function(data, isSimulation) {
            DPUWOO_Utils.hideSection('dpuwoo-update-process');
            DPUWOO_Utils.showSection('dpuwoo-final-results');
            
            const title = isSimulation ? 'Simulación Completada' : 'Actualización Completada';
            const bgColor = isSimulation ? 'bg-green-50 border-green-200' : 'bg-blue-50 border-blue-200';
            const icon = isSimulation ? '✅' : '🎉';
            
            const resultsHtml = `
                <div class="bg-white shadow rounded-xl p-6 border ${bgColor}">
                    <div class="flex items-center mb-6">
                        <div class="text-2xl mr-3">${icon}</div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">${title}</h3>
                            <p class="text-gray-600 text-sm">${isSimulation ? 'Resultados de la simulación' : 'Precios actualizados correctamente'}</p>
                        </div>
                    </div>
                    
                    <!-- Resumen estadístico -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="text-center p-3 bg-green-100 rounded-lg">
                            <div class="text-2xl font-bold text-green-700">${DPUWOO_Globals.cumulativeResults.updated}</div>
                            <div class="text-green-600">Actualizados</div>
                        </div>
                        <div class="text-center p-3 bg-gray-100 rounded-lg">
                            <div class="text-2xl font-bold text-gray-700">${DPUWOO_Globals.cumulativeResults.skipped}</div>
                            <div class="text-gray-600">Sin cambios</div>
                        </div>
                        <div class="text-center p-3 bg-red-100 rounded-lg">
                            <div class="text-2xl font-bold text-red-700">${DPUWOO_Globals.cumulativeResults.errors}</div>
                            <div class="text-red-600">Errores</div>
                        </div>
                        <div class="text-center p-3 bg-blue-100 rounded-lg">
                            <div class="text-2xl font-bold text-blue-700">${data.rate ? '$' + data.rate : 'n/a'}</div>
                            <div class="text-blue-600">Dólar aplicado</div>
                        </div>
                    </div>
                    
                    ${data.ratio ? `<p class="text-sm text-gray-600 mb-4"><strong>Ratio de ajuste:</strong> ${data.ratio.toFixed(4)}x</p>` : ''}
                    
                    ${!isSimulation && data.run_id ? 
                        `<p class="text-sm text-gray-600 mb-4"><strong>ID de ejecución:</strong> ${data.run_id}</p>` : 
                        ''
                    }
                    
                    <!-- Tabla de resultados -->
                    <div class="mt-6">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">${isSimulation ? 'Cambios propuestos' : 'Precios actualizados'}</h4>
                        ${DPUWOO_Simulation.createResultsTable(DPUWOO_Globals.cumulativeResults.changes, isSimulation)}
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Volver al Dashboard
                        </button>
                    </div>
                </div>
            `;
            
            $('#dpuwoo-final-results').html(resultsHtml);
            DPUWOO_Utils.enableButtons();
            
            if (!isSimulation) {
                this.showSuccessAlert(DPUWOO_Globals.cumulativeResults.updated, DPUWOO_Globals.cumulativeResults.errors);
            }
        },

        showSuccessAlert: function(updatedCount, errorCount) {
            Swal.fire({
                icon: 'success',
                title: '¡Actualización Completada!',
                html: `
                    <div class="text-left">
                        <p class="mb-2">✅ <strong>${updatedCount} productos</strong> actualizados correctamente</p>
                        ${errorCount > 0 ? 
                            `<p class="mb-2">⚠️ <strong>${errorCount} productos</strong> con errores (revisa la tabla)</p>` : 
                            `<p class="mb-2">🎉 Todos los productos se procesaron sin errores</p>`
                        }
                        <p class="text-sm text-gray-600 mt-3">Los cambios se han aplicado a los precios de WooCommerce.</p>
                    </div>
                `,
                confirmButtonText: 'Ver Resultados',
                confirmButtonColor: '#3B82F6',
                timer: 5000,
                timerProgressBar: true,
                didOpen: function() {
                    setTimeout(function() {
                        $('html, body').animate({
                            scrollTop: $('#dpuwoo-final-results').offset().top - 100
                        }, 1000);
                    }, 500);
                }
            });
        },

        cancelUpdate: function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cancelar la actualización?')) {
                DPUWOO_Utils.resetAllSections();
                DPUWOO_Utils.enableButtons();
            }
        },

        revertItem: function(e) {
            e.preventDefault();
            const logId = $(e.target).data('log');
            if (!confirm('¿Revertir este cambio?')) return;

            const $btn = $(e.target);
            $btn.prop('disabled', true).text('Revirtiendo...');

            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_revert_item',
                    nonce: dpuwoo_ajax.nonce,
                    log_id: logId
                },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) {
                        alert('Error: ' + (res.data || 'unknown'));
                        $btn.prop('disabled', false).text('Revertir');
                        return;
                    }
                    $btn.text('✅ Revertido').prop('disabled', true);
                    $btn.closest('tr').find('.status').html('<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">reverted</span>');
                },
                error: function () {
                    alert('Error comunicándose con el servidor');
                    $btn.prop('disabled', false).text('Revertir');
                }
            });
        }
    };

})(jQuery);