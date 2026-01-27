<?php
if (!defined('ABSPATH')) exit;

class Activator
{
    public static function activate()
    {
        if (get_option('dpuwoo_initial_setup_done')) {
            return;
        }
        
        self::create_tables();
        
        self::auto_configure_dollar_reference();
        
        $settings = get_option('dpuwoo_settings', []);
        $settings['interval'] = $settings['interval'] ?? 3600;
        $settings['threshold'] = $settings['threshold'] ?? 1.0;
        $settings['reference_currency'] = $settings['reference_currency'] ?? 'USD';
        $settings['last_rate'] = $settings['last_rate'] ?? 0;
        
        update_option('dpuwoo_settings', $settings);
        
        self::create_usd_price_fields_for_products();
        
        if (!wp_next_scheduled('dpuwoo_do_update')) {
            wp_schedule_event(time() + 300, 'hourly', 'dpuwoo_do_update');
        }
        
        update_option('dpuwoo_initial_setup_done', true);
        
        self::add_activation_notice();
    }

    private static function create_usd_price_fields_for_products()
    {
        if (!function_exists('get_posts') || !function_exists('update_post_meta')) {
            return;
        }
        
        $products = get_posts([
            'post_type' => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ]);
        
        $fields_created = 0;
        
        foreach ($products as $product_id) {
            update_post_meta($product_id, '_dpuwoo_regular_price_usd', '');
            update_post_meta($product_id, '_dpuwoo_sale_price_usd', '');
            $fields_created++;
        }
    }
    
    private static function auto_configure_dollar_reference()
    {
        // No es necesario configurar aquí ya que se maneja en admin-settings
        // La configuración se inicializa correctamente en activate()
        return;
    }
    
    private static function fetch_initial_dollar_value()
    {
        if (!function_exists('wp_remote_get')) {
            return 1;
        }

        $url = "https://dolarapi.com/v1/dolares/oficial";
        $args = ['timeout' => 15, 'sslverify' => false];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return 1;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);
        
        if (!isset($body['venta'])) {
            return 1;
        }

        $rate = floatval($body['venta']);
        return ($rate > 0) ? $rate : 1;
    }

    private static function add_activation_notice()
    {
        $message = 'DPU WooCommerce activado. Configura la tasa de cambio en los ajustes.';

        update_option('dpuwoo_admin_notice', [
            'message' => $message,
            'type' => 'info',
            'dismissible' => true
        ]);
    }

    private static function create_tables()
    {
        global $wpdb;

        if (!isset($wpdb) || !$wpdb) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $runs_table = $wpdb->prefix . 'dpuwoo_runs';
        $items_table = $wpdb->prefix . 'dpuwoo_run_items';

        $sql_runs = "CREATE TABLE $runs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date datetime NOT NULL,
            dollar_type varchar(50) NOT NULL,
            dollar_value decimal(10,4) NOT NULL,
            rules text,
            total_products int(11) NOT NULL,
            user_id bigint(20) NOT NULL,
            note text,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_items = "CREATE TABLE $items_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            run_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            old_regular_price decimal(10,2),
            new_regular_price decimal(10,2),
            old_sale_price decimal(10,2),
            new_sale_price decimal(10,2),
            percentage_change decimal(5,2),
            status varchar(50) NOT NULL,
            reason text,
            PRIMARY KEY (id),
            KEY run_id (run_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        dbDelta($sql_runs);
        dbDelta($sql_items);
    }
}