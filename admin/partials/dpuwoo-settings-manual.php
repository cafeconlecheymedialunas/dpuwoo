<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap dpuwoo-admin">

    <?php settings_errors(); ?>

    <!-- Header -->
    <div class="dpu-header">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.25rem;">
            <div class="dpu-page-badge dpu-page-badge--manual">Manual</div>
            <h1 class="dpu-title" style="margin:0;">Configuración de Ejecución Manual</h1>
        </div>
        <p class="dpu-subtitle">Parámetros de API, cálculo y redondeo que se aplican cuando ejecutás una simulación o actualización manual.</p>
    </div>

    <!-- Breadcrumb nav -->
    <div class="dpu-settings-nav">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_dashboard')); ?>" class="dpu-settings-nav__link">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
            Dashboard
        </a>
        <span class="dpu-settings-nav__sep">›</span>
        <span class="dpu-settings-nav__current">Ejecución Manual</span>
        <span class="dpu-settings-nav__sep">·</span>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_cron_settings')); ?>" class="dpu-settings-nav__link">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Ir a Automatización →
        </a>
    </div>

    <!-- Form -->
    <form id="dpuwoo-settings-form" method="post" action="options.php">

        <?php settings_fields('dpuwoo_settings_group'); ?>

        <!-- Sección: Origen -->
        <div class="dpu-settings-section dpu-settings-section--manual">
            <div class="dpu-settings-section__header">
                <div class="dpu-settings-section__icon dpu-settings-section__icon--origin">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                    </svg>
                </div>
                <div>
                    <p class="dpu-settings-section__title">Configuración de Origen</p>
                    <p class="dpu-settings-section__desc">Proveedor de API, monedas y tasa histórica de referencia.</p>
                </div>
            </div>
            <div class="dpu-settings-section__body">
                <table class="form-table"><tbody><?php do_settings_fields('dpuwoo_manual_settings', 'dpuwoo_main_section'); ?></tbody></table>
            </div>
        </div>

        <!-- Sección: Cálculo -->
        <div class="dpu-settings-section dpu-settings-section--manual">
            <div class="dpu-settings-section__header">
                <div class="dpu-settings-section__icon dpu-settings-section__icon--calc">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="dpu-settings-section__title">Lógica de Cálculo</p>
                    <p class="dpu-settings-section__desc">Margen, umbrales de variación y dirección de actualización.</p>
                </div>
            </div>
            <div class="dpu-settings-section__body">
                <table class="form-table"><tbody><?php do_settings_fields('dpuwoo_manual_settings', 'dpuwoo_calculation_section'); ?></tbody></table>
            </div>
        </div>

        <!-- Sección: Redondeo -->
        <div class="dpu-settings-section dpu-settings-section--manual">
            <div class="dpu-settings-section__header">
                <div class="dpu-settings-section__icon dpu-settings-section__icon--round">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                </div>
                <div>
                    <p class="dpu-settings-section__title">Reglas de Redondeo</p>
                    <p class="dpu-settings-section__desc">Cómo se formatean los precios calculados.</p>
                </div>
            </div>
            <div class="dpu-settings-section__body">
                <table class="form-table"><tbody><?php do_settings_fields('dpuwoo_manual_settings', 'dpuwoo_rounding_section'); ?></tbody></table>
            </div>
        </div>

        <!-- Sección: Exclusiones -->
        <div class="dpu-settings-section dpu-settings-section--manual">
            <div class="dpu-settings-section__header">
                <div class="dpu-settings-section__icon dpu-settings-section__icon--excl">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
                <div>
                    <p class="dpu-settings-section__title">Exclusiones</p>
                    <p class="dpu-settings-section__desc">Categorías que no serán afectadas por ninguna actualización.</p>
                </div>
            </div>
            <div class="dpu-settings-section__body">
                <table class="form-table"><tbody><?php do_settings_fields('dpuwoo_manual_settings', 'dpuwoo_exclusion_section'); ?></tbody></table>
            </div>
        </div>

        <!-- Footer -->
        <div class="dpu-settings-footer">
            <button type="submit" id="dpuwoo-save-settings" class="dpu-btn dpu-btn--save">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="btn-text">Guardar configuración manual</span>
                <span class="btn-loading" style="display:none;">
                    <svg width="14" height="14" class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Guardando…
                </span>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_settings_page#dpu-automation')); ?>" class="dpu-btn dpu-btn--ghost">
                Configurar Automatización →
            </a>
            <span id="dpuwoo-save-status" style="font-size:.8rem; color:var(--dpu-text-3);"></span>
        </div>

    </form>

</div><!-- .dpuwoo-admin -->
