<?php
/**
 * Activation flow smoke test.
 *
 * Resets Prixy-only options/cron, calls the real activation callback, and
 * verifies the Local database state that a newly activated plugin should leave.
 *
 * Run: php tests/activation-flow-test.php
 */

$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: $wp_load\n");
}

define('DOING_CRON', true);
$_SERVER['HTTP_HOST'] = 'wp-flux.local';
$_SERVER['REQUEST_URI'] = '/wp-admin/plugins.php';

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

function prixy_reset_activation_state(): void {
    global $wpdb;

    foreach ([
        'prixy_settings',
        'prixy_initial_setup_done',
        'prixy_db_version',
        'prixy_activation_redirect',
        'prixy_admin_notice',
        'prixy_cron_last_run',
    ] as $option) {
        delete_option($option);
    }

    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%prixy_%'");
    $wpdb->query("DELETE FROM {$wpdb->prefix}prixy_run_items");
    $wpdb->query("DELETE FROM {$wpdb->prefix}prixy_runs");
    wp_clear_scheduled_hook('prixy_do_update');

    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('prixy_do_update', [], 'prixy-cron');
    }

    wp_cache_flush();
}

function prixy_table_columns(string $table): array {
    global $wpdb;
    return $wpdb->get_col("DESCRIBE {$table}", 0);
}

$errors = 0;

global $wpdb;

try {
    section('Reset before activation');
    prixy_reset_activation_state();

    assert_true(get_option('prixy_settings', null) === null, 'prixy_settings absent before activation', 'prixy_settings still exists before activation');
    assert_true(Cron::get_next_scheduled_time() === null, 'no prixy cron before activation', 'prixy cron still scheduled before activation');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs")) === 0, 'no previous runs before activation', 'previous runs still exist before activation');

    section('Activate plugin');
    Activator::activate();
    wp_cache_flush();

    $settings = get_option('prixy_settings', []);
    assert_true(is_array($settings) && !empty($settings), 'activation created prixy_settings', 'activation did not create prixy_settings');
    assert_true(($settings['interval'] ?? null) === 3600, 'default interval is 3600', 'default interval mismatch: ' . json_encode($settings['interval'] ?? null));
    assert_true(floatval($settings['threshold'] ?? -1) === 1.0, 'default threshold is 1.0', 'default threshold mismatch');
    assert_true(floatval($settings['threshold_max'] ?? -1) === 0.0, 'default threshold_max is 0', 'default threshold_max mismatch');
    assert_true(($settings['reference_currency'] ?? '') === 'USD', 'default reference currency is USD', 'default reference currency mismatch');
    assert_true(!empty($settings['api_provider']), 'activation selected an API provider', 'activation did not select an API provider');
    assert_true(floatval($settings['origin_exchange_rate'] ?? 0) > 0, 'activation initialized origin exchange rate', 'activation did not initialize origin exchange rate');
    assert_true(empty($settings['origin_rate_locked']), 'activation does not lock onboarding rate', 'activation unexpectedly locked onboarding rate');

    assert_true(get_option('prixy_initial_setup_done') === '1' || get_option('prixy_initial_setup_done') === true, 'initial setup marker created', 'initial setup marker missing');
    assert_true(get_option('prixy_db_version') === Activator::DB_VERSION, 'db version stored after activation', 'db version not stored after activation');
    assert_true(get_transient('prixy_activation_redirect') !== false, 'activation redirect transient set', 'activation redirect transient missing');

    $notice = get_option('prixy_admin_notice', []);
    assert_true(is_array($notice) && !empty($notice['message']), 'activation admin notice created', 'activation admin notice missing');
    assert_true(in_array($notice['type'] ?? '', ['success', 'warning'], true), 'activation notice has valid type', 'activation notice type invalid');
    assert_true(!Admin::is_setup_complete(), 'admin setup remains incomplete until user locks rate', 'admin setup was completed by activation alone');

    $progress = Admin::get_setup_progress();
    assert_true(!empty($progress['rate_initialized']), 'setup progress sees initialized rate', 'setup progress does not see initialized rate');
    assert_true(empty($progress['first_run_done']), 'setup progress has no first run on fresh activation', 'setup progress found an old first run after reset');

    section('Database schema');
    $runs_table = $wpdb->prefix . 'prixy_runs';
    $items_table = $wpdb->prefix . 'prixy_run_items';

    assert_true($wpdb->get_var("SHOW TABLES LIKE '{$runs_table}'") === $runs_table, 'runs table exists', 'runs table missing');
    assert_true($wpdb->get_var("SHOW TABLES LIKE '{$items_table}'") === $items_table, 'run items table exists', 'run items table missing');

    $runs_cols = prixy_table_columns($runs_table);
    foreach (['id', 'date', 'dollar_type', 'reference_currency', 'dollar_value', 'rules', 'total_products', 'user_id', 'note', 'percentage_change', 'context'] as $column) {
        assert_true(in_array($column, $runs_cols, true), "runs column $column exists", "runs column $column missing");
    }

    $items_cols = prixy_table_columns($items_table);
    foreach (['id', 'run_id', 'product_id', 'old_regular_price', 'new_regular_price', 'old_sale_price', 'new_sale_price', 'percentage_change', 'status', 'reason'] as $column) {
        assert_true(in_array($column, $items_cols, true), "items column $column exists", "items column $column missing");
    }

    section('Cron after activation');
    $next = Cron::get_next_scheduled_time();
    assert_true(is_int($next) && $next > 0, 'prixy cron scheduled after activation', 'prixy cron not scheduled after activation');

    if (function_exists('_get_cron_array')) {
        $found = false;
        foreach (_get_cron_array() ?: [] as $timestamp => $hooks) {
            if (isset($hooks['prixy_do_update'])) {
                $found = true;
                break;
            }
        }
        assert_true($found || Cron::is_action_scheduler_available(), 'cron hook visible in scheduler', 'cron hook not visible in WP-Cron');
    }
} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage());
}

echo "\n" . str_repeat('-', 50) . "\n";
if ($errors === 0) {
    echo "OK ACTIVATION FLOW PASSED\n";
    exit(0);
}

echo "ERR ACTIVATION FLOW FAILED ($errors error(s))\n";
exit(1);
