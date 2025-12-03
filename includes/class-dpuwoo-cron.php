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
        $opts = get_option('dpuwoo_settings', []);
        
        // Verificar que tenemos baseline configurado
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
        if ($baseline <= 0) {
            error_log('DPU WooCommerce: Dólar base no configurado');
            return;
        }

        $type = $opts['dollar_type'] ?? 'oficial';
        $api = API_Client::get_instance();
        $rate = $api->get_rate($type);
        
        if ($rate === false) {
            $rate = Fallback::get_instance()->get_fallback_rate();
        }
        
        if ($rate === false) {
            error_log('DPU WooCommerce: No se pudo obtener tasa de dólar');
            return;
        }

        $current_rate = floatval($rate['value']);
        $last_rate = floatval($opts['last_rate'] ?? 0);
        $threshold = floatval($opts['threshold'] ?? 0);

        // CORREGIDO: En la primera ejecución (last_rate = 0), comparar con baseline
        $reference_rate = ($last_rate > 0) ? $last_rate : $baseline;
        
        // Calcular variación respecto a la referencia
        $changed = ($reference_rate > 0) ? abs(($current_rate - $reference_rate) / $reference_rate) * 100 : 100;
        
        // Log para debugging
        error_log("DPU WooCommerce: Reference: $reference_rate, Current: $current_rate, Change: " . round($changed, 2) . "%, Threshold: $threshold%");

        if ($threshold > 0 && $changed < $threshold) {
            error_log("DPU WooCommerce: Umbral no alcanzado (" . round($changed, 2) . "% < $threshold%)");
            return;
        }

        // Actualizar precios
        $updater = Price_Updater::get_instance();
        $result = $updater->update_all_batch(false);
        
        if (isset($result['error'])) {
            error_log('DPU WooCommerce Error: ' . $result['message']);
            return;
        }

        // Guardar el rate actual para la próxima comparación
        $opts['last_rate'] = $current_rate;
        update_option('dpuwoo_settings', $opts);

        error_log("DPU WooCommerce: Actualización completada. Rate: $current_rate, Productos actualizados: " . $result['summary']['updated']);
    }
}