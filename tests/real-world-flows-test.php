<?php
/**
 * Real-world orchestration flow.
 *
 * This intentionally mutates the Local database:
 * reset Prixy data -> activate -> configure -> run real cron over real
 * published WooCommerce products -> rollback -> reset Prixy data again.
 *
 * It keeps a price snapshot and restores any mismatch before exiting.
 *
 * Run: php tests/real-world-flows-test.php
 */

$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: $wp_load\n");
}

define('DOING_CRON', true);
$_SERVER['HTTP_HOST'] = 'wp-flux.local';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';

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

function reset_prixy_real_world_state(): void {
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

function collect_real_product_ids(): array {
    $root_ids = wc_get_products([
        'limit' => -1,
        'status' => 'publish',
        'return' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    $ids = [];
    foreach ($root_ids as $root_id) {
        $ids[] = (int) $root_id;
        $product = wc_get_product($root_id);
        if ($product && $product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $ids[] = (int) $variation_id;
            }
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

function snapshot_prices(array $ids): array {
    $snapshot = [];
    foreach ($ids as $id) {
        $product = wc_get_product($id);
        if (!$product) {
            continue;
        }

        $snapshot[$id] = [
            'regular' => (string) $product->get_regular_price(),
            'sale' => (string) $product->get_sale_price(),
            'price' => (string) $product->get_price(),
        ];
    }
    return $snapshot;
}

function restore_prices(array $snapshot): int {
    $restored = 0;
    foreach ($snapshot as $id => $prices) {
        $product = wc_get_product((int) $id);
        if (!$product) {
            continue;
        }

        if ((string) $product->get_regular_price() !== $prices['regular'] || (string) $product->get_sale_price() !== $prices['sale']) {
            $product->set_regular_price($prices['regular']);
            $product->set_sale_price($prices['sale']);
            $product->save();
            $restored++;
        }
    }
    return $restored;
}

function count_price_differences(array $snapshot): int {
    $diffs = 0;
    foreach ($snapshot as $id => $prices) {
        $product = wc_get_product((int) $id);
        if (!$product) {
            continue;
        }
        if ((string) $product->get_regular_price() !== $prices['regular'] || (string) $product->get_sale_price() !== $prices['sale']) {
            $diffs++;
        }
    }
    return $diffs;
}

function sample_price_differences(array $snapshot, int $limit = 5): array {
    $samples = [];
    foreach ($snapshot as $id => $prices) {
        $product = wc_get_product((int) $id);
        if (!$product) {
            continue;
        }

        $current = [
            'regular' => (string) $product->get_regular_price(),
            'sale' => (string) $product->get_sale_price(),
            'price' => (string) $product->get_price(),
        ];

        if ($current['regular'] !== $prices['regular'] || $current['sale'] !== $prices['sale']) {
            $samples[] = [
                'id' => (int) $id,
                'before' => $prices,
                'after' => $current,
            ];
        }

        if (count($samples) >= $limit) {
            break;
        }
    }

    return $samples;
}

$errors = 0;
$snapshot = [];

global $wpdb, $prixy_container;

try {
    wp_set_current_user(1);

    if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
        throw new RuntimeException('WooCommerce is not loaded.');
    }

    section('Reset and activate');
    reset_prixy_real_world_state();
    Activator::activate();
    $prixy_container = Prixy_Container::build();

    assert_true(get_option('prixy_settings', null) !== null, 'activation created settings', 'activation did not create settings');
    assert_true(Cron::get_next_scheduled_time() !== null, 'activation scheduled cron', 'activation did not schedule cron');

    section('Real product snapshot');
    $product_ids = collect_real_product_ids();
    assert_true(count($product_ids) > 0, 'found real published products/variations: ' . count($product_ids), 'no real published products found');

    if (count($product_ids) === 0) {
        throw new RuntimeException('Cannot run real cron without published products.');
    }

    $snapshot = snapshot_prices($product_ids);
    $priced_before = array_filter($snapshot, fn($p) => floatval($p['regular']) > 0);
    assert_true(count($priced_before) > 0, 'found real priced products/variations: ' . count($priced_before), 'no priced products found');

    section('Configure real cron');
    $api = new API_Client();
    $rate_data = $api->get_rate('usd', 'jsdelivr');
    $current_rate = floatval($rate_data['value'] ?? 0);
    assert_true($current_rate > 0, 'live jsdelivr USD rate = ' . $current_rate, 'could not fetch live jsdelivr rate');

    if ($current_rate <= 0) {
        throw new RuntimeException('Cannot run real cron without a live rate.');
    }

    $origin_rate = round($current_rate / 1.001, 6);
    update_option('prixy_settings', [
        'api_provider' => 'jsdelivr',
        'currency' => 'usd',
        'reference_currency' => 'USD',
        'origin_exchange_rate' => $origin_rate,
        'origin_rate_locked' => '1',
        'threshold' => 0,
        'threshold_max' => 0,
        'update_direction' => 'bidirectional',
        'margin' => 0,
        'rounding_type' => 'none',
        'nearest_to' => '1',
        'exclude_categories' => [],
        'cron_enabled' => 1,
        'interval' => 300,
        'cron_notify_mode' => 'disabled',
        'cron_notify_email' => get_option('admin_email'),
    ]);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();

    assert_true(Admin::is_setup_complete(), 'settings complete onboarding', 'settings did not complete onboarding');
    assert_true(Cron::get_next_scheduled_time() !== null, 'cron scheduled with real settings', 'cron not scheduled with real settings');

    section('Run real cron');
    $before_run_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs"));
    Cron::run_cron();
    $after_run_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs"));

    assert_true($after_run_count > $before_run_count, 'real cron created a run', 'real cron did not create a run');

    $run = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}prixy_runs WHERE context = 'cron' ORDER BY id DESC LIMIT 1");
    assert_true($run !== null, 'found latest cron run', 'cron run not found');

    $run_id = $run ? intval($run->id) : 0;
    $item_count = $run_id > 0 ? intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}prixy_run_items WHERE run_id = %d",
        $run_id
    ))) : 0;
    $changed_count = count_price_differences($snapshot);

    assert_true($run_id > 0, 'cron run_id = ' . $run_id, 'invalid cron run_id');
    assert_true($item_count > 0, 'cron persisted updated items: ' . $item_count, 'cron did not persist updated items');
    assert_true($changed_count > 0, 'real product prices changed during cron: ' . $changed_count, 'no product prices changed during cron');

    section('Rollback real cron');
    $rollback = (new Rollback_Handler(new Log_Repository()))->handle(new Rollback_Run_Command($run_id));
    assert_true(!empty($rollback['success']), 'rollback handler returned success', 'rollback handler failed');

    $diffs_after_rollback = count_price_differences($snapshot);
    if ($diffs_after_rollback > 0) {
        echo "  DIFF " . json_encode(sample_price_differences($snapshot), JSON_UNESCAPED_SLASHES) . "\n";
    }
    assert_true($diffs_after_rollback === 0, 'all real product prices restored by rollback', 'prices still different after rollback: ' . $diffs_after_rollback);

    section('Reset after real flow');
    reset_prixy_real_world_state();
    assert_true(get_option('prixy_settings', null) === null, 'reset removed settings after real flow', 'settings left after reset');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs")) === 0, 'reset removed cron runs after real flow', 'runs left after reset');
    assert_true(Cron::get_next_scheduled_time() === null, 'reset cleared cron schedule after real flow', 'cron still scheduled after reset');
} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage());
} finally {
    if (!empty($snapshot)) {
        $restored = restore_prices($snapshot);
        if ($restored > 0) {
            pass("safety restored product prices manually: {$restored}");
        }
    }

    reset_prixy_real_world_state();
}

echo "\n" . str_repeat('-', 50) . "\n";
if ($errors === 0) {
    echo "OK REAL WORLD FLOWS PASSED\n";
    exit(0);
}

echo "ERR REAL WORLD FLOWS FAILED ($errors error(s))\n";
exit(1);
