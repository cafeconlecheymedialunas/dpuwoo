<?php
if (!defined('ABSPATH')) exit;


class Cron
{
    const HOOK = 'dpuwoo_do_update';


    public static function schedule()
    {
        $opts = get_option('dpuwoo_settings', []);
        $interval = intval($opts['interval'] ?? 3600);
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'hourly', self::HOOK);
        }
        add_action(self::HOOK, [__CLASS__, 'run_cron']);
    }


    public static function unschedule()
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) wp_unschedule_event($timestamp, self::HOOK);
        remove_action(self::HOOK, [__CLASS__, 'run_cron']);
    }


    public static function run_cron()
    {
        // simple flow
        $opts = get_option('dpuwoo_settings', []);
        $type = $opts['dollar_type'] ?? 'oficial';
        $api = API_Client::get_instance();
        $rate = $api->get_rate($type);
        if ($rate === false) {
            $rate = Fallback::get_instance()->get_fallback_rate();
        }
        if ($rate === false) return;


        // compute variation and threshold
        $last = floatval($opts['last_rate'] ?? 0);
        $threshold = floatval($opts['threshold'] ?? 0);
        $changed = ($last > 0) ? abs(($rate - $last) / $last) * 100 : 100;
        if ($threshold > 0 && $changed < $threshold) {
            // record log "no action"
            Logger::get_instance()->log_price_change(0, $last, $rate, $rate, 'cron', 'no_action', 'threshold not met');
            return;
        }


        Price_Updater::get_instance()->update_all();
    }
}
