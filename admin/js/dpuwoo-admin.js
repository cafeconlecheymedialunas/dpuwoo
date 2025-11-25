(function ($) {
    'use strict';

    $(document).ready(function(){

        // Pass nonce and ajax_url from wp_localize_script as dpuwoo_ajax.nonce / ajax_url

        function buildTableRows(items) {
            var html = '';
            if (!items || items.length === 0) {
                html += '<tr><td colspan="8">No hay cambios.</td></tr>';
                return html;
            }

            // Agrupar variaciones por producto padre
            var groupedItems = groupVariationsByParent(items);
            
            groupedItems.forEach(function (item) {
                var pct = item.percentage_change !== null ? parseFloat(item.percentage_change).toFixed(2) + '%' : '-';
                var statusClass = 'dpu-status-' + (item.status || 'unknown');
                var statusBadge = '<span class="' + statusClass + '">' + (item.status || 'unknown') + '</span>';
                
                // Mostrar tipo de producto
                var productType = item.product_type || 'simple';
                var typeBadge = productType === 'variable' ? 
                    '<span class="dpu-type-variable">Variable</span>' : 
                    '<span class="dpu-type-simple">Simple</span>';

                html += '<tr data-log="' + (item.log_id || item.id) + '">';
                html += '<td class="product_name">' + (item.product_name || '') + ' <small>#' + (item.product_id || '') + '</small><br><small>SKU: ' + (item.product_sku || 'N/A') + '</small></td>';
                html += '<td class="type">' + typeBadge + '</td>';
                html += '<td class="base">' + formatPriceRange(item.base_price, item.base_price_range) + '</td>';
                html += '<td class="old">' + formatPriceRange(item.old_regular_price, item.old_price_range) + '</td>';
                html += '<td class="new">' + formatPriceRange(item.new_regular_price, item.new_price_range) + '</td>';
                html += '<td class="pct">' + pct + '</td>';
                html += '<td class="status">' + statusBadge + '</td>';
                html += '<td class="actions">';
                if (item.status === 'updated') {
                    html += '<button class="button dpu-revert-item" data-log="' + (item.log_id || item.id) + '">Revertir</button>';
                } else if (item.status === 'error') {
                    html += '<span class="text-red-600 text-sm">' + (item.reason || 'Error') + '</span>';
                } else if (item.status === 'skipped') {
                    html += '<span class="text-gray-500 text-sm">Sin cambios</span>';
                }
                html += '</td>';
                html += '</tr>';
            });
            return html;
        }

        // Función para agrupar variaciones por producto padre
        function groupVariationsByParent(items) {
            var grouped = [];
            var parentMap = {};

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
                var group = parentMap[parentId];
                if (group.variations.length > 0) {
                    // Usar los datos de la primera variación como base
                    var firstVariation = group.variations[0];
                    
                    // Calcular rangos de precios
                    var oldPrices = group.variations.map(function(v) { return v.old_regular_price || 0; }).filter(function(p) { return p > 0; });
                    var newPrices = group.variations.map(function(v) { return v.new_regular_price || 0; }).filter(function(p) { return p > 0; });
                    var basePrices = group.variations.map(function(v) { return v.base_price || 0; }).filter(function(p) { return p > 0; });
                    
                    var oldMin = oldPrices.length ? Math.min(...oldPrices) : 0;
                    var oldMax = oldPrices.length ? Math.max(...oldPrices) : 0;
                    var newMin = newPrices.length ? Math.min(...newPrices) : 0;
                    var newMax = newPrices.length ? Math.max(...newPrices) : 0;
                    var baseMin = basePrices.length ? Math.min(...basePrices) : 0;
                    var baseMax = basePrices.length ? Math.max(...basePrices) : 0;

                    // Determinar el estado general
                    var statuses = group.variations.map(function(v) { return v.status; });
                    var overallStatus = determineOverallStatus(statuses);

                    // Crear item agrupado
                    var groupedItem = {
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
                        variations: group.variations // Guardar referencias para revertir
                    };

                    grouped.push(groupedItem);
                }
            });

            return grouped;
        }

        // Determinar el estado general basado en los estados de las variaciones
        function determineOverallStatus(statuses) {
            if (statuses.includes('error')) return 'error';
            if (statuses.includes('updated')) return 'updated';
            if (statuses.includes('simulated')) return 'simulated';
            if (statuses.every(s => s === 'skipped')) return 'skipped';
            return 'pending';
        }

        // Calcular porcentaje de cambio promedio
        function calculateAveragePercentage(variations) {
            var validChanges = variations.filter(function(v) { 
                return v.percentage_change !== null && !isNaN(v.percentage_change); 
            });
            
            if (validChanges.length === 0) return null;
            
            var sum = validChanges.reduce(function(total, v) { 
                return total + parseFloat(v.percentage_change); 
            }, 0);
            
            return sum / validChanges.length;
        }

        // Obtener razón general
        function getOverallReason(variations) {
            var errors = variations.filter(function(v) { return v.status === 'error'; });
            if (errors.length > 0) {
                return errors[0].reason || 'Error en variaciones';
            }
            return null;
        }

        // Formatear rango de precios
        function formatPriceRange(singlePrice, priceRange) {
            if (priceRange) {
                return priceRange;
            }
            return singlePrice ? '$' + parseFloat(singlePrice).toFixed(2) : '$0';
        }

        $('#dpuwoo-update-now').on('click', function (e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).text('Actualizando...');
            
            $.post(dpuwoo_ajax.ajax_url, {
                action: 'dpuwoo_update_now',
                nonce: dpuwoo_ajax.nonce
            }, function (res) {
                if (!res.success) {
                    var errorMsg = res.data && res.data.message ? res.data.message : (res.data || 'Error desconocido');
                    alert('Error: ' + errorMsg);
                    $btn.prop('disabled', false).text('Actualizar ahora');
                    return;
                }
                
                var data = res.data;
                
                // Mostrar resumen
                var header = '<div class="dpu-summary mb-4 p-3 bg-blue-50 rounded border border-blue-200">';
                header += '<strong>Dólar Actual:</strong> $' + (data.rate || '0') + ' (' + (data.dollar_type || 'n/a') + ') &nbsp; ';
                header += '<strong>Dólar Base:</strong> $' + (data.baseline_rate || '0') + ' &nbsp; ';
                header += '<strong>Ratio:</strong> ' + (data.ratio ? data.ratio.toFixed(4) : '0') + 'x &nbsp; ';
                if (data.summary) {
                    header += '<strong>Productos:</strong> ' + data.summary.updated + ' actualizados, ' + data.summary.skipped + ' sin cambios, ' + data.summary.errors + ' errores';
                }
                if (data.run_id) {
                    header += ' &nbsp; <strong>Run ID:</strong> ' + data.run_id;
                }
                header += '</div>';
                $('#dpuwoo-sim-results').html(header);

                // Mostrar tabla de cambios
                if (data.changes && data.changes.length > 0) {
                    var tableHtml = '<table class="widefat striped"><thead><tr><th>Producto</th><th>Tipo</th><th>Base</th><th>Antes</th><th>Ahora</th><th>% Cambio</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
                    tableHtml += buildTableRows(data.changes);
                    tableHtml += '</tbody></table>';
                    $('#dpuwoo-sim-results').append(tableHtml);
                } else {
                    $('#dpuwoo-sim-results').append('<p class="text-gray-500">No hay cambios para mostrar.</p>');
                }
                
                $btn.prop('disabled', false).text('Actualizar ahora');
            }, 'json').fail(function (xhr, status, error) {
                alert('Error de conexión: ' + error);
                $btn.prop('disabled', false).text('Actualizar ahora');
            });
        });

        // Simulate
        $('#dpuwoo-simulate').on('click', function (e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).text('Simulando...');
            $.post(dpuwoo_ajax.ajax_url, {
                action: 'dpuwoo_simulate',
                nonce: dpuwoo_ajax.nonce
            }, function (res) {
                if (!res.success) {
                    var errorMsg = res.data && res.data.message ? res.data.message : (res.data || 'Error desconocido');
                    alert('Error: ' + errorMsg);
                    $btn.prop('disabled', false).text('Simular impacto');
                    return;
                }
                var data = res.data;
                var header = '<div class="dpu-summary mb-4 p-3 bg-green-50 rounded border border-green-200">';
                header += '<strong>SIMULACIÓN - Dólar Actual:</strong> $' + (data.rate || '0') + ' (' + (data.dollar_type || 'n/a') + ') &nbsp; ';
                header += '<strong>Dólar Base:</strong> $' + (data.baseline_rate || '0') + ' &nbsp; ';
                header += '<strong>Ratio:</strong> ' + (data.ratio ? data.ratio.toFixed(4) : '0') + 'x &nbsp; ';
                if (data.summary) {
                    header += '<strong>Productos:</strong> ' + data.summary.updated + ' se actualizarían, ' + data.summary.skipped + ' sin cambios, ' + data.summary.errors + ' errores';
                }
                header += '</div>';
                $('#dpuwoo-sim-results').html(header);

                var tableHtml = '<table class="widefat striped"><thead><tr><th>Producto</th><th>Tipo</th><th>Base</th><th>Actual</th><th>Proyectado</th><th>% Cambio</th><th>Estado</th></tr></thead><tbody>';
                tableHtml += buildTableRows(data.changes);
                tableHtml += '</tbody></table>';
                $('#dpuwoo-sim-results').append(tableHtml);

                $btn.prop('disabled', false).text('Simular impacto');
            }, 'json').fail(function () {
                alert('Error comunicándose con el servidor');
                $btn.prop('disabled', false).text('Simular impacto');
            });
        });

        // Delegate revert item - MODIFICADO para manejar productos variables
        $(document).on('click', '.dpu-revert-item', function (e) {
            e.preventDefault();
            var logId = $(this).data('log');
            if (!confirm('¿Revertir este cambio?')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).text('Revirtiendo...');
            $.post(dpuwoo_ajax.ajax_url, {
                action: 'dpuwoo_revert_item',
                nonce: dpuwoo_ajax.nonce,
                log_id: logId
            }, function (res) {
                if (!res.success) {
                    alert('Error: ' + (res.data || 'unknown'));
                    $btn.prop('disabled', false).text('Revertir');
                    return;
                }
                $btn.text('✅ Revertido').prop('disabled', true);
                // Actualizar estado en la tabla
                $btn.closest('tr').find('.status').html('<span class="dpu-status-reverted">reverted</span>');
            }, 'json').fail(function () {
                alert('Error comunicándose con el servidor');
                $btn.prop('disabled', false).text('Revertir');
            });
        });

    });

})(jQuery);