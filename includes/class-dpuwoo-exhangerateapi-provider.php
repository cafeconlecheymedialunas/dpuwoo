<?php
// Incluir el formateador de respuestas
require_once 'class-dpuwoo-api-response-formatter.php';

// Implementación para ExchangeRate-API
class ExchangeRateAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://v6.exchangerate-api.com/v6';
    protected $auth_header = null;
    
    public function get_rate($currency_pair) {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for ExchangeRate-API');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        
        if ($currency_pair === 'latest') {
            $url = "{$this->base_url}/{$api_key}/latest/{$store_currency}";
        } else {
            $currencies = explode('_', $currency_pair);
            if (count($currencies) !== 2) {
                $this->log_error('Invalid currency pair format: ' . $currency_pair);
                return false;
            }
            
            $base_currency = strtoupper($currencies[0]);
            $target_currency = strtoupper($currencies[1]);
            $url = "{$this->base_url}/{$api_key}/pair/{$base_currency}/{$target_currency}";
        }
        
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        if ($currency_pair === 'latest') {
            if (!isset($data['conversion_rates'])) {
                return false;
            }
            
            // Usar formateador estandarizado
            $formatted_data = [
                'value' => $data['conversion_rates'],
                'updated' => $data['time_last_update_utc'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'exchangerate-api',
                'base_currency' => $store_currency,
                'type' => 'latest'
            ];
            
            return API_Response_Formatter::create_rate_response($formatted_data);
        } else {
            if (!isset($data['conversion_rate'])) {
                return false;
            }
            
            // Usar formateador estandarizado
            $formatted_data = [
                'value' => floatval($data['conversion_rate']),
                'buy' => floatval($data['conversion_rate']),
                'sell' => floatval($data['conversion_rate']),
                'updated' => $data['time_last_update_utc'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'exchangerate-api',
                'base_currency' => $store_currency,
                'target_currency' => $target_currency,
                'pair' => $store_currency . '_' . $target_currency,
                'type' => 'spot'
            ];
            
            return API_Response_Formatter::create_rate_response($formatted_data);
        }
    }
    
    public function get_currencies() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for ExchangeRate-API currencies');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        $woocommerce_currencies = get_woocommerce_currencies();
        
        $url = "{$this->base_url}/{$api_key}/latest/{$store_currency}";
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data']['conversion_rates'])) {
            $this->log_error('Failed to get currency rates from ExchangeRate-API');
            return false;
        }
        
        $data = $response['data'];
        $rates = $data['conversion_rates'];
        $updated = $data['time_last_update_utc'] ?? current_time('mysql');
        $currencies_list = [];
        
        foreach ($rates as $currency_code => $rate_value) {
            // Saltar moneda base
            if ($currency_code === $store_currency) {
                continue;
            }
            
            $exchange_rate = (float) $rate_value;
            $currency_name = $woocommerce_currencies[$currency_code] ?? $currency_code;
            
            // Usar formateador estandarizado
            $formatted_data = [
                'code' => $currency_code,
                'name' => $currency_name,
                'type' => strtolower($currency_code),
                'value' => $exchange_rate,
                'buy' => $exchange_rate,
                'sell' => $exchange_rate,
                'updated' => $updated,
                'provider' => 'exchangerate-api',
                'base_currency' => $store_currency,
                'target_currency' => $currency_code,
                'raw' => ['rate' => $exchange_rate],
                'category' => 'global_currencies'
            ];
            
            $currencies_list[] = API_Response_Formatter::create_currency_response($formatted_data);
        }
        
        return $currencies_list;
    }
    
    public function test_connection() {
        $api_key = $this->get_api_key();
        $store_currency = $this->get_store_currency();
        
        if (empty($api_key)) {
            $test_data = [
                'success' => false, 
                'error' => 'API Key no configurada',
                'provider' => 'exchangerate-api'
            ];
            return API_Response_Formatter::create_test_response($test_data);
        }
        
        $url = "{$this->base_url}/{$api_key}/pair/{$store_currency}/USD";
        $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
        
        if (is_wp_error($response)) {
            $test_data = [
                'success' => false, 
                'error' => $response->get_error_message(),
                'provider' => 'exchangerate-api'
            ];
            return API_Response_Formatter::create_test_response($test_data);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        $test_data = [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'Conexión exitosa con ExchangeRate-API' : "Error HTTP: {$code}",
            'provider' => 'exchangerate-api'
        ];
        
        return API_Response_Formatter::create_test_response($test_data);
    }
}