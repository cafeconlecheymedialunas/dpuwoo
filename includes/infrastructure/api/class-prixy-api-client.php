<?php
if (!defined('ABSPATH')) exit;

// Cliente principal
class API_Client
{
    protected static $instance;

    /** @var array<string, API_Provider_Interface> Cache de providers instanciados */
    protected array $providers = [];

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

    public function __construct() {}

    /**
     * Obtiene el provider activo usando API_Provider_Factory.
     * Reemplaza el array hardcodeado por una Factory extensible.
     *
     * @param string|null $provider_key Clave del provider; null = leer de settings.
     * @return API_Provider_Interface
     */
    public function get_provider(?string $provider_key = null): API_Provider_Interface
    {
        if (empty($provider_key)) {
            $opts         = get_option('dpuwoo_settings', []);
            $provider_key = $opts['api_provider'] ?? 'dolarapi';
        }

        // Cache por clave para evitar reinstanciar en el mismo request
        if (!isset($this->providers[$provider_key])) {
            try {
                $this->providers[$provider_key] = API_Provider_Factory::create($provider_key);
            } catch (\InvalidArgumentException $e) {
                // Fallback al provider por defecto
                $this->providers[$provider_key] = API_Provider_Factory::create('dolarapi');
            }
        }

        return $this->providers[$provider_key];
    }
    
    /**
     * Obtener todos los proveedores disponibles
     */
    public static function get_available_providers()
    {
        return [
            'dolarapi' => [
                'name' => 'DolarAPI.com',
                'description' => 'API de cotizaciones fiat para Latam (AR, CL, UY, BR, MX, CO, PE)',
                'domain' => 'dolarapi.com',
                'url' => 'https://dolarapi.com/v1',
                'requires_key' => false,
                'types' => ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta', 'mayorista', 'cripto', 'mep', 'solidario'],
                'supports_currencies' => false,
                'currency_endpoint' => null,
                'countries' => ['ar', 'cl', 'uy', 'br', 'mx', 'co', 'pe']
            ],
            'jsdelivr' => [
                'name' => 'Jsdelivr Currency API',
                'description' => 'API gratuita - 170+ monedas fiat mundiales (sin API Key)',
                'url' => 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api',
                'requires_key' => false,
                'types' => ['usd', 'eur', 'gbp', 'jpy', 'ars', 'brl', 'clp', 'mxn', 'cop', 'uyu', 'bob', 'ves', 'pen'],
                'supports_currencies' => true,
                'currency_endpoint' => 'currencies',
                'countries' => 'all'
            ],
            'cryptoprice' => [
                'name' => 'Crypto Price API',
                'description' => 'API gratuita de criptomonedas - 150+ cryptos (sin API Key)',
                'url' => 'https://crypto-price-api-three.vercel.app/api',
                'requires_key' => false,
                'types' => ['BTC', 'ETH', 'BNB', 'SOL', 'ADA', 'DOGE', 'XRP', 'DOT', 'MATIC', 'AVAX', 'LINK', 'UNI', 'ATOM', 'LTC', 'TRX', 'NEAR', 'FIL', 'ALGO', 'XLM', 'ETC', 'XMR', 'SHIB', 'WIF', 'BONK', 'PEPE', 'FET', 'GALA', 'IMX', 'APT', 'STX', 'KAVA', 'FTM', 'CAKE', 'ZIL', 'ONE', 'EOS', 'XTZ'],
                'supports_currencies' => true,
                'currency_endpoint' => 'prices',
                'categories' => ['crypto'],
                'countries' => 'all'
            ],
            'currencyapi' => [
                'name' => 'CurrencyAPI.com',
                'description' => 'API con soporte para 170+ monedas globales (requiere API Key)',
                'url' => 'https://currencyapi.com/',
                'requires_key' => true,
                'types' => ['latest', 'historical'],
                'supports_currencies' => true,
                'currency_endpoint' => 'currencies'
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
            $result['store_currency'] = strtoupper(\Dpuwoo\Helpers\prixy_get_store_currency());
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
        $provider_key = $provider_key ?? 'dolarapi';
        $transient_key = 'dpuwoo_currencies_' . $provider_key;
        
        // Verificar cache
        $cached = get_transient($transient_key);
        if (false !== $cached) {
            return $cached;
        }
        
        $provider = $this->get_provider($provider_key);
        $currencies = $provider->get_currencies();
        
        // Normalizar formato usando array maestro
        if (is_array($currencies)) {
            $currencies = $this->resolve_all_currencies($currencies);
            
            // Agregar información común
            foreach ($currencies as &$currency) {
                $currency['store_currency'] = strtoupper(\Dpuwoo\Helpers\prixy_get_store_currency());
                $currency['store_country'] = $this->get_store_country();
                $currency['timestamp'] = current_time('mysql');
            }
            
            // Guardar en cache por 1 hora
            set_transient($transient_key, $currencies, HOUR_IN_SECONDS);
        }
        
        return $currencies;
    }
    
    /**
     * Limpiar cache de monedas
     */
    public function clear_currencies_cache($provider_key = null)
    {
        if ($provider_key) {
            delete_transient('dpuwoo_currencies_' . $provider_key);
        } else {
            // Limpiar todos los caches de currencies
            global $wpdb;
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_prixy_currencies_%'");
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_prixy_currencies_%'");
        }
    }
    
    /**
     * Normalizar código de moneda (quitar prefijos como ARS_DOLAR_)
     */
    private function normalize_currency_code($code) {
        // DolarAPI usa formatos como "ARS_DOLAR_OFICIAL", "ARS_DOLAR_BLUE"
        if (strpos($code, '_DOLAR_') !== false) {
            $parts = explode('_DOLAR_', $code);
            return strtolower($parts[1]); // oficial, blue, bolsa, etc.
        }
        
        // Si tiene guión bajo, tomar la última parte o limpiar
        if (strpos($code, '_') !== false) {
            return strtolower(end(explode('_', $code)));
        }
        
        return $code;
    }
    
    /**
     * Detectar categoría de la moneda
     */
    private function detect_category($code, $existing_category) {
        if (!empty($existing_category) && $existing_category !== 'fiat') {
            return $existing_category;
        }
        
        // Detectar crypto por el código
        $cryptos = ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'USDT', 'ADA', 'DOGE', 'DOT', 'MATIC', 'LTC', 'AVAX', 'LINK', 'UNI', 'ATOM', 'XLM', 'TRX', 'NEAR', 'FIL', 'ALGO', 'SHIB', 'PEPE'];
        
        if (in_array(strtoupper($code), $cryptos)) {
            return 'crypto';
        }
        
        // DolarAPI tipos de dólar
        $dollar_types = ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta', 'mayorista', 'cripto', 'mep', 'solidario'];
        if (in_array(strtolower($code), $dollar_types)) {
            return 'dollar_types';
        }
        
        return 'fiat';
    }
    
/**
     * Obtener nombre legible de la moneda
     */
    private function get_currency_name($code, $existing_name = '') {
        if (!empty($existing_name)) return $existing_name;
        
        $names = [
            // Dollar types (DolarAPI)
            'OFICIAL' => 'Dólar Oficial', 'BLUE' => 'Dólar Blue', 'BOLSA' => 'Dólar Bolsa',
            'CONTADOCONLIQUI' => 'Contado con Liquidación', 'MAYORISTA' => 'Dólar Mayorista',
            'CRIPTO' => 'Dólar Cripto', 'TARJETA' => 'Dólar Tarjeta', 'MEP' => 'Dólar MEP',
            'SOLIDARIO' => 'Dólar Solidario',
            // Fiat
            'USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen', 'ARS' => 'Peso Argentino', 'BRL' => 'Real Brasileño',
            'CLP' => 'Peso Chileno', 'MXN' => 'Peso Mexicano', 'COP' => 'Peso Colombiano',
            'UYU' => 'Peso Uruguayo', 'PEN' => 'Sol Peruano', 'VES' => 'Bolívar Venezolano',
            'BOB' => 'Boliviano Boliviano', 'BTC' => 'Bitcoin', 'ETH' => 'Ethereum',
            'SOL' => 'Solana', 'BNB' => 'Binance Coin', 'XRP' => 'XRP', 'ADA' => 'Cardano',
            'DOGE' => 'Dogecoin', 'DOT' => 'Polkadot', 'MATIC' => 'Polygon',
            'AVAX' => 'Avalanche', 'LINK' => 'Chainlink', 'UNI' => 'Uniswap',
            'ATOM' => 'Cosmos', 'LTC' => 'Litecoin', 'TRX' => 'TRON',
            'NEAR' => 'NEAR Protocol', 'FIL' => 'Filecoin', 'ALGO' => 'Algorand',
            'XLM' => 'Stellar', 'ETC' => 'Ethereum Classic', 'XMR' => 'Monero',
            'SHIB' => 'Shiba Inu', 'PEPE' => 'Pepe', 'FET' => 'Fetch AI',
            'GALA' => 'Gala', 'APT' => 'Aptos', 'STX' => 'Stacks',
            'KAVA' => 'Kava', 'FTM' => 'Fantom', 'CAKE' => 'PancakeSwap',
            'ZIL' => 'Zilliqa', 'ONE' => 'Harmony', 'EOS' => 'EOS',
            'XTZ' => 'Tezos', 'FLOW' => 'Flow', 'HBAR' => 'Hedera',
            'THETA' => 'Theta', 'CAD' => 'Canadian Dollar', 'AUD' => 'Australian Dollar',
            'NZD' => 'New Zealand Dollar', 'CHF' => 'Swiss Franc', 'CNY' => 'Chinese Yuan',
            'INR' => 'Indian Rupee', 'KRW' => 'South Korean Won', 'SGD' => 'Singapore Dollar',
            'HKD' => 'Hong Kong Dollar', 'SEK' => 'Swedish Krona', 'NOK' => 'Norwegian Krone',
            'DKK' => 'Danish Krone', 'PLN' => 'Polish Zloty', 'TRY' => 'Turkish Lira',
            'ZAR' => 'South African Rand', 'RUB' => 'Russian Ruble'
        ];
        
        return $names[$code] ?? $code;
    }
    
    /**
     * Obtener símbolo de la moneda (vacío si no existe)
     */
    private function get_currency_symbol($code) {
        $symbols = [
            // Dollar types (DolarAPI) - todos usan $
            'OFICIAL' => '$', 'BLUE' => '$', 'BOLSA' => '$', 'CONTADOCONLIQUI' => '$',
            'MAYORISTA' => '$', 'CRIPTO' => '$', 'TARJETA' => '$', 'MEP' => '$', 'SOLIDARIO' => '$',
            // Fiat
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'ARS' => '$',
            'BRL' => 'R$', 'CLP' => '$', 'MXN' => '$', 'COP' => '$', 'UYU' => '$U',
            'PEN' => 'S/', 'VES' => 'Bs', 'BOB' => 'Bs.', 'BTC' => '₿', 'ETH' => 'Ξ',
            'SOL' => '◎', 'BNB' => '◎', 'XRP' => '✕', 'ADA' => '₳', 'DOGE' => 'Ð',
            'DOT' => '●', 'MATIC' => '⬡', 'AVAX' => '▲', 'LINK' => '⬡', 'UNI' => '🦄',
            'ATOM' => '⚛', 'LTC' => 'Ł', 'TRX' => '✳', 'NEAR' => 'Near', 'FIL' => '⨎',
            'ALGO' => 'Algo', 'XLM' => '✦', 'ETC' => 'Ξ', 'XMR' => 'ɱ', 'SHIB' => 'Shib',
            'PEPE' => 'Pepe', 'FET' => 'FET', 'GALA' => 'GALA', 'APT' => 'APT', 'STX' => 'STX',
            'KAVA' => 'KAVA', 'FTM' => 'FTM', 'CAKE' => 'CAKE', 'ZIL' => 'ZIL', 'ONE' => 'ONE',
            'EOS' => 'EOS', 'XTZ' => 'XTZ', 'FLOW' => 'FLOW', 'HBAR' => 'ħ', 'THETA' => 'Θ',
            'CAD' => 'C$', 'AUD' => 'A$', 'NZD' => 'NZ$', 'CHF' => 'Fr', 'CNY' => '¥',
            'INR' => '₹', 'KRW' => '₩', 'SGD' => 'S$', 'HKD' => 'HK$', 'SEK' => 'kr',
            'NOK' => 'kr', 'DKK' => 'kr', 'PLN' => 'zł', 'TRY' => '₺', 'ZAR' => 'R',
            'RUB' => '₽'
        ];
        
return $symbols[$code] ?? '';
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
            $result['store_currency'] = strtoupper(\Dpuwoo\Helpers\prixy_get_store_currency());
            $result['store_country'] = $this->get_store_country();
        }
        
        return $result;
    }
    
    /**
     * Probar conexión con API específica y API key proporcionada
     */
    public function test_specific_api(string $api_type, string $api_key): array
    {
        try {
            // Crear provider temporal con la API key proporcionada
            $provider = API_Provider_Factory::create($api_type, $api_key);
            $result = $provider->test_connection();
            
            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con ' . $api_type
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error de conexión'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener tasas de cambio desde la API
     */
    public function get_rates(string $api_type, string $base_currency = 'USD'): array
    {
        try {
            $opts = get_option('dpuwoo_settings', []);
            
            // Usar API key correspondiente si existe
            $api_key = '';
            if ($api_type === 'currencyapi') {
                $api_key = $opts['currencyapi_api_key'] ?? '';
            } elseif ($api_type === 'exchangerate') {
                $api_key = $opts['exchangerate_api_key'] ?? '';
            }
            
            $provider = API_Provider_Factory::create($api_type, $api_key);
            $rates = $provider->get_currencies();
            
            // Convertir al formato de tasas
            $formatted_rates = [];
            if (is_array($rates)) {
                foreach ($rates as $rate) {
                    if (isset($rate['code']) && isset($rate['value'])) {
                        $formatted_rates[$rate['code']] = $rate['value'];
                    }
                }
            }
            
            return [
                'success' => true,
                'rates' => $formatted_rates,
                'base' => $base_currency
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener código de país de la tienda
     */
    public function get_store_country()
    {
        $base_country = get_option('woocommerce_default_country', 'AR:AR');
        $country_parts = explode(':', $base_country);
        return strtolower($country_parts[0]);
    }
    
    /**
     * Array maestro de todas las monedas soportadas
     * Fuente: DolarAPI, Jsdelivr, CoinGecko
     */
    public function get_master_currencies(): array {
        return [
            // === DOLLAR TYPES ===
            'DOLAR_OFICIAL' => ['name' => 'Dólar Oficial', 'symbol' => '$'],
            'DOLAR_BLUE' => ['name' => 'Dólar Blue', 'symbol' => '$'],
            'DOLAR_BOLSA' => ['name' => 'Dólar Bolsa', 'symbol' => '$'],
            'DOLAR_CONTADOCONLIQUI' => ['name' => 'Dólar Contado con Liquidación', 'symbol' => '$'],
            'DOLAR_MAYORISTA' => ['name' => 'Dólar Mayorista', 'symbol' => '$'],
            'DOLAR_CRIPTO' => ['name' => 'Dólar Cripto', 'symbol' => '$'],
            'DOLAR_TARJETA' => ['name' => 'Dólar Tarjeta', 'symbol' => '$'],
            
            // === FIAT ===
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'BRL' => ['name' => 'Real Brasileño', 'symbol' => 'R$'],
            'CLP' => ['name' => 'Peso Chileno', 'symbol' => '$'],
            'UYU' => ['name' => 'Peso Uruguayo', 'symbol' => '$U'],
            'ARS' => ['name' => 'Peso Argentino', 'symbol' => '$'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
            'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$'],
            'COP' => ['name' => 'Colombian Peso', 'symbol' => '$'],
            'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'S/'],
            'VES' => ['name' => 'Venezuelan Bolívar', 'symbol' => 'Bs'],
            'BOB' => ['name' => 'Bolivian Boliviano', 'symbol' => 'Bs.'],
            'CRC' => ['name' => 'Costa Rican Colón', 'symbol' => '₡'],
            'GTQ' => ['name' => 'Guatemalan Quetzal', 'symbol' => 'Q'],
            'HNL' => ['name' => 'Honduran Lempira', 'symbol' => 'L'],
            'NIO' => ['name' => 'Nicaraguan Córdoba', 'symbol' => 'C$'],
            'PAB' => ['name' => 'Panamanian Balboa', 'symbol' => 'B/.'],
            'DOP' => ['name' => 'Dominican Peso', 'symbol' => 'RD$'],
            'CUP' => ['name' => 'Cuban Peso', 'symbol' => '₱'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
            'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
            'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'Fr'],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
            'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽'],
            'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩'],
            'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
            'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
            'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
            'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
            'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
            'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł'],
            'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
            'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼'],
            'ILS' => ['name' => 'Israeli Shekel', 'symbol' => '₪'],
            'THB' => ['name' => 'Thai Baht', 'symbol' => '฿'],
            'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
            'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
            'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
            'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫'],
            'TWD' => ['name' => 'Taiwan Dollar', 'symbol' => 'NT$'],
            'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك'],
            'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => '.د.ب'],
            'OMR' => ['name' => 'Omani Rial', 'symbol' => 'ر.ع.'],
            'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'ر.ق'],
            
            // === CRYPTO ===
            'BTC' => ['name' => 'Bitcoin', 'symbol' => '₿'],
            'ETH' => ['name' => 'Ethereum', 'symbol' => 'Ξ'],
            'USDT' => ['name' => 'Tether', 'symbol' => '₮'],
            'XRP' => ['name' => 'XRP', 'symbol' => '✕'],
            'BNB' => ['name' => 'BNB', 'symbol' => '◎'],
            'USDC' => ['name' => 'USD Coin', 'symbol' => '$'],
            'SOL' => ['name' => 'Solana', 'symbol' => '◎'],
            'TRX' => ['name' => 'TRON', 'symbol' => '✳'],
            'DOGE' => ['name' => 'Dogecoin', 'symbol' => 'Ð'],
            'ADA' => ['name' => 'Cardano', 'symbol' => '₳'],
            'BCH' => ['name' => 'Bitcoin Cash', 'symbol' => '₿'],
            'LINK' => ['name' => 'Chainlink', 'symbol' => '⬡'],
            'XMR' => ['name' => 'Monero', 'symbol' => 'ɱ'],
            'ZEC' => ['name' => 'Zcash', 'symbol' => 'Z'],
            'XLM' => ['name' => 'Stellar', 'symbol' => '✦'],
            'DAI' => ['name' => 'Dai', 'symbol' => '◈'],
            'LTC' => ['name' => 'Litecoin', 'symbol' => 'Ł'],
            'AVAX' => ['name' => 'Avalanche', 'symbol' => '▲'],
            'HBAR' => ['name' => 'Hedera', 'symbol' => 'ħ'],
            'TON' => ['name' => 'Toncoin', 'symbol' => '◆'],
            'SUI' => ['name' => 'Sui', 'symbol' => 'SUI'],
            'SHIB' => ['name' => 'Shiba Inu', 'symbol' => 'Shib'],
            'CRO' => ['name' => 'Cronos', 'symbol' => 'CRO'],
            'DOT' => ['name' => 'Polkadot', 'symbol' => '●'],
            'UNI' => ['name' => 'Uniswap', 'symbol' => '🦄'],
            'NEAR' => ['name' => 'NEAR Protocol', 'symbol' => 'NEAR'],
            'OKB' => ['name' => 'OKB', 'symbol' => 'OKB'],
            'PI' => ['name' => 'Pi Network', 'symbol' => 'PI'],
            'FIL' => ['name' => 'Filecoin', 'symbol' => '⨎'],
            'APT' => ['name' => 'Aptos', 'symbol' => 'APT'],
            'AR' => ['name' => 'Arweave', 'symbol' => 'AR'],
            'ALGO' => ['name' => 'Algorand', 'symbol' => 'Algo'],
            'VET' => ['name' => 'VeChain', 'symbol' => 'V'],
            'ICP' => ['name' => 'Internet Computer', 'symbol' => 'ICP'],
            'THETA' => ['name' => 'Theta', 'symbol' => 'Θ'],
            'AAVE' => ['name' => 'Aave', 'symbol' => 'AAVE'],
            'GRT' => ['name' => 'The Graph', 'symbol' => 'GRT'],
            'MKR' => ['name' => 'Maker', 'symbol' => 'MKR'],
            'SNX' => ['name' => 'Synthetix', 'symbol' => 'SNX'],
            'IMX' => ['name' => 'Immutable', 'symbol' => 'IMX'],
            'STX' => ['name' => 'Stacks', 'symbol' => 'STX'],
            'RUNE' => ['name' => 'THORchain', 'symbol' => 'RUNE'],
            'KAVA' => ['name' => 'Kava', 'symbol' => 'KAVA'],
            'FTM' => ['name' => 'Fantom', 'symbol' => 'FTM'],
            'SAND' => ['name' => 'The Sandbox', 'symbol' => 'SAND'],
            'GALA' => ['name' => 'Gala', 'symbol' => 'GALA'],
            'MANA' => ['name' => 'Decentraland', 'symbol' => 'MANA'],
            'AXS' => ['name' => 'Axie Infinity', 'symbol' => 'AXS'],
            'APE' => ['name' => 'ApeCoin', 'symbol' => 'APE'],
            'PEPE' => ['name' => 'Pepe', 'symbol' => 'Pepe'],
            'WIF' => ['name' => 'WIF', 'symbol' => 'WIF'],
            'BONK' => ['name' => 'Bonk', 'symbol' => 'BONK'],
            'FET' => ['name' => 'Fetch.ai', 'symbol' => 'FET'],
            'RNDR' => ['name' => 'Render', 'symbol' => 'RNDR'],
            'INJ' => ['name' => 'Injective', 'symbol' => 'INJ'],
            'SEI' => ['name' => 'Sei', 'symbol' => 'SEI'],
        ];
    }
    
    /**
     * Resolver moneda por código
     */
    public function resolve_currency(string $code): ?array {
        $master = $this->get_master_currencies();
        $code_upper = strtoupper($code);
        
        if (isset($master[$code_upper])) {
            $data = $master[$code_upper];
            $data['code'] = $code_upper;
            $data['display'] = !empty($data['symbol']) 
                ? $data['name'] . ' (' . $data['symbol'] . ')' 
                : $data['name'];
            return $data;
        }
        
        if (strpos($code_upper, 'DOLAR_') !== false) {
            $dollar_type = strtoupper(str_replace(['ARS_', 'CLP_', 'UYU_', 'BRL_', 'MXN_', 'COP_', 'PEN_', 'BOB_'], '', $code_upper));
            $lookup = 'DOLAR_' . $dollar_type;
            if (isset($master[$lookup])) {
                $data = $master[$lookup];
                $data['code'] = $lookup;
                $data['variant'] = strtolower($dollar_type);
                $data['display'] = !empty($data['symbol']) 
                    ? $data['name'] . ' (' . $data['symbol'] . ')' 
                    : $data['name'];
                return $data;
            }
        }
        
        return null;
    }
    
    /**
     * Resolver todas las monedas de un provider
     */
    public function resolve_all_currencies(array $currencies): array {
        $resolved = [];
        
        foreach ($currencies as $currency) {
            $raw_code = $currency['code'] ?? $currency['key'] ?? '';
            if (empty($raw_code)) continue;
            
            $master_data = $this->resolve_currency($raw_code);
            
            if ($master_data) {
                $resolved[] = array_merge($currency, [
                    'code' => $master_data['code'],
                    'name' => $master_data['name'],
                    'symbol' => $master_data['symbol'],
                    'display' => $master_data['display'],
                ]);
            } else {
                $currency['code'] = strtoupper($raw_code);
                $currency['name'] = $currency['name'] ?? $raw_code;
                $resolved[] = $currency;
            }
        }
        
        return $resolved;
    }
}
