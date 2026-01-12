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

        $base_price_usd = floatval(get_post_meta($product_id, '_base_price_usd', true));

        /*---------------------------------------------
        | 1. Cálculo del precio REGULAR
        ----------------------------------------------*/
        $ratio = $current_dollar_value / $previous_dollar_value;

        $new_regular_price = $this->apply_ratio($current_regular_price, $ratio);
        $new_regular_price = $this->apply_global_extra($new_regular_price, $opts);
        $new_regular_price = $this->apply_category_rules($new_regular_price, $product, $product_id);
        $new_regular_price = $this->apply_global_rounding($new_regular_price, $opts);
        
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
     *         Si no hay ejecuciones previas, usar baseline_dollar_value
     * @param bool $simulate Si es una simulación
     * @return float Valor del dólar de referencia
     */
    private function get_previous_dollar_value($simulate = false)
    {
        global $wpdb;
        
        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
        
        // Obtener el último dólar aplicado de la tabla wp_dpuwoo_runs
        $last_applied_dollar = $this->get_last_applied_dollar();
        
        // Siempre usar el último dólar aplicado si existe
        // Si no existe, usar baseline
        if ($last_applied_dollar > 0) {
            return $last_applied_dollar;
        }
        
        return $baseline;
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