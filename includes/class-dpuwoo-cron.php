<?php
if (!defined('ABSPATH')) exit;

class Cron
{
    const HOOK = 'dpuwoo_do_update';
    const SCHEDULE_GROUP = 'dpuwoo-cron';

    public static function register_schedule(array $schedules): array
    {
        $interval = max(300, intval(get_option('dpuwoo_settings', [])['interval'] ?? 3600));

        $schedules['dpuwoo_custom'] = [
            'interval' => $interval,
            'display'  => 'DPU WOO (' . $interval . 's)',
        ];

        return $schedules;
    }

    public static function schedule(): void
    {
        $opts    = get_option('dpuwoo_settings', []);
        $enabled = $opts['cron_enabled'] ?? 1;

        self::unschedule();

        if (!$enabled) {
            return;
        }

        $interval = max(300, intval($opts['interval'] ?? 3600));

        if (self::is_action_scheduler_available()) {
            self::schedule_with_action_scheduler($interval);
        } else {
            self::schedule_with_wp_cron($interval);
        }
    }

    private static function schedule_with_action_scheduler(int $interval): void
    {
        as_schedule_recurring_action(
            time(),
            $interval,
            self::HOOK,
            [],
            self::SCHEDULE_GROUP,
            true
        );
    }

    private static function schedule_with_wp_cron(int $interval): void
    {
        wp_schedule_event(time(), 'dpuwoo_custom', self::HOOK);
    }

    public static function unschedule(): void
    {
        if (self::is_action_scheduler_available()) {
            as_unschedule_all_actions(self::HOOK, [], self::SCHEDULE_GROUP);
        } else {
            $timestamp = wp_next_scheduled(self::HOOK);
            if ($timestamp) {
                wp_unschedule_event($timestamp, self::HOOK);
            }
        }
    }

    public static function is_action_scheduler_available(): bool
    {
        return class_exists('ActionScheduler') && function_exists('as_schedule_recurring_action');
    }

    public static function run_cron(): void
    {
        global $dpuwoo_container;

        if (!$dpuwoo_container instanceof Dpuwoo_Container) {
            $dpuwoo_container = Dpuwoo_Container::build();
        }

        /** @var Command_Bus $bus */
        $bus = $dpuwoo_container->get('command_bus');

        $dpuwoo_container->get('settings')->refresh();

        $opts    = $dpuwoo_container->get('settings')->get_all();
        $enabled = $opts['cron_enabled'] ?? 1;
        if (!$enabled) {
            return;
        }

        $batch  = 0;
        $result = $bus->dispatch(new Update_Prices_Command($batch, simulate: false, context: 'cron'));

        if (isset($result['error'])) {
            return;
        }

        $total_batches = $result['batch_info']['total_batches'] ?? 1;
        $run_id = $result['run_id'] ?? 0;

        for ($batch = 1; $batch < $total_batches; $batch++) {
            $bus->dispatch(new Update_Prices_Command($batch, simulate: false, context: 'cron', run_id: $run_id));
        }
    }

    public static function get_next_scheduled_time(): ?int
    {
        if (self::is_action_scheduler_available()) {
            $action = as_get_scheduled_action(self::HOOK, [], self::SCHEDULE_GROUP);
            if ($action) {
                return $action->get_schedule()->get_next();
            }
            return null;
        }

        return wp_next_scheduled(self::HOOK);
    }

    public static function is_scheduled(): bool
    {
        if (self::is_action_scheduler_available()) {
            return as_has_scheduled_action(self::HOOK, [], self::SCHEDULE_GROUP);
        }

        return wp_next_scheduled(self::HOOK) !== false;
    }
}
