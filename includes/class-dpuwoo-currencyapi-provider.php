<?php
// Implementación para CurrencyAPI
class CurrencyAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://api.currencyapi.com/v3';
    protected $auth_header = 'apikey';
    
    public function get_rate($currency_pair) {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for CurrencyAPI');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        
        // Determinar la moneda objetivo
        if ($currency_pair === 'latest') {
            // Obtener todas las tasas desde la moneda base
            $url = add_query_arg([
                'base_currency' => $store_currency,
            ], "{$this->base_url}/latest");
        } else {
            // Obtener par específico
            $currencies = explode('_', $currency_pair);
            if (count($currencies) !== 2) {
                $this->log_error('Invalid currency pair format: ' . $currency_pair);
                return false;
            }
            
            $target_currency = strtoupper($currencies[1]);
            $url = add_query_arg([
                'base_currency' => $store_currency,
                'currencies' => $target_currency,
            ], "{$this->base_url}/latest");
        }
        
        $response = $this->make_request($url, [
            'headers' => ['apikey' => $api_key],
        ]);
        
        if (!$response || !isset($response['data']['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        if ($currency_pair === 'latest') {
            return [
                'value' => $data['data'],
                'updated' => $data['meta']['last_updated_at'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'currencyapi',
                'base_currency' => $store_currency
            ];
        } else {
            $target_currency = strtoupper(explode('_', $currency_pair)[1]);
            if (!isset($data['data'][$target_currency])) {
                return false;
            }
            
            $rate_data = $data['data'][$target_currency];
            return [
                'value' => floatval($rate_data['value']),
                'buy' => floatval($rate_data['value']),
                'sell' => floatval($rate_data['value']),
                'updated' => $data['meta']['last_updated_at'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'currencyapi',
                'base_currency' => $store_currency,
                'target_currency' => $target_currency,
                'pair' => $store_currency . '_' . $target_currency
            ];
        }
    }
    
    public function get_currencies() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for CurrencyAPI');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        $woocommerce_currencies = get_woocommerce_currencies();
        
        $url = add_query_arg([
            'base_currency' => $store_currency,
        ], "{$this->base_url}/latest");
        
        $response = $this->make_request($url, [
            'headers' => ['apikey' => $api_key],
        ]);
        
        if (!$response || $response['code'] !== 200 || !isset($response['data']['data'])) {
            $this->log_error('CurrencyAPI Error: ' . print_r($response, true));
            return [];
        }
        
        $data = $response['data'];
        $updated = $data['meta']['last_updated_at'] ?? current_time('mysql');
        $currencies_list = [];
        
        foreach ($data['data'] as $currency_code => $rate_data) {
            if (!isset($rate_data['value']) || $currency_code === $store_currency) {
                continue;
            }
            
            $value = (float) $rate_data['value'];
            $currency_name = $woocommerce_currencies[$currency_code] ?? $currency_code;
            
            $currencies_list[] = [
                'code' => $store_currency . '_' . $currency_code,
                'name' => $currency_name,
                'type' => strtolower($currency_code),
                'value' => $value,
                'buy' => $value,
                'sell' => $value,
                'updated' => $updated,
                'provider' => 'currencyapi',
                'base_currency' => $store_currency,
                'target_currency' => $currency_code,
                'raw' => $rate_data
            ];
        }
        
        return $currencies_list;
    }
    
    public function test_connection() {
        $api_key = $this->get_api_key();
        $store_currency = $this->get_store_currency();
        
        if (empty($api_key)) {
            return ['success' => false, 'error' => 'API Key no configurada'];
        }
        
        $url = "{$this->base_url}/latest?base_currency={$store_currency}&currencies=USD";
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['apikey' => $api_key]
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        return [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'Conexión exitosa con CurrencyAPI' : "Error HTTP: {$code}"
        ];
    }
}
