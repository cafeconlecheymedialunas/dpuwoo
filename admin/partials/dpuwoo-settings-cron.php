<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap dpuwoo-admin">

    <?php settings_errors(); ?>

    <!-- Header -->
    <div class="dpu-header">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.25rem;">
            <div class="dpu-page-badge dpu-page-badge--cron">Auto</div>
            <h1 class="dpu-title" style="margin:0;">Automatización</h1>
        </div>
        <p class="dpu-subtitle">Controla cuándo y con qué frecuencia el sistema actualiza los precios de forma automática.</p>
    </div>

    <!-- Breadcrumb nav -->
    <div class="dpu-settings-nav">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_dashboard')); ?>" class="dpu-settings-nav__link">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
            Dashboard
        </a>
        <span class="dpu-settings-nav__sep">›</span>
        <span class="dpu-settings-nav__current">Automatización</span>
        <span class="dpu-settings-nav__sep">·</span>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings_page')); ?>" class="dpu-settings-nav__link">
            Ir a Configuración general
        </a>
    </div>

    <?php
    // Status del cron
    $opts        = get_option('dpuwoo_settings', []);
    $enabled     = $opts['cron_enabled'] ?? 1;
    $interval_s  = intval($opts['interval'] ?? 3600);
    $next        = wp_next_scheduled('dpuwoo_do_update');

    global $wpdb;
    $last_run = $wpdb->get_row(
        "SELECT r.date, r.dollar_value, r.percentage_change,
                SUM(CASE WHEN i.status = 'updated' THEN 1 ELSE 0 END) AS updated_count,
                SUM(CASE WHEN i.status = 'skipped' THEN 1 ELSE 0 END) AS skipped_count
         FROM {$wpdb->prefix}dpuwoo_runs r
         LEFT JOIN {$wpdb->prefix}dpuwoo_run_items i ON i.run_id = r.id
         GROUP BY r.id
         ORDER BY r.id DESC
         LIMIT 1"
    );
    ?>

    <!-- Status Banner -->
    <div class="dpu-cron-status <?php echo $enabled ? 'dpu-cron-status--on' : 'dpu-cron-status--off'; ?>">
        <div class="dpu-cron-status__indicator">
            <?php if ($enabled): ?>
                <span class="dpu-cron-dot dpu-cron-dot--pulse"></span>
                <span class="dpu-cron-status__label">Automatización activa</span>
            <?php else: ?>
                <span class="dpu-cron-dot dpu-cron-dot--off"></span>
                <span class="dpu-cron-status__label">Automatización desactivada</span>
            <?php endif; ?>
        </div>
        <div class="dpu-cron-status__meta">
            <?php if ($enabled && $next): ?>
                <span>Próxima ejecución: <strong><?php echo esc_html(wp_date('d/m/Y H:i', $next)); ?></strong></span>
                <span class="dpu-cron-status__sep">·</span>
                <span>En <?php
                    $diff = $next - time();
                    if ($diff < 60) echo esc_html($diff) . ' seg';
                    elseif ($diff < 3600) echo esc_html(round($diff/60)) . ' min';
                    else echo esc_html(round($diff/3600, 1)) . ' h';
                ?></span>
            <?php elseif ($enabled && !$next): ?>
                <span style="color:var(--warn-text);">Cron no programado — guarda la configuración para activar.</span>
            <?php else: ?>
                <span>Los precios no se actualizarán automáticamente.</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Última ejecución automática -->
    <?php if ($last_run): ?>
    <div class="dpu-card dpu-cron-last-run">
        <div class="dpu-cron-last-run__header">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Última ejecución automática
        </div>
        <div class="dpu-cron-last-run__grid">
            <div class="dpu-cron-stat">
                <span class="dpu-cron-stat__label">Fecha</span>
                <span class="dpu-cron-stat__value"><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($last_run->date))); ?></span>
            </div>
            <div class="dpu-cron-stat">
                <span class="dpu-cron-stat__label">Tipo de cambio</span>
                <span class="dpu-cron-stat__value"><?php echo esc_html(number_format(floatval($last_run->dollar_value), 2)); ?></span>
            </div>
            <div class="dpu-cron-stat">
                <span class="dpu-cron-stat__label">Actualizados</span>
                <span class="dpu-cron-stat__value dpu-cron-stat__value--ok"><?php echo esc_html($last_run->updated_count ?? '—'); ?></span>
            </div>
            <div class="dpu-cron-stat">
                <span class="dpu-cron-stat__label">Sin cambios</span>
                <span class="dpu-cron-stat__value dpu-cron-stat__value--skip"><?php echo esc_html($last_run->skipped_count ?? '—'); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form id="dpuwoo-settings-form" method="post" action="options.php">

        <?php settings_fields('dpuwoo_settings_group'); ?>

        <!-- Sección: Programación -->
        <div class="dpu-settings-section dpu-settings-section--cron">
            <div class="dpu-settings-section__header">
                <div class="dpu-settings-section__icon dpu-settings-section__icon--cron">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="dpu-settings-section__title">Programación del Cron</p>
                    <p class="dpu-settings-section__desc">Activa o desactiva la ejecución automática y define su frecuencia.</p>
                </div>
            </div>
            <div class="dpu-settings-section__body">
                <table class="form-table"><tbody><?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_automation_section'); ?></tbody></table>
            </div>
        </div>

        <!-- Sección: Reglas de cálculo cron -->
        <div class="dpu-settings-section dpu-settings-section--cron">
            <div class="dpu-settings-section__header">
                <div class="dpu-settings-section__icon dpu-settings-section__icon--calc" style="background:var(--upd-200);color:var(--upd-800);">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="dpu-settings-section__title">Reglas de Cálculo para el Cron</p>
                    <p class="dpu-settings-section__desc">Reglas propias del cron. Vacío = usa la configuración de Ejecución Manual como respaldo.</p>
                </div>
            </div>
            <div class="dpu-settings-section__body">
                <table class="form-table"><tbody><?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_cron_rules_section'); ?></tbody></table>
            </div>
        </div>

        <!-- Sección: Redondeo y exclusiones cron -->
        <div class="dpu-settings-section dpu-settings-section--cron">
            <div class="dpu-settings-section__header">
                <div class="dpu-settings-section__icon" style="background:var(--upd-200);color:var(--upd-800);">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                </div>
                <div>
                    <p class="dpu-settings-section__title">Redondeo y Exclusiones para el Cron</p>
                    <p class="dpu-settings-section__desc">Formato y categorías excluidas propios del cron. Vacío = usa configuración manual.</p>
                </div>
            </div>
            <div class="dpu-settings-section__body">
                <table class="form-table"><tbody><?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_cron_format_section'); ?></tbody></table>
            </div>
        </div>

        <!-- Nota informativa -->
        <div class="dpu-cron-info-box">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <strong>Cómo funciona:</strong> Los campos vacíos usan como respaldo la configuración de
                <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings_page')); ?>">Configuración</a>.
                El proveedor de API y las monedas son siempre compartidos entre ambos contextos.
                El historial de ejecuciones automáticas está en
                <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_logs')); ?>">Historial</a>.
            </div>
        </div>

        <!-- Footer -->
        <div class="dpu-settings-footer">
            <button type="submit" id="dpuwoo-save-settings" class="dpu-btn dpu-btn--cron-save">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="btn-text">Guardar automatización</span>
                <span class="btn-loading" style="display:none;">
                    <svg width="14" height="14" class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Guardando…
                </span>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings_page')); ?>" class="dpu-btn dpu-btn--ghost">
                ← Configuración general
            </a>
            <span id="dpuwoo-save-status" style="font-size:.8rem; color:var(--dpu-text-3);"></span>
        </div>

    </form>

</div><!-- .dpuwoo-admin -->
