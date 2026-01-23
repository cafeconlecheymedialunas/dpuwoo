<?php
// Incluir el formateador de respuestas
require_once 'class-dpuwoo-api-response-formatter.php';

class DolarAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://{country}dolarapi.com/v1';
    protected $auth_header = null;
    private $current_country = null;

    public function set_country($country) {
        $this->current_country = $country;
    }
    
    protected function build_url($endpoint) {
        // Usar el país establecido dinámicamente, o el de la tienda por defecto
        $country = $this->current_country ?: $this->get_store_country();
        
        // Convertir código de país al formato ISO 3166-1 alpha-3 SOLO para DolarAPI
        $iso_country = $this->map_to_iso_country($country);
        
        // Convertir código de país al formato que usa DolarAPI (2 letras)
        $country_2letter = $this->convert_country_code_to_2letter($iso_country);
        
        // Argentina no usa prefijo
        if ($country_2letter === 'ar') {
            $url = "https://dolarapi.com/v1" . $endpoint;
        } else {
            $url = "https://{$country_2letter}.dolarapi.com/v1" . $endpoint;
        }
        
        return $url;
    }
    
    /**
     * Mapear código de país a formato ISO 3166-1 alpha-3 (SOLO para URLs de DolarAPI)
     */
    private function map_to_iso_country($country_code) {
        $country_lower = strtolower($country_code);
        
        // Mapeo específico para DolarAPI (ISO 3166-1 alpha-3)
        $country_mapping = [
            'ar' => 'ARG',
            'cl' => 'CHL',
            'uy' => 'URY',
            'br' => 'BRA',
            'mx' => 'MEX',
            'co' => 'COL',
            'pe' => 'PER'
        ];
        
        return $country_mapping[$country_lower] ?? strtoupper($country_code);
    }
    
    /**
     * Convertir código de país de 3 letras a 2 letras para DolarAPI
     */
    private function convert_country_code_to_2letter($country_code) {
        $mapping = [
            'ARG' => 'ar',
            'CHL' => 'cl',
            'URY' => 'uy',
            'BRA' => 'br',
            'MEX' => 'mx',
            'COL' => 'co',
            'PER' => 'pe'
        ];
        
        $country_upper = strtoupper($country_code);
        return $mapping[$country_upper] ?? strtolower(substr($country_code, 0, 2));
    }
    
    
    public function get_rate($type) {
        $store_currency = $this->get_store_currency();
        
        $url = $this->build_url("/dolares/" . rawurlencode($type));
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        // Usar formateador estandarizado
        $dollar_type = strtoupper($type);
        $formatted_data = [
            'value' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'buy' => isset($data['compra']) ? $this->parse_numeric_value($data['compra']) : 0,
            'sell' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'updated' => $data['fechaActualizacion'] ?? current_time('mysql'),
            'raw' => $data,
            'provider' => 'dolarapi',
            'base_currency' => $store_currency,
            'target_currency' => 'USD',
            'pair' => $store_currency . '_DOLAR_' . $dollar_type,
            'type' => $type,
            'api_code' => $type,
            'dollar_type' => $dollar_type
        ];
        
        return API_Response_Formatter::create_rate_response($formatted_data);
    }
    
    public function get_currencies() {
        $store_currency = $this->get_store_currency();
        
        // Obtener ambos endpoints
        $dolares_data = $this->get_dolares_raw();
        $cotizaciones_data = $this->get_cotizaciones_raw();
        
        // Consolidar todo en un solo array
        $all_currencies = [];
        
        // Procesar dólares
        if ($dolares_data && $dolares_data['http_code'] === 200 && is_array($dolares_data['data'])) {
            foreach ($dolares_data['data'] as $dolar) {
                if (!is_array($dolar) || !isset($dolar['casa'])) continue;
                
                // Usar formateador estandarizado para dólares
                // Código: TIENDA_DOLAR_TIPO (ej: CLP_DOLAR_OFICIAL)
                $dollar_type = strtoupper($dolar['casa']);
                $formatted_data = [
                    'type' => 'dolar',
                    'code' => $store_currency . '_DOLAR_' . $dollar_type,
                    'key' => $dolar['casa'],
                    'name' => $dolar['nombre'] ?? ucfirst($dolar['casa']),
                    'value' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'buy' => isset($dolar['compra']) ? $this->parse_numeric_value($dolar['compra']) : 0,
                    'sell' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'updated' => $dolar['fechaActualizacion'] ?? current_time('mysql'),
                    'raw' => $dolar,
                    'provider' => 'dolarapi',
                    'base_currency' => $store_currency,
                    'target_currency' => 'USD',
                    'category' => 'dollar_types',
                    'api_code' => $dolar['casa'], // Código que devuelve la API
                    'dollar_type' => $dollar_type  // Tipo de dólar específico
                ];
                
                $all_currencies[] = API_Response_Formatter::create_currency_response($formatted_data);
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
                
                // Usar formateador estandarizado para otras monedas
                $formatted_data = [
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
                    'target_currency' => $moneda_code,
                    'category' => 'foreign_currencies'
                ];
                
                $all_currencies[] = API_Response_Formatter::create_currency_response($formatted_data);
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
        // Usar build_url para mantener consistencia
        $url = $this->build_url("/dolares");
        
        $start_time = microtime(true);
        
        // Usar curl en lugar de wp_remote_get para mejor compatibilidad
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: DPUWoo-DolarAPI-Test/1.0'
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $response_time = round((microtime(true) - $start_time) * 1000, 2); // ms
        
        $test_data = [
            'success' => !$error && $code === 200,
            'http_code' => $code,
            'url' => $url,
            'message' => !$error && $code === 200 ? 'OK' : ($error ?: "Error: {$code}"),
            'error' => $error,
            'response_time' => $response_time,
            'provider' => 'dolarapi'
        ];
        
        return API_Response_Formatter::create_test_response($test_data);
    }
}