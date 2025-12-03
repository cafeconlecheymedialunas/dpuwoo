(function ($) {
    'use strict';

    window.DPUWOO_Simulation = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#dpuwoo-simulate').on('click', this.startSimulation.bind(this));
            $('#dpuwoo-proceed-update').on('click', this.showUpdateConfirmation.bind(this));
            $('#dpuwoo-confirm-proceed').on('click', this.proceedWithUpdate.bind(this));
            $('#dpuwoo-confirm-cancel').on('click', this.cancelUpdate.bind(this));
            $('#dpuwoo-new-simulation').on('click', this.newSimulation.bind(this));
            $('#dpuwoo-cancel-simulation').on('click', this.cancelSimulation.bind(this));
        },

        startSimulation: function(e) {
            e.preventDefault();
            if (DPUWOO_Globals.isProcessing) return;

            console.log('DPUWOO: Starting simulation');
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
                            this.showSimulationResults(finalData);
                        }.bind(this));
                    } else {
                        this.showSimulationResults(data);
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
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    DPUWOO_Utils.handleProcessError('Error de conexión: ' + error, type);
                }
            });
        },

        showSimulationResults: function(data) {
            DPUWOO_Utils.hideSection('dpuwoo-simulation-process');
            DPUWOO_Utils.showSection('dpuwoo-simulation-results');
            
            const summaryHtml = `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div class="text-center p-3 bg-green-100 rounded-lg">
                        <div class="text-2xl font-bold text-green-700">${DPUWOO_Globals.cumulativeResults.updated}</div>
                        <div class="text-green-600">Se actualizarían</div>
                    </div>
                    <div class="text-center p-3 bg-gray-100 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700">${DPUWOO_Globals.cumulativeResults.skipped}</div>
                        <div class="text-gray-600">Sin cambios</div>
                    </div>
                    <div class="text-center p-3 bg-red-100 rounded-lg">
                        <div class="text-2xl font-bold text-red-700">${DPUWOO_Globals.cumulativeResults.errors}</div>
                        <div class="text-red-600">Con errores</div>
                    </div>
                    <div class="text-center p-3 bg-blue-100 rounded-lg">
                        <div class="text-2xl font-bold text-blue-700">${data.rate ? '$' + data.rate : 'n/a'}</div>
                        <div class="text-blue-600">Dólar actual</div>
                    </div>
                </div>
                ${data.ratio ? `<p class="mt-3 text-sm text-gray-600"><strong>Ratio de ajuste:</strong> ${data.ratio.toFixed(4)}x</p>` : ''}
            `;
            
            $('#dpuwoo-sim-summary').html(summaryHtml);
            console.log(DPUWOO_Globals.cumulativeResults)
            if (DPUWOO_Globals.cumulativeResults.changes.length > 0) {
                const tableHtml = this.createResultsTable(DPUWOO_Globals.cumulativeResults.changes, true);
                $('#dpuwoo-sim-results-table').html(tableHtml);
            } else {
                $('#dpuwoo-sim-results-table').html('<p class="text-gray-500 text-center py-4">No se encontraron cambios para mostrar.</p>');
            }
            
            DPUWOO_Utils.enableButtons();
            this.showSimulationAlert(DPUWOO_Globals.cumulativeResults.updated, DPUWOO_Globals.cumulativeResults.errors);
        },

        showSimulationAlert: function(updatedCount, errorCount) {
            Swal.fire({
                icon: 'info',
                title: 'Simulación Completada',
                html: `
                    <div class="text-left">
                        <p class="mb-2">📊 <strong>${updatedCount} productos</strong> se actualizarían</p>
                        ${errorCount > 0 ? 
                            `<p class="mb-2">⚠️ <strong>${errorCount} productos</strong> tendrían errores</p>` : 
                            `<p class="mb-2">✅ Todos los productos se pueden actualizar</p>`
                        }
                        <p class="text-sm text-gray-600 mt-3">Revisa los cambios propuestos antes de confirmar la actualización real.</p>
                    </div>
                `,
                confirmButtonText: 'Ver Resultados',
                confirmButtonColor: '#10B981',
                timer: 4000,
                timerProgressBar: true
            });
        },

        showUpdateConfirmation: function(e) {
            e.preventDefault();
            
            const totalChanges = DPUWOO_Globals.cumulativeResults.updated + DPUWOO_Globals.cumulativeResults.errors;
            $('#dpuwoo-confirm-message').html(
                `Se actualizarán <strong>${DPUWOO_Globals.cumulativeResults.updated} productos</strong> y ` +
                `se encontrarán <strong>${DPUWOO_Globals.cumulativeResults.errors} errores</strong>.`
            );
            
            $('#dpuwoo-confirm-summary').html(`
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>✅ Productos a actualizar: <strong>${DPUWOO_Globals.cumulativeResults.updated}</strong></div>
                    <div>⚡ Productos sin cambios: <strong>${DPUWOO_Globals.cumulativeResults.skipped}</strong></div>
                    <div>❌ Errores esperados: <strong>${DPUWOO_Globals.cumulativeResults.errors}</strong></div>
                    <div>📊 Total procesado: <strong>${DPUWOO_Globals.cumulativeResults.changes.length}</strong></div>
                </div>
            `);
            
            $('#dpuwoo-confirm-update-modal').removeClass('hidden');
        },

        proceedWithUpdate: function(e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
            DPUWOO_Utils.hideSection('dpuwoo-simulation-results');
            // Delegar a DPUWOO_Update
            if (window.DPUWOO_Update) {
                window.DPUWOO_Update.startDirectUpdate();
            }
        },

        cancelUpdate: function(e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
        },

        newSimulation: function(e) {
            e.preventDefault();
            DPUWOO_Utils.resetAllSections();
            DPUWOO_Utils.enableButtons();
        },

        cancelSimulation: function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cancelar la simulación?')) {
                DPUWOO_Utils.resetAllSections();
                DPUWOO_Utils.enableButtons();
            }
        },

        createResultsTable: function(changes, isSimulation) {
            let tableHtml = '<div class="overflow-x-auto"><table class="w-full text-left border-collapse border border-gray-200"><thead><tr class="bg-gray-100">';
            tableHtml += '<th class="p-3 border border-gray-200">Producto</th><th class="p-3 border border-gray-200">Tipo</th>';
            tableHtml += '<th class="p-3 border border-gray-200">Precio Base</th><th class="p-3 border border-gray-200">Precio Anterior</th>';
            tableHtml += '<th class="p-3 border border-gray-200">' + (isSimulation ? 'Precio Proyectado' : 'Precio Actual') + '</th>';
            tableHtml += '<th class="p-3 border border-gray-200">% Cambio</th><th class="p-3 border border-gray-200">Estado</th>';
            if (!isSimulation) {
                tableHtml += '<th class="p-3 border border-gray-200">Acciones</th>';
            }
            tableHtml += '</tr></thead><tbody>';
            
            if (!changes || changes.length === 0) {
                tableHtml += '<tr><td colspan="' + (isSimulation ? '7' : '8') + '" class="p-4 text-center text-gray-500">No hay cambios para mostrar</td></tr>';
            } else {
                const groupedItems = this.groupVariationsByParent(changes);
                groupedItems.forEach(function (item) {
                    const pct = item.percentage_change !== null ? parseFloat(item.percentage_change).toFixed(2) + '%' : '-';
                    const statusClass = 'px-2 py-1 rounded-full text-xs ' + DPUWOO_Utils.getStatusClass(item.status);
                    const statusText = DPUWOO_Utils.getStatusText(item.status);
                    const statusBadge = '<span class="' + statusClass + '">' + statusText + '</span>';
                    
                    const productType = item.product_type || 'simple';
                    const typeBadge = productType === 'variable' ? 
                        '<span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">Variable</span>' : 
                        '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Simple</span>';

                    tableHtml += '<tr class="border-b border-gray-200 hover:bg-gray-50">';
                    tableHtml += '<td class="p-3 border border-gray-200"><div class="font-medium">' + (item.product_name || '') + '</div><div class="text-xs text-gray-500">ID: ' + (item.product_id || '') + ' | SKU: ' + (item.product_sku || 'N/A') + '</div></td>';
                    tableHtml += '<td class="p-3 border border-gray-200">' + typeBadge + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200 font-mono text-sm">' + DPUWOO_Utils.formatPriceRange(item.base_price, item.base_price_range) + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200 font-mono text-sm">' + DPUWOO_Utils.formatPriceRange(item.old_regular_price, item.old_price_range) + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200 font-mono text-sm font-bold ' + (isSimulation ? 'text-green-600' : 'text-blue-600') + '">' + DPUWOO_Utils.formatPriceRange(item.new_regular_price, item.new_price_range) + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200 font-mono ' + (item.percentage_change > 0 ? 'text-red-600' : 'text-green-600') + '">' + pct + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200">' + statusBadge + '</td>';
                    
                    if (!isSimulation) {
                        tableHtml += '<td class="p-3 border border-gray-200">';
                        if (item.status === 'updated') {
                            tableHtml += '<button class="dpu-revert-item px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition" data-log="' + (item.log_id || item.id) + '">Revertir</button>';
                        } else if (item.status === 'error') {
                            tableHtml += '<span class="text-red-600 text-xs">' + (item.reason || 'Error') + '</span>';
                        } else if (item.status === 'skipped') {
                            tableHtml += '<span class="text-gray-500 text-xs">Sin cambios</span>';
                        }
                        tableHtml += '</td>';
                    }
                    
                    tableHtml += '</tr>';
                });
            }
            
            tableHtml += '</tbody></table></div>';
            return tableHtml;
        },

        groupVariationsByParent: function(items) {
            const grouped = [];
            const parentMap = {};

            items.forEach(function(item) {
                if (item.product_type === 'variation' && item.parent_id) {
                    if (!parentMap[item.parent_id]) {
                        parentMap[item.parent_id] = {
                            variations: [],
                            parent_data: null
                        };
                    }
                    parentMap[item.parent_id].variations.push(item);
                } else {
                    grouped.push(item);
                }
            });

            Object.keys(parentMap).forEach(function(parentId) {
                const group = parentMap[parentId];
                if (group.variations.length > 0) {
                    const firstVariation = group.variations[0];
                    
                    const oldPrices = group.variations.map(function(v) { 
                        return v.old_regular_price || 0; 
                    }).filter(function(p) { return p > 0; });
                    
                    const newPrices = group.variations.map(function(v) { 
                        return v.new_regular_price || 0; 
                    }).filter(function(p) { return p > 0; });
                    
                    const basePrices = group.variations.map(function(v) { 
                        return v.base_price || 0; 
                    }).filter(function(p) { return p > 0; });

                    const oldMin = oldPrices.length ? Math.min(...oldPrices) : 0;
                    const oldMax = oldPrices.length ? Math.max(...oldPrices) : 0;
                    const newMin = newPrices.length ? Math.min(...newPrices) : 0;
                    const newMax = newPrices.length ? Math.max(...newPrices) : 0;
                    const baseMin = basePrices.length ? Math.min(...basePrices) : 0;
                    const baseMax = basePrices.length ? Math.max(...basePrices) : 0;

                    const statuses = group.variations.map(function(v) { return v.status; });
                    const overallStatus = this.determineOverallStatus(statuses);

                    const groupedItem = {
                        product_id: parentId,
                        product_name: firstVariation.product_name.replace(/ - .*$/, ''),
                        product_sku: firstVariation.product_sku,
                        product_type: 'variable',
                        old_regular_price: null,
                        new_regular_price: null,
                        old_price_range: oldMin === oldMax ? '$' + oldMin.toFixed(2) : '$' + oldMin.toFixed(2) + ' - $' + oldMax.toFixed(2),
                        new_price_range: newMin === newMax ? '$' + newMin.toFixed(2) : '$' + newMin.toFixed(2) + ' - $' + newMax.toFixed(2),
                        base_price_range: baseMin === baseMax ? '$' + baseMin.toFixed(2) : '$' + baseMin.toFixed(2) + ' - $' + baseMax.toFixed(2),
                        percentage_change: this.calculateAveragePercentage(group.variations),
                        status: overallStatus,
                        reason: this.getOverallReason(group.variations),
                        variations: group.variations
                    };

                    grouped.push(groupedItem);
                }
            }.bind(this));

            return grouped;
        },

        determineOverallStatus: function(statuses) {
            if (statuses.includes('error')) return 'error';
            if (statuses.includes('updated')) return 'updated';
            if (statuses.includes('simulated')) return 'simulated';
            if (statuses.every(s => s === 'skipped')) return 'skipped';
            return 'pending';
        },

        calculateAveragePercentage: function(variations) {
            const validChanges = variations.filter(function(v) {
                return v.percentage_change !== null && !isNaN(v.percentage_change);
            });
            if (validChanges.length === 0) return null;
            
            const sum = validChanges.reduce(function(total, v) {
                return total + parseFloat(v.percentage_change);
            }, 0);
            return sum / validChanges.length;
        },

        getOverallReason: function(variations) {
            const errors = variations.filter(function(v) { return v.status === 'error'; });
            if (errors.length > 0) {
                return errors[0].reason || 'Error en variaciones';
            }
            return null;
        }
    };

})(jQuery);