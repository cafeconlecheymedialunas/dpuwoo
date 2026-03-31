<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap dpuwoo-admin">

    <?php settings_errors(); ?>

    <!-- Header -->
    <div class="dpu-header">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.25rem;">
            <div class="dpu-page-badge dpu-page-badge--logs">Log</div>
            <h1 class="dpu-title" style="margin:0;">Historial de ejecuciones</h1>
        </div>
        <p class="dpu-subtitle">Registro completo de simulaciones y actualizaciones de precios.</p>
    </div>

    <!-- Breadcrumb nav -->
    <div class="dpu-settings-nav">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dpuwoo_dashboard')); ?>" class="dpu-settings-nav__link">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
            Dashboard
        </a>
        <span class="dpu-settings-nav__sep">›</span>
        <span class="dpu-settings-nav__current">Historial</span>
    </div>

    <!-- Logs Table -->
    <div class="dpu-card" style="padding:0;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--dpu-border);">
            <p class="dpu-section-title" style="margin:0 0 0.125rem;">Ejecuciones registradas</p>
            <p class="dpu-section-subtitle" style="margin:0;">Historial de simulaciones y actualizaciones reales.</p>
        </div>
        <div class="dpu-logs-table-wrap" id="dpuwoo-log-table" style="padding:1.25rem 1.5rem;"></div>
    </div>

</div><!-- .dpuwoo-admin -->

<!-- Modal: Detalles de ejecución (reutilizado desde dashboard) -->
<div id="dpuwoo-run-details-modal" class="hidden dpu-modal-overlay">
    <div class="dpu-modal dpu-modal--lg">
        <div class="dpu-modal__header">
            <h3 class="dpu-modal__title">Detalles de la ejecución</h3>
            <button id="dpuwoo-close-details-modal" class="dpu-modal__close">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="dpu-modal__body">
            <div id="dpuwoo-run-details-content"></div>
        </div>
        <div class="dpu-modal__footer">
            <button id="dpuwoo-close-details-modal-2" class="dpu-btn dpu-btn--ghost">Cerrar</button>
        </div>
    </div>
</div>
