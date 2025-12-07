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

                    console.log(`Batch info: ${batch}/${totalBatchesRes}, Procesados: ${processedInBatch}`);
                    console.log('Cambios en este batch:', res.changes?.length || 0);

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
                        
                        console.log(`Inicializado. Total productos: ${totalProducts}, Total batches: ${totalBatches}`);
                    }

                    // Determinar si el proceso ha finalizado:
                    const isFinished = (batchInfo.current_batch >= totalBatches - 1) || (totalBatches === 0);

                    if (currentProcessType === 'simulation') {
                        // ====== ACUMULAR CAMBIOS DE TODOS LOS BATCHES ======
                        if (res.changes && res.changes.length > 0) {
                            console.log(`Acumulando ${res.changes.length} cambios del batch ${batch}`);
                            
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
                            
                            console.log(`Total acumulado hasta ahora: ${finalSimulationResults.changes.length} cambios`);
                        }
                        
                        totalSimulatedProducts += processedInBatch;

                        // Actualizar UI de simulación
                        simTotalBatches.text(totalBatches);
                        simCurrentBatch.text(batch + 1);
                        simText.text(`Simulando precios - Procesando lote ${batch + 1} de ${totalBatches}...`);
                        updateProgressUI(totalSimulatedProducts, totalProductsToProcess, 'simulation');

                        // Debug final del batch
                        console.log(`Batch ${batch} completado. Acumulados: ${finalSimulationResults.changes.length}`);
                        
                        if (isFinished) {
                            console.groupEnd();
                            console.group("=== SIMULACIÓN COMPLETADA ===");
                            console.log("Total batches procesados:", totalBatches);
                            console.log("Total cambios acumulados:", finalSimulationResults.changes.length);
                            
                            // Contar tipos de productos FINALES
                            const tipos = {};
                            finalSimulationResults.changes.forEach(item => {
                                tipos[item.product_type] = (tipos[item.product_type] || 0) + 1;
                            });
                            console.log("Distribución FINAL por tipo:", tipos);
                            
                            // Verificar padres variables
                            const padres = finalSimulationResults.changes.filter(item => 
                                item.product_type === 'variable' || item.status === 'parent'
                            );
                            console.log("Productos variables/padres encontrados:", padres.length);
                            
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
                            console.log("=== ACTUALIZACIÓN FINALIZADA ===");
                            console.log("Total cambios finales:", finalUpdateResults.changes.length);
                            
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

    // --- Lógica del Acordeón para la UX ---

    /**
     * Configura el manejador de clics para desplegar/colapsar las variaciones.
     * Se llama después de renderizar la tabla.
     */
    function setupVariationAccordion() {
        // Desvincular eventos anteriores para evitar duplicados si la tabla se recarga
        $('#dpuwoo-sim-results-table, #dpuwoo-final-results-table').off('click', '.dpuwoo-variable-product');

        // Escuchar clics en la fila del producto variable
        $('#dpuwoo-sim-results-table, #dpuwoo-final-results-table').on('click', '.dpuwoo-variable-product', function() {
            const $parentRow = $(this);
            const parentId = $parentRow.data('product-id');
            const $variationRows = $(`tbody.dpuwoo-variation-rows[data-parent-id="${parentId}"]`);
            const $toggleIcon = $parentRow.find('.dpuwoo-toggle-icon');

            // Solo actuar si hay variaciones
            if ($variationRows.length === 0) {
                return;
            }

            // Toggle (Mostrar/Ocultar) las filas de las variaciones con animación suave
            $variationRows.slideToggle(150, function() {
                // Cambiar el SVG para indicar el estado
                if ($variationRows.is(':visible')) {
                    // Expandido: flecha hacia arriba
                    $toggleIcon.html(`
                        <svg class="w-4 h-4 text-purple-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 8">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7 7.674 1.3a.91.91 0 0 0-1.348 0L1 7"/>
                        </svg>
                    `);
                } else {
                    // Colapsado: flecha hacia abajo
                    $toggleIcon.html(`
                        <svg class="w-4 h-4 text-purple-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 8">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 5.326 5.7a.909.909 0 0 0 1.348 0L13 1"/>
                        </svg>
                    `);
                }
                
                // Cambiar el color de fondo cuando se expande
                $parentRow.toggleClass('bg-purple-100', $variationRows.is(':visible'));
            });
        });
    }

    // --- Manejo de Resultados y Errores ---

    /**
     * Finaliza la simulación y muestra los resultados.
     */
    function handleSimulationEnd(results) {
        isSimulationRunning = false;

        console.group("=== HANDLE SIMULATION END ===");
        console.log("Resultados recibidos:", results);
        console.log("Total cambios en results.changes:", results.changes.length);
        
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
        console.log("Distribución por tipo de producto:", tipos);
        
        // Buscar productos variables específicamente
        const productosVariables = results.changes.filter(item => 
            item.product_type === 'variable' || item.status === 'parent'
        );
        console.log("Productos variables/padres encontrados:", productosVariables.length);
        
        if (productosVariables.length === 0) {
            console.warn("⚠️ ADVERTENCIA: No hay productos variables en los resultados");
            // Buscar variaciones con parent_id para inferir padres
            const variaciones = results.changes.filter(item => 
                item.product_type === 'variation' && item.parent_id
            );
            const parentIds = [...new Set(variaciones.map(v => v.parent_id))];
            console.log(`Parent IDs inferidos de ${variaciones.length} variaciones:`, parentIds.length);
            console.log("Ejemplo de parent IDs:", parentIds.slice(0, 5));
        } else {
            console.log("Ejemplo de productos variables:");
            productosVariables.slice(0, 3).forEach((padre, i) => {
                console.log(`${i+1}. ID: ${padre.product_id}, Nombre: ${padre.product_name}, Variaciones: ${padre.variations_count || 'N/A'}`);
            });
        }
        
        // Mostrar primeros 5 cambios para debug
        console.log("Primeros 5 cambios:");
        results.changes.slice(0, 5).forEach((item, i) => {
            console.log(`${i+1}. ID: ${item.product_id}, Tipo: ${item.product_type}, Parent: ${item.parent_id || 'N/A'}, Nombre: ${item.product_name?.substring(0, 30)}...`);
        });
        
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

        // 3. Generar tabla de resultados
        const tableHtml = generateResultsTable(results.changes);
        simResultsTable.html(tableHtml);

        // 4. Configurar el acordeón (UX)
        setupVariationAccordion();

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
        console.log("Resultados de actualización:", results);
        console.log("Total cambios:", results.changes?.length || 0);
        console.groupEnd();

        // 1. Ocultar proceso y mostrar resultados finales
        showSection('final-results');

        // 2. Generar resumen y tabla
        const summary = results.summary || {};
        const finalHtml = `
            <div class="bg-white shadow rounded-xl p-6 border border-blue-200 mb-6">
                <div class="flex items-center mb-6">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Actualización Completada</h3>
                        <p class="text-gray-600 text-sm">Los precios han sido modificados en WooCommerce. Registro de corrida ID: <strong>#${results.run_id || 'N/A'}</strong></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 text-center">
                    <div class="p-4 bg-green-50 rounded-lg border border-green-100">
                        <div class="text-2xl font-bold text-green-700">${summary.updated || 0}</div>
                        <div class="text-sm text-green-600">Productos Actualizados</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-100">
                        <div class="text-2xl font-bold text-gray-700">${summary.skipped || 0}</div>
                        <div class="text-sm text-gray-600">Productos Sin Cambios</div>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg border border-red-100">
                        <div class="text-2xl font-bold text-red-700">${summary.errors || 0}</div>
                        <div class="text-sm text-red-600">Errores</div>
                    </div>
                </div>
                
                <div class="mt-4 space-y-2">
                    <p><strong>Tasa de cambio aplicada:</strong> ${parseFloat(results.rate || 0).toFixed(4)}</p>
                    <p><strong>Total de Productos Procesados:</strong> ${summary.total || totalProductsToProcess}</p>
                </div>

                <button onclick="window.location.reload();"
                    class="mt-6 px-6 py-3 bg-gray-600 text-white rounded-lg shadow hover:bg-gray-700 transition flex items-center font-medium">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Volver al Dashboard
                </button>
            </div>
            <div class="mt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Detalle de Cambios</h3>
                ${generateResultsTable(results.changes)}
            </div>
        `;
        $('#dpuwoo-final-results').html(finalHtml);

        // 3. Configurar el acordeón (UX) para los resultados finales
        setupVariationAccordion();

        // 4. Reactivar botones principales
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

    /**
     * Genera la tabla HTML para mostrar los resultados (simulación o final),
     * implementando el acordeón para las variaciones.
     */
    function generateResultsTable(changes) {
        console.group("=== GENERATE RESULTS TABLE ===");
        console.log("Total cambios recibidos:", changes.length);
        
        if (!changes || changes.length === 0) {
            console.groupEnd();
            return '<div class="text-center p-8 text-gray-500"><p>No se encontraron productos para el cálculo.</p></div>';
        }

        // === PASO 1: CREAR PADRES FALTANTES AUTOMÁTICAMENTE ===
        const enhancedChanges = [...changes]; // Copia para no modificar el original
        
        // Identificar todos los parent_id únicos de las variaciones
        const parentIdsFromVariations = [...new Set(
            changes
                .filter(item => item.product_type === 'variation' && item.parent_id)
                .map(item => parseInt(item.parent_id))
        )];
        
        console.log("Parent IDs encontrados en variaciones:", parentIdsFromVariations.length);
        console.log("Ejemplo:", parentIdsFromVariations.slice(0, 5));
        
        // Para cada parent_id, verificar si ya existe como producto variable
        parentIdsFromVariations.forEach(parentId => {
            const parentExists = changes.some(item => 
                parseInt(item.product_id) === parentId && 
                (item.product_type === 'variable' || item.status === 'parent')
            );
            
            if (!parentExists) {
                console.log(`🚨 Creando padre faltante para ID: ${parentId}`);
                
                // Buscar todas las variaciones de este padre
                const variacionesDelPadre = changes.filter(item => 
                    parseInt(item.parent_id) === parentId
                );
                
                if (variacionesDelPadre.length > 0) {
                    // Usar la primera variación para obtener información del padre
                    const primeraVariacion = variacionesDelPadre[0];
                    
                    // Extraer nombre base del producto (remover tamaño/color)
                    let nombreBase = primeraVariacion.product_name || '';
                    const guionIndex = nombreBase.lastIndexOf(' - ');
                    if (guionIndex > -1) {
                        nombreBase = nombreBase.substring(0, guionIndex);
                    }
                    
                    // Contar cuántas variaciones tienen cada estado
                    const estados = {
                        simulated: 0,
                        updated: 0,
                        skipped: 0,
                        error: 0
                    };
                    
                    variacionesDelPadre.forEach(variacion => {
                        if (estados.hasOwnProperty(variacion.status)) {
                            estados[variacion.status]++;
                        }
                    });
                    
                    // Determinar status del padre basado en sus variaciones
                    let statusPadre = 'parent';
                    if (estados.simulated > 0) statusPadre = 'simulated';
                    if (estados.updated > 0) statusPadre = 'updated';
                    
                    // Crear el padre
                    const padre = {
                        product_id: parentId,
                        product_name: nombreBase || `Producto #${parentId}`,
                        product_sku: 'N/A',
                        product_type: 'variable',
                        old_regular_price: 0,
                        new_regular_price: 0,
                        old_sale_price: 0,
                        new_sale_price: 0,
                        base_price: 0,
                        percentage_change: 0,
                        status: statusPadre,
                        reason: 'Producto variable',
                        parent_id: parentId,
                        edit_link: `post.php?post=${parentId}&action=edit`,
                        is_variable_parent: true,
                        variations_count: variacionesDelPadre.length,
                        variations_status: estados
                    };
                    
                    // Insertar el padre al inicio del array
                    enhancedChanges.unshift(padre);
                    console.log(`✅ Padre creado: ID ${parentId}, Nombre: "${nombreBase.substring(0, 30)}...", Variaciones: ${variacionesDelPadre.length}`);
                }
            }
        });
        
        console.log(`Total cambios después de crear padres: ${enhancedChanges.length} (antes: ${changes.length})`);
        
        // === PASO 2: AGRUPAR LOS DATOS POR PRODUCTO PADRE ===
        const groupedChanges = enhancedChanges.reduce((acc, item) => {
            const parentId = item.parent_id || item.product_id; 
            
            if (!acc[parentId]) {
                acc[parentId] = { parentItem: null, variations: [] };
            }

            if (item.product_type === 'variation') {
                acc[parentId].variations.push(item);
            } else { 
                // Si es el padre o un producto simple
                acc[parentId].parentItem = item;
            }

            return acc;
        }, {});
        
        console.log("Grupos creados:", Object.keys(groupedChanges).length);
        console.log("Ejemplo de grupos:");
        Object.keys(groupedChanges).slice(0, 3).forEach((key, index) => {
            const group = groupedChanges[key];
            console.log(`${index+1}. Grupo ${key}: Padre=${group.parentItem?.product_id || 'No'}, Variaciones=${group.variations.length}`);
        });

        // === PASO 3: GENERAR HTML DE LA TABLA ===
        let html = `
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto / Variación</th>
                        
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">REGULAR (Antes)</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">OFERTA (Antes)</th>
                        
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">REGULAR (Nuevo)</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">OFERTA (Nuevo)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
        `;

        // === PASO 4: RENDERIZAR CADA GRUPO ===
        Object.keys(groupedChanges).forEach(key => {
            const group = groupedChanges[key];
            const parent = group.parentItem;

            if (parent) {
                const isVariable = parent.product_type === 'variable' || parent.is_variable_parent;
                const hasVariations = group.variations.length > 0;
                
                // Determinar precios para mostrar
                let oldRegularText = '—';
                let oldSaleText = '—';
                let newRegularText = '—';
                let newSaleText = '—';
                
                if (!isVariable) {
                    // Producto simple: mostrar precios reales
                    oldRegularText = parseFloat(parent.old_regular_price || 0).toFixed(2);
                    oldSaleText = parseFloat(parent.old_sale_price || 0) > 0 ? parseFloat(parent.old_sale_price).toFixed(2) : '—';
                    newRegularText = parseFloat(parent.new_regular_price || 0).toFixed(2);
                    newSaleText = parseFloat(parent.new_sale_price || 0) > 0 ? parseFloat(parent.new_sale_price).toFixed(2) : '—';
                }
                
                // Determinar etiquetas y estilos según tipo
                let statusText = '';
                let rowClasses = '';
                let bgColorClass = '';
                let toggleIcon = '<span class="mr-2 text-xl font-bold align-middle text-gray-300">│</span>';
                
                if (isVariable) {
                  
                    statusText = `<span class="text-purple-600 font-semibold">${group.variations.length} Variaciones</span>`;
                    bgColorClass = 'bg-purple-50';
                    
                    if (hasVariations) {
                        // SVG inicial: flecha hacia abajo (colapsado)
                        toggleIcon = `
                            <span class="dpuwoo-toggle-icon mr-2 text-xl font-bold align-middle cursor-pointer">
                                <svg class="w-4 h-4 text-purple-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 8">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 5.326 5.7a.909.909 0 0 0 1.348 0L13 1"/>
                                </svg>
                            </span>
                        `;
                        rowClasses = 'cursor-pointer hover:bg-purple-100 transition dpuwoo-variable-product';
                    }
                } else {
                
                    statusText = parent.status || 'N/A';
                    bgColorClass = 'bg-blue-50';
                    
                    
                }
                
                // Fila del padre
                html += `
                <tr class="${bgColorClass} font-bold border-t-2 border-gray-300 ${rowClasses}" 
                    data-product-id="${parent.product_id}">
                    
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">
                        <a href="${parent.edit_link || '#'}" target="_blank" class="text-blue-600 hover:text-blue-800 flex items-center">
                            <span class="truncate max-w-xs">${parent.product_name || 'Producto Desconocido'}</span>
                        </a>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-500 font-mono">${oldRegularText}</td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-500 font-mono">${oldSaleText}</td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-900 font-mono font-semibold">${newRegularText}</td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-900 font-mono font-semibold">${newSaleText}</td>
                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                        ${toggleIcon}
                    </td>
                </tr>
                `;
                
                // === VARIACIONES (solo para productos variables) ===
                if (isVariable && hasVariations) {
                    html += `<tbody class="dpuwoo-variation-rows hidden" data-parent-id="${parent.product_id}">`;
                    
                    group.variations.forEach(item => {
                        const statusClass = item.status === 'simulated' || item.status === 'updated'
                            ? 'text-green-600'
                            : item.status === 'skipped'
                                ? 'text-gray-500'
                                : 'text-red-600';
                        
                        const basePrice = parseFloat(item.base_price || 0).toFixed(2);
                        const oldPriceReg = parseFloat(item.old_regular_price || 0).toFixed(2);
                        const oldPriceSale = parseFloat(item.old_sale_price || 0).toFixed(2);
                        
                        const newPriceReg = parseFloat(item.new_regular_price || 0);
                        const newPriceSale = parseFloat(item.new_sale_price || 0);

                        const newPriceRegText = newPriceReg > 0 ? newPriceReg.toFixed(2) : '—';
                        const newPriceSaleText = newPriceSale > 0 ? newPriceSale.toFixed(2) : '—';
                        
                        const statusText = item.status === 'simulated' ? 'Simulado' : 
                                          item.status === 'updated' ? 'Actualizado' : 
                                          item.status === 'skipped' ? 'Sin cambio' : 
                                          item.status === 'error' ? `Error: ${item.reason || 'Desconocido'}` : 
                                          item.status || 'N/A';

                        html += `
                        <tr class="text-xs hover:bg-purple-50 transition-all">
                           
                            <td class="px-6 py-2 whitespace-nowrap text-gray-700 pl-10">
                                <div class="flex items-center">
                                    <span class="truncate max-w-xs">${item.variation_name || item.product_name || 'Variación'}</span>
                                </div>
                            </td>
                            
                            <td class="px-6 py-2 whitespace-nowrap text-right text-gray-500 font-mono">${oldPriceReg}</td>
                            <td class="px-6 py-2 whitespace-nowrap text-right text-gray-500 font-mono">${oldPriceSale}</td>
                            
                            <td class="px-6 py-2 whitespace-nowrap text-right text-gray-900 font-mono font-semibold">${newPriceRegText}</td>
                            <td class="px-6 py-2 whitespace-nowrap text-right text-gray-900 font-mono font-semibold">${newPriceSaleText}</td>
                        </tr>
                        `;
                    });
                    
                    html += `</tbody>`;
                }
            }
        });

        html += `
                </tbody>
            </table>
        </div>
        `;
        
        console.groupEnd();
        return html;
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