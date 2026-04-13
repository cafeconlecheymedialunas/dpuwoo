<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts = get_option('dpuwoo_settings', []);

// Reference rate (from selected API - uses origin_exchange_rate)
$has_reference_rate = isset($opts['origin_exchange_rate']) && $opts['origin_exchange_rate'] > 0;
$reference_exchange_rate = $opts['origin_exchange_rate'] ?? '';

// Get connected APIs
$connected_apis = $opts['connected_apis'] ?? [];
$is_currencyapi_connected = !empty($opts['currencyapi_api_key']);
$is_exchangerate_connected = !empty($opts['exchangerate_api_key']);

// Reference currency
$reference_currency = $opts['reference_currency'] ?? 'USD';

$api_providers_list = [
    'dolarapi'      => 'DolarAPI.com',
    'jsdelivr'     => 'Jsdelivr Currency',
    'cryptoprice'  => 'Crypto Price API',
    'currencyapi'  => 'CurrencyAPI',
    'exchangerate' => 'ExchangeRate-API',
];

// Get currencies from API for reference currency select (lazy load via JS)
$provider = $opts['api_provider'] ?? 'dolarapi';

$all_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
$excluded_cats = $opts['exclude_categories'] ?? [];
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
            <p class="dpuwoo-header__subtitle">Configuración inicial del plugin</p>
        </div>
        <div class="dpuwoo-header__actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_automation')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Automatización
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_dashboard')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
                Manual
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_logs')); ?>" class="dpuwoo-btn dpuwoo-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Dashboard
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

        <!-- API Provider Section -->
        <?php
        $current_provider = $opts['api_provider'] ?? 'dolarapi';
        $providers = [
            'dolarapi' => [
                'label'    => 'DolarAPI.com',
                'desc'   => 'Fiat Latam (AR, CL, VE, UY, MX, BO, BR, CO)',
                'tag'    => '✓ Listo',
                'tag_ok' => true,
                'key_field' => null,
                'icon_bg' => '#e0f2fe',
                'icon_color' => '#0284c7',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 2a2 2 0 00-2 2v1H4a1 1 0 00-1 1v3a1 1 0 001 1h1v7a2 2 0 002 2h6a2 2 0 002-2v-7h1a1 1 0 001-1V4a1 1 0 00-1-1h-1V4a2 2 0 00-2-2h-2z"/>',
            ],
            'jsdelivr' => [
                'label'  => 'Jsdelivr Currency',
                'desc'  => 'Fiat mundial (170+ monedas)',
                'tag'   => '✓ Listo',
                'tag_ok' => true,
                'key_field' => null,
                'icon_bg' => '#e0e7ff',
                'icon_color' => '#4f46e5',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 2a2 2 0 00-2 2v1H4a1 1 0 00-1 1v3a1 1 0 001 1h1v7a2 2 0 002 2h6a2 2 0 002-2v-7h1a1 1 0 001-1V4a1 1 0 00-1-1h-1V4a2 2 0 00-2-2h-2z"/>',
            ],
            'cryptoprice' => [
                'label'  => 'Crypto Price API',
                'desc'  => 'Criptomonedas (150+ coins)',
                'tag'   => '✓ Listo',
                'tag_ok' => true,
                'key_field' => null,
                'icon_bg' => '#fef3c7',
                'icon_color' => '#f59e0b',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            'currencyapi' => [
                'label'     => 'CurrencyAPI',
                'desc'      => '170+ monedas · Internacional',
                'tag'       => $is_currencyapi_connected ? '✓ Conectada' : 'Requiere API Key',
                'tag_ok'    => $is_currencyapi_connected,
                'key_field' => 'currencyapi_api_key',
                'key_label' => 'API Key',
                'key_link'  => 'https://currencyapi.com',
                'icon_bg'   => '#fef3c7',
                'icon_color' => '#d97706',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            'exchangerate' => [
                'label'     => 'ExchangeRate-API',
                'desc'      => '160+ monedas · Internacional',
                'tag'       => $is_exchangerate_connected ? '✓ Conectada' : 'Requiere API Key',
                'tag_ok'   => $is_exchangerate_connected,
                'key_field' => 'exchangerate_api_key',
                'key_label' => 'API Key',
                'key_link'  => 'https://www.exchangerate-api.com',
                'icon_bg'   => '#f0fdf4',
                'icon_color' => '#16a34a',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            ],
        ];
        ?>
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible dpuwoo-collapsible--expanded" data-section="apikeys">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Conexión API</span>
                <span class="dpuwoo-collapsible__summary">
                    <?php 
                    $provider_labels = [
                        'dolarapi' => 'DolarAPI',
                        'jsdelivr' => 'Jsdelivr',
                        'cryptoprice' => 'Crypto Price',
                        'currencyapi' => 'CurrencyAPI',
                        'exchangerate' => 'ExchangeRate'
                    ];
                    echo esc_html($provider_labels[$current_provider] ?? $current_provider);
                    ?>
                </span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-apikeys">
                <!-- Provider Cards Grid -->
                <div class="dpuwoo-provider-grid">
                    <?php foreach ($providers as $slug => $p): 
                        $selected = ($slug === $current_provider);
                        $has_key = !empty($opts[$p['key_field']] ?? '');
                    ?>
                    <div class="dpuwoo-provider-card <?php echo $selected ? 'dpuwoo-provider-card--selected' : ''; ?>" 
                         data-provider="<?php echo esc_attr($slug); ?>"
                         onclick="selectProvider('<?php echo esc_attr($slug); ?>')">
                        <div class="dpuwoo-provider-card__radio">
                            <div class="dpuwoo-provider-card__radio-inner <?php echo $selected ? 'dpuwoo-provider-card__radio-inner--checked' : ''; ?>"></div>
                        </div>
                        <div class="dpuwoo-provider-card__icon" style="background: <?php echo esc_attr($p['icon_bg']); ?>">
                            <svg width="20" height="20" fill="none" stroke="<?php echo esc_attr($p['icon_color']); ?>" viewBox="0 0 24 24" stroke-width="2"><?php echo $p['icon']; ?></svg>
                        </div>
                        <div class="dpuwoo-provider-card__content">
                            <div class="dpuwoo-provider-card__name"><?php echo esc_html($p['label']); ?></div>
                            <div class="dpuwoo-provider-card__desc"><?php echo esc_html($p['desc']); ?></div>
                        </div>
                        <div class="dpuwoo-provider-card__status">
                            <?php if (in_array($slug, ['dolarapi', 'jsdelivr', 'cryptoprice'])): ?>
                                <span class="dpuwoo-provider-card__badge dpuwoo-provider-card__badge--success"><?php echo $p['tag']; ?></span>
                            <?php elseif ($has_key): ?>
                                <span class="dpuwoo-provider-card__badge dpuwoo-provider-card__badge--success"><?php echo $p['tag']; ?></span>
                            <?php else: ?>
                                <span class="dpuwoo-provider-card__badge">API Key</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="dpuwoo_settings[api_provider]" id="dpuwoo-api-provider-input" value="<?php echo esc_attr($current_provider); ?>">

                <!-- API Key Input Panels (show only for selected provider that requires key) -->
                <div class="dpuwoo-api-key-panels">
                    <div class="dpuwoo-api-key-panel" id="panel-currencyapi" style="display: <?php echo $current_provider === 'currencyapi' ? 'block' : 'none'; ?>;">
                        <div class="dpuwoo-api-key-panel__header">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            <span>CurrencyAPI</span>
                        </div>
                        <div class="dpuwoo-api-key-panel__body">
                            <label class="dpuwoo-api-key-panel__label">
                                <?php echo esc_html($p['key_label']); ?>
                                <a href="https://currencyapi.com" target="_blank" rel="noopener">Obtener key →</a>
                            </label>
                            <div class="dpuwoo-api-key-panel__input-wrap">
                                <input type="password" name="dpuwoo_settings[currencyapi_api_key]" value="<?php echo esc_attr($opts['currencyapi_api_key'] ?? ''); ?>" class="dpuwoo-field__input" placeholder="Tu API Key">
                                <button type="button" class="dpuwoo-toggle-pass" onclick="toggleApiKey('currencyapi')">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <button type="button" class="dpuwoo-btn dpuwoo-btn--outline dpuwoo-btn--sm dpuwoo-test-api" data-api="currencyapi">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Test
                            </button>
                        </div>
                    </div>

                    <div class="dpuwoo-api-key-panel" id="panel-exchangerate" style="display: <?php echo $current_provider === 'exchangerate' ? 'block' : 'none'; ?>;">
                        <div class="dpuwoo-api-key-panel__header">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            <span>ExchangeRate-API</span>
                        </div>
                        <div class="dpuwoo-api-key-panel__body">
                            <label class="dpuwoo-api-key-panel__label">
                                API Key
                                <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener">Obtener key →</a>
                            </label>
                            <div class="dpuwoo-api-key-panel__input-wrap">
                                <input type="password" name="dpuwoo_settings[exchangerate_api_key]" value="<?php echo esc_attr($opts['exchangerate_api_key'] ?? ''); ?>" class="dpuwoo-field__input" placeholder="Tu API Key">
                                <button type="button" class="dpuwoo-toggle-pass" onclick="toggleApiKey('exchangerate')">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <button type="button" class="dpuwoo-btn dpuwoo-btn--outline dpuwoo-btn--sm dpuwoo-test-api" data-api="exchangerate">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Test
                            </button>
                        </div>
                    </div>
                </div>
                <div id="dpuwoo-api-test-result" class="dpuwoo-api-test-result"></div>
            </div>
        </div>

        <!-- Reference Currency Section -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible <?php echo !empty($reference_currency) ? '' : 'dpuwoo-collapsible--expanded'; ?>" data-section="reference">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Moneda de Referencia</span>
                <span class="dpuwoo-collapsible__summary">
                    <?php if (!empty($reference_currency)): ?>
                        <?php echo esc_html($reference_currency); ?>
                    <?php else: ?>
                        Seleccioná
                    <?php endif; ?>
                </span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-reference">
                <div class="dpuwoo-reference-set">
                    <div class="dpuwoo-reference-set__info">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <strong>Seleccioná la moneda de referencia</strong>
                            <p class="dpuwoo-reference-set__hint">Elegí la moneda que usás para tus precios en la tienda.</p>
                        </div>
                    </div>
                    <div class="dpuwoo-fields-grid" style="margin-top: 16px;">
                        <div class="dpuwoo-field">
                            <label class="dpuwoo-field__label">Moneda</label>
                            <select name="dpuwoo_settings[reference_currency]" id="dpuwoo-ref-currency" class="dpuwoo-field__select" data-selected="<?php echo esc_attr($reference_currency); ?>">
                                <option value="">Cargando...</option>
                            </select>
                            <p class="dpuwoo-field__hint">Las monedas se cargan desde la API seleccionada.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rules Section -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible" data-section="rules">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Reglas de Precio</span>
                <span class="dpuwoo-collapsible__summary"><?php echo number_format(floatval($opts['margin'] ?? 0), 1); ?>% margen · <?php echo number_format(floatval($opts['threshold'] ?? 0.5), 1); ?>% umbral</span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-rules">
                <div class="dpuwoo-fields-grid">
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Margen de corrección</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[margin]" value="<?php echo esc_attr($opts['margin'] ?? ''); ?>" step="0.01" class="dpuwoo-field__input" placeholder="0">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">+% absorbe menos suba · -% absorbe parte de la suba · Default: 0%</p>
                    </div>
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Variación mínima</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[threshold]" value="<?php echo esc_attr($opts['threshold'] ?? ''); ?>" step="0.01" min="0" class="dpuwoo-field__input" placeholder="0.5">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">Protege contra fluctuaciones pequeñas · Default: 0.5%</p>
                    </div>
                    <div class="dpuwoo-field">
                        <label class="dpuwoo-field__label">Variación máxima</label>
                        <div class="dpuwoo-field__input-wrap">
                            <input type="number" name="dpuwoo_settings[threshold_max]" value="<?php echo esc_attr($opts['threshold_max'] ?? ''); ?>" step="0.01" min="0" class="dpuwoo-field__input" placeholder="Sin límite">
                            <span class="dpuwoo-field__suffix">%</span>
                        </div>
                        <p class="dpuwoo-field__hint">Frenó de seguridad · Dejar vacío para sin límite</p>
                    </div>
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

        <!-- Rounding Section -->
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

        <!-- Exclusions Section -->
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
