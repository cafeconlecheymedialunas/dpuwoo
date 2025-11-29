<?php if (!defined('ABSPATH')) exit; ?>

<!-- TAILWIND -->

<div class="wrap dpuwoo-admin max-w-6xl mx-auto py-6">

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
                        <?php $opts = get_option('dpuwoo_settings'); echo esc_html($opts['last_rate'] ?? 'n/a'); ?>
                    </p>
                </div>

                <!-- Baseline -->
                <div class="p-5 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500 text-sm">Valor de referencia (baseline)</p>
                    <p class="text-3xl font-semibold text-gray-800 mt-1">
                        <?php echo esc_html($opts['baseline_dollar_value'] ?? 'n/a'); ?>
                    </p>
                </div>

                <!-- Ratio -->
                <div class="p-5 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500 text-sm">Ratio de ajuste</p>
                    <p class="text-3xl font-semibold text-gray-800 mt-1">
                        <?php 
                        $opts = get_option('dpuwoo_settings');
                        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
                        $last_rate = floatval($opts['last_rate'] ?? 0);
                        if ($baseline > 0 && $last_rate > 0) {
                            echo number_format($last_rate / $baseline, 2) . 'x';
                        } else {
                            echo 'n/a';
                        }
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
            <form method="post" action="options.php" class="space-y-6">

                <?php
                settings_fields('dpuwoo_settings_group');
                do_settings_sections('dpuwoo_settings');
                submit_button('Guardar cambios', 'primary', null, false, [
                    'class' => 'px-5 py-2 bg-blue-600 text-white rounded-lg'
                ]);
                ?>

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