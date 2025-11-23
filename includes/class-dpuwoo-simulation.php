<?php
if (!defined('ABSPATH')) exit;

class Simulation
{
    protected static $instance;

    public static function init()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    public function simulate_all()
    {
        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
        if ($baseline <= 0) return ['error'=>'baseline_dollar_missing'];

        $type = $opts['dollar_type'] ?? 'oficial';
        $rate = API_Client::get_instance()->get_rate($type);
        if ($rate === false) return ['error'=>'no_rate_available'];

        $products = get_posts(['post_type'=>'product','posts_per_page'=>-1,'fields'=>'ids']);
        $res = [];
        foreach ($products as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;
            $current = floatval($product->get_regular_price());
            $projected = Price_Calculator::get_instance()->calculate_for_product($pid, $rate);
            if (isset($projected['error'])) continue;
            $res[] = [
                'id'=>$pid,
                'title'=>get_the_title($pid),
                'current'=>$current,
                'projected'=>$projected['new_price'],
                'diff'=>round($projected['new_price'] - $current,2)
            ];
        }
        return ['rate'=>$rate,'rows'=>$res];
    }
}
