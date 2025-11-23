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
        if (!$product) return null;

        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);

        if ($baseline <= 0) {
            return ['error' => 'baseline_dollar_missing'];
        }

        $base_regular = get_post_meta($product_id, '_dpuwoo_base_regular_price', true);
        $base_sale = get_post_meta($product_id, '_dpuwoo_base_sale_price', true);

        if ($base_regular === '' || $base_regular === null) {
            $base_regular = floatval($product->get_regular_price());
            update_post_meta($product_id, '_dpuwoo_base_regular_price', $base_regular);
        }

        if ($base_sale === '' || $base_sale === null) {
            $sale = $product->get_sale_price();
            $base_sale = is_numeric($sale) ? floatval($sale) : null;
            if ($base_sale !== null) {
                update_post_meta($product_id, '_dpuwoo_base_sale_price', $base_sale);
            }
        }

        $ratio = floatval($current_dollar_value) / $baseline;

        $new_price = $base_regular * $ratio;
        $new_sale_price = $base_sale !== null ? $base_sale * $ratio : null;

        // Global extra
        $extra_pct = floatval($opts['extra_pct'] ?? 0);
        if ($extra_pct != 0) {
            $new_price *= (1 + $extra_pct / 100);
            if ($new_sale_price !== null) $new_sale_price *= (1 + $extra_pct / 100);
        }

        // Category rules
        $category_rules = get_option('dpuwoo_category_rules', []);
        $cats = get_the_terms($product_id, 'product_cat');
        if ($cats && is_array($cats)) {
            foreach ($cats as $cat) {
                if (!empty($category_rules[$cat->term_id])) {
                    $r = $category_rules[$cat->term_id];
                    if (!empty($r['extra_percent'])) {
                        $new_price *= (1 + floatval($r['extra_percent']) / 100);
                        if ($new_sale_price !== null) $new_sale_price *= (1 + floatval($r['extra_percent']) / 100);
                    }
                    if (!empty($r['round'])) {
                        $new_price = $this->apply_rounding($new_price, $r['round'], $r['round_multiple'] ?? 10);
                    }
                }
            }
        }

        // Global rounding
        $rounding = $opts['rounding'] ?? 'none';
        $multiple = $opts['round_multiple'] ?? 10;
        $new_price = $this->apply_rounding($new_price, $rounding, $multiple);

        return [
            'new_price' => round($new_price, 2),
            'new_sale_price' => $new_sale_price !== null ? round($new_sale_price, 2) : null,
            'old_regular' => $base_regular,
            'old_sale' => $base_sale,
            'ratio' => $ratio,
            'applied_rules' => ['ratio_' . round($ratio, 6)]
        ];
    }

    protected function apply_rounding($price, $method, $multiple=10)
    {
        switch ($method) {
            case 'up': return ceil($price / $multiple) * $multiple;
            case 'down': return floor($price / $multiple) * $multiple;
            case 'multiple': return round($price / $multiple) * $multiple;
            default: return $price;
        }
    }
}
