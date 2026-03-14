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
                $('#dpuwoo-simulate, #dpuwoo-update-now, #dpuwoo-initialize-baseline').prop('disabled', false);
                isProcessing = false;
            },

            disableButtons: function () {
                $('#dpuwoo-simulate, #dpuwoo-update-now, #dpuwoo-initialize-baseline').prop('disabled', true);
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
                    'updated':   'dpu-status-updated',
                    'simulated': 'dpu-status-simulated',
                    'error':     'dpu-status-error',
                    'skipped':   'dpu-status-skipped',
                    'pending':   'dpu-status-skipped',
                    'parent':    'dpu-status-parent'
                };
                return statusClasses[status] || 'dpu-status-skipped';
            },

            /**
             * GENERAR RESUMEN (STATS)
             */

            generateCompleteResults: function (data, isSimulation = false) {
                const summary = data.summary || { updated: 0, skipped: 0, errors: 0, total: 0 };
                const changes = data.changes || [];

                // Umbrales: desde la respuesta (threshold bloqueado) o desde settings localizados (threshold pasado)
                const thresholdMin = data.threshold_min !== undefined
                    ? parseFloat(data.threshold_min)
                    : parseFloat(dpuwoo_ajax.threshold_min ?? 0);
                const thresholdMax = data.threshold_max !== undefined
                    ? parseFloat(data.threshold_max)
                    : parseFloat(dpuwoo_ajax.threshold_max ?? 0);
                const thresholdMet = data.threshold_met !== undefined ? data.threshold_met : true;
                const blockMessage = data.message || '';
                const percentageChange = Math.abs(data.percentage_change || 0);

                // Para simulación, hay cambios si hay elementos en "changes" con status "simulated"
                // En actualización real, hay cambios si summary.updated > 0
                const hasRealChanges = isSimulation
                    ? changes.some(item => item.status === 'simulated' || item.status === 'updated')
                    : (summary.updated || 0) > 0;

                const totalProcessed = (summary.updated || 0) + (summary.skipped || 0) + (summary.errors || 0);

                const oldRate = data.previous_rate || 1;
                const newRate = data.rate || 1;

                // Determinar si el dólar cambió o es el mismo
                const rateChanged = parseFloat(oldRate) !== parseFloat(newRate);

                // Contar productos simulados para mostrar correctamente
                const simulatedCount = changes.filter(item =>
                    item.status === 'simulated' || item.status === 'updated'
                ).length;

                const mainCount = isSimulation ? simulatedCount : (summary.updated || 0);
                const mainLabel = isSimulation ? 'A modificar' : 'Actualizados';
                const rateBoxNewClass = isSimulation ? 'dpu-rate-box--sim' : 'dpu-rate-box--upd';
                const shouldShowTable = hasRealChanges && thresholdMet;

                let html = `
    <div class="dpu-result-card dpu-card">
        <div class="dpu-result-header ${isSimulation ? 'dpu-result-header--sim' : 'dpu-result-header--upd'}">
            <div class="dpu-result-header__left">
                <div class="dpu-result-header__icon">
                    ${hasRealChanges
                        ? `<svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>`
                        : `<svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`}
                </div>
                <div>
                    <p class="dpu-result-header__title">${isSimulation ? 'Simulación finalizada' : 'Actualización completada'}</p>
                    <p class="dpu-result-header__sub">${changes.length} productos analizados</p>
                </div>
            </div>
            <span class="dpu-sync-badge">${isSimulation ? 'Vista previa' : 'Guardado'}</span>
        </div>

        <div class="dpu-result-body">
            <div class="dpu-rate-grid">
                <div class="dpu-rate-box dpu-rate-box--old">
                    <span class="dpu-rate-box__label">Tasa anterior</span>
                    <span class="dpu-rate-box__value">$${parseFloat(oldRate).toFixed(2)}</span>
                </div>
                <div class="dpu-rate-box ${rateBoxNewClass}">
                    <span class="dpu-rate-box__label">${isSimulation ? 'Tasa a aplicar' : 'Tasa aplicada'}</span>
                    <span class="dpu-rate-box__value">$${parseFloat(newRate).toFixed(2)}</span>
                </div>
            </div>

            <div class="dpu-threshold-row">
                <span class="dpu-threshold-row__label">Variación</span>
                <span class="dpu-threshold-row__pct">${(data.percentage_change || 0) >= 0 ? '+' : ''}${(data.percentage_change || 0).toFixed(2)}%</span>
                <span class="dpu-threshold-row__sep">·</span>
                <span class="dpu-threshold-row__check ${thresholdMet ? 'dpu-threshold-row__check--ok' : 'dpu-threshold-row__check--fail'}">
                    ${thresholdMet
                        ? (thresholdMin === 0 && thresholdMax === 0
                            ? 'Sin umbral · Actualiza siempre'
                            : `Umbral mín. ${thresholdMin}% · Alcanzado`)
                        : (thresholdMax > 0 && percentageChange > thresholdMax
                            ? `Umbral máx. ${thresholdMax}% · Superado`
                            : `Umbral mín. ${thresholdMin}% · No alcanzado`)
                    }
                </span>
            </div>

            <div class="dpu-counters-grid">
                <div class="dpu-counter ${mainCount > 0 ? (isSimulation ? 'dpu-counter--sim' : 'dpu-counter--upd') : ''}">
                    <span class="dpu-counter__num">${mainCount}</span>
                    <span class="dpu-counter__label">${mainLabel}</span>
                </div>
                <div class="dpu-counter">
                    <span class="dpu-counter__num dpu-counter__num--skip">${summary.skipped || 0}</span>
                    <span class="dpu-counter__label">Sin cambios</span>
                </div>
                <div class="dpu-counter ${(summary.errors || 0) > 0 ? 'dpu-counter--err' : ''}">
                    <span class="dpu-counter__num ${(summary.errors || 0) > 0 ? 'dpu-counter__num--err' : 'dpu-counter__num--zero'}">${summary.errors || 0}</span>
                    <span class="dpu-counter__label">Errores</span>
                </div>
            </div>`;

                if (!shouldShowTable && !thresholdMet) {
                    // MODO: UMBRAL NO ALCANZADO
                    const blockTitle = thresholdMax > 0 && percentageChange > thresholdMax
                        ? 'Umbral máximo superado'
                        : 'Umbral mínimo no alcanzado';
                    const blockBody = blockMessage
                        || (thresholdMax > 0 && percentageChange > thresholdMax
                            ? `La variación del ${percentageChange.toFixed(2)}% supera el umbral máximo permitido del ${thresholdMax}%. No se realizaron cambios por seguridad.`
                            : `La variación del ${percentageChange.toFixed(2)}% no alcanza el umbral mínimo configurado del ${thresholdMin}%. No se realizaron cambios.`);
                    html += `
            <div class="dpu-message-block dpu-message-block--warn">
                <div class="dpu-message-block__icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h4 class="dpu-message-block__title">${blockTitle}</h4>
                <p class="dpu-message-block__body">${blockBody}</p>
                <p class="dpu-message-block__meta">
                    Tasa actual: <strong>$${parseFloat(newRate).toFixed(2)}</strong> &nbsp;·&nbsp;
                    Variación: <strong>${(data.percentage_change || 0) >= 0 ? '+' : ''}${(data.percentage_change || 0).toFixed(2)}%</strong>
                </p>
                <button onclick="location.reload()" class="dpu-btn dpu-btn--ghost">Volver al inicio</button>
            </div>`;
                } else if (shouldShowTable) {
                    // MODO: MOSTRAR TABLA (hay cambios y umbral alcanzado)
                    html += `
            <div class="dpu-table-section">
                <div class="dpu-table-section__header">
                    <span class="dpu-table-section__title">Detalle de variaciones</span>
                    <span class="dpu-table-section__meta">Ratio: ${parseFloat(data.ratio || 1).toFixed(4)}×</span>
                </div>
                ${this.generateResultsTable(changes, isSimulation)}
            </div>
            <div class="dpu-result-footer">
                <button onclick="location.reload()" class="dpu-btn dpu-btn--ghost">← Cancelar y volver</button>
                ${isSimulation ? `<button id="dpuwoo-proceed-update" class="dpu-btn dpu-btn--update">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Confirmar y aplicar cambios
                </button>` : ''}
            </div>`;
                } else {
                    // MODO: SIN CAMBIOS (umbral alcanzado pero 0 productos modificados)
                    html += `
            <div class="dpu-no-changes">
                <p class="dpu-no-changes__label">Sin cambios de precio detectados</p>
                <p class="dpu-no-changes__detail">
                    ${summary.skipped > 0
                        ? `${summary.skipped} producto${summary.skipped !== 1 ? 's' : ''} procesado${summary.skipped !== 1 ? 's' : ''}, ninguno requirió ajuste a la tasa <strong>$${parseFloat(newRate).toFixed(2)}</strong>.`
                        : `Ningún producto requirió ajuste a la tasa <strong>$${parseFloat(newRate).toFixed(2)}</strong>.`
                    }
                </p>
                <button onclick="location.reload()" class="dpu-btn dpu-btn--ghost">Volver al inicio</button>
            </div>`;
                }

                html += `</div></div>`;
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
                <div class="dpu-results-table-wrap">
                    <table class="dpu-results-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio regular</th>
                                <th>Precio oferta</th>
                                <th>Estado</th>
                                <th>Var. %</th>
                            </tr>
                        </thead>
                        <tbody>`;

                Object.values(groups).forEach(group => {
                    const p = group.parent;
                    if (!p) return;
                    const hasVars = group.variations.length > 0;

                    let regCell = '<span class="dpu-tbl-empty-val">—</span>';
                    let saleCell = '<span class="dpu-tbl-empty-val">—</span>';
                    let hasSale = false;

                    if (hasVars) {
                        const rOld = group.variations.map(v => parseFloat(v.old_regular_price || 0)).filter(n => n > 0);
                        const rNew = group.variations.map(v => parseFloat(v.new_regular_price || 0)).filter(n => n > 0);
                        const sNew = group.variations.map(v => parseFloat(v.new_sale_price  || 0)).filter(n => n > 0);
                        if (rNew.length) {
                            const oMin = rOld.length ? Math.min(...rOld) : 0, oMax = rOld.length ? Math.max(...rOld) : 0;
                            const nMin = Math.min(...rNew), nMax = Math.max(...rNew);
                            const oldStr = rOld.length ? (oMin === oMax ? `$${oMin.toFixed(2)}` : `$${oMin.toFixed(2)} – $${oMax.toFixed(2)}`) : '';
                            const newStr = nMin === nMax ? `$${nMin.toFixed(2)}` : `$${nMin.toFixed(2)} – $${nMax.toFixed(2)}`;
                            const dir = nMin >= oMin ? 'up' : 'down';
                            regCell = oldStr
                                ? `<span class="dpu-tbl-old-price">${oldStr}</span><span class="dpu-tbl-price-arrow dpu-tbl-price-arrow--${dir}">→</span><span class="dpu-tbl-new-price--${dir}">${newStr}</span>`
                                : newStr;
                        }
                        if (sNew.length) {
                            hasSale = true;
                            saleCell = `<span class="dpu-tbl-new-price--up">$${Math.min(...sNew).toFixed(2)}${sNew.length > 1 && Math.min(...sNew) !== Math.max(...sNew) ? ' – $' + Math.max(...sNew).toFixed(2) : ''}</span>`;
                        }
                    } else {
                        regCell  = this.formatPriceChange(p.old_regular_price, p.new_regular_price);
                        saleCell = this.formatPriceChange(p.old_sale_price,    p.new_sale_price);
                        hasSale  = p.new_sale_price > 0;
                    }

                    const pct = parseFloat(p.percentage_change || 0);
                    const pctClass = pct >= 0 ? 'dpu-tbl-pct--up' : 'dpu-tbl-pct--down';
                    const pctSign  = pct > 0 ? '+' : '';
                    html += `
                    <tr class="dpu-tbl-row dpuwoo-variable-product" data-product-id="${p.product_id}">
                        <td class="dpu-tbl-cell-product">
                            ${hasVars ? '<span class="dpuwoo-toggle-icon dpu-tbl-toggle">⊕</span>' : '<span class="dpu-tbl-bullet">·</span>'}
                            <div>
                                <div class="dpu-tbl-product-name">${p.product_name}</div>
                                <div class="dpu-tbl-product-meta">ID ${p.product_id} · ${p.product_type.toUpperCase()}</div>
                            </div>
                        </td>
                        <td class="dpu-tbl-cell dpu-tbl-price">${regCell}</td>
                        <td class="dpu-tbl-cell dpu-tbl-price${hasSale ? ' dpu-tbl-price--sale' : ''}">${saleCell}</td>
                        <td class="dpu-tbl-cell">
                            <span class="${this.getStatusClass(p.status)}">${this.getStatusText(p.status, isSimulation)}</span>
                        </td>
                        <td class="dpu-tbl-cell dpu-tbl-pct ${pctClass}">
                            ${pct !== 0 ? pctSign + pct.toFixed(2) + '%' : '—'}
                        </td>
                    </tr>`;

                    if (hasVars) {
                        group.variations.forEach(v => {
                            const vPct = parseFloat(v.percentage_change || 0);
                            const vPctClass = vPct >= 0 ? 'dpu-tbl-pct--up' : 'dpu-tbl-pct--down';
                            const vPctSign  = vPct > 0 ? '+' : '';
                            html += `
                            <tr class="dpuwoo-variation-rows dpu-tbl-row dpu-tbl-row--variation hidden" data-parent-id="${p.product_id}">
                                <td class="dpu-tbl-cell-product dpu-tbl-cell-product--variation">
                                    <span class="dpu-tbl-var-indent">└</span>
                                    <span class="dpu-tbl-var-name">${v.variation_name || 'Variación'}</span>
                                </td>
                                <td class="dpu-tbl-cell dpu-tbl-price">${this.formatPriceChange(v.old_regular_price, v.new_regular_price)}</td>
                                <td class="dpu-tbl-cell dpu-tbl-price${v.new_sale_price > 0 ? ' dpu-tbl-price--sale' : ''}">${this.formatPriceChange(v.old_sale_price, v.new_sale_price)}</td>
                                <td class="dpu-tbl-cell">
                                    <span class="${this.getStatusClass(v.status)}">${this.getStatusText(v.status, isSimulation)}</span>
                                </td>
                                <td class="dpu-tbl-cell dpu-tbl-pct ${vPctClass}">${vPct !== 0 ? vPctSign + vPct.toFixed(2) + '%' : '—'}</td>
                            </tr>`;
                        });
                    }
                });

                return html + `        </tbody>
                    </table>
                </div>`;
            },

            showCompleteResults: function (data, isSimulation) {
                const resultsHtml = this.generateCompleteResults(data, isSimulation);
                const target = isSimulation ? '#dpuwoo-simulation-results' : '#dpuwoo-final-results';
                // Ocultar la barra de progreso antes de mostrar resultados
                this.hideSection(isSimulation ? 'dpuwoo-simulation-process' : 'dpuwoo-update-process');
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
                    $(this).toggleClass('dpu-tbl-row--expanded');
                });
            },
            formatPrice: function (price) {
                if (price === null || price === undefined || price === '' || parseFloat(price) === 0) {
                    return '-';
                }
                return '$' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            },

            formatPriceChange: function (oldPrice, newPrice) {
                const hasNew = newPrice > 0.001;
                const hasOld = oldPrice > 0.001;
                if (!hasNew) return '<span class="dpu-tbl-empty-val">—</span>';
                if (!hasOld) return `<span class="dpu-tbl-new-price--same">$${parseFloat(newPrice).toFixed(2)}</span>`;

                const diff = parseFloat(newPrice) - parseFloat(oldPrice);
                const dir  = diff > 0.001 ? 'up' : diff < -0.001 ? 'down' : 'same';

                return `<span class="dpu-tbl-old-price">$${parseFloat(oldPrice).toFixed(2)}</span>` +
                       `<span class="dpu-tbl-price-arrow dpu-tbl-price-arrow--${dir}">→</span>` +
                       `<span class="dpu-tbl-new-price--${dir}">$${parseFloat(newPrice).toFixed(2)}</span>`;
            },

            /**
             * Maneja errores en el proceso de simulación/actualización
             * @param {string} errorMessage - Mensaje de error a mostrar
             * @param {string} processType - Tipo de proceso ('simulation' o 'update')
             */
            handleProcessError: function (errorMessage, processType) {
                console.error('DPUWoo Process Error:', errorMessage);

                // Mostrar mensaje de error en la interfaz
                const errorHtml = `
                    <div class="dpu-process-error">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <strong>Error en el proceso</strong>
                            <p>${errorMessage}</p>
                        </div>
                    </div>
                `;

                // Insertar error en la sección correspondiente
                const targetSection = processType === 'simulation'
                    ? '#dpuwoo-simulation-process'
                    : '#dpuwoo-update-process';

                $(targetSection).prepend(errorHtml);

                // Resetear estado y habilitar botones
                this.resetAllSections();
                this.enableButtons();

                // Mostrar sección de error
                this.showSection(targetSection.replace('#', ''));
            }
        };

        // Exponer variables globales
        window.DPUWOO_Globals = { isProcessing, currentProcessType, cumulativeResults };

        // Inicializar módulos
        if (window.DPUWOO_Tabs) DPUWOO_Tabs.init();
        if (window.DPUWOO_Simulation) DPUWOO_Simulation.init();
        if (window.DPUWOO_Update) DPUWOO_Update.init();
        if (window.DPUWOO_Logs) DPUWOO_Logs.init();
    });

})(jQuery);