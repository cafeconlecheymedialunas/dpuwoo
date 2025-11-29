<?php
if (!defined('ABSPATH')) exit;

class Ajax_Manager
{
    public static function init()
    {
        add_action('wp_ajax_dpuwoo_update_now', [__CLASS__, 'ajax_update_now']);
        add_action('wp_ajax_dpuwoo_simulate', [__CLASS__, 'ajax_simulate']);
        add_action('wp_ajax_dpuwoo_simulate_batch', [__CLASS__, 'ajax_simulate_batch']);
        add_action('wp_ajax_dpuwoo_update_batch', [__CLASS__, 'ajax_update_batch']);
        add_action('wp_ajax_dpuwoo_revert_item', [__CLASS__, 'ajax_revert_item']);
        add_action('wp_ajax_dpuwoo_revert_run', [__CLASS__, 'ajax_revert_run']);
    }

    /**
     * Nueva simulación por lotes
     */
    public static function ajax_simulate_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');
        
        $batch = intval($_POST['batch'] ?? 0);
        $updater = Price_Updater::get_instance();
        $res = $updater->update_all_batch(true, $batch);
        
        if (isset($res['error'])) {
            wp_send_json_error($res);
        }
        wp_send_json_success($res);
    }

    public static function ajax_get_runs()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');
        $logger = Logger::get_instance();
        $rows = $logger->get_runs();

        wp_send_json_success($rows);
    }

    public static function ajax_get_run_items()
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
    public static function ajax_update_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');
        
        $batch = intval($_POST['batch'] ?? 0);
        $updater = Price_Updater::get_instance();
        $res = $updater->update_all_batch(false, $batch);
        
        if (isset($res['error'])) {
            wp_send_json_error($res);
        }
        wp_send_json_success($res);
    }

    /**
     * Métodos legacy para compatibilidad
     */
    public static function ajax_update_now()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');

        $updater = Price_Updater::get_instance();
        $res = $updater->update_all(false); // live

        if (isset($res['error'])) {
            wp_send_json_error($res);
        }

        wp_send_json_success($res);
    }

    public static function ajax_simulate()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');

        $updater = Price_Updater::get_instance();
        $res = $updater->update_all(true); // simulate

        if (isset($res['error'])) {
            wp_send_json_error($res);
        }

        wp_send_json_success($res);
    }

    public static function ajax_revert_item()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');

        $log_id = intval($_POST['log_id'] ?? 0);
        if (!$log_id) wp_send_json_error('invalid_log');

        $ok = Price_Updater::get_instance()->rollback_item($log_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'reverted', 'log_id' => $log_id]);
    }

    public static function ajax_revert_run()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_nonce', 'nonce');

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) wp_send_json_error('invalid_run');

        $ok = Price_Updater::get_instance()->rollback_run($run_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'run_reverted', 'run_id' => $run_id]);
    }
}