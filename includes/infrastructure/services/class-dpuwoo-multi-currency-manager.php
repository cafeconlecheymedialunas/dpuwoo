<?php
if (!defined('ABSPATH')) exit;

/**
 * MultiCurrency_Manager
 * Gestiona el sistema multi-moneda que ignora cambios manuales 
 * y usa logs históricos como fuente de verdad
 */
class MultiCurrency_Manager
{
    protected static $instance;
    
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
        // Constructor privado para singleton
    }
    
    /**
     * Verifica si un producto tiene precio original en USD establecido
     * @param int $product_id ID del producto
     * @return bool
     */
    public function has_original_price_usd($product_id)
    {
        $original_price = get_post_meta($product_id, '_dpuwoo_original_price_usd', true);
        return !empty($original_price) && floatval($original_price) > 0;
    }
    
    /**
     * Obtiene el precio original en USD de un producto
     * @param int $product_id ID del producto
     * @return float|null Precio original o null si no existe
     */
    public function get_original_price_usd($product_id)
    {
        $original_price = get_post_meta($product_id, '_dpuwoo_original_price_usd', true);
        return (!empty($original_price) && floatval($original_price) > 0) ? floatval($original_price) : null;
    }
    
    /**
     * Establece el precio original en USD para un producto
     * @param int $product_id ID del producto
     * @param float $usd_price Precio en USD
     * @return bool Éxito de la operación
     */
    public function set_original_price_usd($product_id, $usd_price)
    {
        if (floatval($usd_price) <= 0) {
            return false;
        }
        
        $result = update_post_meta($product_id, '_dpuwoo_original_price_usd', floatval($usd_price));
        update_post_meta($product_id, '_dpuwoo_baseline_date', current_time('mysql'));
        
        return $result !== false;
    }
    
    /**
     * Calcula nuevo precio usando el precio original en USD
     * @param int $product_id ID del producto
     * @param float $usd_price Precio original en USD
     * @param float $current_rate Tasa de cambio actual
     * @return float Nuevo precio calculado
     */
    public function calculate_price_from_usd($product_id, $usd_price, $current_rate)
    {
        if (floatval($usd_price) <= 0 || floatval($current_rate) <= 0) {
            // Fallback: usar precio actual si hay datos inválidos
            $product = wc_get_product($product_id);
            return floatval($product->get_regular_price());
        }
        
        return floatval($usd_price) * floatval($current_rate);
    }
    
    /**
     * Obtiene información del precio original de un producto
     * @param int $product_id ID del producto
     * @return array|null Información del precio original o null si no existe
     */
    public function get_original_price_info($product_id)
    {
        $usd_price = $this->get_original_price_usd($product_id);
        $established_date = get_post_meta($product_id, '_dpuwoo_baseline_date', true);
        
        if (!$usd_price) {
            return null;
        }
        
        return [
            'usd_price' => $usd_price,
            'established_date' => $established_date,
            'currency' => 'USD'
        ];
    }
    
    /**
     * Elimina todos los precios originales USD (para reiniciar el sistema)
     * @return int Número de productos afectados
     */
    public function reset_all_original_prices()
    {
        global $wpdb;
        
        // Eliminar metadata de productos
        $deleted_meta = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                '_dpuwoo_original_price_usd'
            )
        );
        
        // También eliminar fechas de baseline
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                '_dpuwoo_baseline_date'
            )
        );
        
        return intval($deleted_meta);
    }
}