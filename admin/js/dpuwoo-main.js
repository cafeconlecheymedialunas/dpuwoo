(function ($) {
    'use strict';

    let isProcessing = false;
    let currentProcessType = null;
    let cumulativeResults = { updated: 0, skipped: 0, errors: 0, changes: [] };

    $(document).ready(function () {
        $('#dpuwoo-simulation-process').addClass('hidden');
        $('#dpuwoo-simulation-results').addClass('hidden');
        $('#dpuwoo-update-process').addClass('hidden');
        $('#dpuwoo-final-results').addClass('hidden');

        // Toggle config section
        $('#dpuwoo-toggle-config').on('click', function() {
            const $details = $('#dpuwoo-config-details');
            const $btn = $(this);
            if ($details.hasClass('hidden')) {
                $details.removeClass('hidden');
                $btn.html('<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>Cerrar');
            } else {
                $details.addClass('hidden');
                $btn.html('<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>Ver detalles');
            }
        });

        // Collapsible sections
        $('.dpuwoo-collapsible').on('click', function() {
            const $btn = $(this);
            const section = $btn.data('section');
            const $content = $('#section-' + section);
            
            $btn.toggleClass('dpuwoo-collapsible--expanded');
            $content.slideToggle(200);
        });

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
             * GENERAR RESUMEN CON FÓRMULAS
             */
            generateCompleteResults: function (data, isSimulation = false) {
                const summary = data.summary || { updated: 0, skipped: 0, errors: 0 };
                const changes = data.changes || [];

                const thresholdMin = data.threshold_min !== undefined ? parseFloat(data.threshold_min) : parseFloat(dpuwoo_ajax.threshold_min ?? 0);
                const thresholdMax = data.threshold_max !== undefined ? parseFloat(data.threshold_max) : parseFloat(dpuwoo_ajax.threshold_max ?? 0);
                const thresholdMet = data.threshold_met !== undefined ? data.threshold_met : true;
                const blockMessage = data.message || '';
                const percentageChange = Math.abs(data.percentage_change || 0);

                const hasRealChanges = isSimulation
                    ? changes.some(item => item.status === 'simulated' || item.status === 'updated')
                    : (summary.updated || 0) > 0;

                const oldRate = data.previous_rate || 1;
                const newRate = data.rate || 1;
                const rateRatio = oldRate > 0 ? newRate / oldRate : 1;
                const mainCount = isSimulation
                    ? changes.filter(item => item.status === 'simulated' || item.status === 'updated').length
                    : (summary.updated || 0);
                const mainLabel = isSimulation ? 'A modificar' : 'Actualizados';
                const shouldShowTable = hasRealChanges && thresholdMet;

                let html = `<div class="dpuwoo-results-container">`;

                // Header
                html += `
    <div class="dpuwoo-result-card">
        <div class="dpuwoo-result-header ${isSimulation ? 'dpuwoo-result-header--sim' : 'dpuwoo-result-header--upd'}">
            <div class="dpuwoo-result-header__left">
                <div class="dpuwoo-result-header__icon">${hasRealChanges ? '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' : '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'}</div>
                <div>
                    <p class="dpuwoo-result-header__title">${isSimulation ? 'Simulación completada' : 'Actualización completada'}</p>
                    <p class="dpuwoo-result-header__sub">${changes.length} productos analizados</p>
                </div>
            </div>
            <span class="dpuwoo-result-badge ${isSimulation ? 'dpuwoo-result-badge--sim' : 'dpuwoo-result-badge--upd'}">${isSimulation ? 'Vista previa' : 'Guardado'}</span>
        </div>`;

                // Rate info
                html += `
        <div class="dpuwoo-result-rates">
            <div class="dpuwoo-rate-box">
                <span class="dpuwoo-rate-box__label">Tasa anterior</span>
                <span class="dpuwoo-rate-box__value">$${parseFloat(oldRate).toFixed(2)}</span>
            </div>
            <div class="dpuwoo-rate-arrow">→</div>
            <div class="dpuwoo-rate-box ${isSimulation ? 'dpuwoo-rate-box--sim' : 'dpuwoo-rate-box--upd'}">
                <span class="dpuwoo-rate-box__label">${isSimulation ? 'Tasa a aplicar' : 'Tasa aplicada'}</span>
                <span class="dpuwoo-rate-box__value">$${parseFloat(newRate).toFixed(2)}</span>
            </div>
            <div class="dpuwoo-rate-change ${(data.percentage_change || 0) >= 0 ? 'up' : 'down'}">
                ${(data.percentage_change || 0) >= 0 ? '+' : ''}${(data.percentage_change || 0).toFixed(2)}%
            </div>
        </div>`;



                // Threshold status
                const thresholdClass = thresholdMet ? 'dpuwoo-threshold--ok' : 'dpuwoo-threshold--fail';
                const thresholdText = thresholdMet
                    ? (thresholdMin === 0 && thresholdMax === 0 ? 'Sin umbral · Se actualiza siempre' : `Umbral mín. ${thresholdMin}% · Alcanzado`)
                    : (thresholdMax > 0 && percentageChange > thresholdMax ? `Umbral máx. ${thresholdMax}% · Superado` : `Umbral mín. ${thresholdMin}% · No alcanzado`);
                html += `<div class="dpuwoo-threshold ${thresholdClass}"><span class="dpuwoo-threshold__dot"></span>${thresholdText}</div>`;

                // Counters
                html += `
        <div class="dpuwoo-result-counters">
            <div class="dpuwoo-result-counter ${mainCount > 0 ? (isSimulation ? 'dpuwoo-result-counter--sim' : 'dpuwoo-result-counter--upd') : ''}">
                <span class="dpuwoo-result-counter__num">${mainCount}</span>
                <span class="dpuwoo-result-counter__label">${mainLabel}</span>
            </div>
            <div class="dpuwoo-result-counter">
                <span class="dpuwoo-result-counter__num dpuwoo-result-counter__num--skip">${summary.skipped || 0}</span>
                <span class="dpuwoo-result-counter__label">Sin cambios</span>
            </div>
            <div class="dpuwoo-result-counter ${(summary.errors || 0) > 0 ? 'dpuwoo-result-counter--err' : ''}">
                <span class="dpuwoo-result-counter__num ${(summary.errors || 0) > 0 ? 'dpuwoo-result-counter__num--err' : ''}">${summary.errors || 0}</span>
                <span class="dpuwoo-result-counter__label">Errores</span>
            </div>
        </div>`;

                // Content based on state
                if (!shouldShowTable && !thresholdMet) {
                    const blockTitle = thresholdMax > 0 && percentageChange > thresholdMax ? 'Umbral máximo superado' : 'Umbral mínimo no alcanzado';
                    const blockBody = blockMessage || (thresholdMax > 0 && percentageChange > thresholdMax
                        ? `La variación del ${percentageChange.toFixed(2)}% supera el umbral máximo del ${thresholdMax}%. No se realizaron cambios por seguridad.`
                        : `La variación del ${percentageChange.toFixed(2)}% no alcanza el umbral mínimo del ${thresholdMin}%.`);
                    html += `
        <div class="dpuwoo-result-block dpuwoo-result-block--warn">
            <div class="dpuwoo-result-block__icon"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
            <h4>${blockTitle}</h4>
            <p>${blockBody}</p>
            <button onclick="location.reload()" class="dpuwoo-btn dpuwoo-btn--ghost">Volver al inicio</button>
        </div>`;
                } else if (shouldShowTable) {
                    html += `
        <div class="dpuwoo-result-table">
            <div class="dpuwoo-result-table__header">
                <span>Detalle de productos</span>
                <span>${changes.filter(c => c.status === 'simulated' || c.status === 'updated').length} a modificar</span>
            </div>
            ${this.generateResultsTableWithFormulas(changes, isSimulation, rateRatio, oldRate, newRate)}
        </div>
        <div class="dpuwoo-result-actions">
            <button onclick="location.reload()" class="dpuwoo-btn dpuwoo-btn--ghost">← Cancelar</button>
            ${isSimulation ? `<button id="dpuwoo-proceed-update" class="dpuwoo-btn dpuwoo-btn--primary"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Confirmar y aplicar</button>` : ''}
        </div>`;
                } else {
                    html += `
        <div class="dpuwoo-result-block dpuwoo-result-block--info">
            <div class="dpuwoo-result-block__icon"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <h4>Sin cambios detectados</h4>
            <p>${summary.skipped > 0 ? `${summary.skipped} productos procesados, ninguno requirió ajuste a la tasa $${parseFloat(newRate).toFixed(2)}.` : `Ningún producto requirió ajuste a la tasa $${parseFloat(newRate).toFixed(2)}.`}</p>
            <button onclick="location.reload()" class="dpuwoo-btn dpuwoo-btn--ghost">Volver al inicio</button>
        </div>`;
                }

                html += `</div></div>`;
                return html;
            },

            /**
             * GENERAR TABLA SIMPLE DE RESULTADOS
             */
            generateResultsTableWithFormulas: function (changes, isSimulation, rateRatio, oldRate, newRate) {
                if (!changes || changes.length === 0) return '<p class="dpuwoo-table-empty">No hay cambios para mostrar.</p>';

                let html = `<div class="dpuwoo-table-wrap"><table class="dpuwoo-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Precio anterior</th>
                    <th>Precio nuevo</th>
                    <th>Cambio</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>`;

                changes.filter(c => c.status === 'simulated' || c.status === 'updated').forEach(item => {
                    const oldPrice = parseFloat(item.old_regular_price) || 0;
                    const newPrice = parseFloat(item.new_regular_price) || 0;
                    const pct = parseFloat(item.percentage_change) || 0;
                    const pctClass = pct >= 0 ? 'up' : 'down';
                    const statusClass = item.status === 'simulated' ? 'dpuwoo-status--sim' : 'dpuwoo-status--upd';
                    const statusText = isSimulation ? 'Simulado' : 'Actualizado';

                    if (oldPrice > 0 && newPrice > 0) {
                        html += `
                <tr class="dpuwoo-table__row">
                    <td class="dpuwoo-table__product">
                        <span class="dpuwoo-table__name">${item.product_name}</span>
                        <span class="dpuwoo-table__meta">ID ${item.product_id}</span>
                    </td>
                    <td class="dpuwoo-table__price">$${oldPrice.toFixed(2)}</td>
                    <td class="dpuwoo-table__price dpuwoo-table__price--new ${pctClass}">$${newPrice.toFixed(2)}</td>
                    <td class="dpuwoo-table__pct ${pctClass}">${pct >= 0 ? '+' : ''}${pct.toFixed(1)}%</td>
                    <td><span class="dpuwoo-status ${statusClass}">${statusText}</span></td>
                </tr>`;
                    }
                });

                html += `</tbody></table></div>`;
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