<?php
if (!defined('ABSPATH')) exit;

// Cliente principal
class API_Client
{
    protected static $instance;
    protected $providers = [];
    
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
    }
    
    /**
     * Obtener proveedor específico
     */
    public function get_provider($provider_key = null)
    {
        // Si no se especifica proveedor, usar el de la configuración
        if (empty($provider_key)) {
            $opts = get_option('dpuwoo_settings', []);
            $provider_key = $opts['api_provider'] ?? 'dolarapi';
        }
        
        // Validar que el proveedor exista
        if (!isset($this->providers[$provider_key])) {
            error_log("API Provider '{$provider_key}' no encontrado. Usando dolarapi");
            $provider_key = 'dolarapi';
        }
        
        return $this->providers[$provider_key];
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
     * Obtener tasa según proveedor (formato estandarizado)
     */
    public function get_rate($type = 'oficial', $provider_key = null)
    {
        $provider = $this->get_provider($provider_key);
        $result = $provider->get_rate($type);
        
        // Agregar información común si existe
        if ($result) {
            $result['store_currency'] = strtoupper(get_woocommerce_currency());
            $result['store_country'] = $this->get_store_country();
            $result['timestamp'] = current_time('mysql');
        }
        
        return $result;
    }
    
    /**
     * Obtener monedas según proveedor (formato estandarizado)
     */
    public function get_currencies($provider_key = null)
    {
        $provider = $this->get_provider($provider_key);
        $currencies = $provider->get_currencies();
        
        // Agregar información común a cada moneda
        if (is_array($currencies)) {
            foreach ($currencies as &$currency) {
                $currency['store_currency'] = strtoupper(get_woocommerce_currency());
                $currency['store_country'] = $this->get_store_country();
                $currency['timestamp'] = current_time('mysql');
            }
        }
        
        return $currencies;
    }
    
    /**
     * Probar conexión de proveedor específico
     */
    public function test_connection($provider_key = null)
    {
        $provider = $this->get_provider($provider_key);
        $result = $provider->test_connection();
        
        // Agregar información común
        if ($result) {
            $result['store_currency'] = strtoupper(get_woocommerce_currency());
            $result['store_country'] = $this->get_store_country();
        }
        
        return $result;
    }
    
    /**
     * Obtener código de país de la tienda
     */
    private function get_store_country()
    {
        $base_country = get_option('woocommerce_default_country', 'AR:AR');
        $country_parts = explode(':', $base_country);
        return strtolower($country_parts[0]);
    }
}