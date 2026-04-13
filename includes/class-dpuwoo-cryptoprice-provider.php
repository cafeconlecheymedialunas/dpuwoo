<?php
require_once 'class-dpuwoo-api-response-formatter.php';

class CryptoPrice_Provider extends Base_API_Provider {
    protected $base_url = 'https://api.coingecko.com/api/v3';
    protected $auth_header = null;

    private $symbol_mapping = [
        'BTC' => 'BTC',
        'ETH' => 'ETH',
        'BNB' => 'BNB',
        'XRP' => 'XRP',
        'USDT' => 'USDT',
        'SOL' => 'SOL',
        'ADA' => 'ADA',
        'DOGE' => 'DOGE',
        'DOT' => 'DOT',
        'MATIC' => 'MATIC',
        'LTC' => 'LTC',
        'AVAX' => 'AVAX',
        'LINK' => 'LINK',
        'UNI' => 'UNI',
        'ATOM' => 'ATOM',
        'XLM' => 'XLM',
        'ALGO' => 'ALGO',
        'FIL' => 'FIL',
        'TRX' => 'TRX',
        'ETC' => 'ETC',
        'XMR' => 'XMR',
        'NEAR' => 'NEAR',
        'AR' => 'AR',
        'LDO' => 'LDO',
        'QNT' => 'QNT',
        'GRT' => 'GRT',
        'RNDR' => 'RNDR',
        'OP' => 'OP',
        'ARB' => 'ARB',
        'INJ' => 'INJ',
        'SUI' => 'SUI',
        'SEI' => 'SEI',
        'TIA' => 'TIA',
        'PEPE' => 'PEPE',
        'SHIB' => 'SHIB',
        'WIF' => 'WIF',
        'BONK' => 'BONK',
        'FET' => 'FET',
        'GALA' => 'GALA',
        'IMX' => 'IMX',
        'APT' => 'APT',
        'STX' => 'STX',
        'RUNE' => 'RUNE',
        'KAVA' => 'KAVA',
        'FTM' => 'FTM',
        'CAKE' => 'CAKE',
        'MINA' => 'MINA',
        'ROSE' => 'ROSE',
        'ZIL' => 'ZIL',
        'ONE' => 'ONE',
        'CELO' => 'CELO',
        'QTUM' => 'QTUM',
        'NEO' => 'NEO',
        'EOS' => 'EOS',
        'XTZ' => 'XTZ',
        'FLOW' => 'FLOW',
        'HBAR' => 'HBAR',
        'THETA' => 'THETA'
    ];
    
    private $currency_code_mapping = [
        'USD' => 'usd',
        'EUR' => 'eur',
        'GBP' => 'gbp',
        'JPY' => 'jpy',
        'ARS' => 'ars',
        'BRL' => 'brl',
        'CLP' => 'clp',
        'MXN' => 'mxn',
        'COP' => 'cop',
        'UYU' => 'uyu',
        'PEN' => 'pen',
        'VES' => 'ves'
    ];

    public function get_rate($type) {
        $store_currency = $this->get_store_currency();
        $currency = $this->get_currency_code($store_currency);
        
        $coin_id = $this->get_coin_id($type);
        
        $url = $this->build_url("/simple/price?ids={$coin_id}&vs_currencies={$currency}");
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'][$coin_id][$currency])) {
            return false;
        }
        
        $price = floatval($response['data'][$coin_id][$currency]);
        
        $formatted_data = [
            'value' => $price,
            'buy' => $price,
            'sell' => $price,
            'updated' => current_time('mysql'),
            'raw' => $response,
            'provider' => 'cryptoprice',
            'base_currency' => $symbol,
            'target_currency' => $store_currency,
            'pair' => $store_currency . '_' . $symbol,
            'type' => $type,
            'api_code' => $symbol,
            'currency' => $symbol
        ];
        
        return API_Response_Formatter::create_rate_response($formatted_data);
    }
    
    protected function build_url($endpoint) {
        return $this->base_url . $endpoint;
    }
    
    public function get_currencies() {
        $store_currency = $this->get_store_currency();
        $currency = $this->get_currency_code($store_currency);
        
        $url = $this->build_url("/coins/markets?vs_currency={$currency}&order=market_cap_desc&per_page=100&page=1");
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $all_currencies = [];
        
        foreach ($response['data'] as $coin) {
            if (!isset($coin['symbol'], $coin['current_price'])) continue;
            
            $symbol = strtoupper($coin['symbol']);
            $formatted_data = [
                'type' => 'crypto',
                'code' => $symbol,
                'key' => $coin['id'],
                'name' => $coin['name'] ?? $symbol,
                'value' => floatval($coin['current_price']),
                'buy' => floatval($coin['current_price']),
                'sell' => floatval($coin['current_price']),
                'updated' => current_time('mysql'),
                'raw' => $coin,
                'provider' => 'cryptoprice',
                'base_currency' => $symbol,
                'target_currency' => $store_currency,
                'category' => 'crypto',
                'api_code' => $coin['id'],
                'currency' => $symbol
            ];
            
            $all_currencies[] = API_Response_Formatter::create_currency_response($formatted_data);
        }
        
        return $all_currencies;
    }
    
    public function test_connection() {
        $url = $this->build_url('/simple/price?ids=bitcoin&vs_currencies=usd');
        $response = $this->make_request($url);
        
        if ($response && isset($response['data']['bitcoin']['usd'])) {
            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'value' => $response['data']['bitcoin']['usd']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Error de conexión'
        ];
    }
    
    private function get_symbol($symbol) {
        $symbol = strtoupper($symbol);
        return $this->symbol_mapping[$symbol] ?? $symbol;
    }
    
    private function get_currency_code($currency) {
        $currency = strtoupper($currency);
        return $this->currency_code_mapping[$currency] ?? 'usd';
    }
    
    private function get_coin_id($symbol) {
        $symbol = strtoupper($symbol);
        return $this->symbol_mapping[$symbol] ?? strtolower($symbol);
    }
}