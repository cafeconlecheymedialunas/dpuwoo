<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap dpuwoo-admin">

    <?php settings_errors(); ?>

    <!-- Header -->
    <div class="dpu-header">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <div>
                <h1 class="dpu-title">Configuración</h1>
                <p class="dpu-subtitle">API, reglas de precio y automatización del cron.</p>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_dashboard')); ?>" class="dpu-btn dpu-btn--ghost" style="font-size:0.75rem;">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
                Volver al Dashboard
            </a>
        </div>
    </div>

    <?php
    $opts       = get_option('dpuwoo_settings', []);
    $cron_enabled = $opts['cron_enabled'] ?? 1;
    $next_cron  = wp_next_scheduled('dpuwoo_do_update');

    global $wpdb;
    $last_run = $wpdb->get_row("SELECT created_at, dollar_value, updated_count, skipped_count FROM {$wpdb->prefix}dpuwoo_runs WHERE run_type != 'simulation' ORDER BY id DESC LIMIT 1");
    ?>

    <!-- Cron status banner (solo si cron está activo) -->
    <div class="dpu-cron-status <?php echo $cron_enabled ? 'dpu-cron-status--on' : 'dpu-cron-status--off'; ?>" style="margin-bottom:1.5rem;">
        <div class="dpu-cron-status__indicator">
            <?php if ($cron_enabled): ?>
                <span class="dpu-cron-dot dpu-cron-dot--pulse"></span>
                <span class="dpu-cron-status__label">Automatización activa</span>
            <?php else: ?>
                <span class="dpu-cron-dot dpu-cron-dot--off"></span>
                <span class="dpu-cron-status__label">Automatización desactivada</span>
            <?php endif; ?>
        </div>
        <div class="dpu-cron-status__meta">
            <?php if ($cron_enabled && $next_cron): ?>
                <span>Próxima ejecución: <strong><?php echo esc_html(wp_date('d/m/Y H:i', $next_cron)); ?></strong></span>
            <?php elseif ($cron_enabled && !$next_cron): ?>
                <span>Cron no programado — guardá la configuración para activar.</span>
            <?php else: ?>
                <span>Los precios no se actualizarán automáticamente.</span>
            <?php endif; ?>
        </div>
    </div>

    <form id="dpuwoo-settings-form" method="post" action="options.php">

        <?php settings_fields('dpuwoo_settings_group'); ?>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- SECCIÓN 1: Conexión                               -->
        <!-- ══════════════════════════════════════════════════ -->
        <div class="dpu-cfg-section">
            <div class="dpu-cfg-section__header">
                <div class="dpu-cfg-section__icon dpu-cfg-section__icon--api">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                </div>
                <div>
                    <p class="dpu-cfg-section__title">Conexión</p>
                    <p class="dpu-cfg-section__desc">Proveedor de API, monedas y tasa histórica de referencia.</p>
                </div>
            </div>
            <div class="dpu-cfg-section__body">
                <table class="form-table"><tbody>
                    <?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_main_section'); ?>
                </tbody></table>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- SECCIÓN 2: Reglas de Precio                       -->
        <!-- ══════════════════════════════════════════════════ -->
        <div class="dpu-cfg-section">
            <div class="dpu-cfg-section__header">
                <div class="dpu-cfg-section__icon dpu-cfg-section__icon--rules">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="dpu-cfg-section__title">Reglas de Precio</p>
                    <p class="dpu-cfg-section__desc">Margen, umbrales, dirección y redondeo. Se aplican a las ejecuciones manuales y al cron (salvo que el cron tenga sus propias reglas).</p>
                </div>
            </div>
            <div class="dpu-cfg-section__body">
                <p class="dpu-cfg-group-label">Cálculo</p>
                <table class="form-table"><tbody>
                    <?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_calculation_section'); ?>
                </tbody></table>

                <p class="dpu-cfg-group-label" style="margin-top:1rem;">Redondeo</p>
                <table class="form-table"><tbody>
                    <?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_rounding_section'); ?>
                </tbody></table>

                <p class="dpu-cfg-group-label" style="margin-top:1rem;">Exclusiones</p>
                <table class="form-table"><tbody>
                    <?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_exclusion_section'); ?>
                </tbody></table>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- SECCIÓN 3: Automatización                         -->
        <!-- ══════════════════════════════════════════════════ -->
        <div class="dpu-cfg-section">
            <div class="dpu-cfg-section__header dpu-cfg-section__header--cron">
                <div class="dpu-cfg-section__icon dpu-cfg-section__icon--cron">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="dpu-cfg-section__title">Automatización</p>
                    <p class="dpu-cfg-section__desc">Activá el cron y definí con qué frecuencia actualiza los precios.</p>
                </div>
            </div>
            <div class="dpu-cfg-section__body">

                <!-- Campos base del cron -->
                <table class="form-table"><tbody>
                    <?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_automation_section'); ?>
                </tbody></table>

                <!-- Acordeón: Reglas propias del cron -->
                <div class="dpu-accordion" id="dpu-cron-overrides-accordion">
                    <button type="button" class="dpu-accordion__trigger" aria-expanded="false" aria-controls="dpu-cron-overrides-body">
                        <span class="dpu-accordion__trigger-label">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            Reglas específicas del cron
                            <span class="dpu-accordion__badge">opcional</span>
                        </span>
                        <svg class="dpu-accordion__chevron" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="dpu-accordion__body" id="dpu-cron-overrides-body" hidden>
                        <p class="dpu-accordion__hint">
                            Dejá los campos vacíos para usar las <strong>Reglas de Precio</strong> de arriba como respaldo. Solo completá los que querés que sean distintos en la ejecución automática.
                        </p>
                        <p class="dpu-cfg-group-label">Cálculo del cron</p>
                        <table class="form-table"><tbody>
                            <?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_cron_rules_section'); ?>
                        </tbody></table>
                        <p class="dpu-cfg-group-label" style="margin-top:1rem;">Redondeo y exclusiones del cron</p>
                        <table class="form-table"><tbody>
                            <?php do_settings_fields('dpuwoo_settings_page', 'dpuwoo_cron_format_section'); ?>
                        </tbody></table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Footer -->
        <div class="dpu-cfg-footer">
            <button type="submit" class="dpu-btn dpu-btn--save">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span class="btn-text">Guardar configuración</span>
                <span class="btn-loading" style="display:none;">
                    <svg width="14" height="14" class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Guardando…
                </span>
            </button>
            <span id="dpuwoo-save-status" style="font-size:.8rem;color:var(--dpu-text-3);"></span>
        </div>

    </form>

</div><!-- .dpuwoo-admin -->
