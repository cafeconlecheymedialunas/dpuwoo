<?php

class MoneyConvert_Provider extends Base_API_Provider {
    protected $base_url = 'https://cdn.moneyconvert.net/api';
    
    protected function build_url($endpoint) {
        return $this->base_url . $endpoint;
    }
    
    public function get_rate($target_currency = null) {
        $store_currency = $this->get_store_currency();
        
        if (!$target_currency) {
            $target_currency = 'USD';
        }
        
        $url = $this->build_url('/latest.json');
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['rates'])) {
            return [
                'success' => false,
                'error' => 'No se pudieron obtener tasas'
            ];
        }
        
        $rates = $response['rates'];
        $base_rate = $rates[$store_currency] ?? 1;
        $target_rate = $rates[$target_currency] ?? 0;
        
        if ($target_rate === 0) {
            return [
                'success' => false,
                'error' => 'Moneda no soportada: ' . $target_currency
            ];
        }
        
        $calculated_rate = $target_rate / $base_rate;
        
        return API_Response_Formatter::create_rate_response([
            'base_currency' => $store_currency,
            'target_currency' => $target_currency,
            'rate' => $calculated_rate,
            'updated' => current_time('mysql'),
            'provider' => 'moneyconvert'
        ]);
    }
    
    public function get_currencies() {
        $url = $this->build_url('/latest.json');
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['rates'])) {
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        $all_currencies = [];
        
        foreach ($response['rates'] as $code => $value) {
            if (!is_numeric($value)) continue;
            
            $formatted_data = [
                'type' => 'fiat',
                'code' => $code,
                'key' => strtolower($code),
                'name' => $code,
                'value' => floatval($value),
                'buy' => floatval($value),
                'sell' => floatval($value),
                'updated' => current_time('mysql'),
                'raw' => null,
                'provider' => 'moneyconvert',
                'base_currency' => 'USD',
                'target_currency' => $code,
                'category' => 'fiat',
                'api_code' => $code,
                'currency' => $code
            ];
            
            $all_currencies[] = API_Response_Formatter::create_currency_response($formatted_data);
        }
        
        return $all_currencies;
    }
    
    public function test_connection() {
        $url = $this->build_url('/latest.json');
        $response = $this->make_request($url);
        
        if ($response && isset($response['rates']['ARS'])) {
            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'value' => $response['rates']['ARS']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Error de conexión'
        ];
    }
}