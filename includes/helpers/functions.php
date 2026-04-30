<?php

namespace Dpuwoo\Helpers;


if (!defined('ABSPATH')) exit;


function is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

/**
 * Wrapper seguro de get_woocommerce_currency().
 * Devuelve 'USD' si WooCommerce aún no está cargado.
 */
function prixy_get_store_currency(): string
{
    return function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
}



function sanitize_percentage($val)
{
    return floatval($val);
}

