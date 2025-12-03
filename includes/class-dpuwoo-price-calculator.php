<?php
if (!defined('ABSPATH')) exit;

class Price_Calculator
{
    protected static $instance;

    /*==============================================================
    =            Singleton
    ==============================================================*/
    public static function init()
    {
        return self::$instance ?? (self::$instance = new self());
    }

    public static function get_instance()
    {
        return self::init();
    }

    /*==============================================================
    =            API pública
    ==============================================================*/
    public function calculate_for_product($product_id, $current_dollar_value, $previous_dollar_value = null, $simulate = false)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return $this->error('invalid_product');
        }

        $opts = get_option('dpuwoo_settings', []);

        // Obtener dólar anterior
        $previous_dollar_value = $this->resolve_previous_dollar($previous_dollar_value, $opts, $simulate);
        if ($previous_dollar_value <= 0) {
            return $this->error('previous_dollar_missing');
        }

        if ($current_dollar_value <= 0) {
            return $this->error('invalid_current_dollar');
        }

        $current_price = floatval($product->get_regular_price());
        if ($current_price <= 0) {
            return $this->error('invalid_current_price');
        }

        $base_price_usd = floatval(get_post_meta($product_id, '_base_price_usd', true));

        /*---------------------------------------------
        | Cálculo principal del precio
        ----------------------------------------------*/
        $ratio = $current_dollar_value / $previous_dollar_value;

        // ============================================
        // CAMBIO IMPORTANTE: Para simulación, forzar cálculo
        // ============================================
        if ($simulate && abs($ratio - 1.0) < 0.0001) {
            // Si es simulación y el ratio es ~1, usar un ratio mínimo para mostrar cambios
            $ratio = 1.001; // 0.1% de cambio mínimo para mostrar proyección
        }

        $new_price = $this->apply_ratio($current_price, $ratio);
        $new_price = $this->apply_global_extra($new_price, $opts);
        $new_price = $this->apply_category_rules($new_price, $product, $product_id);
        $new_price = $this->apply_global_rounding($new_price, $opts);

        $percentage_change = $this->calculate_percentage_change($current_price, $new_price);

        return [
            'new_price'          => round($new_price, 2),
            'new_sale_price'     => null,
            'old_regular'        => $current_price,
            'old_sale'           => null,
            'current_dollar'     => $current_dollar_value,
            'previous_dollar'    => $previous_dollar_value,
            'ratio'              => $ratio,
            'percentage_change'  => $percentage_change,
            'applied_rules'      => $this->rules,
            'base_price'         => $base_price_usd > 0 ? $base_price_usd : null,
            'base_price_range'   => $base_price_usd > 0 ? '$' . $base_price_usd : null,
            'simulated'          => $simulate  // Agregar flag de simulación
        ];
    }

    /*==============================================================
    =            Helpers
    ==============================================================*/

    protected $rules = [];

    protected function error($code)
    {
        return ['error' => $code];
    }

    protected function resolve_previous_dollar($previous, $opts, $simulate = false)
    {
        if ($previous !== null) {
            return floatval($previous);
        }
        
        // Para simulación, usar siempre baseline como referencia
        if ($simulate) {
            return floatval($opts['baseline_dollar_value'] ?? 0);
        }
        
        return floatval($opts['baseline_dollar_value'] ?? 0);
    }

    protected function apply_ratio($price, $ratio)
    {
        $this->rules[] = 'ratio_' . round($ratio, 4);
        return $price * $ratio;
    }

    protected function apply_global_extra($price, $opts)
    {
        $extra_pct = floatval($opts['extra_pct'] ?? 0);
        if ($extra_pct != 0) {
            $this->rules[] = "global_extra_{$extra_pct}%";
            $price *= (1 + $extra_pct / 100);
        }
        return $price;
    }

    protected function apply_category_rules($price, $product, $product_id)
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
                $price *= (1 + $extra / 100);
                $this->rules[] = "{$cat->name} (+{$extra}%)";
            }

            if (!empty($rule['round'])) {
                $price = $this->apply_rounding($price, $rule['round'], $rule['round_multiple'] ?? 10);
            }
        }

        return $price;
    }

    protected function apply_global_rounding($price, $opts)
    {
        return $this->apply_rounding(
            $price,
            $opts['rounding'] ?? 'none',
            $opts['round_multiple'] ?? 10
        );
    }

    protected function calculate_percentage_change($old, $new)
    {
        return $old > 0
            ? round((($new - $old) / $old) * 100, 2)
            : 0;
    }

    /*==============================================================
    =            Redondeo
    ==============================================================*/
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