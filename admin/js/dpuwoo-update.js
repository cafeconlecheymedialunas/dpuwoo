// Módulo de Actualización
(function ($) {
    'use strict';

    window.DPUWOO_Update = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#dpuwoo-update-now').on('click', this.showDirectUpdateModal.bind(this));
            $('#dpuwoo-initialize-baseline').on('click', this.initializeBaseline.bind(this));
            $('#dpuwoo-direct-proceed').on('click', this.confirmDirectUpdate.bind(this));
            $('#dpuwoo-direct-cancel').on('click', this.cancelDirectUpdate.bind(this));
            $('#dpuwoo-cancel-update').on('click', this.cancelUpdate.bind(this));
            $(document).on('click', '#dpuwoo-proceed-update', this.showConfirmUpdateModal.bind(this));
            $(document).on('click', '#dpuwoo-confirm-proceed', this.startUpdateFromSimulation.bind(this));
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

        showConfirmUpdateModal: function(e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').removeClass('hidden');
        },

        startUpdateFromSimulation: function(e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
            this.startDirectUpdate();
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

        cancelUpdate: function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cancelar la actualización?')) {
                DPUWOO_Utils.resetAllSections();
                DPUWOO_Utils.enableButtons();
            }
        },

        initializeBaseline: function(e) {
            e.preventDefault();
            console.log('DPUWoo: Initialize baseline button clicked');
            
            // Show loading state
            const $button = $('#dpuwoo-initialize-baseline');
            const originalText = $button.html();
            $button.prop('disabled', true).html('<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Inicializando...');
            
            console.log('DPUWoo: Sending AJAX request');
            console.log('DPUWoo: AJAX URL:', dpuwoo_ajax.ajax_url);
            console.log('DPUWoo: Nonce:', dpuwoo_ajax.nonce);
            
            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_initialize_baseline',
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    console.log('DPUWoo: AJAX success response:', res);
                    $button.prop('disabled', false).html(originalText);
                    
                    if (res.success) {
                        // Show success message
                        const successHtml = `
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800">Baseline inicializado correctamente</h3>
                                        <div class="mt-2 text-sm text-green-700">
                                            <p>Valor de baseline: <strong>$${res.data.formatted_value}</strong></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#dpuwoo-tab-dashboard').prepend(successHtml);
                        
                        // Reload page after 3 seconds to show updated values
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        // Show error message
                        const errorHtml = `
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">Error al inicializar baseline</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <p>${res.data?.message || 'Error desconocido'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#dpuwoo-tab-dashboard').prepend(errorHtml);
                    }
                },
                error: function (xhr, status, error) {
                    console.log('DPUWoo: AJAX error:', xhr, status, error);
                    console.log('DPUWoo: Response text:', xhr.responseText);
                    $button.prop('disabled', false).html(originalText);
                    
                    const errorHtml = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Error de conexión</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>No se pudo conectar con el servidor</p>
                                        <p>Status: ${status}</p>
                                        <p>Error: ${error}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    $('#dpuwoo-tab-dashboard').prepend(errorHtml);
                }
            });
        }
    };

})(jQuery);