<?php
if (!defined('ABSPATH')) exit;

class Price_Updater
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

    public function update_all($simulate = false)
    {
        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
        if ($baseline <= 0) {
            return ['error' => 'baseline_dollar_missing'];
        }

        $type = $opts['dollar_type'] ?? 'oficial';
        $api_res = API_Client::get_instance()->get_rate($type);
        if ($api_res === false) return ['error' => 'no_rate_available'];

        $rate = floatval($api_res['value']);
        $opts['last_dollar_value'] = $rate;
        update_option('dpuwoo_settings', $opts);

        $products = get_posts(['post_type'=>'product','posts_per_page'=>-1,'fields'=>'ids']);
        $changes = [];

        foreach ($products as $pid) {
            $calc = Price_Calculator::get_instance()->calculate_for_product($pid, $rate);
            if (isset($calc['error'])) continue;

            $old_regular = floatval($calc['old_regular']);
            $new_regular = floatval($calc['new_price']);

            $changes[] = [
                'product_id'=>$pid,
                'old_regular'=>$old_regular,
                'new_regular'=>$new_regular,
                'percentage_change'=>$old_regular>0?(($new_regular-$old_regular)/$old_regular*100):null
            ];

            if (!$simulate) {
                $product = wc_get_product($pid);
                if ($product) {
                    $product->set_regular_price($new_regular);
                    $product->save();
                }
            }
        }

        return ['rate'=>$rate,'changes'=>$changes];
    }
}
