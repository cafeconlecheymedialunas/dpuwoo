(function ($) {
    'use strict';

    // Variables globales para el proceso
    let isProcessing = false;
    let currentProcessType = null;
    let cumulativeResults = {
        updated: 0,
        skipped: 0,
        errors: 0,
        changes: []
    };

    $(document).ready(function(){
        
        console.log('DPUWOO: Admin JS loaded');

        // Ocultar todas las secciones de proceso al cargar
        $('#dpuwoo-simulation-process').addClass('hidden');
        $('#dpuwoo-simulation-results').addClass('hidden');
        $('#dpuwoo-update-process').addClass('hidden');
        $('#dpuwoo-final-results').addClass('hidden');

        // Inicializar módulos
        if (window.DPUWOO_Tabs) DPUWOO_Tabs.init();
        if (window.DPUWOO_Logs) DPUWOO_Logs.init();
        if (window.DPUWOO_Simulation) DPUWOO_Simulation.init();
        if (window.DPUWOO_Update) DPUWOO_Update.init();

        // Funciones de utilidad compartidas
        window.DPUWOO_Utils = {
            showSection: function(sectionId) {
                $('#' + sectionId).removeClass('hidden').addClass('block');
            },

            hideSection: function(sectionId) {
                $('#' + sectionId).removeClass('block').addClass('hidden');
            },

            resetAllSections: function() {
                this.hideSection('dpuwoo-simulation-process');
                this.hideSection('dpuwoo-simulation-results');
                this.hideSection('dpuwoo-update-process');
                this.hideSection('dpuwoo-final-results');
                
                $('#dpuwoo-sim-summary').empty();
                $('#dpuwoo-sim-results-table').empty();
                $('#dpuwoo-final-results').empty();
                
                cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };
            },

            enableButtons: function() {
                $('#dpuwoo-simulate, #dpuwoo-update-now').prop('disabled', false);
                isProcessing = false;
            },

            disableButtons: function() {
                $('#dpuwoo-simulate, #dpuwoo-update-now').prop('disabled', true);
                isProcessing = true;
            },

            updateProgressBar: function(type, current, total, text) {
                const percent = total > 0 ? Math.round((current / total) * 100) : 100;
                const progressBarId = type === 'simulation' ? 'dpuwoo-sim-progress' : 'dpuwoo-update-progress';
                const percentId = type === 'simulation' ? 'dpuwoo-sim-percent' : 'dpuwoo-update-percent';
                const textId = type === 'simulation' ? 'dpuwoo-sim-text' : 'dpuwoo-update-text';
                const batchCurrentId = type === 'simulation' ? 'dpuwoo-sim-current-batch' : 'dpuwoo-update-current-batch';
                const batchTotalId = type === 'simulation' ? 'dpuwoo-sim-total-batches' : 'dpuwoo-update-total-batches';
                const processedId = type === 'simulation' ? 'dpuwoo-sim-processed-products' : 'dpuwoo-update-processed-products';
                const totalProductsId = type === 'simulation' ? 'dpuwoo-sim-total-products' : 'dpuwoo-update-total-products';

                $('#' + progressBarId).css('width', percent + '%');
                $('#' + percentId).text(percent + '%');
                $('#' + textId).text(text);
                
                $('#' + batchCurrentId).text(current);
                $('#' + batchTotalId).text(total);
                
                const processed = cumulativeResults.updated + cumulativeResults.skipped + cumulativeResults.errors;
                $('#' + processedId).text(processed);
            },

            updateCumulativeResults: function(batchResults, type) {
                cumulativeResults.updated += batchResults.summary.updated || 0;
                cumulativeResults.skipped += batchResults.summary.skipped || 0;
                cumulativeResults.errors += batchResults.summary.errors || 0;
                cumulativeResults.changes = cumulativeResults.changes.concat(batchResults.changes || []);

                if (type === 'update') {
                    $('#dpuwoo-live-updated').text(cumulativeResults.updated);
                    $('#dpuwoo-live-skipped').text(cumulativeResults.skipped);
                    $('#dpuwoo-live-errors').text(cumulativeResults.errors);
                    $('#dpuwoo-update-live-results').removeClass('hidden');
                }
            },

            handleProcessError: function(message, type) {
                alert('Error en el proceso: ' + message);
                this.resetAllSections();
                this.enableButtons();
            },

            getStatusText: function(status) {
                const statusTexts = {
                    'updated': 'Actualizado',
                    'simulated': 'Simulado',
                    'error': 'Error',
                    'skipped': 'Sin cambios',
                    'pending': 'Pendiente'
                };
                return statusTexts[status] || status;
            },

            getStatusClass: function(status) {
                const statusClasses = {
                    'updated': 'bg-green-100 text-green-800',
                    'simulated': 'bg-blue-100 text-blue-800',
                    'error': 'bg-red-100 text-red-800',
                    'skipped': 'bg-gray-100 text-gray-800',
                    'pending': 'bg-yellow-100 text-yellow-800'
                };
                return statusClasses[status] || 'bg-gray-100 text-gray-800';
            },

            formatPrice: function(price) {
                if (!price || price === '0.00') return '-';
                return '$' + parseFloat(price).toFixed(2);
            },

            formatPriceRange: function(singlePrice, priceRange) {
                if (priceRange) {
                    return priceRange;
                }
                return singlePrice ? '$' + parseFloat(singlePrice).toFixed(2) : '$0';
            }
        };

        // Exponer variables globales para los módulos
        window.DPUWOO_Globals = {
            isProcessing: isProcessing,
            currentProcessType: currentProcessType,
            cumulativeResults: cumulativeResults
        };
    });

})(jQuery);