<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts = get_option('dpuwoo_settings', []);
$origin_rate = floatval($opts['origin_exchange_rate'] ?? 0);
$rate_locked = !empty($opts['origin_rate_locked']);
$rate_auto = $origin_rate > 0;

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
$products_done = $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM {$wpdb->prefix}dpuwoo_run_items WHERE status = 'updated'");
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

<!-- dpuwoo-settings.php starting here -->
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
            <p class="dpuwoo-header__subtitle">Configuración del plugin Dollar Sync</p>
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

    <?php
    // STEP 1: Si no hay tasa guardada o no está lockeada, mostrar onboarding
    if (!$rate_locked):
    ?>

    <!-- ONBOARDING UX DISEÑADO -->
    <div style="max-width: 900px; margin: 0 auto; padding-top: 20px;">

        <!-- Header -->
        <div style="text-align: center; margin-bottom: 24px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #111827; margin: 0 0 8px;">Setup Inicial - Dollar Sync</h1>
            <p style="font-size: 14px; color: #6b7280; margin: 0;">Configurá la tasa de referencia para comenzar a sincronizar tus precios</p>
        </div>

        <!-- Explicación breve -->
        <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 12px; padding: 16px 20px; color: white; margin-bottom: 24px; display: flex; gap: 16px; align-items: center;">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div style="font-size: 14px; opacity: 0.95;">
                <strong>¿Por qué necesito esto?</strong> Ingresá el valor del dólar cuando cargaste tus precios. Así Dollar Sync puede detectar cambios y actualizar automáticamente.
            </div>
        </div>

        <!-- Input de Tasa -->
        <div style="background: white; border: 2px solid #e5e7eb; border-radius: 16px; padding: 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #6b7280; margin-bottom: 8px;">TASA DE REFERENCIA (ARS/USD)</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 18px; font-weight: 700;">$</span>
                    <input type="number" id="dpuwoo-origin-rate" value="<?php echo $origin_rate > 0 ? esc_attr(number_format($origin_rate, 2, '.', '')) : ''; ?>" step="0.01" min="0.01" placeholder="ej: 1368.00" style="padding: 14px 16px 14px 36px; border: 2px solid #d1d5db; border-radius: 10px; font-size: 20px; font-weight: 700; width: 100%; max-width: 200px; outline: none; color: #111827;">
                </div>
            </div>
            <button type="button" id="dpuwoo-preview-btn" style="background: #f3f4f6; color: #374151; border: none; border-radius: 10px; padding: 14px 24px; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                Ver Productos
            </button>
        </div>

        <!-- Área de productos (se muestra al hacer click) -->
        <div id="products-preview-area" style="display: none; background: white; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; margin-bottom: 24px;">
            <div style="background: #f9fafb; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 600; color: #111827;">Vista previa de productos</span>
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
                        <!-- Se llena via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mensaje de estado -->
        <div id="dpuwoo-rate-msg" style="font-size: 13px; margin-bottom: 16px; min-height: 20px;"></div>

        <!-- Botón Confirmar (flotante) -->
        <div id="confirm-section" style="display: none; position: sticky; bottom: 20px; background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 20px; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <span style="font-weight: 600; color: #111827;">¿Todo listo?</span>
                <span style="font-size: 13px; color: #6b7280; display: block;">Confirmá para guardar los precios históricos en USD</span>
            </div>
            <button type="button" id="dpuwoo-save-origin-rate" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; border-radius: 10px; padding: 14px 32px; font-size: 15px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(99,102,241,0.4);">
                Confirmar Setup
            </button>
        </div>

    </div>

    <?php
    // STEP 2: Si la tasa está lockeada pero hay productos por procesar
    elseif ($needs_setup > 0):
    ?>

    <!-- PROCESSING: Tasa guardada, procesar productos -->
    <div style="max-width: 700px; margin: 0 auto; padding-top: 20px;">

        <div style="background: linear-gradient(135deg, var(--upd-600), var(--upd-700)); border-radius: 16px; padding: 28px; color: white; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Procesamiento Inicial
                    </h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 14px;">
                        Quedan <strong><?php echo number_format($needs_setup); ?></strong> productos por procesar con la tasa de referencia.
                    </p>
                    <p style="margin: 8px 0 0; opacity: 0.75; font-size: 12px;">
                        Esto guardará el precio en USD de cada producto para poder comparar cambios futuros.
                    </p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; opacity: 0.8;">Tasa de referencia</div>
                    <div style="font-size: 28px; font-weight: 700;">$<?php echo number_format($origin_rate, 2); ?></div>
                </div>
            </div>

            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <div style="font-size: 12px; opacity: 0.8; margin-bottom: 6px;">Progreso</div>
                        <div style="background: rgba(255,255,255,0.2); border-radius: 999px; height: 10px; overflow: hidden;">
                            <div id="setup-progress-fill" style="background: white; height: 100%; width: <?php echo $total_products > 0 ? (($products_done / $total_products) * 100) : 0; ?>%; transition: width 0.3s;"></div>
                        </div>
                        <div style="font-size: 13px; margin-top: 8px;">
                            <?php echo number_format($products_done); ?> / <?php echo number_format($total_products); ?> productos
                        </div>
                    </div>
                    <button type="button" id="start-setup-btn" class="dpuwoo-btn" style="background: white; color: var(--upd-600); padding: 14px 28px; font-size: 15px; font-weight: 700;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Iniciar Procesamiento
                    </button>
                </div>
            </div>
        </div>

        <!-- Progress Panel -->
        <div id="setup-progress-panel" style="display: none; background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius); padding: 24px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 16px;">
                <span style="font-weight: 600; font-size: 14px;">Procesando productos...</span>
                <span id="setup-count" style="color: var(--upd-600); font-weight: 700;">0 / <?php echo number_format($total_products); ?></span>
            </div>
            <div style="background: var(--dpu-border); border-radius: 999px; height: 6px; overflow: hidden;">
                <div id="setup-progress-bar" style="background: var(--upd-500); height: 100%; width: 0%; transition: width 0.2s;"></div>
            </div>
            <div id="setup-products-list" style="margin-top: 20px; max-height: 240px; overflow-y: auto; font-size: 13px;">
            </div>
        </div>

        <div id="setup-complete-msg" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; text-align: center;">
            <svg width="48" height="48" fill="none" stroke="#22c55e" viewBox="0 0 24 24" stroke-width="2" style="margin: 0 auto 12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 style="margin: 0 0 8px; color: #166534; font-size: 18px;">¡Procesamiento completado!</h3>
            <p style="margin: 0; color: #166534; font-size: 14px;">Todos los productos fueron procesados. Podés continuar con la configuración.</p>
        </div>

    <?php
    // STEP 3: Todo completo, mostrar settings normal
    else:
    ?>

    <!-- Info Notice -->
    <div class="dpuwoo-notice dpuwoo-notice--info">
        <div class="dpuwoo-notice__icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="dpuwoo-notice__content">
            <strong>Configuración</strong>
            <p>Configurá el provider de API y la moneda de referencia.</p>
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
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible dpuwoo-collapsible--expanded" data-section="apikeys">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Conexión API</span>
                <span class="dpuwoo-collapsible__summary">
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

        <!-- Automatización Section -->
        <div class="dpuwoo-section">
            <button type="button" class="dpuwoo-collapsible dpuwoo-collapsible--expanded" data-section="automation">
                <span class="dpuwoo-collapsible__icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="dpuwoo-collapsible__title">Automatización</span>
                <span class="dpuwoo-collapsible__summary"><?php echo $opts['cron_enabled'] ? 'Activa' : 'Inactiva'; ?></span>
                <span class="dpuwoo-collapsible__chevron"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></span>
            </button>
            <div class="dpuwoo-collapsible__content" id="section-automation">
                
                <!-- Enable Toggle -->
                <div class="dpuwoo-field" style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--dpu-bg); border-radius: var(--dpu-radius-sm); margin-bottom: 20px; border: 1px solid var(--dpu-border);">
                    <div>
                        <label style="font-weight: 500; cursor: pointer;">Actualización automática</label>
                        <p style="margin: 4px 0 0; font-size: 13px; color: var(--dpu-text-2);">Ejecutar automáticamente según la frecuencia configurada</p>
                    </div>
                    <label class="dpuwoo-toggle">
                        <input type="checkbox" name="dpuwoo_settings[cron_enabled]" value="1" <?php checked(1, $opts['cron_enabled'] ?? 0); ?>>
                        <span class="dpuwoo-toggle__slider"></span>
                    </label>
                </div>

                <!-- Frecuencia -->
                <div class="dpuwoo-field">
                    <label class="dpuwoo-field__label">Frecuencia</label>
                    <select name="dpuwoo_settings[update_interval]" class="dpuwoo-field__select">
                        <option value="hourly" <?php selected('hourly', $opts['update_interval'] ?? 'twicedaily'); ?>>Cada hora</option>
                        <option value="twicedaily" <?php selected('twicedaily', $opts['update_interval'] ?? 'twicedaily'); ?>>Dos veces por día</option>
                        <option value="daily" <?php selected('daily', $opts['update_interval'] ?? 'twicedaily'); ?>>Una vez por día</option>
                        <option value="weekly" <?php selected('weekly', $opts['update_interval'] ?? 'twicedaily'); ?>>Una vez por semana</option>
                    </select>
                </div>

                <!-- API Override -->
                <div class="dpuwoo-field">
                    <label class="dpuwoo-field__label">API (opcional)</label>
                    <select name="dpuwoo_settings[cron_api_provider]" class="dpuwoo-field__select">
                        <option value="" <?php selected('', $opts['cron_api_provider'] ?? ''); ?>>Usar configuración principal</option>
                        <option value="dolarapi" <?php selected('dolarapi', $opts['cron_api_provider'] ?? ''); ?>>DolarAPI</option>
                        <option value="jsdelivr" <?php selected('jsdelivr', $opts['cron_api_provider'] ?? ''); ?>>Jsdelivr</option>
                        <option value="moneyconvert" <?php selected('moneyconvert', $opts['cron_api_provider'] ?? ''); ?>>MoneyConvert</option>
                        <option value="hexarate" <?php selected('hexarate', $opts['cron_api_provider'] ?? ''); ?>>HexaRate</option>
                        <option value="foreignrate" <?php selected('foreignrate', $opts['cron_api_provider'] ?? ''); ?>>ForeignRate</option>
                        <option value="cryptoprice" <?php selected('cryptoprice', $opts['cron_api_provider'] ?? ''); ?>>CoinGecko</option>
                    </select>
                    <p class="dpuwoo-field__hint">Dejar en blanco para usar la API configurada arriba.</p>
                </div>

                <!-- Notificaciones -->
                <div class="dpuwoo-field">
                    <label class="dpuwoo-field__label">Notificaciones</label>
                    <select name="dpuwoo_settings[cron_notify_mode]" class="dpuwoo-field__select">
                        <option value="update_and_notify" <?php selected('update_and_notify', $opts['cron_notify_mode'] ?? 'update_and_notify'); ?>>Actualizar y notificar</option>
                        <option value="simulate_only" <?php selected('simulate_only', $opts['cron_notify_mode'] ?? 'update_and_notify'); ?>>Solo simular (sin actualizar)</option>
                        <option value="disabled" <?php selected('disabled', $opts['cron_notify_mode'] ?? 'update_and_notify'); ?>>Sin notificaciones</option>
                    </select>
                </div>

                <!-- Email -->
                <div class="dpuwoo-field">
                    <label class="dpuwoo-field__label">Email</label>
                    <input type="email" name="dpuwoo_settings[cron_notify_email]" value="<?php echo esc_attr($opts['cron_notify_email'] ?? get_option('admin_email')); ?>" class="dpuwoo-field__input">
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
alert('Settings JS loaded! Check console for logs.');
console.log('=== SETTINGS PAGE JS LOADED ===');
// Ejecutar inmediatamente sin esperar a jQuery
(function() {
    console.log('=== IMMEDIATE FUNCTION RUNNING ===');
    console.log('dpuwoo_ajax:', typeof dpuwoo_ajax !== 'undefined' ? dpuwoo_ajax : 'NOT DEFINED');
    
    var input = document.getElementById('dpuwoo-origin-rate');
    var btn = document.getElementById('dpuwoo-save-origin-rate');
    var msg = document.getElementById('dpuwoo-rate-msg');
    
    console.log('Elements:', input, btn, msg);
    
    if (input && btn && msg) {
        var currentVal = input.value;
        console.log('Current value:', currentVal);
        
        // Auto-load si no hay valor
        if (!currentVal || currentVal == '') {
            btn.disabled = true;
            btn.innerHTML = '<svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Cargando...';
            msg.innerHTML = '<span style="color: #6b7280;">Obteniendo tasa...</span>';
            
            if (typeof dpuwoo_ajax !== 'undefined') {
                console.log(' Calling AJAX...');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', dpuwoo_ajax.ajax_url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var res = JSON.parse(xhr.responseText);
                                console.log('Response:', res);
                                if (res.success && res.data && res.data.rate > 0) {
                                    input.value = res.data.rate.toFixed(2);
                                    btn.disabled = false;
                                    btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Confirmar y continuar';
                                    msg.innerHTML = '<span style="color: #22c55e;">✓ Tasa: $' + res.data.rate.toFixed(2) + '</span>';
                                } else {
                                    btn.disabled = false;
                                    btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Guardar y continuar';
                                    msg.innerHTML = '<span style="color: #d97706;">Manual</span>';
                                }
                            } catch(e) {
                                console.log('Parse error:', e);
                                btn.disabled = false;
                            }
                        } else {
                            console.log('HTTP error:', xhr.status);
                            btn.disabled = false;
                        }
                    }
                };
                xhr.send('action=dpuwoo_get_current_rate&nonce=' + dpuwoo_ajax.nonce);
            } else {
                console.log('dpuwoo_ajax no definido');
                btn.disabled = false;
                btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Guardar y continuar';
            }
        }
    }
})();

jQuery(document).ready(function($) {
    console.log('=== JQUERY READY ===');
    
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

    // Auto-load rate on page load
    console.log('=== AUTO-LOAD START ===');
    try {
        var $input = $('#dpuwoo-origin-rate');
        var $btn = $('#dpuwoo-save-origin-rate');
        var $msg = $('#dpuwoo-rate-msg');
        
        console.log('Elements found - input:', $input.length, 'btn:', $btn.length, 'msg:', $msg.length);
        
        var currentVal = $input.val();
        console.log('Current input value:', currentVal, 'type:', typeof currentVal);
        
        // Solo si no hay valor guardado, obtener de API
        if (!currentVal || currentVal == '' || currentVal == '0') {
            $btn.prop('disabled', true).html('<svg class="animate-spin" width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Cargando...');
            $msg.html('<span style="color: #6b7280;">Obteniendo tasa automáticamente...</span>');

            console.log('Calling AJAX...');

            $.post(dpuwoo_ajax.ajax_url, {
                action: 'dpuwoo_get_current_rate',
                nonce: dpuwoo_ajax.nonce
            }, function(res) {
                console.log('SUCCESS response:', res);
                if (res.success && res.data && res.data.rate > 0) {
                    $input.val(res.data.rate.toFixed(2));
                    $btn.prop('disabled', false).html('<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Confirmar y continuar');
                    $msg.html('<span style="color: #22c55e;">✓ Tasa: $' + res.data.rate.toFixed(2) + '</span>');
                } else {
                    $btn.prop('disabled', false).html('<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Guardar y continuar');
                    $msg.html('<span style="color: #d97706;">Ingresa manualmente.</span>');
                }
            }, 'json').fail(function(xhr, status, error) {
                console.log('AJAX ERROR:', status, error, 'response:', xhr.responseText);
                $btn.prop('disabled', false).html('<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Guardar y continuar');
                $msg.html('<span style="color: #d97706;">Error. Ingresa manualmente.</span>');
            });
        } else {
            console.log('Value already exists, skipping auto-load');
        }
    } catch(e) {
        console.log('AUTO-LOAD ERROR:', e);
    }
    console.log('=== AUTO-LOAD END ===');

    // Save Origin Rate (Onboarding) - procesa productos
    $(document).on('click', '#dpuwoo-save-origin-rate', function(e) {
        e.preventDefault();
        var rate = parseFloat($('#dpuwoo-origin-rate').val());
        var $btn = $(this);
        var $msg = $('#dpuwoo-rate-msg');

        if (!rate || rate <= 0) {
            $msg.html('<span style="color: #dc2626;">Valor inválido.</span>');
            return;
        }

        $btn.prop('disabled', true).html('<svg class="animate-spin" width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Procesando...');
        $msg.html('<span style="color: #6b7280;">Procesando productos...</span>');

        $.ajax({
            url: dpuwoo_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'dpuwoo_save_origin_rate',
                value: rate,
                nonce: dpuwoo_ajax.nonce
            },
            success: function(res) {
                if (res.success) {
                    var products = res.data.products || [];
                    var count = res.data.processed || 0;
                    
                    if (products.length > 0) {
                        var html = '<div style="margin-top: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">';
                        html += '<div style="background: #f0fdf4; padding: 12px 16px; border-bottom: 1px solid #bbf7d0; display: flex; align-items: center; gap: 8px;">';
                        html += '<svg width="20" height="20" fill="none" stroke="#16a34a" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                        html += '<span style="color: #166534; font-weight: 600;">✓ ' + count + ' productos procesados</span>';
                        html += '</div>';
                        html += '<div style="max-height: 300px; overflow-y: auto;">';
                        html += '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
                        html += '<tr style="background: #f9fafb;"><th style="padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb;">Producto</th><th style="padding: 8px 12px; text-align: right; border-bottom: 1px solid #e5e7eb;">ARS</th><th style="padding: 8px 12px; text-align: right; border-bottom: 1px solid #e5e7eb;">USD</th></tr>';
                        
                        for (var i = 0; i < products.length; i++) {
                            var p = products[i];
                            html += '<tr>';
                            html += '<td style="padding: 8px 12px; border-bottom: 1px solid #f3f4f6;">' + p.name + '</td>';
                            html += '<td style="padding: 8px 12px; text-align: right; border-bottom: 1px solid #f3f4f6; color: #dc2626;">$' + p.ars + '</td>';
                            html += '<td style="padding: 8px 12px; text-align: right; border-bottom: 1px solid #f3f4f6; color: #16a34a; font-weight: 600;">$' + p.usd + '</td>';
                            html += '</tr>';
                        }
                        html += '</table></div></div>';
                        
                        $msg.html(html);
                    } else {
                        $msg.html('<span style="color: #22c55e;">✓ Tasa guardada. ' + count + ' productos configurados.</span>');
                    }
                    
                    $btn.hide();
                    
                    setTimeout(function() { window.location.reload(); }, 3000);
                } else {
                    $btn.prop('disabled', false).html('<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Guardar y continuar');
                    $msg.html('<span style="color: #dc2626;">Error: ' + (res.data?.message || 'No se pudo guardar') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Guardar y continuar');
                $msg.html('<span style="color: #dc2626;">Error de conexión.</span>');
            }
        });
    });

    // First Setup Button
    var setupProcessed = <?php echo intval($products_done); ?>;
    var setupTotal = <?php echo intval($total_products); ?>;
    var setupRate = <?php echo floatval($origin_rate); ?>;
    var setupBatchSize = 5;

    $('#start-setup-btn').on('click', function() {
        if (setupRate <= 0) {
            alert('Primero configurá la tasa de referencia en el formulario.');
            return;
        }

        $('#setup-progress-panel').show();
        $('#start-setup-btn').prop('disabled', true).text('Procesando...');

        processSetupBatch();
    });

    function processSetupBatch() {
        if (setupProcessed >= setupTotal) {
            completeSetup();
            return;
        }

        $.post(dpuwoo_ajax.ajax_url, {
            action: 'dpuwoo_first_setup_batch',
            nonce: dpuwoo_ajax.nonce,
            offset: setupProcessed,
            limit: setupBatchSize,
            rate: setupRate
        }, function(response) {
            if (response.success && response.data.products) {
                setupProcessed += response.data.products.length;
                updateSetupProgress();

                response.data.products.forEach(function(p) {
                    $('#setup-products-list').append(
                        '<div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid var(--dpu-border);">' +
                        '<span style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + p.name + '</span>' +
                        '<span style="color: var(--upd-600);">$' + p.ars + ' → $' + p.usd + '</span>' +
                        '</div>'
                    );
                });

                var list = $('#setup-products-list')[0];
                list.scrollTop = list.scrollHeight;

                setTimeout(processSetupBatch, 100);
            }
        }).fail(function() {
            setupProcessed += setupBatchSize;
            updateSetupProgress();
            setTimeout(processSetupBatch, 100);
        });
    }

    function updateSetupProgress() {
        var percent = setupTotal > 0 ? (setupProcessed / setupTotal) * 100 : 0;
        $('#setup-progress-bar').css('width', percent + '%');
        $('#setup-count').text(setupProcessed + ' / ' + setupTotal);
    }

    function completeSetup() {
        $('#setup-progress-bar').css('width', '100%');
        $('#setup-count').text(setupTotal + ' / ' + setupTotal);
        $('#start-setup-btn').text('✓ Completado').addClass('dpuwoo-btn--success').prop('disabled', false);
    }
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

<?php endif; // rate_locked / needs_setup / else ?>
