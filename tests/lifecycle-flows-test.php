<?php
/**
 * Lifecycle flow smoke test.
 *
 * Covers activation, settings save/sanitize, cron schedule/unschedule,
 * deactivation, and the admin reset DB partial.
 *
 * Run: php tests/lifecycle-flows-test.php
 */

$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: $wp_load\n");
}

define('DOING_CRON', true);
$_SERVER['HTTP_HOST'] = 'wp-flux.local';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=prixy_reset';

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

function prixy_reset_plugin_state(): void {
    global $wpdb;

    foreach ([
        'prixy_settings',
        'prixy_initial_setup_done',
        'prixy_db_version',
        'prixy_activation_redirect',
        'prixy_admin_notice',
        'prixy_cron_last_run',
        'prixy_setup_run_id',
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

$errors = 0;
global $wpdb;

try {
    wp_set_current_user(1);

    section('Fresh activation');
    prixy_reset_plugin_state();
    Activator::activate();
    wp_cache_flush();

    assert_true(get_option('prixy_db_version') === Activator::DB_VERSION, 'activation stores DB version', 'activation DB version missing');
    assert_true(Cron::get_next_scheduled_time() !== null, 'activation schedules cron', 'activation did not schedule cron');
    assert_true(!Admin::is_setup_complete(), 'activation leaves onboarding unlocked', 'activation incorrectly completed onboarding');

    section('Settings save and sanitize');
    $saved = Admin_Settings::sanitize([
        'api_provider' => 'jsdelivr',
        'country' => '<b>AR</b>',
        'base_currency' => 'ARS',
        'reference_currency' => 'USD',
        'origin_exchange_rate' => '1234.56',
        'origin_rate_locked' => '1',
        'rate_generation_method' => 'api',
        'currency' => 'usd',
        'margin' => '12.5',
        'threshold' => '0',
        'threshold_max' => '25',
        'update_direction' => 'up_only',
        'rounding_type' => 'nearest',
        'nearest_to' => '50',
        'exclude_categories' => ['2', 'bad', '7'],
        'cron_enabled' => '1',
        'interval' => '60',
        'cron_api_provider' => 'foreignrate',
        'cron_currency' => 'eur',
        'cron_notify_mode' => 'invalid-mode',
        'cron_notify_email' => 'not-an-email',
    ]);
    update_option('prixy_settings', $saved);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();

    assert_true($saved['country'] === 'AR', 'country sanitized', 'country sanitize mismatch: ' . $saved['country']);
    assert_true($saved['interval'] === 300, 'interval is clamped to 300 seconds', 'interval was not clamped: ' . $saved['interval']);
    assert_true($saved['origin_rate_locked'] === '1', 'origin rate lock saved', 'origin rate lock missing');
    assert_true($saved['cron_notify_mode'] === 'update_and_notify', 'invalid notify mode falls back', 'notify mode fallback mismatch');
    assert_true($saved['cron_notify_email'] === '', 'invalid notify email is cleared', 'invalid notify email was not cleared');
    assert_true($saved['exclude_categories'] === [2, 0, 7], 'exclude categories are integer-cast', 'exclude categories sanitize mismatch');
    assert_true(Admin::is_setup_complete(), 'locked rate completes onboarding', 'locked rate did not complete onboarding');
    assert_true(Cron::get_next_scheduled_time() !== null, 'enabled settings schedule cron', 'enabled settings did not schedule cron');

    $repo = new Settings_Repository();
    $manual = $repo->get_for_context('manual');
    $cron = $repo->get_for_context('cron');
    assert_true($manual['api_provider'] === 'jsdelivr', 'manual context keeps main provider', 'manual context provider mismatch');
    assert_true($cron['api_provider'] === 'foreignrate', 'cron context applies provider override', 'cron context provider mismatch');
    assert_true($cron['currency'] === 'eur', 'cron context applies currency override', 'cron context currency mismatch');

    section('Cron disable and deactivation');
    $disabled = $saved;
    unset($disabled['cron_enabled']);
    $disabled = Admin_Settings::sanitize($disabled);
    update_option('prixy_settings', $disabled);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();

    assert_true(Cron::get_next_scheduled_time() === null, 'disabled settings unschedule cron', 'cron still scheduled after disable');

    $enabled = $saved;
    update_option('prixy_settings', $enabled);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();
    assert_true(Cron::get_next_scheduled_time() !== null, 'cron rescheduled before deactivation', 'cron not scheduled before deactivation');
    Deactivator::deactivate();
    assert_true(Cron::get_next_scheduled_time() === null, 'deactivation unschedules cron', 'cron still scheduled after deactivation');

    section('Admin reset partial');
    $log_repo = new Log_Repository();
    $run_id = $log_repo->insert_run([
        'currency' => 'usd',
        'reference_currency' => 'USD',
        'dollar_value' => 1234.56,
        'total_products' => 1,
        'percentage_change' => 0,
        'context' => 'test',
        'note' => 'Lifecycle reset test',
    ]);
    $log_repo->insert_run_item($run_id, [
        'product_id' => 999999,
        'status' => 'updated',
        'old_regular_price' => 1,
        'new_regular_price' => 2,
        'old_sale_price' => 0,
        'new_sale_price' => 0,
        'percentage_change' => 100,
        'reason' => 'Lifecycle reset test',
    ]);
    Cron::schedule();

    $_POST = [
        'reset_prixy' => '1',
        '_wpnonce' => wp_create_nonce('prixy_reset'),
    ];
    ob_start();
    include PRIXY_PLUGIN_DIR . 'admin/partials/prixy-reset-db.php';
    $html = ob_get_clean();
    $_POST = [];

    assert_true(str_contains($html, 'Base de datos'), 'reset partial rendered', 'reset partial output missing');
    assert_true(get_option('prixy_settings', null) === null, 'reset partial deletes settings', 'reset partial left settings');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs")) === 0, 'reset partial deletes runs', 'reset partial left runs');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_run_items")) === 0, 'reset partial deletes run items', 'reset partial left run items');
    assert_true(Cron::get_next_scheduled_time() === null, 'reset partial clears cron', 'reset partial left cron scheduled');

    section('Reactivate after reset');
    Activator::activate();
    wp_cache_flush();
    assert_true(get_option('prixy_settings', null) !== null, 'plugin reactivates after reset', 'plugin did not reactivate after reset');
    assert_true(Cron::get_next_scheduled_time() !== null, 'reactivation schedules cron again', 'reactivation did not schedule cron');
} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage());
}

echo "\n" . str_repeat('-', 50) . "\n";
if ($errors === 0) {
    echo "OK LIFECYCLE FLOWS PASSED\n";
    exit(0);
}

echo "ERR LIFECYCLE FLOWS FAILED ($errors error(s))\n";
exit(1);
