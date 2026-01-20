<?php
if (!defined('ABSPATH')) exit;

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
            $this->log_error('HTTP Request Error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200 || empty($body)) {
            $this->log_error("HTTP Error {$code} - URL: {$url}");
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !is_array($data)) {
            $this->log_error('Invalid JSON Response');
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
        return $opts['api_key'] ?? '';
    }
    
    /**
     * Log de errores
     */
    protected function log_error($message) {
        error_log('[API Provider] ' . $message);
    }
    
    /**
     * Obtener código de país
     */
    protected function get_country_code() {
        $base_country = get_option('woocommerce_default_country', 'AR');
        $country_code = strtolower($base_country);
        
        if (strpos($country_code, ':') !== false) {
            $country_parts = explode(':', $country_code);
            $country_code = $country_parts[0];
        }
        
        return $country_code;
    }
}

// Clase base abstracta para proveedores
abstract class Base_API_Provider {
    use HTTP_Request_Trait;
    
    protected $cache_time = 300; // 5 minutos
    protected $auth_header = 'Authorization'; // Header por defecto para auth
    
    abstract public function get_rate($type);
    abstract public function get_currencies();
    abstract public function test_connection();
    
    /**
     * Obtener nombre de moneda por código
     */
    protected function get_currency_name($currency_code) {
        $currencies = [
            'USD' => 'Dólar Estadounidense',
            'ARS' => 'Peso Argentino',
            'BRL' => 'Real Brasileño',
            'MXN' => 'Peso Mexicano',
            'CLP' => 'Peso Chileno',
            'COP' => 'Peso Colombiano',
            'PEN' => 'Sol Peruano',
            'UYU' => 'Peso Uruguayo',
            'EUR' => 'Euro',
            'GBP' => 'Libra Esterlina',
            'JPY' => 'Yen Japonés'
        ];
        
        return $currencies[$currency_code] ?? $currency_code;
    }
    
    /**
     * Obtener cache key específico del proveedor
     */
    protected function get_cache_key($type) {
        $provider_name = strtolower((new ReflectionClass($this))->getShortName());
        return 'dpuwoo_' . $provider_name . '_' . sanitize_key($type);
    }
    
    /**
     * Obtener datos de cache
     */
    protected function get_cached($key) {
        return get_transient($key);
    }
    
    /**
     * Guardar datos en cache
     */
    protected function set_cache($key, $data) {
        if ($data) {
            set_transient($key, $data, $this->cache_time);
        }
    }
}

// Implementación para DolarAPI
class DolarAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://{country}.dolarapi.com/v1';
    protected $auth_header = null; // DolarAPI no requiere auth
    
    public function get_rate($type) {
        $cache_key = $this->get_cache_key('rate_' . $type);
        $cached = $this->get_cached($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = $this->build_url("/dolares/" . rawurlencode($type));
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data']['venta'])) {
            return false;
        }
        
        $data = $response['data'];
        $result = [
            'value' => $this->parse_numeric_value($data['venta']),
            'buy' => isset($data['compra']) ? $this->parse_numeric_value($data['compra']) : $this->parse_numeric_value($data['venta']),
            'sell' => $this->parse_numeric_value($data['venta']),
            'updated' => $data['fechaActualizacion'] ?? current_time('mysql'),
            'raw' => $data,
            'provider' => 'dolarapi'
        ];
        
        $this->set_cache($cache_key, $result);
        return $result;
    }
    
    public function get_currencies() {
        $cache_key = $this->get_cache_key('currencies');
        $cached = $this->get_cached($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = $this->build_url("/dolares");
        $response = $this->make_request($url);
        
        if (!$response || !is_array($response['data'])) {
            $this->log_error('No se pudieron obtener datos de DolarAPI');
            return false;
        }
        
        $data = $response['data'];
        $processed = [];
        
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['casa'])) continue;
            
            $type = $item['casa'];
            $value = isset($item['venta']) ? $this->parse_numeric_value($item['venta']) : 0;
            
            if ($value > 0) {
                $name = $this->get_currency_display_name($item, $type);
                
                $processed[] = [
                    'code' => 'USD-' . strtoupper($type),
                    'name' => $name,
                    'type' => $type,
                    'value' => $value,
                    'buy' => isset($item['compra']) ? $this->parse_numeric_value($item['compra']) : $value,
                    'sell' => $value,
                    'updated' => $item['fechaActualizacion'] ?? $item['fecha'] ?? current_time('mysql'),
                    'provider' => 'dolarapi',
                    'raw' => $item
                ];
            }
        }
        
        $this->set_cache($cache_key, $processed);
        return $processed;
    }
    
    public function test_connection() {
        $url = $this->build_url("/dolares/oficial");
        $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        return [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'Conexión exitosa con DolarAPI' : "Error HTTP: {$code}"
        ];
    }
    
    private function build_url($endpoint) {
        $country = $this->get_country_code();
        return str_replace('{country}', $country, $this->base_url) . $endpoint;
    }
    
    private function get_currency_display_name($item, $default_type) {
        if (isset($item['nombre']) && !empty($item['nombre'])) {
            return $item['nombre'];
        }
        
        if (isset($item['moneda']) && !empty($item['moneda'])) {
            return $item['moneda'];
        }
        
        // Mapear nombres comunes
        $common_names = [
            'oficial' => 'Dólar Oficial',
            'blue' => 'Dólar Blue',
            'bolsa' => 'Dólar Bolsa',
            'contadoconliqui' => 'Dólar Contado con Liqui',
            'tarjeta' => 'Dólar Tarjeta',
            'mayorista' => 'Dólar Mayorista',
            'cripto' => 'Dólar Cripto',
            'solidario' => 'Dólar Solidario',
            'mep' => 'Dólar MEP'
        ];
        
        return $common_names[$default_type] ?? ucfirst($default_type);
    }
}

// Implementación para CurrencyAPI
class CurrencyAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://api.currencyapi.com/v3';
    protected $auth_header = 'apikey'; // CurrencyAPI usa header 'apikey'
    
    public function get_rate($currency_pair) {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for CurrencyAPI');
            return false;
        }
        
        $cache_key = $this->get_cache_key('rate_' . $currency_pair);
        $cached = $this->get_cached($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        if ($currency_pair === 'latest') {
            $url = "{$this->base_url}/latest";
        } else {
            $currencies = explode('_', $currency_pair);
            if (count($currencies) !== 2) {
                $this->log_error('Invalid currency pair format: ' . $currency_pair);
                return false;
            }
            
            $base_currency = strtoupper($currencies[0]);
            $target_currency = strtoupper($currencies[1]);
            $url = "{$this->base_url}/latest?base_currency=" . urlencode($base_currency) . 
                   "&currencies=" . urlencode($target_currency);
        }
        
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data']['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        if ($currency_pair === 'latest') {
            $result = [
                'value' => $data['data'],
                'updated' => $data['meta']['last_updated_at'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'currencyapi'
            ];
        } else {
            $target_currency = strtoupper(explode('_', $currency_pair)[1]);
            if (!isset($data['data'][$target_currency])) {
                return false;
            }
            
            $rate_data = $data['data'][$target_currency];
            $result = [
                'value' => floatval($rate_data['value']),
                'buy' => floatval($rate_data['value']),
                'sell' => floatval($rate_data['value']),
                'updated' => $data['meta']['last_updated_at'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'currencyapi'
            ];
        }
        
        $this->set_cache($cache_key, $result);
        return $result;
    }
    
    public function get_currencies() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            // Fallback a lista estática si no hay API key
            return $this->get_static_currencies();
        }
        
        $cache_key = $this->get_cache_key('currencies');
        $cached = $this->get_cached($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = "{$this->base_url}/latest?base_currency=USD";
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data']['data'])) {
            return $this->get_static_currencies();
        }
        
        $data = $response['data']['data'];
        $currencies = [];
        
        // Monedas latinoamericanas y principales
        $target_currencies = ['ARS', 'BRL', 'MXN', 'CLP', 'COP', 'PEN', 'UYU', 'EUR', 'GBP', 'JPY'];
        
        foreach ($target_currencies as $currency) {
            if (isset($data[$currency])) {
                $rate_data = $data[$currency];
                $currencies[] = [
                    'code' => 'USD_' . $currency,
                    'name' => $this->get_currency_name($currency),
                    'type' => strtolower($currency),
                    'value' => floatval($rate_data['value']),
                    'buy' => floatval($rate_data['value']),
                    'sell' => floatval($rate_data['value']),
                    'updated' => $response['data']['meta']['last_updated_at'] ?? current_time('mysql'),
                    'provider' => 'currencyapi'
                ];
                
                if (count($currencies) >= 7) break;
            }
        }
        
        $this->set_cache($cache_key, $currencies);
        return $currencies;
    }
    
    public function test_connection() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return ['success' => false, 'error' => 'API Key no configurada'];
        }
        
        $url = "{$this->base_url}/latest?base_currency=USD&currencies=ARS";
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
    
    private function get_static_currencies() {
        return [
            [
                'code' => 'USD_ARS',
                'name' => 'Peso Argentino',
                'type' => 'ars',
                'value' => 350.50,
                'buy' => 350.50,
                'sell' => 350.50,
                'updated' => current_time('mysql'),
                'provider' => 'currencyapi_static'
            ],
            [
                'code' => 'USD_BRL',
                'name' => 'Real Brasileño',
                'type' => 'brl',
                'value' => 5.20,
                'buy' => 5.20,
                'sell' => 5.20,
                'updated' => current_time('mysql'),
                'provider' => 'currencyapi_static'
            ]
        ];
    }
}

// Implementación para ExchangeRate-API
class ExchangeRateAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://v6.exchangerate-api.com/v6';
    protected $auth_header = null; // API key va en la URL
    
    public function get_rate($currency_pair) {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for ExchangeRate-API');
            return false;
        }
        
        $cache_key = $this->get_cache_key('rate_' . $currency_pair);
        $cached = $this->get_cached($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        if ($currency_pair === 'latest') {
            $url = "{$this->base_url}/{$api_key}/latest/USD";
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
        
        if (!$response) {
            return false;
        }
        
        $data = $response['data'];
        
        if ($currency_pair === 'latest') {
            if (!isset($data['conversion_rates'])) {
                return false;
            }
            
            $result = [
                'value' => $data['conversion_rates'],
                'updated' => $data['time_last_update_utc'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'exchangerate-api'
            ];
        } else {
            if (!isset($data['conversion_rate'])) {
                return false;
            }
            
            $result = [
                'value' => floatval($data['conversion_rate']),
                'buy' => floatval($data['conversion_rate']),
                'sell' => floatval($data['conversion_rate']),
                'updated' => $data['time_last_update_utc'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'exchangerate-api'
            ];
        }
        
        $this->set_cache($cache_key, $result);
        return $result;
    }
    
    public function get_currencies() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return $this->get_static_currencies();
        }
        
        $cache_key = $this->get_cache_key('currencies');
        $cached = $this->get_cached($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = "{$this->base_url}/{$api_key}/latest/USD";
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data']['conversion_rates'])) {
            return $this->get_static_currencies();
        }
        
        $data = $response['data']['conversion_rates'];
        $currencies = [];
        
        // Monedas latinoamericanas y principales
        $target_currencies = ['ARS', 'BRL', 'MXN', 'CLP', 'COP', 'PEN', 'UYU', 'EUR', 'GBP', 'JPY'];
        
        foreach ($target_currencies as $currency) {
            if (isset($data[$currency])) {
                $currencies[] = [
                    'code' => 'USD_' . $currency,
                    'name' => $this->get_currency_name($currency),
                    'type' => strtolower($currency),
                    'value' => floatval($data[$currency]),
                    'buy' => floatval($data[$currency]),
                    'sell' => floatval($data[$currency]),
                    'updated' => $response['data']['time_last_update_utc'] ?? current_time('mysql'),
                    'provider' => 'exchangerate-api'
                ];
                
                if (count($currencies) >= 7) break;
            }
        }
        
        $this->set_cache($cache_key, $currencies);
        return $currencies;
    }
    
    public function test_connection() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return ['success' => false, 'error' => 'API Key no configurada'];
        }
        
        $url = "{$this->base_url}/{$api_key}/pair/USD/ARS";
        $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        return [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'Conexión exitosa con ExchangeRate-API' : "Error HTTP: {$code}"
        ];
    }
    
    private function get_static_currencies() {
        return [
            [
                'code' => 'USD_ARS',
                'name' => 'Peso Argentino',
                'type' => 'ars',
                'value' => 350.50,
                'buy' => 350.50,
                'sell' => 350.50,
                'updated' => current_time('mysql'),
                'provider' => 'exchangerate-api_static'
            ],
            [
                'code' => 'USD_EUR',
                'name' => 'Euro',
                'type' => 'eur',
                'value' => 0.92,
                'buy' => 0.92,
                'sell' => 0.92,
                'updated' => current_time('mysql'),
                'provider' => 'exchangerate-api_static'
            ]
        ];
    }
}

// Cliente principal que usa el patrón Strategy
class API_Client
{
    protected static $instance;
    protected $providers = [];
    protected $current_provider;
    
    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_instance()
    {
        return self::init();
    }
    
    private function __construct()
    {
        // Inicializar proveedores
        $this->providers = [
            'currencyapi' => new CurrencyAPI_Provider(),
            'dolarapi' => new DolarAPI_Provider(),
            'exchangerate-api' => new ExchangeRateAPI_Provider()
        ];
        
        // Establecer proveedor actual desde configuración
        $opts = get_option('dpuwoo_settings', []);
        $provider_key = $opts['api_provider'] ?? 'dolarapi';
        $this->set_provider($provider_key);
    }
    
    /**
     * Establecer proveedor actual
     */
    public function set_provider($provider_key)
    {
        if (isset($this->providers[$provider_key])) {
            $this->current_provider = $this->providers[$provider_key];
            return true;
        }
        
        // Fallback a DolarAPI si el proveedor no existe
        $this->current_provider = $this->providers['dolarapi'];
        return false;
    }
    
    /**
     * Obtener proveedor actual
     */
    public function get_provider($provider_key = null)
    {
        if ($provider_key && isset($this->providers[$provider_key])) {
            return $this->providers[$provider_key];
        }
        
        return $this->current_provider;
    }
    
    /**
     * Obtener todos los proveedores disponibles
     */
    public static function get_available_providers()
    {
        return [
            'currencyapi' => [
                'name' => 'CurrencyAPI.com',
                'description' => 'API con soporte para 170+ monedas globales (requiere API Key)',
                'url' => 'https://currencyapi.com/',
                'requires_key' => true,
                'types' => ['latest', 'historical'],
                'supports_currencies' => true,
                'currency_endpoint' => 'currencies'
            ],
            'dolarapi' => [
                'name' => 'DolarAPI.com',
                'description' => 'API pública de cotizaciones del dólar en Argentina',
                'domain' => 'dolarapi.com',
                'url' => 'https://dolarapi.com/v1',
                'requires_key' => false,
                'types' => ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta', 'mayorista', 'cripto', 'mep', 'solidario'],
                'supports_currencies' => false,
                'currency_endpoint' => null
            ],
            'exchangerate-api' => [
                'name' => 'ExchangeRate-API.com',
                'description' => 'API con soporte para 165+ monedas globales',
                'url' => 'https://www.exchangerate-api.com/',
                'requires_key' => true,
                'types' => ['latest', 'convert'],
                'supports_currencies' => true,
                'currency_endpoint' => 'codes'
            ]
        ];
    }
    
    /**
     * Métodos delegados al proveedor actual
     */
    public function get_rate($type = 'oficial')
    {
        return $this->current_provider->get_rate($type);
    }
    
    public function get_currencies()
    {
        return $this->current_provider->get_currencies();
    }
    
    public function test_connection($provider_key = null)
    {
        $provider = $provider_key ? $this->get_provider($provider_key) : $this->current_provider;
        return $provider->test_connection();
    }
    
    /**
     * Limpiar cache de todos los proveedores
     */
    public function clear_cache($type = 'all')
    {
        global $wpdb;
        
        if ($type === 'all' || $type === 'rates') {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_dpuwoo_%'
                )
            );
        }
        
        return true;
    }
}