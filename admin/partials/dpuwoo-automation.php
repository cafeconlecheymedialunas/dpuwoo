<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts         = get_option('dpuwoo_settings', []);
$cron_enabled = $opts['cron_enabled'] ?? 0;
$next_cron    = \Cron::get_next_scheduled_time();

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

$notify_mode = $opts['cron_notify_mode'] ?? 'update_and_notify';
$notify_email = $opts['cron_notify_email'] ?? get_option('admin_email');
$notify_email_placeholder = get_option('admin_email');

$api_providers_list = [
    'dolarapi'       => 'DolarAPI',
    'jsdelivr'     => 'Jsdelivr',
    'cryptoprice'  => 'CoinGecko',
    'moneyconvert' => 'MoneyConvert',
    'hexarate'   => 'HexaRate',
    'foreignrate' => 'ForeignRate',
    'currencyapi'  => 'CurrencyAPI',
    'exchangerate' => 'ExchangeRate-API',
];

$is_currencyapi_connected = !empty($opts['currencyapi_api_key']);
$is_exchangerate_connected = !empty($opts['exchangerate_api_key']);
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
                        <?php
                        $now = current_time('timestamp');
                        $is_past = $next_cron < $now;
                        ?>
                        Próxima ejecución: <strong><?php echo esc_html(wp_date('d/m/Y H:i', $next_cron)); ?></strong>
                        <?php if ($is_past): ?>
                            <span style="color: #dc2626;">(atrasado)</span>
                        <?php endif; ?>
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

        <!-- API for Automation (override Settings) -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="cron-api">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0 3-4.03 3-9s1.343-9 3-9"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">API (opcional)</span>
                <span class="dpuwoo-collapsible__summary"><?php echo esc_html($api_providers_list[$cron_provider] ?? 'Usar configuración'); ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-cron-api">
                <div class="dpuwoo-fields-grid">
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Proveedor</label>
                        <select name="dpuwoo_settings[cron_api_provider]" class="dpuwoo-field__select">
                            <option value="" <?php selected($cron_provider, ''); ?>>Usar configuración de Configuración</option>
                            <option value="dolarapi" <?php selected($cron_provider, 'dolarapi'); ?>>DolarAPI.com (Gratuita)</option>
                            <option value="currencyapi" <?php selected($cron_provider, 'currencyapi'); ?> <?php echo !$is_currencyapi_connected ? 'disabled' : ''; ?>>CurrencyAPI</option>
                            <option value="exchangerate" <?php selected($cron_provider, 'exchangerate'); ?> <?php echo !$is_exchangerate_connected ? 'disabled' : ''; ?>>ExchangeRate-API</option>
                        </select>
                        <p class="dpuwoo-field__hint">Dejá en blanco para usar la API de Configuración</p>
                    </div>
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Moneda</label>
                        <select name="dpuwoo_settings[cron_dollar_type]" class="dpuwoo-field__select" id="dpuwoo-cron-dollar-type">
                            <option value="">Seleccionar...</option>
                        </select>
                    </div>
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
