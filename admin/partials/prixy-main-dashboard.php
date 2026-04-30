<?php
if (!defined('ABSPATH')) exit;

// Si el setup no está completo, redirigir a Configuración
if (!Admin::is_setup_complete()) {
    wp_redirect(admin_url('admin.php?page=prixy_configuration'));
    exit;
}

$opts          = get_option('prixy_settings', []);
$cron_enabled  = !empty($opts['cron_enabled']);
$api_provider  = $opts['api_provider'] ?? 'dolarapi';
$dollar_type   = $opts['dollar_type']  ?? 'oficial';
$next_cron     = Cron::get_next_scheduled_time();

$provider_labels = [
    'dolarapi'       => 'DolarAPI',
    'jsdelivr'      => 'Jsdelivr',
    'cryptoprice'   => 'CoinGecko',
    'moneyconvert'  => 'MoneyConvert',
    'hexarate'     => 'HexaRate',
    'foreignrate'   => 'ForeignRate',
    'currencyapi'  => 'CurrencyAPI',
    'exchangerate'  => 'ExchangeRate-API',
];
$provider_label = $provider_labels[$api_provider] ?? $api_provider;
$product_count  = Log_Repository::get_instance()->count_all_products();
?>

<div class="wrap prixy-admin">

    <!-- ── Header ─────────────────────────────────────────────────────────────── -->
    <div class="prixy-header">
        <div class="prixy-header__left">
            <h1 class="prixy-header__title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </h1>
            <p class="prixy-header__subtitle">Panel de control — Dollar Sync</p>
        </div>
        <!-- Status pill -->
        <div style="display:flex; align-items:center; gap:8px; padding:6px 14px; background:<?php echo $cron_enabled ? '#f0fdf4' : '#fef2f2'; ?>; border:1px solid <?php echo $cron_enabled ? '#bbf7d0' : '#fecaca'; ?>; border-radius:20px;">
            <span style="width:8px; height:8px; border-radius:50%; background:<?php echo $cron_enabled ? '#22c55e' : '#ef4444'; ?>; display:inline-block;"></span>
            <span style="font-size:12px; font-weight:600; color:<?php echo $cron_enabled ? '#15803d' : '#dc2626'; ?>;">
                Auto <?php echo $cron_enabled ? 'activa' : 'inactiva'; ?>
            </span>
        </div>
    </div>

    <!-- ── Aviso: primera actualización pendiente ───────────────────────────── -->
    <?php if (!$_setup['first_run_done']): ?>
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px 18px; margin-bottom:18px; display:flex; align-items:center; gap:12px; font-size:13px; color:#1d4ed8;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Sistema listo. Podés <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_dashboard')); ?>" style="color:#2563eb; font-weight:600; text-decoration:none;">ejecutar la primera actualización</a> cuando quieras, o activar el cron para que corra automáticamente.</span>
    </div>
    <?php endif; ?>

    <!-- ── Hero: Simulate + KPI strip ────────────────────────────────────────── -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">

        <!-- Simulate card -->
        <div style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%); border-radius:14px; padding:28px 28px 24px; color:#fff; display:flex; flex-direction:column; justify-content:space-between; min-height:180px;">
            <div>
                <div style="font-size:12px; font-weight:600; opacity:.8; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px;">Operación rápida</div>
                <div style="font-size:22px; font-weight:700; line-height:1.2; margin-bottom:6px;">Simular impacto<br>de precios</div>
                <div style="font-size:13px; opacity:.75;">Previsualiza los cambios antes de aplicarlos</div>
            </div>
            <div style="display:flex; gap:10px; align-items:center; margin-top:20px; flex-wrap:wrap;">
                <button id="prixy-btn-simulate" style="display:inline-flex; align-items:center; gap:8px; background:#fff; color:#6366f1; border:none; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:700; cursor:pointer; transition:opacity .15s;">
                    <svg id="prixy-sim-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span id="prixy-sim-label">Simular ahora</span>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_dashboard')); ?>" style="font-size:13px; color:rgba(255,255,255,.85); text-decoration:none; font-weight:500;">
                    Ver página completa →
                </a>
            </div>
            <!-- Progress bar (visible while running) -->
            <div id="prixy-sim-progress-wrap" style="display:none; margin-top:14px;">
                <div style="background:rgba(255,255,255,.25); border-radius:4px; height:5px; overflow:hidden;">
                    <div id="prixy-sim-progress-bar" style="height:100%; background:#fff; width:0%; transition:width .35s;"></div>
                </div>
                <div id="prixy-sim-progress-label" style="font-size:12px; color:rgba(255,255,255,.8); margin-top:6px;">Procesando...</div>
            </div>
        </div>

        <!-- KPI strip (right column) -->
        <div style="display:flex; flex-direction:column; gap:12px;">

            <!-- Rate -->
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 18px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <div style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Última tasa aplicada</div>
                    <div id="prixy-kpi-rate" style="font-size:22px; font-weight:700; color:#111827;">—</div>
                    <div id="prixy-kpi-rate-type" style="font-size:11px; color:#9ca3af; margin-top:2px;"><?php echo esc_html($dollar_type); ?></div>
                </div>
                <div style="background:#f0fdf4; border-radius:10px; padding:10px;">
                    <svg width="22" height="22" fill="none" stroke="#22c55e" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- Products + Next cron (2 cols) -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 18px;">
                    <div style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Productos</div>
                    <div style="font-size:22px; font-weight:700; color:#3b82f6;"><?php echo number_format($product_count); ?></div>
                    <div style="font-size:11px; color:#9ca3af; margin-top:2px;">publicados</div>
                </div>
                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 18px;">
                    <div style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Próx. auto</div>
                    <div id="prixy-kpi-next-cron" style="font-size:18px; font-weight:700; color:#f97316;"><?php echo $cron_enabled ? '...' : '—'; ?></div>
                    <div id="prixy-kpi-next-cron-sub" style="font-size:11px; color:#9ca3af; margin-top:2px;"><?php echo $cron_enabled ? '' : 'Inactiva'; ?></div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Simulation result panel (hidden by default) ────────────────────────── -->
    <div id="prixy-sim-result" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:12px; padding:18px 22px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <svg width="20" height="20" fill="none" stroke="#6366f1" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <div style="font-size:13px; font-weight:600; color:#4f46e5;" id="prixy-sim-result-title">Resultado de simulación</div>
                    <div id="prixy-sim-summary" style="font-size:13px; color:#6b7280; margin-top:2px;">—</div>
                </div>
            </div>
            <div id="prixy-sim-actions" style="display:flex; gap:10px; align-items:center;">
                <button id="prixy-btn-apply" style="font-size:13px; font-weight:600; color:#fff; background:#6366f1; padding:8px 16px; border-radius:7px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                    <svg id="prixy-apply-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span id="prixy-apply-label">Aplicar cambios</span>
                </button>
                <button id="prixy-sim-close" style="background:none; border:none; cursor:pointer; color:#9ca3af; font-size:18px; line-height:1; padding:4px;">×</button>
            </div>
        </div>
        <!-- Update progress bar (shown when applying) -->
        <div id="prixy-apply-progress-wrap" style="display:none; margin-top:12px;">
            <div style="background:#e8e3fc; border-radius:4px; height:4px; overflow:hidden;">
                <div id="prixy-apply-progress-bar" style="height:100%; background:#6366f1; width:0%; transition:width .35s;"></div>
            </div>
            <div id="prixy-apply-progress-label" style="font-size:12px; color:#6b7280; margin-top:5px;"></div>
        </div>
    </div>

    <!-- ── Quick Access ───────────────────────────────────────────────────────── -->
    <div style="margin-bottom:20px;">
        <div style="font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px;">Accesos directos</div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px;">

            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_dashboard')); ?>" class="prixy-access-link" style="display:flex; align-items:center; gap:10px; padding:13px 16px; background:#fff; border:1px solid #e5e7eb; border-radius:9px; text-decoration:none; color:#374151; font-size:13px; font-weight:500; transition:all .15s;">
                <svg width="17" height="17" fill="none" stroke="#3b82f6" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Actualización manual
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_configuration')); ?>" class="prixy-access-link" style="display:flex; align-items:center; gap:10px; padding:13px 16px; background:#fff; border:1px solid #e5e7eb; border-radius:9px; text-decoration:none; color:#374151; font-size:13px; font-weight:500; transition:all .15s;">
                <svg width="17" height="17" fill="none" stroke="#a855f7" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Configuración
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_automation')); ?>" class="prixy-access-link" style="display:flex; align-items:center; gap:10px; padding:13px 16px; background:#fff; border:1px solid #e5e7eb; border-radius:9px; text-decoration:none; color:#374151; font-size:13px; font-weight:500; transition:all .15s;">
                <svg width="17" height="17" fill="none" stroke="#f97316" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Automatización
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_logs')); ?>" class="prixy-access-link" style="display:flex; align-items:center; gap:10px; padding:13px 16px; background:#fff; border:1px solid #e5e7eb; border-radius:9px; text-decoration:none; color:#374151; font-size:13px; font-weight:500; transition:all .15s;">
                <svg width="17" height="17" fill="none" stroke="#6b7280" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Historial
            </a>

        </div>
    </div>

    <!-- ── Recent Activity ────────────────────────────────────────────────────── -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="font-size:14px; font-weight:600; color:#111827; margin:0;">Actividad reciente</h3>
            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_logs')); ?>" style="font-size:12px; color:#6366f1; text-decoration:none; font-weight:500;">Ver todo →</a>
        </div>

        <div id="prixy-activity-loading" style="text-align:center; padding:28px 0; color:#9ca3af; font-size:13px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="animation:spin 1s linear infinite; display:inline-block; margin-right:6px; vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Cargando...
        </div>
        <div id="prixy-activity-empty" style="display:none; text-align:center; padding:28px 0; color:#9ca3af; font-size:13px;">
            Sin ejecuciones aún.
            <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_dashboard')); ?>" style="color:#6366f1; text-decoration:none; margin-left:4px;">Iniciar primera actualización →</a>
        </div>

        <table id="prixy-activity-table" style="display:none; width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <th style="text-align:left; padding:7px 10px; font-weight:600; color:#9ca3af; font-size:11px; text-transform:uppercase;">Fecha</th>
                    <th style="text-align:left; padding:7px 10px; font-weight:600; color:#9ca3af; font-size:11px; text-transform:uppercase;">Tipo</th>
                    <th style="text-align:right; padding:7px 10px; font-weight:600; color:#9ca3af; font-size:11px; text-transform:uppercase;">Tasa</th>
                    <th style="text-align:right; padding:7px 10px; font-weight:600; color:#9ca3af; font-size:11px; text-transform:uppercase;">Productos</th>
                    <th style="text-align:right; padding:7px 10px; font-weight:600; color:#9ca3af; font-size:11px; text-transform:uppercase;">Variación</th>
                    <th style="text-align:right; padding:7px 10px; font-weight:600; color:#9ca3af; font-size:11px; text-transform:uppercase;">Acciones</th>
                </tr>
            </thead>
            <tbody id="prixy-activity-tbody"></tbody>
        </table>
    </div>

    <!-- ── Statistics (secondary) ────────────────────────────────────────────── -->
    <details style="margin-bottom:20px;">
        <summary style="cursor:pointer; font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; padding:6px 0; list-style:none; display:flex; align-items:center; gap:6px; user-select:none;">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            Estadísticas globales
        </summary>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-top:12px;">

            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:9px; padding:14px 16px; text-align:center;">
                <div id="prixy-stat-runs" style="font-size:26px; font-weight:700; color:#6366f1;">—</div>
                <div style="font-size:12px; color:#6b7280; margin-top:3px;">Ejecuciones</div>
            </div>

            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:9px; padding:14px 16px; text-align:center;">
                <div id="prixy-stat-products" style="font-size:26px; font-weight:700; color:#3b82f6;">—</div>
                <div style="font-size:12px; color:#6b7280; margin-top:3px;">Actualizaciones</div>
            </div>

            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:9px; padding:14px 16px; text-align:center;">
                <div id="prixy-stat-avg" style="font-size:26px; font-weight:700; color:#111827;">—</div>
                <div style="font-size:12px; color:#6b7280; margin-top:3px;">Cambio promedio</div>
            </div>

            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:9px; padding:14px 16px; text-align:center;">
                <div id="prixy-stat-success" style="font-size:26px; font-weight:700; color:#22c55e;">—</div>
                <div style="font-size:12px; color:#6b7280; margin-top:3px;">Tasa de éxito</div>
            </div>

        </div>

        <!-- Charts inside stats section -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(380px, 1fr)); gap:14px; margin-top:14px;">
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:18px;">
                <div style="font-size:13px; font-weight:600; color:#111827; margin-bottom:4px;">Evolución del tipo de cambio</div>
                <div style="font-size:11px; color:#9ca3af; margin-bottom:14px;">Últimas 30 ejecuciones</div>
                <div id="prixy-chart-rate-empty" style="display:none; text-align:center; padding:32px 0; color:#9ca3af; font-size:12px;">Sin datos</div>
                <canvas id="prixy-chart-rate" style="max-height:180px;"></canvas>
            </div>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:18px;">
                <div style="font-size:13px; font-weight:600; color:#111827; margin-bottom:4px;">Productos por ejecución</div>
                <div style="font-size:11px; color:#9ca3af; margin-bottom:14px;">Últimas 10 ejecuciones</div>
                <div id="prixy-chart-products-empty" style="display:none; text-align:center; padding:32px 0; color:#9ca3af; font-size:12px;">Sin datos</div>
                <canvas id="prixy-chart-products" style="max-height:180px;"></canvas>
            </div>
        </div>
    </details>

</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.prixy-access-link:hover { border-color:#c7d2fe !important; background:#fafafe !important; }
#prixy-btn-simulate:hover { opacity:.9; }
details > summary::-webkit-details-marker { display:none; }
</style>
