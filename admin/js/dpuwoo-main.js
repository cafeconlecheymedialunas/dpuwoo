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

    $(document).ready(function () {


        // Ocultar todas las secciones de proceso al cargar
        $('#dpuwoo-simulation-process').addClass('hidden');
        $('#dpuwoo-simulation-results').addClass('hidden');
        $('#dpuwoo-update-process').addClass('hidden');
        $('#dpuwoo-final-results').addClass('hidden');

        // Funciones de utilidad compartidas
        window.DPUWOO_Utils = {
            showSection: function (sectionId) {
                $('#' + sectionId).removeClass('hidden').addClass('block');
            },

            hideSection: function (sectionId) {
                $('#' + sectionId).removeClass('block').addClass('hidden');
            },

            resetAllSections: function () {
                this.hideSection('dpuwoo-simulation-process');
                this.hideSection('dpuwoo-simulation-results');
                this.hideSection('dpuwoo-update-process');
                this.hideSection('dpuwoo-final-results');

                $('#dpuwoo-sim-summary').empty();
                $('#dpuwoo-sim-results-table').empty();
                $('#dpuwoo-final-results').empty();

                cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };
            },

            enableButtons: function () {
                $('#dpuwoo-simulate, #dpuwoo-update-now').prop('disabled', false);
                isProcessing = false;
            },

            disableButtons: function () {
                $('#dpuwoo-simulate, #dpuwoo-update-now').prop('disabled', true);
                isProcessing = true;
            },

            updateProgressBar: function (type, current, total, text) {
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

            updateCumulativeResults: function (batchResults, type) {
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

            handleProcessError: function (message, type) {
                alert('Error en el proceso: ' + message);
                this.resetAllSections();
                this.enableButtons();
            },

            getStatusText: function (status) {
                const statusTexts = {
                    'updated': 'Actualizado',
                    'simulated': 'Simulado',
                    'error': 'Error',
                    'skipped': 'Sin cambios',
                    'pending': 'Pendiente'
                };
                return statusTexts[status] || status;
            },

            getStatusClass: function (status) {
                const statusClasses = {
                    'updated': 'bg-green-100 text-green-800',
                    'simulated': 'bg-blue-100 text-blue-800',
                    'error': 'bg-red-100 text-red-800',
                    'skipped': 'bg-gray-100 text-gray-800',
                    'pending': 'bg-yellow-100 text-yellow-800'
                };
                return statusClasses[status] || 'bg-gray-100 text-gray-800';
            },

            formatPrice: function (price) {
                if (!price || price === '0.00') return '-';
                return '$' + parseFloat(price).toFixed(2);
            },

            formatPriceRange: function (singlePrice, priceRange) {
                if (priceRange) {
                    return priceRange;
                }
                return singlePrice ? '$' + parseFloat(singlePrice).toFixed(2) : '$0';
            },

            // NUEVA FUNCIÓN: Generar tabla de resultados
            generateResultsTable: function (changes, isSimulation = false) {
        
    
                
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
                
                // Para cada parent_id, verificar si ya existe como producto variable
                parentIdsFromVariations.forEach(parentId => {
                    const parentExists = changes.some(item => 
                        parseInt(item.product_id) === parentId && 
                        (item.product_type === 'variable' || item.status === 'parent')
                    );
                    
                    if (!parentExists) {
                        
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
                        }
                    }
                });
                
                
                // === PASO 2: AGRUPAR LOS DATOS POR PRODUCTO PADRE ===
                const groupedChanges = enhancedChanges.reduce((acc, item) => {
                    const parentId = item.parent_id || item.product_id; 
                    
                    if (!acc[parentId]) {
                        acc[parentId] = { 
                            parentItem: null, 
                            variations: [],
                            isVariable: item.product_type === 'variable' || item.status === 'parent' || item.is_variable_parent
                        };
                    }

                    if (item.product_type === 'variation') {
                        acc[parentId].variations.push(item);
                    } else { 
                        // Si es el padre o un producto simple
                        acc[parentId].parentItem = item;
                    }

                    return acc;
                }, {});

                // === PASO 3: GENERAR HTML DE LA TABLA ===
                let html = `
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Producto / Variación
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Precio Regular
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Precio Oferta
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estado
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cambio %
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
        `;

                // === PASO 4: RENDERIZAR CADA GRUPO ===
                Object.keys(groupedChanges).forEach((key) => {
                    const group = groupedChanges[key];
                    const parent = group.parentItem;

                    if (parent) {
                        const isVariable = group.isVariable;
                        const hasVariations = group.variations.length > 0;
                        
                        // Estilo para la fila padre
                        let parentRowClass = '';
                        let statusClass = '';
                        let statusText = '';
                        
                        if (parent.status === 'updated' || parent.status === 'simulated') {
                            statusClass = 'bg-green-100 text-green-800';
                            statusText = isSimulation ? 'Simulado' : 'Actualizado';
                            parentRowClass = 'bg-green-50 hover:bg-green-100';
                        } else if (parent.status === 'skipped') {
                            statusClass = 'bg-gray-100 text-gray-800';
                            statusText = 'Sin cambios';
                            parentRowClass = 'bg-gray-50 hover:bg-gray-100';
                        } else if (parent.status === 'error') {
                            statusClass = 'bg-red-100 text-red-800';
                            statusText = `Error: ${parent.reason || 'Desconocido'}`;
                            parentRowClass = 'bg-red-50 hover:bg-red-100';
                        } else if (parent.status === 'parent') {
                            statusClass = 'bg-purple-100 text-purple-800';
                            statusText = 'Producto variable';
                            parentRowClass = 'bg-purple-50 hover:bg-purple-100';
                        }
                        
                        // Precios del padre
                        const oldPriceReg = parseFloat(parent.old_regular_price || 0).toFixed(2);
                        const oldPriceSale = parseFloat(parent.old_sale_price || 0).toFixed(2);
                        const newPriceReg = parseFloat(parent.new_regular_price || 0).toFixed(2);
                        const newPriceSale = parseFloat(parent.new_sale_price || 0).toFixed(2);
                        
                        const percentageChange = parseFloat(parent.percentage_change || 0).toFixed(2);
                        
                        // Fila del producto padre
                        html += `
                <tr class="${parentRowClass} transition-colors dpuwoo-variable-product" data-product-id="${parent.product_id}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-8 h-8 mr-3">
                                ${isVariable && hasVariations
                                ? `<span class="dpuwoo-toggle-icon cursor-pointer text-purple-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 5.326 5.7a.909.909 0 0 0 1.348 0L13 1"/>
                                        </svg>
                                    </span>`
                                : `<span class="text-gray-400">│</span>`
                            }
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="${parent.edit_link || '#'}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        ${parent.product_name || `Producto #${parent.product_id}`}
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500">
                                    SKU: ${parent.product_sku || 'N/A'} | 
                                    Tipo: ${parent.product_type || 'simple'}
                                    ${isVariable ? ` (${group.variations.length} variaciones)` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-500">${oldPriceReg !== '0.00' ? '$' + oldPriceReg : '—'}</div>
                        <div class="text-sm font-semibold text-gray-900">${newPriceReg !== '0.00' ? '$' + newPriceReg : '—'}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-500">${oldPriceSale !== '0.00' ? '$' + oldPriceSale : '—'}</div>
                        <div class="text-sm font-semibold text-gray-900">${newPriceSale !== '0.00' ? '$' + newPriceSale : '—'}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 py-1 text-xs rounded-full ${statusClass}">
                            ${statusText}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                        <span class="${percentageChange >= 0 ? 'text-green-600' : 'text-red-600'} font-medium">
                            ${percentageChange}%
                        </span>
                    </td>
                </tr>
                `;
                        
                        // Si es variable y tiene variaciones, agregar filas para variaciones
                        if (isVariable && hasVariations) {
                            html += `<tbody class="dpuwoo-variation-rows hidden" data-parent-id="${parent.product_id}">`;
                            
                            group.variations.forEach((variation, vIndex) => {
                                const vOldPriceReg = parseFloat(variation.old_regular_price || 0).toFixed(2);
                                const vOldPriceSale = parseFloat(variation.old_sale_price || 0).toFixed(2);
                                const vNewPriceReg = parseFloat(variation.new_regular_price || 0).toFixed(2);
                                const vNewPriceSale = parseFloat(variation.new_sale_price || 0).toFixed(2);
                                const vPercentageChange = parseFloat(variation.percentage_change || 0).toFixed(2);
                                
                                let vStatusClass = '';
                                let vStatusText = '';
                                
                                if (variation.status === 'updated' || variation.status === 'simulated') {
                                    vStatusClass = 'bg-green-100 text-green-800';
                                    vStatusText = isSimulation ? 'Simulado' : 'Actualizado';
                                } else if (variation.status === 'skipped') {
                                    vStatusClass = 'bg-gray-100 text-gray-800';
                                    vStatusText = 'Sin cambios';
                                } else if (variation.status === 'error') {
                                    vStatusClass = 'bg-red-100 text-red-800';
                                    vStatusText = `Error: ${variation.reason || 'Desconocido'}`;
                                }
                                
                                html += `
                        <tr class="bg-gray-50 hover:bg-gray-100">
                            <td class="px-6 py-3 whitespace-nowrap pl-16">
                                <div class="flex items-center">
                                    <div class="text-sm text-gray-700">
                                        <span class="text-gray-500">└─</span> ${variation.variation_name || variation.product_name || `Variación ${vIndex + 1}`}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-center">
                                <div class="text-sm text-gray-500">${vOldPriceReg !== '0.00' ? '$' + vOldPriceReg : '—'}</div>
                                <div class="text-sm font-medium text-gray-900">${vNewPriceReg !== '0.00' ? '$' + vNewPriceReg : '—'}</div>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-center">
                                <div class="text-sm text-gray-500">${vOldPriceSale !== '0.00' ? '$' + vOldPriceSale : '—'}</div>
                                <div class="text-sm font-medium text-gray-900">${vNewPriceSale !== '0.00' ? '$' + vNewPriceSale : '—'}</div>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs rounded-full ${vStatusClass}">
                                    ${vStatusText}
                                </span>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-center text-sm">
                                <span class="${vPercentageChange >= 0 ? 'text-green-600' : 'text-red-600'}">
                                    ${vPercentageChange}%
                                </span>
                            </td>
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
            },

            // NUEVA FUNCIÓN: Mostrar resultados finales
            showFinalResults: function (data, isSimulation) {
                const title = isSimulation ? 'Simulación Completada' : 'Actualización Completada';
                const bgColor = isSimulation ? 'bg-green-50 border-green-200' : 'bg-blue-50 border-blue-200';
                const icon = isSimulation ? '✅' : '🎉';
                const summary = data.summary || { updated: 0, skipped: 0, errors: 0, total: 0 };

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
                        <div class="text-2xl font-bold text-green-700">${summary.updated || 0}</div>
                        <div class="text-green-600">Actualizados</div>
                    </div>
                    <div class="text-center p-3 bg-gray-100 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700">${summary.skipped || 0}</div>
                        <div class="text-gray-600">Sin cambios</div>
                    </div>
                    <div class="text-center p-3 bg-red-100 rounded-lg">
                        <div class="text-2xl font-bold text-red-700">${summary.errors || 0}</div>
                        <div class="text-red-600">Errores</div>
                    </div>
                    <div class="text-center p-3 bg-blue-100 rounded-lg">
                        <div class="text-2xl font-bold text-blue-700">${data.rate ? '$' + parseFloat(data.rate).toFixed(4) : 'n/a'}</div>
                        <div class="text-blue-600">Dólar aplicado</div>
                    </div>
                </div>
                
                ${data.ratio ? `<p class="text-sm text-gray-600 mb-4"><strong>Ratio de ajuste:</strong> ${parseFloat(data.ratio).toFixed(4)}x</p>` : ''}
                
                ${!isSimulation && data.run_id ?
                        `<p class="text-sm text-gray-600 mb-4"><strong>ID de ejecución:</strong> ${data.run_id}</p>` :
                        ''
                    }
                
                <!-- Tabla de resultados -->
                <div class="mt-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">${isSimulation ? 'Cambios propuestos' : 'Precios actualizados'}</h4>
                    ${this.generateResultsTable(data.changes || [], isSimulation)}
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Volver al Dashboard
                    </button>
                </div>
            </div>
        `;

                $('#dpuwoo-final-results').html(resultsHtml);
                
                // Configurar el acordeón
                this.setupVariationAccordion();
                
                // Mostrar alerta de éxito si es actualización real
                if (!isSimulation && summary.updated > 0) {
                    this.showSuccessAlert(summary.updated, summary.errors);
                }
            },

            // NUEVA FUNCIÓN: Configurar acordeón
            setupVariationAccordion: function () {
                // Escuchar clics en las filas de productos variables
                $(document).on('click', '.dpuwoo-variable-product', function() {
                    const $parentRow = $(this);
                    const parentId = $parentRow.data('product-id');
                    const $variationRows = $(`.dpuwoo-variation-rows[data-parent-id="${parentId}"]`);
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
                            $parentRow.addClass('bg-purple-100');
                        } else {
                            // Colapsado: flecha hacia abajo
                            $toggleIcon.html(`
                                <svg class="w-4 h-4 text-purple-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 8">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 5.326 5.7a.909.909 0 0 0 1.348 0L13 1"/>
                                </svg>
                            `);
                            $parentRow.removeClass('bg-purple-100');
                        }
                    });
                });
            },

            // NUEVA FUNCIÓN: Mostrar alerta de éxito
            showSuccessAlert: function (updatedCount, errorCount) {
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
                    didOpen: function () {
                        setTimeout(function () {
                            $('html, body').animate({
                                scrollTop: $('#dpuwoo-final-results').offset().top - 100
                            }, 1000);
                        }, 500);
                    }
                });
            }
        };

        // Exponer variables globales para los módulos
        window.DPUWOO_Globals = {
            isProcessing: isProcessing,
            currentProcessType: currentProcessType,
            cumulativeResults: cumulativeResults
        };

        // Inicializar módulos
        if (window.DPUWOO_Tabs) DPUWOO_Tabs.init();
        if (window.DPUWOO_Logs) DPUWOO_Logs.init();
        if (window.DPUWOO_Simulation) DPUWOO_Simulation.init();
        if (window.DPUWOO_Update) DPUWOO_Update.init();
    });

})(jQuery);