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
     * Establece el precio base multi-moneda para un producto
     * Solo se ejecuta una vez por producto
     * @param int $product_id ID del producto
     * @param float $current_price Precio actual del producto
     * @param float $current_rate Tasa de cambio actual
     */
    public function establish_baseline($product_id, $current_price, $current_rate)
    {
        // Solo ejecutar una vez por producto
        if (get_post_meta($product_id, '_dpuwoo_base_price_reference', true)) {
            return; // Ya existe baseline
        }
        
        $opts = get_option('dpuwoo_settings', []);
        $reference_currency = $opts['reference_currency'] ?? 'USD';
        
        // Calcular precio base en moneda de referencia
        $base_price_reference = $current_price / $current_rate;
        
        // Guardar metadata
        update_post_meta($product_id, '_dpuwoo_base_price_reference', $base_price_reference);
        update_post_meta($product_id, '_dpuwoo_reference_currency', $reference_currency);
        update_post_meta($product_id, '_dpuwoo_baseline_rate', $current_rate);
        update_post_meta($product_id, '_dpuwoo_baseline_date', current_time('mysql'));
        
        error_log("DPUWoo: Established baseline for product {$product_id} - Base price: {$base_price_reference} {$reference_currency}");
    }
    
    /**
     * Calcula nuevo precio usando baseline multi-moneda
     * Ignora completamente el precio actual del producto
     * @param int $product_id ID del producto
     * @param float $new_rate Nueva tasa de cambio
     * @return float Nuevo precio calculado desde baseline
     */
    public function calculate_price_from_baseline($product_id, $new_rate)
    {
        // Obtener precio base en moneda de referencia
        $base_price = get_post_meta($product_id, '_dpuwoo_base_price_reference', true);
        $reference_currency = get_post_meta($product_id, '_dpuwoo_reference_currency', true);
        
        if ($base_price && $reference_currency) {
            // Aplicar nueva tasa para la moneda de referencia
            $new_price = $base_price * $new_rate;
            
            // Registrar en logs que se usó baseline
            $this->log_baseline_usage($product_id, $base_price, $reference_currency, $new_rate, $new_price);
            
            return $new_price;
        }
        
        // Fallback: usar precio actual si no hay baseline
        $product = wc_get_product($product_id);
        return floatval($product->get_regular_price());
    }
    
    /**
     * Verifica si un producto tiene baseline establecido
     * @param int $product_id ID del producto
     * @return bool
     */
    public function has_baseline($product_id)
    {
        return (bool) get_post_meta($product_id, '_dpuwoo_base_price_reference', true);
    }
    
    /**
     * Obtiene información del baseline de un producto
     * @param int $product_id ID del producto
     * @return array|null Información del baseline o null si no existe
     */
    public function get_baseline_info($product_id)
    {
        $base_price = get_post_meta($product_id, '_dpuwoo_base_price_reference', true);
        $currency = get_post_meta($product_id, '_dpuwoo_reference_currency', true);
        $baseline_rate = get_post_meta($product_id, '_dpuwoo_baseline_rate', true);
        $baseline_date = get_post_meta($product_id, '_dpuwoo_baseline_date', true);
        
        if (!$base_price || !$currency) {
            return null;
        }
        
        return [
            'base_price' => floatval($base_price),
            'currency' => $currency,
            'baseline_rate' => floatval($baseline_rate),
            'established_date' => $baseline_date
        ];
    }
    
    /**
     * Registra uso de baseline en logs del sistema
     */
    private function log_baseline_usage($product_id, $base_price, $currency, $rate_applied, $calculated_price)
    {
        error_log("DPUWoo: Used baseline for product {$product_id} - Base: {$base_price} {$currency}, Rate: {$rate_applied}, New Price: {$calculated_price}");
        
        // Registrar también en la tabla de logs del plugin
        $this->record_baseline_calculation($product_id, $base_price, $currency, $rate_applied, $calculated_price);
    }
    
    /**
     * Registra el cálculo de baseline en la base de datos
     */
    private function record_baseline_calculation($product_id, $base_price, $currency, $rate_applied, $calculated_price)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        
        // Crear tabla si no existe
        $this->ensure_baseline_table_exists();
        
        $wpdb->insert($table_name, [
            'product_id' => $product_id,
            'base_price' => $base_price,
            'currency' => $currency,
            'rate_applied' => $rate_applied,
            'calculated_price' => $calculated_price,
            'date_created' => current_time('mysql')
        ]);
    }
    
    /**
     * Asegura que la tabla de logs de baseline exista
     */
    private function ensure_baseline_table_exists()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            base_price decimal(10,4) NOT NULL,
            currency varchar(10) NOT NULL,
            rate_applied decimal(10,4) NOT NULL,
            calculated_price decimal(10,2) NOT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY date_created (date_created)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obtiene el historial de cálculos de baseline para un producto
     * @param int $product_id ID del producto
     * @param int $limit Límite de registros
     * @return array Historial de cálculos
     */
    public function get_baseline_history($product_id, $limit = 50)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        
        // Asegurar que la tabla exista
        $this->ensure_baseline_table_exists();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d ORDER BY date_created DESC LIMIT %d",
            $product_id,
            $limit
        ));
    }
    
    /**
     * Elimina todos los baselines (para reiniciar el sistema)
     * @return int Número de productos afectados
     */
    public function reset_all_baselines()
    {
        global $wpdb;
        
        // Eliminar metadata de productos
        $deleted_meta = $wpdb->query("
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key IN ('_dpuwoo_base_price_reference', '_dpuwoo_reference_currency', '_dpuwoo_baseline_rate', '_dpuwoo_baseline_date')
        ");
        
        // Eliminar tabla de logs
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        return $deleted_meta;
    }
}