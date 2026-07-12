<?php
/**
 * Master orchestrated smoke test.
 *
 * Tests ALL possible user flows, configurations, and edge cases
 * against the real database. Resets everything at the end.
 *
 * Run: php tests/master-smoke-test.php
 */

$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: $wp_load\n");
}

if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1:10018');
}

$_SERVER['HTTP_HOST'] = 'wp-flux.local';
$_SERVER['REQUEST_URI'] = '/';

set_error_handler(function ($severity, $message) {
    if (str_contains($message, 'Constant DB_HOST already defined')) {
        return true;
    }
    return false;
});
require_once $wp_load;
restore_error_handler();

if (file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
}

$assertions = 0;
$errors = 0;

function pass(string $msg): void { global $assertions; $assertions++; echo "  OK  $msg\n"; }
function fail(string $msg): void { echo "  ERR $msg\n"; global $errors; $errors++; }
function section(string $title): void { echo "\n=== $title ===\n"; }
function assert_true(bool $condition, string $pass_msg, string $fail_msg): void {
    $condition ? pass($pass_msg) : fail($fail_msg);
}

function reset_prixy_state(): void {
    global $wpdb;
    foreach ([
        'prixy_settings', 'prixy_initial_setup_done', 'prixy_db_version',
        'prixy_activation_redirect', 'prixy_admin_notice', 'prixy_cron_last_run', 'prixy_setup_run_id',
    ] as $option) {
        delete_option($option);
    }
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%prixy_%'");
    $wpdb->query("DELETE FROM {$wpdb->prefix}prixy_run_items");
    $wpdb->query("DELETE FROM {$wpdb->prefix}prixy_runs");
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_prixy_%'");
    wp_clear_scheduled_hook('prixy_do_update');
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('prixy_do_update', [], 'prixy-cron');
    }
    wp_cache_flush();
}

function snapshot_site_prices(): array {
    $ids = wc_get_products(['limit' => -1, 'status' => 'publish', 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
    $ids_all = [];
    foreach ($ids as $root_id) {
        $ids_all[] = (int) $root_id;
        $p = wc_get_product($root_id);
        if ($p && $p->is_type('variable')) {
            foreach ($p->get_children() as $vid) { $ids_all[] = (int) $vid; }
        }
    }
    $ids_all = array_unique(array_filter($ids_all));
    $snap = [];
    foreach ($ids_all as $id) {
        $p = wc_get_product($id);
        if ($p) {
            $snap[$id] = ['regular' => (string) $p->get_regular_price(), 'sale' => (string) $p->get_sale_price()];
        }
    }
    return $snap;
}

function restore_site_prices(array $snap): int {
    $r = 0;
    foreach ($snap as $id => $prices) {
        $p = wc_get_product((int) $id);
        if (!$p) continue;
        if ((string) $p->get_regular_price() !== $prices['regular'] || (string) $p->get_sale_price() !== $prices['sale']) {
            $p->set_regular_price($prices['regular']);
            $p->set_sale_price($prices['sale']);
            $p->save();
            $r++;
        }
    }
    return $r;
}

class Fixture_Product_Repo extends Product_Repository
{
    private array $ids;
    public function __construct(array $ids) { parent::__construct(); $this->ids = $ids; }
    public function count_all_products(): int { return count($this->ids); }
    public function get_product_ids_batch(int $limit = 500, int $offset = 0): array { return array_slice($this->ids, $offset, $limit); }
}

class Fixed_API_Client extends API_Client
{
    private float $rate;
    public function __construct(float $rate) { $this->rate = $rate; }
    public function get_rate($type = 'usd', $provider_key = null) {
        return ['value' => $this->rate, 'buy' => $this->rate, 'sell' => $this->rate, 'provider' => 'fixed', 'base_currency' => 'ARS', 'target_currency' => 'USD', 'valid' => true];
    }
}

$snapshot = [];
$created_product_ids = [];
$created_term_ids = [];
$created_run_ids = [];
$original_settings = [];

global $wpdb, $prixy_container;

try {
    wp_set_current_user(1);

    if (!class_exists('WooCommerce')) {
        throw new RuntimeException('WooCommerce not loaded.');
    }

    // ═══════════════════════════════════════════════════════════
    // 1. SNAPSHOT
    // ═══════════════════════════════════════════════════════════
    section('1. Pre-test snapshot');
    $snapshot = snapshot_site_prices();
    $original_settings = get_option('prixy_settings', []);
    assert_true(count($snapshot) > 0, 'captured ' . count($snapshot) . ' product prices snapshot', 'no products to snapshot');
    reset_prixy_state();
    pass('initial state reset complete');

    // ═══════════════════════════════════════════════════════════
    // 2. ACTIVATION FLOW
    // ═══════════════════════════════════════════════════════════
    section('2. Activation flow');
    Activator::activate();
    wp_cache_flush();

    $settings = get_option('prixy_settings', []);
    assert_true(is_array($settings) && !empty($settings), 'settings created', 'settings missing');
    assert_true(floatval($settings['threshold'] ?? 0) === 1.0, 'default threshold=1.0', 'threshold mismatch');
    assert_true(floatval($settings['threshold_max'] ?? -1) === 0.0, 'default threshold_max=0', 'threshold_max mismatch');
    assert_true(($settings['reference_currency'] ?? '') === 'USD', 'ref currency=USD', 'ref currency mismatch');
    assert_true(!empty($settings['api_provider']), 'api_provider selected', 'api_provider missing');
    assert_true(floatval($settings['origin_exchange_rate'] ?? 0) > 0, 'origin rate auto-detected', 'origin rate missing');
    assert_true(empty($settings['origin_rate_locked']), 'origin rate not locked', 'origin rate locked');
    assert_true(get_option('prixy_db_version') === Activator::DB_VERSION, 'db version=' . Activator::DB_VERSION, 'db version missing');
    assert_true(get_transient('prixy_activation_redirect') !== false, 'redirect transient set', 'redirect transient missing');
    assert_true(!Admin::is_setup_complete(), 'onboarding not complete', 'onboarding prematurely complete');
    assert_true(Cron::get_next_scheduled_time() !== null, 'cron scheduled', 'cron not scheduled');

    // DB tables
    $tables = [$wpdb->prefix . 'prixy_runs', $wpdb->prefix . 'prixy_run_items'];
    foreach ($tables as $t) {
        assert_true($wpdb->get_var("SHOW TABLES LIKE '{$t}'") === $t, "table $t exists", "table $t missing");
    }
    $runs_cols = $wpdb->get_col("DESCRIBE {$tables[0]}", 0);
    foreach (['id','date','dollar_type','reference_currency','dollar_value','rules','total_products','user_id','note','percentage_change','context'] as $c) {
        assert_true(in_array($c, $runs_cols), "runs column $c", "runs missing column $c");
    }
    $items_cols = $wpdb->get_col("DESCRIBE {$tables[1]}", 0);
    foreach (['id','run_id','product_id','old_regular_price','new_regular_price','old_sale_price','new_sale_price','percentage_change','status','reason'] as $c) {
        assert_true(in_array($c, $items_cols), "items column $c", "items missing column $c");
    }

    // ═══════════════════════════════════════════════════════════
    // 3. SETTINGS SAVE/SANITIZE
    // ═══════════════════════════════════════════════════════════
    section('3. Settings save & sanitize');
    $sanitized = Admin_Settings::sanitize([
        'api_provider' => 'jsdelivr',
        'country' => '<script>AR</script>',
        'base_currency' => 'ARS',
        'reference_currency' => 'USD',
        'origin_exchange_rate' => '1234.56',
        'origin_rate_locked' => '1',
        'rate_generation_method' => 'api',
        'currency' => 'usd',
        'margin' => '12.5',
        'threshold' => '3',
        'threshold_max' => '20',
        'update_direction' => 'down_only',
        'rounding_type' => 'nearest',
        'nearest_to' => '100',
        'exclude_categories' => ['5', 'x', '12'],
        'cron_enabled' => '1',
        'interval' => '60',
        'cron_api_provider' => 'dolarapi',
        'cron_currency' => 'brl',
        'cron_notify_mode' => 'invalid',
        'cron_notify_email' => 'bad-email',
    ]);
    update_option('prixy_settings', $sanitized);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();

    assert_true($sanitized['country'] === 'AR', 'country sanitized to AR', 'country not sanitized (got: "' . $sanitized['country'] . '")');
    assert_true($sanitized['interval'] === 300, 'interval clamped to 300', 'interval not clamped');
    assert_true($sanitized['cron_notify_mode'] === 'update_and_notify', 'invalid notify mode falls back', 'notify mode fallback fail');
    assert_true($sanitized['cron_notify_email'] === '', 'bad email cleared', 'bad email not cleared');
    assert_true($sanitized['exclude_categories'] === [5, 0, 12], 'categories integer-cast', 'category cast fail');
    assert_true(Admin::is_setup_complete(), 'setup complete', 'setup not complete');
    assert_true(Cron::get_next_scheduled_time() !== null, 'cron scheduled after save', 'cron not scheduled');

    $repo = new Settings_Repository();
    $manual = $repo->get_for_context('manual');
    $cron = $repo->get_for_context('cron');
    assert_true($manual['api_provider'] === 'jsdelivr', 'manual provider preserved', 'manual provider mismatch');
    assert_true($cron['api_provider'] === 'dolarapi', 'cron provider override', 'cron provider mismatch');
    assert_true($cron['currency'] === 'brl', 'cron currency override', 'cron currency mismatch');

    // ═══════════════════════════════════════════════════════════
    // 4. PRICE CALCULATION ENGINE — ALL RULES
    // ═══════════════════════════════════════════════════════════
    section('4. Price calculation engine');
    $engine = new Price_Calculation_Engine([new Ratio_Rule(), new Direction_Rule(), new Margin_Rule(), new Rounding_Rule()]);
    $policy = new Threshold_Policy();

    // Ratio: USD baseline
    $r = $engine->calculate(new Price_Context(1, 1000, 0, 10.0, new Exchange_Rate(1200, 1000), ['margin' => 0, 'rounding_type' => 'none', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']));
    assert_true(abs($r->new_regular - 12000) < 0.01, 'ratio rule: 10*1200=12000', "ratio got {$r->new_regular}");

    // Margin
    $r = $engine->calculate(new Price_Context(2, 1000, 0, 10.0, new Exchange_Rate(1200, 1000), ['margin' => 10, 'rounding_type' => 'none', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']));
    $expected = round(10 * 1200 * 1.10);
    assert_true(abs($r->new_regular - $expected) < 1, "margin rule: $expected", "margin got {$r->new_regular}");

    // Direction: up_only with falling rate
    $r2 = $engine->calculate(new Price_Context(3, 10000, 0, 10.0, new Exchange_Rate(800, 1000), ['margin' => 0, 'rounding_type' => 'none', 'nearest_to' => '1', 'update_direction' => 'up_only', 'reference_currency' => 'USD']));
    assert_true(abs($r2->new_regular - 10000) < 0.01, 'up_only blocks price drop', "up_only failed: {$r2->new_regular}");
    assert_true(!$r2->changed, 'up_only: no change flag', 'up_only: change flag set');

    // Direction: down_only with rising rate
    $r3 = $engine->calculate(new Price_Context(4, 10000, 0, 10.0, new Exchange_Rate(1200, 1000), ['margin' => 0, 'rounding_type' => 'none', 'nearest_to' => '1', 'update_direction' => 'down_only', 'reference_currency' => 'USD']));
    assert_true(abs($r3->new_regular - 10000) < 0.01, 'down_only blocks price rise', "down_only failed: {$r3->new_regular}");

    // Rounding: integer
    $r4 = $engine->calculate(new Price_Context(5, 1000, 0, 10.37, new Exchange_Rate(1000, 1000), ['margin' => 0, 'rounding_type' => 'integer', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']));
    assert_true(abs($r4->new_regular - round(10.37 * 1000)) < 0.01, 'integer rounding', "integer rounding got {$r4->new_regular}");

    // Rounding: ceil
    $r5 = $engine->calculate(new Price_Context(5, 1000, 0, 10.37, new Exchange_Rate(1000, 1000), ['margin' => 0, 'rounding_type' => 'ceil', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']));
    assert_true(abs($r5->new_regular - (int)ceil(10.37 * 1000)) < 0.01, 'ceil rounding', "ceil got {$r5->new_regular}");

    // Rounding: floor
    $r6 = $engine->calculate(new Price_Context(5, 1000, 0, 10.37, new Exchange_Rate(1000, 1000), ['margin' => 0, 'rounding_type' => 'floor', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']));
    assert_true(abs($r6->new_regular - (int)floor(10.37 * 1000)) < 0.01, 'floor rounding', "floor got {$r6->new_regular}");

    // Rounding: nearest 100
    $r7 = $engine->calculate(new Price_Context(5, 1000, 0, 10.37, new Exchange_Rate(1000, 1000), ['margin' => 0, 'rounding_type' => 'nearest', 'nearest_to' => '100', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']));
    $raw = 10.37 * 1000;
    $er7 = round($raw / 100) * 100;
    assert_true(abs($r7->new_regular - $er7) < 0.01, 'nearest 100 rounding', "nearest 100 got {$r7->new_regular}");

    // Threshold policy
    assert_true($policy->should_update(new Exchange_Rate(1100, 1000), 5.0, 0, 'bidirectional', false), 'threshold: 10% > 5%', 'threshold false positive');
    assert_true(!$policy->should_update(new Exchange_Rate(1001, 1000), 5.0, 0, 'bidirectional', false), 'threshold: 0.1% < 5%', 'threshold false negative');
    assert_true(!$policy->should_update(new Exchange_Rate(1100, 1000), 5.0, 8.0, 'bidirectional', false), 'threshold_max: 10% > 8%', 'threshold_max fail');
    assert_true(!$policy->should_update(new Exchange_Rate(900, 1000), 0, 0, 'up_only', false), 'direction: up_only blocks down', 'direction fail');

    // Excluded category
    $rules_with_exclude = [new Ratio_Rule(), new Direction_Rule(), new Margin_Rule(), new Rounding_Rule(), new Category_Exclusion_Rule()];
    $eng2 = new Price_Calculation_Engine($rules_with_exclude);
    $ctx_ex = new Price_Context(6, 2000, 1800, 20.0, new Exchange_Rate(1250, 1000), ['margin' => 0, 'rounding_type' => 'none', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'exclude_categories' => [99]], category_ids: [99]);
    $r8 = $eng2->calculate($ctx_ex);
    assert_true(!$r8->changed, 'excluded category unchanged', "excluded category changed: {$r8->new_regular}");

    // Sale price: engine applies rate ratio to old sale (800 * 1250/1000 = 1000)
    $ctx_sale = new Price_Context(7, 1000, 800, 10.0, new Exchange_Rate(1250, 1000), ['margin' => 0, 'rounding_type' => 'none', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']);
    $r9 = $engine->calculate($ctx_sale);
    assert_true(abs($r9->new_regular - round(10 * 1250)) < 0.01, 'sale: regular correct', "sale regular: {$r9->new_regular}");
    $expected_sale = round(800 * 1250 / 1000);
    assert_true(abs($r9->new_sale - $expected_sale) < 0.01, 'sale: sale correct', "sale sale: {$r9->new_sale} vs $expected_sale");

    // First run fallback (no USD baseline): uses price ratio instead
    $r10 = $engine->calculate(new Price_Context(8, 5000, 0, 0, new Exchange_Rate(1200, 1000), ['margin' => 0, 'rounding_type' => 'none', 'nearest_to' => '1', 'update_direction' => 'bidirectional', 'reference_currency' => 'USD']));
    assert_true(abs($r10->new_regular - 6000) < 0.01, 'first run: price = old * (1200/1000)', "first run: {$r10->new_regular} vs 6000");

    // ═══════════════════════════════════════════════════════════
    // 5. SETTINGS MATRIX (216 combos)
    // ═══════════════════════════════════════════════════════════
    section('5. Settings matrix (216 calculations)');
    $directions = ['bidirectional', 'up_only', 'down_only'];
    $rounding_types = ['none', 'integer', 'ceil', 'floor', 'nearest'];
    $nearest_values = ['1', '10', '50', '100'];
    $margins = [-10, 0, 12.5];
    $movements = [
        'up' => new Exchange_Rate(1250, 1000),
        'down' => new Exchange_Rate(750, 1000),
        'flat' => new Exchange_Rate(1000, 1000),
    ];
    $calc_count = 0;
    foreach ($movements as $mov => $er) {
        foreach ($directions as $dir) {
            foreach ($rounding_types as $rt) {
                $nearest_loop = $rt === 'nearest' ? $nearest_values : ['1'];
                foreach ($nearest_loop as $nt) {
                    foreach ($margins as $m) {
                        $s = ['margin' => $m, 'threshold' => 0, 'threshold_max' => 0, 'update_direction' => $dir, 'rounding_type' => $rt, 'nearest_to' => $nt, 'exclude_categories' => [], 'reference_currency' => 'USD'];
                        $c = new Price_Context(999, 1234.56, 999.99, 1.23456, $er, $s, category_ids: []);
                        $cal = $engine->calculate($c);
                        assert_true($cal->new_regular >= 0, "matrix $mov/$dir/$rt/$nt/$m regular>=0", "matrix regular<0");
                        assert_true($cal->new_sale >= 0, "matrix $mov/$dir/$rt/$nt/$m sale>=0", "matrix sale<0");
                        assert_true($cal->new_sale === 0.0 || $cal->new_sale < $cal->new_regular, "matrix $mov/$dir/$rt/$nt/$m sale<regular", "matrix sale>=regular");
                        $calc_count++;
                    }
                }
            }
        }
    }
    assert_true($calc_count === 216, "completed $calc_count calculations", "count mismatch: $calc_count");

    // ═══════════════════════════════════════════════════════════
    // 6. PRODUCT TYPES WITH REAL WC PRODUCTS
    // ═══════════════════════════════════════════════════════════
    section('6. Product type support');
    $stamp = 'prixy-ms-' . time();
    $cat_normal = wp_insert_term($stamp . '-cat', 'product_cat');
    $cat_excluded = wp_insert_term($stamp . '-exc', 'product_cat');
    if (is_wp_error($cat_normal) || is_wp_error($cat_excluded)) throw new RuntimeException('Cannot create categories');
    $cat_n_id = intval($cat_normal['term_id']);
    $cat_e_id = intval($cat_excluded['term_id']);
    $created_term_ids = [$cat_n_id, $cat_e_id];

    $make_simple = function ($name, $sku, $regular, $sale = '', $cats = []) use ($stamp, &$created_product_ids) {
        $p = new WC_Product_Simple();
        $p->set_name($name); $p->set_status('publish'); $p->set_sku($sku);
        if ($regular !== '') $p->set_regular_price($regular);
        if ($sale !== '') $p->set_sale_price($sale);
        if ($cats) $p->set_category_ids($cats);
        $id = $p->save(); $created_product_ids[] = $id; return $id;
    };

    $simple_sale_id = $make_simple('MS Simple Sale', "$stamp-s-sale", '1000', '800', [$cat_n_id]);
    $simple_nsale_id = $make_simple('MS Simple No Sale', "$stamp-s-nosale", '1500', '', [$cat_n_id]);
    $zero_id = $make_simple('MS Zero Price', "$stamp-zero", '', '', [$cat_n_id]);
    $excluded_id = $make_simple('MS Excluded', "$stamp-excluded", '2000', '1600', [$cat_e_id]);
    $group_child_id = $make_simple('MS Group Child', "$stamp-grp-child", '500', '', [$cat_n_id]);

    $virtual = new WC_Product_Simple();
    $virtual->set_name('MS Virtual'); $virtual->set_status('publish'); $virtual->set_sku("$stamp-virtual");
    $virtual->set_regular_price('700'); $virtual->set_virtual(true); $virtual->set_category_ids([$cat_n_id]);
    $virtual_id = $virtual->save(); $created_product_ids[] = $virtual_id;

    $downloadable = new WC_Product_Simple();
    $downloadable->set_name('MS Downloadable'); $downloadable->set_status('publish'); $downloadable->set_sku("$stamp-dl");
    $downloadable->set_regular_price('900'); $downloadable->set_downloadable(true); $downloadable->set_category_ids([$cat_n_id]);
    $dl_id = $downloadable->save(); $created_product_ids[] = $dl_id;

    $external = new WC_Product_External();
    $external->set_name('MS External'); $external->set_status('publish'); $external->set_sku("$stamp-ext");
    $external->set_regular_price('1100'); $external->set_product_url('https://example.com');
    $external->set_button_text('Buy'); $external->set_category_ids([$cat_n_id]);
    $ext_id = $external->save(); $created_product_ids[] = $ext_id;

    $grouped = new WC_Product_Grouped();
    $grouped->set_name('MS Grouped'); $grouped->set_status('publish'); $grouped->set_sku("$stamp-grp");
    $grouped_id = $grouped->save(); $grouped->set_children([$group_child_id]); $grouped->save();
    $created_product_ids[] = $grouped_id;

    $variable = new WC_Product_Variable();
    $variable->set_name('MS Variable'); $variable->set_status('publish'); $variable->set_sku("$stamp-var");
    $variable->set_category_ids([$cat_n_id]); $variable_id = $variable->save(); $created_product_ids[] = $variable_id;

    $v_sale = new WC_Product_Variation();
    $v_sale->set_parent_id($variable_id); $v_sale->set_status('publish'); $v_sale->set_sku("$stamp-var-s");
    $v_sale->set_regular_price('3000'); $v_sale->set_sale_price('2500'); $v_sale->set_attributes(['size' => 's']);
    $v_sale_id = $v_sale->save(); $created_product_ids[] = $v_sale_id;

    $v_nsale = new WC_Product_Variation();
    $v_nsale->set_parent_id($variable_id); $v_nsale->set_status('publish'); $v_nsale->set_sku("$stamp-var-ns");
    $v_nsale->set_regular_price('1200'); $v_nsale->set_attributes(['size' => 'l']);
    $v_nsale_id = $v_nsale->save(); $created_product_ids[] = $v_nsale_id;
    WC_Product_Variable::sync($variable_id);

    $exc_var = new WC_Product_Variable();
    $exc_var->set_name('MS Excluded Var'); $exc_var->set_status('publish'); $exc_var->set_sku("$stamp-exc-var");
    $exc_var->set_category_ids([$cat_e_id]); $exc_v_id = $exc_var->save(); $created_product_ids[] = $exc_v_id;

    $exc_variation = new WC_Product_Variation();
    $exc_variation->set_parent_id($exc_v_id); $exc_variation->set_status('publish'); $exc_variation->set_sku("$stamp-exc-v");
    $exc_variation->set_regular_price('4000'); $exc_variation->set_sale_price('3000'); $exc_variation->set_attributes(['size' => 'x']);
    $exc_variation_id = $exc_variation->save(); $created_product_ids[] = $exc_variation_id;
    WC_Product_Variable::sync($exc_v_id);

    assert_true(count($created_product_ids) === 14, '14 product fixtures created', "count: " . count($created_product_ids));

    $root_ids = array_filter($created_product_ids, fn($id) => !in_array($id, [$v_sale_id, $v_nsale_id, $exc_variation_id]));
    $all_priced = [$simple_sale_id, $simple_nsale_id, $excluded_id, $group_child_id, $virtual_id, $dl_id, $ext_id, $v_sale_id, $v_nsale_id, $exc_variation_id];

    // Setup baseline with fixed rate
    $log_repo = new Log_Repository();
    $base_rate = 1000.0;
    $settings_base = array_merge($original_settings, [
        'api_provider' => 'jsdelivr', 'currency' => 'usd', 'reference_currency' => 'USD',
        'origin_exchange_rate' => $base_rate, 'origin_rate_locked' => '1',
        'threshold' => 0, 'threshold_max' => 0, 'update_direction' => 'bidirectional',
        'margin' => 0, 'rounding_type' => 'integer', 'nearest_to' => '1',
        'exclude_categories' => [$cat_e_id],
    ]);
    update_option('prixy_settings', $settings_base);
    wp_cache_delete('prixy_settings', 'options');

    $baseline_id = $log_repo->insert_run([
        'currency' => 'usd', 'reference_currency' => 'USD', 'dollar_value' => $base_rate,
        'total_products' => count($all_priced), 'percentage_change' => null, 'context' => 'setup',
        'note' => 'MS baseline', 'user_id' => get_current_user_id(),
    ]);
    $created_run_ids[] = $baseline_id;

    $prices_before = [
        $simple_sale_id => [1000, 800], $simple_nsale_id => [1500, 0],
        $excluded_id => [2000, 1600], $group_child_id => [500, 0],
        $virtual_id => [700, 0], $dl_id => [900, 0], $ext_id => [1100, 0],
        $v_sale_id => [3000, 2500], $v_nsale_id => [1200, 0], $exc_variation_id => [4000, 3000],
    ];
    foreach ($prices_before as $pid => [$reg, $sale]) {
        $log_repo->insert_run_item($baseline_id, [
            'product_id' => $pid, 'status' => 'updated',
            'old_regular_price' => 0, 'new_regular_price' => $reg,
            'old_sale_price' => 0, 'new_sale_price' => $sale,
            'percentage_change' => null, 'reason' => 'MS baseline',
        ]);
    }

    // Update with fixed rate
    $new_rate = 1250.0;
    $fixture_repo = new Fixture_Product_Repo($root_ids);
    $handler = new Update_Prices_Handler(
        new Settings_Repository(), new Fixed_API_Client($new_rate),
        new Batch_Processor($fixture_repo, new Price_Calculation_Engine([new Ratio_Rule(), new Direction_Rule(), new Margin_Rule(), new Rounding_Rule()]), $log_repo),
        $fixture_repo, new Logger($log_repo), new Threshold_Policy(), $log_repo
    );
    $result = $handler->handle(new Update_Prices_Command(0, false, 'manual'));
    $run_id = intval($result['run_id'] ?? 0);
    if ($run_id > 0) $created_run_ids[] = $run_id;

    assert_true(empty($result['error']), 'product update completed', 'update error: ' . ($result['message'] ?? '?'));
    assert_true($run_id > 0, "update created run $run_id", 'no run created');

    $expect = [
        $simple_sale_id => [1250, 1000], $simple_nsale_id => [1875, 0],
        $group_child_id => [625, 0], $virtual_id => [875, 0], $dl_id => [1125, 0],
        $ext_id => [1375, 0], $v_sale_id => [3750, 3125], $v_nsale_id => [1500, 0],
    ];
    foreach ($expect as $pid => [$reg, $sale]) {
        $p = wc_get_product($pid);
        assert_true(floatval($p->get_regular_price()) === (float)$reg, "product $pid regular=$reg", "product $pid regular={$p->get_regular_price()}");
        assert_true(floatval($p->get_sale_price()) === (float)$sale, "product $pid sale=$sale", "product $pid sale={$p->get_sale_price()}");
    }
    assert_true(floatval(wc_get_product($excluded_id)->get_regular_price()) === 2000.0, 'excluded unchanged', 'excluded changed');
    assert_true(floatval(wc_get_product($exc_variation_id)->get_regular_price()) === 4000.0, 'excluded var unchanged', 'excluded var changed');
    assert_true(floatval(wc_get_product($zero_id)->get_regular_price()) === 0.0, 'zero price unchanged', 'zero price changed');

    // ═══════════════════════════════════════════════════════════
    // 7. ROLLBACK
    // ═══════════════════════════════════════════════════════════
    section('7. Rollback');
    $rollback = (new Rollback_Handler($log_repo))->handle(new Rollback_Run_Command($run_id));
    assert_true(!empty($rollback['success']), 'rollback succeeded', 'rollback failed');
    foreach ($prices_before as $pid => [$reg, $sale]) {
        $p = wc_get_product($pid);
        assert_true(floatval($p->get_regular_price()) === (float)$reg, "rollback pid=$pid reg=$reg", "rollback pid=$pid reg={$p->get_regular_price()}");
        assert_true(floatval($p->get_sale_price()) === (float)$sale, "rollback pid=$pid sale=$sale", "rollback pid=$pid sale={$p->get_sale_price()}");
    }

    // ═══════════════════════════════════════════════════════════
    // 8. ADMIN PAGE RENDERING
    // ═══════════════════════════════════════════════════════════
    section('8. Admin page rendering');
    $routes = [
        'prixy_settings' => [Admin::class, 'render_overview'],
        'prixy_configuration' => [Admin::class, 'render_settings'],
        'prixy_automation' => [Admin::class, 'render_automation'],
        'prixy_dashboard' => [Admin::class, 'render_dashboard'],
        'prixy_logs' => [Admin::class, 'render_logs'],
        'prixy_reset' => [Admin::class, 'render_reset_db'],
    ];
    foreach ($routes as $slug => $cb) {
        $_GET['page'] = $slug; $_POST = [];
        ob_start();
        try {
            call_user_func($cb);
            $html = ob_get_clean();
            assert_true(strlen($html) > 500, "$slug rendered (" . strlen($html) . "b)", "$slug empty: " . strlen($html));
        } catch (Throwable $e) {
            ob_end_clean();
            fail("$slug threw: " . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 9. AJAX USER ACTIONS
    // ═══════════════════════════════════════════════════════════
    section('9. AJAX user actions');

    // wp_send_json in CLI calls die() which kills the script.
    // define DOING_AJAX so it calls wp_die() instead — we intercept
    // that with a custom handler that throws an exception, capture
    // the JSON from the output buffer, and return it.
    if (!defined('DOING_AJAX')) define('DOING_AJAX', true);

    $prixy_container = Prixy_Container::build();
    $ajax = $prixy_container->get('ajax_controller');

    class AjaxTestDone extends Exception {}

    $ajax_die_handler = function ($m = '', $t = '', $a = []) { throw new AjaxTestDone(); };
    add_filter('wp_die_handler', fn() => $ajax_die_handler);
    add_filter('wp_die_ajax_handler', fn() => $ajax_die_handler);

    $call_ajax = function ($method, $post = []) use ($ajax, &$ajax_die_handler) {
        $_POST = array_merge(['nonce' => wp_create_nonce('prixy_ajax_nonce')], $post);
        $_REQUEST = $_POST;
        ob_start();
        try { $ajax->{$method}(); } catch (AjaxTestDone) {}
        $raw = trim(ob_get_clean()); $_POST = []; $_REQUEST = [];
        $json = json_decode($raw, true);
        return is_array($json) ? $json : ['success' => false, 'data' => ['raw' => $raw]];
    };

    // Reset + re-activate clean for AJAX
    reset_prixy_state();
    Activator::activate();
    $prixy_container = Prixy_Container::build();
    $ajax = $prixy_container->get('ajax_controller');

    // Providers
    $resp = $call_ajax('handle_get_providers_info');
    assert_true(!empty($resp['success']), 'get_providers_info', 'get_providers_info failed');
    assert_true(!empty($resp['data']['providers']['jsdelivr']), 'providers has jsdelivr', 'jsdelivr missing');

    // Currencies
    $resp = $call_ajax('handle_get_currencies', ['provider' => 'jsdelivr']);
    assert_true(!empty($resp['success']), 'get_currencies', 'get_currencies failed');
    assert_true(($resp['data']['count'] ?? 0) > 0, 'currencies returned rows', 'currencies empty');

    // Rate
    $resp = $call_ajax('handle_get_current_rate', ['provider' => 'jsdelivr', 'currency' => 'usd']);
    assert_true(!empty($resp['success']), 'get_current_rate', 'get_current_rate failed');
    $live_rate = floatval($resp['data']['rate'] ?? 0);
    assert_true($live_rate > 0, "live rate=$live_rate", 'live rate invalid');

    // Test API
    $resp = $call_ajax('handle_test_api_connection', ['provider' => 'jsdelivr']);
    assert_true(!empty($resp['success']), 'test_api_connection', 'test_api_connection failed');

    // Dashboard stats
    $resp = $call_ajax('handle_get_dashboard_stats');
    assert_true(!empty($resp['success']), 'get_dashboard_stats', 'get_dashboard_stats failed');

    // Setup progress
    $resp = $call_ajax('handle_get_setup_progress');
    assert_true(!empty($resp['success']), 'get_setup_progress', 'get_setup_progress failed');

    // Preview products
    $resp = $call_ajax('handle_preview_products', ['rate' => $live_rate]);
    assert_true(!empty($resp['success']), 'preview_products', 'preview_products failed');
    assert_true(is_array($resp['data']['products'] ?? null), 'preview products array', 'preview missing products');

    // Initialize baseline
    $resp = $call_ajax('handle_initialize_baseline');
    assert_true(!empty($resp['success']), 'initialize_baseline', 'initialize_baseline failed');
    assert_true(floatval($resp['data']['value'] ?? 0) > 0, 'baseline value > 0', 'baseline value missing');

    // First setup batch
    $resp = $call_ajax('handle_first_setup_batch', ['offset' => 0, 'limit' => 5, 'rate' => $live_rate]);
    assert_true(!empty($resp['success']), 'first_setup_batch', 'first_setup_batch failed');
    assert_true(is_array($resp['data']['products'] ?? null), 'first setup products array', 'first setup missing products');

    // Save origin rate
    $resp = $call_ajax('handle_save_origin_rate', ['value' => $live_rate]);
    assert_true(!empty($resp['success']), 'save_origin_rate', 'save_origin_rate failed');
    assert_true(!empty($resp['data']['redirect']), 'save origin redirect', 'save origin missing redirect');

    // Setup for simulate/update
    $settings = get_option('prixy_settings', []);
    $settings = array_merge($settings, [
        'api_provider' => 'jsdelivr', 'currency' => 'usd', 'reference_currency' => 'USD',
        'origin_exchange_rate' => round($live_rate / 1.001, 6), 'origin_rate_locked' => '1',
        'threshold' => 0, 'threshold_max' => 0, 'update_direction' => 'bidirectional',
        'margin' => 0, 'rounding_type' => 'none', 'nearest_to' => '1', 'exclude_categories' => [],
        'cron_enabled' => 0, 'cron_notify_mode' => 'disabled',
    ]);
    update_option('prixy_settings', $settings);
    wp_cache_delete('prixy_settings', 'options');
    $prixy_container->get('settings')->refresh();

    // Delete setup runs so get_last_applied_rate() returns 0,
    // forcing get_previous_rate() to use origin_exchange_rate.
    $wpdb->query("DELETE FROM {$wpdb->prefix}prixy_run_items WHERE run_id IN (SELECT id FROM (SELECT id FROM {$wpdb->prefix}prixy_runs WHERE context = 'setup') AS tmp)");
    $wpdb->query("DELETE FROM {$wpdb->prefix}prixy_runs WHERE context = 'setup'");

    // Simulate
    $resp = $call_ajax('handle_simulate_batch', ['batch' => 0]);
    assert_true(!empty($resp['success']), 'simulate_batch', 'simulate_batch failed');
    assert_true(!empty($resp['data']['summary']['simulated']), 'simulate flag', 'simulate_missing flag');

    // Update
    $resp = $call_ajax('handle_update_batch', ['batch' => 0]);
    pass('update_batch raw: ' . json_encode($resp));
    assert_true(!empty($resp['success']), 'update_batch', 'update_batch failed');
    $aj_run_id = intval($resp['data']['run_id'] ?? 0);
    assert_true($aj_run_id > 0, "update run_id=$aj_run_id", 'update no run_id');

    // Runs list
    $resp = $call_ajax('handle_get_runs');
    pass('get_runs raw: ' . json_encode($resp));
    assert_true(!empty($resp['success']), 'get_runs', 'get_runs failed');
    assert_true(count($resp['data'] ?? []) > 0, 'runs returned rows', 'runs empty');

    // Run items
    $resp = $call_ajax('handle_get_run_items', ['run_id' => $aj_run_id]);
    pass('get_run_items raw: ' . json_encode($resp));
    assert_true(!empty($resp['success']), 'get_run_items', 'get_run_items failed');
    assert_true(count($resp['data'] ?? []) > 0, 'run items returned rows', 'run items empty');

    // Revert item
    $first_item = $resp['data'][0] ?? [];
    $item_id = intval(is_object($first_item) ? ($first_item->id ?? 0) : ($first_item['id'] ?? 0));
    if ($item_id > 0) {
        $resp = $call_ajax('handle_revert_item', ['log_id' => $item_id]);
        pass('revert_item raw: ' . json_encode($resp));
        assert_true(!empty($resp['success']), 'revert_item', "revert_item failed");
    }

    // Revert run
    $resp = $call_ajax('handle_revert_run', ['run_id' => $aj_run_id]);
    pass('revert_run raw: ' . json_encode($resp));
    assert_true(!empty($resp['success']), 'revert_run', 'revert_run failed');

    // ═══════════════════════════════════════════════════════════
    // 10. CRON SCHEDULING
    // ═══════════════════════════════════════════════════════════
    section('10. Cron scheduling');
    reset_prixy_state();
    Activator::activate();
    wp_cache_flush();

    assert_true(Cron::get_next_scheduled_time() !== null, 'cron scheduled after activation', 'cron not scheduled');

    $cron_settings = get_option('prixy_settings', []);
    $cron_settings['cron_enabled'] = 0;
    update_option('prixy_settings', $cron_settings);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();
    assert_true(Cron::get_next_scheduled_time() === null, 'cron unscheduled when disabled', 'cron still scheduled');

    $cron_settings['cron_enabled'] = 1;
    update_option('prixy_settings', $cron_settings);
    wp_cache_delete('prixy_settings', 'options');
    Cron::schedule();
    assert_true(Cron::get_next_scheduled_time() !== null, 'cron re-scheduled when enabled', 'cron not re-scheduled');

    // ═══════════════════════════════════════════════════════════
    // 11. DEACTIVATION
    // ═══════════════════════════════════════════════════════════
    section('11. Deactivation');
    Deactivator::deactivate();
    assert_true(Cron::get_next_scheduled_time() === null, 'cron unscheduled after deactivation', 'cron still scheduled after deactivation');

    // ═══════════════════════════════════════════════════════════
    // 12. RESET (admin partial)
    // ═══════════════════════════════════════════════════════════
    section('12. Reset (admin partial)');
    Activator::activate();
    wp_cache_flush();

    $log_repo2 = new Log_Repository();
    $rid = $log_repo2->insert_run([
        'currency' => 'usd', 'reference_currency' => 'USD', 'dollar_value' => 1000,
        'total_products' => 1, 'percentage_change' => 0, 'context' => 'test', 'note' => 'Pre-reset test',
    ]);
    $log_repo2->insert_run_item($rid, [
        'product_id' => 999999, 'status' => 'updated',
        'old_regular_price' => 10, 'new_regular_price' => 20, 'old_sale_price' => 0, 'new_sale_price' => 0,
        'percentage_change' => 100, 'reason' => 'Pre-reset',
    ]);

    $_POST = ['reset_prixy' => '1', '_wpnonce' => wp_create_nonce('prixy_reset')];
    ob_start();
    include PRIXY_PLUGIN_DIR . 'admin/partials/prixy-reset-db.php';
    $html = ob_get_clean();
    $_POST = [];

    assert_true(str_contains($html, 'Base de datos'), 'reset partial rendered', 'reset partial missing');
    assert_true(get_option('prixy_settings', null) === null, 'reset deleted settings', 'reset left settings');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs")) === 0, 'reset deleted runs', 'reset left runs');
    assert_true(Cron::get_next_scheduled_time() === null, 'reset cleared cron', 'reset left cron');

    // ═══════════════════════════════════════════════════════════
    // 13. REACTIVATION
    // ═══════════════════════════════════════════════════════════
    section('13. Reactivation');
    Activator::activate();
    wp_cache_flush();
    assert_true(get_option('prixy_settings', null) !== null, 'reactivation created settings', 'reactivation did not create settings');
    assert_true(Cron::get_next_scheduled_time() !== null, 'reactivation scheduled cron', 'reactivation did not schedule cron');

    // ═══════════════════════════════════════════════════════════
    // 14. FINAL RESET
    // ═══════════════════════════════════════════════════════════
    section('14. Final reset');
    reset_prixy_state();
    assert_true(get_option('prixy_settings', null) === null, 'final reset removed settings', 'final reset left settings');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs")) === 0, 'final reset removed runs', 'final reset left runs');
    assert_true(intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_run_items")) === 0, 'final reset removed run_items', 'final reset left run_items');
    assert_true(Cron::get_next_scheduled_time() === null, 'final reset cleared cron', 'final reset left cron');

    // Restore original settings
    if (!empty($original_settings)) {
        update_option('prixy_settings', $original_settings);
        wp_cache_delete('prixy_settings', 'options');
        Cron::schedule();
        pass('restored original settings');
    }

} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
} finally {
    // Safety: restore product prices
    if (!empty($snapshot)) {
        $restored = restore_site_prices($snapshot);
        if ($restored > 0) pass("safety restored $restored product prices");
    }

    // Cleanup fixture products
    foreach (array_unique(array_filter($created_product_ids)) as $pid) {
        wp_delete_post($pid, true);
    }
    foreach (array_unique(array_filter($created_term_ids)) as $tid) {
        wp_delete_term($tid, 'product_cat');
    }
    foreach (array_unique(array_filter($created_run_ids)) as $rid) {
        $wpdb->delete($wpdb->prefix . 'prixy_run_items', ['run_id' => intval($rid)]);
        $wpdb->delete($wpdb->prefix . 'prixy_runs', ['id' => intval($rid)]);
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Assertions: $assertions  |  Failures: $errors\n";
echo str_repeat('=', 50) . "\n";

if ($errors === 0) {
    echo "OK ALL MASTER SMOKE TESTS PASSED\n";
    exit(0);
}

echo "ERR MASTER SMOKE TESTS FAILED ($errors error(s))\n";
exit(1);
