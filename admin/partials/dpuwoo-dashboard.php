<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap dpuwoo-admin max-w-5xl">

    <h1 class="wp-heading-inline mb-6">Dollar Price Engine – Panel</h1>

    <!-- TABS -->
    <nav class="dpuwoo-tabs-nav flex border-b border-gray-300 mb-6 text-sm">
        <button data-tab="dashboard" class="dpuwoo-tab active px-4 py-2">Dashboard</button>
        <button data-tab="logs" class="dpuwoo-tab px-4 py-2">Logs</button>
        <button data-tab="settings" class="dpuwoo-tab px-4 py-2">Configuración</button>
    </nav>

    <!-- TAB: DASHBOARD -->
    <section id="dpuwoo-tab-dashboard" class="dpuwoo-tab-content">
        <div class="bg-white border border-gray-300 rounded-lg p-6 shadow-sm">

            <h2 class="text-xl font-semibold mb-4">Estado actual</h2>

            <p class="mb-2 text-gray-700">
                <strong>Valor del dólar:</strong>
                <?php $opts = get_option('dpuwoo_settings'); echo esc_html($opts['last_rate'] ?? 'n/a'); ?>
            </p>

            <p class="mb-4 text-gray-700"><strong>Próxima actualización:</strong> n/a</p>

            <div class="flex gap-3 mb-6">
                <button class="button button-primary dpuwoo-btn" id="dpuwoo-update-now">Actualizar ahora</button>
                <button class="button dpuwoo-btn" id="dpuwoo-simulate">Simular impacto</button>
            </div>

            <div id="dpuwoo-sim-results" class="mt-4"></div>

        </div>
    </section>

    <!-- TAB: LOGS -->
    <section id="dpuwoo-tab-logs" class="dpuwoo-tab-content hidden">
        <div class="bg-white border border-gray-300 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold mb-4">Historial de Cambios</h2>
            <p class="text-gray-700 mb-4">Revisa los últimos ajustes automáticos y manuales.</p>
            <div id="dpuwoo-log-table">
                <p class="text-gray-500 text-sm">No hay registros aún.</p>
            </div>
        </div>
    </section>

    <!-- TAB: SETTINGS -->
    <section id="dpuwoo-tab-settings" class="dpuwoo-tab-content hidden">
        <div class="bg-white border border-gray-300 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold mb-4">Configuración</h2>
            <form method="post" action="options.php" class="space-y-6">
                <?php
                settings_fields('dpuwoo_settings_group');
                do_settings_sections('dpuwoo_settings');
                submit_button();
                ?>
            </form>
        </div>
    </section>

</div>

<style>
/* WooCommerce-like styling + tabs */
.dpuwoo-tabs-nav button {
    cursor: pointer;
    border-bottom: 2px solid transparent;
    background: transparent;
    margin-right: 8px;
    font-weight: 500;
    color: #334155;
}
.dpuwoo-tabs-nav button.active {
    border-color: #2271b1;
    color: #1e293b;
}
.dpuwoo-tab-content.hidden {
    display: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {

    // Tabs
    const tabButtons = document.querySelectorAll('.dpuwoo-tab');
    const tabContents = document.querySelectorAll('.dpuwoo-tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-tab');

            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            tabContents.forEach(c => c.classList.add('hidden'));
            document.querySelector('#dpuwoo-tab-' + tab).classList.remove('hidden');
        });
    });
});
</script>
