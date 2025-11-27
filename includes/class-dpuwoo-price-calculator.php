<?php
if (!defined('ABSPATH')) exit;

class Price_Calculator
{
    protected static $instance;

    public static function init()
    {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    public function calculate_for_product($product_id, $current_dollar_value, $previous_dollar_value = null)
    {
        $product = wc_get_product($product_id);
        if (!$product) return ['error' => 'invalid_product'];

        $opts = get_option('dpuwoo_settings', []);
        
        // Si no se proporciona previous_dollar_value, usar el baseline como referencia
        if ($previous_dollar_value === null) {
            $previous_dollar_value = floatval($opts['baseline_dollar_value'] ?? 0);
        }

        if ($previous_dollar_value <= 0) {
            return ['error' => 'previous_dollar_missing'];
        }

        if ($current_dollar_value <= 0) {
            return ['error' => 'invalid_current_dollar'];
        }

        // Obtener el precio ACTUAL del producto (no el base)
        $current_price = floatval($product->get_regular_price());
        
        if ($current_price <= 0) {
            return ['error' => 'invalid_current_price'];
        }

        // CALCULO CORREGIDO: Ratio entre dólar anterior y actual
        // Esto nos dice cuánto ha cambiado el dólar desde la última actualización
        $ratio = $current_dollar_value / $previous_dollar_value;
        
        // El nuevo precio es el precio actual multiplicado por el ratio de cambio del dólar
        $new_price = $current_price * $ratio;

        if ($new_price <= 0) {
            return ['error' => 'invalid_calculated_price'];
        }

        // Aplicar porcentaje extra global
        $extra_pct = floatval($opts['extra_pct'] ?? 0);
        if ($extra_pct != 0) {
            $new_price *= (1 + $extra_pct / 100);
        }

        // Aplicar reglas de categoría (usar categoría del producto padre para variaciones)
        $category_rules = get_option('dpuwoo_category_rules', []);
        
        // Para variaciones, obtener categorías del producto padre
        $category_product_id = $product->is_type('variation') ? $product->get_parent_id() : $product_id;
        
        $cats = get_the_terms($category_product_id, 'product_cat');
        $applied_categories = [];
        
        if ($cats && is_array($cats)) {
            foreach ($cats as $cat) {
                if (!empty($category_rules[$cat->term_id])) {
                    $r = $category_rules[$cat->term_id];
                    if (!empty($r['extra_percent'])) {
                        $extra_cat_pct = floatval($r['extra_percent']);
                        $new_price *= (1 + $extra_cat_pct / 100);
                        $applied_categories[] = $cat->name . " (+{$extra_cat_pct}%)";
                    }
                    if (!empty($r['round'])) {
                        $new_price = $this->apply_rounding($new_price, $r['round'], $r['round_multiple'] ?? 10);
                    }
                }
            }
        }

        // Aplicar redondeo global
        $rounding = $opts['rounding'] ?? 'none';
        $multiple = $opts['round_multiple'] ?? 10;
        $new_price = $this->apply_rounding($new_price, $rounding, $multiple);

        // Calcular porcentaje de cambio
        $percentage_change = (($new_price - $current_price) / $current_price * 100);

        $applied_rules = ['ratio_' . round($ratio, 4)];
        if ($extra_pct != 0) $applied_rules[] = 'global_extra_' . $extra_pct . '%';
        if (!empty($applied_categories)) $applied_rules = array_merge($applied_rules, $applied_categories);

        return [
            'new_price' => round($new_price, 2),
            'new_sale_price' => null,
            'old_regular' => $current_price,
            'old_sale' => null,
            'current_dollar' => $current_dollar_value,
            'previous_dollar' => $previous_dollar_value,
            'ratio' => $ratio,
            'percentage_change' => round($percentage_change, 2),
            'applied_rules' => $applied_rules
        ];
    }

    protected function apply_rounding($price, $method, $multiple = 10)
    {
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