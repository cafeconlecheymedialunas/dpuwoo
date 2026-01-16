<?php
if (!defined('ABSPATH')) exit;

class API_Client
{
    protected static $instance;
    protected $base = 'https://dolarapi.com/v1/dolares';

    public static function init()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    public static function get_available_providers()
    {
        return [
            'dolarapi' => [
                'name' => 'DolarAPI',
                'description' => 'API pública de cotizaciones del dólar en Argentina',
                'url' => 'https://dolarapi.com',
                'requires_key' => false,
                'types' => ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta']
            ],
            'custom' => [
                'name' => 'API Personalizada',
                'description' => 'Configurar endpoint personalizado',
                'url' => '',
                'requires_key' => true,
                'types' => []
            ]
        ];
    }

    public function get_rate($type = 'oficial')
    {
        $opts = get_option('dpuwoo_settings', []);
        $api_key = $opts['api_key'] ?? '';
        $url = rtrim($this->base, '/') . '/' . rawurlencode($type);

        $args = ['timeout' => 15];

        if (!empty($api_key)) {
            $args['headers'] = ['Authorization' => 'Bearer ' . trim($api_key), 'Accept' => 'application/json'];
        } else {
            $args['headers'] = ['Accept' => 'application/json'];
        }

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) return false;

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200 || empty($body)) return false;

        $data = json_decode($body, true);
        if (!$data || !is_array($data)) return false;

        if (isset($data['venta']) && is_numeric($data['venta'])) {
            return ['value' => floatval($data['venta']), 'raw' => $data];
        }

        foreach (array_values($data) as $v) {
            if (is_array($v) && isset($v['venta']) && is_numeric($v['venta'])) {
                return ['value' => floatval($v['venta']), 'raw' => $v];
            }
            if (is_numeric($v)) {
                return ['value' => floatval($v), 'raw' => $data];
            }
        }

        return false;
    }
}
