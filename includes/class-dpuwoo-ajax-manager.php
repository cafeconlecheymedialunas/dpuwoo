<?php
if (!defined('ABSPATH')) exit;

class Ajax_Manager
{
    /**
     * Nueva simulación por lotes
     */
    public function ajax_simulate_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');
        
        $batch = intval($_POST['batch'] ?? 0);
        $updater = Price_Updater::get_instance();
        
        // SIMULACIÓN: forzar a que use baseline como previous_dollar_value
        $res = $updater->update_all_batch(true, $batch);
        
        if (isset($res['error'])) {
            wp_send_json_error($res);
        }
        
        // Añadir información detallada de configuración
        $opts = get_option('dpuwoo_settings', []);
        $res['reference_used'] = 'usd_price'; // Para simulación
        
        // Usar promedio de baselines USD de productos
        global $wpdb;
        $avg_baseline = $wpdb->get_var("SELECT AVG(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = '_dpuwoo_original_price_usd' AND meta_value > 0");
        $res['baseline_rate'] = $avg_baseline ? floatval($avg_baseline) : 0;
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
            'margin' => floatval($opts['margin'] ?? 0),
            'threshold' => floatval($opts['threshold'] ?? 0.5),
            'update_direction' => $opts['update_direction'] ?? 'bidirectional',
            'rounding_type' => $opts['rounding_type'] ?? 'integer',
            'last_rate' => floatval($opts['last_rate'] ?? 0)
        ];
        
        wp_send_json_success($res);
    }

    public function ajax_get_runs()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');
        $logger = Logger::get_instance();
        $rows = $logger->get_runs();

        wp_send_json_success($rows);
    }

    public function ajax_get_run_items()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');

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
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');
        
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
        
        // Usar promedio de baselines USD de productos
        global $wpdb;
        $avg_baseline = $wpdb->get_var("SELECT AVG(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = '_dpuwoo_original_price_usd' AND meta_value > 0");
        $res['baseline_rate'] = $avg_baseline ? floatval($avg_baseline) : 0;
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
            'margin' => floatval($opts['margin'] ?? 0),
            'threshold' => floatval($opts['threshold'] ?? 0.5),
            'update_direction' => $opts['update_direction'] ?? 'bidirectional',
            'rounding_type' => $opts['rounding_type'] ?? 'integer',
            'last_rate' => floatval($opts['last_rate'] ?? 0)
        ];
        
        wp_send_json_success($res);
    }

    public function ajax_revert_item()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');

        $log_id = intval($_POST['log_id'] ?? 0);
        if (!$log_id) wp_send_json_error('invalid_log');
        $ok = Logger::get_instance()->rollback_item($log_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'reverted', 'log_id' => $log_id]);
    }

    public function ajax_revert_run()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) wp_send_json_error('invalid_run');

        $ok = Logger::get_instance()->rollback_run($run_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'run_everted', 'run_id' => $run_id]);
    }

    /**
     * AJAX Handler: Obtener monedas según proveedor
     */
    public static function ajax_get_currencies()
    {
        // Verificar nonce para seguridad
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $country = sanitize_text_field($_POST['country'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        
        // Configurar proveedor específico si se solicita
      
        
        // Si se especifica país, actualizar configuración temporalmente
        if (!empty($country)) {
            update_option('woocommerce_default_country', $country);
        }
        
        // Obtener cotizaciones usando el proveedor especificado
        $currencies_data = $api_client->get_currencies($provider);
        
        if (!$currencies_data) {
            wp_send_json_error([
                'message' => 'No se pudieron obtener datos del proveedor: ' . $provider,
                'provider' => $provider,
                'country' => $country
            ]);
        }
        
        // Formatear respuesta según el tipo de proveedor
        $formatted_currencies = [];
        $provider_info = $api_client->get_provider($provider);
    
        
        wp_send_json_success([
            'currencies' => $currencies_data,
            'provider' => $provider,
            'count' => count($currencies_data)
        ]);
    }

    /**
     * AJAX Handler: Obtener tasa de cambio actual
     */
    public static function ajax_get_current_rate()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'oficial');
        $currency_pair = sanitize_text_field($_POST['currency_pair'] ?? '');
        
        // Configurar proveedor si se especifica
        if (!empty($provider)) {
            $api_client->set_provider($provider);
        }
        
        // Determinar qué tipo de rate obtener
        $rate_type = !empty($currency_pair) ? $currency_pair : $type;
        
        // Obtener la tasa
        $rate_data = $api_client->get_rate($rate_type);
        
        if ($rate_data) {
            $response = [
                'success' => true,
                'rate' => $rate_data['value'] ?? 0,
                'buy' => $rate_data['buy'] ?? $rate_data['value'] ?? 0,
                'sell' => $rate_data['sell'] ?? $rate_data['value'] ?? 0,
                'updated' => $rate_data['updated'] ?? current_time('mysql'),
                'provider' => $rate_data['provider'] ?? $provider,
                'raw' => $rate_data['raw'] ?? []
            ];
            
            // Actualizar última tasa en opciones si es exitosa
            if (isset($rate_data['value']) && $rate_data['value'] > 0) {
                $opts = get_option('dpuwoo_settings', []);
                $opts['last_rate'] = floatval($rate_data['value']);
                update_option('dpuwoo_settings', $opts);
            }
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error([
                'message' => 'No se pudo obtener la tasa de cambio',
                'provider' => $provider,
                'type' => $rate_type
            ]);
        }
    }

    /**
     * AJAX Handler: Obtener todas las cotizaciones disponibles
     */
    public static function ajax_get_all_rates()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        
        $rates = $api_client->get_currencies();
        
        if ($rates) {
            wp_send_json_success([
                'success' => true,
                'rates' => $rates,
                'count' => count($rates),
                'provider' => $provider,
                'timestamp' => current_time('mysql')
            ]);
        } else {
            wp_send_json_error([
                'message' => 'No se pudieron obtener las cotizaciones',
                'provider' => $provider
            ]);
        }
    }

    /**
     * AJAX Handler: Probar conexión con API
     */
    public static function ajax_test_api_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        // Guardar API key temporalmente para la prueba
        if (!empty($api_key)) {
            $opts = get_option('dpuwoo_settings', []);
            $opts['api_key'] = $api_key;
            $opts['api_provider'] = $provider;
            update_option('dpuwoo_settings', $opts);
        }
        
        // Probar conexión usando el método del proveedor
        $test_result = $api_client->test_connection($provider);
        
        if ($test_result['success']) {
            $result = [
                'provider' => $provider,
                'status' => 'connected',
                'timestamp' => current_time('mysql'),
                'message' => $test_result['message'] ?? 'Conexión exitosa',
                'http_code' => $test_result['http_code'] ?? 200,
                'url' => $test_result['url'] ?? ''
            ];
            
            // Si la prueba fue exitosa, limpiar cache
            $api_client->clear_cache();
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error([
                'provider' => $provider,
                'status' => 'failed',
                'timestamp' => current_time('mysql'),
                'message' => $test_result['error'] ?? 'Error de conexión',
                'http_code' => $test_result['http_code'] ?? 0,
                'url' => $test_result['url'] ?? ''
            ]);
        }
    }

    /**
     * AJAX Handler: Obtener información de proveedores disponibles
     */
    public static function ajax_get_providers_info()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $providers = API_Client::get_available_providers();
        $current_provider = get_option('dpuwoo_settings', [])['api_provider'] ?? 'dolarapi';
        
        wp_send_json_success([
            'providers' => $providers,
            'current_provider' => $current_provider,
            'woocommerce_currency' => get_woocommerce_currency(),
            'woocommerce_country' => get_option('woocommerce_default_country', 'AR')
        ]);
    }

    /**
     * AJAX Handler: Actualizar configuración del proveedor
     */
    public static function ajax_update_provider_config()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($provider)) {
            wp_send_json_error(['message' => 'Proveedor no especificado']);
        }
        
        $opts = get_option('dpuwoo_settings', []);
        $opts['api_provider'] = $provider;
        
        if (!empty($api_key)) {
            $opts['api_key'] = $api_key;
        }
        
        update_option('dpuwoo_settings', $opts);
        
        wp_send_json_success([
            'message' => 'Configuración actualizada',
            'provider' => $provider,
        ]);
    }

    /**
     * AJAX Handler: Limpiar cache de API
     */
    public static function ajax_clear_api_cache()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        // Como no hay implementación de cache, devolvemos éxito
        wp_send_json_success([
            'message' => 'Función de cache no implementada',
            'success' => true
        ]);
    }
    
    /**
     * AJAX Handler: Get current product price
     */
    public function ajax_get_product_current_price() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permissions']);
        }
        
        if (!check_ajax_referer('dpuwoo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID']);
        }
        
        if (!function_exists('wc_get_product')) {
            wp_send_json_error(['message' => 'WooCommerce not available']);
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product not found']);
        }
        
        $current_price = floatval($product->get_regular_price());
        
        wp_send_json_success([
            'price' => $current_price,
            'formatted_price' => wc_price($current_price),
            'product_id' => $product_id,
            'product_title' => $product->get_title()
        ]);
    }
    
    /**
     * AJAX Handler: Get current exchange rate
     */
    public function ajax_get_current_exchange_rate() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permissions']);
        }
        
        if (!check_ajax_referer('dpuwoo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        try {
            if (class_exists('API_Client')) {
                $api_client = API_Client::get_instance();
                $settings = get_option('dpuwoo_settings', []);
                $dollar_type = $settings['dollar_type'] ?? 'oficial';
                
                $rate_data = $api_client->get_rate($dollar_type);
                
                if ($rate_data && isset($rate_data['value'])) {
                    wp_send_json_success([
                        'rate' => floatval($rate_data['value']),
                        'formatted_rate' => '$' . number_format(floatval($rate_data['value']), 2),
                        'type' => $dollar_type,
                        'provider' => $rate_data['provider'] ?? 'unknown'
                    ]);
                } else {
                    wp_send_json_error(['message' => 'Could not get exchange rate from API']);
                }
            } else {
                wp_send_json_error(['message' => 'API Client not available']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error getting exchange rate: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX Handler: Initialize baseline using current API exchange rate
     */
    public function ajax_initialize_baseline_api() {
        // Esta funcionalidad ha sido simplificada
        // Los baselines se establecen automáticamente durante la activación
        wp_send_json_error([
            'message' => 'Esta funcionalidad ha sido simplificada. Los baselines se establecen automáticamente durante la activación del plugin.'
        ]);
    }
    
}
