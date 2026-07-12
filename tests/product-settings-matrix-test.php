<?php
/**
 * Product type + settings matrix smoke test.
 *
 * Uses real WooCommerce products in the Local database for product type
 * behaviour, and a deterministic settings matrix for calculation rules.
 *
 * Run: php tests/product-settings-matrix-test.php
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

class Matrix_Product_Repository extends Product_Repository
{
    public function __construct(private array $root_ids)
    {
        parent::__construct();
    }

    public function count_all_products(): int
    {
        return count($this->root_ids);
    }

    public function get_product_ids_batch(int $limit = 500, int $offset = 0): array
    {
        return array_slice($this->root_ids, $offset, $limit);
    }
}

class Matrix_Fixed_API_Client extends API_Client
{
    public function __construct(private float $rate) {}

    public function get_rate($type = 'usd', $provider_key = null)
    {
        return [
            'value' => $this->rate,
            'buy' => $this->rate,
            'sell' => $this->rate,
            'provider' => 'matrix-fixed',
            'base_currency' => 'ARS',
            'target_currency' => strtoupper((string) $type),
            'valid' => true,
        ];
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

    section('Create product type fixtures');

    $stamp = 'prixy-matrix-' . time();
    $normal_term = wp_insert_term($stamp . '-normal', 'product_cat');
    $excluded_term = wp_insert_term($stamp . '-excluded', 'product_cat');
    if (is_wp_error($normal_term) || is_wp_error($excluded_term)) {
        throw new RuntimeException('Could not create categories.');
    }
    $normal_term_id = intval($normal_term['term_id']);
    $excluded_term_id = intval($excluded_term['term_id']);
    $created_term_ids[] = $normal_term_id;
    $created_term_ids[] = $excluded_term_id;

    $make_simple = function (string $name, string $sku, string $regular, string $sale = '', array $cats = []) use (&$created_product_ids, &$root_product_ids): int {
        $p = new WC_Product_Simple();
        $p->set_name($name);
        $p->set_status('publish');
        $p->set_sku($sku);
        if ($regular !== '') $p->set_regular_price($regular);
        if ($sale !== '') $p->set_sale_price($sale);
        if ($cats) $p->set_category_ids($cats);
        $id = $p->save();
        $created_product_ids[] = $id;
        $root_product_ids[] = $id;
        return $id;
    };

    $simple_sale_id = $make_simple('Matrix Simple Sale', "$stamp-simple-sale", '1000', '800', [$normal_term_id]);
    $simple_no_sale_id = $make_simple('Matrix Simple No Sale', "$stamp-simple-no-sale", '1500', '', [$normal_term_id]);
    $zero_price_id = $make_simple('Matrix Zero Price', "$stamp-zero", '', '', [$normal_term_id]);
    $excluded_id = $make_simple('Matrix Excluded', "$stamp-excluded", '2000', '1600', [$excluded_term_id]);
    $group_child_id = $make_simple('Matrix Group Child', "$stamp-group-child", '500', '', [$normal_term_id]);

    $virtual = new WC_Product_Simple();
    $virtual->set_name('Matrix Virtual');
    $virtual->set_status('publish');
    $virtual->set_sku("$stamp-virtual");
    $virtual->set_regular_price('700');
    $virtual->set_virtual(true);
    $virtual->set_category_ids([$normal_term_id]);
    $virtual_id = $virtual->save();
    $created_product_ids[] = $virtual_id;
    $root_product_ids[] = $virtual_id;

    $downloadable = new WC_Product_Simple();
    $downloadable->set_name('Matrix Downloadable');
    $downloadable->set_status('publish');
    $downloadable->set_sku("$stamp-downloadable");
    $downloadable->set_regular_price('900');
    $downloadable->set_downloadable(true);
    $downloadable->set_category_ids([$normal_term_id]);
    $downloadable_id = $downloadable->save();
    $created_product_ids[] = $downloadable_id;
    $root_product_ids[] = $downloadable_id;

    $external = new WC_Product_External();
    $external->set_name('Matrix External');
    $external->set_status('publish');
    $external->set_sku("$stamp-external");
    $external->set_regular_price('1100');
    $external->set_product_url('https://example.com/matrix');
    $external->set_button_text('Buy');
    $external->set_category_ids([$normal_term_id]);
    $external_id = $external->save();
    $created_product_ids[] = $external_id;
    $root_product_ids[] = $external_id;

    $grouped = new WC_Product_Grouped();
    $grouped->set_name('Matrix Grouped');
    $grouped->set_status('publish');
    $grouped->set_sku("$stamp-grouped");
    $grouped_id = $grouped->save();
    $grouped->set_children([$group_child_id]);
    $grouped->save();
    $created_product_ids[] = $grouped_id;
    $root_product_ids[] = $grouped_id;

    $variable = new WC_Product_Variable();
    $variable->set_name('Matrix Variable');
    $variable->set_status('publish');
    $variable->set_sku("$stamp-variable");
    $variable->set_category_ids([$normal_term_id]);
    $variable_id = $variable->save();
    $created_product_ids[] = $variable_id;
    $root_product_ids[] = $variable_id;

    $variation_sale = new WC_Product_Variation();
    $variation_sale->set_parent_id($variable_id);
    $variation_sale->set_status('publish');
    $variation_sale->set_sku("$stamp-var-sale");
    $variation_sale->set_regular_price('3000');
    $variation_sale->set_sale_price('2500');
    $variation_sale->set_attributes(['size' => 'small']);
    $variation_sale_id = $variation_sale->save();
    $created_product_ids[] = $variation_sale_id;

    $variation_no_sale = new WC_Product_Variation();
    $variation_no_sale->set_parent_id($variable_id);
    $variation_no_sale->set_status('publish');
    $variation_no_sale->set_sku("$stamp-var-no-sale");
    $variation_no_sale->set_regular_price('1200');
    $variation_no_sale->set_attributes(['size' => 'large']);
    $variation_no_sale_id = $variation_no_sale->save();
    $created_product_ids[] = $variation_no_sale_id;
    WC_Product_Variable::sync($variable_id);

    $excluded_variable = new WC_Product_Variable();
    $excluded_variable->set_name('Matrix Excluded Variable');
    $excluded_variable->set_status('publish');
    $excluded_variable->set_sku("$stamp-excluded-variable");
    $excluded_variable->set_category_ids([$excluded_term_id]);
    $excluded_variable_id = $excluded_variable->save();
    $created_product_ids[] = $excluded_variable_id;
    $root_product_ids[] = $excluded_variable_id;

    $excluded_variation = new WC_Product_Variation();
    $excluded_variation->set_parent_id($excluded_variable_id);
    $excluded_variation->set_status('publish');
    $excluded_variation->set_sku("$stamp-excluded-var");
    $excluded_variation->set_regular_price('4000');
    $excluded_variation->set_sale_price('3000');
    $excluded_variation->set_attributes(['size' => 'excluded']);
    $excluded_variation_id = $excluded_variation->save();
    $created_product_ids[] = $excluded_variation_id;
    WC_Product_Variable::sync($excluded_variable_id);

    assert_true(count($created_product_ids) === 14, 'created all product type fixtures', 'fixture count mismatch: ' . count($created_product_ids));

    section('Apply real product type update');

    $previous_rate = 1000.0;
    $current_rate = 1250.0;
    $settings = array_merge($original_settings, [
        'api_provider' => 'jsdelivr',
        'currency' => 'usd',
        'reference_currency' => 'USD',
        'origin_exchange_rate' => $previous_rate,
        'origin_rate_locked' => '1',
        'threshold' => 0,
        'threshold_max' => 0,
        'update_direction' => 'bidirectional',
        'margin' => 0,
        'rounding_type' => 'integer',
        'nearest_to' => '1',
        'exclude_categories' => [$excluded_term_id],
    ]);
    update_option('prixy_settings', $settings);
    wp_cache_delete('prixy_settings', 'options');

    $log_repo = new Log_Repository();
    $baseline_run_id = $log_repo->insert_run([
        'currency' => 'usd',
        'reference_currency' => 'USD',
        'dollar_value' => $previous_rate,
        'total_products' => count($created_product_ids),
        'percentage_change' => null,
        'context' => 'setup',
        'note' => 'Product settings matrix baseline',
        'user_id' => get_current_user_id(),
    ]);
    $created_run_ids[] = $baseline_run_id;

    $baseline_prices = [
        $simple_sale_id => [1000, 800],
        $simple_no_sale_id => [1500, 0],
        $excluded_id => [2000, 1600],
        $group_child_id => [500, 0],
        $virtual_id => [700, 0],
        $downloadable_id => [900, 0],
        $external_id => [1100, 0],
        $variation_sale_id => [3000, 2500],
        $variation_no_sale_id => [1200, 0],
        $excluded_variation_id => [4000, 3000],
    ];

    foreach ($baseline_prices as $product_id => [$regular, $sale]) {
        $log_repo->insert_run_item($baseline_run_id, [
            'product_id' => $product_id,
            'status' => 'updated',
            'old_regular_price' => 0,
            'new_regular_price' => $regular,
            'old_sale_price' => 0,
            'new_sale_price' => $sale,
            'percentage_change' => null,
            'reason' => 'Matrix baseline',
        ]);
    }

    $fixture_repo = new Matrix_Product_Repository($root_product_ids);
    $handler = new Update_Prices_Handler(
        new Settings_Repository(),
        new Matrix_Fixed_API_Client($current_rate),
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

    $result = $handler->handle(new Update_Prices_Command(batch: 0, simulate: false, context: 'manual'));
    $run_id = intval($result['run_id'] ?? 0);
    if ($run_id > 0) $created_run_ids[] = $run_id;

    assert_true(empty($result['error']), 'product type update completed without handler error', 'handler error: ' . ($result['message'] ?? $result['error'] ?? 'unknown'));
    assert_true($run_id > 0, "product type update created run $run_id", 'product type update did not create run');

    $expect_prices = [
        $simple_sale_id => [1250, 1000, 'simple with sale'],
        $simple_no_sale_id => [1875, 0, 'simple without sale'],
        $group_child_id => [625, 0, 'grouped child simple'],
        $virtual_id => [875, 0, 'virtual simple'],
        $downloadable_id => [1125, 0, 'downloadable simple'],
        $external_id => [1375, 0, 'external product'],
        $variation_sale_id => [3750, 3125, 'variation with sale'],
        $variation_no_sale_id => [1500, 0, 'variation without sale'],
    ];

    foreach ($expect_prices as $product_id => [$regular, $sale, $label]) {
        $product = wc_get_product($product_id);
        assert_true(floatval($product->get_regular_price()) === (float) $regular, "$label regular price = $regular", "$label regular price expected $regular got " . $product->get_regular_price());
        assert_true(floatval($product->get_sale_price()) === (float) $sale, "$label sale price = $sale", "$label sale price expected $sale got " . $product->get_sale_price());
    }

    assert_true(floatval(wc_get_product($excluded_id)->get_regular_price()) === 2000.0, 'excluded product regular stayed unchanged', 'excluded product regular changed');
    assert_true(floatval(wc_get_product($excluded_variation_id)->get_regular_price()) === 4000.0, 'excluded variation regular stayed unchanged', 'excluded variation regular changed');
    assert_true(floatval(wc_get_product($excluded_variation_id)->get_sale_price()) === 3000.0, 'excluded variation sale stayed unchanged', 'excluded variation sale changed');
    assert_true(floatval(wc_get_product($zero_price_id)->get_regular_price()) === 0.0, 'zero-price product stayed unchanged', 'zero-price product changed');
    assert_true(floatval(wc_get_product($grouped_id)->get_regular_price()) === 0.0, 'grouped parent stayed price-less', 'grouped parent got a price');

    $items = $log_repo->get_run_items($run_id, 50);
    assert_true(count($items) === 8, 'only changed priced products/variations were logged', 'expected 8 update items got ' . count($items));

    section('Rollback product type update');

    $rollback = (new Rollback_Handler($log_repo))->handle(new Rollback_Run_Command($run_id));
    assert_true(!empty($rollback['success']), 'rollback succeeded', 'rollback failed');

    foreach ($baseline_prices as $product_id => [$regular, $sale]) {
        $product = wc_get_product($product_id);
        assert_true(floatval($product->get_regular_price()) === (float) $regular, "rollback regular restored for $product_id", "rollback regular mismatch for $product_id");
        assert_true(floatval($product->get_sale_price()) === (float) $sale, "rollback sale restored for $product_id", "rollback sale mismatch for $product_id");
    }

    section('Settings calculation matrix');

    $engine = new Price_Calculation_Engine([new Ratio_Rule(), new Direction_Rule(), new Margin_Rule(), new Rounding_Rule()]);
    $policy = new Threshold_Policy();
    $directions = ['bidirectional', 'up_only', 'down_only'];
    $rounding_types = ['none', 'integer', 'ceil', 'floor', 'nearest'];
    $nearest_values = ['1', '10', '50', '100'];
    $margins = [-10, 0, 12.5];
    $movements = [
        'up' => new Exchange_Rate(1250, 1000),
        'down' => new Exchange_Rate(750, 1000),
        'flat' => new Exchange_Rate(1000, 1000),
    ];
    $calculation_count = 0;

    foreach ($movements as $movement => $exchange_rate) {
        foreach ($directions as $direction) {
            foreach ($rounding_types as $rounding_type) {
                $nearest_loop = $rounding_type === 'nearest' ? $nearest_values : ['1'];
                foreach ($nearest_loop as $nearest_to) {
                    foreach ($margins as $margin) {
                        $settings_case = [
                            'margin' => $margin,
                            'threshold' => 0,
                            'threshold_max' => 0,
                            'update_direction' => $direction,
                            'rounding_type' => $rounding_type,
                            'nearest_to' => $nearest_to,
                            'exclude_categories' => [],
                            'reference_currency' => 'USD',
                        ];

                        $context = new Price_Context(
                            product_id: 999,
                            old_regular: 1234.56,
                            old_sale: 999.99,
                            usd_baseline: 1.23456,
                            exchange_rate: $exchange_rate,
                            settings: $settings_case,
                            category_ids: []
                        );
                        $calc = $engine->calculate($context);
                        $allowed = $policy->should_update($exchange_rate, 0, 0, $direction, false);
                        $expected_allowed = match ($direction) {
                            'up_only' => $exchange_rate->percentage_change > 0,
                            'down_only' => $exchange_rate->percentage_change < 0,
                            default => true,
                        };

                        assert_true($calc->new_regular >= 0, "matrix $movement/$direction/$rounding_type/$nearest_to/$margin regular non-negative", "matrix regular negative");
                        assert_true($calc->new_sale >= 0, "matrix $movement/$direction/$rounding_type/$nearest_to/$margin sale non-negative", "matrix sale negative");
                        assert_true($calc->new_sale === 0.0 || $calc->new_sale < $calc->new_regular, "matrix $movement/$direction/$rounding_type/$nearest_to/$margin sale below regular or cleared", "matrix sale >= regular");
                        assert_true($allowed === $expected_allowed, "policy $movement/$direction allowed state is correct", "policy $movement/$direction allowed state mismatch");

                        $calculation_count++;
                    }
                }
            }
        }
    }

    assert_true($calculation_count === 216, "settings matrix completed $calculation_count calculations", "settings matrix count mismatch: $calculation_count");
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
    echo "OK PRODUCT SETTINGS MATRIX PASSED\n";
    exit(0);
}

echo "ERR PRODUCT SETTINGS MATRIX FAILED ($errors error(s))\n";
exit(1);
