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
     * @param float $previous_dollar_value Tasa de dólar de referencia.
     * @param bool $simulate Si es una simulación.
     * @return array|false Resultado del cálculo o false en caso de error.
     */
    public function calculate_for_product($product_id, $current_dollar_value, $previous_dollar_value = null, $simulate = false)
    {
        // Resetear reglas para este cálculo
        $this->rules = []; 
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return $this->error('invalid_product');
        }

        $opts = get_option('dpuwoo_settings', []);

        $previous_dollar_value = $this->resolve_previous_dollar($previous_dollar_value, $opts, $simulate);
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

        if ($simulate && abs($ratio - 1.0) < 0.0001) {
            $ratio = 1.001; // 0.1% de cambio mínimo para simulación
        }

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
            $calculated_new_sale_price = $this->apply_global_extra($calculated_new_sale_price, $opts, 'sale_price_extra');
            $calculated_new_sale_price = $this->apply_category_rules($calculated_new_sale_price, $product, $product_id, 'sale_price_category');
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

    /*==============================================================
    =           Helpers (incluyendo rules acumulativas)
    ==============================================================*/

    protected function error($code)
    {
        return ['error' => $code];
    }

    protected function resolve_previous_dollar($previous, $opts, $simulate = false)
    {
        if ($previous !== null) {
            return floatval($previous);
        }
        
        if ($simulate) {
            return floatval($opts['baseline_dollar_value'] ?? 0);
        }
        
        return floatval(get_option('dpuwoo_last_dollar_value', $opts['baseline_dollar_value'] ?? 0));
    }

    protected function apply_ratio($price, $ratio, $rule_key = 'ratio')
    {
        // Solo agregar a rules si es el cálculo principal (regular)
        if ($rule_key === 'ratio') {
            $this->rules[] = 'ratio_' . round($ratio, 4);
        }
        return $price * $ratio;
    }

    protected function apply_global_extra($price, $opts, $rule_key = 'global_extra')
    {
        $extra_pct = floatval($opts['extra_pct'] ?? 0);
        if ($extra_pct != 0 && $rule_key === 'global_extra') {
            $this->rules[] = "global_extra_{$extra_pct}%";
        }
        if ($extra_pct != 0) {
            $price *= (1 + $extra_pct / 100);
        }
        return $price;
    }

    protected function apply_category_rules($price, $product, $product_id, $rule_key = 'category_rules')
    {
        $category_rules = get_option('dpuwoo_category_rules', []);

        $category_product_id = $product->is_type('variation')
            ? $product->get_parent_id()
            : $product_id;

        $cats = get_the_terms($category_product_id, 'product_cat');
        if (!$cats || !is_array($cats)) {
            return $price;
        }

        foreach ($cats as $cat) {
            if (!isset($category_rules[$cat->term_id])) continue;

            $rule = $category_rules[$cat->term_id];

            if (!empty($rule['extra_percent'])) {
                $extra = floatval($rule['extra_percent']);
                if ($rule_key === 'category_rules') {
                    $this->rules[] = "{$cat->name} (+{$extra}%)";
                }
                $price *= (1 + $extra / 100);
            }

            if (!empty($rule['round']) && $rule_key === 'category_rules') {
                $price = $this->apply_rounding($price, $rule['round'], $rule['round_multiple'] ?? 10, "{$cat->name}_rounding");
            }
        }

        return $price;
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