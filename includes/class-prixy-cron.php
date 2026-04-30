<?php
if (!defined('ABSPATH')) exit;

class Cron
{
    const HOOK = 'prixy_do_update';
    const SCHEDULE_GROUP = 'prixy-cron';

    public static function register_schedule(array $schedules): array
    {
        $opts    = get_option('prixy_settings', []);
        $interval_key = $opts['update_interval'] ?? 'twicedaily';
        $interval_seconds = [
            'hourly' => 3600,
            'twicedaily' => 43200,
            'daily' => 86400,
            'weekly' => 604800,
        ];
        $interval = $interval_seconds[$interval_key] ?? 43200;
        $interval = max(300, $interval);

        $schedules['prixy_custom'] = [
            'interval' => $interval,
            'display'  => 'DPU WOO (' . $interval . 's)',
        ];

        return $schedules;
    }

    public static function schedule(): void
    {
        $opts    = get_option('prixy_settings', []);
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
        wp_schedule_event(time(), 'prixy_custom', self::HOOK);
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
        global $prixy_container;

        if (!$prixy_container instanceof Prixy_Container) {
            $prixy_container = Prixy_Container::build();
        }

        /** @var Command_Bus $bus */
        $bus = $prixy_container->get('command_bus');

        $prixy_container->get('settings')->refresh();

        $opts    = $prixy_container->get('settings')->get_all();
        $enabled = $opts['cron_enabled'] ?? 1;
        
        if (!$enabled) {
            return;
        }

        $notify_mode = $opts['cron_notify_mode'] ?? 'update_and_notify';

        // Determine if we simulate or update
        $simulate = ($notify_mode === 'simulate_only');

        // Run the batch processing
        $result = $bus->dispatch(new Update_Prices_Command(0, simulate: $simulate, context: 'cron'));

        if (isset($result['error'])) {
            error_log('DPUWoo Cron: error en ejecución — ' . ($result['message'] ?? $result['error']));
            if ($notify_mode !== 'disabled') {
                self::send_error_notification($result['error']);
            }
            return;
        }

        if (isset($result['threshold_met']) && $result['threshold_met'] === false) {
            error_log('DPUWoo Cron: threshold no alcanzado — ' . ($result['message'] ?? ''));
            return;
        }

        $total_batches = $result['batch_info']['total_batches'] ?? 0;
        $run_id        = $result['run_id'] ?? 0;

        // Acumular summary de todos los batches para la notificación
        $combined_summary = $result['summary'] ?? ['updated' => 0, 'errors' => 0, 'skipped' => 0];

        for ($batch = 1; $batch < $total_batches; $batch++) {
            $batch_result = $bus->dispatch(new Update_Prices_Command($batch, simulate: $simulate, context: 'cron', run_id: $run_id));

            if (isset($batch_result['error'])) {
                error_log('DPUWoo Cron: error en batch ' . $batch . ': ' . $batch_result['error']);
                break;
            }

            $bs = $batch_result['summary'] ?? [];
            $combined_summary['updated']  += $bs['updated']  ?? 0;
            $combined_summary['errors']   += $bs['errors']   ?? 0;
            $combined_summary['skipped']  += $bs['skipped']  ?? 0;
        }

        $result['summary'] = $combined_summary;

        // Send notification if enabled
        if ($notify_mode !== 'disabled') {
            self::send_notification($result, $simulate);
        }
    }

    private static function send_notification(array $result, bool $simulate): void
    {
        if (!class_exists('Email_Notifier')) {
            require_once PRIXY_PLUGIN_DIR . 'includes/class-prixy-email-notifier.php';
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
            require_once PRIXY_PLUGIN_DIR . 'includes/class-prixy-email-notifier.php';
        }

        $opts = get_option('prixy_settings', []);
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
            $timestamp = as_next_scheduled_action(self::HOOK, [], self::SCHEDULE_GROUP);
            return $timestamp ?: null;
        }

        return wp_next_scheduled(self::HOOK) ?: null;
    }
}
