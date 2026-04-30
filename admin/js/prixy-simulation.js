// Módulo de Simulación
(function ($) {
    'use strict';

    window.DPUWOO_Simulation = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#dpuwoo-simulate').on('click', this.startSimulation.bind(this));
            $('#dpuwoo-cancel-simulation').on('click', this.cancelSimulation.bind(this));
        },

        startSimulation: function(e) {
            e.preventDefault();
            if (DPUWOO_Globals.isProcessing) return;
            
            DPUWOO_Utils.disableButtons();
            DPUWOO_Utils.resetAllSections();
            DPUWOO_Utils.showSection('dpuwoo-simulation-process');
            
            DPUWOO_Globals.cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };

            $.ajax({
                url: dpuwoo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dpuwoo_simulate_batch',
                    batch: 0,
                    nonce: dpuwoo_ajax.nonce
                },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) {
                        DPUWOO_Utils.handleProcessError(res.data?.message || 'Error desconocido', 'simulation');
                        return;
                    }

                    const data = res.data;
                    const totalBatches = data.batch_info?.total_batches || 1;
                    
                    DPUWOO_Utils.updateProgressBar('simulation', 1, totalBatches, 'Simulando...');
                    
                    if (data.batch_info) {
                        $('#dpuwoo-sim-total-products').text(data.batch_info.total_products);
                    }
                    
                    DPUWOO_Utils.updateCumulativeResults(data, 'simulation');

                    if (totalBatches > 1) {
                        this.processBatch('dpuwoo_simulate_batch', 1, totalBatches, 'simulation', function(finalData) {
                            DPUWOO_Utils.showCompleteResults(finalData, true);
                            localStorage.setItem('dpuwoo_simulation_timestamp', Date.now());
                        }.bind(this));
                    } else {
                        DPUWOO_Utils.showCompleteResults(data, true);
                        localStorage.setItem('dpuwoo_simulation_timestamp', Date.now());
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.handleProcessError('Error comunicándose con el servidor: ' + error, 'simulation');
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
                        // Guardar timestamp cuando la simulación termina
                        if (type === 'simulation') {
                            localStorage.setItem('dpuwoo_simulation_timestamp', Date.now());
                        }
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.handleProcessError('Error de conexión: ' + error, type);
                }
            });
        },

        cancelSimulation: function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cancelar la simulación?')) {
                DPUWOO_Utils.resetAllSections();
                DPUWOO_Utils.enableButtons();
            }
        }
    };

})(jQuery);