<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts         = get_option('dpuwoo_settings', []);
$cron_enabled = $opts['cron_enabled'] ?? 0;
$next_cron    = wp_next_scheduled('dpuwoo_do_update');

$is_first_time = false;

$last_run = $wpdb->get_row(
    "SELECT r.date, r.dollar_value, r.percentage_change,
            SUM(CASE WHEN i.status = 'updated' THEN 1 ELSE 0 END) AS updated_count,
            SUM(CASE WHEN i.status = 'skipped' THEN 1 ELSE 0 END) AS skipped_count
     FROM {$wpdb->prefix}dpuwoo_runs r
     LEFT JOIN {$wpdb->prefix}dpuwoo_run_items i ON i.run_id = r.id
     WHERE r.context = 'cron'
     GROUP BY r.id
     ORDER BY r.id DESC
     LIMIT 1"
);

$update_interval = $opts['update_interval'] ?? 'twicedaily';
$intervals = [
    'hourly'      => 'Cada hora',
    'twicedaily'  => 'Dos veces por día',
    'daily'       => 'Una vez por día',
    'weekly'      => 'Una vez por semana',
];

$base_country = get_option('woocommerce_default_country', '');
if (strpos($base_country, ':') !== false) {
    $base_country = substr($base_country, 0, strpos($base_country, ':'));
}
$country_name = $base_country;
if (function_exists('WC') && method_exists(WC()->countries, 'get_countries')) {
    $countries    = WC()->countries->get_countries();
    $country_name = $countries[$base_country] ?? $base_country;
}
$store_currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'ARS';

$notify_mode = $opts['cron_notify_mode'] ?? 'update_and_notify';
$notify_email = $opts['cron_notify_email'] ?? get_option('admin_email');
$notify_email_placeholder = get_option('admin_email');

$providers = class_exists('API_Client') ? API_Client::get_available_providers() : [];
$api_providers_list = [
    'dolarapi'      => 'DolarAPI.com',
    'currencyapi'   => 'CurrencyAPI',
    'exchangerate' => 'ExchangeRate-API',
];

// Get connected APIs status
$is_currencyapi_connected = !empty($opts['currencyapi_api_key']);
$is_exchangerate_connected = !empty($opts['exchangerate_api_key']);

// Get current cron provider
$cron_provider = $opts['cron_api_provider'] ?? '';
?>

<div class="wrap dpuwoo-admin">

    <!-- Header -->
    <div class="dpuwoo-header">
        <div class="dpuwoo-header__left">
            <h1 class="dpuwoo-header__title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Automatización
            </h1>
            <p class="dpuwoo-header__subtitle">Configuración de actualizaciones automáticas</p>
        </div>
        <div class="dpuwoo-header__actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_dashboard')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
                Actualización manual
            </a>
        </div>
    </div>

    <!-- Status Card -->
    <div class="dpuwoo-auto-card">
        <div class="dpuwoo-auto-card__status <?php echo $cron_enabled ? 'active' : 'inactive'; ?>">
            <div class="dpuwoo-auto-card__status-icon">
                <?php if ($cron_enabled): ?>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <?php else: ?>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                <?php endif; ?>
            </div>
            <div class="dpuwoo-auto-card__status-info">
                <h3><?php echo $cron_enabled ? 'Automatización activa' : 'Automatización desactivada'; ?></h3>
                <p>
                    <?php if ($cron_enabled && $next_cron): ?>
                        Próxima ejecución: <strong><?php echo esc_html(wp_date('d/m/Y H:i', $next_cron)); ?></strong>
                    <?php elseif ($cron_enabled): ?>
                        Cron no programado — guardá la configuración para activar.
                    <?php else: ?>
                        Los precios no se actualizarán automáticamente.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($last_run): ?>
        <div class="dpuwoo-auto-card__last-run">
            <span class="dpuwoo-auto-card__last-run-label">Última ejecución:</span>
            <span><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($last_run->date))); ?></span>
            <span class="dpuwoo-auto-card__last-run-stats">
                <?php echo intval($last_run->updated_count); ?> actualizados · 
                <?php echo intval($last_run->skipped_count); ?> sin cambios
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Inheritance Notice (first time) -->
    <form id="dpuwoo-automation-form" method="post" action="options.php">

        <?php settings_fields('dpuwoo_settings_group'); ?>

        <!-- Activation Toggle -->
        <div class="dpuwoo-section">
            <div class="dpuwoo-section__header">
                <h2 class="dpuwoo-section__title">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Activar automatización
                </h2>
                <label class="dpuwoo-toggle">
                    <input type="checkbox" name="dpuwoo_settings[cron_enabled]" value="1" <?php checked(1, $cron_enabled); ?>>
                    <span class="dpuwoo-toggle__slider"></span>
                </label>
            </div>
        </div>

        <!-- Frequency Section (Collapsible) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="frequency">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Frecuencia</span>
                <span class="dpuwoo-collapsible__summary"><?php echo esc_html($intervals[$update_interval] ?? 'Dos veces por día'); ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-frequency">
                <div class="dpuwoo-frequency-options">
                    <?php foreach ($intervals as $key => $label): ?>
                    <label class="dpuwoo-frequency-option <?php echo $update_interval === $key ? 'selected' : ''; ?>">
                        <input type="radio" name="dpuwoo_settings[update_interval]" value="<?php echo esc_attr($key); ?>" <?php checked($update_interval, $key); ?>>
                        <span class="dpuwoo-frequency-option__label"><?php echo esc_html($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- API Section (Collapsible) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="api">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">API y Conexión</span>
                <span class="dpuwoo-collapsible__summary"><?php echo esc_html($api_providers_list[$cron_provider] ?? 'No configurado'); ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-api">
                <div class="dpuwoo-fields-grid">
                    <!-- Provider -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Proveedor</label>
                        <select name="dpuwoo_settings[cron_api_provider]" class="dpuwoo-field__select" id="dpuwoo-api-provider">
                            <option value="dolarapi" <?php selected($cron_provider, 'dolarapi'); ?>>DolarAPI.com (Gratuita)</option>
                            <option value="currencyapi" <?php selected($cron_provider, 'currencyapi'); ?> <?php echo !$is_currencyapi_connected ? 'disabled' : ''; ?>>CurrencyAPI <?php echo !$is_currencyapi_connected ? '(Sin API Key)' : '(Conectada)'; ?></option>
                            <option value="exchangerate" <?php selected($cron_provider, 'exchangerate'); ?> <?php echo !$is_exchangerate_connected ? 'disabled' : ''; ?>>ExchangeRate-API <?php echo !$is_exchangerate_connected ? '(Sin API Key)' : '(Conectada)'; ?></option>
                        </select>
                        <p class="dpuwoo-field__hint">Solo APIs conectadas están disponibles. <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings')); ?>">Configurar API Keys</a></p>
                    </div>

                    <!-- Currency/Type Reference -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Moneda de referencia</label>
                        <select name="dpuwoo_settings[cron_dollar_type]" class="dpuwoo-field__select" id="dpuwoo-api-type">
                            <option value="">Primero seleccioná proveedor</option>
                        </select>
                        <p class="dpuwoo-field__hint" id="dpuwoo-currency-hint">Monedas disponibles para <?php echo esc_html($country_name); ?></p>
                    </div>

                    <!-- Country -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">País</label>
                        <input type="text" value="<?php echo esc_attr($country_name . ' (' . $base_country . ')'); ?>" class="dpuwoo-field__input" readonly style="background-color: var(--dpu-surface);">
                        <input type="hidden" name="dpuwoo_settings[cron_country]" value="<?php echo esc_attr($base_country); ?>">
                        <p class="dpuwoo-field__hint">Configurado en WooCommerce</p>
                    </div>

                </div>
            </div>
        </div>

        <!-- Rules Section (Collapsible) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible dpuwoo-collapsible--expanded" data-section="rules">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Reglas de Precio</span>
                <span class="dpuwoo-collapsible__summary">
                    <?php 
                    $cron_margin = $opts['cron_margin'] ?? '';
                    echo ($cron_margin !== '' ? number_format(floatval($cron_margin), 1) . '% margen' : '0% margen');
                    ?>
                </span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-rules">
                <div class="dpuwoo-fields-grid">
                    <!-- Margin -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Margen de corrección</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[cron_margin]" value="<?php echo esc_attr($cron_margin); ?>" step="0.01" class="dpuwoo-field__input" placeholder="0">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">+% absorbe menos suba · -% absorbe parte de la suba · Default: 0%</p>
                    </div>

                    <!-- Threshold Min -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Variación mínima</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[cron_threshold]" value="<?php echo esc_attr($opts['cron_threshold'] ?? ''); ?>" step="0.01" class="dpuwoo-field__input" placeholder="0.5">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">Protege contra fluctuaciones pequeñas · Default: 0.5%</p>
                    </div>

                    <!-- Threshold Max -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Variación máxima</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[cron_threshold_max]" value="<?php echo esc_attr($opts['cron_threshold_max'] ?? ''); ?>" step="0.01" class="dpuwoo-field__input" placeholder="Sin límite">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">Frenó de seguridad · Dejar vacío para sin límite</p>
                    </div>

                    <!-- Direction -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Sentido de actualización</label>
                        <select name="dpuwoo_settings[cron_update_direction]" class="dpuwoo-field__select">
                            <option value="bidirectional" <?php selected($opts['cron_update_direction'] ?? '', 'bidirectional'); ?>>Bidireccional</option>
                            <option value="up_only" <?php selected($opts['cron_update_direction'] ?? '', 'up_only'); ?>>Solo cuando sube</option>
                            <option value="down_only" <?php selected($opts['cron_update_direction'] ?? '', 'down_only'); ?>>Solo cuando baja</option>
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
                <span class="dpuwoo-collapsible__summary"><?php echo esc_html($opts['cron_rounding_type'] ?? 'Sin redondeo'); ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-rounding">
                <div class="dpuwoo-fields-grid">
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Tipo de redondeo</label>
                        <select name="dpuwoo_settings[cron_rounding_type]" class="dpuwoo-field__select">
                            <option value="none" <?php selected($opts['cron_rounding_type'] ?? '', 'none'); ?>>Sin redondeo</option>
                            <option value="integer" <?php selected($opts['cron_rounding_type'] ?? '', 'integer'); ?>>Enteros</option>
                            <option value="ceil" <?php selected($opts['cron_rounding_type'] ?? '', 'ceil'); ?>>Hacia arriba</option>
                            <option value="floor" <?php selected($opts['cron_rounding_type'] ?? '', 'floor'); ?>>Hacia abajo</option>
                            <option value="nearest" <?php selected($opts['cron_rounding_type'] ?? '', 'nearest'); ?>>Al más cercano</option>
                        </select>
                    </div>
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Redondear a</label>
                        <div class="dpuwoo-field__input-wrap">
                            <span class="dpuwoo-field__prefix">$</span>
                            <input type="number" name="dpuwoo_settings[cron_nearest_to]" value="<?php echo esc_attr($opts['cron_nearest_to'] ?? '1'); ?>" step="1" min="1" class="dpuwoo-field__input">
                        </div>
                        <p class="dpuwoo-field__hint">Ej: 1 para redondear a enteros, 5 para redondear a múltiplos de 5</p>
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
                <span class="dpuwoo-collapsible__summary">
                    <?php 
                    $excluded = $opts['cron_exclude_categories'] ?? [];
                    $count = count($excluded);
                    echo $count > 0 ? "$count categorías" : 'Ninguna';
                    ?>
                </span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-exclusions">
                <?php
                $all_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                ?>
                <div class="dpuwoo-field">
                    <label class="dpuwoo-field__label">Categorías excluidas</label>
                    <select name="dpuwoo_settings[cron_exclude_categories][]" multiple class="dpuwoo-field__select" style="min-height: 150px;">
                        <?php foreach ($all_cats as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php echo in_array($cat->term_id, $excluded) ? 'selected' : ''; ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="dpuwoo-field__hint">Productos de estas categorías no se actualizarán. Mantener ctrl/cmd para seleccionar varias.</p>
                </div>
            </div>
        </div>

        <!-- Notifications Section (Collapsible) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="notifications">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Notificaciones</span>
                <span class="dpuwoo-collapsible__summary">
                    <?php 
                    $mode_labels = [
                        'update_and_notify' => 'Actualizar y notificar',
                        'simulate_only' => 'Solo notificar',
                        'disabled' => 'Desactivadas'
                    ];
                    echo esc_html($mode_labels[$notify_mode] ?? 'Actualizar y notificar');
                    ?>
                </span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-notifications">
                <div class="dpuwoo-fields-grid">
                    <div class="dpuwoo-field" style="grid-column: 1 / -1;">
                        <label class="dpuwoo-field__label">Modo de notificación</label>
                        <div class="dpuwoo-radio-group">
                            <label class="dpuwoo-radio">
                                <input type="radio" name="dpuwoo_settings[cron_notify_mode]" value="update_and_notify" <?php checked($notify_mode, 'update_and_notify'); ?>>
                                <span class="dpuwoo-radio__content">
                                    <strong>Actualizar y notificar</strong>
                                    <span>Ejecuta la actualización y envía un email con los resultados.</span>
                                </span>
                            </label>
                            <label class="dpuwoo-radio">
                                <input type="radio" name="dpuwoo_settings[cron_notify_mode]" value="simulate_only" <?php checked($notify_mode, 'simulate_only'); ?>>
                                <span class="dpuwoo-radio__content">
                                    <strong>Solo notificar</strong>
                                    <span>Simula los cambios y envía un email, pero <strong>no modifica precios</strong>.</span>
                                </span>
                            </label>
                            <label class="dpuwoo-radio">
                                <input type="radio" name="dpuwoo_settings[cron_notify_mode]" value="disabled" <?php checked($notify_mode, 'disabled'); ?>>
                                <span class="dpuwoo-radio__content">
                                    <strong>Notificaciones desactivadas</strong>
                                    <span>No envía ningún email cuando se ejecuta el cron.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Email para notificaciones</label>
                        <input type="email" name="dpuwoo_settings[cron_notify_email]" value="<?php echo esc_attr($notify_email); ?>" class="dpuwoo-field__input" placeholder="<?php echo esc_attr($notify_email_placeholder); ?>">
                        <p class="dpuwoo-field__hint">Por defecto se usa el email del administrador: <?php echo esc_html($notify_email_placeholder); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="dpuwoo-form-actions">
            <button type="submit" class="dpuwoo-btn dpuwoo-btn--primary">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Guardar configuración
            </button>
        </div>

    </form>

</div>

<script>
jQuery(document).ready(function($) {
    // Load currencies on page load if provider is already selected
    var initialProvider = $('#dpuwoo-api-provider').val();
    if (initialProvider) {
        loadCurrencies(initialProvider);
    }
    
    // Collapsible sections
    $('.dpuwoo-collapsible').on('click', function() {
        var $btn = $(this);
        var section = $btn.data('section');
        var $content = $('#section-' + section);
        
        $btn.toggleClass('dpuwoo-collapsible--expanded');
        $content.slideToggle(200);
    });

    // Frequency radio styling
    $('.dpuwoo-frequency-option input').on('change', function() {
        $('.dpuwoo-frequency-option').removeClass('selected');
        $(this).closest('.dpuwoo-frequency-option').addClass('selected');
    });

    // Form submission feedback
    $('#dpuwoo-automation-form').on('submit', function() {
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<svg width="14" height="14" class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Guardando...');
    });

    // Dynamic API provider change - load currencies
    $(document).on('change', '#dpuwoo-api-provider', function() {
        var provider = $(this).val();
        if (provider) {
            loadCurrencies(provider);
        }
    });

    // Function to load currencies
    function loadCurrencies(provider) {
        var $typeSelect = $('#dpuwoo-api-type');
        var $hint = $('#dpuwoo-currency-hint');
        
        if (!provider) {
            $typeSelect.empty().append($('<option>', { value: '', text: 'Primero seleccioná proveedor' }));
            $hint.text('Seleccioná un proveedor');
            return;
        }
        
        $typeSelect.empty().append($('<option>', { value: '', text: 'Cargando...' }));
        
        $.post(dpuwoo_ajax.ajax_url, {
            action: 'dpuwoo_get_currencies',
            provider: provider,
            nonce: dpuwoo_ajax.nonce
        }, function(res) {
            $typeSelect.empty();
            
            if (!res.success || !res.data.currencies || res.data.currencies.length === 0) {
                $typeSelect.append($('<option>', { value: '', text: 'Error al cargar' }));
                $hint.text('No se pudieron obtener las monedas');
                return;
            }
            
            var currencies = res.data.currencies;
            
            // Separate by category
            var hasCategories = currencies[0] && currencies[0].category !== undefined;
            var dollarTypes = [];
            var otherCurrencies = [];
            
            if (hasCategories) {
                dollarTypes = currencies.filter(function(c) { return c.category === 'dollar_types'; });
                otherCurrencies = currencies.filter(function(c) { return c.category !== 'dollar_types'; });
                
                // Sort dollar types by predefined order
                var dollarOrder = ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta', 'mayorista', 'cripto', 'mep', 'solidario'];
                dollarTypes.sort(function(a, b) {
                    var aIdx = dollarOrder.indexOf(a.key);
                    var bIdx = dollarOrder.indexOf(b.key);
                    return (aIdx === -1 ? 999 : aIdx) - (bIdx === -1 ? 999 : bIdx);
                });
            } else {
                otherCurrencies = currencies;
            }
            
            // Sort other currencies by name
            otherCurrencies.sort(function(a, b) {
                var nameA = a.name || a.code || '';
                var nameB = b.name || b.code || '';
                return nameA.localeCompare(nameB);
            });
            
            // Add dollar types
            if (dollarTypes.length > 0) {
                $typeSelect.append($('<option>', { value: '', text: '--- Tipos de Dólar ---', disabled: true }));
                $.each(dollarTypes, function(i, curr) {
                    $typeSelect.append($('<option>', { value: curr.key, text: curr.name }));
                });
            }
            
            // Add other currencies
            if (otherCurrencies.length > 0) {
                $typeSelect.append($('<option>', { value: '', text: '--- Monedas ---', disabled: true }));
                $.each(otherCurrencies, function(i, curr) {
                    var key = curr.key || curr.code || '';
                    var name = curr.name || key;
                    $typeSelect.append($('<option>', { value: key, text: name }));
                });
            }
            
            $hint.text(res.data.count + ' monedas disponibles');
            
        }).fail(function() {
            $typeSelect.empty().append($('<option>', { value: '', text: 'Error de conexión' }));
            $hint.text('Error de conexión con el servidor');
        });
    }
});

    // Frequency radio styling
    $('.dpuwoo-frequency-option input').on('change', function() {
        $('.dpuwoo-frequency-option').removeClass('selected');
        $(this).closest('.dpuwoo-frequency-option').addClass('selected');
    });

    // Form submission feedback
    $('#dpuwoo-automation-form').on('submit', function() {
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<svg width="14" height="14" class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Guardando...');
    });

    // Dynamic API provider change - load currencies
    $(document).on('change', '#dpuwoo-api-provider', function() {
        var provider = $(this).val();
        var $typeSelect = $('#dpuwoo-api-type');
        var $hint = $('#dpuwoo-currency-hint');
        
        if (!provider) {
            $typeSelect.empty().append($('<option>', { value: '', text: 'Primero seleccioná proveedor' }));
            $hint.text('Seleccioná un proveedor');
            return;
        }
        
        // DolarAPI: Load from API with country-specific data
        if (provider === 'dolarapi') {
            $typeSelect.empty().append($('<option>', { value: '', text: 'Cargando...' }));
            $hint.text('Monedas disponibles para <?php echo esc_js($country_name); ?>');
            
            $.post(dpuwoo_ajax.ajax_url, {
                action: 'dpuwoo_get_currencies',
                provider: provider,
                nonce: dpuwoo_ajax.nonce
            }, function(res) {
                $typeSelect.empty();
                
                if (res.success && res.data.currencies && res.data.currencies.length > 0) {
                    var currencies = res.data.currencies;
                    
                    // Separate by category
                    var dollarTypes = currencies.filter(function(c) { return c.category === 'dollar_types'; });
                    var otherCurrencies = currencies.filter(function(c) { return c.category === 'foreign_currencies'; });
                    
                    // Sort dollar types by predefined order
                    var dollarOrder = ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta', 'mayorista', 'cripto', 'mep', 'solidario'];
                    dollarTypes.sort(function(a, b) {
                        var aIdx = dollarOrder.indexOf(a.key);
                        var bIdx = dollarOrder.indexOf(b.key);
                        return (aIdx === -1 ? 999 : aIdx) - (bIdx === -1 ? 999 : bIdx);
                    });
                    
                    // Sort other currencies by name
                    otherCurrencies.sort(function(a, b) {
                        return (a.name || '').localeCompare(b.name || '');
                    });
                    
                    // Add USD first
                    $typeSelect.append($('<option>', { value: 'USD', text: 'USD - Dólar' }));
                    
                    // Add dollar types
                    if (dollarTypes.length > 0) {
                        $typeSelect.append($('<option>', { value: '', text: '--- Tipos de Dólar ---', disabled: true }));
                        $.each(dollarTypes, function(i, curr) {
                            $typeSelect.append($('<option>', { value: curr.key, text: curr.name }));
                        });
                    }
                    
                    // Add other currencies
                    if (otherCurrencies.length > 0) {
                        $typeSelect.append($('<option>', { value: '', text: '--- Monedas del País ---', disabled: true }));
                        $.each(otherCurrencies, function(i, curr) {
                            var displayText = curr.name + ' (' + curr.key.toUpperCase() + ')';
                            $typeSelect.append($('<option>', { value: curr.key, text: displayText }));
                        });
                    }
                    
                } else {
                    $typeSelect.append($('<option>', { value: '', text: 'No hay monedas disponibles' }));
                }
            }).fail(function() {
                $typeSelect.empty().append($('<option>', { value: '', text: 'Error de conexión' }));
            });
            
        } else {
            // CurrencyAPI / ExchangeRate: Load from API
            $typeSelect.empty().append($('<option>', { value: '', text: 'Cargando...' }));
            $hint.text('Obteniendo monedas disponibles...');
            
            $.post(dpuwoo_ajax.ajax_url, {
                action: 'dpuwoo_get_currencies',
                provider: provider,
                nonce: dpuwoo_ajax.nonce
            }, function(res) {
                $typeSelect.empty();
                
                if (res.success && res.data.currencies && res.data.currencies.length > 0) {
                    var currencies = res.data.currencies;
                    
                    // Sort alphabetically
                    currencies.sort(function(a, b) {
                        return (a.code || '').localeCompare(b.code || '');
                    });
                    
                    // Common currencies first
                    var commonOrder = ['USD', 'EUR', 'GBP', 'ARS', 'BRL', 'CLP', 'MXN', 'COP', 'PEN', 'UYU'];
                    var common = currencies.filter(function(c) { return commonOrder.indexOf(c.code) !== -1; });
                    var others = currencies.filter(function(c) { return commonOrder.indexOf(c.code) === -1; });
                    
                    // Sort common by predefined order
                    common.sort(function(a, b) {
                        return commonOrder.indexOf(a.code) - commonOrder.indexOf(b.code);
                    });
                    
                    // Add common currencies
                    if (common.length > 0) {
                        $typeSelect.append($('<option>', { value: '', text: '--- Monedas comunes ---', disabled: true }));
                        $.each(common, function(i, curr) {
                            $typeSelect.append($('<option>', { value: curr.code, text: curr.code + ' - ' + curr.name }));
                        });
                    }
                    
                    // Add other currencies
                    if (others.length > 0) {
                        $typeSelect.append($('<option>', { value: '', text: '--- Otras ---', disabled: true }));
                        $.each(others, function(i, curr) {
                            $typeSelect.append($('<option>', { value: curr.code, text: curr.code + ' - ' + curr.name }));
                        });
                    }
                    
                    $hint.text('Monedas disponibles: ' + res.data.currencies.length);
                } else {
                    $typeSelect.append($('<option>', { value: '', text: 'Error al cargar' }));
                    $hint.text('No se pudieron obtener las monedas');
                }
            }).fail(function() {
                $typeSelect.empty().append($('<option>', { value: '', text: 'Error de conexión' }));
                $hint.text('Error de conexión con el servidor');
            });
        }
    });
});
</script>
