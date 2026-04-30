<?php

class Jsdelivr_Provider extends Base_API_Provider {
    protected $base_url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1';
    protected $auth_header = null;

    protected function build_url($endpoint) {
        return $this->base_url . $endpoint;
    }

    public function get_rate($type) {
        $store_currency = $this->get_store_currency();
        $currency_lower = strtolower($store_currency);
        $type_lower = strtolower($type);
        
        // La API de jsdelivr devuelve: { "ars": { "usd": 0.000736, ... } }
        // donde 1 ARS = 0.000736 USD, entonces ARS/USD = 1/0.000736 ≈ 1358
        $url = $this->build_url("/currencies/{$type_lower}.json");
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'][$type_lower])) {
            return false;
        }
        
        $data = $response['data'][$type_lower];
        
        // Buscar el valor relativo a USD (la clave es "usd" en minúsculas)
        $usd_value = floatval($data['usd'] ?? 0);
        
        if ($usd_value <= 0) {
            return false;
        }
        
        // Invertir: si 1 ARS = 0.000736 USD, entonces 1 USD = 1/0.000736 ARS
        $value = 1 / $usd_value;
        
        $formatted_data = [
            'value' => $value,
            'buy' => $value,
            'sell' => $value,
            'updated' => current_time('mysql'),
            'raw' => $response,
            'provider' => 'jsdelivr',
            'base_currency' => $store_currency,
            'target_currency' => 'USD',
            'pair' => $store_currency . '_USD',
            'type' => $type,
            'api_code' => $type,
            'currency' => strtoupper($type)
        ];
        
        return API_Response_Formatter::create_rate_response($formatted_data);
    }
    
    public function get_currencies() {
        $url = $this->build_url('/currencies/usd.json');
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data']['usd'])) {
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        $all_currencies = [];
        
        foreach ($response['data']['usd'] as $code => $value) {
            if (!is_numeric($value)) continue;
            
            $formatted_data = [
                'type' => 'fiat',
                'code' => $code,
                'key' => strtolower($code),
                'name' => $this->get_currency_name($code),
                'value' => floatval($value),
                'buy' => floatval($value),
                'sell' => floatval($value),
                'updated' => current_time('mysql'),
                'raw' => null,
                'provider' => 'jsdelivr',
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
        $url = $this->build_url('/currencies/usd.json');
        $response = $this->make_request($url);
        
        if ($response && isset($response['data']['usd']['ars'])) {
            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'value' => $response['data']['usd']['ars']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Error de conexión'
        ];
    }
    
    private function get_currency_name($code) {
        $names = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen',
            'ARS' => 'Argentine Peso',
            'BRL' => 'Brazilian Real',
            'CLP' => 'Chilean Peso',
            'MXN' => 'Mexican Peso',
            'COP' => 'Colombian Peso',
            'UYU' => 'Uruguayan Peso',
            'BOB' => 'Bolivian Bolíviano',
            'VES' => 'Venezuelan Bolívar',
            'PEN' => 'Peruvian Sol',
            'CRC' => 'Costa Rican Colón',
            'GTQ' => 'Guatemalan Quetzal',
            'HNL' => 'Honduran Lempira',
            'NIO' => 'Nicaraguan Córdoba',
            'PAB' => 'Panamanian Balboa',
            'DOP' => 'Dominican Peso',
            'CUP' => 'Cuban Peso',
            'JAM' => 'Jamaican Dollar',
            'TTD' => 'Trinidad Dollar',
            'BBD' => 'Barbadian Dollar',
            'BSD' => 'Bahamian Dollar',
            'BZD' => 'Belize Dollar',
            'GYD' => 'Guyanese Dollar',
            'SRD' => 'Surinamese Dollar',
            'AWG' => 'Aruban Florin',
            'ANG' => 'Netherlands Antillean Guilder',
            'XCD' => 'East Caribbean Dollar',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'NZD' => 'New Zealand Dollar',
            'CHF' => 'Swiss Franc',
            'CNY' => 'Chinese Yuan',
            'INR' => 'Indian Rupee',
            'RUB' => 'Russian Ruble',
            'KRW' => 'South Korean Won',
            'SGD' => 'Singapore Dollar',
            'HKD' => 'Hong Kong Dollar',
            'SEK' => 'Swedish Krona',
            'NOK' => 'Norwegian Krone',
            'DKK' => 'Danish Krone',
            'PLN' => 'Polish Zloty',
            'TRY' => 'Turkish Lira',
            'ZAR' => 'South African Rand',
            'AED' => 'UAE Dirham',
            'SAR' => 'Saudi Riyal',
            'ILS' => 'Israeli Shekel',
            'THB' => 'Thai Baht',
            'MYR' => 'Malaysian Ringgit',
            'IDR' => 'Indonesian Rupiah',
            'PHP' => 'Philippine Peso',
            'VND' => 'Vietnamese Dong',
            'TWD' => 'Taiwan Dollar',
            'KWD' => 'Kuwaiti Dinar',
            'BHD' => 'Bahraini Dinar',
            'OMR' => 'Omani Rial',
            'QAR' => 'Qatari Riyal'
        ];
        
        return $names[$code] ?? $code;
    }
}