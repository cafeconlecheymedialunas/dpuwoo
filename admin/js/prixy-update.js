// Módulo de Actualización
(function ($) {
    'use strict';

    const SIMULATION_EXPIRY_MINUTES = 30;
    let simulationTimestamp = null;

    window.DPUWOO_Update = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#dpuwoo-initialize-baseline').on('click', this.initializeBaseline.bind(this));
            $('#dpuwoo-cancel-update').on('click', this.cancelUpdate.bind(this));
            $(document).on('click', '#dpuwoo-proceed-update', this.showConfirmUpdateModal.bind(this));
            $(document).on('click', '#dpuwoo-confirm-proceed', this.startUpdateFromSimulation.bind(this));
            $(document).on('click', '#dpuwoo-confirm-cancel', this.cancelConfirmModal.bind(this));
        },

        showConfirmUpdateModal: function(e) {
            e.preventDefault();
            
            if (this.isSimulationExpired()) {
                this.showExpiredNotice();
                return;
            }
            
            $('#dpuwoo-confirm-update-modal').removeClass('hidden');
            $('#dpuwoo-update-progress-inline').addClass('hidden');
        },

        startUpdateFromSimulation: function(e) {
            e.preventDefault();
            
            $('#dpuwoo-update-progress-inline').removeClass('hidden');
            DPUWOO_Utils.btnLoading('#dpuwoo-confirm-proceed', 'Aplicando…');
            $('#dpuwoo-confirm-cancel').prop('disabled', true);
            
            DPUWOO_Globals.cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };
            $('#dpuwoo-update-log').empty();
            
            this.addLogEntry('info', 'Iniciando actualización de precios...');
            this.startUpdateBatch(0, null);
        },

        startUpdateBatch: function(batch, runId) {
            const _this = this;
            
            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_update_batch',
                    batch: batch,
                    run_id: runId || 0,
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) {
                        _this.addLogEntry('error', 'ERROR: ' + (res.data?.message || 'Error desconocido'));
                        _this.showUpdateError(res.data?.message || 'Error desconocido');
                        return;
                    }

                    const data = res.data;
                    const totalBatches = data.batch_info?.total_batches || 1;
                    const currentBatch = batch + 1;
                    
                    // Update product details if available
                    if (data.batch_results && data.batch_results.length > 0) {
                        const firstItem = data.batch_results[0];
                        const lastItem = data.batch_results[data.batch_results.length - 1];
                        
                        $('#dpuwoo-current-product').text(firstItem.product_name || 'Producto #' + firstItem.product_id);
                        
                        if (totalBatches > currentBatch) {
                            $('#dpuwoo-next-product').text('Lote ' + (currentBatch + 1));
                        } else {
                            $('#dpuwoo-next-product').text('—');
                        }
                        
                        // Add log entries for each product in batch
                        data.batch_results.forEach(function(item) {
                            if (item.status === 'updated') {
                                _this.addLogEntry('updated', '✓ ' + (item.product_name || '#' + item.product_id));
                            } else if (item.status === 'error') {
                                _this.addLogEntry('error', '✗ ' + (item.product_name || '#' + item.product_id) + ': ' + (item.error || 'Error'));
                            } else {
                                _this.addLogEntry('skipped', '— ' + (item.product_name || '#' + item.product_id));
                            }
                        });
                    }
                    
                    _this.updateInlineProgress(currentBatch, totalBatches, data);
                    
                    const newRunId = data.run_id || runId;
                    DPUWOO_Utils.updateCumulativeResults(data, 'update');

                    if (currentBatch < totalBatches) {
                        _this.addLogEntry('info', `Lote ${currentBatch} completado, continuando...`);
                        _this.startUpdateBatch(currentBatch, newRunId);
                    } else {
                        _this.addLogEntry('info', '✓ Actualización completada');
                        _this.completeUpdate(data);
                    }
                },
                error: function (xhr, status, error) {
                    _this.addLogEntry('error', 'ERROR de conexión: ' + error);
                    _this.showUpdateError('Error de conexión: ' + error);
                }
            });
        },

        addLogEntry: function(type, message) {
            const now = new Date();
            const time = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const $log = $('#dpuwoo-update-log');
            
            $log.append('<div class="dpuwoo-update-log-entry dpuwoo-update-log-entry--' + type + '"><span class="dpuwoo-update-log-time">' + time + '</span><span>' + message + '</span></div>');
            $log.scrollTop($log[0].scrollHeight);
        },

        updateInlineProgress: function(currentBatch, totalBatches, data) {
            const percent = totalBatches > 0 ? Math.round((currentBatch / totalBatches) * 100) : 100;
            
            $('#dpuwoo-update-progress-fill').css('width', percent + '%');
            $('#dpuwoo-update-progress-percent').text(percent + '%');
            $('#dpuwoo-update-progress-text').text('Lote ' + currentBatch + ' de ' + totalBatches);
            
            const processed = DPUWOO_Globals.cumulativeResults.updated + DPUWOO_Globals.cumulativeResults.skipped + DPUWOO_Globals.cumulativeResults.errors;
            const total = data.batch_info?.total_products || 0;
            
            $('#dpuwoo-update-products-processed').text(processed);
            $('#dpuwoo-update-products-total').text(total);
            $('#dpuwoo-updated-count').text(DPUWOO_Globals.cumulativeResults.updated);
            $('#dpuwoo-skipped-count').text(DPUWOO_Globals.cumulativeResults.skipped);
            $('#dpuwoo-errors-count').text(DPUWOO_Globals.cumulativeResults.errors);
        },

        completeUpdate: function(data) {
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
            $('#dpuwoo-update-progress-inline').addClass('hidden');
            DPUWOO_Utils.btnReset('#dpuwoo-confirm-proceed');
            $('#dpuwoo-confirm-cancel').prop('disabled', false);
            
            // Clear simulation timestamp after successful update
            localStorage.removeItem('dpuwoo_simulation_timestamp');
            
            DPUWOO_Utils.showCompleteResults(data, false);
            if (window.DPUWOO_Logs) DPUWOO_Logs.refresh();
        },

        showUpdateError: function(message) {
            DPUWOO_Utils.btnReset('#dpuwoo-confirm-proceed');
            $('#dpuwoo-confirm-cancel').prop('disabled', false);
            alert('Error: ' + message);
        },

        cancelConfirmModal: function(e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
        },

        isSimulationExpired: function() {
            const stored = localStorage.getItem('dpuwoo_simulation_timestamp');
            if (!stored) return true;
            
            const elapsed = Date.now() - parseInt(stored);
            const expiryMs = SIMULATION_EXPIRY_MINUTES * 60 * 1000;
            
            return elapsed > expiryMs;
        },

        showExpiredNotice: function() {
            const noticeHtml = `
                <div class="dpuwoo-notice dpuwoo-notice--warn" style="margin-bottom: 1rem;">
                    <div class="dpuwoo-notice__icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="dpuwoo-notice__content">
                        <strong>Simulación expirada</strong>
                        <p>Esta simulación tiene más de ${SIMULATION_EXPIRY_MINUTES} minutos. Ejecutá una nueva para ver los resultados actualizados.</p>
                    </div>
                </div>
            `;
            
            $('#dpuwoo-confirm-update-modal').before(noticeHtml);
            
            setTimeout(function() {
                $('.dpuwoo-notice--warn').fadeOut(300, function() { $(this).remove(); });
            }, 5000);
            
            $('#dpuwoo-proceed-update').prop('disabled', true).addClass('disabled');
        },

        cancelUpdate: function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cancelar la actualización?')) {
                DPUWOO_Utils.resetAllSections();
                DPUWOO_Utils.enableButtons();
            }
        },

        initializeBaseline: function(e) {
            e.preventDefault();
            
            const $button = $('#dpuwoo-initialize-baseline');
            const originalText = $button.html();
            $button.prop('disabled', true).html('<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Inicializando...');
            
            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_initialize_baseline',
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    $button.prop('disabled', false).html(originalText);
                    
                    if (res.success) {
                        const successHtml = `
                            <div class="dpuwoo-notice dpuwoo-notice--success" style="margin-bottom: 1rem;">
                                <div class="dpuwoo-notice__icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="dpuwoo-notice__content">
                                    <strong>Baseline inicializado correctamente</strong>
                                    <p>Valor de baseline: <strong>$${res.data.formatted_value}</strong></p>
                                </div>
                            </div>
                        `;
                        $('#dpuwoo-tab-dashboard, .dpuwoo-admin').prepend(successHtml);
                        
                        setTimeout(() => { location.reload(); }, 3000);
                    } else {
                        const errorHtml = `
                            <div class="dpuwoo-notice dpuwoo-notice--error" style="margin-bottom: 1rem;">
                                <div class="dpuwoo-notice__icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="dpuwoo-notice__content">
                                    <strong>Error al inicializar baseline</strong>
                                    <p>${res.data?.message || 'Error desconocido'}</p>
                                </div>
                            </div>
                        `;
                        $('#dpuwoo-tab-dashboard, .dpuwoo-admin').prepend(errorHtml);
                    }
                },
                error: function (xhr, status, error) {
                    $button.prop('disabled', false).html(originalText);
                    const errorHtml = `
                        <div class="dpuwoo-notice dpuwoo-notice--error" style="margin-bottom: 1rem;">
                            <div class="dpuwoo-notice__icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="dpuwoo-notice__content">
                                <strong>Error de conexión</strong>
                                <p>No se pudo conectar con el servidor — ${error}</p>
                            </div>
                        </div>
                    `;
                    $('#dpuwoo-tab-dashboard, .dpuwoo-admin').prepend(errorHtml);
                }
            });
        }
    };

})(jQuery);