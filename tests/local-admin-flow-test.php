<?php
/**
 * End-to-end local smoke test for the admin flow.
 *
 * Creates temporary WooCommerce products, runs the real pricing flow against
 * the Local database and live jsdelivr rates, rolls the changes back, then
 * deletes all fixtures and test logs.
 *
 * Run: php tests/local-admin-flow-test.php
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

$previous_error_reporting = error_reporting();
set_error_handler(function ($severity, $message) {
    if (str_contains($message, 'Constant DB_HOST already defined')) {
        return true;
    }
    return false;
});
require_once $wp_load;
restore_error_handler();
error_reporting($previous_error_reporting);

if (file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
}

function pass(string $msg): void { echo "  OK  $msg\n"; }
function fail(string $msg): void { echo "  ERR $msg\n"; global $errors; $errors++; }
function section(string $title): void { echo "\n=== $title ===\n"; }
function assert_true(bool $condition, string $pass, string $fail): void {
    $condition ? pass($pass) : fail($fail);
}

class Local_Fixture_Product_Repository extends Product_Repository
{
    public function __construct(private array $fixture_ids)
    {
        parent::__construct();
    }

    public function count_all_products(): int
    {
        return count($this->fixture_ids);
    }

    public function get_product_ids_batch(int $limit = 500, int $offset = 0): array
    {
        return array_slice($this->fixture_ids, $offset, $limit);
    }
}

$errors = 0;
$created_product_ids = [];
$root_product_ids = [];
$created_term_ids = [];
$created_run_ids = [];
$original_settings = get_option('prixy_settings', []);

global $wpdb;

try {
    wp_set_current_user(1);

    if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
        throw new RuntimeException('WooCommerce is not loaded.');
    }

    section('Admin page routes');

    $routes = [
        'prixy_settings'       => [Admin::class, 'render_overview'],
        'prixy_configuration'  => [Admin::class, 'render_settings'],
        'prixy_automation'     => [Admin::class, 'render_automation'],
        'prixy_dashboard'      => [Admin::class, 'render_dashboard'],
        'prixy_logs'           => [Admin::class, 'render_logs'],
        'prixy_reset'          => [Admin::class, 'render_reset_db'],
    ];

    foreach ($routes as $slug => $callback) {
        $_GET['page'] = $slug;
        $_POST = [];
        ob_start();
        try {
            call_user_func($callback);
            $html = ob_get_clean();
            assert_true(strlen($html) > 500, "$slug rendered (" . strlen($html) . " bytes)", "$slug rendered empty output");
        } catch (Throwable $e) {
            ob_end_clean();
            fail("$slug failed: " . $e->getMessage());
        }
    }

    section('Live API');

    $api = new API_Client();
    $rate_data = $api->get_rate('usd', 'jsdelivr');
    $current_rate = floatval($rate_data['value'] ?? 0);
    assert_true($current_rate > 0, "jsdelivr live USD rate = $current_rate", 'jsdelivr live USD rate failed');

    if ($current_rate <= 0) {
        throw new RuntimeException('Cannot continue without a live rate.');
    }

    section('Create local fixtures');

    $stamp = 'prixy-flow-' . time();
    $normal_term = wp_insert_term($stamp . '-normal', 'product_cat');
    $excluded_term = wp_insert_term($stamp . '-excluded', 'product_cat');

    if (is_wp_error($normal_term) || is_wp_error($excluded_term)) {
        throw new RuntimeException('Could not create product categories.');
    }

    $normal_term_id = intval($normal_term['term_id']);
    $excluded_term_id = intval($excluded_term['term_id']);
    $created_term_ids[] = $normal_term_id;
    $created_term_ids[] = $excluded_term_id;

    $normal = new WC_Product_Simple();
    $normal->set_name('Prixy Flow Product Normal');
    $normal->set_status('publish');
    $normal->set_sku($stamp . '-normal');
    $normal->set_regular_price('1000');
    $normal->set_sale_price('900');
    $normal->set_category_ids([$normal_term_id]);
    $normal_id = $normal->save();
    $created_product_ids[] = $normal_id;
    $root_product_ids[] = $normal_id;

    $no_sale = new WC_Product_Simple();
    $no_sale->set_name('Prixy Flow Product No Sale');
    $no_sale->set_status('publish');
    $no_sale->set_sku($stamp . '-no-sale');
    $no_sale->set_regular_price('1500');
    $no_sale->set_category_ids([$normal_term_id]);
    $no_sale_id = $no_sale->save();
    $created_product_ids[] = $no_sale_id;
    $root_product_ids[] = $no_sale_id;

    $excluded = new WC_Product_Simple();
    $excluded->set_name('Prixy Flow Product Excluded');
    $excluded->set_status('publish');
    $excluded->set_sku($stamp . '-excluded');
    $excluded->set_regular_price('2000');
    $excluded->set_sale_price('1800');
    $excluded->set_category_ids([$excluded_term_id]);
    $excluded_id = $excluded->save();
    $created_product_ids[] = $excluded_id;
    $root_product_ids[] = $excluded_id;

    $zero_price = new WC_Product_Simple();
    $zero_price->set_name('Prixy Flow Product Zero Price');
    $zero_price->set_status('publish');
    $zero_price->set_sku($stamp . '-zero');
    $zero_price->set_category_ids([$normal_term_id]);
    $zero_price_id = $zero_price->save();
    $created_product_ids[] = $zero_price_id;
    $root_product_ids[] = $zero_price_id;

    $variable = new WC_Product_Variable();
    $variable->set_name('Prixy Flow Variable Product');
    $variable->set_status('publish');
    $variable->set_sku($stamp . '-variable');
    $variable->set_category_ids([$normal_term_id]);
    $variable_id = $variable->save();
    $created_product_ids[] = $variable_id;
    $root_product_ids[] = $variable_id;

    $variation_sale = new WC_Product_Variation();
    $variation_sale->set_parent_id($variable_id);
    $variation_sale->set_status('publish');
    $variation_sale->set_sku($stamp . '-var-sale');
    $variation_sale->set_regular_price('3000');
    $variation_sale->set_sale_price('2500');
    $variation_sale->set_attributes(['size' => 'small']);
    $variation_sale_id = $variation_sale->save();
    $created_product_ids[] = $variation_sale_id;

    $variation_no_sale = new WC_Product_Variation();
    $variation_no_sale->set_parent_id($variable_id);
    $variation_no_sale->set_status('publish');
    $variation_no_sale->set_sku($stamp . '-var-no-sale');
    $variation_no_sale->set_regular_price('1200');
    $variation_no_sale->set_attributes(['size' => 'large']);
    $variation_no_sale_id = $variation_no_sale->save();
    $created_product_ids[] = $variation_no_sale_id;

    WC_Product_Variable::sync($variable_id);

    assert_true(
        $normal_id > 0 && $no_sale_id > 0 && $excluded_id > 0 && $zero_price_id > 0 && $variable_id > 0 && $variation_sale_id > 0 && $variation_no_sale_id > 0,
        'created simple, excluded, zero-price, variable and variation fixtures',
        'not all fixture products were created'
    );

    section('Setup baseline');

    $origin_rate = round($current_rate * 0.75, 4);
    $settings = array_merge($original_settings, [
        'api_provider'          => 'jsdelivr',
        'currency'              => 'usd',
        'reference_currency'    => 'USD',
        'origin_exchange_rate'  => $origin_rate,
        'origin_rate_locked'    => '1',
        'threshold'             => 0,
        'threshold_max'         => 0,
        'update_direction'      => 'bidirectional',
        'margin'                => 0,
        'rounding_type'         => 'integer',
        'nearest_to'            => '1',
        'exclude_categories'    => [$excluded_term_id],
    ]);
    update_option('prixy_settings', $settings);
    wp_cache_delete('prixy_settings', 'options');

    $log_repo = new Log_Repository();
    $baseline_run_id = $log_repo->insert_run([
        'currency'           => 'usd',
        'reference_currency' => 'USD',
        'dollar_value'       => $origin_rate,
        'total_products'     => 2,
        'percentage_change'  => null,
        'context'            => 'setup',
        'note'               => 'Local admin flow test baseline',
        'user_id'            => get_current_user_id(),
    ]);
    $created_run_ids[] = $baseline_run_id;

    foreach ([
        $normal_id => [1000, 900],
        $no_sale_id => [1500, 0],
        $excluded_id => [2000, 1800],
        $variation_sale_id => [3000, 2500],
        $variation_no_sale_id => [1200, 0],
    ] as $product_id => [$regular, $sale]) {
        $log_repo->insert_run_item($baseline_run_id, [
            'product_id'        => $product_id,
            'status'            => 'updated',
            'old_regular_price' => 0,
            'new_regular_price' => $regular,
            'old_sale_price'    => 0,
            'new_sale_price'    => $sale,
            'percentage_change' => null,
            'reason'            => 'Local admin flow baseline',
        ]);
    }

    assert_true(Admin::is_setup_complete(), 'admin setup reports complete after locked baseline', 'admin setup did not report complete');

    section('Simulate update');

    $fixture_repo = new Local_Fixture_Product_Repository($root_product_ids);
    $handler = new Update_Prices_Handler(
        new Settings_Repository(),
        new API_Client(),
        new Batch_Processor(
            $fixture_repo,
            new Price_Calculation_Engine([new Ratio_Rule(), new Direction_Rule(), new Margin_Rule(), new Rounding_Rule()]),
            $log_repo
        ),
        $fixture_repo,
        new Logger($log_repo),
        new Threshold_Policy(),
        $log_repo
    );

    $sim = $handler->handle(new Update_Prices_Command(batch: 0, simulate: true, context: 'manual'));
    assert_true(empty($sim['error']), 'simulation returned without error', 'simulation error: ' . ($sim['message'] ?? $sim['error'] ?? 'unknown'));
    assert_true(count($sim['changes'] ?? []) === 6, 'simulation returned simple, excluded, zero-price and variation cases', 'simulation did not return all product cases');
    assert_true(floatval(wc_get_product($normal_id)->get_regular_price()) === 1000.0, 'simulation did not persist price changes', 'simulation changed product price');

    section('Apply update');

    $update = $handler->handle(new Update_Prices_Command(batch: 0, simulate: false, context: 'manual'));
    $update_run_id = intval($update['run_id'] ?? 0);
    if ($update_run_id > 0) {
        $created_run_ids[] = $update_run_id;
    }

    $normal_after = wc_get_product($normal_id);
    $no_sale_after = wc_get_product($no_sale_id);
    $excluded_after = wc_get_product($excluded_id);
    $zero_price_after = wc_get_product($zero_price_id);
    $variation_sale_after = wc_get_product($variation_sale_id);
    $variation_no_sale_after = wc_get_product($variation_no_sale_id);
    $normal_new_price = floatval($normal_after->get_regular_price());
    $normal_new_sale = floatval($normal_after->get_sale_price());
    $no_sale_new_price = floatval($no_sale_after->get_regular_price());
    $no_sale_new_sale = floatval($no_sale_after->get_sale_price());
    $excluded_new_price = floatval($excluded_after->get_regular_price());
    $excluded_new_sale = floatval($excluded_after->get_sale_price());
    $zero_price_new_price = floatval($zero_price_after->get_regular_price());
    $variation_sale_new_price = floatval($variation_sale_after->get_regular_price());
    $variation_sale_new_sale = floatval($variation_sale_after->get_sale_price());
    $variation_no_sale_new_price = floatval($variation_no_sale_after->get_regular_price());
    $variation_no_sale_new_sale = floatval($variation_no_sale_after->get_sale_price());

    assert_true(empty($update['error']), 'update returned without error', 'update error: ' . ($update['message'] ?? $update['error'] ?? 'unknown'));
    assert_true($update_run_id > 0, "update created run_id=$update_run_id", 'update did not create a run');
    assert_true($normal_new_price > 1000.0, "normal product updated to $normal_new_price", "normal product was not updated ($normal_new_price)");
    assert_true($normal_new_sale > 900.0 && $normal_new_sale < $normal_new_price, "normal sale updated to $normal_new_sale", "normal sale price is wrong ($normal_new_sale)");
    assert_true($no_sale_new_price > 1500.0, "no-sale product updated to $no_sale_new_price", "no-sale product was not updated ($no_sale_new_price)");
    assert_true($no_sale_new_sale === 0.0, 'no-sale product kept empty sale price', "no-sale product sale price changed to $no_sale_new_sale");
    assert_true($excluded_new_price === 2000.0, 'excluded product stayed unchanged', "excluded product changed to $excluded_new_price");
    assert_true($excluded_new_sale === 1800.0, 'excluded sale price stayed unchanged', "excluded sale price changed to $excluded_new_sale");
    assert_true($zero_price_new_price === 0.0, 'zero-price product stayed unchanged', "zero-price product changed to $zero_price_new_price");
    assert_true($variation_sale_new_price > 3000.0, "variation with sale updated to $variation_sale_new_price", "variation with sale was not updated ($variation_sale_new_price)");
    assert_true($variation_sale_new_sale > 2500.0 && $variation_sale_new_sale < $variation_sale_new_price, "variation sale updated to $variation_sale_new_sale", "variation sale price is wrong ($variation_sale_new_sale)");
    assert_true($variation_no_sale_new_price > 1200.0, "variation without sale updated to $variation_no_sale_new_price", "variation without sale was not updated ($variation_no_sale_new_price)");
    assert_true($variation_no_sale_new_sale === 0.0, 'variation without sale kept empty sale price', "variation without sale price changed to $variation_no_sale_new_sale");

    $items = $log_repo->get_run_items($update_run_id, 20);
    assert_true(count($items) === 4, 'update run persisted only changed items', 'update run item count expected 4, got ' . count($items));

    section('Rollback update');

    $rollback = (new Rollback_Handler($log_repo))->handle(new Rollback_Run_Command($update_run_id));
    $normal_reverted = floatval(wc_get_product($normal_id)->get_regular_price());
    $normal_sale_reverted = floatval(wc_get_product($normal_id)->get_sale_price());
    $no_sale_reverted = floatval(wc_get_product($no_sale_id)->get_regular_price());
    $excluded_reverted = floatval(wc_get_product($excluded_id)->get_regular_price());
    $variation_sale_reverted = floatval(wc_get_product($variation_sale_id)->get_regular_price());
    $variation_sale_sale_reverted = floatval(wc_get_product($variation_sale_id)->get_sale_price());
    $variation_no_sale_reverted = floatval(wc_get_product($variation_no_sale_id)->get_regular_price());

    assert_true(!empty($rollback['success']), 'rollback completed successfully', 'rollback failed');
    assert_true($normal_reverted === 1000.0, 'normal product reverted to original price', "normal product rollback price is $normal_reverted");
    assert_true($normal_sale_reverted === 900.0, 'normal sale reverted to original price', "normal sale rollback price is $normal_sale_reverted");
    assert_true($no_sale_reverted === 1500.0, 'no-sale product reverted to original price', "no-sale rollback price is $no_sale_reverted");
    assert_true($excluded_reverted === 2000.0, 'excluded product remained original after rollback', "excluded product rollback price is $excluded_reverted");
    assert_true($variation_sale_reverted === 3000.0, 'variation with sale reverted to original price', "variation rollback price is $variation_sale_reverted");
    assert_true($variation_sale_sale_reverted === 2500.0, 'variation sale reverted to original price', "variation sale rollback price is $variation_sale_sale_reverted");
    assert_true($variation_no_sale_reverted === 1200.0, 'variation without sale reverted to original price', "variation no-sale rollback price is $variation_no_sale_reverted");
} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage());
} finally {
    section('Cleanup');

    foreach (array_unique(array_filter($created_run_ids)) as $run_id) {
        $wpdb->delete($wpdb->prefix . 'prixy_run_items', ['run_id' => intval($run_id)]);
        $wpdb->delete($wpdb->prefix . 'prixy_runs', ['id' => intval($run_id)]);
        pass("deleted run $run_id");
    }

    foreach (array_unique(array_filter($created_product_ids)) as $product_id) {
        wp_delete_post($product_id, true);
        pass("deleted product $product_id");
    }

    foreach (array_unique(array_filter($created_term_ids)) as $term_id) {
        wp_delete_term($term_id, 'product_cat');
        pass("deleted category $term_id");
    }

    update_option('prixy_settings', $original_settings);
    wp_cache_delete('prixy_settings', 'options');
    pass('restored original prixy_settings');
}

echo "\n" . str_repeat('-', 50) . "\n";
if ($errors === 0) {
    echo "OK LOCAL ADMIN FLOW PASSED\n";
    exit(0);
}

echo "ERR LOCAL ADMIN FLOW FAILED ($errors error(s))\n";
exit(1);
