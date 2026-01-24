(function($) {
    'use strict';

    // Baseline Manager Module
    window.DPUWOO_Baseline = {
        init: function() {
            this.bindEvents();
            this.loadBaselineStats();
        },

        bindEvents: function() {
            // Refresh stats button
            $('#dpuwoo-refresh-baseline-stats').on('click', (e) => {
                e.preventDefault();
                this.loadBaselineStats();
            });

            // Reset baselines button
            $('#dpuwoo-reset-baselines').on('click', (e) => {
                e.preventDefault();
                this.resetBaselines();
            });
        },

        loadBaselineStats: function() {
            const $countElement = $('#dpuwoo-baseline-count');
            const $loadingElement = $('#dpuwoo-baseline-loading');
            
            $countElement.text('Cargando...');
            $loadingElement.html('<div class="text-center py-4"><div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600"></div><p class="mt-2 text-sm">Cargando estadísticas...</p></div>');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dpuwoo_get_baseline_stats',
                    nonce: $('#dpuwoo_ajax_nonce').val() || ''
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        $countElement.text(`${data.total_products_with_baseline} de ${data.total_products}`);
                        
                        // Update products list
                        this.updateProductsList(data.products_with_baseline);
                        
                        // Update last update time
                        if (data.last_update) {
                            $('#dpuwoo-last-baseline-update').text(data.last_update);
                        }
                    } else {
                        $countElement.text('Error');
                        $loadingElement.html('<div class="text-center py-4 text-red-500">Error al cargar estadísticas</div>');
                    }
                },
                error: () => {
                    $countElement.text('Error');
                    $loadingElement.html('<div class="text-center py-4 text-red-500">Error de conexión</div>');
                }
            });
        },

        updateProductsList: function(products) {
            const $container = $('#dpuwoo-baseline-products-list');
            
            if (!products || products.length === 0) {
                $container.find('#dpuwoo-baseline-loading').html(`
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.47.881-6.08 2.32M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-500">No hay productos con baseline establecido</p>
                        <p class="text-sm text-gray-400 mt-1">Los baselines se crearán automáticamente durante la próxima actualización</p>
                    </div>
                `);
                return;
            }

            let html = `
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Base</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda Ref</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tasa Base</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Establecido</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último Cálculo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cálculos</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;

            products.forEach(product => {
                html += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                                        <span class="text-purple-800 font-bold text-sm">${product.name.charAt(0)}</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">${product.name}</div>
                                    <div class="text-sm text-gray-500">ID: ${product.id}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            $${parseFloat(product.base_price).toFixed(2)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${product.currency}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${parseFloat(product.baseline_rate).toFixed(4)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${product.established_date}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${product.last_calculation}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                            ${product.total_calculations}
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-sm text-gray-500 text-center">
                    Mostrando ${products.length} productos con baseline establecido
                </div>
            `;

            $container.html(html);
        },

        resetBaselines: function() {
            if (!confirm('¿Estás seguro de que quieres reiniciar todos los baselines? Esta acción no se puede deshacer.')) {
                return;
            }

            const $button = $('#dpuwoo-reset-baselines');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('🗑️ Reiniciando...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dpuwoo_reset_baselines',
                    nonce: $('#dpuwoo_ajax_nonce').val() || ''
                },
                success: (response) => {
                    if (response.success) {
                        alert(`✅ Baselines reiniciados correctamente. ${response.data.deleted_count} productos afectados.`);
                        this.loadBaselineStats();
                    } else {
                        alert('❌ Error al reiniciar baselines: ' + response.data.message);
                    }
                },
                error: () => {
                    alert('❌ Error de conexión al reiniciar baselines');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (window.DPUWOO_Baseline) {
            DPUWOO_Baseline.init();
        }
    });

})(jQuery);