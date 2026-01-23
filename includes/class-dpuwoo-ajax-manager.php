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
        $res['reference_used'] = 'baseline'; // Para simulación
        $res['baseline_rate'] = floatval($opts['baseline_dollar_value'] ?? 0);
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
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
        $res['baseline_rate'] = floatval($opts['baseline_dollar_value'] ?? 0);
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
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

        wp_send_json_success(['message' => 'run_reverted', 'run_id' => $run_id]);
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
     * AJAX Handler: Guardar configuración de settings
     */
    public static function ajax_save_settings()
    {
        error_log('DPUWoo AJAX Save: Handler called');
        error_log('DPUWoo AJAX Save: Current user ID: ' . get_current_user_id());
        error_log('DPUWoo AJAX Save: User can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
        error_log('DPUWoo AJAX Save: Nonce received: ' . ($_POST['nonce'] ?? 'NONE'));
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            error_log('DPUWoo AJAX Save: Nonce verification failed');
            wp_send_json_error(['message' => 'Acceso no autorizado - Token inválido']);
        }
        
        if (!current_user_can('manage_options')) {
            error_log('DPUWoo AJAX Save: Insufficient permissions');
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        // Log para debugging - show what we actually received
        error_log('DPUWoo AJAX Save: COMPLETE POST KEYS: ' . print_r(array_keys($_POST), true));
        error_log('DPUWoo AJAX Save: POST action: ' . ($_POST['action'] ?? 'NONE'));
        error_log('DPUWoo AJAX Save: POST nonce: ' . ($_POST['nonce'] ?? 'NONE'));
        
        // Check specifically for dpuwoo_settings keys
        $dpuwoo_keys = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'dpuwoo_settings[') === 0) {
                $dpuwoo_keys[] = $key;
            }
        }
        error_log('DPUWoo AJAX Save: Found dpuwoo_settings keys: ' . print_r($dpuwoo_keys, true));
        
        // Collect settings data - try multiple methods
        $settings_data = [];
        
        // Method 1: Check for nested settings array (new format)
        if (isset($_POST['dpuwoo_settings']) && is_array($_POST['dpuwoo_settings'])) {
            $settings_data = $_POST['dpuwoo_settings'];
            error_log('DPUWoo AJAX Save: Found nested dpuwoo_settings array with ' . count($settings_data) . ' items');
        }
        
        // Method 2: Check for alternative nested format
        if (empty($settings_data) && isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings_data = $_POST['settings'];
            error_log('DPUWoo AJAX Save: Found alternative settings array with ' . count($settings_data) . ' items');
        }
        
        // Method 3: Direct collection from flattened format (old format)
        if (empty($settings_data)) {
            error_log('DPUWoo AJAX Save: Trying direct collection from flattened format');
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'dpuwoo_settings[') === 0) {
                    // Extract field name: dpuwoo_settings[field_name] -> field_name
                    $field_name = preg_replace('/^dpuwoo_settings\[([^\]]+)\]$/', '$1', $key);
                    $settings_data[$field_name] = $value;
                    error_log("DPUWoo AJAX Save: Collected {$field_name} = " . (is_array($value) ? 'ARRAY' : $value));
                }
            }
            error_log('DPUWoo AJAX Save: Direct collection found ' . count($settings_data) . ' fields');
        }
        
        // Method 3: If still empty, log everything we received
        if (empty($settings_data)) {
            error_log('DPUWoo AJAX Save: STILL EMPTY - Complete POST dump: ' . print_r($_POST, true));
        }
        
        if (empty($settings_data) || !is_array($settings_data)) {
            error_log('DPUWoo AJAX Save: Invalid settings data received');
            error_log('DPUWoo AJAX Save: POST keys: ' . print_r(array_keys($_POST), true));
            wp_send_json_error([
                'message' => 'Datos de configuración inválidos - No se recibieron datos del formulario'
            ]);
        }
        
        // Log para debugging
        error_log('DPUWoo AJAX Save: Processing settings for user ' . get_current_user_id());
        
        // Sanitizar y validar los datos
        $sanitized_data = [];
        
        // API Keys
        $sanitized_data['currencyapi_api_key'] = sanitize_text_field($settings_data['currencyapi_api_key'] ?? '');
        $sanitized_data['exchangerate_api_key'] = sanitize_text_field($settings_data['exchangerate_api_key'] ?? '');
        
        // Configuración básica
        $sanitized_data['api_provider'] = sanitize_text_field($settings_data['api_provider'] ?? 'dolarapi');
        $sanitized_data['country'] = sanitize_text_field($settings_data['country'] ?? 'AR');
        $sanitized_data['base_currency'] = sanitize_text_field($settings_data['base_currency'] ?? get_woocommerce_currency());
        $sanitized_data['reference_currency'] = sanitize_text_field($settings_data['reference_currency'] ?? 'USD');
        $sanitized_data['baseline_dollar_value'] = floatval($settings_data['baseline_dollar_value'] ?? 0);
        
        // Valores de referencia
        $sanitized_data['last_rate'] = floatval($settings_data['last_rate'] ?? 0);
        
        // Cálculo y ajuste
        $sanitized_data['margin'] = floatval($settings_data['margin'] ?? 0);
        $sanitized_data['threshold'] = floatval($settings_data['threshold'] ?? 0.5);
        $sanitized_data['update_direction'] = sanitize_text_field($settings_data['update_direction'] ?? 'bidirectional');
        
        // Redondeo
        $sanitized_data['rounding_type'] = sanitize_text_field($settings_data['rounding_type'] ?? 'integer');
        $sanitized_data['nearest_to'] = sanitize_text_field($settings_data['nearest_to'] ?? '1');
        $sanitized_data['psychological_pricing'] = isset($settings_data['psychological_pricing']) ? 1 : 0;
        $sanitized_data['psychological_ending'] = sanitize_text_field($settings_data['psychological_ending'] ?? '99');
        
        // Automatización
        $sanitized_data['interval'] = intval($settings_data['interval'] ?? 3600);
        
        // Exclusiones
        $sanitized_data['exclude_on_sale'] = isset($settings_data['exclude_on_sale']) ? 1 : 0;
        $exclude_categories = $settings_data['exclude_categories'] ?? [];
        $sanitized_data['exclude_categories'] = is_array($exclude_categories) && !empty($exclude_categories)
            ? array_map('intval', $exclude_categories)
            : [];
        
        // Guardar la configuración
        error_log('DPUWoo AJAX Save: Attempting to save settings data');
        error_log('DPUWoo AJAX Save: Data to save: ' . print_r($sanitized_data, true));
        error_log('DPUWoo AJAX Save: Data type: ' . gettype($sanitized_data));
        error_log('DPUWoo AJAX Save: Data count: ' . count($sanitized_data));
        
        // Test direct database write first
        global $wpdb;
        $option_name = 'dpuwoo_settings';
        $option_value = serialize($sanitized_data);
        
        error_log('DPUWoo AJAX Save: Testing direct database write');
        error_log('DPUWoo AJAX Save: Option name: ' . $option_name);
        error_log('DPUWoo AJAX Save: Serialized data length: ' . strlen($option_value));
        
        // Check if option exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $option_name));
        error_log('DPUWoo AJAX Save: Option exists: ' . ($exists ? 'YES (' . $exists . ')' : 'NO'));
        
        // Try direct insert/update
        if ($exists) {
            $result_direct = $wpdb->update(
                $wpdb->options,
                ['option_value' => $option_value],
                ['option_name' => $option_name],
                ['%s'],
                ['%s']
            );
        } else {
            $result_direct = $wpdb->insert(
                $wpdb->options,
                [
                    'option_name' => $option_name,
                    'option_value' => $option_value,
                    'autoload' => 'yes'
                ],
                ['%s', '%s', '%s']
            );
        }
        
        error_log('DPUWoo AJAX Save: Direct DB result: ' . var_export($result_direct, true));
        error_log('DPUWoo AJAX Save: Last query: ' . $wpdb->last_query);
        error_log('DPUWoo AJAX Save: Last error: ' . $wpdb->last_error);
        
        // Also try update_option
        $result = update_option('dpuwoo_settings', $sanitized_data);
        
        error_log('DPUWoo AJAX Save: update_option result: ' . var_export($result, true));
        error_log('DPUWoo AJAX Save: update_option return type: ' . gettype($result));
        
        if ($result !== false) { // update_option returns false on failure
            error_log('DPUWoo AJAX Save: Settings saved successfully');
            
            // Verify the save actually worked
            $verify_settings = get_option('dpuwoo_settings', []);
            error_log('DPUWoo AJAX Save: Verification - Saved settings: ' . print_r($verify_settings, true));
            error_log('DPUWoo AJAX Save: Verification - Match: ' . (($verify_settings == $sanitized_data) ? 'YES' : 'NO'));
            
            wp_send_json_success([
                'message' => 'Configuración guardada correctamente',
                'data' => $sanitized_data,
                'timestamp' => current_time('mysql')
            ]);
        } else {
            error_log('DPUWoo AJAX Save: Failed to save settings');
            error_log('DPUWoo AJAX Save: Current user ID: ' . get_current_user_id());
            error_log('DPUWoo AJAX Save: Current user can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
            wp_send_json_error([
                'message' => 'Error al guardar la configuración - Verifique permisos y datos'
            ]);
        }
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
}