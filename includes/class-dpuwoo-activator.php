<?php
if (!defined('ABSPATH')) exit;

class Activator
{
    public static function activate()
    {
        // Evitar que se ejecute múltiples veces
        if (get_option('dpuwoo_initial_setup_done')) {
            return;
        }
        self::create_tables();

        $settings = get_option('dpuwoo_settings', []);

        // 1. OBTENER DÓLAR ACTUAL PARA USAR COMO BASELINE
        $dollar_value = self::fetch_initial_dollar_value();
        
        if ($dollar_value && $dollar_value > 0) {
            $settings['baseline_dollar_value'] = $dollar_value;
            $settings['auto_detection_date'] = current_time('mysql');
        } else {
            // Valor por defecto si no se puede obtener
            $settings['baseline_dollar_value'] = 1;
        }

        // 2. CONFIGURACIÓN MÍNIMA ESENCIAL
        $settings['interval'] = $settings['interval'] ?? 3600;
        $settings['threshold'] = $settings['threshold'] ?? 1.0;
        $settings['dollar_type'] = $settings['dollar_type'] ?? 'oficial';

        update_option('dpuwoo_settings', $settings);

        // 3. GUARDAR ESTE DÓLAR COMO "ÚLTIMO DÓLAR" PARA PRÓXIMOS CÁLCULOS
        update_option('dpuwoo_last_dollar_value', $settings['baseline_dollar_value']);

        // 4. PROGRAMAR ACTUALIZACIONES AUTOMÁTICAS
        if (!wp_next_scheduled('dpuwoo_do_update')) {
            wp_schedule_event(time() + 300, 'hourly', 'dpuwoo_do_update');
        }

        // 5. MARCAR COMO ACTIVADO
        update_option('dpuwoo_initial_setup_done', true);
        
        // 6. NOTIFICACIÓN AL ADMIN
        self::add_activation_notice($dollar_value);
    }

    private static function fetch_initial_dollar_value()
    {
        $url = "https://dolarapi.com/v1/dolares/oficial";
        $args = ['timeout' => 15, 'sslverify' => false];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            error_log('DPU WooCommerce - Error fetching dollar: ' . $res->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);
        
        if (!isset($body['venta'])) {
            error_log('DPU WooCommerce - Invalid API response');
            return false;
        }

        $rate = floatval($body['venta']);
        return ($rate > 0) ? $rate : false;
    }

    private static function add_activation_notice($detected_rate)
    {
        $message = $detected_rate ? 
            sprintf('DPU WooCommerce activado. Dólar base detectado: <strong>$%s</strong>', esc_html($detected_rate)) :
            'DPU WooCommerce activado. Configura el dólar base en los ajustes.';

        update_option('dpuwoo_admin_notice', [
            'message' => $message,
            'type' => 'info',
            'dismissible' => true
        ]);
    }

    private static function create_tables()
    {
        global $wpdb;

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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_runs);
        dbDelta($sql_items);
    }
}