<?php
if (!defined('ABSPATH')) exit;

class Cron
{
    const HOOK = 'dpuwoo_do_update';

    public static function schedule()
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'hourly', self::HOOK);
        }
    }

    public static function unschedule()
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) wp_unschedule_event($timestamp, self::HOOK);
    }

    public static function run_cron()
    {
        // Use the new baseline manager for reliable baseline retrieval
        $baseline_manager = DPUWOO_Baseline_Manager::get_instance();
        $baseline = $baseline_manager->get_current_baseline('dollar');
        
        // If no baseline, try to initialize
        if ($baseline === null || $baseline <= 0) {
            $baseline_manager->force_initialize();
            $baseline = $baseline_manager->get_current_baseline('dollar');
        }
        
        if ($baseline === null || $baseline <= 0) {
            error_log('DPUWoo Cron: Cannot run - missing baseline configuration');
            return;
        }

        $type = get_option('dpuwoo_settings', [])['dollar_type'] ?? 'oficial';
        $api = API_Client::get_instance();
        $rate = $api->get_rate($type);
        
        if ($rate === false) {
            $rate = Fallback::get_instance()->get_fallback_rate();
        }
        
        if ($rate === false) {
            return;
        }

        $current_rate = floatval($rate['value']);
        $last_rate = floatval(get_option('dpuwoo_settings', [])['last_rate'] ?? 0);
        $threshold = floatval(get_option('dpuwoo_settings', [])['threshold'] ?? 0);

        // CORREGIDO: En la primera ejecución (last_rate = 0), comparar con baseline
        $reference_rate = ($last_rate > 0) ? $last_rate : $baseline;
        
        // Calcular variación respecto a la referencia
        $changed = ($reference_rate > 0) ? abs(($current_rate - $reference_rate) / $reference_rate) * 100 : 100;

        if ($threshold > 0 && $changed < $threshold) {
            return;
        }

        // Actualizar precios
        $updater = Price_Updater::get_instance();
        $result = $updater->update_all_batch(false);
        
        if (isset($result['error'])) {
            return;
        }

        // Guardar el rate actual para la próxima comparación
        $opts = get_option('dpuwoo_settings', []);
        $opts['last_rate'] = $current_rate;
        update_option('dpuwoo_settings', $opts);
    }
}