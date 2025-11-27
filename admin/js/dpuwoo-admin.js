(function ($) {
    'use strict';

    // Variables globales para el proceso
    let isProcessing = false;
    let currentProcessType = null; // 'simulation' o 'update'
    let cumulativeResults = {
        updated: 0,
        skipped: 0,
        errors: 0,
        changes: []
    };

    $(document).ready(function(){
        
        console.log('DPUWOO: Admin JS loaded');

        // Cargar logs al iniciar la página
        loadLogs();

        // Funciones de utilidad para mostrar/ocultar secciones
        function showSection(sectionId) {
            $('#' + sectionId).removeClass('hidden').addClass('block');
        }

        function hideSection(sectionId) {
            $('#' + sectionId).removeClass('block').addClass('hidden');
        }

        function resetAllSections() {
            hideSection('dpuwoo-simulation-process');
            hideSection('dpuwoo-simulation-results');
            hideSection('dpuwoo-update-process');
            hideSection('dpuwoo-final-results');
            
            // Limpiar resultados anteriores
            $('#dpuwoo-sim-summary').empty();
            $('#dpuwoo-sim-results-table').empty();
            $('#dpuwoo-final-results').empty();
            
            cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };
        }

        function enableButtons() {
            $('#dpuwoo-simulate, #dpuwoo-update-now').prop('disabled', false);
            isProcessing = false;
        }

        function disableButtons() {
            $('#dpuwoo-simulate, #dpuwoo-update-now').prop('disabled', true);
            isProcessing = true;
        }

        // Función para actualizar la barra de progreso
        function updateProgressBar(type, current, total, text) {
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
            
            // Actualizar productos procesados
            const processed = cumulativeResults.updated + cumulativeResults.skipped + cumulativeResults.errors;
            $('#' + processedId).text(processed);
        }

        // Función para actualizar resultados acumulados
        function updateCumulativeResults(batchResults, type) {
            cumulativeResults.updated += batchResults.summary.updated || 0;
            cumulativeResults.skipped += batchResults.summary.skipped || 0;
            cumulativeResults.errors += batchResults.summary.errors || 0;
            cumulativeResults.changes = cumulativeResults.changes.concat(batchResults.changes || []);

            // Actualizar resultados en tiempo real si es actualización
            if (type === 'update') {
                $('#dpuwoo-live-updated').text(cumulativeResults.updated);
                $('#dpuwoo-live-skipped').text(cumulativeResults.skipped);
                $('#dpuwoo-live-errors').text(cumulativeResults.errors);
                $('#dpuwoo-update-live-results').removeClass('hidden');
            }
        }

        // Función para procesar por lotes
        function processBatch(action, batch, totalBatches, type, onComplete) {
            console.log('DPUWOO: Processing batch', action, batch, totalBatches, type);
            
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
                    console.log('DPUWOO: Batch response', res);
                    
                    if (!res.success) {
                        handleProcessError(res.data?.message || 'Error desconocido', type);
                        return;
                    }

                    const data = res.data;
                    const actionText = type === 'simulation' ? 'Simulando...' : 'Actualizando...';
                    
                    updateProgressBar(type, batch + 1, totalBatches, actionText);
                    
                    // Actualizar información del lote
                    if (data.batch_info) {
                        const totalProductsId = type === 'simulation' ? 'dpuwoo-sim-total-products' : 'dpuwoo-update-total-products';
                        $('#' + totalProductsId).text(data.batch_info.total_products);
                    }

                    // Acumular resultados
                    updateCumulativeResults(data, type);

                    // Procesar siguiente lote si existe
                    if (batch < totalBatches - 1) {
                        processBatch(action, batch + 1, totalBatches, type, onComplete);
                    } else {
                        // Proceso completado
                        onComplete(data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('DPUWOO: Batch error', xhr, status, error);
                    handleProcessError('Error de conexión: ' + error, type);
                }
            });
        }

        function handleProcessError(message, type) {
            alert('Error en el proceso: ' + message);
            resetAllSections();
            enableButtons();
        }

        // ========== FUNCIONALIDAD DE LOGS ==========

        // Función para cargar logs con loading
        function loadLogs() {
            console.log('DPUWOO: Loading logs...');
            showLogsLoading();
            
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
                        displayLogs(res.data);
                    } else {
                        showLogsError('Error al cargar los logs: ' + (res.data || 'Error desconocido'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('DPUWOO: Error loading logs', xhr, status, error);
                    showLogsError('Error de conexión: ' + error);
                }
            });
        }

        // Mostrar loading en la tabla de logs
        function showLogsLoading() {
            $('#dpuwoo-log-table').html(`
                <div class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    <span class="ml-3 text-gray-600">Cargando historial...</span>
                </div>
            `);
        }

        // Mostrar error en logs
        function showLogsError(message) {
            $('#dpuwoo-log-table').html(`
                <div class="text-center py-8">
                    <div class="text-red-500 text-lg mb-2">❌</div>
                    <p class="text-red-600 mb-4">${message}</p>
                    <button onclick="loadLogs()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        Reintentar
                    </button>
                </div>
            `);
        }

        // Mostrar logs en la tabla
        function displayLogs(logs) {
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
                const date = new Date(log.date || log.created_at);
                const formattedDate = date.toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // Determinar tipo de dólar
                const dollarType = log.dollar_type || 'official';
                const dollarTypeText = dollarType === 'official' ? 'Oficial' : 
                                      dollarType === 'blue' ? 'Blue' : 
                                      dollarType;

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

            tableHtml += `
                    </tbody>
                </table>
            `;

            $('#dpuwoo-log-table').html(tableHtml);
        }

        // Función para ver detalles de un run
        function viewRunDetails(runId) {
            console.log('DPUWOO: Viewing run details', runId);
            
            // Mostrar modal de detalles
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
                    console.log('DPUWOO: Run details loaded', res);
                    
                    if (!res.success) {
                        $('#dpuwoo-run-details-content').html(`
                            <div class="text-center py-8 text-red-600">
                                Error al cargar los detalles: ${res.data || 'Error desconocido'}
                            </div>
                        `);
                        return;
                    }

                    displayRunDetails(res.data, runId);
                },
                error: function (xhr, status, error) {
                    console.error('DPUWOO: Error loading run details', xhr, status, error);
                    $('#dpuwoo-run-details-content').html(`
                        <div class="text-center py-8 text-red-600">
                            Error de conexión: ${error}
                        </div>
                    `);
                }
            });
        }

        // Mostrar detalles de un run
        function displayRunDetails(items, runId) {
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
                const statusClass = getStatusClass(item.status);
                const statusText = getStatusText(item.status);
                const percentageChange = item.percentage_change ? 
                    parseFloat(item.percentage_change).toFixed(2) + '%' : '-';
                
                detailsHtml += `
                    <tr class="border-b border-gray-200">
                        <td class="p-2 border border-gray-200">
                            <div class="font-medium">${item.product_name || 'N/A'}</div>
                            <div class="text-xs text-gray-500">ID: ${item.product_id}</div>
                        </td>
                        <td class="p-2 border border-gray-200 font-mono text-xs">${item.product_sku || 'N/A'}</td>
                        <td class="p-2 border border-gray-200 font-mono">${formatPrice(item.old_regular_price)}</td>
                        <td class="p-2 border border-gray-200 font-mono">${formatPrice(item.new_regular_price)}</td>
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

            detailsHtml += `
                        </tbody>
                    </table>
                </div>
            `;

            $('#dpuwoo-run-details-content').html(detailsHtml);
        }

        // Función auxiliar para formatear precios
        function formatPrice(price) {
            if (!price || price === '0.00') return '-';
            return '$' + parseFloat(price).toFixed(2);
        }

        // Función para revertir un run completo
        function revertRun(runId) {
            if (!confirm('¿Estás seguro de que deseas revertir esta ejecución completa? Esta acción no se puede deshacer.')) {
                return;
            }

            const $btn = $(`.dpuwoo-revert-run[data-run="${runId}"]`);
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
                        // Recargar logs para reflejar los cambios
                        loadLogs();
                        
                        // Mostrar notificación de éxito
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
                },
                error: function (xhr, status, error) {
                    alert('Error de conexión: ' + error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        }

        // Event listeners para los botones de logs
        $(document).on('click', '.dpuwoo-view-run', function (e) {
            e.preventDefault();
            const runId = $(this).data('run');
            viewRunDetails(runId);
        });

        $(document).on('click', '.dpuwoo-revert-run', function (e) {
            e.preventDefault();
            const runId = $(this).data('run');
            revertRun(runId);
        });

        // Cerrar modal de detalles
        $('#dpuwoo-close-details-modal').on('click', function (e) {
            e.preventDefault();
            $('#dpuwoo-run-details-modal').addClass('hidden');
        });

        // ========== SIMULACIÓN ==========

        $('#dpuwoo-simulate').on('click', function (e) {
            e.preventDefault();
            if (isProcessing) return;

            console.log('DPUWOO: Starting simulation');
            disableButtons();
            resetAllSections();
            showSection('dpuwoo-simulation-process');
            
            cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };

            // Iniciar proceso de simulación
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
                    console.log('DPUWOO: First simulation batch response', res);
                    
                    if (!res.success) {
                        handleProcessError(res.data?.message || 'Error desconocido', 'simulation');
                        return;
                    }

                    const data = res.data;
                    const totalBatches = data.batch_info?.total_batches || 1;
                    
                    updateProgressBar('simulation', 1, totalBatches, 'Simulando...');
                    
                    if (data.batch_info) {
                        $('#dpuwoo-sim-total-products').text(data.batch_info.total_products);
                    }
                    
                    updateCumulativeResults(data, 'simulation');

                    // Continuar con los lotes restantes
                    if (totalBatches > 1) {
                        processBatch('dpuwoo_simulate_batch', 1, totalBatches, 'simulation', function(finalData) {
                            showSimulationResults(finalData);
                        });
                    } else {
                        showSimulationResults(data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('DPUWOO: First simulation batch error', xhr, status, error);
                    handleProcessError('Error comunicándose con el servidor: ' + error, 'simulation');
                }
            });
        });

        // ========== ACTUALIZACIÓN DIRECTA ==========

        $('#dpuwoo-update-now').on('click', function (e) {
            e.preventDefault();
            if (isProcessing) return;

            console.log('DPUWOO: Direct update clicked');
            $('#dpuwoo-direct-update-modal').removeClass('hidden');
        });

        // Confirmar actualización directa
        $('#dpuwoo-direct-proceed').on('click', function (e) {
            e.preventDefault();
            $('#dpuwoo-direct-update-modal').addClass('hidden');
            startDirectUpdate();
        });

        // Cancelar actualización directa
        $('#dpuwoo-direct-cancel').on('click', function (e) {
            e.preventDefault();
            $('#dpuwoo-direct-update-modal').addClass('hidden');
        });

        function startDirectUpdate() {
            disableButtons();
            resetAllSections();
            showSection('dpuwoo-update-process');
            
            cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };

            // Iniciar proceso de actualización directa
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
                    console.log('DPUWOO: First update batch response', res);
                    
                    if (!res.success) {
                        handleProcessError(res.data?.message || 'Error desconocido', 'update');
                        return;
                    }

                    const data = res.data;
                    const totalBatches = data.batch_info?.total_batches || 1;
                    
                    updateProgressBar('update', 1, totalBatches, 'Actualizando...');
                    
                    if (data.batch_info) {
                        $('#dpuwoo-update-total-products').text(data.batch_info.total_products);
                    }
                    
                    updateCumulativeResults(data, 'update');

                    if (totalBatches > 1) {
                        processBatch('dpuwoo_update_batch', 1, totalBatches, 'update', function(finalData) {
                            showFinalResults(finalData, false);
                            // Recargar logs después de actualización
                            loadLogs();
                        });
                    } else {
                        showFinalResults(data, false);
                        // Recargar logs después de actualización
                        loadLogs();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('DPUWOO: First update batch error', xhr, status, error);
                    handleProcessError('Error comunicándose con el servidor: ' + error, 'update');
                }
            });
        }

        // ========== ACTUALIZACIÓN POST-SIMULACIÓN ==========

        // Proceder con actualización después de simulación
        $('#dpuwoo-proceed-update').on('click', function (e) {
            e.preventDefault();
            
            // Mostrar modal de confirmación con resumen
            const totalChanges = cumulativeResults.updated + cumulativeResults.errors;
            $('#dpuwoo-confirm-message').html(
                `Se actualizarán <strong>${cumulativeResults.updated} productos</strong> y ` +
                `se encontrarán <strong>${cumulativeResults.errors} errores</strong>.`
            );
            
            $('#dpuwoo-confirm-summary').html(`
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>✅ Productos a actualizar: <strong>${cumulativeResults.updated}</strong></div>
                    <div>⚡ Productos sin cambios: <strong>${cumulativeResults.skipped}</strong></div>
                    <div>❌ Errores esperados: <strong>${cumulativeResults.errors}</strong></div>
                    <div>📊 Total procesado: <strong>${cumulativeResults.changes.length}</strong></div>
                </div>
            `);
            
            $('#dpuwoo-confirm-update-modal').removeClass('hidden');
        });

        // Confirmar actualización post-simulación
        $('#dpuwoo-confirm-proceed').on('click', function (e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
            hideSection('dpuwoo-simulation-results');
            startDirectUpdate();
        });

        // Cancelar actualización post-simulación
        $('#dpuwoo-confirm-cancel').on('click', function (e) {
            e.preventDefault();
            $('#dpuwoo-confirm-update-modal').addClass('hidden');
        });

        // ========== BOTONES DE NAVEGACIÓN ==========

        // Nueva simulación
        $('#dpuwoo-new-simulation').on('click', function (e) {
            e.preventDefault();
            resetAllSections();
            enableButtons();
        });

        // Cancelar simulación
        $('#dpuwoo-cancel-simulation').on('click', function (e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cancelar la simulación?')) {
                resetAllSections();
                enableButtons();
            }
        });

        // Cancelar actualización
        $('#dpuwoo-cancel-update').on('click', function (e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cancelar la actualización?')) {
                resetAllSections();
                enableButtons();
            }
        });

        // ========== FUNCIONES DE RESULTADOS ==========

        // Función para mostrar resultados de simulación
        function showSimulationResults(data) {
            hideSection('dpuwoo-simulation-process');
            showSection('dpuwoo-simulation-results');
            
            const summaryHtml = `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div class="text-center p-3 bg-green-100 rounded-lg">
                        <div class="text-2xl font-bold text-green-700">${cumulativeResults.updated}</div>
                        <div class="text-green-600">Se actualizarían</div>
                    </div>
                    <div class="text-center p-3 bg-gray-100 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700">${cumulativeResults.skipped}</div>
                        <div class="text-gray-600">Sin cambios</div>
                    </div>
                    <div class="text-center p-3 bg-red-100 rounded-lg">
                        <div class="text-2xl font-bold text-red-700">${cumulativeResults.errors}</div>
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
            
            // Mostrar tabla de resultados si hay cambios
            if (cumulativeResults.changes.length > 0) {
                const tableHtml = createResultsTable(cumulativeResults.changes, true);
                $('#dpuwoo-sim-results-table').html(tableHtml);
            } else {
                $('#dpuwoo-sim-results-table').html('<p class="text-gray-500 text-center py-4">No se encontraron cambios para mostrar.</p>');
            }
            
            enableButtons();
            
            // Mostrar alerta de simulación completada
            showSimulationAlert(cumulativeResults.updated, cumulativeResults.errors);
        }

        // Función para mostrar alerta de simulación completada
        function showSimulationAlert(updatedCount, errorCount) {
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
        }

        // Función para mostrar resultados finales
        function showFinalResults(data, isSimulation) {
            hideSection('dpuwoo-update-process');
            showSection('dpuwoo-final-results');
            
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
                            <div class="text-2xl font-bold text-green-700">${cumulativeResults.updated}</div>
                            <div class="text-green-600">Actualizados</div>
                        </div>
                        <div class="text-center p-3 bg-gray-100 rounded-lg">
                            <div class="text-2xl font-bold text-gray-700">${cumulativeResults.skipped}</div>
                            <div class="text-gray-600">Sin cambios</div>
                        </div>
                        <div class="text-center p-3 bg-red-100 rounded-lg">
                            <div class="text-2xl font-bold text-red-700">${cumulativeResults.errors}</div>
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
                        ${createResultsTable(cumulativeResults.changes, isSimulation)}
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Volver al Dashboard
                        </button>
                    </div>
                </div>
            `;
            
            $('#dpuwoo-final-results').html(resultsHtml);
            enableButtons();
            
            // Mostrar alerta de éxito si es actualización real
            if (!isSimulation) {
                showSuccessAlert(cumulativeResults.updated, cumulativeResults.errors);
            }
        }

        // Función para mostrar alerta de éxito con SweetAlert2
        function showSuccessAlert(updatedCount, errorCount) {
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
                didOpen: () => {
                    // Scroll automático a los resultados
                    setTimeout(() => {
                        $('html, body').animate({
                            scrollTop: $('#dpuwoo-final-results').offset().top - 100
                        }, 1000);
                    }, 500);
                }
            });
        }

        // Función para crear tablas de resultados
        function createResultsTable(changes, isSimulation) {
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
                // Agrupar variaciones por producto padre
                const groupedItems = groupVariationsByParent(changes);
                groupedItems.forEach(function (item) {
                    const pct = item.percentage_change !== null ? parseFloat(item.percentage_change).toFixed(2) + '%' : '-';
                    const statusClass = 'px-2 py-1 rounded-full text-xs ' + getStatusClass(item.status);
                    const statusText = getStatusText(item.status);
                    const statusBadge = '<span class="' + statusClass + '">' + statusText + '</span>';
                    
                    // Mostrar tipo de producto
                    const productType = item.product_type || 'simple';
                    const typeBadge = productType === 'variable' ? 
                        '<span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">Variable</span>' : 
                        '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Simple</span>';

                    tableHtml += '<tr class="border-b border-gray-200 hover:bg-gray-50">';
                    tableHtml += '<td class="p-3 border border-gray-200"><div class="font-medium">' + (item.product_name || '') + '</div><div class="text-xs text-gray-500">ID: ' + (item.product_id || '') + ' | SKU: ' + (item.product_sku || 'N/A') + '</div></td>';
                    tableHtml += '<td class="p-3 border border-gray-200">' + typeBadge + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200 font-mono text-sm">' + formatPriceRange(item.base_price, item.base_price_range) + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200 font-mono text-sm">' + formatPriceRange(item.old_regular_price, item.old_price_range) + '</td>';
                    tableHtml += '<td class="p-3 border border-gray-200 font-mono text-sm font-bold ' + (isSimulation ? 'text-green-600' : 'text-blue-600') + '">' + formatPriceRange(item.new_regular_price, item.new_price_range) + '</td>';
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
        }

        function getStatusText(status) {
            const statusTexts = {
                'updated': 'Actualizado',
                'simulated': 'Simulado',
                'error': 'Error',
                'skipped': 'Sin cambios',
                'pending': 'Pendiente'
            };
            return statusTexts[status] || status;
        }

        function getStatusClass(status) {
            const statusClasses = {
                'updated': 'bg-green-100 text-green-800',
                'simulated': 'bg-blue-100 text-blue-800',
                'error': 'bg-red-100 text-red-800',
                'skipped': 'bg-gray-100 text-gray-800',
                'pending': 'bg-yellow-100 text-yellow-800'
            };
            return statusClasses[status] || 'bg-gray-100 text-gray-800';
        }

        function groupVariationsByParent(items) {
            const grouped = [];
            const parentMap = {};

            items.forEach(function(item) {
                // Si es una variación, agrupar por parent_id
                if (item.product_type === 'variation' && item.parent_id) {
                    if (!parentMap[item.parent_id]) {
                        parentMap[item.parent_id] = {
                            variations: [],
                            parent_data: null
                        };
                    }
                    parentMap[item.parent_id].variations.push(item);
                } else {
                    // Es producto simple o variable sin variaciones
                    grouped.push(item);
                }
            });

            // Procesar los grupos de variaciones
            Object.keys(parentMap).forEach(function(parentId) {
                const group = parentMap[parentId];
                if (group.variations.length > 0) {
                    // Usar los datos de la primera variación como base
                    const firstVariation = group.variations[0];
                    
                    // Calcular rangos de precios
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

                    // Determinar el estado general
                    const statuses = group.variations.map(function(v) { return v.status; });
                    const overallStatus = determineOverallStatus(statuses);

                    // Crear item agrupado
                    const groupedItem = {
                        product_id: parentId,
                        product_name: firstVariation.product_name.replace(/ - .*$/, ''), // Remover atributos del nombre
                        product_sku: firstVariation.product_sku,
                        product_type: 'variable',
                        old_regular_price: null,
                        new_regular_price: null,
                        old_price_range: oldMin === oldMax ? '$' + oldMin.toFixed(2) : '$' + oldMin.toFixed(2) + ' - $' + oldMax.toFixed(2),
                        new_price_range: newMin === newMax ? '$' + newMin.toFixed(2) : '$' + newMin.toFixed(2) + ' - $' + newMax.toFixed(2),
                        base_price_range: baseMin === baseMax ? '$' + baseMin.toFixed(2) : '$' + baseMin.toFixed(2) + ' - $' + baseMax.toFixed(2),
                        percentage_change: calculateAveragePercentage(group.variations),
                        status: overallStatus,
                        reason: getOverallReason(group.variations),
                        variations: group.variations
                    };

                    grouped.push(groupedItem);
                }
            });

            return grouped;
        }

        function determineOverallStatus(statuses) {
            if (statuses.includes('error')) return 'error';
            if (statuses.includes('updated')) return 'updated';
            if (statuses.includes('simulated')) return 'simulated';
            if (statuses.every(s => s === 'skipped')) return 'skipped';
            return 'pending';
        }

        function calculateAveragePercentage(variations) {
            const validChanges = variations.filter(function(v) {
                return v.percentage_change !== null && !isNaN(v.percentage_change);
            });
            if (validChanges.length === 0) return null;
            
            const sum = validChanges.reduce(function(total, v) {
                return total + parseFloat(v.percentage_change);
            }, 0);
            return sum / validChanges.length;
        }

        function getOverallReason(variations) {
            const errors = variations.filter(function(v) { return v.status === 'error'; });
            if (errors.length > 0) {
                return errors[0].reason || 'Error en variaciones';
            }
            return null;
        }

        function formatPriceRange(singlePrice, priceRange) {
            if (priceRange) {
                return priceRange;
            }
            return singlePrice ? '$' + parseFloat(singlePrice).toFixed(2) : '$0';
        }

        // Revertir item individual
        $(document).on('click', '.dpu-revert-item', function (e) {
            e.preventDefault();
            const logId = $(this).data('log');
            if (!confirm('¿Revertir este cambio?')) return;

            const $btn = $(this);
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
                    // Actualizar estado en la tabla
                    $btn.closest('tr').find('.status').html('<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">reverted</span>');
                },
                error: function () {
                    alert('Error comunicándose con el servidor');
                    $btn.prop('disabled', false).text('Revertir');
                }
            });
        });

    });

})(jQuery);