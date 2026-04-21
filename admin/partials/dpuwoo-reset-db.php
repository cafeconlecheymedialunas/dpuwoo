<?php
/**
 * Reset Database Tool
 * Access via: /wp-admin/admin.php?page=dpuwoo_reset
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Process reset if requested
$message = '';
if (isset($_POST['reset_dpuwoo']) && wp_verify_nonce($_POST['_wpnonce'], 'dpuwoo_reset')) {
    $options_to_delete = [
        'dpuwoo_settings',
        'dpuwoo_initial_setup_done',
        'dpuwoo_db_version',
        'dpuwoo_activation_redirect',
        'dpuwoo_admin_notice',
        'dpuwoo_cron_last_run',
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Delete transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%dpuwoo_%'");
    
    // Clear cron
    wp_clear_scheduled_hook('dpuwoo_do_update');
    
    $message = '<div class="notice notice-success"><p>✅ Base de datos reseteada correctamente.</p></div>';
}
?>
<div class="wrap">
    <h1>Reset Base de Datos - DPUWoo</h1>
    
    <?php echo $message; ?>
    
    <div style="background: #fff; border: 1px solid #ccc; padding: 20px; max-width: 600px; margin-top: 20px;">
        <h2>¿Qué hace este reset?</h2>
        <ul>
            <li>Elimina todas las opciones del plugin</li>
            <li>Elimina los transients de cache</li>
            <li>Limpia los eventos cron programados</li>
            <li>Las tablas de la base de datos se mantienen</li>
        </ul>
        
        <p style="color: #d63638;"><strong>⚠️ Atención:</strong> Después de resetear, el plugin volverá al estado inicial como si nunca hubiera sido configurado.</p>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('dpuwoo_reset'); ?>
            <button type="submit" name="reset_dpuwoo" class="button button-primary" onclick="return confirm('¿Estás seguro? Esta acción no se puede deshacer.');">
                Resetear Base de Datos
            </button>
        </form>
    </div>
</div>
