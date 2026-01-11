// Módulo de Actualización
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
        }
    };

})(jQuery);