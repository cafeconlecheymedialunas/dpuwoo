<?php 
// Trait para manejar peticiones HTTP comunes
trait HTTP_Request_Trait {
    /**
     * Realizar petición HTTP
     */
    protected function make_request($url, $args = [], $method = 'GET') {
        $default_args = [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
            'method' => $method
        ];
        
        // Combinar argumentos
        $args = wp_parse_args($args, $default_args);
        
        // Añadir API key si está disponible
        $api_key = $this->get_api_key();
        if (!empty($api_key) && isset($this->auth_header)) {
            $args['headers'][$this->auth_header] = $this->auth_header === 'Authorization' 
                ? 'Bearer ' . trim($api_key) 
                : trim($api_key);
        }
        
        // Realizar la solicitud
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('HTTP Request Error: ' . $response->get_error_message() . ' - URL: ' . $url);
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $this->log_error("HTTP Error {$code} - URL: {$url} - Body: " . substr($body, 0, 200));
            return false;
        }
        
        if (empty($body)) {
            $this->log_error("Empty response - URL: {$url}");
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            $this->log_error('Invalid JSON Response - URL: ' . $url . ' - Body: ' . substr($body, 0, 200));
            return false;
        }
        
        return [
            'code' => $code,
            'data' => $data,
            'raw' => $body
        ];
    }
    
    /**
     * Parsear valor numérico de diferentes formatos
     */
    protected function parse_numeric_value($value) {
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        if (is_string($value)) {
            // Manejar formatos como "1.234,56" o "1234.56"
            $cleaned = str_replace(['.', ','], ['', '.'], $value);
            return is_numeric($cleaned) ? floatval($cleaned) : 0;
        }
        
        return 0;
    }
    
    /**
     * Obtener API key de configuración
     */
    protected function get_api_key() {
        $opts = get_option('dpuwoo_settings', []);
        
        // Obtener la API key específica del proveedor actual
        if (get_class($this) === 'CurrencyAPI_Provider') {
            return $opts['currencyapi_api_key'] ?? '';
        } elseif (get_class($this) === 'ExchangeRateAPI_Provider') {
            return $opts['exchangerate_api_key'] ?? '';
        }
        
        // API key general para otros proveedores
        return $opts['api_key'] ?? '';
    }
    
    /**
     * Log de errores
     */
    protected function log_error($message) {
        
    }
    
    /**
     * Obtener código de país base de la tienda
     */
    protected function get_store_country() {
        $base_country = get_option('woocommerce_default_country', 'AR:AR');
        $country_parts = explode(':', $base_country);
        return strtolower($country_parts[0]);
    }
    
    /**
     * Obtener código de moneda base de la tienda
     */
    protected function get_store_currency() {
        return strtoupper(get_woocommerce_currency());
    }
    
    /**
     * Obtener nombre de moneda desde WooCommerce
     */
    protected function get_currency_name_from_woocommerce($currency_code) {
        $woocommerce_currencies = get_woocommerce_currencies();
        return $woocommerce_currencies[$currency_code] ?? $currency_code;
    }
}

// Clase base abstracta para proveedores
abstract class Base_API_Provider {
    use HTTP_Request_Trait;
    
    protected $auth_header = 'Authorization'; // Header por defecto para auth
    
    abstract public function get_rate($type);
    abstract public function get_currencies();
    abstract public function test_connection();
}
