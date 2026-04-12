<?php
if (!defined('ABSPATH')) exit;

class Cron
{
    const HOOK = 'dpuwoo_do_update';
    const SCHEDULE_GROUP = 'dpuwoo-cron';

    public static function register_schedule(array $schedules): array
    {
        $opts    = get_option('dpuwoo_settings', []);
        $interval_key = $opts['update_interval'] ?? 'twicedaily';
        $interval_seconds = [
            'hourly' => 3600,
            'twicedaily' => 43200,
            'daily' => 86400,
            'weekly' => 604800,
        ];
        $interval = $interval_seconds[$interval_key] ?? 43200;
        $interval = max(300, $interval);

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

        $interval = $opts['update_interval'] ?? 'twicedaily';
        $interval_seconds = [
            'hourly' => 3600,
            'twicedaily' => 43200,
            'daily' => 86400,
            'weekly' => 604800,
        ];
        $interval = $interval_seconds[$interval] ?? 43200;

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
        return class_exists('ActionScheduler') 
            && function_exists('as_schedule_recurring_action')
            && function_exists('as_get_scheduled_action');
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

        $notify_mode = $opts['cron_notify_mode'] ?? 'update_and_notify';

        // Determine if we simulate or update
        $simulate = ($notify_mode === 'simulate_only');

        // Run the batch processing
        $batch  = 0;
        $result = $bus->dispatch(new Update_Prices_Command($batch, simulate: $simulate, context: 'cron'));

        if (isset($result['error'])) {
            // Send error notification if enabled
            if ($notify_mode !== 'disabled') {
                self::send_error_notification($result['error']);
            }
            return;
        }

        $total_batches = $result['batch_info']['total_batches'] ?? 1;
        $run_id = $result['run_id'] ?? 0;

        for ($batch = 1; $batch < $total_batches; $batch++) {
            $bus->dispatch(new Update_Prices_Command($batch, simulate: $simulate, context: 'cron', run_id: $run_id));
        }

        // Send notification if enabled
        if ($notify_mode !== 'disabled') {
            self::send_notification($result, $simulate);
        }
    }

    private static function send_notification(array $result, bool $simulate): void
    {
        if (!class_exists('Email_Notifier')) {
            require_once DPUWOO_PLUGIN_DIR . 'includes/class-dpuwoo-email-notifier.php';
        }

        $notifier = new Email_Notifier();

        if ($simulate) {
            $notifier->send_simulation_results($result, 'cron');
        } else {
            $notifier->send_update_results($result, 'cron');
        }
    }

    private static function send_error_notification(string $error): void
    {
        if (!class_exists('Email_Notifier')) {
            require_once DPUWOO_PLUGIN_DIR . 'includes/class-dpuwoo-email-notifier.php';
        }

        $opts = get_option('dpuwoo_settings', []);
        $to = $opts['cron_notify_email'] ?? get_option('admin_email');

        $subject = sprintf(
            '[%s] Dollar Sync - Error en Cron',
            get_bloginfo('name')
        );

        $message = sprintf(
            '<p>Hubo un error durante la ejecución del cron de Dollar Sync:</p><p><strong>%s</strong></p>',
            esc_html($error)
        );

        wp_mail(
            $to,
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
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
}
