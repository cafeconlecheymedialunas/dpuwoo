<?php

namespace Dpuwoo\Helpers;


if (!defined('ABSPATH')) exit;


function is_woocommerce_active()
{
    return class_exists('WooCommerce');
}



function sanitize_percentage($val)
{
    return floatval($val);
}


function reset_base_prices()
{
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ];

    $products = get_posts($args);

    foreach ($products as $product_id) {

        $product = wc_get_product($product_id);
        if (!$product) continue;

        $regular_price = $product->get_regular_price();
        if ($regular_price === '' || !is_numeric($regular_price)) {
            continue;
        }

        update_post_meta($product_id, '_dpuwoo_base_price', floatval($regular_price));
    }

    return true;
}
