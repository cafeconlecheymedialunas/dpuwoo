<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts = get_option('prixy_settings', []);
$origin_rate = floatval($opts['origin_exchange_rate'] ?? 0);
$rate_locked = $origin_rate > 0 || !empty($opts['origin_rate_locked']);

// Obtener tasa automáticamente si no hay una guardada
if (!$rate_locked && $origin_rate <= 0) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/ars.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        $usd_value = floatval($data['ars']['usd'] ?? 0);
        if ($usd_value > 0) {
            $origin_rate = 1 / $usd_value;
        }
    }
}

// Get products stats
$product_count = wp_count_posts('product');
$total_products = $product_count->publish ?? 0;
$products_done = $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM {$wpdb->prefix}prixy_run_items WHERE status = 'updated' AND product_id > 0");
$needs_setup = $total_products - $products_done;

// Reference rate
$has_reference_rate = $origin_rate > 0;
$reference_exchange_rate = $origin_rate;

// Get connected APIs
$connected_apis = $opts['connected_apis'] ?? [];
$is_currencyapi_connected = !empty($opts['currencyapi_api_key']);
$is_exchangerate_connected = !empty($opts['exchangerate_api_key']);

// Reference currency
$reference_currency = $opts['reference_currency'] ?? 'USD';

$api_providers_list = [
    'dolarapi'       => 'DolarAPI',
    'jsdelivr'       => 'Jsdelivr',
    'cryptoprice'    => 'CoinGecko',
    'moneyconvert'   => 'MoneyConvert',
    'hexarate'       => 'HexaRate',
    'foreignrate'    => 'ForeignRate',
    'currencyapi'   => 'CurrencyAPI',
    'exchangerate'  => 'ExchangeRate-API',
];

$provider = $opts['api_provider'] ?? 'dolarapi';

$all_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
$excluded_cats = $opts['exclude_categories'] ?? [];
?>

<!-- prixy-settings.php starting here -->
<div class="wrap prixy-admin">

    <!-- Header -->
    <div class="prixy-header">
        <div class="prixy-header__left">
            <h1 class="prixy-header__title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Configuración
            </h1>
            <p class="prixy-header__subtitle">Configuración del plugin Dollar Sync</p>
        </div>
        <div class="prixy-header__actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_automation')); ?>" class="prixy-btn prixy-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Automatización
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_dashboard')); ?>" class="prixy-btn prixy-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
                Manual
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_logs')); ?>" class="prixy-btn prixy-btn--ghost">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Dashboard
            </a>
        </div>
    </div>

    <?php
    // STEP 1: Si no hay tasa guardada o no está lockeada, mostrar onboarding
    if (!$rate_locked):

        $all_products = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        ]);
        $preview_products = [];
        while ($all_products->have_posts()) {
            $all_products->the_post();
            $prod = wc_get_product(get_the_ID());
            if ($prod) {
                $rp = floatval($prod->get_regular_price());
                if ($rp > 0) {
                    $preview_products[] = [
                        'id' => $prod->get_id(),
                        'name' => $prod->get_name(),
                        'ars' => $rp,
                    ];
                }
            }
        }
        wp_reset_postdata();
    ?>

    <!-- ONBOARDING -->
    <div style="max-width: 900px; margin: 0 auto; padding-top: 20px;">

        <div style="text-align: center; margin-bottom: 24px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #111827; margin: 0 0 8px;">Setup Inicial - Dollar Sync</h1>
            <p style="font-size: 14px; color: #6b7280; margin: 0;">Configurá la tasa de referencia para comenzar a sincronizar tus precios</p>
        </div>

        <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 12px; padding: 16px 20px; color: white; margin-bottom: 24px; display: flex; gap: 16px; align-items: center;">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div style="font-size: 14px; opacity: 0.95;">
                <strong>¿Por qué necesito esto?</strong> Ingresá el valor del dólar cuando cargaste tus precios. Así Dollar Sync puede detectar cambios y actualizar automáticamente.
            </div>
        </div>

        <!-- Input de Tasa -->
        <div style="background: white; border: 2px solid #e5e7eb; border-radius: 16px; padding: 24px; margin-bottom: 24px; display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #6b7280; margin-bottom: 8px;">TASA DE REFERENCIA (ARS/USD)</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 18px; font-weight: 700;">$</span>
                    <input type="number" id="prixy-origin-rate" value="<?php echo $origin_rate > 0 ? esc_attr(number_format($origin_rate, 2, '.', '')) : ''; ?>" step="0.01" min="0.01" placeholder="ej: 1368.00" style="padding: 14px 16px 14px 36px; border: 2px solid #d1d5db; border-radius: 10px; font-size: 20px; font-weight: 700; width: 100%; max-width: 200px; outline: none; color: #111827;">
                </div>
            </div>
            <div id="prixy-rate-msg" style="font-size: 13px; flex: 1;"></div>
            <button type="button" id="prixy-save-origin-rate" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; border-radius: 10px; padding: 14px 32px; font-size: 15px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(99,102,241,0.4); white-space: nowrap;">
                Confirmar y continuar
            </button>
        </div>

        <!-- Preview de productos con calculo en vivo -->
        <div id="products-preview-area" style="background: white; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; margin-bottom: 24px;">
            <div style="background: #f9fafb; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 600; color: #111827;">Vista previa de productos (cálculo en vivo)</span>
                <span id="products-count" style="font-size: 13px; color: #6b7280;"></span>
            </div>
            <div id="products-table-container" style="max-height: 400px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead style="background: #f9fafb; position: sticky; top: 0;">
                        <tr>
                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Producto</th>
                            <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Precio ARS</th>
                            <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Precio USD</th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        <?php foreach ($preview_products as $pp): ?>
                        <tr>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #f3f4f6;"><?php echo esc_html($pp['name']); ?></td>
                            <td style="padding: 8px 12px; text-align: right; border-bottom: 1px solid #f3f4f6;" data-ars="<?php echo esc_attr($pp['ars']); ?>">$<?php echo number_format($pp['ars'], 2); ?></td>
                            <td class="usd-cell" style="padding: 8px 12px; text-align: right; border-bottom: 1px solid #f3f4f6; color: #16a34a; font-weight: 600;">—</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php
    // STEP 2: Si la tasa está lockeada pero hay productos por procesar
    elseif ($needs_setup > 0):
    ?>

    <!-- PROCESSING: Tasa guardada, procesando automáticamente -->
    <div style="max-width: 700px; margin: 0 auto; padding-top: 20px;">

        <div id="prixy-settings-metadata" style="display:none;" data-products-done="<?php echo intval($products_done); ?>" data-total-products="<?php echo intval($total_products); ?>" data-origin-rate="<?php echo floatval($origin_rate); ?>"></div>

        <div style="background: linear-gradient(135deg, var(--upd-600), var(--upd-700)); border-radius: 16px; padding: 28px; color: white; margin-bottom: 24px;">
            <h2 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Procesando productos...
            </h2>
            <p style="margin: 0; opacity: 0.9; font-size: 14px;">
                Configurando <strong><?php echo number_format($needs_setup); ?></strong> productos con la tasa de referencia.
            </p>
            <div style="margin-top: 20px;">
                <div style="background: rgba(255,255,255,0.2); border-radius: 999px; height: 10px; overflow: hidden;">
                    <div id="setup-progress-fill" style="background: white; height: 100%; width: <?php echo $total_products > 0 ? (($products_done / $total_products) * 100) : 0; ?>%; transition: width 0.3s;"></div>
                </div>
                <div id="setup-count" style="font-size: 13px; margin-top: 8px;">
                    <?php echo number_format($products_done); ?> / <?php echo number_format($total_products); ?> productos
                </div>
            </div>
        </div>

        <div id="setup-products-list" style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; max-height: 300px; overflow-y: auto; font-size: 13px; margin-bottom: 24px;">
        </div>

        <div id="setup-complete-msg" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; text-align: center;">
            <svg width="48" height="48" fill="none" stroke="#22c55e" viewBox="0 0 24 24" stroke-width="2" style="margin: 0 auto 12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 style="margin: 0 0 8px; color: #166534; font-size: 18px;">¡Completado!</h3>
            <p style="margin: 0; color: #166534; font-size: 14px;">Redirigiendo a la configuración...</p>
        </div>

    </div>

    <?php
    // STEP 3: Todo completo, mostrar settings normal
    else:
    ?>

    <!-- Info Notice -->
    <div class="prixy-notice prixy-notice--info">
        <div class="prixy-notice__icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="prixy-notice__content">
            <strong>Configuración</strong>
            <p>Configurá el provider de API y la moneda de referencia.</p>
        </div>
    </div>

    <form id="prixy-config-form" method="post" action="options.php">

        <?php settings_fields('prixy_settings_group'); ?>

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
                'label'  => 'CoinGecko',
                'desc'  => 'Criptomonedas (100+ coins)',
                'tag'   => '✓ Listo',
                'tag_ok' => true,
                'key_field' => null,
                'icon_bg' => '#fef3c7',
                'icon_color' => '#f59e0b',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            'moneyconvert' => [
                'label'   => 'MoneyConvert',
                'desc'    => 'Fiat mundial (182+ monedas)',
                'tag'     => '✓ Listo',
                'tag_ok'  => true,
                'key_field' => null,
                'icon_bg'  => '#e0f2fe',
                'icon_color' => '#0891b2',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            'hexarate' => [
                'label'   => 'HexaRate',
                'desc'    => 'Fiat mundial (170+ monedas)',
                'tag'     => '✓ Listo',
                'tag_ok'  => true,
                'key_field' => null,
                'icon_bg'  => '#f0f9ff',
                'icon_color' => '#0284c7',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            'foreignrate' => [
                'label'   => 'ForeignRate',
                'desc'    => 'Fiat mundial (150+ monedas)',
                'tag'     => '✓ Listo',
                'tag_ok'  => true,
                'key_field' => null,
                'icon_bg'  => '#f5f3ff',
                'icon_color' => '#7c3aed',
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
        <div class="prixy-section">
            <button type="button" class="prixy-collapsible prixy-collapsible--expanded" data-section="apikeys">
                <span class="prixy-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </span>
                <span class="prixy-collapsible__title">Conexión API</span>
                <span class="prixy-collapsible__summary">
                    <?php 
                    $provider_labels = [
                        'dolarapi'      => 'DolarAPI',
                        'jsdelivr'     => 'Jsdelivr',
                        'cryptoprice'  => 'CoinGecko',
                        'moneyconvert' => 'MoneyConvert',
                        'hexarate'     => 'HexaRate',
                        'foreignrate'  => 'ForeignRate',
                        'currencyapi' => 'CurrencyAPI',
                        'exchangerate' => 'ExchangeRate-API'
                    ];
                    echo esc_html($provider_labels[$current_provider] ?? $current_provider);
                    ?>
                </span>
                <span class="prixy-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="prixy-collapsible__content" id="section-apikeys">
                <!-- Provider Cards Grid -->
                <div class="prixy-provider-grid">
                    <?php foreach ($providers as $slug => $p): 
                        $selected = ($slug === $current_provider);
                        $has_key = !empty($opts[$p['key_field']] ?? '');
                    ?>
                    <div class="prixy-provider-card <?php echo $selected ? 'prixy-provider-card--selected' : ''; ?>" 
                         data-provider="<?php echo esc_attr($slug); ?>"
                         onclick="selectProvider('<?php echo esc_attr($slug); ?>')">
                        <div class="prixy-provider-card__radio">
                            <div class="prixy-provider-card__radio-inner <?php echo $selected ? 'prixy-provider-card__radio-inner--checked' : ''; ?>"></div>
                        </div>
                        <div class="prixy-provider-card__icon" style="background: <?php echo esc_attr($p['icon_bg']); ?>">
                            <svg width="20" height="20" fill="none" stroke="<?php echo esc_attr($p['icon_color']); ?>" viewBox="0 0 24 24" stroke-width="2"><?php echo $p['icon']; ?></svg>
                        </div>
                        <div class="prixy-provider-card__content">
                            <div class="prixy-provider-card__name"><?php echo esc_html($p['label']); ?></div>
                            <div class="prixy-provider-card__desc"><?php echo esc_html($p['desc']); ?></div>
                        </div>
                        <div class="prixy-provider-card__status">
                            <?php if (in_array($slug, ['dolarapi', 'jsdelivr', 'cryptoprice'])): ?>
                                <span class="prixy-provider-card__badge prixy-provider-card__badge--success"><?php echo $p['tag']; ?></span>
                            <?php elseif ($has_key): ?>
                                <span class="prixy-provider-card__badge prixy-provider-card__badge--success"><?php echo $p['tag']; ?></span>
                            <?php else: ?>
                                <span class="prixy-provider-card__badge">API Key</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="prixy_settings[api_provider]" id="prixy-api-provider-input" value="<?php echo esc_attr($current_provider); ?>">

                <!-- API Key Input Panels (show only for selected provider that requires key) -->
                <div class="prixy-api-key-panels">
                    <div class="prixy-api-key-panel" id="panel-currencyapi" style="display: <?php echo $current_provider === 'currencyapi' ? 'block' : 'none'; ?>;">
                        <div class="prixy-api-key-panel__header">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            <span>CurrencyAPI</span>
                        </div>
                        <div class="prixy-api-key-panel__body">
                            <label class="prixy-api-key-panel__label">
                                <?php echo esc_html($p['key_label']); ?>
                                <a href="https://currencyapi.com" target="_blank" rel="noopener">Obtener key →</a>
                            </label>
                            <div class="prixy-api-key-panel__input-wrap">
                                <input type="password" name="prixy_settings[currencyapi_api_key]" value="<?php echo esc_attr($opts['currencyapi_api_key'] ?? ''); ?>" class="prixy-field__input" placeholder="Tu API Key">
                                <button type="button" class="prixy-toggle-pass" onclick="toggleApiKey('currencyapi')">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <button type="button" class="prixy-btn prixy-btn--outline prixy-btn--sm prixy-test-api" data-api="currencyapi">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Test
                            </button>
                        </div>
                    </div>

                    <div class="prixy-api-key-panel" id="panel-exchangerate" style="display: <?php echo $current_provider === 'exchangerate' ? 'block' : 'none'; ?>;">
                        <div class="prixy-api-key-panel__header">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            <span>ExchangeRate-API</span>
                        </div>
                        <div class="prixy-api-key-panel__body">
                            <label class="prixy-api-key-panel__label">
                                API Key
                                <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener">Obtener key →</a>
                            </label>
                            <div class="prixy-api-key-panel__input-wrap">
                                <input type="password" name="prixy_settings[exchangerate_api_key]" value="<?php echo esc_attr($opts['exchangerate_api_key'] ?? ''); ?>" class="prixy-field__input" placeholder="Tu API Key">
                                <button type="button" class="prixy-toggle-pass" onclick="toggleApiKey('exchangerate')">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <button type="button" class="prixy-btn prixy-btn--outline prixy-btn--sm prixy-test-api" data-api="exchangerate">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Test
                            </button>
                        </div>
                    </div>
                </div>
                <div id="prixy-api-test-result" class="prixy-api-test-result"></div>
            </div>
        </div>

        <!-- Reference Currency Section -->
        <div class="prixy-section">
            <button type="button" class="prixy-collapsible <?php echo !empty($reference_currency) ? '' : 'prixy-collapsible--expanded'; ?>" data-section="reference">
                <span class="prixy-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="prixy-collapsible__title">Moneda de Referencia</span>
                <span class="prixy-collapsible__summary">
                    <?php if (!empty($reference_currency)): ?>
                        <?php echo esc_html($reference_currency); ?>
                    <?php else: ?>
                        Seleccioná
                    <?php endif; ?>
                </span>
                <span class="prixy-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="prixy-collapsible__content" id="section-reference">
                <div class="prixy-reference-set">
                    <div class="prixy-reference-set__info">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <strong>Seleccioná la moneda de referencia</strong>
                            <p class="prixy-reference-set__hint">Elegí la moneda que usás para tus precios en la tienda.</p>
                        </div>
                    </div>
                    <div class="prixy-fields-grid" style="margin-top: 16px;">
                        <div class="prixy-field">
                            <label class="prixy-field__label">Moneda</label>
                            <select name="prixy_settings[reference_currency]" id="prixy-ref-currency" class="prixy-field__select" data-selected="<?php echo esc_attr($reference_currency); ?>">
                                <option value="">Cargando...</option>
                            </select>
                            <p class="prixy-field__hint">Las monedas se cargan desde la API seleccionada.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rules Section -->
        <div class="prixy-section">
            <button type="button" class="prixy-collapsible" data-section="rules">
                <span class="prixy-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
                <span class="prixy-collapsible__title">Reglas de Precio</span>
                <span class="prixy-collapsible__summary"><?php echo number_format(floatval($opts['margin'] ?? 0), 1); ?>% margen · <?php echo number_format(floatval($opts['threshold'] ?? 0.5), 1); ?>% umbral</span>
                <span class="prixy-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="prixy-collapsible__content" id="section-rules">
                <div class="prixy-fields-grid">
                    <div class="prixy-field">
                        <label class="prixy-field__label">Margen de corrección</label>
                        <div class="prixy-field__input-wrap">
                            <input type="number" name="prixy_settings[margin]" value="<?php echo esc_attr($opts['margin'] ?? ''); ?>" step="0.01" class="prixy-field__input" placeholder="0">
                            <span class="prixy-field__suffix">%</span>
                        </div>
                        <p class="prixy-field__hint">+% absorbe menos suba · -% absorbe parte de la suba · Default: 0%</p>
                    </div>
                    <div class="prixy-field">
                        <label class="prixy-field__label">Variación mínima</label>
                        <div class="prixy-field__input-wrap">
                            <input type="number" name="prixy_settings[threshold]" value="<?php echo esc_attr($opts['threshold'] ?? ''); ?>" step="0.01" min="0" class="prixy-field__input" placeholder="0.5">
                            <span class="prixy-field__suffix">%</span>
                        </div>
                        <p class="prixy-field__hint">Protege contra fluctuaciones pequeñas · Default: 0.5%</p>
                    </div>
                    <div class="prixy-field">
                        <label class="prixy-field__label">Variación máxima</label>
                        <div class="prixy-field__input-wrap">
                            <input type="number" name="prixy_settings[threshold_max]" value="<?php echo esc_attr($opts['threshold_max'] ?? ''); ?>" step="0.01" min="0" class="prixy-field__input" placeholder="Sin límite">
                            <span class="prixy-field__suffix">%</span>
                        </div>
                        <p class="prixy-field__hint">Frenó de seguridad · Dejar vacío para sin límite</p>
                    </div>
                    <div class="prixy-field">
                        <label class="prixy-field__label">Sentido de actualización</label>
                        <select name="prixy_settings[update_direction]" class="prixy-field__select">
                            <option value="bidirectional" <?php selected($opts['update_direction'] ?? '', 'bidirectional'); ?>>Bidireccional</option>
                            <option value="up_only" <?php selected($opts['update_direction'] ?? '', 'up_only'); ?>>Solo cuando sube</option>
                            <option value="down_only" <?php selected($opts['update_direction'] ?? '', 'down_only'); ?>>Solo cuando baja</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rounding Section -->
        <div class="prixy-section">
            <button type="button" class="prixy-collapsible" data-section="rounding">
                <span class="prixy-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </span>
                <span class="prixy-collapsible__title">Redondeo</span>
                <span class="prixy-collapsible__summary"><?php echo esc_html(ucfirst($opts['rounding_type'] ?? 'Enteros')); ?></span>
                <span class="prixy-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="prixy-collapsible__content" id="section-rounding">
                <div class="prixy-fields-grid">
                    <div class="prixy-field">
                        <label class="prixy-field__label">Tipo de redondeo</label>
                        <select name="prixy_settings[rounding_type]" class="prixy-field__select">
                            <option value="integer" <?php selected($opts['rounding_type'] ?? '', 'integer'); ?>>Enteros</option>
                            <option value="none" <?php selected($opts['rounding_type'] ?? '', 'none'); ?>>Sin redondeo</option>
                            <option value="ceil" <?php selected($opts['rounding_type'] ?? '', 'ceil'); ?>>Hacia arriba</option>
                            <option value="floor" <?php selected($opts['rounding_type'] ?? '', 'floor'); ?>>Hacia abajo</option>
                            <option value="nearest" <?php selected($opts['rounding_type'] ?? '', 'nearest'); ?>>Al más cercano</option>
                        </select>
                    </div>
                    <div class="prixy-field">
                        <label class="prixy-field__label">Redondear a</label>
                        <div class="prixy-field__input-wrap">
                            <span class="prixy-field__prefix">$</span>
                            <input type="number" name="prixy_settings[nearest_to]" value="<?php echo esc_attr($opts['nearest_to'] ?? '1'); ?>" step="1" min="1" class="prixy-field__input">
                        </div>
                        <p class="prixy-field__hint">Ej: 1 para enteros, 5 para múltiplos de 5</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exclusions Section -->
        <div class="prixy-section">
            <button type="button" class="prixy-collapsible" data-section="exclusions">
                <span class="prixy-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
                <span class="prixy-collapsible__title">Exclusiones</span>
                <span class="prixy-collapsible__summary"><?php echo count($excluded_cats) > 0 ? count($excluded_cats) . ' categorías' : 'Ninguna'; ?></span>
                <span class="prixy-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="prixy-collapsible__content" id="section-exclusions">
                <div class="prixy-field">
                    <label class="prixy-field__label">Categorías excluidas</label>
                    <select name="prixy_settings[exclude_categories][]" multiple class="prixy-field__select" style="min-height: 150px;">
                        <?php foreach ($all_cats as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php echo in_array($cat->term_id, $excluded_cats) ? 'selected' : ''; ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="prixy-field__hint">Productos de estas categorías no se actualizarán. Mantener ctrl/cmd para seleccionar varias.</p>
                </div>
            </div>
        </div>

        <!-- Automatización Section -->
        <div class="prixy-section">
            <button type="button" class="prixy-collapsible prixy-collapsible--expanded" data-section="automation">
                <span class="prixy-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="prixy-collapsible__title">Automatización</span>
                <span class="prixy-collapsible__summary"><?php echo $opts['cron_enabled'] ? 'Activa' : 'Inactiva'; ?></span>
                <span class="prixy-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="prixy-collapsible__content" id="section-automation">
                
                <!-- Enable Toggle -->
                <div class="prixy-field" style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--dpu-bg); border-radius: var(--dpu-radius-sm); margin-bottom: 20px; border: 1px solid var(--dpu-border);">
                    <div>
                        <label style="font-weight: 500; cursor: pointer;">Actualización automática</label>
                        <p style="margin: 4px 0 0; font-size: 13px; color: var(--dpu-text-2);">Ejecutar automáticamente según la frecuencia configurada</p>
                    </div>
                    <label class="prixy-toggle">
                        <input type="checkbox" name="prixy_settings[cron_enabled]" value="1" <?php checked(1, $opts['cron_enabled'] ?? 0); ?>>
                        <span class="prixy-toggle__slider"></span>
                    </label>
                </div>

                <!-- Frecuencia -->
                <div class="prixy-field">
                    <label class="prixy-field__label">Frecuencia</label>
                    <select name="prixy_settings[update_interval]" class="prixy-field__select">
                        <option value="hourly" <?php selected('hourly', $opts['update_interval'] ?? 'twicedaily'); ?>>Cada hora</option>
                        <option value="twicedaily" <?php selected('twicedaily', $opts['update_interval'] ?? 'twicedaily'); ?>>Dos veces por día</option>
                        <option value="daily" <?php selected('daily', $opts['update_interval'] ?? 'twicedaily'); ?>>Una vez por día</option>
                        <option value="weekly" <?php selected('weekly', $opts['update_interval'] ?? 'twicedaily'); ?>>Una vez por semana</option>
                    </select>
                </div>

                <!-- API Override -->
                <div class="prixy-field">
                    <label class="prixy-field__label">API (opcional)</label>
                    <select name="prixy_settings[cron_api_provider]" class="prixy-field__select">
                        <option value="" <?php selected('', $opts['cron_api_provider'] ?? ''); ?>>Usar configuración principal</option>
                        <option value="dolarapi" <?php selected('dolarapi', $opts['cron_api_provider'] ?? ''); ?>>DolarAPI</option>
                        <option value="jsdelivr" <?php selected('jsdelivr', $opts['cron_api_provider'] ?? ''); ?>>Jsdelivr</option>
                        <option value="moneyconvert" <?php selected('moneyconvert', $opts['cron_api_provider'] ?? ''); ?>>MoneyConvert</option>
                        <option value="hexarate" <?php selected('hexarate', $opts['cron_api_provider'] ?? ''); ?>>HexaRate</option>
                        <option value="foreignrate" <?php selected('foreignrate', $opts['cron_api_provider'] ?? ''); ?>>ForeignRate</option>
                        <option value="cryptoprice" <?php selected('cryptoprice', $opts['cron_api_provider'] ?? ''); ?>>CoinGecko</option>
                    </select>
                    <p class="prixy-field__hint">Dejar en blanco para usar la API configurada arriba.</p>
                </div>

                <!-- Notificaciones -->
                <div class="prixy-field">
                    <label class="prixy-field__label">Notificaciones</label>
                    <select name="prixy_settings[cron_notify_mode]" class="prixy-field__select">
                        <option value="update_and_notify" <?php selected('update_and_notify', $opts['cron_notify_mode'] ?? 'update_and_notify'); ?>>Actualizar y notificar</option>
                        <option value="simulate_only" <?php selected('simulate_only', $opts['cron_notify_mode'] ?? 'update_and_notify'); ?>>Solo simular (sin actualizar)</option>
                        <option value="disabled" <?php selected('disabled', $opts['cron_notify_mode'] ?? 'update_and_notify'); ?>>Sin notificaciones</option>
                    </select>
                </div>

                <!-- Email -->
                <div class="prixy-field">
                    <label class="prixy-field__label">Email</label>
                    <input type="email" name="prixy_settings[cron_notify_email]" value="<?php echo esc_attr($opts['cron_notify_email'] ?? get_option('admin_email')); ?>" class="prixy-field__input">
                </div>

            </div>
        </div>

        <!-- Save Button -->
        <div class="prixy-form-actions">
            <button type="submit" class="prixy-btn prixy-btn--primary">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Guardar configuración
            </button>
        </div>

    </form>

</div>

<?php endif; // rate_locked / needs_setup / else ?>

<script>
(function() {
    var input = document.getElementById('prixy-origin-rate');
    var btn = document.getElementById('prixy-save-origin-rate');
    var msg = document.getElementById('prixy-rate-msg');
    var usdCells = document.querySelectorAll('.usd-cell');
    var arstds = document.querySelectorAll('#products-tbody td[data-ars]');

    // Calcular USD en vivo cada vez que cambia la tasa
    function updateUsdPreview() {
        var rate = parseFloat(input.value);
        if (!rate || rate <= 0) {
            usdCells.forEach(function(cell) { cell.textContent = '—'; });
            return;
        }
        arstds.forEach(function(td, i) {
            var ars = parseFloat(td.getAttribute('data-ars'));
            if (!isNaN(ars) && ars > 0 && usdCells[i]) {
                usdCells[i].textContent = '$' + (ars / rate).toFixed(2);
            }
        });
    }

    if (input) {
        updateUsdPreview();
        input.addEventListener('input', updateUsdPreview);
        input.addEventListener('change', updateUsdPreview);
    }

    // Auto-load rate desde API si no hay valor
    if (input && btn && msg && typeof prixy_ajax !== 'undefined') {
        var currentVal = input.value;
        if (!currentVal || currentVal == '' || currentVal == '0') {
            btn.disabled = true;
            btn.innerHTML = 'Cargando tasa...';
            msg.innerHTML = '<span style="color: #6b7280;">Obteniendo tasa del dólar...</span>';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', prixy_ajax.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success && res.data && res.data.rate > 0) {
                            input.value = res.data.rate.toFixed(2);
                            msg.innerHTML = '<span style="color: #22c55e; font-weight: 600;">✓ Tasa auto-cargada: $' + res.data.rate.toFixed(2) + '</span>';
                        } else {
                            msg.innerHTML = '<span style="color: #d97706;">Ingresá la tasa manualmente.</span>';
                        }
                    } catch(e) {
                        msg.innerHTML = '<span style="color: #d97706;">Ingresá la tasa manualmente.</span>';
                    }
                    btn.disabled = false;
                    updateUsdPreview();
                }
            };
            xhr.send('action=prixy_get_current_rate&nonce=' + prixy_ajax.nonce);
        } else {
            updateUsdPreview();
        }
    }
})();

jQuery(document).ready(function($) {

    // Collapsible sections
    $('.prixy-collapsible').on('click', function() {
        var $btn = $(this);
        var section = $btn.data('section');
        var $content = $('#section-' + section);
        $btn.toggleClass('prixy-collapsible--expanded');
        $content.slideToggle(200);
    });

    // Test API connection
    $('.prixy-test-api').on('click', function() {
        var api = $(this).data('api');
        var $result = $('#prixy-api-test-result');
        var $btn = $(this);
        $btn.prop('disabled', true).html('Testeando...');
        $result.html('<div class="prixy-notice prixy-notice--info"><div class="prixy-notice__content"><p>Probando conexión...</p></div></div>');
        var apiKey = api === 'currencyapi' ? $('input[name="prixy_settings[currencyapi_api_key]"]').val() : $('input[name="prixy_settings[exchangerate_api_key]"]').val();
        $.post(prixy_ajax.ajax_url, { action: 'prixy_test_api', api: api, api_key: apiKey, nonce: prixy_ajax.nonce }, function(res) {
            $btn.prop('disabled', false).html('Test');
            if (res.success) {
                $result.html('<div class="prixy-notice prixy-notice--success"><div class="prixy-notice__content"><strong>¡Conexión exitosa!</strong><p>' + res.data.message + '</p></div></div>');
            } else {
                $result.html('<div class="prixy-notice prixy-notice--error"><div class="prixy-notice__content"><strong>Error</strong><p>' + res.data.message + '</p></div></div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('Test');
            $result.html('<div class="prixy-notice prixy-notice--error"><div class="prixy-notice__content"><strong>Error</strong><p>No se pudo conectar.</p></div></div>');
        });
    });

    // Get from API button
    $('#prixy-get-from-api').on('click', function() {
        var $btn = $(this), $result = $('#prixy-api-rates-result');
        var currency = $('#prixy-ref-currency').val();
        $btn.prop('disabled', true).html('Obteniendo...');
        $result.html('<div class="prixy-notice prixy-notice--info"><div class="prixy-notice__content"><p>Obteniendo tasas...</p></div></div>');
        $.post(prixy_ajax.ajax_url, { action: 'prixy_get_rates', currency: currency, nonce: prixy_ajax.nonce }, function(res) {
            $btn.prop('disabled', false).html('Obtener de API');
            if (res.success && res.data.rates) {
                var html = '<div class="prixy-rates-list">';
                for (var code in res.data.rates) {
                    html += '<button type="button" class="prixy-rate-option" data-currency="' + code + '" data-rate="' + res.data.rates[code] + '"><span class="prixy-rate-option__currency">' + code + '</span><span class="prixy-rate-option__rate">$' + parseFloat(res.data.rates[code]).toFixed(2) + '</span></button>';
                }
                html += '</div>';
                $result.html(html);
                $('.prixy-rate-option').on('click', function() {
                    $('#prixy-ref-currency').val($(this).data('currency'));
                    $('#prixy-ref-rate').val($(this).data('rate'));
                    $result.html('<div class="prixy-notice prixy-notice--success"><div class="prixy-notice__content"><strong>¡Seleccionada!</strong><p>' + $(this).data('currency') + ': $' + parseFloat($(this).data('rate')).toFixed(4) + '</p></div></div>');
                });
            } else {
                $result.html('<div class="prixy-notice prixy-notice--error"><div class="prixy-notice__content"><strong>Error</strong><p>' + (res.data?.message || 'No se pudieron obtener las tasas.') + '</p></div></div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('Obtener de API');
            $result.html('<div class="prixy-notice prixy-notice--error"><div class="prixy-notice__content"><strong>Error</strong><p>No se pudo conectar.</p></div></div>');
        });
    });

    // Form submit feedback
    $('#prixy-config-form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true).html('Guardando...');
    });

    /* ====== STEP 1: Save Origin Rate ====== */
    $('#prixy-save-origin-rate').on('click', function(e) {
        e.preventDefault();
        var rate = parseFloat($('#prixy-origin-rate').val());
        var $btn = $(this);
        var $msg = $('#prixy-rate-msg');
        if (!rate || rate <= 0) {
            $msg.html('<span style="color: #dc2626;">Ingresá un valor válido para la tasa.</span>');
            return;
        }
        $btn.prop('disabled', true).html('Procesando productos...');
        $msg.html('<span style="color: #6b7280;">Guardando tasa y creando primer log...</span>');
        $.ajax({
            url: prixy_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: { action: 'prixy_save_origin_rate', value: rate, nonce: prixy_ajax.nonce },
            success: function(res) {
                if (res.success && res.data) {
                    var count = res.data.processed || 0;
                    $msg.html('<div style="margin-top: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; gap: 10px;"><svg width="20" height="20" fill="none" stroke="#16a34a" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span style="color: #166534; font-weight: 600;">✓ Tasa guardada (' + count + ' productos). Redirigiendo a configuración...</span></div>');
                    $btn.html('✓ Completado');
                    var redirectUrl = res.data.redirect || '/wp-admin/admin.php?page=prixy_configuration';
                    setTimeout(function() { window.location.replace(redirectUrl); }, 1200);
                } else {
                    $btn.prop('disabled', false).html('Confirmar y continuar');
                    $msg.html('<span style="color: #dc2626;">Error: respuesta inesperada del servidor.</span>');
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).html('Confirmar y continuar');
                $msg.html('<span style="color: #dc2626;">Error de conexión: ' + status + '</span>');
            }
        });
    });

    /* ====== STEP 2: Auto-start batch processing ====== */
    var setupProcessed = <?php echo intval($products_done); ?>;
    var setupTotal = <?php echo intval($total_products); ?>;
    var setupRate = <?php echo floatval($origin_rate); ?>;
    var setupBatchSize = 10;

    if (setupTotal > 0 && setupProcessed < setupTotal && setupRate > 0) {
        setTimeout(processSetupBatch, 500);
    }

    function processSetupBatch() {
        if (setupProcessed >= setupTotal) {
            completeSetup();
            return;
        }
        $.post(prixy_ajax.ajax_url, {
            action: 'prixy_first_setup_batch',
            nonce: prixy_ajax.nonce,
            offset: setupProcessed,
            limit: setupBatchSize,
            rate: setupRate
        }, function(response) {
            if (response.success && response.data.products) {
                setupProcessed += response.data.products.length;
                updateSetupProgress();
                response.data.products.forEach(function(p) {
                    var usd = p.usd || (parseFloat(p.ars) / setupRate).toFixed(2);
                    $('#setup-products-list').append(
                        '<div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">' +
                        '<span style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + p.name + '</span>' +
                        '<span style="color: #6366f1;">$' + p.ars + ' → $' + usd + '</span></div>'
                    );
                });
                var list = $('#setup-products-list')[0];
                if (list) list.scrollTop = list.scrollHeight;
                if (response.data.done) {
                    completeSetup();
                } else {
                    setTimeout(processSetupBatch, 100);
                }
            }
        }).fail(function() {
            setupProcessed += setupBatchSize;
            updateSetupProgress();
            setTimeout(processSetupBatch, 100);
        });
    }

    function updateSetupProgress() {
        var percent = setupTotal > 0 ? (setupProcessed / setupTotal) * 100 : 0;
        $('#setup-progress-fill').css('width', percent + '%');
        $('#setup-count').text(setupProcessed + ' / ' + setupTotal);
    }

    function completeSetup() {
        $('#setup-progress-fill').css('width', '100%');
        $('#setup-count').text(setupTotal + ' / ' + setupTotal);
        $('#setup-complete-msg').show();
        setTimeout(function() { window.location.reload(); }, 2000);
    }
});
</script>

<style>
.prixy-field__status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.375rem;
}
.prixy-field__status--connected { color: #16a34a; }
.prixy-field__status--error { color: #dc2626; }

.prixy-api-test-result,
.prixy-api-rates-result {
    margin-top: 1rem;
}

.prixy-reference-set {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: var(--dpu-radius);
    padding: 1.25rem;
}
.prixy-reference-set__info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}
.prixy-reference-set__info svg { color: #16a34a; flex-shrink: 0; }
.prixy-reference-set__info strong { display: block; color: #166534; margin-bottom: 0.25rem; }
.prixy-reference-set__info p { margin: 0; color: #166534; }
.prixy-reference-set__hint { font-size: 0.8125rem; color: #15803d !important; margin-top: 0.5rem !important; }

.prixy-get-from-api {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--dpu-border);
}

.prixy-rates-list {
    margin-top: 1rem;
}
.prixy-rates-list__label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--dpu-text);
    margin-bottom: 0.75rem;
}
.prixy-rate-option {
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
.prixy-rate-option:hover {
    border-color: var(--upd-500);
    background: var(--upd-50);
}
.prixy-rate-option__currency {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--dpu-text);
}
.prixy-rate-option__rate {
    font-size: 0.875rem;
    font-weight: 700;
    font-family: var(--dpu-font-mono);
    color: var(--upd-600);
}

.prixy-notice--success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}
.prixy-notice--success .prixy-notice__icon { color: #16a34a; }
.prixy-notice--success .prixy-notice__content strong { color: #166534; }
.prixy-notice--success .prixy-notice__content p { color: #166534; }

.prixy-notice--error {
    background: #fef2f2;
    border: 1px solid #fecaca;
}
.prixy-notice--error .prixy-notice__icon { color: #dc2626; }
.prixy-notice--error .prixy-notice__content strong { color: #991b1b; }
.prixy-notice--error .prixy-notice__content p { color: #991b1b; }
</style>
