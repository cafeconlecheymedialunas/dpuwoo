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

