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

    global $wpdb;
    $table = $wpdb->prefix . 'dpuwoo_runs';

    $rows = $wpdb->get_results("
        SELECT id, date, dollar_value, dollar_type, total_products, user_id, note
        FROM $table
        ORDER BY id DESC
        LIMIT 200
    ");

    wp_send_json_success($rows);
}

public static function ajax_get_run_items()
{
    if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
    check_ajax_referer('dpuwoo_nonce', 'nonce');

    $run_id = intval($_POST['run_id'] ?? 0);
    if (!$run_id) wp_send_json_error('Invalid run');

    global $wpdb;
    $table = $wpdb->prefix . 'dpuwoo_run_items';

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT 
            id,
            run_id,
            product_id,
            product_sku,
            old_regular_price,
            new_regular_price,
            old_sale_price,
            new_sale_price,
            percentage_change,
            category_name,
            status,
            reason,
            extra
        FROM $table
        WHERE run_id = %d
        ORDER BY id ASC
    ", $run_id));

    // Obtener nombres de productos desde WooCommerce
    $items_with_names = [];
    foreach ($rows as $item) {
        $product_name = '';
        $product = wc_get_product($item->product_id);
        if ($product) {
            $product_name = $product->get_name();
        } else {
            $product_name = 'Producto no encontrado (ID: ' . $item->product_id . ')';
        }
        
        $items_with_names[] = (object) array_merge((array) $item, [
            'product_name' => $product_name
        ]);
    }

    wp_send_json_success($items_with_names);
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