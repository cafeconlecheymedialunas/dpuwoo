<?php
if (!defined('ABSPATH')) exit;

/**
 * Price_Calculator - Calculadora de precios simplificada sin baseline
 */
class Price_Calculator
{
    private static $instance = null;
    private $rules = [];

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Constructor privado para singleton
    }

    /**
     * Calcula nuevo precio usando tasa de cambio directa (sin baseline)
     * @param int $product_id ID del producto
     * @param float $current_price Precio actual del producto
     * @param float $new_rate Nueva tasa de cambio
     * @return float Nuevo precio calculado
     */
    public function calculate_new_price($product_id, $current_price, $new_rate)
    {
        // Usar precio actual multiplicado por la nueva tasa
        $new_price = $current_price * $new_rate;
        
        return round($new_price, 2);
    }
    
    /**
     * Verifica si se puede calcular precio (producto tiene precio válido)
     * @param int $product_id ID del producto
     * @return bool
     */
    public function can_calculate_price($product_id)
    {
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            $price = floatval($product->get_regular_price());
            return $price > 0;
        }
        return false;
    }

    /**
     * Obtiene el precio base para cálculos
     * @param int $product_id ID del producto
     * @return float Precio base del producto
     */
    public function get_base_price($product_id)
    {
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            return floatval($product->get_regular_price());
        }
        return 0;
    }

    /**
     * Obtiene el precio base para cálculos (versión pública)
     * @param int $product_id ID del producto
     * @return float Precio base del producto
     */
    public function get_base_price_public($product_id)
    {
        return $this->get_base_price($product_id);
    }

    /**
     * Obtiene la tasa de cambio base para cálculos
     * @return float Tasa de cambio base
     */
    public function get_base_rate()
    {
        // Usar el último dólar aplicado de la tabla wp_dpuwoo_runs
        $last_applied_dollar = $this->get_last_applied_dollar();

        // Siempre usar el último dólar aplicado si existe
        if ($last_applied_dollar > 0) {
            return $last_applied_dollar;
        }

        // Si no hay registros anteriores, usar el rate actual como referencia inicial
        return 1.0; // Valor por defecto
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