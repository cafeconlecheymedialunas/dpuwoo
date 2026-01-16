<?php
if (!defined('ABSPATH')) exit;

class Ajax_Manager
{
    /**
     * Nueva simulación por lotes
     */
    public function ajax_simulate_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');
        
        $batch = intval($_POST['batch'] ?? 0);
        $updater = Price_Updater::get_instance();
        
        // SIMULACIÓN: forzar a que use baseline como previous_dollar_value
        $res = $updater->update_all_batch(true, $batch);
        
        if (isset($res['error'])) {
            wp_send_json_error($res);
        }
        
        // Añadir información detallada de configuración
        $opts = get_option('dpuwoo_settings', []);
        $res['reference_used'] = 'baseline'; // Para simulación
        $res['baseline_rate'] = floatval($opts['baseline_dollar_value'] ?? 0);
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
            'dollar_type' => $opts['dollar_type'] ?? 'oficial',
            'margin' => floatval($opts['margin'] ?? 0),
            'threshold' => floatval($opts['threshold'] ?? 0.5),
            'update_direction' => $opts['update_direction'] ?? 'bidirectional',
            'rounding_type' => $opts['rounding_type'] ?? 'integer',
            'psychological_pricing' => !empty($opts['psychological_pricing']),
            'exclude_on_sale' => !empty($opts['exclude_on_sale']),
            'baseline_dollar_value' => floatval($opts['baseline_dollar_value'] ?? 0),
            'last_rate' => floatval($opts['last_rate'] ?? 0)
        ];
        
        wp_send_json_success($res);
    }

    public function ajax_get_runs()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');
        $logger = Logger::get_instance();
        $rows = $logger->get_runs();

        wp_send_json_success($rows);
    }

    public function ajax_get_run_items()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) wp_send_json_error('Invalid run');

        // Usar el Logger que ya tiene la lógica de enriquecimiento
        $logger = Logger::get_instance();
        $items = $logger->get_run_items($run_id, 500);

        wp_send_json_success($items);
    }

    /**
     * Actualización real por lotes (después de simulación)
     */
    public function ajax_update_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');
        
        $batch = intval($_POST['batch'] ?? 0);
        $updater = Price_Updater::get_instance();
        $res = $updater->update_all_batch(false, $batch);
        
        if (isset($res['error'])) {
            wp_send_json_error($res);
        }
        
        // Añadir información sobre qué referencia se usó
        $opts = get_option('dpuwoo_settings', []);
        $res['reference_used'] = 'last_rate'; // Para actualización real
        $res['last_rate'] = floatval($opts['last_rate'] ?? 0);
        $res['baseline_rate'] = floatval($opts['baseline_dollar_value'] ?? 0);
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
            'dollar_type' => $opts['dollar_type'] ?? 'oficial',
            'margin' => floatval($opts['margin'] ?? 0),
            'threshold' => floatval($opts['threshold'] ?? 0.5),
            'update_direction' => $opts['update_direction'] ?? 'bidirectional',
            'rounding_type' => $opts['rounding_type'] ?? 'integer',
            'psychological_pricing' => !empty($opts['psychological_pricing']),
            'exclude_on_sale' => !empty($opts['exclude_on_sale']),
            'baseline_dollar_value' => floatval($opts['baseline_dollar_value'] ?? 0),
            'last_rate' => floatval($opts['last_rate'] ?? 0)
        ];
        
        wp_send_json_success($res);
    }

    public function ajax_revert_item()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');

        $log_id = intval($_POST['log_id'] ?? 0);
        if (!$log_id) wp_send_json_error('invalid_log');
        $ok = Logger::get_instance()->rollback_item($log_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'reverted', 'log_id' => $log_id]);
    }

    public function ajax_revert_run()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) wp_send_json_error('invalid_run');

        $ok = Logger::get_instance()->rollback_run($run_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'run_reverted', 'run_id' => $run_id]);
    }
}