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

        $settings = get_option('dpuwoo_settings', []);
        $api_key = $settings['api_key'] ?? '';
        $dollar_type = $settings['dollar_type'] ?? 'oficial';

        // 1. Intentar obtener dólar inicial desde la API si hay API key
        $auto_detected_rate = false;
        if (!empty($api_key)) {
            $auto_detected_rate = self::fetch_initial_dollar_value($api_key, $dollar_type);
            
            if ($auto_detected_rate) {
                $settings['auto_detected_dollar_value'] = $auto_detected_rate;
                $settings['auto_detection_date'] = current_time('mysql');
                $settings['auto_detection_status'] = 'success';
            } else {
                $settings['auto_detection_status'] = 'failed';
            }
        } else {
            $settings['auto_detection_status'] = 'no_api_key';
        }

        // 2. Configuración por defecto
        $settings['interval'] = $settings['interval'] ?? 3600;
        $settings['threshold'] = $settings['threshold'] ?? 1.0;
        $settings['rounding'] = $settings['rounding'] ?? 'multiple';
        $settings['round_multiple'] = $settings['round_multiple'] ?? 10;

        update_option('dpuwoo_settings', $settings);

        // 3. Crear tablas de logs
        self::create_tables();

        // 4. Guardar precios base (INCLUYENDO VARIACIONES)
        self::store_base_prices();

        // 5. Programar cron
        wp_schedule_event(time(), 'hourly', 'dpuwoo_do_update');

        // 6. Marcar setup inicial
        update_option('dpuwoo_initial_setup_done', true);
        
        // 7. Crear admin notice
        self::add_activation_notice($auto_detected_rate);
    }

    private static function fetch_initial_dollar_value($api_key, $type)
    {
        $url = "https://dolarapi.com/v1/dolares/{$type}";
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 15
        ];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) return false;

        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!isset($body['venta'])) return false;

        return floatval($body['venta']);
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
            product_sku varchar(100),
            product_name text NOT NULL,
            old_regular_price decimal(10,2),
            new_regular_price decimal(10,2),
            old_sale_price decimal(10,2),
            new_sale_price decimal(10,2),
            percentage_change decimal(5,2),
            category_name varchar(255),
            status varchar(50) NOT NULL,
            reason text,
            extra text,
            PRIMARY KEY (id),
            KEY run_id (run_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_runs);
        dbDelta($sql_items);
    }

    /**
     * Guardar base_price en post meta SOLO si no existe.
     * AHORA INCLUYE VARIACIONES
     */
    private static function store_base_prices()
    {
        // Productos simples y variables
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => ['simple', 'variable'],
                ]
            ]
        ];

        $products = get_posts($args);

        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            // Si es producto variable, procesar sus variaciones
            if ($product->is_type('variable')) {
                $variation_ids = $product->get_children();
                foreach ($variation_ids as $variation_id) {
                    self::store_single_product_base_price($variation_id);
                }
            } else {
                // Producto simple
                self::store_single_product_base_price($product_id);
            }
        }
    }

    /**
     * Guardar precio base para un producto individual (simple o variación)
     */
    private static function store_single_product_base_price($product_id)
    {
        $stored = get_post_meta($product_id, '_dpuwoo_base_price', true);

        // Si ya existe, no lo pisa
        if ($stored !== '') {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) return;

        $regular_price = $product->get_regular_price();
        if ($regular_price === '' || !is_numeric($regular_price) || floatval($regular_price) <= 0) {
            return;
        }

        update_post_meta($product_id, '_dpuwoo_base_price', floatval($regular_price));
    }

    private static function add_activation_notice($auto_detected_rate)
    {
        $notice_message = '';

        if ($auto_detected_rate) {
            $notice_message = sprintf(
                'DPU WooCommerce detectó automáticamente el dólar en <strong>$%s</strong>. Por favor, revisa y confirma este valor en la configuración del plugin.',
                esc_html($auto_detected_rate)
            );
        } else {
            $notice_message = 'DPU WooCommerce ha sido activado. Por favor, configura el valor del dólar base histórico en la configuración del plugin para comenzar a usar las actualizaciones automáticas de precios.';
        }

        update_option('dpuwoo_admin_notice', [
            'id' => 'dpuwoo_initial_setup_required',
            'message' => $notice_message,
            'type' => 'info',
            'dismissible' => true
        ]);
    }
}