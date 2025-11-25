<?php if (!defined('ABSPATH')) exit; ?>

<!-- TAILWIND -->
<script src="https://cdn.tailwindcss.com"></script>

<div class="wrap dpuwoo-admin max-w-6xl mx-auto py-6">

    <h1 class="text-3xl font-bold mb-8 text-gray-800">
        Dollar Price Engine – Panel
    </h1>

    <!-- NAV TABS -->
    <div class="border-b border-gray-300 mb-8">
        <nav class="flex space-x-6 text-sm font-medium">
            <button data-tab="dashboard" class="dpuwoo-tab active py-3 border-b-2 border-blue-600 text-blue-600">
                Dashboard
            </button>
            <button data-tab="simulation" class="dpuwoo-tab py-3 text-gray-500 hover:text-gray-700">
                Simulación
            </button>
            <button data-tab="logs" class="dpuwoo-tab py-3 text-gray-500 hover:text-gray-700">
                Logs
            </button>
            <button data-tab="settings" class="dpuwoo-tab py-3 text-gray-500 hover:text-gray-700">
                Configuración
            </button>
        </nav>
    </div>

    <!-- DASHBOARD -->
    <section id="dpuwoo-tab-dashboard" class="dpuwoo-tab-content">

        <div class="bg-white shadow rounded-xl p-8 border border-gray-200">

            <h2 class="text-xl font-semibold text-gray-800 mb-6">Estado general</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

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

                <!-- Próxima actualización -->
                <div class="p-5 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500 text-sm">Próxima actualización</p>
                    <p class="text-xl font-semibold text-gray-800 mt-1">n/a</p>
                </div>

            </div>

            <div class="flex items-center gap-4 mt-8">
                <button id="dpuwoo-update-now"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition">
                    Actualizar ahora
                </button>

                <button id="dpuwoo-simulate"
                    class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Simular impacto
                </button>
            </div>

        </div>
    </section>

    <!-- SIMULATION TAB -->
    <section id="dpuwoo-tab-simulation" class="dpuwoo-tab-content hidden">

        <div class="bg-white shadow rounded-xl p-8 border border-gray-200">

            <h2 class="text-xl font-semibold text-gray-800 mb-6">Simulación de precios</h2>

            <p class="text-gray-600 mb-6">
                Aquí verás cómo cambiarían los precios si aplicas una actualización ahora mismo.
            </p>

            <div id="dpuwoo-sim-results" class="space-y-4">

                <div class="p-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg hidden" id="dpuwoo-sim-loading">
                    Procesando simulación...
                </div>

                <!-- Tabla dinámica generada por JS -->
            </div>

        </div>

    </section>

    <!-- LOGS TAB -->
    <section id="dpuwoo-tab-logs" class="dpuwoo-tab-content hidden">

        <div class="bg-white shadow rounded-xl p-8 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Historial de cambios</h2>

            <div id="dpuwoo-log-table" class="overflow-x-auto">

                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700 text-sm border-b">
                            <th class="p-3">Fecha</th>
                            <th class="p-3">Producto</th>
                            <th class="p-3">Precio anterior</th>
                            <th class="p-3">Precio nuevo</th>
                            <th class="p-3">Cambio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="p-3 text-gray-400 text-sm" colspan="5">No hay registros aún.</td>
                        </tr>
                    </tbody>
                </table>
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

<script>
document.addEventListener('DOMContentLoaded', () => {

    const buttons = document.querySelectorAll('.dpuwoo-tab');
    const contents = document.querySelectorAll('.dpuwoo-tab-content');

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;

            buttons.forEach(b => {
                b.classList.remove('active', 'border-blue-600', 'text-blue-600');
                b.classList.add('text-gray-500');
            });

            btn.classList.add('active', 'border-blue-600', 'text-blue-600');
            btn.classList.remove('text-gray-500');

            contents.forEach(c => c.classList.add('hidden'));
            document.querySelector('#dpuwoo-tab-' + tab).classList.remove('hidden');
        });
    });

});
</script>
