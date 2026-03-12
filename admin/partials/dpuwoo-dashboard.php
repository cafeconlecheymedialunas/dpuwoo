<?php if (!defined('ABSPATH')) exit; ?>

<!-- TAILWIND -->

<div class="wrap dpuwoo-admin max-w-6xl mx-auto py-6">

    <!-- Display WordPress settings messages -->
    <?php settings_errors(); ?>

    <h1 class="text-3xl font-bold mb-8 text-gray-800">
        Dollar Price Engine – Panel
    </h1>

    <!-- NAV TABS -->
    <div class="border-b border-gray-300 mb-8">
        <nav class="flex space-x-6 text-sm font-medium">
            <button data-tab="dashboard" 
                    class="dpuwoo-tab py-3 border-b-2 border-blue-600 text-blue-600 font-semibold transition-colors duration-200">
                Dashboard
            </button>
            <button data-tab="logs" 
                    class="dpuwoo-tab py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium transition-colors duration-200">
                Historial
            </button>
            <button data-tab="settings" 
                    class="dpuwoo-tab py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium transition-colors duration-200">
                Configuración
            </button>
        </nav>
    </div>

    <!-- DASHBOARD -->
    <section id="dpuwoo-tab-dashboard" class="dpuwoo-tab-content">

        <div class="bg-white shadow rounded-xl p-8 border border-gray-200">

            <h2 class="text-xl font-semibold text-gray-800 mb-6">Estado general</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Valor dólar -->
                <div class="p-5 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500 text-sm">Valor actual (última actualización)</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">
                        <?php
                        global $wpdb;
                        $last = $wpdb->get_var("SELECT dollar_value FROM {$wpdb->prefix}dpuwoo_runs ORDER BY id DESC LIMIT 1");
                        echo $last ? esc_html(number_format(floatval($last), 2)) : 'n/a';
                        ?>
                    </p>
                </div>

                <!-- Productos -->
                <div class="p-5 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500 text-sm">Total de productos</p>
                    <p class="text-3xl font-semibold text-gray-800 mt-1">
                        <?php
                        $product_count = wp_count_posts('product');
                        echo ($product_count->publish ?? 0) + ($product_count->draft ?? 0);
                        ?>
                    </p>
                </div>

            </div>

            <!-- Configuración Activa Detallada -->
            <div class="mt-8 bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Configuración Activa del Sistema
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Todas las configuraciones que se aplican en las actualizaciones de precios</p>
                </div>
                <div class="p-6">
                    <?php 
                    $opts = get_option('dpuwoo_settings', []);
                    $providers = class_exists('API_Client') ? API_Client::get_available_providers() : [];
                    $provider_key = $opts['api_provider'] ?? '';
                    $provider_info = isset($providers[$provider_key]) ? $providers[$provider_key] : [];
                    $store_currency = get_woocommerce_currency();
                    ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Columna Izquierda: Configuración Base -->
                        <div class="space-y-6">
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                                <h4 class="font-bold text-blue-800 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                                    </svg>
                                    Configuración de Origen
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Proveedor API:</span>
                                        <span class="font-semibold"><?php echo esc_html($provider_info['name'] ?? $provider_key ?: 'No configurado'); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Moneda de Referencia:</span>
                                        <span class="font-semibold"><?php echo esc_html($opts['reference_currency'] ?? 'USD'); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Moneda Tienda:</span>
                                        <span class="font-semibold"><?php echo esc_html($store_currency); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                                <h4 class="font-bold text-green-800 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    Valores Base
                                </h4>
                              

                        <!-- Columna Derecha: Reglas de Cálculo -->
                        <div class="space-y-6">
                            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                                <h4 class="font-bold text-yellow-800 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                    </svg>
                                    Reglas de Cálculo
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Margen de Corrección:</span>
                                        <span class="font-semibold"><?php echo number_format(floatval($opts['margin'] ?? 0), 2); ?>%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Umbral de Cambio:</span>
                                        <span class="font-semibold"><?php echo number_format(floatval($opts['threshold'] ?? 0.5), 2); ?>%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Dirección Permitida:</span>
                                        <span class="font-semibold capitalize">
                                            <?php 
                                            $direction = $opts['update_direction'] ?? 'bidirectional';
                                            switch($direction) {
                                                case 'up_only': echo 'Solo Subida'; break;
                                                case 'down_only': echo 'Solo Bajada'; break;
                                                default: echo 'Ambas Direcciones';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-purple-50 p-4 rounded-lg border border-purple-100">
                                <h4 class="font-bold text-purple-800 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                    </svg>
                                    Formato de Precios
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Tipo de Redondeo:</span>
                                        <span class="font-semibold capitalize">
                                            <?php 
                                            $rounding = $opts['rounding_type'] ?? 'integer';
                                            switch($rounding) {
                                                case 'none': echo 'Sin Redondeo'; break;
                                                case 'integer': echo 'Enteros'; break;
                                                case 'ceil': echo 'Hacia Arriba'; break;
                                                case 'floor': echo 'Hacia Abajo'; break;
                                                case 'nearest': echo 'Al Más Cercano'; break;
                                                default: echo ucfirst($rounding);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($rounding === 'nearest'): ?>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Redondear a:</span>
                                        <span class="font-semibold">$<?php echo esc_html($opts['nearest_to'] ?? '1'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de Acciones -->
            <div class="mt-8 p-6 bg-blue-50 rounded-lg border border-blue-200">
                <h3 class="text-lg font-semibold text-blue-800 mb-4">Proceso de Actualización</h3>
                <p class="text-blue-700 mb-4">
                    <strong>Flujo recomendado:</strong> Primero simula los cambios para revisar el impacto, luego confirma la actualización real.
                </p>
                
                <div class="flex items-center gap-4">
                    <button id="dpuwoo-simulate"
                        class="px-6 py-3 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 transition flex items-center font-medium">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Paso 1: Simular Impacto
                    </button>

                    <span class="text-gray-500">o</span>
                                        
                    <button id="dpuwoo-update-now"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition flex items-center font-medium">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Actualizar Directamente
                    </button>
                </div>
                
                <div class="mt-3 text-sm text-blue-600">
                    <p>💡 <strong>Nota:</strong> La simulación puede tomar unos minutos dependiendo de la cantidad de productos.</p>
                </div>
            </div>

            <!-- Proceso de Simulación -->
            <div id="dpuwoo-simulation-process" class="hidden mt-6">
                <div class="bg-white shadow rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center text-white font-bold mr-3">1</div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Simulación en Progreso</h3>
                            <p class="text-gray-600 text-sm">Calculando cómo cambiarían los precios...</p>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso simulación -->
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span id="dpuwoo-sim-text">Iniciando simulación...</span>
                            <span id="dpuwoo-sim-percent">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div id="dpuwoo-sim-progress" class="bg-green-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Información del lote -->
                    <div id="dpuwoo-sim-batch-info" class="text-sm text-gray-600 space-y-1 mb-4">
                        <div>Lote: <span id="dpuwoo-sim-current-batch">0</span> / <span id="dpuwoo-sim-total-batches">0</span></div>
                        <div>Productos simulados: <span id="dpuwoo-sim-processed-products">0</span> / <span id="dpuwoo-sim-total-products">0</span></div>
                    </div>

                    <!-- Botón de cancelar simulación -->
                    <div class="mt-4">
                        <button id="dpuwoo-cancel-simulation" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            Cancelar Simulación
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resultados de Simulación -->
            <div id="dpuwoo-simulation-results" class="hidden mt-6">
                <div class="bg-white shadow rounded-xl p-6 border border-green-200">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold mr-3">✓</div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Simulación Completada</h3>
                            <p class="text-gray-600 text-sm">Revisa los cambios propuestos antes de continuar</p>
                        </div>
                    </div>

                    <!-- Resumen de simulación -->
                    <div id="dpuwoo-sim-summary" class="mb-6 p-4 bg-green-50 rounded-lg">
                        <!-- Se llenará dinámicamente -->
                    </div>

                    <!-- Tabla de resultados -->
                    <div id="dpuwoo-sim-results-table" class="mb-6">
                        <!-- Se llenará dinámicamente -->
                    </div>

                    <!-- Acciones post-simulación -->
                    <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
                        <button id="dpuwoo-proceed-update"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition flex items-center font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Paso 2: Confirmar Actualización
                        </button>

                        <button id="dpuwoo-new-simulation"
                            class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                            Nueva Simulación
                        </button>
                    </div>
                </div>
            </div>

            <!-- Proceso de Actualización Real -->
            <div id="dpuwoo-update-process" class="hidden mt-6">
                <div class="bg-white shadow rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold mr-3">2</div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Actualización en Progreso</h3>
                            <p class="text-gray-600 text-sm">Aplicando cambios reales a los productos...</p>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso actualización -->
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span id="dpuwoo-update-text">Iniciando actualización...</span>
                            <span id="dpuwoo-update-percent">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div id="dpuwoo-update-progress" class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Información del lote -->
                    <div id="dpuwoo-update-batch-info" class="text-sm text-gray-600 space-y-1 mb-4">
                        <div>Lote: <span id="dpuwoo-update-current-batch">0</span> / <span id="dpuwoo-update-total-batches">0</span></div>
                        <div>Productos actualizados: <span id="dpuwoo-update-processed-products">0</span> / <span id="dpuwoo-update-total-products">0</span></div>
                    </div>

                    <!-- Resultados en tiempo real -->
                    <div id="dpuwoo-update-live-results" class="mt-4 p-3 bg-blue-50 rounded-lg hidden">
                        <h4 class="font-medium mb-2 text-blue-800">Progreso actual:</h4>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div class="text-center">
                                <div class="text-green-600 font-bold" id="dpuwoo-live-updated">0</div>
                                <div class="text-gray-500">Actualizados</div>
                            </div>
                            <div class="text-center">
                                <div class="text-gray-600 font-bold" id="dpuwoo-live-skipped">0</div>
                                <div class="text-gray-500">Sin cambios</div>
                            </div>
                            <div class="text-center">
                                <div class="text-red-600 font-bold" id="dpuwoo-live-errors">0</div>
                                <div class="text-gray-500">Errores</div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de cancelar actualización -->
                    <div class="mt-4">
                        <button id="dpuwoo-cancel-update" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            Cancelar Actualización
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resultados Finales -->
            <div id="dpuwoo-final-results" class="hidden mt-6">
                <!-- Se llenará dinámicamente con los resultados finales -->
            </div>

        </div>
    </section>

    <!-- LOGS TAB -->
    <section id="dpuwoo-tab-logs" class="dpuwoo-tab-content hidden">

        <div class="bg-white shadow rounded-xl p-8 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Historial de cambios</h2>

            <div id="dpuwoo-log-table" class="overflow-x-auto">

    
            </div>
        </div>

    </section>

    <!-- SETTINGS TAB -->
    <section id="dpuwoo-tab-settings" class="dpuwoo-tab-content hidden">

        <div class="bg-white shadow rounded-xl p-8 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Configuración</h2>
            <form id="dpuwoo-settings-form" method="post" action="options.php" class="space-y-6">

                <?php
                settings_fields('dpuwoo_settings_group');
                do_settings_sections('dpuwoo_settings');
                ?>
                
                <div class="pt-6 border-t border-gray-200">
                    <button type="submit" id="dpuwoo-save-settings" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors relative">
                        <span class="btn-text">Guardar cambios</span>
                        <span class="btn-loading" style="display: none;">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Guardando...
                        </span>
                    </button>
                    <span id="dpuwoo-save-status" class="ml-4 text-sm"></span>
                </div>

            </form>
        </div>

    </section>

</div>

<!-- Modal de Confirmación de Actualización Directa -->
<div id="dpuwoo-direct-update-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center text-white font-bold mr-3">!</div>
            <h3 class="text-lg font-semibold text-gray-800">Actualización Directa</h3>
        </div>
        <p class="text-gray-600 mb-4">
            Estás a punto de actualizar los precios <strong>sin simulación previa</strong>. 
            Esta acción modificará directamente los precios de todos los productos.
        </p>
        <p class="text-yellow-600 text-sm mb-4">
            ⚠️ <strong>Recomendación:</strong> Para mayor seguridad, considera primero ejecutar una simulación.
        </p>
        <div class="flex gap-3 justify-end">
            <button id="dpuwoo-direct-cancel" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition font-medium">
                Cancelar
            </button>
            <button id="dpuwoo-direct-proceed" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Entiendo, Proceder
            </button>
        </div>
    </div>
</div>

<!-- Modal de Confirmación Post-Simulación -->
<div id="dpuwoo-confirm-update-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold mr-3">✓</div>
            <h3 class="text-lg font-semibold text-gray-800">Confirmar Actualización</h3>
        </div>
        <p class="text-gray-600 mb-4" id="dpuwoo-confirm-message">
            ¿Estás seguro de que deseas proceder con la actualización real de precios?
        </p>
        <div id="dpuwoo-confirm-summary" class="mb-4 p-3 bg-blue-50 rounded text-sm">
            <!-- Resumen de cambios -->
        </div>
        <div class="flex gap-3 justify-end">
            <button id="dpuwoo-confirm-cancel" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition font-medium">
                Cancelar
            </button>
            <button id="dpuwoo-confirm-proceed" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                Sí, Actualizar Precios
            </button>
        </div>
    </div>
</div>

<!-- Modal de Detalles de Ejecución -->
<div id="dpuwoo-run-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800">Detalles de la Ejecución</h3>
            <button id="dpuwoo-close-details-modal" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Content -->
        <div class="flex-1 overflow-auto p-6">
            <div id="dpuwoo-run-details-content">
                <!-- El contenido se carga aquí dinámicamente -->
            </div>
        </div>
        
        <!-- Footer -->
        <div class="flex justify-end p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl">
            <button id="dpuwoo-close-details-modal-2" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition font-medium">
                Cerrar
            </button>
        </div>
    </div>
</div>