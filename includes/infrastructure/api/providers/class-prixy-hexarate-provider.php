<?php

class HexaRate_Provider extends Base_API_Provider {
    protected $base_url = 'https://hexarate.paikama.co/api';
    
    protected function build_url($endpoint) {
        return $this->base_url . $endpoint;
    }
    
    public function get_rate($target_currency = null) {
        $store_currency = $this->get_store_currency();
        
        if (!$target_currency) {
            $target_currency = 'USD';
        }
        
        $url = $this->build_url('/rates/' . $store_currency . '/' . $target_currency . '/latest');
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return [
                'success' => false,
                'error' => 'No se pudieron obtener tasas'
            ];
        }
        
        $data = $response['data'];
        
        return API_Response_Formatter::create_rate_response([
            'base_currency' => $store_currency,
            'target_currency' => $target_currency,
            'rate' => floatval($data['mid']),
            'updated' => $data['timestamp'] ?? current_time('mysql'),
            'provider' => 'hexarate'
        ]);
    }
    
    public function get_currencies() {
        $url = $this->build_url('/currencies');
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        $all_currencies = [];
        
        foreach ($response['data'] as $code => $info) {
            $code_upper = strtoupper($code);
            
            $formatted_data = [
                'type' => 'fiat',
                'code' => $code_upper,
                'key' => strtolower($code_upper),
                'name' => is_array($info) ? ($info['name'] ?? $code_upper) : $code_upper,
                'value' => 1,
                'buy' => 1,
                'sell' => 1,
                'updated' => current_time('mysql'),
                'raw' => null,
                'provider' => 'hexarate',
                'base_currency' => 'USD',
                'target_currency' => $code_upper,
                'category' => 'fiat',
                'api_code' => $code,
                'currency' => $code_upper
            ];
            
            $all_currencies[] = API_Response_Formatter::create_currency_response($formatted_data);
        }
        
        return $all_currencies;
    }
    
    public function test_connection() {
        $url = $this->build_url('/rates/USD/ARS/latest');
        $response = $this->make_request($url);
        
        if ($response && isset($response['data']['mid'])) {
            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'value' => $response['data']['mid']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Error de conexión'
        ];
    }
}