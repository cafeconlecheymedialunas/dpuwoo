<?php
/**
 * Real admin user actions flow.
 *
 * Exercises the AJAX controller actions as a real admin user with nonce checks,
 * live API calls, real WooCommerce products, real DB writes, rollback and reset.
 *
 * Run: php tests/user-actions-real-test.php
 */

$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: $wp_load\n");
}

define('DOING_AJAX', true);
$_SERVER['HTTP_HOST'] = 'wp-flux.local';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';

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

class Prixy_Ajax_Test_Done extends Exception {}

function prixy_ajax_die_handler(): callable {
    return function ($message = '', $title = '', $args = []) {
        throw new Prixy_Ajax_Test_Done();
    };
}

function reset_prixy_user_action_state(): void {
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

function collect_real_product_ids_for_user_actions(): array {
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

function snapshot_user_action_prices(array $ids): array {
    $snapshot = [];
    foreach ($ids as $id) {
        $product = wc_get_product($id);
        if (!$product) {
            continue;
        }

        $snapshot[$id] = [
            'regular' => (string) $product->get_regular_price(),
            'sale' => (string) $product->get_sale_price(),
        ];
    }
    return $snapshot;
}

function restore_user_action_prices(array $snapshot): int {
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

function count_user_action_price_differences(array $snapshot): int {
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

function call_prixy_ajax(Ajax_Controller $controller, string $method, array $post = []): array {
    $_POST = array_merge(['nonce' => wp_create_nonce('prixy_ajax_nonce')], $post);
    $_REQUEST = $_POST;
    add_filter('wp_die_handler', 'prixy_ajax_die_handler');
    add_filter('wp_die_ajax_handler', 'prixy_ajax_die_handler');

    ob_start();
    try {
        $controller->{$method}();
    } catch (Prixy_Ajax_Test_Done) {
        // Expected from wp_send_json().
    } finally {
        remove_filter('wp_die_handler', 'prixy_ajax_die_handler');
        remove_filter('wp_die_ajax_handler', 'prixy_ajax_die_handler');
        $_POST = [];
        $_REQUEST = [];
    }

    $raw = trim(ob_get_clean());
    $json = json_decode($raw, true);

    if (!is_array($json)) {
        return ['success' => false, 'data' => ['message' => 'Invalid JSON response', 'raw' => $raw]];
    }

    return $json;
}

function assert_ajax_success(Ajax_Controller $controller, string $method, array $post, string $label): array {
    $response = call_prixy_ajax($controller, $method, $post);
    assert_true(!empty($response['success']), "$label returned success", "$label failed: " . json_encode($response, JSON_UNESCAPED_SLASHES));
    return $response;
}

$errors = 0;
$snapshot = [];

global $wpdb, $prixy_container;

try {
    wp_set_current_user(1);

    if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
        throw new RuntimeException('WooCommerce is not loaded.');
    }

    section('Reset and activation');
    reset_prixy_user_action_state();
    Activator::activate();
    $prixy_container = Prixy_Container::build();
    /** @var Ajax_Controller $controller */
    $controller = $prixy_container->get('ajax_controller');

    assert_true(current_user_can('manage_options'), 'test user has admin permissions', 'test user is not admin');
    assert_true(get_option('prixy_settings', null) !== null, 'activation created settings', 'activation did not create settings');

    section('Real product snapshot');
    $product_ids = collect_real_product_ids_for_user_actions();
    assert_true(count($product_ids) > 0, 'found real products/variations: ' . count($product_ids), 'no published products found');
    $snapshot = snapshot_user_action_prices($product_ids);

    section('Read/API user actions');
    $providers = assert_ajax_success($controller, 'handle_get_providers_info', [], 'get providers info');
    assert_true(!empty($providers['data']['providers']['jsdelivr']), 'providers include jsdelivr', 'providers missing jsdelivr');

    $currencies = assert_ajax_success($controller, 'handle_get_currencies', ['provider' => 'jsdelivr'], 'get currencies');
    assert_true(($currencies['data']['count'] ?? 0) > 0, 'currencies returned rows', 'currencies returned empty list');

    $rate = assert_ajax_success($controller, 'handle_get_current_rate', ['provider' => 'jsdelivr', 'currency' => 'usd'], 'get current rate');
    $current_rate = floatval($rate['data']['rate'] ?? 0);
    assert_true($current_rate > 0, 'current rate is positive: ' . $current_rate, 'current rate is invalid');

    assert_ajax_success($controller, 'handle_test_api_connection', ['provider' => 'jsdelivr'], 'test API connection');
    assert_ajax_success($controller, 'handle_get_dashboard_stats', [], 'get dashboard stats');
    assert_ajax_success($controller, 'handle_get_setup_progress', [], 'get setup progress');

    $preview = assert_ajax_success($controller, 'handle_preview_products', ['rate' => $current_rate], 'preview products');
    assert_true(is_array($preview['data']['products'] ?? null), 'preview returned products array', 'preview missing products array');

    section('Setup user actions');
    $baseline = assert_ajax_success($controller, 'handle_initialize_baseline', [], 'initialize baseline');
    assert_true(floatval($baseline['data']['value'] ?? 0) > 0, 'baseline initialized value', 'baseline value missing');

    $first_setup = assert_ajax_success($controller, 'handle_first_setup_batch', [
        'offset' => 0,
        'limit' => 5,
        'rate' => $current_rate,
    ], 'first setup batch');
    assert_true(is_array($first_setup['data']['products'] ?? null), 'first setup batch returned products array', 'first setup missing products');

    $save_origin = assert_ajax_success($controller, 'handle_save_origin_rate', ['value' => $current_rate], 'save origin rate');
    assert_true(!empty($save_origin['data']['redirect']), 'save origin returns redirect', 'save origin missing redirect');

    section('Manual update user actions');
    $settings = (array) get_option('prixy_settings', []);
    $settings = array_merge($settings, [
        'api_provider' => 'jsdelivr',
        'currency' => 'usd',
        'reference_currency' => 'USD',
        'origin_exchange_rate' => round($current_rate / 1.001, 6),
        'origin_rate_locked' => '1',
        'threshold' => 0,
        'threshold_max' => 0,
        'update_direction' => 'bidirectional',
        'margin' => 0,
        'rounding_type' => 'none',
        'nearest_to' => '1',
        'exclude_categories' => [],
        'cron_enabled' => 0,
        'cron_notify_mode' => 'disabled',
    ]);
    update_option('prixy_settings', $settings);
    wp_cache_delete('prixy_settings', 'options');
    $prixy_container->get('settings')->refresh();

    $simulate = assert_ajax_success($controller, 'handle_simulate_batch', ['batch' => 0], 'simulate batch');
    assert_true(count_user_action_price_differences($snapshot) === 0, 'simulate did not change real prices', 'simulate changed real prices');
    assert_true(!empty($simulate['data']['summary']['simulated']), 'simulate summary marks simulated', 'simulate summary missing simulated flag');

    $update = assert_ajax_success($controller, 'handle_update_batch', ['batch' => 0], 'update batch');
    $run_id = intval($update['data']['run_id'] ?? 0);
    assert_true($run_id > 0, 'update created run_id=' . $run_id, 'update did not create run');
    assert_true(count_user_action_price_differences($snapshot) > 0, 'update changed real prices', 'update did not change real prices');

    $runs = assert_ajax_success($controller, 'handle_get_runs', [], 'get runs');
    assert_true(count($runs['data'] ?? []) > 0, 'get runs returned rows', 'get runs returned empty');

    $items = assert_ajax_success($controller, 'handle_get_run_items', ['run_id' => $run_id], 'get run items');
    assert_true(count($items['data'] ?? []) > 0, 'get run items returned rows', 'get run items returned empty');

    $first_item_id = intval(($items['data'][0]->id ?? null) ?: ($items['data'][0]['id'] ?? 0));
    assert_true($first_item_id > 0, 'found first run item id=' . $first_item_id, 'missing first item id');
    if ($first_item_id > 0) {
        assert_ajax_success($controller, 'handle_revert_item', ['log_id' => $first_item_id], 'revert item');
    }

    assert_ajax_success($controller, 'handle_revert_run', ['run_id' => $run_id], 'revert run');
    assert_true(count_user_action_price_differences($snapshot) === 0, 'revert run restored all real prices', 'prices still differ after revert run');

    section('Reset after user actions');
    reset_prixy_user_action_state();
    assert_true(get_option('prixy_settings', null) === null, 'reset removed settings', 'reset left settings');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs")) === 0, 'reset removed runs', 'reset left runs');
    assert_true(Cron::get_next_scheduled_time() === null, 'reset cleared cron', 'reset left cron scheduled');
} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage());
} finally {
    if (!empty($snapshot)) {
        $restored = restore_user_action_prices($snapshot);
        if ($restored > 0) {
            pass("safety restored product prices manually: {$restored}");
        }
    }
    reset_prixy_user_action_state();
}

echo "\n" . str_repeat('-', 50) . "\n";
if ($errors === 0) {
    echo "OK USER ACTIONS REAL PASSED\n";
    exit(0);
}

echo "ERR USER ACTIONS REAL FAILED ($errors error(s))\n";
exit(1);
