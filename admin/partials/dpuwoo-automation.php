<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$opts = get_option('prixy_settings', []);
$cron_enabled = $opts['cron_enabled'] ?? 0;
$update_interval = $opts['update_interval'] ?? 'twicedaily';
$next_cron = \Cron::get_next_scheduled_time();

// Interval labels
$interval_labels = [
    'hourly' => 'Cada hora',
    'twicedaily' => '2 veces/día',
    'daily' => '1 vez/día',
    'weekly' => '1 vez/semana'
];

// Get last 10 cron runs
$recent_runs = $wpdb->get_results(
    "SELECT r.id, r.date, r.dollar_value, r.percentage_change, r.context,
            SUM(CASE WHEN i.status = 'updated' THEN 1 ELSE 0 END) AS updated_count,
            SUM(CASE WHEN i.status = 'skipped' THEN 1 ELSE 0 END) AS skipped_count,
            SUM(CASE WHEN i.status = 'error' THEN 1 ELSE 0 END) AS error_count
     FROM {$wpdb->prefix}prixy_runs r
     LEFT JOIN {$wpdb->prefix}prixy_run_items i ON i.run_id = r.id
     WHERE r.context = 'cron'
     GROUP BY r.id
     ORDER BY r.id DESC
     LIMIT 10"
);

// Get WP Cron events
$wp_cron_events = wp_get_scheduled_event('prixy_do_update');

// Count products affected
$total_products = wp_count_posts('product')->publish;
?>

<div class="wrap prixy-admin">

    <!-- Header -->
    <div class="prixy-header">
        <div class="prixy-header__left">
            <h1 class="prixy-header__title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Scheduler
            </h1>
            <p class="prixy-header__subtitle">Monitoreo de automatizaciones</p>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="prixy-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        
        <!-- Status Card -->
        <div class="prixy-stat" style="background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius); padding: 20px;">
            <div class="prixy-stat__label" style="font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Estado</div>
            <div class="prixy-stat__value" style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $cron_enabled ? '#22c55e' : '#9ca3af'; ?>;"></span>
                <span style="font-weight: 600; color: <?php echo $cron_enabled ? '#15803d' : '#6b7280'; ?>;">
                    <?php echo $cron_enabled ? 'Activo' : 'Inactivo'; ?>
                </span>
            </div>
        </div>

        <!-- Next Run Card -->
        <div class="prixy-stat" style="background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius); padding: 20px;">
            <div class="prixy-stat__label" style="font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Próxima ejecución</div>
            <div class="prixy-stat__value" style="font-weight: 600; color: var(--dpu-text);">
                <?php if ($cron_enabled && $next_cron): ?>
                    <?php 
                    $now = current_time('timestamp');
                    $diff = $next_cron - $now;
                    if ($diff < 0) {
                        echo '<span style="color: #dc2626;">Atrasado</span>';
                    } else {
                        $hours = floor($diff / 3600);
                        $minutes = floor(($diff % 3600) / 60);
                        if ($hours > 0) {
                            echo esc_html(wp_date('d/m H:i', $next_cron)) . ' (' . $hours . 'h ' . $minutes . 'm)';
                        } else {
                            echo esc_html(wp_date('H:i', $next_cron)) . ' (' . $minutes . ' min)';
                        }
                    }
                    ?>
                <?php else: ?>
                    <span style="color: var(--dpu-text-3);">Sin programar</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Frequency Card -->
        <div class="prixy-stat" style="background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius); padding: 20px;">
            <div class="prixy-stat__label" style="font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Frecuencia</div>
            <div class="prixy-stat__value" style="font-weight: 600; color: var(--dpu-text);">
                <?php echo esc_html($interval_labels[$update_interval] ?? 'No configurada'); ?>
            </div>
        </div>

        <!-- Products Card -->
        <div class="prixy-stat" style="background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius); padding: 20px;">
            <div class="prixy-stat__label" style="font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Productos</div>
            <div class="prixy-stat__value" style="font-weight: 600; color: var(--dpu-text);">
                <?php echo number_format($total_products); ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="margin-bottom: 24px; display: flex; gap: 12px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_settings')); ?>" class="prixy-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius-sm); color: var(--dpu-text); text-decoration: none; font-size: 14px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Configurar
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=prixy_dashboard')); ?>" class="prixy-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; background: var(--upd-600); border: none; border-radius: var(--dpu-radius-sm); color: white; text-decoration: none; font-size: 14px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Ejecutar ahora
        </a>
    </div>

    <!-- Cron Events Table -->
    <div class="prixy-section" style="background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius); padding: 20px; margin-bottom: 24px;">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            WP Cron Events
        </h2>
        
        <table class="widefat" style="border: none;">
            <thead>
                <tr style="border-bottom: 1px solid var(--dpu-border);">
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Hook</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Programado</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $scheduled_events = wp_get_scheduled_hook_times('prixy_do_update');
                if (!empty($scheduled_events)): 
                    foreach ($scheduled_events as $timestamp):
                ?>
                <tr style="border-bottom: 1px solid var(--dpu-border);">
                    <td style="padding: 12px 8px; font-family: var(--dpu-font-mono); font-size: 13px;">prixy_do_update</td>
                    <td style="padding: 12px 8px;"><?php echo esc_html(wp_date('d/m/Y H:i:s', $timestamp)); ?></td>
                    <td style="padding: 12px 8px;">
                        <?php if ($timestamp < current_time('timestamp')): ?>
                            <span style="color: #dc2626; font-size: 12px;">Atrasado</span>
                        <?php else: ?>
                            <span style="color: #16a34a; font-size: 12px;">Pendiente</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    endforeach;
                else:
                ?>
                <tr>
                    <td colspan="3" style="padding: 24px; text-align: center; color: var(--dpu-text-3);">
                        No hay eventos programados
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Runs History -->
    <div class="prixy-section" style="background: var(--dpu-surface); border: 1px solid var(--dpu-border); border-radius: var(--dpu-radius); padding: 20px;">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Historial de ejecuciones
        </h2>
        
        <?php if (!empty($recent_runs)): ?>
        <table class="widefat" style="border: none;">
            <thead>
                <tr style="border-bottom: 1px solid var(--dpu-border);">
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Fecha</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Valor</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Variación</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Actualizados</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Ignorados</th>
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--dpu-text-3); text-transform: uppercase;">Errores</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_runs as $run): ?>
                <tr style="border-bottom: 1px solid var(--dpu-border);">
                    <td style="padding: 12px 8px;"><?php echo esc_html(wp_date('d/m H:i', strtotime($run->date))); ?></td>
                    <td style="padding: 12px 8px; font-family: var(--dpu-font-mono);">
                        <?php echo $run->dollar_value ? '$' . number_format($run->dollar_value, 2) : '-'; ?>
                    </td>
                    <td style="padding: 12px 8px;">
                        <?php if ($run->percentage_change !== null): ?>
                            <span style="color: <?php echo $run->percentage_change >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo ($run->percentage_change >= 0 ? '+' : '') . number_format($run->percentage_change, 2); ?>%
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 8px;">
                        <span style="color: #16a34a; font-weight: 500;"><?php echo intval($run->updated_count); ?></span>
                    </td>
                    <td style="padding: 12px 8px; color: var(--dpu-text-3);">
                        <?php echo intval($run->skipped_count); ?>
                    </td>
                    <td style="padding: 12px 8px;">
                        <?php if ($run->error_count > 0): ?>
                            <span style="color: #dc2626;"><?php echo intval($run->error_count); ?></span>
                        <?php else: ?>
                            <span style="color: var(--dpu-text-3);">0</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; color: var(--dpu-text-3); padding: 24px;">No hay ejecuciones registradas aún.</p>
        <?php endif; ?>
    </div>

</div>
