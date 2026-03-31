<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap dpuwoo-admin">

    <?php settings_errors(); ?>

    <!-- Header -->
    <div class="dpu-header">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <div>
                <h1 class="dpu-title">Dollar Price Engine</h1>
                <p class="dpu-subtitle">Motor de actualización de precios por tipo de cambio — WooCommerce</p>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings_page')); ?>" class="dpu-btn dpu-btn--ghost" style="font-size:0.75rem;">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Configuración
            </a>
        </div>
    </div>

    <?php
    global $wpdb;
    $opts           = get_option('dpuwoo_settings', []);
    $last_dollar    = $wpdb->get_var("SELECT dollar_value FROM {$wpdb->prefix}dpuwoo_runs ORDER BY id DESC LIMIT 1");
    $product_count  = wp_count_posts('product');
    $total_products = ($product_count->publish ?? 0) + ($product_count->draft ?? 0);
    $store_currency = \Dpuwoo\Helpers\dpuwoo_get_store_currency();
    $cron_enabled   = $opts['cron_enabled'] ?? 1;
    $next_cron      = wp_next_scheduled('dpuwoo_do_update');

    // Labels
    switch ($opts['update_direction'] ?? 'bidirectional') {
        case 'up_only':   $direction_label = 'Solo subida'; break;
        case 'down_only': $direction_label = 'Solo bajada'; break;
        default:          $direction_label = 'Ambas';
    }
    switch ($opts['rounding_type'] ?? 'integer') {
        case 'none':    $rounding_label = 'Sin redondeo'; break;
        case 'integer': $rounding_label = 'Enteros'; break;
        case 'ceil':    $rounding_label = 'Hacia arriba'; break;
        case 'floor':   $rounding_label = 'Hacia abajo'; break;
        case 'nearest': $rounding_label = 'Al más cercano'; break;
        default:        $rounding_label = ucfirst($opts['rounding_type'] ?? '');
    }
    $providers    = class_exists('API_Client') ? API_Client::get_available_providers() : [];
    $provider_key = $opts['api_provider'] ?? '';
    $provider_name = $providers[$provider_key]['name'] ?? ($provider_key ?: '—');
    ?>

    <!-- Status Bar -->
    <div class="dpu-status-bar">

        <div class="dpu-status-bar__stat">
            <span class="dpu-status-bar__label">Tipo de cambio</span>
            <span class="dpu-status-bar__value dpu-status-bar__value--mono">
                <?php echo $last_dollar ? esc_html(number_format(floatval($last_dollar), 2)) : '—'; ?>
            </span>
        </div>

        <div class="dpu-status-bar__divider"></div>

        <div class="dpu-status-bar__stat">
            <span class="dpu-status-bar__label">Productos</span>
            <span class="dpu-status-bar__value dpu-status-bar__value--mono"><?php echo esc_html($total_products); ?></span>
        </div>

        <div class="dpu-status-bar__divider"></div>

        <div class="dpu-status-bar__stat">
            <span class="dpu-status-bar__label">Proveedor</span>
            <span class="dpu-status-bar__value"><?php echo esc_html($provider_name); ?></span>
        </div>

        <div class="dpu-status-bar__divider"></div>

        <div class="dpu-status-bar__stat">
            <span class="dpu-status-bar__label">Cron</span>
            <span class="dpu-status-bar__value">
                <?php if ($cron_enabled && $next_cron): ?>
                    <span class="dpu-cron-dot dpu-cron-dot--pulse" style="display:inline-block;margin-right:4px;"></span>
                    <?php echo esc_html(wp_date('H:i', $next_cron)); ?>
                <?php elseif ($cron_enabled): ?>
                    <span style="color:var(--warn-text);">Sin programar</span>
                <?php else: ?>
                    <span style="color:var(--dpu-text-3);">Desactivado</span>
                <?php endif; ?>
            </span>
        </div>

        <div class="dpu-status-bar__actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_logs')); ?>" class="dpu-btn dpu-btn--ghost" style="font-size:0.72rem;padding:0.35rem 0.75rem;">
                Ver historial →
            </a>
        </div>

    </div>

    <!-- Config Summary (read-only) -->
    <div class="dpu-config dpu-card" style="margin-bottom:1.25rem;">
        <div class="dpu-config__header">
            <span class="dpu-config__title">Configuración activa</span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings_page')); ?>" style="font-size:0.72rem;color:var(--dpu-text-3);text-decoration:none;" onmouseover="this.style.color='var(--dpu-text)'" onmouseout="this.style.color='var(--dpu-text-3)'">Editar →</a>
        </div>
        <div class="dpu-config-grid">
            <div>
                <p class="dpu-config-group__title"><span class="dpu-config-group__dot dpu-config-group__dot--neutral"></span>Origen</p>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Proveedor API</span><span class="dpu-config-row__val"><?php echo esc_html($provider_name); ?></span></div>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Moneda ref.</span><span class="dpu-config-row__val"><?php echo esc_html($opts['reference_currency'] ?? 'USD'); ?></span></div>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Moneda tienda</span><span class="dpu-config-row__val"><?php echo esc_html($store_currency); ?></span></div>
            </div>
            <div>
                <p class="dpu-config-group__title"><span class="dpu-config-group__dot dpu-config-group__dot--sim"></span>Reglas</p>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Margen</span><span class="dpu-config-row__val"><?php echo esc_html(number_format(floatval($opts['margin'] ?? 0), 2)); ?>%</span></div>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Umbral</span><span class="dpu-config-row__val"><?php echo esc_html(number_format(floatval($opts['threshold'] ?? 0.5), 2)); ?>%</span></div>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Dirección</span><span class="dpu-config-row__val"><?php echo esc_html($direction_label); ?></span></div>
            </div>
            <div>
                <p class="dpu-config-group__title"><span class="dpu-config-group__dot dpu-config-group__dot--upd"></span>Formato</p>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Redondeo</span><span class="dpu-config-row__val"><?php echo esc_html($rounding_label); ?></span></div>
                <?php if (($opts['rounding_type'] ?? '') === 'nearest'): ?>
                <div class="dpu-config-row"><span class="dpu-config-row__key">Redondear a</span><span class="dpu-config-row__val">$<?php echo esc_html($opts['nearest_to'] ?? '1'); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Action Zone -->
    <div class="dpu-action-zone">

        <div class="dpu-action-card dpu-action-card--sim">
            <span class="dpu-action-card__step">Paso 1 · Opcional</span>
            <h3 class="dpu-action-card__title">Simular Impacto</h3>
            <p class="dpu-action-card__desc">Previsualiza cómo cambiarían los precios. No modifica ningún dato real.</p>
            <button id="dpuwoo-simulate" class="dpu-btn dpu-btn--sim">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Iniciar simulación
            </button>
        </div>

        <div class="dpu-action-card dpu-action-card--update">
            <span class="dpu-action-card__step">Acción directa</span>
            <h3 class="dpu-action-card__title">Actualizar Precios</h3>
            <p class="dpu-action-card__desc">Aplica el tipo de cambio actual a todos los productos directamente.</p>
            <button id="dpuwoo-update-now" class="dpu-btn dpu-btn--update">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Actualizar ahora
            </button>
        </div>

    </div>

    <!-- Simulation Process -->
    <div id="dpuwoo-simulation-process" class="hidden dpu-mt">
        <div class="dpu-process dpu-process--sim">
            <div class="dpu-process__header">
                <div class="dpu-process__icon">1</div>
                <div>
                    <p class="dpu-process__title">Simulación en progreso</p>
                    <p class="dpu-process__subtitle">Calculando cambios de precio sin modificar datos…</p>
                </div>
            </div>
            <div class="dpu-process__body">
                <div class="dpu-progress dpu-progress--sim">
                    <div class="dpu-progress-meta">
                        <span class="dpu-progress-label" id="dpuwoo-sim-text">Iniciando simulación…</span>
                        <span class="dpu-progress-pct"   id="dpuwoo-sim-percent">0%</span>
                    </div>
                    <div class="dpu-progress-track">
                        <div class="dpu-progress-fill" id="dpuwoo-sim-progress" style="width:0%"></div>
                    </div>
                </div>
                <div class="dpu-batch-info" id="dpuwoo-sim-batch-info">
                    <span>Lote <strong id="dpuwoo-sim-current-batch">0</strong> / <strong id="dpuwoo-sim-total-batches">0</strong></span>
                    <span>Productos <strong id="dpuwoo-sim-processed-products">0</strong> / <strong id="dpuwoo-sim-total-products">0</strong></span>
                </div>
                <button id="dpuwoo-cancel-simulation" class="dpu-btn dpu-btn--danger">Cancelar simulación</button>
            </div>
        </div>
    </div>

    <!-- Simulation Results -->
    <div id="dpuwoo-simulation-results" class="hidden dpu-mt">
        <div class="dpu-results dpu-results--sim">
            <div class="dpu-results__header">
                <div>
                    <p class="dpu-results__title">Simulación completada</p>
                    <p class="dpu-results__subtitle">Revisa los cambios propuestos antes de continuar</p>
                </div>
                <div class="dpu-results__checkmark">✓</div>
            </div>
            <div class="dpu-results__body">
                <div class="dpu-results__summary" id="dpuwoo-sim-summary"></div>
                <div id="dpuwoo-sim-results-table"></div>
                <div class="dpu-results__actions">
                    <button id="dpuwoo-proceed-update" class="dpu-btn dpu-btn--update">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Paso 2: Confirmar actualización
                    </button>
                    <button id="dpuwoo-new-simulation" class="dpu-btn dpu-btn--ghost">Nueva simulación</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Process -->
    <div id="dpuwoo-update-process" class="hidden dpu-mt">
        <div class="dpu-process dpu-process--update">
            <div class="dpu-process__header">
                <div class="dpu-process__icon">2</div>
                <div>
                    <p class="dpu-process__title">Actualización en progreso</p>
                    <p class="dpu-process__subtitle">Aplicando cambios reales a los productos…</p>
                </div>
            </div>
            <div class="dpu-process__body">
                <div class="dpu-progress dpu-progress--update">
                    <div class="dpu-progress-meta">
                        <span class="dpu-progress-label" id="dpuwoo-update-text">Iniciando actualización…</span>
                        <span class="dpu-progress-pct"   id="dpuwoo-update-percent">0%</span>
                    </div>
                    <div class="dpu-progress-track">
                        <div class="dpu-progress-fill" id="dpuwoo-update-progress" style="width:0%"></div>
                    </div>
                </div>
                <div class="dpu-batch-info" id="dpuwoo-update-batch-info">
                    <span>Lote <strong id="dpuwoo-update-current-batch">0</strong> / <strong id="dpuwoo-update-total-batches">0</strong></span>
                    <span>Productos <strong id="dpuwoo-update-processed-products">0</strong> / <strong id="dpuwoo-update-total-products">0</strong></span>
                </div>
                <div id="dpuwoo-update-live-results" class="hidden dpu-live-counters">
                    <div class="dpu-live-counter">
                        <span class="dpu-live-counter__num dpu-live-counter__num--ok"   id="dpuwoo-live-updated">0</span>
                        <span class="dpu-live-counter__label">Actualizados</span>
                    </div>
                    <div class="dpu-live-counter">
                        <span class="dpu-live-counter__num dpu-live-counter__num--skip" id="dpuwoo-live-skipped">0</span>
                        <span class="dpu-live-counter__label">Sin cambios</span>
                    </div>
                    <div class="dpu-live-counter">
                        <span class="dpu-live-counter__num dpu-live-counter__num--err"  id="dpuwoo-live-errors">0</span>
                        <span class="dpu-live-counter__label">Errores</span>
                    </div>
                </div>
                <button id="dpuwoo-cancel-update" class="dpu-btn dpu-btn--danger">Cancelar actualización</button>
            </div>
        </div>
    </div>

    <!-- Final Results -->
    <div id="dpuwoo-final-results" class="hidden dpu-mt"></div>

</div><!-- .dpuwoo-admin -->

<!-- MODAL: Confirmación actualización directa -->
<div id="dpuwoo-direct-update-modal" class="hidden dpu-modal-overlay">
    <div class="dpu-modal">
        <div class="dpu-modal__header">
            <div class="dpu-modal-icon dpu-modal-icon--warn">⚠</div>
            <h3 class="dpu-modal__title">Actualización directa</h3>
        </div>
        <div class="dpu-modal__body">
            <p>Estás a punto de actualizar los precios <strong>sin simulación previa</strong>. Esta acción modificará directamente los precios de todos los productos.</p>
            <div class="dpu-modal-alert dpu-modal-alert--warn">Para mayor seguridad, considerá primero ejecutar una simulación.</div>
        </div>
        <div class="dpu-modal__footer">
            <button id="dpuwoo-direct-cancel"  class="dpu-btn dpu-btn--ghost">Cancelar</button>
            <button id="dpuwoo-direct-proceed" class="dpu-btn dpu-btn--update">Entiendo, proceder</button>
        </div>
    </div>
</div>

<!-- MODAL: Confirmación post-simulación -->
<div id="dpuwoo-confirm-update-modal" class="hidden dpu-modal-overlay">
    <div class="dpu-modal">
        <div class="dpu-modal__header">
            <div class="dpu-modal-icon dpu-modal-icon--ok">✓</div>
            <h3 class="dpu-modal__title">Confirmar actualización</h3>
        </div>
        <div class="dpu-modal__body">
            <p id="dpuwoo-confirm-message">¿Confirmás la actualización real de precios según la simulación?</p>
            <div class="dpu-modal-confirm-summary" id="dpuwoo-confirm-summary"></div>
        </div>
        <div class="dpu-modal__footer">
            <button id="dpuwoo-confirm-cancel"  class="dpu-btn dpu-btn--ghost">Cancelar</button>
            <button id="dpuwoo-confirm-proceed" class="dpu-btn dpu-btn--confirm">Sí, actualizar precios</button>
        </div>
    </div>
</div>

<!-- MODAL: Detalles de ejecución -->
<div id="dpuwoo-run-details-modal" class="hidden dpu-modal-overlay">
    <div class="dpu-modal dpu-modal--lg">
        <div class="dpu-modal__header">
            <h3 class="dpu-modal__title">Detalles de la ejecución</h3>
            <button id="dpuwoo-close-details-modal" class="dpu-modal__close">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="dpu-modal__body"><div id="dpuwoo-run-details-content"></div></div>
        <div class="dpu-modal__footer">
            <button id="dpuwoo-close-details-modal-2" class="dpu-btn dpu-btn--ghost">Cerrar</button>
        </div>
    </div>
</div>
