<?php
/**
 * Formateador de respuestas estandarizadas para todos los proveedores de API
 * Implementa el patrón Factory para crear objetos de respuesta consistentes
 */

class API_Response_Formatter {
    
    /**
     * Crear respuesta estandarizada para tasas de cambio individuales
     */
    public static function create_rate_response($data) {
        return [
            'value' => self::get_numeric_value($data, 'value', 0),
            'buy' => self::get_numeric_value($data, 'buy', 0),
            'sell' => self::get_numeric_value($data, 'sell', 0),
            'mid' => self::get_numeric_value($data, 'mid', 0),
            'updated' => self::get_updated_timestamp($data),
            'raw' => $data['raw'] ?? $data,
            'provider' => $data['provider'] ?? 'unknown',
            'base_currency' => strtoupper($data['base_currency'] ?? ''),
            'target_currency' => strtoupper($data['target_currency'] ?? ''),
            'pair' => $data['pair'] ?? '',
            'type' => $data['type'] ?? 'spot',
            'timestamp' => current_time('mysql'),
            'valid' => isset($data['value']) && $data['value'] > 0
        ];
    }
    
    /**
     * Crear respuesta estandarizada para listas de monedas
     */
    public static function create_currency_response($data) {
        return [
            'code' => strtoupper($data['code'] ?? ''),
            'name' => $data['name'] ?? '',
            'type' => $data['type'] ?? 'currency',
            'key' => $data['key'] ?? '',
            'value' => self::get_numeric_value($data, 'value', 0),
            'buy' => self::get_numeric_value($data, 'buy', 0),
            'sell' => self::get_numeric_value($data, 'sell', 0),
            'mid' => self::get_numeric_value($data, 'mid', 0),
            'updated' => self::get_updated_timestamp($data),
            'raw' => $data['raw'] ?? $data,
            'provider' => $data['provider'] ?? 'unknown',
            'base_currency' => strtoupper($data['base_currency'] ?? ''),
            'target_currency' => strtoupper($data['target_currency'] ?? ''),
            'pair' => $data['pair'] ?? '',
            'category' => $data['category'] ?? 'forex',
            'timestamp' => current_time('mysql'),
            'valid' => isset($data['value']) && $data['value'] > 0
        ];
    }
    
    /**
     * Crear respuesta estandarizada para conexión/test
     */
    public static function create_test_response($data) {
        return [
            'success' => (bool) ($data['success'] ?? false),
            'http_code' => (int) ($data['http_code'] ?? 0),
            'url' => $data['url'] ?? '',
            'message' => $data['message'] ?? '',
            'error' => $data['error'] ?? '',
            'response_time' => $data['response_time'] ?? 0,
            'timestamp' => current_time('mysql'),
            'provider' => $data['provider'] ?? 'unknown'
        ];
    }
    
    /**
     * Helper para obtener valores numéricos con fallback
     */
    private static function get_numeric_value($data, $key, $default = 0) {
        if (!isset($data[$key])) {
            return $default;
        }
        
        $value = $data[$key];
        
        // Si ya es número, devolverlo
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        // Si es string, intentar parsearlo
        if (is_string($value)) {
            // Manejar formatos como "1.234,56" o "1234.56"
            $cleaned = str_replace(['.', ','], ['', '.'], $value);
            return is_numeric($cleaned) ? floatval($cleaned) : $default;
        }
        
        return $default;
    }
    
    /**
     * Helper para obtener timestamp de actualización
     */
    private static function get_updated_timestamp($data) {
        $updated = $data['updated'] ?? $data['timestamp'] ?? $data['time_last_update_utc'] ?? '';
        
        if (empty($updated)) {
            return current_time('mysql');
        }
        
        // Si es timestamp ISO, convertirlo
        if (strtotime($updated)) {
            return date('Y-m-d H:i:s', strtotime($updated));
        }
        
        return $updated;
    }
    
    /**
     * Validar estructura de respuesta de tasa
     */
    public static function validate_rate_response($response) {
        $required_fields = ['value', 'provider', 'base_currency', 'target_currency'];
        foreach ($required_fields as $field) {
            if (!isset($response[$field])) {
                return false;
            }
        }
        return $response['value'] > 0;
    }
    
    /**
     * Validar estructura de respuesta de moneda
     */
    public static function validate_currency_response($response) {
        $required_fields = ['code', 'name', 'value', 'provider'];
        foreach ($required_fields as $field) {
            if (!isset($response[$field])) {
                return false;
            }
        }
        return $response['value'] > 0;
    }
}