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
            
            // Check if simulation has expired
            if (this.isSimulationExpired()) {
                this.showExpiredNotice();
                return;
            }
            
            $('#dpuwoo-confirm-update-modal').removeClass('hidden');
        },

        startUpdateFromSimulation: function(e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
            this.startDirectUpdate();
        },

        cancelConfirmModal: function(e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
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
                        this.processBatch('dpuwoo_update_batch', 1, totalBatches, 'update', data.run_id, function(finalData) {
                            DPUWOO_Utils.showCompleteResults(finalData, false);
                            if (window.DPUWOO_Logs) DPUWOO_Logs.refresh();
                        }.bind(this));
                    } else {
                        DPUWOO_Utils.showCompleteResults(data, false);
                        if (window.DPUWOO_Logs) DPUWOO_Logs.refresh();
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.handleProcessError('Error comunicándose con el servidor: ' + error, 'update');
                }
            });
        },

        processBatch: function(action, batch, totalBatches, type, runId, onComplete) {
            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    batch: batch,
                    run_id: runId,
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
                        this.processBatch(action, batch + 1, totalBatches, type, runId, onComplete);
                    } else {
                        // Store simulation timestamp when simulation completes
                        if (type === 'simulation') {
                            simulationTimestamp = Date.now();
                            localStorage.setItem('dpuwoo_simulation_timestamp', simulationTimestamp);
                        }
                        onComplete(data);
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.handleProcessError('Error de conexión: ' + error, type);
                }
            });
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
            
            // Remove notice after 5 seconds
            setTimeout(function() {
                $('.dpuwoo-notice--warn').fadeOut(300, function() { $(this).remove(); });
            }, 5000);
            
            // Disable the confirm button
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
