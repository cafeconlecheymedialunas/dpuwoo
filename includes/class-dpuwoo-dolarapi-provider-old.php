<?php
class DolarAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://{country}dolarapi.com/v1';
    protected $auth_header = null;
    private $current_country = null;

    public function set_country($country) {
        $this->current_country = strtolower($country);
    }
    
    private function build_url($endpoint) {
        // Usar el país establecido dinámicamente, o el de la tienda por defecto
        $country = $this->current_country ?: $this->get_store_country();
        
        // Argentina no usa prefijo
        if ($country === 'ar') {
            $url = "https://dolarapi.com/v1" . $endpoint;
        } else {
            $url = str_replace('{country}', $country . '.', $this->base_url) . $endpoint;
        }
        
        return $url;
    }
    
    
    public function get_rate($type) {
        $store_currency = $this->get_store_currency();
        
        $url = $this->build_url("/dolares/" . rawurlencode($type));
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        // Formato estandarizado de respuesta
        return [
            'value' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'buy' => isset($data['compra']) ? $this->parse_numeric_value($data['compra']) : 0,
            'sell' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'updated' => $data['fechaActualizacion'] ?? current_time('mysql'),
            'raw' => $data,
            'provider' => 'dolarapi',
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'pair' => 'USD_ARS'
        ];
    }
    
    public function get_currencies() {
        $store_currency = $this->get_store_currency();
        
        // Para DolarAPI, solo funciona con USD como base
        if ($store_currency !== 'USD') {
            $this->log_error('DolarAPI solo funciona con USD como moneda base. Tienda usa: ' . $store_currency);
            return [];
        }
        
        // Obtener ambos endpoints
        $dolares_data = $this->get_dolares_raw();
        $cotizaciones_data = $this->get_cotizaciones_raw();
        
        // Consolidar todo en un solo array
        $all_currencies = [];
        
        // Procesar dólares
        if ($dolares_data && $dolares_data['http_code'] === 200 && is_array($dolares_data['data'])) {
            foreach ($dolares_data['data'] as $dolar) {
                if (!is_array($dolar) || !isset($dolar['casa'])) continue;
                
                $all_currencies[] = [
                    'type' => 'dolar',
                    'code' => $store_currency . '_' . strtoupper($dolar['casa']),
                    'key' => $dolar['casa'],
                    'name' => $dolar['nombre'] ?? ucfirst($dolar['casa']),
                    'value' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'buy' => isset($dolar['compra']) ? $this->parse_numeric_value($dolar['compra']) : 0,
                    'sell' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'updated' => $dolar['fechaActualizacion'] ?? current_time('mysql'),
                    'raw' => $dolar,
                    'provider' => 'dolarapi',
                    'base_currency' => $store_currency,
                    'target_currency' => 'ARS'
                ];
            }
        }
        
        // Procesar cotizaciones
        if ($cotizaciones_data && $cotizaciones_data['http_code'] === 200 && is_array($cotizaciones_data['data'])) {
            foreach ($cotizaciones_data['data'] as $moneda) {
                if (!is_array($moneda) || !isset($moneda['moneda'])) continue;
                
                // Filtrar solo monedas (no dólares)
                $moneda_code = strtoupper($moneda['moneda']);
                $moneda_nombre = strtolower($moneda['nombre'] ?? '');
                
                // Excluir dólares
                if ($moneda_code === 'USD' || 
                    strpos($moneda_nombre, 'dólar') !== false || 
                    strpos($moneda_nombre, 'dolar') !== false) {
                    continue;
                }
                
                $all_currencies[] = [
                    'type' => 'moneda',
                    'code' => $store_currency . '_' . $moneda_code,
                    'key' => strtolower($moneda_code),
                    'name' => $moneda['nombre'] ?? $moneda_code,
                    'value' => isset($moneda['venta']) ? $this->parse_numeric_value($moneda['venta']) : 0,
                    'buy' => isset($moneda['compra']) ? $this->parse_numeric_value($moneda['compra']) : 0,
                    'sell' => isset($moneda['venta']) ? $this->parse_numeric_value($moneda['venta']) : 0,
                    'updated' => $moneda['fechaActualizacion'] ?? current_time('mysql'),
                    'raw' => $moneda,
                    'provider' => 'dolarapi',
                    'base_currency' => $store_currency,
                    'target_currency' => $moneda_code
                ];
            }
        }
        
        return $all_currencies;
    }
    
    private function get_dolares_raw() {
        $url = $this->build_url("/dolares");
        $response = $this->make_request($url);
        
        if (!$response || $response['code'] !== 200) {
            return false;
        }
        
        return [
            'data' => $response['data'],
            'raw' => $response['raw'],
            'http_code' => $response['code'],
            'url' => $url
        ];
    }
    
    private function get_cotizaciones_raw() {
        $url = $this->build_url("/cotizaciones");
        $response = $this->make_request($url);
        
        if (!$response || $response['code'] !== 200) {
            return false;
        }
        
        return [
            'data' => $response['data'],
            'raw' => $response['raw'],
            'http_code' => $response['code'],
            'url' => $url
        ];
    }
    
    public function test_connection() {
        $country = $this->get_store_country();
        
        // Argentina no usa prefijo
        if ($country === 'ar') {
            $url = "https://dolarapi.com/v1/dolares";
        } else {
            $url = "https://{$country}.dolarapi.com/v1/dolares";
        }
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => false,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'url' => $url
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        return [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'OK' : "Error: {$code}"
        ];
    }
}