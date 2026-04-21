<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts           = get_option('dpuwoo_settings', []);
$product_count  = wp_count_posts('product');
$total_products = $product_count->publish ?? 0;

// Si no hay tasa configurada, intentar obtenerla de la API
$display_rate = $opts['origin_exchange_rate'] ?? 0;
if (empty($display_rate)) {
    $currency_type = $opts['currency'] ?? 'oficial';
    $api = new API_Client();
    $rate = $api->get_rate($currency_type);
    if ($rate && isset($rate['value'])) {
        $display_rate = floatval($rate['value']);
    }
}

$provider_key = $opts['api_provider'] ?? 'dolarapi';
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
$provider_name = $api_providers_list[$provider_key] ?? 'No configurado';
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
            <span class="dpuwoo-stat__label">Tasa de cambio</span>
            <span class="dpuwoo-stat__value">
                <?php if ($display_rate > 0): ?>
                    <span class="dpuwoo-stat__rate">$<?php echo number_format($display_rate, 2); ?></span>
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

        <!-- Current Rate Display -->
        <div class="dpuwoo-section">
            <div class="dpuwoo-current-rate">
                <div class="dpuwoo-current-rate__label">Tasa de cambio actual</div>
                <div class="dpuwoo-current-rate__value">
                    <?php if (($opts['origin_exchange_rate'] ?? 0) > 0): ?>
                        <span class="dpuwoo-current-rate__amount">$<?php echo number_format($opts['origin_exchange_rate'], 2); ?></span>
                        <span class="dpuwoo-current-rate__currency"><?php echo esc_html($opts['dollar_type'] ?? $opts['reference_currency'] ?? 'USD'); ?></span>
                    <?php else: ?>
                        <span class="dpuwoo-current-rate__empty">Sin configurar</span>
                    <?php endif; ?>
                </div>
                <p class="dpuwoo-current-rate__hint">
                    Proveedor: <?php echo esc_html($api_providers_list[$provider_key] ?? 'No configurado'); ?> · 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings')); ?>">Editar configuración</a>
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="dpuwoo-action-buttons">
            <button type="button" id="dpuwoo-simulate" class="dpuwoo-btn dpuwoo-btn--primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Simular impacto
            </button>
        </div>

    </form>

    <!-- Simulation Process -->
    <div id="dpuwoo-simulation-process" class="dpuwoo-process-section hidden">
        <div class="dpu-process-bar">
            <div class="dpu-process-bar__track">
                <div id="dpuwoo-sim-progress" class="dpu-process-bar__fill" style="width: 0%"></div>
            </div>
            <div class="dpu-process-bar__info">
                <span id="dpuwoo-sim-text">Iniciando...</span>
                <span id="dpuwoo-sim-percent">0%</span>
            </div>
            <div class="dpu-process-bar__stats">
                <span id="dpuwoo-sim-processed-products">0</span> / <span id="dpuwoo-sim-total-products">0</span> productos
            </div>
        </div>
        <button type="button" id="dpuwoo-cancel-simulation" class="dpuwoo-btn dpuwoo-btn--outline" style="margin-top: 1rem;">
            Cancelar
        </button>
    </div>

    <!-- Simulation Results -->
    <div id="dpuwoo-simulation-results" class="hidden"></div>

    <!-- Update Process -->
    <div id="dpuwoo-update-process" class="dpuwoo-process-section hidden">
        <div class="dpu-process-bar">
            <div class="dpu-process-bar__track">
                <div id="dpuwoo-update-progress" class="dpu-process-bar__fill" style="width: 0%"></div>
            </div>
            <div class="dpu-process-bar__info">
                <span id="dpuwoo-update-text">Iniciando...</span>
                <span id="dpuwoo-update-percent">0%</span>
            </div>
            <div class="dpu-process-bar__stats">
                <span id="dpuwoo-update-processed-products">0</span> / <span id="dpuwoo-update-total-products">0</span> productos · 
                <span id="dpuwoo-update-count">0</span> actualizados · 
                <span id="dpuwoo-update-skipped">0</span> sin cambios
            </div>
        </div>
        <button type="button" id="dpuwoo-cancel-update" class="dpuwoo-btn dpuwoo-btn--outline" style="margin-top: 1rem;">
            Cancelar actualización
        </button>
    </div>

    <!-- Final Results -->
    <div id="dpuwoo-final-results" class="hidden"></div>

    <!-- Confirm Update Modal -->
    <div id="dpuwoo-confirm-update-modal" class="dpuwoo-modal-overlay hidden">
        <div class="dpuwoo-modal">
            <div class="dpuwoo-modal__header dpuwoo-modal__header--ok">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3>Confirmar actualización</h3>
            </div>
            <div class="dpuwoo-modal__body">
                <p>¿Confirmás la actualización real de precios según la simulación?</p>
                <div id="dpuwoo-confirm-summary" class="dpuwoo-confirm-summary"></div>
                
                <!-- Update Progress (hidden initially) -->
                <div id="dpuwoo-update-progress-inline" class="dpuwoo-update-progress hidden">
                    <div class="dpuwoo-update-progress__bar">
                        <div id="dpuwoo-update-progress-fill" class="dpuwoo-update-progress__fill" style="width: 0%"></div>
                    </div>
                    <div class="dpuwoo-update-progress__info">
                        <span id="dpuwoo-update-progress-text">Iniciando...</span>
                        <span id="dpuwoo-update-progress-percent">0%</span>
                    </div>
                    <div class="dpuwoo-update-progress__stats">
                        <span id="dpuwoo-update-products-processed">0</span> / <span id="dpuwoo-update-products-total">0</span> productos
                    </div>
                    
                    <!-- Detailed Progress -->
                    <div class="dpuwoo-update-progress__detail">
                        <div class="dpuwoo-progress-current">
                            <span class="dpuwoo-progress-label">Procesando:</span>
                            <span id="dpuwoo-current-product" class="dpuwoo-progress-value">—</span>
                        </div>
                        <div class="dpuwoo-progress-next">
                            <span class="dpuwoo-progress-label">Siguiente:</span>
                            <span id="dpuwoo-next-product" class="dpuwoo-progress-value">—</span>
                        </div>
                    </div>
                    
                    <!-- Live Log -->
                    <div class="dpuwoo-update-progress__log">
                        <div id="dpuwoo-update-log" class="dpuwoo-update-log"></div>
                    </div>
                    
                    <!-- Counts -->
                    <div class="dpuwoo-update-progress__counts">
                        <span class="dpuwoo-update-count"><strong id="dpuwoo-updated-count">0</strong> actualizados</span>
                        <span class="dpuwoo-skip-count"><strong id="dpuwoo-skipped-count">0</strong> sin cambios</span>
                        <span class="dpuwoo-error-count"><strong id="dpuwoo-errors-count">0</strong> errores</span>
                    </div>
                </div>
            </div>
            <div class="dpuwoo-modal__footer">
                <button id="dpuwoo-confirm-cancel" class="dpuwoo-btn dpuwoo-btn--ghost">Cancelar</button>
                <button id="dpuwoo-confirm-proceed" class="dpuwoo-btn dpuwoo-btn--primary">Sí, actualizar precios</button>
            </div>
        </div>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    console.log('DPUWOO Dashboard JS loaded');
});
</script>

<style>
.dpuwoo-current-rate {
    background: var(--dpu-surface);
    border: 1px solid var(--dpu-border);
    border-radius: var(--dpu-radius);
    padding: 1.5rem;
    text-align: center;
}
.dpuwoo-current-rate__label {
    font-size: 0.875rem;
    color: var(--dpu-text-muted);
    margin-bottom: 0.5rem;
}
.dpuwoo-current-rate__value {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.dpuwoo-current-rate__amount {
    font-size: 2rem;
    font-weight: 700;
    color: var(--upd-600);
    font-family: var(--dpu-font-mono);
}
.dpuwoo-current-rate__currency {
    font-size: 1rem;
    color: var(--dpu-text-muted);
}
.dpuwoo-current-rate__empty {
    font-size: 1.5rem;
    color: var(--dpu-text-muted);
}
.dpuwoo-current-rate__hint {
    font-size: 0.8125rem;
    color: var(--dpu-text-muted);
    margin: 0;
}
.dpuwoo-current-rate__hint a {
    color: var(--upd-500);
    text-decoration: none;
}
.dpuwoo-current-rate__hint a:hover {
    text-decoration: underline;
}
.dpuwoo-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.dpuwoo-modal-overlay.hidden { display: none; }
.dpuwoo-modal {
    background: var(--dpu-surface, #fff);
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
}
.dpuwoo-modal__header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--dpu-border, #e5e7eb);
}
.dpuwoo-modal__header--ok svg { color: #16a34a; }
.dpuwoo-modal__header h3 { margin: 0; font-size: 1.125rem; font-weight: 600; }
.dpuwoo-modal__body { padding: 1.5rem; }
.dpuwoo-modal__body p { margin: 0 0 1rem; color: var(--dpu-text-muted, #6b7280); }
.dpuwoo-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--dpu-border, #e5e7eb);
}
.dpuwoo-confirm-summary {
    background: var(--dpu-surface, #f9fafb);
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1rem;
}
.dpuwoo-update-progress { margin-top: 1rem; }
.dpuwoo-update-progress__bar {
    height: 8px;
    background: var(--dpu-border, #e5e7eb);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.75rem;
}
.dpuwoo-update-progress__fill {
    height: 100%;
    background: linear-gradient(90deg, var(--upd-500, #2563eb), var(--sim-500, #10b981));
    border-radius: 4px;
    transition: width 0.3s ease;
}
.dpuwoo-update-progress__info {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
    color: var(--dpu-text-muted, #6b7280);
    margin-bottom: 0.5rem;
}
.dpuwoo-update-progress__stats {
    text-align: center;
    font-size: 0.875rem;
    color: var(--dpu-text-muted, #6b7280);
    margin-bottom: 0.5rem;
}
.dpuwoo-update-progress__counts {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    font-size: 0.8125rem;
}
.dpuwoo-update-count strong { color: #16a34a; }
.dpuwoo-skip-count strong { color: #6b7280; }
.dpuwoo-error-count strong { color: #dc2626; }
.dpuwoo-update-progress__detail {
    background: var(--dpu-surface, #f9fafb);
    border-radius: 6px;
    padding: 0.75rem;
    margin: 0.75rem 0;
}
.dpuwoo-progress-current, .dpuwoo-progress-next {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    padding: 0.25rem 0;
}
.dpuwoo-progress-label {
    color: var(--dpu-text-muted, #6b7280);
    min-width: 80px;
}
.dpuwoo-progress-value {
    color: var(--dpu-text, #111);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 280px;
}
.dpuwoo-update-progress__log {
    max-height: 120px;
    overflow-y: auto;
    background: #1f2937;
    border-radius: 6px;
    padding: 0.5rem;
    margin: 0.75rem 0;
}
.dpuwoo-update-log {
    font-family: var(--dpu-font-mono, monospace);
    font-size: 0.75rem;
    color: #10b981;
    line-height: 1.5;
}
.dpuwoo-update-log-entry {
    padding: 2px 0;
    display: flex;
    gap: 0.5rem;
}
.dpuwoo-update-log-entry--updated { color: #10b981; }
.dpuwoo-update-log-entry--skipped { color: #9ca3af; }
.dpuwoo-update-log-entry--error { color: #ef4444; }
.dpuwoo-update-log-entry--info { color: #60a5fa; }
.dpuwoo-update-log-time {
    color: #6b7280;
    flex-shrink: 0;
}
</style>

<style>
.dpuwoo-field__result {
    margin-top: 0.5rem;
    font-size: 0.8125rem;
}
</style>
