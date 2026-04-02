<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts = get_option('dpuwoo_settings', []);

// Check if reference rate is already set
$has_reference_rate = isset($opts['origin_exchange_rate']) && $opts['origin_exchange_rate'] > 0;

// Get connected APIs
$connected_apis = $opts['connected_apis'] ?? [];
$is_currencyapi_connected = !empty($opts['currencyapi_api_key']);
$is_exchangerate_connected = !empty($opts['exchangerate_api_key']);

// Reference currency and rate
$reference_currency = $opts['reference_currency'] ?? 'USD';
$origin_exchange_rate = $opts['origin_exchange_rate'] ?? '';

$api_providers_list = [
    'dolarapi'      => 'DolarAPI.com',
    'currencyapi'   => 'CurrencyAPI',
    'exchangerate' => 'ExchangeRate-API',
];
?>

<div class="wrap dpuwoo-admin">

    <!-- Header -->
    <div class="dpuwoo-header">
        <div class="dpuwoo-header__left">
            <h1 class="dpuwoo-header__title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Configuración
            </h1>
            <p class="dpuwoo-header__subtitle">API Keys y tasa de referencia inicial</p>
        </div>
        <div class="dpuwoo-header__actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_dashboard')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
                Manual
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_automation')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Automatización
            </a>
        </div>
    </div>

    <!-- Info Notice -->
    <div class="dpuwoo-notice dpuwoo-notice--info">
        <div class="dpuwoo-notice__icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="dpuwoo-notice__content">
            <strong>Setup inicial</strong>
            <p>Configurá las API Keys y establecé la tasa de referencia. Esto se hace una sola vez.</p>
        </div>
    </div>

    <form id="dpuwoo-config-form" method="post" action="options.php">

        <?php settings_fields('dpuwoo_settings_group'); ?>

        <!-- API Keys Section -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible dpuwoo-collapsible--expanded" data-section="apikeys">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">API Keys</span>
                <span class="dpuwoo-collapsible__summary">
                    <?php 
                    $connected_count = ($is_currencyapi_connected ? 1 : 0) + ($is_exchangerate_connected ? 1 : 0);
                    echo $connected_count > 0 ? "$connected_count conectada(s)" : 'Ninguna';
                    ?>
                </span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-apikeys">
                <div class="dpuwoo-fields-grid">
                    <!-- DolarAPI (always available) -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">DolarAPI.com</label>
                        <div class="dpuwoo-field__input-wrap">
                            <span class="dpuwoo-field__status dpuwoo-field__status--connected">✓ Conectada</span>
                            <span class="dpuwoo-field__hint">No requiere API Key</span>
                        </div>
                        <input type="hidden" name="dpuwoo_settings[exchangerate_api_key]" value="<?php echo esc_attr($opts['exchangerate_api_key'] ?? ''); ?>">
                    </div>

                    <!-- CurrencyAPI -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">CurrencyAPI</label>
                        <input type="text" name="dpuwoo_settings[currencyapi_api_key]" value="<?php echo esc_attr($opts['currencyapi_api_key'] ?? ''); ?>" class="dpuwoo-field__input" placeholder="Tu API Key">
                        <?php if ($is_currencyapi_connected): ?>
                        <span class="dpuwoo-field__status dpuwoo-field__status--connected">✓ Conectada</span>
                        <?php endif; ?>
                        <button type="button" class="dpuwoo-btn dpuwoo-btn--outline dpuwoo-btn--sm dpuwoo-test-api" data-api="currencyapi">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Test
                        </button>
                    </div>

                    <!-- ExchangeRate -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">ExchangeRate-API</label>
                        <input type="text" name="dpuwoo_settings[exchangerate_api_key]" value="<?php echo esc_attr($opts['exchangerate_api_key'] ?? ''); ?>" class="dpuwoo-field__input" placeholder="Tu API Key">
                        <?php if ($is_exchangerate_connected): ?>
                        <span class="dpuwoo-field__status dpuwoo-field__status--connected">✓ Conectada</span>
                        <?php endif; ?>
                        <button type="button" class="dpuwoo-btn dpuwoo-btn--outline dpuwoo-btn--sm dpuwoo-test-api" data-api="exchangerate">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Test
                        </button>
                    </div>
                </div>
                <div id="dpuwoo-api-test-result" class="dpuwoo-api-test-result"></div>
            </div>
        </div>

        <!-- Reference Rate Section -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible <?php echo $has_reference_rate ? '' : 'dpuwoo-collapsible--expanded'; ?>" data-section="reference">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Tasa de Referencia</span>
                <span class="dpuwoo-collapsible__summary">
                    <?php if ($has_reference_rate): ?>
                        <?php echo esc_html($reference_currency); ?> - $<?php echo number_format($origin_exchange_rate, 2); ?>
                    <?php else: ?>
                        No configurada
                    <?php endif; ?>
                </span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-reference">
                <?php if ($has_reference_rate): ?>
                <div class="dpuwoo-reference-set">
                    <div class="dpuwoo-reference-set__info">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <strong>Tipo de cambio configurado</strong>
                            <p><?php echo esc_html($reference_currency); ?>: <strong>$<?php echo number_format($origin_exchange_rate, 2); ?></strong></p>
                            <p class="dpuwoo-reference-set__hint">Este valor se usa como base cuando no hay historial.</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="dpuwoo-fields-grid">
                    <!-- Reference Currency -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Moneda de referencia</label>
                        <select name="dpuwoo_settings[reference_currency]" class="dpuwoo-field__select" id="dpuwoo-ref-currency">
                            <?php
                            $currencies = [
                                'USD' => 'Dólar USD',
                                'EUR' => 'Euro EUR',
                                'GBP' => 'Libra GBP',
                                'ARS' => 'Peso ARS',
                                'BRL' => 'Real BRL',
                                'CLP' => 'Peso CLP',
                                'MXN' => 'Peso MXN',
                            ];
                            foreach ($currencies as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($reference_currency, $code); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="dpuwoo-field__hint">Moneda para la tasa de referencia.</p>
                    </div>

                    <!-- Origin Exchange Rate -->
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Valor de la tasa</label>
                        <div class="dpuwoo-field__input-wrap">
                            <span class="dpuwoo-field__prefix">$</span>
                            <input type="number" name="dpuwoo_settings[origin_exchange_rate]" value="<?php echo esc_attr($origin_exchange_rate); ?>" step="0.0001" min="0.0001" class="dpuwoo-field__input" id="dpuwoo-ref-rate" placeholder="Ej: 850.00">
                        </div>
                        <p class="dpuwoo-field__hint">Ingresá el valor manualmente o usá "Obtener de API".</p>
                    </div>
                </div>

                <!-- Get from API -->
                <div class="dpuwoo-get-from-api">
                    <button type="button" id="dpuwoo-get-from-api" class="dpuwoo-btn dpuwoo-btn--primary">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Obtener de API
                    </button>
                    <div id="dpuwoo-api-rates-result" class="dpuwoo-api-rates-result"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Save Button -->
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
    // Collapsible sections
    $('.dpuwoo-collapsible').on('click', function() {
        var $btn = $(this);
        var section = $btn.data('section');
        var $content = $('#section-' + section);
        
        $btn.toggleClass('dpuwoo-collapsible--expanded');
        $content.slideToggle(200);
    });

    // Test API connection
    $('.dpuwoo-test-api').on('click', function() {
        var api = $(this).data('api');
        var $result = $('#dpuwoo-api-test-result');
        var $btn = $(this);
        
        $btn.prop('disabled', true).html('<svg class="animate-spin" width="12" height="12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Testeando...');
        
        $result.html('<div class="dpuwoo-notice dpuwoo-notice--info"><div class="dpuwoo-notice__icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div class="dpuwoo-notice__content"><p>Probando conexión...</p></div></div>');
        
        var apiKey = api === 'currencyapi' 
            ? $('input[name="dpuwoo_settings[currencyapi_api_key]"]').val()
            : $('input[name="dpuwoo_settings[exchangerate_api_key]"]').val();
        
        $.post(dpuwoo_ajax.ajax_url, {
            action: 'dpuwoo_test_api',
            api: api,
            api_key: apiKey,
            nonce: dpuwoo_ajax.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Test');
            
            if (res.success) {
                $result.html('<div class="dpuwoo-notice dpuwoo-notice--success"><div class="dpuwoo-notice__icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div class="dpuwoo-notice__content"><strong>¡Conexión exitosa!</strong><p>' + res.data.message + '</p></div></div>');
            } else {
                $result.html('<div class="dpuwoo-notice dpuwoo-notice--error"><div class="dpuwoo-notice__icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div><div class="dpuwoo-notice__content"><strong>Error de conexión</strong><p>' + res.data.message + '</p></div></div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Test');
            $result.html('<div class="dpuwoo-notice dpuwoo-notice--error"><div class="dpuwoo-notice__content"><strong>Error</strong><p>No se pudo conectar con el servidor.</p></div></div>');
        });
    });

    // Get from API button
    $('#dpuwoo-get-from-api').on('click', function() {
        var $btn = $(this);
        var $result = $('#dpuwoo-api-rates-result');
        var currency = $('#dpuwoo-ref-currency').val();
        
        $btn.prop('disabled', true).html('<svg class="animate-spin" width="14" height="14" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Obteniendo...');
        
        $result.html('<div class="dpuwoo-notice dpuwoo-notice--info"><div class="dpuwoo-notice__content"><p>Obteniendo tasas...</p></div></div>');
        
        $.post(dpuwoo_ajax.ajax_url, {
            action: 'dpuwoo_get_rates',
            currency: currency,
            nonce: dpuwoo_ajax.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Obtener de API');
            
            if (res.success && res.data.rates) {
                var rates = res.data.rates;
                var optionsHtml = '<div class="dpuwoo-rates-list"><p class="dpuwoo-rates-list__label">Seleccioná una moneda:</p>';
                
                for (var code in rates) {
                    optionsHtml += '<button type="button" class="dpuwoo-rate-option" data-currency="' + code + '" data-rate="' + rates[code] + '">';
                    optionsHtml += '<span class="dpuwoo-rate-option__currency">' + code + '</span>';
                    optionsHtml += '<span class="dpuwoo-rate-option__rate">$' + parseFloat(rates[code]).toFixed(2) + '</span>';
                    optionsHtml += '</button>';
                }
                optionsHtml += '</div>';
                
                $result.html(optionsHtml);
                
                // Handle rate selection
                $('.dpuwoo-rate-option').on('click', function() {
                    var curr = $(this).data('currency');
                    var rate = $(this).data('rate');
                    
                    $('#dpuwoo-ref-currency').val(curr);
                    $('#dpuwoo-ref-rate').val(rate);
                    
                    $result.html('<div class="dpuwoo-notice dpuwoo-notice--success"><div class="dpuwoo-notice__content"><strong>¡Tasa seleccionada!</strong><p>' + curr + ': $' + parseFloat(rate).toFixed(4) + '</p></div></div>');
                });
            } else {
                $result.html('<div class="dpuwoo-notice dpuwoo-notice--error"><div class="dpuwoo-notice__content"><strong>Error</strong><p>' + (res.data?.message || 'No se pudieron obtener las tasas.') + '</p></div></div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Obtener de API');
            $result.html('<div class="dpuwoo-notice dpuwoo-notice--error"><div class="dpuwoo-notice__content"><strong>Error</strong><p>No se pudo conectar con el servidor.</p></div></div>');
        });
    });

    // Form submit feedback
    $('#dpuwoo-config-form').on('submit', function() {
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<svg class="animate-spin" width="14" height="14" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Guardando...');
    });
});
</script>

<style>
.dpuwoo-field__status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.375rem;
}
.dpuwoo-field__status--connected { color: #16a34a; }
.dpuwoo-field__status--error { color: #dc2626; }

.dpuwoo-api-test-result,
.dpuwoo-api-rates-result {
    margin-top: 1rem;
}

.dpuwoo-reference-set {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: var(--dpu-radius);
    padding: 1.25rem;
}
.dpuwoo-reference-set__info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}
.dpuwoo-reference-set__info svg { color: #16a34a; flex-shrink: 0; }
.dpuwoo-reference-set__info strong { display: block; color: #166534; margin-bottom: 0.25rem; }
.dpuwoo-reference-set__info p { margin: 0; color: #166534; }
.dpuwoo-reference-set__hint { font-size: 0.8125rem; color: #15803d !important; margin-top: 0.5rem !important; }

.dpuwoo-get-from-api {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--dpu-border);
}

.dpuwoo-rates-list {
    margin-top: 1rem;
}
.dpuwoo-rates-list__label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--dpu-text);
    margin-bottom: 0.75rem;
}
.dpuwoo-rate-option {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem 1rem;
    margin: 0.25rem;
    background: var(--dpu-surface);
    border: 1px solid var(--dpu-border);
    border-radius: var(--dpu-radius-sm);
    cursor: pointer;
    transition: all 0.15s;
    min-width: 80px;
}
.dpuwoo-rate-option:hover {
    border-color: var(--upd-500);
    background: var(--upd-50);
}
.dpuwoo-rate-option__currency {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--dpu-text);
}
.dpuwoo-rate-option__rate {
    font-size: 0.875rem;
    font-weight: 700;
    font-family: var(--dpu-font-mono);
    color: var(--upd-600);
}

.dpuwoo-notice--success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}
.dpuwoo-notice--success .dpuwoo-notice__icon { color: #16a34a; }
.dpuwoo-notice--success .dpuwoo-notice__content strong { color: #166534; }
.dpuwoo-notice--success .dpuwoo-notice__content p { color: #166534; }

.dpuwoo-notice--error {
    background: #fef2f2;
    border: 1px solid #fecaca;
}
.dpuwoo-notice--error .dpuwoo-notice__icon { color: #dc2626; }
.dpuwoo-notice--error .dpuwoo-notice__content strong { color: #991b1b; }
.dpuwoo-notice--error .dpuwoo-notice__content p { color: #991b1b; }
</style>
