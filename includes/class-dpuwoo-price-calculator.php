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

    public function calculate_for_product($product_id, $current_dollar_value)
    {
        $product = wc_get_product($product_id);
        if (!$product) return ['error' => 'invalid_product'];

        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);

        if ($baseline <= 0) {
            return ['error' => 'baseline_dollar_missing'];
        }

        // Obtener precio base - funciona para productos simples Y variaciones
        $base_price = get_post_meta($product_id, '_dpuwoo_base_price', true);
        
        // Si no existe base price, usar el precio regular actual y guardarlo
        if ($base_price === '' || $base_price === null || floatval($base_price) <= 0) {
            $base_price = floatval($product->get_regular_price());
            if ($base_price > 0) {
                update_post_meta($product_id, '_dpuwoo_base_price', $base_price);
            } else {
                return ['error' => 'invalid_base_price'];
            }
        }

        $base_price = floatval($base_price);
        $current_price = floatval($product->get_regular_price());
        
        // Calcular ratio
        $ratio = floatval($current_dollar_value) / $baseline;
        $new_price = $base_price * $ratio;

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
        if ($cats && is_array($cats)) {
            foreach ($cats as $cat) {
                if (!empty($category_rules[$cat->term_id])) {
                    $r = $category_rules[$cat->term_id];
                    if (!empty($r['extra_percent'])) {
                        $new_price *= (1 + floatval($r['extra_percent']) / 100);
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

        // Calcular porcentaje de cambio respecto al precio ACTUAL, no al base
        $percentage_change = ($current_price > 0) ? (($new_price - $current_price) / $current_price * 100) : 100;

        return [
            'new_price' => round($new_price, 2),
            'new_sale_price' => null,
            'old_regular' => $current_price,
            'old_sale' => null,
            'base_price' => $base_price,
            'ratio' => $ratio,
            'percentage_change' => $percentage_change,
            'applied_rules' => ['ratio_' . round($ratio, 6)]
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