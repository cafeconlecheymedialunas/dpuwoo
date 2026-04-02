<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts           = get_option('dpuwoo_settings', []);
$last_run       = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}dpuwoo_runs ORDER BY id DESC LIMIT 1");
$product_count  = wp_count_posts('product');
$total_products = $product_count->publish ?? 0;
$store_currency = \Dpuwoo\Helpers\dpuwoo_get_store_currency();

// Manual es independiente de Cron - cada uno tiene su propia config
$provider_key = $opts['api_provider'] ?? 'dolarapi';
$dollar_type = $opts['dollar_type'] ?? '';
$providers = class_exists('API_Client') ? API_Client::get_available_providers() : [];
$provider_name  = $providers[$provider_key]['name'] ?? ($provider_key ?: 'No configurado');

    $api_providers_list = [
    'dolarapi'      => 'DolarAPI.com',
    'currencyapi'   => 'CurrencyAPI',
    'exchangerate' => 'ExchangeRate-API',
];

// Get connected APIs status
$is_currencyapi_connected = !empty($opts['currencyapi_api_key']);
$is_exchangerate_connected = !empty($opts['exchangerate_api_key']);

// Get current provider
$current_provider = $opts['api_provider'] ?? 'dolarapi';

$base_country = get_option('woocommerce_default_country', '');
if (strpos($base_country, ':') !== false) {
    $base_country = substr($base_country, 0, strpos($base_country, ':'));
}
$country_name = $base_country;
if (function_exists('WC') && method_exists(WC()->countries, 'get_countries')) {
    $countries = WC()->countries->get_countries();
    $country_name = $countries[$base_country] ?? $base_country;
}

$all_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
$excluded_cats = $opts['exclude_categories'] ?? [];
?>

<div class="wrap dpuwoo-admin">

    <!-- Header -->
    <div class="dpuwoo-header">
        <div class="dpuwoo-header__left">
            <h1 class="dpuwoo-header__title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Actualización Manual
            </h1>
            <p class="dpuwoo-header__subtitle">Simulá y actualizá precios de forma manual</p>
        </div>
        <div class="dpuwoo-header__actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_automation')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Automatización
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_logs')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Historial
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="dpuwoo-stats-bar">
        <div class="dpuwoo-stat">
            <span class="dpuwoo-stat__label">Tipo de cambio actual</span>
            <span class="dpuwoo-stat__value">
                <?php if (($opts['origin_exchange_rate'] ?? 0) > 0): ?>
                    <span class="dpuwoo-stat__rate">$<?php echo number_format($opts['origin_exchange_rate'], 2); ?></span>
                <?php else: ?>
                    <span class="dpuwoo-stat__empty">Sin configurar</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="dpuwoo-stat">
            <span class="dpuwoo-stat__label">Productos</span>
            <span class="dpuwoo-stat__value"><?php echo number_format($total_products); ?></span>
        </div>
        <div class="dpuwoo-stat">
            <span class="dpuwoo-stat__label">Proveedor</span>
            <span class="dpuwoo-stat__value"><?php echo esc_html($provider_name); ?></span>
        </div>
    </div>

    <form id="dpuwoo-dashboard-form" method="post" action="options.php">

        <?php settings_fields('dpuwoo_settings_group'); ?>

        <!-- API Section (Collapsible) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="api">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">API y Conexión</span>
                <span class="dpuwoo-collapsible__summary"><?php echo esc_html($api_providers_list[$provider_key] ?? 'No configurado'); ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-api">
                <div class="dpuwoo-fields-grid">
                    <!-- Provider -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Proveedor</label>
                        <select name="dpuwoo_settings[api_provider]" class="dpuwoo-field__select" id="dpuwoo-api-provider">
                            <option value="dolarapi" <?php selected($provider_key, 'dolarapi'); ?>>DolarAPI.com (Gratuita)</option>
                            <option value="currencyapi" <?php selected($provider_key, 'currencyapi'); ?> <?php echo !$is_currencyapi_connected ? 'disabled' : ''; ?>>CurrencyAPI <?php echo !$is_currencyapi_connected ? '(Sin API Key)' : '(Conectada)'; ?></option>
                            <option value="exchangerate" <?php selected($provider_key, 'exchangerate'); ?> <?php echo !$is_exchangerate_connected ? 'disabled' : ''; ?>>ExchangeRate-API <?php echo !$is_exchangerate_connected ? '(Sin API Key)' : '(Conectada)'; ?></option>
                        </select>
                        <p class="dpuwoo-field__hint">Solo APIs conectadas están disponibles. <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings')); ?>">Configurar API Keys</a></p>
                    </div>

                    <!-- Currency/Type Reference -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Moneda de referencia</label>
                        <select name="dpuwoo_settings[dollar_type]" class="dpuwoo-field__select" id="dpuwoo-api-type">
                            <option value="">Seleccionar...</option>
                        </select>
                        <p class="dpuwoo-field__hint" id="dpuwoo-currency-hint">Monedas disponibles para <?php echo esc_html($country_name); ?></p>
                    </div>

                    <!-- Country (Readonly) -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">País</label>
                        <input type="text" value="<?php echo esc_attr($country_name . ' (' . $base_country . ')'); ?>" class="dpuwoo-field__input" readonly>
                        <p class="dpuwoo-field__hint">Configurado en WooCommerce</p>
                        <input type="hidden" name="dpuwoo_settings[country]" value="<?php echo esc_attr($base_country); ?>">
                    </div>

                    <!-- Base Currency (Readonly) -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Moneda base</label>
                        <input type="text" value="<?php echo esc_attr($store_currency); ?>" class="dpuwoo-field__input" readonly>
                        <p class="dpuwoo-field__hint">Configurada en WooCommerce</p>
                        <input type="hidden" name="dpuwoo_settings[base_currency]" value="<?php echo esc_attr($store_currency); ?>">
                    </div>

                    <!-- Origin Rate with Get from API -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Tipo de cambio</label>
                        <div class="dpuwoo-field__input-wrap">
                            <span class="dpuwoo-field__prefix">$</span>
                            <input type="number" name="dpuwoo_settings[origin_exchange_rate]" value="<?php echo esc_attr($opts['origin_exchange_rate'] ?? ''); ?>" step="0.0001" min="0.0001" class="dpuwoo-field__input" id="dpuwoo-origin-rate" placeholder="Ej: 850.00">
                        </div>
                        <button type="button" id="dpuwoo-get-current-rate" class="dpuwoo-btn dpuwoo-btn--outline dpuwoo-btn--sm" style="margin-top: 0.5rem;">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Obtener tipo de cambio
                        </button>
                        <p class="dpuwoo-field__hint">Tipo de cambio actual para el cálculo de variaciones</p>
                        <div class="dpuwoo-field__error" id="rate-error"></div>
                        <div id="dpuwoo-rate-result" class="dpuwoo-field__result"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rules Section (Collapsible - Expanded by default) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible dpuwoo-collapsible--expanded" data-section="rules">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Reglas de Precio</span>
                <span class="dpuwoo-collapsible__summary"><?php echo number_format(floatval($opts['margin'] ?? 0), 1); ?>% margen · <?php echo number_format(floatval($opts['threshold'] ?? 0.5), 1); ?>% umbral</span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-rules">
                <div class="dpuwoo-fields-grid">
                    <!-- Margin -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Margen de corrección</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[margin]" value="<?php echo esc_attr($opts['margin'] ?? ''); ?>" step="0.01" class="dpuwoo-field__input" id="dpuwoo-margin" placeholder="0">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">+% absorbe menos suba · -% absorbe parte de la suba · Default: 0%</p>
                        <div class="dpuwoo-field__warning" id="margin-warning"></div>
                    </div>

                    <!-- Threshold Min -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Variación mínima</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[threshold]" value="<?php echo esc_attr($opts['threshold'] ?? ''); ?>" step="0.01" min="0" class="dpuwoo-field__input" id="dpuwoo-threshold" placeholder="0.5">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">Protege contra fluctuaciones pequeñas · Default: 0.5%</p>
                        <div class="dpuwoo-field__error" id="threshold-error"></div>
                    </div>

                    <!-- Threshold Max -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Variación máxima</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[threshold_max]" value="<?php echo esc_attr($opts['threshold_max'] ?? ''); ?>" step="0.01" min="0" class="dpuwoo-field__input" placeholder="Sin límite">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">Frenó de seguridad · Dejar vacío para sin límite</p>
                    </div>

                    <!-- Direction -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Sentido de actualización</label>
                        <select name="dpuwoo_settings[update_direction]" class="dpuwoo-field__select">
                            <option value="bidirectional" <?php selected($opts['update_direction'] ?? '', 'bidirectional'); ?>>Bidireccional</option>
                            <option value="up_only" <?php selected($opts['update_direction'] ?? '', 'up_only'); ?>>Solo cuando sube</option>
                            <option value="down_only" <?php selected($opts['update_direction'] ?? '', 'down_only'); ?>>Solo cuando baja</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rounding Section (Collapsible) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="rounding">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Redondeo</span>
                <span class="dpuwoo-collapsible__summary"><?php echo esc_html(ucfirst($opts['rounding_type'] ?? 'Enteros')); ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-rounding">
                <div class="dpuwoo-fields-grid">
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Tipo de redondeo</label>
                        <select name="dpuwoo_settings[rounding_type]" class="dpuwoo-field__select">
                            <option value="integer" <?php selected($opts['rounding_type'] ?? '', 'integer'); ?>>Enteros</option>
                            <option value="none" <?php selected($opts['rounding_type'] ?? '', 'none'); ?>>Sin redondeo</option>
                            <option value="ceil" <?php selected($opts['rounding_type'] ?? '', 'ceil'); ?>>Hacia arriba</option>
                            <option value="floor" <?php selected($opts['rounding_type'] ?? '', 'floor'); ?>>Hacia abajo</option>
                            <option value="nearest" <?php selected($opts['rounding_type'] ?? '', 'nearest'); ?>>Al más cercano</option>
                        </select>
                    </div>
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Redondear a</label>
                        <div class="dpuwoo-field__input-wrap">
                            <span class="dpuwoo-field__prefix">$</span>
                            <input type="number" name="dpuwoo_settings[nearest_to]" value="<?php echo esc_attr($opts['nearest_to'] ?? '1'); ?>" step="1" min="1" class="dpuwoo-field__input">
                        </div>
                        <p class="dpuwoo-field__hint">Ej: 1 para enteros, 5 para múltiplos de 5</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exclusions Section (Collapsible) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="exclusions">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Exclusiones</span>
                <span class="dpuwoo-collapsible__summary"><?php echo count($excluded_cats) > 0 ? count($excluded_cats) . ' categorías' : 'Ninguna'; ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-exclusions">
                <div class="dpuwoo-field">
                    <label class="dpuwoo-field__label">Categorías excluidas</label>
                    <select name="dpuwoo_settings[exclude_categories][]" multiple class="dpuwoo-field__select" style="min-height: 150px;">
                        <?php foreach ($all_cats as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php echo in_array($cat->term_id, $excluded_cats) ? 'selected' : ''; ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="dpuwoo-field__hint">Productos de estas categorías no se actualizarán. Mantener ctrl/cmd para seleccionar varias.</p>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="dpuwoo-summary-card">
            <h3 class="dpuwoo-summary-card__title">Configuración que se aplicará</h3>
            <div class="dpuwoo-summary-card__items">
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Proveedor</span>
                    <span class="dpuwoo-summary-item__value"><?php echo esc_html($api_providers_list[$provider_key] ?? 'No configurado'); ?></span>
                </div>
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Moneda referencia</span>
                    <span class="dpuwoo-summary-item__value"><?php echo esc_html($opts['reference_currency'] ?? 'USD'); ?></span>
                </div>
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Margen</span>
                    <span class="dpuwoo-summary-item__value"><?php echo number_format(floatval($opts['margin'] ?? 0), 1); ?>%</span>
                </div>
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Umbral mín.</span>
                    <span class="dpuwoo-summary-item__value"><?php echo number_format(floatval($opts['threshold'] ?? 0.5), 1); ?>%</span>
                </div>
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Umbral máx.</span>
                    <span class="dpuwoo-summary-item__value"><?php echo ($opts['threshold_max'] ?? '') ? number_format(floatval($opts['threshold_max']), 1) . '%' : 'Sin límite'; ?></span>
                </div>
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Dirección</span>
                    <span class="dpuwoo-summary-item__value"><?php 
                        $dir_labels = ['bidirectional' => 'Bidireccional', 'up_only' => 'Solo suba', 'down_only' => 'Solo baja'];
                        echo esc_html($dir_labels[$opts['update_direction'] ?? ''] ?? 'Bidireccional');
                    ?></span>
                </div>
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Redondeo</span>
                    <span class="dpuwoo-summary-item__value"><?php echo esc_html(ucfirst($opts['rounding_type'] ?? 'Enteros')); ?></span>
                </div>
                <div class="dpuwoo-summary-item">
                    <span class="dpuwoo-summary-item__label">Excluidas</span>
                    <span class="dpuwoo-summary-item__value"><?php echo count($excluded_cats); ?> categorías</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="dpuwoo-action-buttons">
            <button type="submit" class="dpuwoo-btn dpuwoo-btn--outline">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Guardar configuración
            </button>
            <button type="button" id="dpuwoo-simulate" class="dpuwoo-btn dpuwoo-btn--primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Simular impacto
            </button>
        </div>

    </form>

    <!-- Simulation Process -->
    <div id="dpuwoo-simulation-process" class="dpuwoo-process-section hidden"></div>

    <!-- Simulation Results -->
    <div id="dpuwoo-simulation-results" class="hidden"></div>

    <!-- Update Process -->
    <div id="dpuwoo-update-process" class="dpuwoo-process-section hidden"></div>

    <!-- Final Results -->
    <div id="dpuwoo-final-results" class="hidden"></div>

</div>

<script>
jQuery(document).ready(function($) {
    console.log('DPUWOO Dashboard JS loaded');
    
    // Load currencies on page load if provider is already selected
    var initialProvider = $('#dpuwoo-api-provider').val();
    console.log('Initial provider:', initialProvider);
    if (initialProvider) {
        loadCurrencies(initialProvider);
    }
    
    // Test change event
    $('#dpuwoo-api-provider').on('change', function() {
        var provider = $(this).val();
        console.log('Provider changed to:', provider);
        if (provider) {
            loadCurrencies(provider);
        }
    });
    
    // Function to load currencies
    function loadCurrencies(provider) {
        var $typeSelect = $('#dpuwoo-api-type');
        var $hint = $('#dpuwoo-currency-hint');
        
        console.log('Loading currencies for:', provider);
        $typeSelect.empty().append($('<option>', { value: '', text: 'Cargando...' }));
        
        $.post(dpuwoo_ajax.ajax_url, {
            action: 'dpuwoo_get_currencies',
            provider: provider,
            nonce: dpuwoo_ajax.nonce
        }, function(res) {
            console.log('AJAX Response:', res);
            $typeSelect.empty();
            
            if (!res.success) {
                $typeSelect.append($('<option>', { value: '', text: 'Error: ' + (res.data?.message || 'Error') }));
                $hint.text('Error al cargar');
                return;
            }
            
            if (res.data.currencies && res.data.currencies.length > 0) {
                var currencies = res.data.currencies;
                
                // Separate by category if available
                var hasCategories = currencies[0] && currencies[0].category !== undefined;
                var dollarTypes = [];
                var otherCurrencies = [];
                
                if (hasCategories) {
                    dollarTypes = currencies.filter(function(c) { return c.category === 'dollar_types'; });
                    otherCurrencies = currencies.filter(function(c) { return c.category !== 'dollar_types'; });
                    
                    var dollarOrder = ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta', 'mayorista', 'cripto', 'mep', 'solidario'];
                    dollarTypes.sort(function(a, b) {
                        var aIdx = dollarOrder.indexOf(a.key);
                        var bIdx = dollarOrder.indexOf(b.key);
                        return (aIdx === -1 ? 999 : aIdx) - (bIdx === -1 ? 999 : bIdx);
                    });
                } else {
                    otherCurrencies = currencies;
                }
                
                otherCurrencies.sort(function(a, b) {
                    var nameA = a.name || a.code || '';
                    var nameB = b.name || b.code || '';
                    return nameA.localeCompare(nameB);
                });
                
                if (dollarTypes.length > 0) {
                    $typeSelect.append($('<option>', { value: '', text: '--- Tipos de Dólar ---', disabled: true }));
                    $.each(dollarTypes, function(i, curr) {
                        $typeSelect.append($('<option>', { value: curr.key, text: curr.name }));
                    });
                }
                
                if (otherCurrencies.length > 0) {
                    $typeSelect.append($('<option>', { value: '', text: '--- Monedas ---', disabled: true }));
                    $.each(otherCurrencies, function(i, curr) {
                        var key = curr.key || curr.code || '';
                        var name = curr.name || key;
                        $typeSelect.append($('<option>', { value: key, text: name }));
                    });
                }
                
                $hint.text(res.data.count + ' monedas disponibles');
            } else {
                $typeSelect.append($('<option>', { value: '', text: 'No hay monedas' }));
            }
        }).fail(function(xhr, status, error) {
            console.log('AJAX Error:', error);
            $typeSelect.empty().append($('<option>', { value: '', text: 'Error de conexión' }));
        });
    }

    // Get current rate button
    $('#dpuwoo-get-current-rate').on('click', function() {
        var $btn = $(this);
        var $result = $('#dpuwoo-rate-result');
        var provider = $('#dpuwoo-api-provider').val();
        var type = $('#dpuwoo-api-type').val();
        var $rateInput = $('#dpuwoo-origin-rate');
        
        if (!type) {
            $result.html('<span class="dpuwoo-field__error-text">Seleccioná un tipo o moneda primero</span>');
            return;
        }
        
        $btn.prop('disabled', true).html('<svg class="animate-spin" width="12" height="12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Obteniendo...');
        $result.html('<span class="dpuwoo-field__loading">Conectando con API...</span>');
        
        $.post(dpuwoo_ajax.ajax_url, {
            action: 'dpuwoo_get_current_rate',
            provider: provider,
            type: type,
            nonce: dpuwoo_ajax.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Obtener tipo de cambio');
            
            if (res.success) {
                var rate = parseFloat(res.data.rate).toFixed(4);
                $rateInput.val(rate);
                $result.html('<span class="dpuwoo-field__success-text">✓ Tasa actualizada: $' + rate + '</span>');
                
                // Update the stats bar
                $('.dpuwoo-stat__rate').text('$' + parseFloat(res.data.rate).toFixed(2));
            } else {
                $result.html('<span class="dpuwoo-field__error-text">Error: ' + (res.data?.message || 'No se pudo obtener la tasa') + '</span>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Obtener tipo de cambio');
            $result.html('<span class="dpuwoo-field__error-text">Error de conexión con el servidor</span>');
        });
    });
});
</script>

<style>
.dpuwoo-field__result {
    margin-top: 0.5rem;
    font-size: 0.8125rem;
}
.dpuwoo-field__success-text {
    color: #16a34a;
    font-weight: 600;
}
.dpuwoo-field__error-text {
    color: #dc2626;
    font-weight: 600;
}
.dpuwoo-field__loading {
    color: #6b7280;
    font-style: italic;
}
</style>

<!-- Confirm Update Modal -->
<div id="dpuwoo-confirm-update-modal" class="dpuwoo-modal-overlay hidden">
    <div class="dpuwoo-modal">
        <div class="dpuwoo-modal__header dpuwoo-modal__header--ok">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3>Confirmar actualización</h3>
        </div>
        <div class="dpuwoo-modal__body">
            <p>¿Confirmás la actualización real de precios según la simulación?</p>
            <div id="dpuwoo-confirm-summary"></div>
        </div>
        <div class="dpuwoo-modal__footer">
            <button id="dpuwoo-confirm-cancel" class="dpuwoo-btn dpuwoo-btn--ghost">Cancelar</button>
            <button id="dpuwoo-confirm-proceed" class="dpuwoo-btn dpuwoo-btn--primary">Sí, actualizar precios</button>
        </div>
    </div>
</div>
