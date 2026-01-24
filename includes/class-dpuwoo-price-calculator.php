<?php
if (!defined('ABSPATH')) exit;

class Price_Calculator
{
    protected static $instance;
    protected $rules = []; // Inicialización de rules

    public static function init()
    {
        return self::$instance ?? (self::$instance = new self());
    }

    public static function get_instance()
    {
        return self::init();
    }

    /*==============================================================
    =           API pública
    ==============================================================*/
    /**
     * Calcula los nuevos precios (regular y oferta) de un producto.
     * @param int $product_id ID del producto o variación.
     * @param float $current_dollar_value Tasa de dólar actual.
     * @param bool $simulate Si es una simulación.
     * @return array|false Resultado del cálculo o false en caso de error.
     */
    public function calculate_for_product($product_id, $current_dollar_value, $simulate = false)
    {
        // Resetear reglas para este cálculo
        $this->rules = []; 
        
        if (!function_exists('wc_get_product')) {
            return $this->error('woocommerce_not_available');
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return $this->error('invalid_product');
        }

        $opts = get_option('dpuwoo_settings', []);

        // Obtener el dólar anterior usando la nueva lógica centralizada
        $previous_dollar_value = $this->get_previous_dollar_value($simulate);
        if ($previous_dollar_value <= 0) {
            return $this->error('previous_dollar_missing');
        }

        if ($current_dollar_value <= 0) {
            return $this->error('invalid_current_dollar');
        }

        // Obtener precios actuales
        $current_regular_price = floatval($product->get_regular_price());
        $current_sale_price = floatval($product->get_sale_price()); 
        
        if ($current_regular_price <= 0) {
            return $this->error('invalid_current_price');
        }

        $base_price_usd = 0;
        if (function_exists('get_post_meta')) {
            $base_price_usd = floatval(get_post_meta($product_id, '_base_price_usd', true));
        }

        /*---------------------------------------------
        | 1. Cálculo del precio REGULAR
        ----------------------------------------------*/
        $ratio = $current_dollar_value / $previous_dollar_value;

        $new_regular_price = $this->apply_ratio($current_regular_price, $ratio);
        $new_regular_price = $this->apply_global_extra($new_regular_price, $opts);
        $new_regular_price = $this->apply_category_rules($new_regular_price, $product, $product_id);
        
        // Aplicar configuración de redondeo
        $new_regular_price = $this->apply_configured_rounding($new_regular_price, $opts);
        
        // Aplicar dirección de actualización
        $new_regular_price = $this->apply_update_direction($new_regular_price, $current_regular_price, $opts);
        
        $new_regular_price = round($new_regular_price, 2); 

        /*---------------------------------------------
        | 2. Cálculo del precio de OFERTA
        ----------------------------------------------*/
        $new_sale_price = null; // null: no hay cambio de oferta, 0.0: limpiar oferta
        
        // Solo calcular oferta si había una oferta activa y es menor que el regular
        if ($current_sale_price > 0 && $current_sale_price < $current_regular_price) {
            
            // Aplicar el ratio de cambio al precio de oferta anterior
            $calculated_new_sale_price = $this->apply_ratio($current_sale_price, $ratio, 'sale_price_ratio');
            
            // Aplicar reglas globales y de categoría (usando la misma lógica)
            $calculated_new_sale_price = $this->apply_global_rounding($calculated_new_sale_price, $opts, 'sale_price_rounding');

            $calculated_new_sale_price = round($calculated_new_sale_price, 2);
            
            // Válido: el nuevo precio de oferta debe ser menor que el nuevo precio regular
            if ($calculated_new_sale_price < $new_regular_price) {
                $new_sale_price = $calculated_new_sale_price;
            } else {
                // Si el nuevo precio de oferta calculado supera o iguala al regular, lo eliminamos.
                $new_sale_price = 0.0; 
                $this->rules[] = 'sale_price_cleared';
            }
        } else if ($current_sale_price > 0 && $current_sale_price >= $current_regular_price) {
            // Caso borde: si la oferta actual es inválida (mayor que regular), la eliminamos
            $new_sale_price = 0.0;
            $this->rules[] = 'invalid_old_sale_cleared';
        }
        
        // Si no hay oferta anterior y el resultado de la oferta es null, se queda en null (no hay cambio)
        if ($current_sale_price == 0 && $new_sale_price === null) {
            // Aseguramos que el resultado retorne 0 si no había y no se calculó.
            $new_sale_price = 0.0;
        }


        $percentage_change = $this->calculate_percentage_change($current_regular_price, $new_regular_price);

        return [
            'new_price'          => $new_regular_price,
            'new_sale_price'     => $new_sale_price, 
            'old_regular'        => $current_regular_price,
            'old_sale'           => $current_sale_price, 
            'current_dollar'     => $current_dollar_value,
            'previous_dollar'    => $previous_dollar_value,
            'ratio'              => $ratio,
            'percentage_change'  => $percentage_change,
            'applied_rules'      => $this->rules,
            'base_price'         => $base_price_usd > 0 ? $base_price_usd : null,
            'base_price_range'   => $base_price_usd > 0 ? '$' . $base_price_usd : null,
            'simulated'          => $simulate
        ];
    }

    /**
     * Obtiene el dólar de referencia (previous) para el cálculo
     * Lógica: Siempre usar el último dólar aplicado de wp_dpuwoo_runs
     *         Si no hay ejecuciones previas, usar baseline del manager
     * @param bool $simulate Si es una simulación
     * @return float Valor del dólar de referencia
     */
    private function get_previous_dollar_value($simulate = false)
    {
        global $wpdb;
            
        // Use the new baseline manager for reliable baseline retrieval
        $baseline_manager = DPUWOO_Baseline_Manager::get_instance();
        $baseline = $baseline_manager->get_current_baseline('dollar');
            
        // If no baseline from manager, try to initialize
        if ($baseline === null || $baseline <= 0) {
            $baseline_manager->force_initialize();
            $baseline = $baseline_manager->get_current_baseline('dollar');
        }
            
        // Obtener el último dólar aplicado de la tabla wp_dpuwoo_runs
        $last_applied_dollar = $this->get_last_applied_dollar();
            
        // Siempre usar el último dólar aplicado si existe
        // Si no existe, usar baseline
        if ($last_applied_dollar > 0) {
            return $last_applied_dollar;
        }
            
        return $baseline > 0 ? $baseline : 1.0; // Ensure we always return a valid value
    }
        
    /**
     * Obtiene el último dólar aplicado de la tabla wp_dpuwoo_runs
     * @return float El valor del último dólar aplicado, o 0 si no hay ejecuciones
     */
    private function get_last_applied_dollar()
    {
        global $wpdb;
            
        $table_name = $wpdb->prefix . 'dpuwoo_runs';
            
        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            
        if (!$table_exists) {
            return 0;
        }
            
        // Obtener el dollar_value de la última ejecución
        $query = $wpdb->prepare("SELECT dollar_value FROM {$table_name} ORDER BY id DESC LIMIT 1");
        $last_dollar = $wpdb->get_var($query);
            
        return $last_dollar ? floatval($last_dollar) : 0;
    }
        
    /**
     * Establece el precio base multi-moneda para un producto
     * @param int $product_id ID del producto
     * @param float $current_price Precio actual del producto
     * @param float $current_rate Tasa de cambio actual
     */
    public function establish_multi_currency_baseline($product_id, $current_price, $current_rate)
    {
        // Verificar que las funciones de WordPress estén disponibles
        if (!function_exists('get_post_meta') || !function_exists('update_post_meta')) {
            return; // No podemos establecer baseline sin funciones de WordPress
        }
        
        // Solo ejecutar una vez por producto
        if (get_post_meta($product_id, '_dpuwoo_original_price_usd', true)) {
            return; // Ya existe baseline
        }
            
        // ALWAYS STORE IN USD - convert current price to USD baseline
        $baseline_usd = $current_price / $current_rate;
            
        // Guardar metadata USD baseline
        update_post_meta($product_id, '_dpuwoo_original_price_usd', $baseline_usd);
        update_post_meta($product_id, '_dpuwoo_baseline_date', current_time('mysql'));
        update_post_meta($product_id, '_dpuwoo_baseline_source_rate', $current_rate);
            
        error_log("DPUWoo: Established USD baseline for product {$product_id} - Base price: {$baseline_usd} USD");
    }
    
    /**
     * Establece baseline USD para un producto (ALWAYS IN USD)
     * @param int $product_id ID del producto
     * @param float $current_price Precio actual del producto
     * @param float $current_rate Tasa de dólar actual
     */
    public function establish_usd_baseline($product_id, $current_price, $current_rate)
    {
        // Solo ejecutar una vez por producto
        if (get_post_meta($product_id, '_dpuwoo_original_price_usd', true)) {
            return; // Baseline ya existe
        }
        
        if ($current_price > 0 && $current_rate > 0) {
            // ALWAYS STORE IN USD - convertir precio actual a baseline USD
            $baseline_usd = $current_price / $current_rate;
            
            update_post_meta($product_id, '_dpuwoo_original_price_usd', $baseline_usd);
            update_post_meta($product_id, '_dpuwoo_baseline_date', current_time('mysql'));
            update_post_meta($product_id, '_dpuwoo_baseline_source_rate', $current_rate);
            
            error_log("DPUWoo: Established USD baseline for product {$product_id}: {$baseline_usd} USD");
        }
    }
    
    /**
     * Calcula precio desde baseline USD (IGNORA precio manual actual)
     * @param int $product_id ID del producto
     * @param float $new_rate Nueva tasa de dólar para cálculo
     * @return float Precio calculado en moneda objetivo
     */
    public function calculate_from_usd_baseline($product_id, $new_rate)
    {
        // Obtener baseline USD (fuente de verdad)
        $baseline_usd = get_post_meta($product_id, '_dpuwoo_original_price_usd', true);
        
        if ($baseline_usd && $baseline_usd > 0) {
            // Calcular precio final: baseline_USD × nueva_tasa
            $new_price = $baseline_usd * $new_rate;
            
            // Registrar uso de baseline
            $this->log_baseline_calculation($product_id, $baseline_usd, $new_rate, $new_price);
            
            return round($new_price, 2);
        }
        
        // Fallback a precio actual si no existe baseline
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            return floatval($product->get_regular_price());
        }
        
        return 0;
    }
    
    /**
     * Verifica si producto tiene baseline USD
     * @param int $product_id ID del producto
     * @return bool
     */
    public function has_usd_baseline($product_id)
    {
        return (bool) get_post_meta($product_id, '_dpuwoo_original_price_usd', true);
    }
    
    /**
     * Asegura que todos los productos tengan baselines (método de verificación)
     * @param float $current_rate Tasa de dólar actual
     */
    public function ensure_missing_baselines($current_rate)
    {
        global $wpdb;
        
        if (!function_exists('get_posts') || !function_exists('get_post_meta')) {
            return;
        }
        
        // Encontrar productos sin baseline USD
        $products_without_baseline = $wpdb->get_col(
            "SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dpuwoo_original_price_usd'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm.meta_id IS NULL"
        );
        
        foreach ($products_without_baseline as $product_id) {
            $current_price = get_post_meta($product_id, '_price', true);
            if ($current_price && $current_price > 0) {
                $this->establish_usd_baseline($product_id, $current_price, $current_rate);
            }
        }
    }
    
    private function log_baseline_calculation($product_id, $baseline_usd, $rate_applied, $calculated_price)
    {
        error_log("DPUWoo: Used USD baseline for product {$product_id} - Baseline: {$baseline_usd} USD, Rate: {$rate_applied}, New Price: {$calculated_price}");
    }
        
    /**
     * Calcula nuevo precio usando baseline multi-moneda (IGNORA precio actual)
     * @param int $product_id ID del producto
     * @param float $new_rate Nueva tasa de cambio
     * @return float Nuevo precio calculado desde baseline
     */
    public function calculate_price_from_baseline($product_id, $new_rate)
    {
        // Verificar que las funciones de WordPress estén disponibles
        if (!function_exists('get_post_meta')) {
            // Fallback: usar precio actual si no hay funciones disponibles
            if (function_exists('wc_get_product')) {
                $product = wc_get_product($product_id);
                return floatval($product->get_regular_price());
            }
            return 0;
        }
        
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
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            return floatval($product->get_regular_price());
        }
        
        return 0;
    }
        
    /**
     * Verifica si un producto tiene baseline establecido
     * @param int $product_id ID del producto
     * @return bool
     */
    public function has_baseline($product_id)
    {
        if (!function_exists('get_post_meta')) {
            return false;
        }
        return (bool) get_post_meta($product_id, '_dpuwoo_base_price_reference', true);
    }
        
    /**
     * Registra uso de baseline en logs del sistema existente
     */
    private function log_baseline_usage($product_id, $base_price, $currency, $rate_applied, $calculated_price)
    {
        error_log("DPUWoo: Used baseline for product {$product_id} - Base: {$base_price} {$currency}, Rate: {$rate_applied}, New Price: {$calculated_price}");
        
        // Registrar en los logs existentes del sistema
        // Esta información se puede almacenar en los metadatos del run_item existente
        // o en un campo adicional si se extiende la tabla
    }

    /*==============================================================
    =           Configuración de Redondeo y Reglas de Negocio
    ==============================================================*/
    
    /**
     * Aplica el redondeo configurado según los settings
     */
    protected function apply_configured_rounding($price, $opts)
    {
        $rounding_type = $opts['rounding_type'] ?? 'integer';
        $nearest_to = floatval($opts['nearest_to'] ?? 1);
        
        switch ($rounding_type) {
            case 'none':
                // Sin redondeo
                $this->rules[] = 'rounding_none';
                return $price;
                
            case 'integer':
                // Redondeo a enteros
                $rounded = round($price);
                $this->rules[] = 'rounding_integer';
                return $rounded;
                
            case 'ceil':
                // Redondeo hacia arriba
                $rounded = ceil($price);
                $this->rules[] = 'rounding_ceil';
                return $rounded;
                
            case 'floor':
                // Redondeo hacia abajo
                $rounded = floor($price);
                $this->rules[] = 'rounding_floor';
                return $rounded;
                
            case 'nearest':
                // Redondeo al más cercano (múltiplo)
                $rounded = round($price / $nearest_to) * $nearest_to;
                $this->rules[] = "rounding_nearest_{$nearest_to}";
                return $rounded;
                
            default:
                // Por defecto redondeo normal
                $this->rules[] = 'rounding_default';
                return round($price, 2);
        }
    }

    /**
     * Aplica margen de corrección configurado
     */
    protected function apply_global_extra($price, $opts)
    {
        $margin = floatval($opts['margin'] ?? 0);
        
        if ($margin != 0) {
            $adjusted_price = $price * (1 + ($margin / 100));
            $this->rules[] = "margin_{$margin}%";
            return $adjusted_price;
        }
        
        return $price;
    }
    
    /**
     * Aplica reglas de dirección de actualización
     */
    protected function apply_update_direction($new_price, $old_price, $opts)
    {
        $direction = $opts['update_direction'] ?? 'bidirectional';
        
        switch ($direction) {
            case 'up_only':
                // Solo permitir aumentos
                if ($new_price < $old_price) {
                    $this->rules[] = 'direction_up_only_blocked';
                    return $old_price; // Mantener precio original
                }
                $this->rules[] = 'direction_up_only_allowed';
                return $new_price;
                
            case 'down_only':
                // Solo permitir disminuciones
                if ($new_price > $old_price) {
                    $this->rules[] = 'direction_down_only_blocked';
                    return $old_price; // Mantener precio original
                }
                $this->rules[] = 'direction_down_only_allowed';
                return $new_price;
                
            default:
                // Bidireccional - permitir ambos
                $this->rules[] = 'direction_bidirectional';
                return $new_price;
        }
    }
    
    /**
     * Aplica reglas de categoría (exclusiones)
     */
    protected function apply_category_rules($price, $product, $product_id)
    {
        $opts = get_option('dpuwoo_settings', []);
        $excluded_categories = $opts['exclude_categories'] ?? [];
        
        // Verificar si el producto pertenece a categorías excluidas
        if (!empty($excluded_categories)) {
            // Usar método de WooCommerce para obtener categorías
            $product_categories = $product->get_category_ids();
            
            // Verificar que sea un array válido
            if (is_array($product_categories) && !empty($product_categories)) {
                // Si hay intersección entre categorías del producto y categorías excluidas
                if (array_intersect($product_categories, $excluded_categories)) {
                    $this->rules[] = 'category_excluded';
                    // Podríamos mantener el precio original o aplicar una regla especial
                    // Por ahora mantenemos el precio con una regla de exclusión
                }
            }
        }
        
        return $price;
    }
    
    /*==============================================================
    =           Helpers (incluyendo rules acumulativas)
    ==============================================================*/

    protected function error($code)
    {
        return ['error' => $code];
    }

    protected function apply_ratio($price, $ratio, $rule_key = 'ratio')
    {
        // Solo agregar a rules si es el cálculo principal (regular)
        if ($rule_key === 'ratio') {
            $this->rules[] = 'ratio_' . round($ratio, 4);
        }
        return $price * $ratio;
    }

 

    protected function apply_global_rounding($price, $opts, $rule_key = 'global_rounding')
    {
        return $this->apply_rounding(
            $price,
            $opts['rounding'] ?? 'none',
            $opts['round_multiple'] ?? 10,
            $rule_key
        );
    }

    protected function calculate_percentage_change($old, $new)
    {
        return $old > 0
            ? round((($new - $old) / $old) * 100, 2)
            : 0;
    }

    /*==============================================================
    =           Redondeo
    ==============================================================*/
    protected function apply_rounding($price, $method, $multiple = 10, $rule_key = 'rounding')
    {
        if ($rule_key === 'global_rounding' && $method !== 'none') {
             $this->rules[] = "global_round_{$method}_{$multiple}";
        }
        
        switch ($method) {
            case 'up':
                return ceil($price / $multiple) * $multiple;
            case 'down':
                return floor($price / $multiple) * $multiple;
            case 'multiple':
                return round($price / $multiple) * $multiple;
            default:
                return round($price, 2);
        }
    }
}