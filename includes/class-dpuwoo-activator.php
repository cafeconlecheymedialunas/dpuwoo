<?php

if (!defined('ABSPATH')) exit;

class DPUWoo_Activator
{
    public static function activate()
    {
        // Evitar que se ejecute múltiples veces si WP llama al hook + network activation
        if (get_option('dpuwoo_initial_setup_done')) {
            return;
        }

        $settings = get_option('dpuwoo_settings', []);
        $api_key = $settings['api_key'] ?? '';
        $dollar_type = $settings['dollar_type'] ?? 'oficial';

        // 1. Obtener dólar inicial desde la API
        $initial_dollar = self::fetch_initial_dollar_value($api_key, $dollar_type);
        if ($initial_dollar) {
            update_option('dpuwoo_initial_dollar_value', $initial_dollar);
        }

        // 2. Guardar los precios base de los productos
        self::store_base_prices();

        // Marcamos setup
        update_option('dpuwoo_initial_setup_done', true);
    }

    /**
     * Llama a la API y devuelve el valor del dólar en float.
     */
    private static function fetch_initial_dollar_value($api_key, $type)
    {
        $url = "https://dolarapi.com/v1/dolares/{$type}";
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ]
        ];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) return false;

        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!isset($body['venta'])) return false;

        return floatval($body['venta']);
    }

    /**
     * Guarda base_price en post meta SOLO si no existe.
     */
    private static function store_base_prices()
    {
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        $products = get_posts($args);

        foreach ($products as $product_id) {
            $stored = get_post_meta($product_id, '_dpuwoo_base_price', true);

            // Si ya existe, no lo pisa
            if ($stored !== '') {
                continue;
            }

            $product = wc_get_product($product_id);
            if (!$product) continue;

            $regular_price = $product->get_regular_price();
            if ($regular_price === '' || !is_numeric($regular_price)) {
                continue;
            }

            update_post_meta($product_id, '_dpuwoo_base_price', floatval($regular_price));
        }
    }
}
