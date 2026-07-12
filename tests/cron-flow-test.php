<?php
/**
 * Cron flow smoke test.
 *
 * Uses a fake command bus inside a real Prixy_Container so Cron::run_cron()
 * can be exercised without touching WooCommerce products or sending emails.
 *
 * Run: php tests/cron-flow-test.php
 */

$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: $wp_load\n");
}

define('DOING_CRON', true);
$_SERVER['HTTP_HOST'] = 'wp-flux.local';
$_SERVER['REQUEST_URI'] = '/';

if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1:10018');
}

set_error_handler(function ($severity, $message) {
    if (str_contains($message, 'Constant DB_HOST already defined')) {
        return true;
    }
    return false;
});
require_once $wp_load;
restore_error_handler();

function pass(string $msg): void { echo "  OK  $msg\n"; }
function fail(string $msg): void { echo "  ERR $msg\n"; global $errors; $errors++; }
function section(string $title): void { echo "\n=== $title ===\n"; }
function assert_true(bool $condition, string $pass, string $fail): void {
    $condition ? pass($pass) : fail($fail);
}

class Cron_Fake_Update_Handler
{
    public array $commands = [];
    public string $mode = 'multi_batch';

    public function handle(Update_Prices_Command $command): array
    {
        $this->commands[] = [
            'batch' => $command->batch,
            'simulate' => $command->simulate,
            'context' => $command->context,
            'run_id' => $command->run_id,
        ];

        if ($this->mode === 'error') {
            return ['error' => 'fake_error', 'message' => 'Fake cron error'];
        }

        if ($this->mode === 'threshold') {
            return [
                'threshold_met' => false,
                'message' => 'Fake threshold block',
                'summary' => ['updated' => 0, 'errors' => 0, 'skipped' => 0],
            ];
        }

        return [
            'run_id' => 901,
            'batch_info' => ['total_batches' => 3],
            'summary' => [
                'updated' => $command->batch === 0 ? 2 : 1,
                'errors' => 0,
                'skipped' => $command->batch,
            ],
        ];
    }
}

$errors = 0;
$original_settings = get_option('prixy_settings', []);
$previous_container = $GLOBALS['prixy_container'] ?? null;

try {
    section('Schedule state');
    $settings = array_merge($original_settings, [
        'cron_enabled' => 1,
        'interval' => 300,
        'cron_notify_mode' => 'disabled',
        'api_provider' => 'jsdelivr',
        'currency' => 'usd',
        'reference_currency' => 'USD',
        'origin_exchange_rate' => 1000,
        'origin_rate_locked' => '1',
    ]);
    update_option('prixy_settings', $settings);
    wp_cache_delete('prixy_settings', 'options');

    Cron::schedule();
    assert_true(Cron::get_next_scheduled_time() !== null, 'cron schedules when enabled', 'cron was not scheduled');

    $disabled = $settings;
    $disabled['cron_enabled'] = 0;
    update_option('prixy_settings', $disabled);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();
    assert_true(Cron::get_next_scheduled_time() === null, 'cron unschedules when disabled', 'cron remained scheduled while disabled');

    section('run_cron disabled');
    $fake_handler = new Cron_Fake_Update_Handler();
    $bus = new Command_Bus();
    $bus->register(Update_Prices_Command::class, $fake_handler);
    $container = new Prixy_Container();
    $container->singleton('settings', fn() => new Settings_Repository());
    $container->singleton('command_bus', fn() => $bus);
    $GLOBALS['prixy_container'] = $container;

    update_option('prixy_settings', $disabled);
    wp_cache_delete('prixy_settings', 'options');
    Cron::run_cron();
    assert_true(count($fake_handler->commands) === 0, 'run_cron returns early when disabled', 'run_cron dispatched while disabled');

    section('run_cron multi-batch');
    update_option('prixy_settings', $settings);
    wp_cache_delete('prixy_settings', 'options');
    Cron::run_cron();

    assert_true(count($fake_handler->commands) === 3, 'run_cron dispatched all batches', 'run_cron dispatch count mismatch: ' . count($fake_handler->commands));
    assert_true($fake_handler->commands[0]['batch'] === 0 && $fake_handler->commands[0]['run_id'] === 0, 'batch 0 starts without run_id', 'batch 0 command mismatch');
    assert_true($fake_handler->commands[1]['batch'] === 1 && $fake_handler->commands[1]['run_id'] === 901, 'batch 1 receives run_id', 'batch 1 command mismatch');
    assert_true($fake_handler->commands[2]['batch'] === 2 && $fake_handler->commands[2]['run_id'] === 901, 'batch 2 receives run_id', 'batch 2 command mismatch');
    assert_true($fake_handler->commands[0]['context'] === 'cron', 'cron command uses cron context', 'cron command context mismatch');
    assert_true($fake_handler->commands[0]['simulate'] === false, 'disabled notifications still execute update mode', 'cron simulate flag mismatch');

    section('run_cron simulate_only');
    $fake_handler->commands = [];
    $simulate_settings = $settings;
    $simulate_settings['cron_notify_mode'] = 'simulate_only';
    update_option('prixy_settings', $simulate_settings);
    wp_cache_delete('prixy_settings', 'options');

    // Avoid mail side effects by making cron stop before notification.
    $fake_handler->mode = 'threshold';
    Cron::run_cron();
    assert_true(count($fake_handler->commands) === 1, 'simulate cron dispatches first batch', 'simulate cron did not dispatch first batch');
    assert_true($fake_handler->commands[0]['simulate'] === true, 'simulate_only passes simulate=true', 'simulate_only did not pass simulate=true');

    section('run_cron error handling');
    $fake_handler->commands = [];
    $fake_handler->mode = 'error';
    $error_settings = $settings;
    $error_settings['cron_notify_mode'] = 'disabled';
    update_option('prixy_settings', $error_settings);
    wp_cache_delete('prixy_settings', 'options');
    Cron::run_cron();
    assert_true(count($fake_handler->commands) === 1, 'error cron stops after first dispatch', 'error cron dispatch count mismatch');
} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage());
} finally {
    if ($previous_container instanceof Prixy_Container) {
        $GLOBALS['prixy_container'] = $previous_container;
    } else {
        unset($GLOBALS['prixy_container']);
    }

    update_option('prixy_settings', $original_settings);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();
}

echo "\n" . str_repeat('-', 50) . "\n";
if ($errors === 0) {
    echo "OK CRON FLOW PASSED\n";
    exit(0);
}

echo "ERR CRON FLOW FAILED ($errors error(s))\n";
exit(1);
