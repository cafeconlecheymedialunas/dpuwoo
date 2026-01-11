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
                
                $('#' + progressBarId).css('width', percent + '%');
                $('#' + percentId).text(percent + '%');
                $('#' + textId).text(text);

                const processed = cumulativeResults.updated + cumulativeResults.skipped + cumulativeResults.errors;
                const processedId = type === 'simulation' ? 'dpuwoo-sim-processed-products' : 'dpuwoo-update-processed-products';
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

            getStatusText: function (status, isSimulation = false) {
                const statusTexts = {
                    'updated': isSimulation ? 'Simulado' : 'Actualizado',
                    'simulated': 'Simulado',
                    'error': 'Error',
                    'skipped': 'Sin cambios',
                    'pending': 'Pendiente',
                    'parent': 'Producto variable'
                };
                return statusTexts[status] || status;
            },

            getStatusClass: function (status) {
                const statusClasses = {
                    'updated': 'bg-green-100 text-green-800',
                    'simulated': 'bg-blue-100 text-blue-800',
                    'error': 'bg-red-100 text-red-800',
                    'skipped': 'bg-gray-100 text-gray-800',
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'parent': 'bg-purple-100 text-purple-800 border border-purple-200'
                };
                return statusClasses[status] || 'bg-gray-100 text-gray-800';
            },

            /**
             * GENERAR RESUMEN (STATS)
             */

            generateCompleteResults: function (data, isSimulation = false) {
                const summary = data.summary || { updated: 0, skipped: 0, errors: 0, total: 0 };
                const changes = data.changes || [];
                
                // Un proceso es "Exitoso sin cambios" si no hay actualizaciones pero tampoco errores críticos
                const hasRealChanges = summary.updated > 0;
                const totalProcessed = (summary.updated || 0) + (summary.skipped || 0) + (summary.errors || 0);

                const oldRate = typeof dpuwoo_params !== 'undefined' ? dpuwoo_params.baseline_rate : '0.00';
                const newRate = data.rate || '0.00';
                
                // Determinar si el dólar cambió o es el mismo
                const rateChanged = parseFloat(oldRate) !== parseFloat(newRate);

                let html = `
            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-100">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mr-4">
                            ${hasRealChanges ? '🔄' : '✨'}
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">${isSimulation ? 'Simulación Finalizada' : 'Proceso Completado'}</h3>
                            <p class="text-gray-500 text-sm italic">${totalProcessed} productos analizados en total</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="block text-[10px] uppercase font-bold text-gray-400 tracking-wider">Estado del Sistema</span>
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold uppercase">Sincronizado</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <div class="flex items-center p-4 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="mr-4 text-gray-400 text-2xl">⏳</div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-500">Tasa Anterior</p>
                            <p class="text-2xl font-mono font-bold text-gray-700">$${parseFloat(oldRate).toFixed(2)}</p>
                        </div>
                    </div>
                    <div class="flex items-center p-4 bg-blue-600 rounded-xl shadow-md border border-blue-700">
                        <div class="mr-4 text-white opacity-80 text-2xl">📊</div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-blue-100">Tasa Aplicada</p>
                            <p class="text-2xl font-mono font-bold text-white">$${parseFloat(newRate).toFixed(2)}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-8">
                    <div class="p-3 text-center border border-gray-100 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600">${summary.updated || 0}</p>
                        <p class="text-[10px] uppercase text-gray-500 font-bold">${isSimulation ? 'A Modificar' : 'Actualizados'}</p>
                    </div>
                    <div class="p-3 text-center border border-gray-100 rounded-lg bg-gray-50">
                        <p class="text-2xl font-bold text-gray-400">${summary.skipped || 0}</p>
                        <p class="text-[10px] uppercase text-gray-500 font-bold">Sin Cambios</p>
                    </div>
                    <div class="p-3 text-center border border-gray-100 rounded-lg ${summary.errors > 0 ? 'bg-red-50' : ''}">
                        <p class="text-2xl font-bold ${summary.errors > 0 ? 'text-red-600' : 'text-gray-300'}">${summary.errors || 0}</p>
                        <p class="text-[10px] uppercase text-gray-500 font-bold">Errores</p>
                    </div>
                </div>`;

                if (!hasRealChanges) {
                    // MODO: TODO AL DÍA
                    html += `
                <div class="bg-blue-50 border border-blue-100 p-6 rounded-xl text-center">
                    <div class="inline-block p-3 bg-blue-100 text-blue-600 rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h4 class="text-lg font-bold text-blue-900 mb-2">Todo bajo control</h4>
                    <p class="text-blue-800 text-sm max-w-md mx-auto">
                        Tus precios ya están alineados con la tasa de <strong>$${parseFloat(newRate).toFixed(2)}</strong>. 
                        No se realizaron cambios porque los productos mantienen el valor correcto.
                    </p>
                    <div class="mt-6">
                        <button onclick="location.reload()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-bold shadow-sm">
                            Volver al Inicio
                        </button>
                    </div>
                </div>`;
                } else {
                    // MODO: HAY CAMBIOS (Tabla estándar)
                    html += `
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Detalle de Variaciones</h4>
                        <span class="px-2 py-1 bg-gray-100 text-gray-500 text-[10px] font-mono rounded">Ratio: ${parseFloat(data.ratio || 1).toFixed(4)}x</span>
                    </div>
                    ${this.generateResultsTable(changes, isSimulation)}
                </div>
                
                <div class="mt-8 pt-6 border-t border-gray-200 flex justify-between items-center">
                    <button onclick="location.reload()" class="text-gray-500 hover:text-gray-800 text-sm font-semibold transition">
                        ← Cancelar y volver
                    </button>
                    ${isSimulation ? `
                        <button id="dpuwoo-proceed-update" class="px-8 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all shadow-lg hover:shadow-xl font-bold flex items-center transform hover:-translate-y-1">
                            <span class="mr-2">🚀</span> Confirmar y Aplicar Cambios
                        </button>
                    ` : ''}
                </div>`;
                }

                html += `</div>`;
                return html;
            },

            /**
             * GENERAR TABLA CON RANGOS DIFERENCIADOS
             */
            generateResultsTable: function (changes, isSimulation = false) {
                if (!changes || changes.length === 0) return '<p class="p-4 text-gray-500">No hay cambios para mostrar.</p>';

                // 1. Agrupar variaciones bajo padres
                const enhancedChanges = [...changes];
                const parentIds = [...new Set(changes.filter(i => i.parent_id).map(i => parseInt(i.parent_id)))];
                
                parentIds.forEach(pId => {
                    if (!changes.some(i => parseInt(i.product_id) === pId)) {
                        const vars = changes.filter(i => parseInt(i.parent_id) === pId);
                        enhancedChanges.unshift({
                            product_id: pId,
                            product_name: vars[0].product_name.split(' - ')[0],
                            product_type: 'variable',
                            status: 'parent',
                            edit_link: `post.php?post=${pId}&action=edit`
                        });
                    }
                });

                const groups = enhancedChanges.reduce((acc, item) => {
                    const id = item.parent_id || item.product_id;
                    if (!acc[id]) acc[id] = { parent: null, variations: [] };
                    if (item.product_type === 'variation') acc[id].variations.push(item);
                    else acc[id].parent = item;
                    return acc;
                }, {});

                let html = `
                <div class="overflow-hidden rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-[10px] font-bold text-gray-500 uppercase">Producto / Variación</th>
                                <th class="px-6 py-3 text-center text-[10px] font-bold text-gray-500 uppercase">Precio Regular</th>
                                <th class="px-6 py-3 text-center text-[10px] font-bold text-gray-500 uppercase">Precio Oferta</th>
                                <th class="px-6 py-3 text-center text-[10px] font-bold text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-center text-[10px] font-bold text-gray-500 uppercase">Var. %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">`;

                Object.values(groups).forEach(group => {
                    const p = group.parent;
                    if (!p) return;
                    const hasVars = group.variations.length > 0;
                    
                    let regRange = '—', saleRange = '—';

                    if (hasVars) {
                        const rP = group.variations.map(v => parseFloat(v.new_regular_price || 0)).filter(n => n > 0);
                        const sP = group.variations.map(v => parseFloat(v.new_sale_price || 0)).filter(n => n > 0);
                        if (rP.length) {
                            const min = Math.min(...rP), max = Math.max(...rP);
                            regRange = min === max ? `$${min.toFixed(2)}` : `$${min.toFixed(2)} - $${max.toFixed(2)}`;
                        }
                        if (sP.length) {
                            const min = Math.min(...sP), max = Math.max(...sP);
                            saleRange = min === max ? `$${min.toFixed(2)}` : `$${min.toFixed(2)} - $${max.toFixed(2)}`;
                        }
                    } else {
                        regRange = p.new_regular_price > 0 ? `$${parseFloat(p.new_regular_price).toFixed(2)}` : '—';
                        saleRange = p.new_sale_price > 0 ? `$${parseFloat(p.new_sale_price).toFixed(2)}` : '—';
                    }

                    html += `
                    <tr class="dpuwoo-variable-product hover:bg-gray-50 transition-colors cursor-pointer" data-product-id="${p.product_id}">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                ${hasVars ? '<span class="dpuwoo-toggle-icon mr-3 text-purple-600 font-bold text-lg">⊕</span>' : '<span class="mr-3 text-gray-300">●</span>'}
                                <div>
                                    <div class="text-sm font-bold text-gray-900">${p.product_name}</div>
                                    <div class="text-[10px] text-gray-400">ID: ${p.product_id} | ${p.product_type.toUpperCase()}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded border border-gray-200">${regRange}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            ${saleRange !== '—' ? `<span class="text-xs font-mono bg-red-50 text-red-700 px-2 py-1 rounded border border-red-100 font-bold">${saleRange}</span>` : '<span class="text-gray-300">—</span>'}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${this.getStatusClass(p.status)}">${this.getStatusText(p.status, isSimulation)}</span>
                        </td>
                        <td class="px-6 py-4 text-center font-bold text-sm ${p.percentage_change >= 0 ? 'text-green-600' : 'text-red-600'}">
                            ${p.percentage_change ? p.percentage_change + '%' : '0%'}
                        </td>
                    </tr>`;

                    if (hasVars) {
                        html += `<tbody class="dpuwoo-variation-rows hidden bg-gray-50" data-parent-id="${p.product_id}">`;
                        group.variations.forEach(v => {
                            html += `
                            <tr class="border-l-4 border-purple-400">
                                <td class="px-6 py-2 pl-14 text-xs italic text-gray-500">└ ${v.variation_name || 'Variación'}</td>
                                <td class="px-6 py-2 text-center text-xs font-mono">$${parseFloat(v.new_regular_price).toFixed(2)}</td>
                                <td class="px-6 py-2 text-center text-xs font-mono text-red-500">${v.new_sale_price > 0 ? '$' + parseFloat(v.new_sale_price).toFixed(2) : '—'}</td>
                                <td class="px-6 py-2 text-center text-[9px] uppercase font-bold text-gray-400">${v.status}</td>
                                <td class="px-6 py-2 text-center text-xs font-bold text-gray-400">${v.percentage_change}%</td>
                            </tr>`;
                        });
                        html += `</tbody>`;
                    }
                });

                return html + `</tbody></table></div>`;
            },

            showCompleteResults: function (data, isSimulation) {
                const resultsHtml = this.generateCompleteResults(data, isSimulation);
                const target = isSimulation ? '#dpuwoo-simulation-results' : '#dpuwoo-final-results';
                $(target).html(resultsHtml);
                this.showSection(target.replace('#', ''));
                this.setupVariationAccordion();
                this.enableButtons();
            },

            setupVariationAccordion: function () {
                $(document).off('click', '.dpuwoo-variable-product').on('click', '.dpuwoo-variable-product', function () {
                    const id = $(this).data('product-id');
                    const $icon = $(this).find('.dpuwoo-toggle-icon');
                    const $rows = $(`.dpuwoo-variation-rows[data-parent-id="${id}"]`);
                    
                    $rows.toggleClass('hidden');
                    $icon.text($rows.hasClass('hidden') ? '⊕' : '⊖');
                    $(this).toggleClass('bg-purple-50');
                });
            }
        };

        // Exponer variables globales
        window.DPUWOO_Globals = { isProcessing, currentProcessType, cumulativeResults };

        // Inicializar módulos
        if (window.DPUWOO_Tabs) DPUWOO_Tabs.init();
        if (window.DPUWOO_Simulation) DPUWOO_Simulation.init();
        if (window.DPUWOO_Update) DPUWOO_Update.init();
    });

})(jQuery);