<?php

if (!defined('ABSPATH')) exit;


class Fallback
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


    public function get_fallback_rate()
    {
        $opts = get_option('dpuwoo_settings', []);
        if (!empty($opts['last_rate'])) return floatval($opts['last_rate']);
        return false;
    }
}
