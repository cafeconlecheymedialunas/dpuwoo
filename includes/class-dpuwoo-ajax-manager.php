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

    /**
     * AJAX Handler: Obtener monedas según proveedor y país
     */
    public static function ajax_get_currencies()
    {
        // Verificar nonce para seguridad
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_die('Acceso no autorizado');
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $country = sanitize_text_field($_POST['country'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? ''); // Para tipo específico de dólar
        
        // Si no se especifica país, usar el de WooCommerce
        if (empty($country)) {
            $country = get_option('woocommerce_default_country', 'AR');
        }
        
        $currencies = [];

        // Lógica para obtener monedas según el proveedor
        switch ($provider) {
            case 'currencyapi':
                // CurrencyAPI soporta ~170 monedas globales
                $currencies_data = $api_client->get_currencies();
                if ($currencies_data) {
                    foreach ($currencies_data as $currency) {
                        $currencies[$currency['code']] = $currency['name'] . ' (' . $currency['code'] . ')';
                    }
                } else {
                    // Fallback a la lista estática si la API falla
                    $currencies = $api_client->fetch_currencyapi_currencies();
                }
                break;
                
            case 'dolarapi':
                // DolarAPI se especializa en cotizaciones del dólar
                $currencies_data = $api_client->get_currencies();
                
                if ($currencies_data) {
                    foreach ($currencies_data as $currency) {
                        $currencies[$currency['code']] = $currency['name'] . ' (' . number_format($currency['value'], 2) . ')';
                    }
                } else {
                    // Si falla la API, usar tipos predefinidos
                    $providers = API_Client::get_available_providers();
                    $types = $providers['dolarapi']['types'] ?? [];
                    foreach ($types as $type) {
                        $friendly_names = [
                            'oficial' => 'Dólar Oficial',
                            'blue' => 'Dólar Blue',
                            'bolsa' => 'Dólar Bolsa',
                            'contadoconliqui' => 'Dólar Contado con Liqui',
                            'tarjeta' => 'Dólar Tarjeta',
                            'mayorista' => 'Dólar Mayorista',
                            'cripto' => 'Dólar Cripto',
                            'mep' => 'Dólar MEP',
                            'solidario' => 'Dólar Solidario'
                        ];
                        $currencies[$type] = $friendly_names[$type] ?? ucfirst($type);
                    }
                }
                break;
                
            case 'exchangerate-api':
                // ExchangeRate-API soporta ~165 monedas globales
                $currencies_data = $api_client->get_currencies();
                if ($currencies_data) {
                    foreach ($currencies_data as $currency) {
                        $currencies[$currency['code']] = $currency['name'] . ' (' . $currency['code'] . ')';
                    }
                } else {
                    // Fallback a la lista estática
                    $currencies = $api_client->fetch_exchangerate_api_currencies();
                }
                break;
                
            default:
                // Fallback a DolarAPI como default
                $currencies_data = $api_client->get_currencies();
                if ($currencies_data) {
                    foreach ($currencies_data as $currency) {
                        $currencies[$currency['code']] = $currency['name'] . ' (' . number_format($currency['value'], 2) . ')';
                    }
                }
        }

        // Filtrar para excluir la moneda base de la tienda (evitar conversión a sí misma)
        $base_currency = get_woocommerce_currency();
        foreach ($currencies as $code => $name) {
            if (strpos($code, $base_currency) === 0 || $code === $base_currency) {
                unset($currencies[$code]);
            }
        }

        // Ordenar alfabéticamente por nombre
        asort($currencies);

        // Si se solicita un tipo específico, devolver solo ese
        if (!empty($type) && isset($currencies[$type])) {
            $currencies = [$type => $currencies[$type]];
        }

        wp_send_json_success($currencies);
    }

    /**
     * AJAX Handler: Obtener tasa de cambio actual
     */
    public static function ajax_get_current_rate()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_die('Acceso no autorizado');
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $type = sanitize_text_field($_POST['type'] ?? 'oficial');
        $currency_pair = sanitize_text_field($_POST['currency_pair'] ?? '');
        
        $rate_data = null;
        
        if (!empty($currency_pair)) {
            // Para APIs de monedas globales
            $rate_data = $api_client->get_rate($currency_pair);
        } else {
            // Para DolarAPI (tipo específico)
            $rate_data = $api_client->get_rate($type);
        }
        
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
            if ($rate_data['value'] > 0) {
                $opts = get_option('dpuwoo_settings', []);
                $opts['last_rate'] = floatval($rate_data['value']);
                update_option('dpuwoo_settings', $opts);
            }
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error(['message' => 'No se pudo obtener la tasa de cambio']);
        }
    }

    /**
     * AJAX Handler: Obtener todas las cotizaciones disponibles
     */
    public static function ajax_get_all_rates()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_die('Acceso no autorizado');
        }
        
        $api_client = API_Client::get_instance();
        $rates = $api_client->get_currencies();
        
        if ($rates) {
            wp_send_json_success([
                'success' => true,
                'rates' => $rates,
                'count' => count($rates),
                'timestamp' => current_time('mysql')
            ]);
        } else {
            wp_send_json_error(['message' => 'No se pudieron obtener las cotizaciones']);
        }
    }

    /**
     * AJAX Handler: Probar conexión con API
     */
    public static function ajax_test_api_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_die('Acceso no autorizado');
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
        
        $result = [
            'provider' => $provider,
            'status' => 'testing',
            'timestamp' => current_time('mysql')
        ];
        
        // Probar según el proveedor
        switch ($provider) {
            case 'currencyapi':
                $test_rate = $api_client->get_currencyapi_rate('USD_ARS');
                if ($test_rate) {
                    $result['status'] = 'connected';
                    $result['rate'] = $test_rate['value'] ?? 0;
                    $result['message'] = 'Conexión exitosa con CurrencyAPI';
                } else {
                    $result['status'] = 'failed';
                    $result['message'] = 'No se pudo conectar con CurrencyAPI. Verifica tu API Key.';
                }
                break;
                
            case 'dolarapi':
                $test_rate = $api_client->get_dolarapi_rate('oficial');
                if ($test_rate) {
                    $result['status'] = 'connected';
                    $result['rate'] = $test_rate['value'] ?? 0;
                    $result['message'] = 'Conexión exitosa con DolarAPI';
                } else {
                    $result['status'] = 'failed';
                    $result['message'] = 'No se pudo conectar con DolarAPI';
                }
                break;
                
            case 'exchangerate-api':
                $test_rate = $api_client->get_exchangerate_api_rate('USD_ARS');
                if ($test_rate) {
                    $result['status'] = 'connected';
                    $result['rate'] = $test_rate['value'] ?? 0;
                    $result['message'] = 'Conexión exitosa con ExchangeRate-API';
                } else {
                    $result['status'] = 'failed';
                    $result['message'] = 'No se pudo conectar con ExchangeRate-API. Verifica tu API Key.';
                }
                break;
                
            default:
                $result['status'] = 'failed';
                $result['message'] = 'Proveedor no soportado';
        }
        
        // Limpiar cache después de la prueba
        $api_client->clear_cache();
        
        wp_send_json_success($result);
    }
}