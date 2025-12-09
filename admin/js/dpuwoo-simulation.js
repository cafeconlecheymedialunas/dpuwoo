/**
 * Lógica de JavaScript para el panel de administración del Dollar Price Updater (DPUWoo)
 * Maneja la simulación por lotes, la actualización real y el estado del UI.
 */
document.addEventListener('DOMContentLoaded', function () {
    const $ = jQuery;

    // --- Elementos de UI ---
    const simulateButton = $('#dpuwoo-simulate');
    const updateNowButton = $('#dpuwoo-update-now');
    const simulationProcess = $('#dpuwoo-simulation-process');
    const simulationResults = $('#dpuwoo-simulation-results');
    const updateProcess = $('#dpuwoo-update-process');
    const directUpdateModal = $('#dpuwoo-direct-update-modal');
    const confirmUpdateModal = $('#dpuwoo-confirm-update-modal');
    const finalResults = $('#dpuwoo-final-results');

    // Simulación UI elements
    const simProgress = $('#dpuwoo-sim-progress');
    const simPercent = $('#dpuwoo-sim-percent');
    const simText = $('#dpuwoo-sim-text');
    const simCurrentBatch = $('#dpuwoo-sim-current-batch');
    const simTotalBatches = $('#dpuwoo-sim-total-batches');
    const simProcessedProducts = $('#dpuwoo-sim-processed-products');
    const simTotalProducts = $('#dpuwoo-sim-total-products');
    const simResultsTable = $('#dpuwoo-sim-results-table'); 

    // Actualización UI elements
    const updateProgress = $('#dpuwoo-update-progress');
    const updatePercent = $('#dpuwoo-update-percent');
    const updateText = $('#dpuwoo-update-text');
    const updateCurrentBatch = $('#dpuwoo-update-current-batch');
    const updateTotalBatches = $('#dpuwoo-update-total-batches');
    const updateProcessedProducts = $('#dpuwoo-update-processed-products');
    const updateTotalProducts = $('#dpuwoo-update-total-products');
    const liveUpdated = $('#dpuwoo-live-updated');
    const liveSkipped = $('#dpuwoo-live-skipped');
    const liveErrors = $('#dpuwoo-live-errors');

    // --- Variables de Estado ---
    let isSimulationRunning = false;
    let isUpdateRunning = false;
    let totalSimulatedProducts = 0;
    let totalUpdatedProducts = 0;
    let totalProductsToProcess = 0; // Total de productos a procesar (para la barra)
    let totalBatches = 0;
    let finalSimulationResults = null;
    let finalUpdateResults = null;
    let currentProcessType = null; // 'simulation' o 'update'

    // --- Funciones de Utilidad ---

    /**
     * Muestra u oculta secciones del dashboard.
     */
    function showSection(sectionName) {
        simulationProcess.hide();
        simulationResults.hide();
        updateProcess.hide();
        finalResults.hide();

        if (sectionName === 'sim-process') {
            simulationProcess.show();
        } else if (sectionName === 'sim-results') {
            simulationResults.show();
        } else if (sectionName === 'update-process') {
            updateProcess.show();
        } else if (sectionName === 'final-results') {
            finalResults.show();
        }
    }

    /**
     * Actualiza la barra de progreso.
     */
    function updateProgressUI(processed, total, type) {
        // Usa totalProductsToProcess para el total, asegurando un cálculo correcto.
        const percentage = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
        const progressEl = type === 'simulation' ? simProgress : updateProgress;
        const percentEl = type === 'simulation' ? simPercent : updatePercent;
        const processedEl = type === 'simulation' ? simProcessedProducts : updateProcessedProducts;
        const totalEl = type === 'simulation' ? simTotalProducts : updateTotalProducts;

        progressEl.css('width', percentage + '%');
        percentEl.text(percentage + '%');
        processedEl.text(processed);
        totalEl.text(total); 

        if (type === 'update') {
            $('#dpuwoo-update-live-results').show();
        }
    }

    /**
     * Inicia el proceso de lotes (simulación o actualización).
     */
    function startBatchProcess(isSimulate) {
        currentProcessType = isSimulate ? 'simulation' : 'update';

        // Resetear variables de estado
        totalProductsToProcess = 0;
        totalSimulatedProducts = 0;
        totalUpdatedProducts = 0;
        totalBatches = 0;

        if (isSimulate) {
            isSimulationRunning = true;
            showSection('sim-process');
            simText.text('Iniciando simulación...');
        } else {
            isUpdateRunning = true;
            showSection('update-process');
            updateText.text('Iniciando actualización...');
            // Reset live results
            liveUpdated.text(0);
            liveSkipped.text(0);
            liveErrors.text(0);
        }

        // Desactivar botones principales
        simulateButton.prop('disabled', true).addClass('opacity-50');
        updateNowButton.prop('disabled', true).addClass('opacity-50');

        processBatch(0); // Empezar con el lote 0
    }
    
    /**
     * Llama a la API AJAX para procesar un lote.
     */
    function processBatch(batch) {
        if (currentProcessType === 'simulation' && !isSimulationRunning) return;
        if (currentProcessType === 'update' && !isUpdateRunning) return;

        const action = currentProcessType === 'simulation' ? 'dpuwoo_simulate_batch' : 'dpuwoo_update_batch';
        const nonce = dpuwoo_ajax.nonce; 

        $.ajax({
            url: dpuwoo_ajax.ajax_url,
            method: 'POST',
            data: {
                action: action,
                nonce: nonce,
                batch: batch
            },
            dataType: 'json',
            success: function (response) {
                console.group(`=== DPUWOO BATCH ${batch} RESPONSE ===`);
                
                if (response.success) {
                    const res = response.data;
                    const summary = res.summary || {};
                    
                    const batchInfo = res.batch_info || {};
                    const totalProducts = batchInfo.total_products || 0;
                    const totalBatchesRes = batchInfo.total_batches || 0;
                    const processedInBatch = batchInfo.processed_in_batch || 0;

                    // Inicializar el total de productos a procesar en el primer batch
                    if (batch === 0) {
                        totalProductsToProcess = totalProducts;
                        totalBatches = totalBatchesRes;
                        
                        if (currentProcessType === 'simulation') {
                            // Inicializar con array vacío para acumular todos los cambios
                            finalSimulationResults = { 
                                changes: [], 
                                rate: res.rate, 
                                baseline_rate: res.baseline_rate, 
                                percentage_change: res.percentage_change, 
                                summary: { 
                                    updated: 0, 
                                    errors: 0, 
                                    skipped: 0,
                                    total: totalProducts
                                },
                                all_batches_changes: [] // Para debug
                            };
                        } else {
                            finalUpdateResults = { 
                                changes: [], 
                                run_id: res.run_id, 
                                rate: res.rate, 
                                summary: { 
                                    updated: 0, 
                                    errors: 0, 
                                    skipped: 0,
                                    total: totalProducts
                                },
                                all_batches_changes: []
                            };
                        }
                    }

                    // Determinar si el proceso ha finalizado:
                    const isFinished = (batchInfo.current_batch >= totalBatches - 1) || (totalBatches === 0);

                    if (currentProcessType === 'simulation') {
                        // ====== ACUMULAR CAMBIOS DE TODOS LOS BATCHES ======
                        if (res.changes && res.changes.length > 0) {
                            
                            // Guardar una copia de este batch para debug
                            finalSimulationResults.all_batches_changes.push({
                                batch: batch,
                                changes: res.changes.slice() // Copia
                            });
                            
                            // ACUMULAR: Agregar al array de cambios TOTAL
                            finalSimulationResults.changes = finalSimulationResults.changes.concat(res.changes);
                            
                            // ACUMULAR: Sumar contadores del summary
                            finalSimulationResults.summary.updated += (summary.updated_count || 0);
                            finalSimulationResults.summary.errors += (summary.errors || 0);
                            finalSimulationResults.summary.skipped += (summary.skipped_count || 0);
                        }
                        
                        totalSimulatedProducts += processedInBatch;

                        // Actualizar UI de simulación
                        simTotalBatches.text(totalBatches);
                        simCurrentBatch.text(batch + 1);
                        simText.text(`Simulando precios - Procesando lote ${batch + 1} de ${totalBatches}...`);
                        updateProgressUI(totalSimulatedProducts, totalProductsToProcess, 'simulation');

                        
                        if (isFinished) {
                            console.groupEnd();
                            console.group("=== SIMULACIÓN COMPLETADA ===");
                            
                            // Contar tipos de productos FINALES
                            const tipos = {};
                            finalSimulationResults.changes.forEach(item => {
                                tipos[item.product_type] = (tipos[item.product_type] || 0) + 1;
                            });
                            
                            // Verificar padres variables
                            const padres = finalSimulationResults.changes.filter(item => 
                                item.product_type === 'variable' || item.status === 'parent'
                            );
                            
                            if (padres.length === 0) {
                                console.warn("⚠️ NO HAY PRODUCTOS VARIABLES PADRE - Crearemos automáticamente");
                            }
                            
                            console.groupEnd();
                            handleSimulationEnd(finalSimulationResults);
                        } else if (isSimulationRunning) {
                            console.groupEnd();
                            setTimeout(() => processBatch(batch + 1), 100); // Pequeño delay para evitar bloqueo
                        }
                    } else { 
                        // Actualización real - misma lógica para acumular
                        if (res.changes && res.changes.length > 0) {
                            finalUpdateResults.all_batches_changes.push({
                                batch: batch,
                                changes: res.changes.slice()
                            });
                            
                            finalUpdateResults.changes = finalUpdateResults.changes.concat(res.changes);
                            
                            finalUpdateResults.summary.updated += (summary.updated_count || 0);
                            finalUpdateResults.summary.errors += (summary.errors || 0);
                            finalUpdateResults.summary.skipped += (summary.skipped_count || 0);
                        }
                        
                        totalUpdatedProducts += processedInBatch;

                        // Actualizar UI de actualización
                        updateTotalBatches.text(totalBatches);
                        updateCurrentBatch.text(batch + 1);
                        updateText.text(`Actualizando precios - Aplicando lote ${batch + 1} de ${totalBatches}...`);
                        updateProgressUI(totalUpdatedProducts, totalProductsToProcess, 'update');

                        // Actualizar contadores en tiempo real
                        liveUpdated.text(parseInt(liveUpdated.text()) + (summary.updated_count || 0));
                        liveSkipped.text(parseInt(liveSkipped.text()) + (summary.skipped_count || 0));
                        liveErrors.text(parseInt(liveErrors.text()) + (summary.error_count || 0));

                        if (isFinished) {
                            console.groupEnd();
                            
                            handleUpdateEnd(finalUpdateResults);
                        } else if (isUpdateRunning) {
                            console.groupEnd();
                            setTimeout(() => processBatch(batch + 1), 100);
                        }
                    }
                } else {
                    console.error('❌ Error en la respuesta:', response.data);
                    console.groupEnd();
                    const errorMsg = response.data.message || response.data.error || 'Error desconocido al procesar el lote.';
                    handleError(errorMsg);
                }
            },
            error: function (xhr, status, error) {
                console.error('🔥 Error AJAX:', error);
                console.groupEnd();
                handleError('Error de red o servidor: ' + error);
            }
        });
    }

    // --- Manejo de Resultados y Errores ---

    /**
     * Finaliza la simulación y muestra los resultados.
     */
    function handleSimulationEnd(results) {
        isSimulationRunning = false;

        console.group("=== HANDLE SIMULATION END ===");
        
        // Verificar estructura de los datos
        if (!results.changes || results.changes.length === 0) {
            console.warn("⚠️ No hay cambios en los resultados");
            console.groupEnd();
            return;
        }
        
        // Contar tipos de productos
        const tipos = {};
        results.changes.forEach(item => {
            const tipo = item.product_type || 'desconocido';
            tipos[tipo] = (tipos[tipo] || 0) + 1;
        });
        
        // Buscar productos variables específicamente
        const productosVariables = results.changes.filter(item => 
            item.product_type === 'variable' || item.status === 'parent'
        );
        
        if (productosVariables.length === 0) {
            console.warn("⚠️ ADVERTENCIA: No hay productos variables en los resultados");
            // Buscar variaciones con parent_id para inferir padres
            const variaciones = results.changes.filter(item => 
                item.product_type === 'variation' && item.parent_id
            );
            const parentIds = [...new Set(variaciones.map(v => v.parent_id))];
        }
        
        console.groupEnd();

        // 1. Ocultar proceso y mostrar resultados
        showSection('sim-results');

        // 2. Generar resumen
        const summary = results.summary || {};
        const summaryHtml = `
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Resumen de la Simulación</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p><strong class="text-gray-700">Tasa de cambio aplicada:</strong> ${parseFloat(results.rate || 0).toFixed(4)}</p>
                        <p><strong class="text-gray-700">Tasa de referencia (Baseline):</strong> ${parseFloat(results.baseline_rate || 0).toFixed(4)}</p>
                        <p><strong class="text-gray-700">Cambio porcentual:</strong> <span class="${parseFloat(results.percentage_change || 0) > 0 ? 'text-red-600' : 'text-green-600'}">${parseFloat(results.percentage_change || 0).toFixed(2)}%</span></p>
                    </div>
                    <div>
                        <p><strong class="text-gray-700">Productos simulados:</strong> ${summary.total || totalProductsToProcess}</p>
                        <p><strong class="text-gray-700">Productos con cambio:</strong> <span class="text-green-600">${summary.updated || 0}</span></p>
                        <p><strong class="text-gray-700">Productos sin cambio:</strong> <span class="text-gray-600">${summary.skipped || 0}</span></p>
                        <p><strong class="text-gray-700">Errores:</strong> <span class="text-red-600">${summary.errors || 0}</span></p>
                    </div>
                </div>
            </div>
        `;
        $('#dpuwoo-sim-summary').html(summaryHtml);

        // 3. Generar tabla de resultados USANDO LA FUNCIÓN DE DPUWOO_Utils
        const tableHtml = DPUWOO_Utils.generateResultsTable(results.changes, true);
        simResultsTable.html(tableHtml);

        // 4. Configurar el acordeón (UX) usando la función de DPUWOO_Utils
        DPUWOO_Utils.setupVariationAccordion();

        // 5. Preparar el modal de confirmación de actualización
        $('#dpuwoo-confirm-summary').html(summaryHtml);

        // 6. Reactivar botones principales
        simulateButton.prop('disabled', false).removeClass('opacity-50');
        updateNowButton.prop('disabled', false).removeClass('opacity-50');
    }

    /**
     * Finaliza la actualización real y muestra los resultados finales.
     */
    function handleUpdateEnd(results) {
        isUpdateRunning = false;

        console.group("=== HANDLE UPDATE END ===");
        console.groupEnd();

        // 1. Ocultar proceso y mostrar resultados finales
        showSection('final-results');

        // 2. Usar la función de utilidad para mostrar resultados
        DPUWOO_Utils.showFinalResults(results, false);

        // 3. Reactivar botones principales
        simulateButton.prop('disabled', false).removeClass('opacity-50');
        updateNowButton.prop('disabled', false).removeClass('opacity-50');
    }

    /**
     * Maneja errores durante la ejecución.
     */
    function handleError(error) {
        isSimulationRunning = false;
        isUpdateRunning = false;

        // Ocultar procesos y mostrar un mensaje de error
        showSection('');

        const errorHtml = `
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-6" role="alert">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <strong class="font-bold">Error en la ejecución:</strong>
                </div>
                <span class="block sm:inline mt-2">${error}</span>
                <button onclick="window.location.reload();" class="mt-3 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Recargar Panel
                </button>
            </div>
        `;
        $('#dpuwoo-simulation-process').after(errorHtml);

        // Reactivar botones principales
        simulateButton.prop('disabled', false).removeClass('opacity-50');
        updateNowButton.prop('disabled', false).removeClass('opacity-50');
    }

    // --- Listeners de Eventos ---

    // 1. Botón de Iniciar Simulación
    simulateButton.on('click', function () {
        if (!isSimulationRunning) {
            startBatchProcess(true);
        }
    });

    // 2. Botón de Actualizar Directamente (Muestra Modal)
    updateNowButton.on('click', function () {
        directUpdateModal.removeClass('hidden');
    });

    // 3. Botón de Confirmar Actualización Directa (DENTRO DEL MODAL)
    $('#dpuwoo-direct-proceed').on('click', function () {
        directUpdateModal.addClass('hidden');
        if (!isUpdateRunning) {
            startBatchProcess(false);
        }
    });

    // 4. Botón de Cancelar Directa (DENTRO DEL MODAL)
    $('#dpuwoo-direct-cancel').on('click', function () {
        directUpdateModal.addClass('hidden');
    });

    // 5. Botón de Proceder Actualización (POST-SIMULACIÓN)
    $('#dpuwoo-proceed-update').on('click', function () {
        confirmUpdateModal.removeClass('hidden');
    });

    // 6. Botón de Confirmar Actualización (DENTRO DEL MODAL POST-SIMULACIÓN)
    $('#dpuwoo-confirm-proceed').on('click', function () {
        confirmUpdateModal.addClass('hidden');
        if (!isUpdateRunning) {
            startBatchProcess(false);
        }
    });

    // 7. Botón de Cancelar Confirmación (DENTRO DEL MODAL POST-SIMULACIÓN)
    $('#dpuwoo-confirm-cancel').on('click', function () {
        confirmUpdateModal.addClass('hidden');
    });

    // 8. Botón de Nueva Simulación (Reiniciar)
    $('#dpuwoo-new-simulation').on('click', function () {
        showSection(''); // Vuelve al estado inicial del dashboard
        finalSimulationResults = null;
        totalSimulatedProducts = 0;
        totalProductsToProcess = 0;
        simProgress.css('width', '0%');
        simPercent.text('0%');
    });

    // 9. Botón de Cancelar Simulación/Actualización
    $('#dpuwoo-cancel-simulation, #dpuwoo-cancel-update').on('click', function () {
        isSimulationRunning = false;
        isUpdateRunning = false;
        handleError('Proceso cancelado por el usuario.');
    });

    // --- Lógica de pestañas y carga de logs ---

    $('.dpuwoo-tab').on('click', function () {
        const targetTab = $(this).data('tab');
        $('.dpuwoo-tab-content').hide();
        $(`#dpuwoo-tab-${targetTab}`).show();

        $('.dpuwoo-tab').removeClass('border-blue-600 text-blue-600 font-semibold').addClass('border-transparent text-gray-500 hover:text-gray-700 font-medium');
        $(this).removeClass('border-transparent text-gray-500 hover:text-gray-700 font-medium').addClass('border-blue-600 text-blue-600 font-semibold');

        if (targetTab === 'logs') {
            // Placeholder para la carga de logs
        }
    });

    // Carga inicial (asegurar que el dashboard esté visible)
    $('#dpuwoo-tab-dashboard').show();

});