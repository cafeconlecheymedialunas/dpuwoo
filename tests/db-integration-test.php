<?php
/**
 * Test de integración con BD real.
 * Ejecutar: php tests/db-integration-test.php
 * Requiere WordPress cargado.
 */

// Bootstrap WordPress
$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: No se encontró wp-load.php en: $wp_load\n");
}

define('DOING_CRON', true); // Evita headers admin
$_SERVER['HTTP_HOST']   = 'wp-flux.local';
$_SERVER['REQUEST_URI'] = '/';

if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1:10018');
}

$previous_error_reporting = error_reporting();
set_error_handler(function ($severity, $message, $file, $line) {
    if (str_contains($message, 'Constant DB_HOST already defined')) {
        return true;
    }
    return false;
});
require_once $wp_load;
restore_error_handler();
error_reporting($previous_error_reporting);

// ── Helpers ────────────────────────────────────────────────────────────────

function pass(string $msg): void { echo "  ✓ $msg\n"; }
function fail(string $msg): void { echo "  ✗ $msg\n"; global $errors; $errors++; }
function section(string $title): void { echo "\n=== $title ===\n"; }

function approx_equal(float $actual, float $expected, float $tolerance_pct = 0.5): bool {
    if ($expected <= 0) return false;
    return abs($actual - $expected) <= ($expected * ($tolerance_pct / 100));
}

$errors = 0;

// ── 1. Tablas de BD ────────────────────────────────────────────────────────
section('Tablas de BD');

global $wpdb;

$bootstrap_settings = get_option('prixy_settings', []);
if (empty($bootstrap_settings) && class_exists('Activator')) {
    Activator::activate();
    wp_cache_delete('prixy_settings', 'options');
}

$runs_exists  = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}prixy_runs'");
$items_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}prixy_run_items'");

$runs_exists  ? pass("Tabla prixy_runs existe") : fail("Tabla prixy_runs NO existe");
$items_exists ? pass("Tabla prixy_run_items existe") : fail("Tabla prixy_run_items NO existe");

// ── 2. Columnas de prixy_runs ──────────────────────────────────────────────
section('Columnas prixy_runs');

$cols = $wpdb->get_col("DESCRIBE {$wpdb->prefix}prixy_runs", 0);
$required_cols = ['id','date','dollar_type','reference_currency','dollar_value','rules','total_products','user_id','note','percentage_change','context'];
foreach ($required_cols as $col) {
    in_array($col, $cols) ? pass("Columna '$col' existe") : fail("Columna '$col' FALTA");
}

// ── 3. Columnas de prixy_run_items ─────────────────────────────────────────
section('Columnas prixy_run_items');
$cols_items = $wpdb->get_col("DESCRIBE {$wpdb->prefix}prixy_run_items", 0);
$required_items_cols = ['id','run_id','product_id','old_regular_price','new_regular_price','old_sale_price','new_sale_price','percentage_change','status','reason'];
foreach ($required_items_cols as $col) {
    in_array($col, $cols_items) ? pass("Columna '$col' existe") : fail("Columna '$col' FALTA");
}

// ── 4. Settings guardados ─────────────────────────────────────────────────
section('Settings en BD');

$opts = get_option('prixy_settings', []);
$opts ? pass("prixy_settings existe en BD (" . count($opts) . " claves)") : fail("prixy_settings NO encontrado");

$required_keys = ['api_provider','reference_currency','threshold','interval'];
foreach ($required_keys as $k) {
    isset($opts[$k]) ? pass("Clave '$k' = " . json_encode($opts[$k])) : fail("Clave '$k' FALTA en settings");
}

// ── 5. Insert/Read/Delete en prixy_runs ───────────────────────────────────
section('Admin onboarding flow');

$original_settings = $opts;
$probe_settings = $original_settings;
$probe_settings['origin_exchange_rate'] = 1234.56;
unset($probe_settings['origin_rate_locked']);
update_option('prixy_settings', $probe_settings);
wp_cache_delete('prixy_settings', 'options');

class_exists('Admin') && !Admin::is_setup_complete()
    ? pass("Tasa auto-detectada sin origin_rate_locked NO completa onboarding")
    : fail("Tasa auto-detectada sin origin_rate_locked marco setup completo");

$probe_settings['origin_rate_locked'] = '1';
update_option('prixy_settings', $probe_settings);
wp_cache_delete('prixy_settings', 'options');

class_exists('Admin') && Admin::is_setup_complete()
    ? pass("origin_rate_locked=1 completa onboarding")
    : fail("origin_rate_locked=1 no completo onboarding");

update_option('prixy_settings', $original_settings);
wp_cache_delete('prixy_settings', 'options');

section('API real: jsdelivr');

$store_currency = strtolower(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'ARS');
$provider = new Jsdelivr_Provider();

foreach (['usd', 'eur'] as $base) {
    $url = "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$base}.json";
    $http = wp_remote_get($url, ['timeout' => 15, 'headers' => ['Accept' => 'application/json']]);

    if (is_wp_error($http)) {
        fail("Consulta real {$base}.json fallo: " . $http->get_error_message());
        continue;
    }

    $code = wp_remote_retrieve_response_code($http);
    $body = json_decode(wp_remote_retrieve_body($http), true);
    $expected = floatval($body[$base][$store_currency] ?? 0);

    if ($code !== 200 || $expected <= 0) {
        fail("Consulta real {$base}.json no devolvio {$store_currency}: HTTP {$code}");
        continue;
    }

    $rate = $provider->get_rate($base);
    if (!$rate || empty($rate['value'])) {
        fail("Jsdelivr_Provider::get_rate('{$base}') no devolvio tasa valida");
        continue;
    }

    approx_equal(floatval($rate['value']), $expected)
        ? pass("get_rate('{$base}') coincide con API real: {$rate['value']}")
        : fail("get_rate('{$base}')={$rate['value']} pero API real={$expected}");
}

section('CRUD prixy_runs');

$repo = new Log_Repository();

$run_data = [
    'currency'           => 'oficial',
    'dollar_value'       => 1250.50,
    'total_products'     => 5,
    'percentage_change'  => 2.35,
    'context'            => 'test',
    'note'               => 'Integration test',
    'reference_currency' => 'USD',
];

$run_id = $repo->insert_run($run_data);
$run_id ? pass("insert_run() → run_id=$run_id") : fail("insert_run() falló");

if ($run_id) {
    $run = $repo->get_run($run_id);
    $run ? pass("get_run($run_id) devolvió datos") : fail("get_run($run_id) no encontró el registro");

    if ($run) {
        // get_run() devuelve stdClass
        $dv = floatval($run->dollar_value ?? 0);
        abs($dv - 1250.50) < 0.01
            ? pass("dollar_value guardado correctamente: $dv")
            : fail("dollar_value incorrecto: esperaba 1250.50, obtuvo $dv");

        ($run->context ?? '') === 'test'
            ? pass("context guardado: '{$run->context}'")
            : fail("context incorrecto: '{$run->context}'");
    }

    // Actualizar run
    $repo->update_run($run_id, ['total_products' => 10, 'note' => 'Updated']);
    $run2 = $repo->get_run($run_id);
    ($run2 && intval($run2->total_products) === 10)
        ? pass("update_run() persistió total_products=10")
        : fail("update_run() no persistió: total_products=" . ($run2->total_products ?? 'N/A'));
}

// ── 6. Insert items en prixy_run_items ────────────────────────────────────
section('CRUD prixy_run_items');

if ($run_id) {
    $item = [
        'product_id'        => 9999,
        'status'            => 'updated',
        'old_regular_price' => 10000.00,
        'new_regular_price' => 12500.00,
        'old_sale_price'    => 9000.00,
        'new_sale_price'    => 11250.00,
        'percentage_change' => 25.00,
        'reason'            => 'Test item',
    ];

    $item_id = $repo->insert_run_item($run_id, $item);
    $item_id ? pass("insert_run_item() → item_id=$item_id") : fail("insert_run_item() falló");

    // get_run_items() enriquece con wc_get_product() — consulta directo para no depender de WC
    $raw_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}prixy_run_items WHERE run_id = %d", $run_id
    ));
    (count($raw_items) === 1) ? pass("prixy_run_items: 1 item insertado") : fail("prixy_run_items: " . count($raw_items) . " items");

    if (!empty($raw_items)) {
        $it = $raw_items[0]; // stdClass
        abs(floatval($it->old_regular_price) - 10000.00) < 0.01
            ? pass("old_regular_price correcto: {$it->old_regular_price}")
            : fail("old_regular_price incorrecto: {$it->old_regular_price}");
        abs(floatval($it->new_regular_price) - 12500.00) < 0.01
            ? pass("new_regular_price correcto: {$it->new_regular_price}")
            : fail("new_regular_price incorrecto: {$it->new_regular_price}");
        abs(floatval($it->percentage_change) - 25.00) < 0.01
            ? pass("percentage_change correcto: {$it->percentage_change}")
            : fail("percentage_change incorrecto: {$it->percentage_change}");
    }
}

// ── 7. Cálculo de precios con datos de BD ─────────────────────────────────
section('Cálculo de precios (Price_Calculation_Engine)');

$settings_for_calc = [
    'margin'           => 10.0,    // +10% margen
    'update_direction' => 'bidirectional',
    'rounding_type'    => 'integer',
    'nearest_to'       => '1',
    'exclude_categories' => [],
    'reference_currency' => 'USD',
    'origin_exchange_rate' => 1000.0,
];

$rules = [
    new Ratio_Rule(),
    new Direction_Rule(),
    new Margin_Rule(),
    new Rounding_Rule(),
];

$engine = new Price_Calculation_Engine($rules);

// Caso 1: Tasa subió 25% → precio debe subir ~25% + 10% margen
$exchange_rate = new Exchange_Rate(1250.0, 1000.0); // +25%
$price_ctx = new Price_Context(
    product_id:    1,
    old_regular:   10000.0,
    old_sale:      9000.0,
    usd_baseline:  10.0,      // 10 USD
    exchange_rate: $exchange_rate,
    settings:      $settings_for_calc
);
$result = $engine->calculate($price_ctx);

// Esperado: 10 USD * 1250 * 1.10 = 13750 → redondeado = 13750
$expected_regular = round(10.0 * 1250.0 * 1.10);
abs($result->new_regular - $expected_regular) < 1
    ? pass("Caso 1 (subida 25%+10% margen): nuevo precio={$result->new_regular} (esperado≈$expected_regular)")
    : fail("Caso 1 incorrecto: {$result->new_regular} vs esperado $expected_regular");

// Caso 2: Dirección up_only con tasa cayendo → precio NO debe cambiar
$settings_down = array_merge($settings_for_calc, ['update_direction' => 'up_only', 'margin' => 0]);
$exchange_rate2 = new Exchange_Rate(900.0, 1000.0); // -10%
$price_ctx2 = new Price_Context(
    product_id:    2,
    old_regular:   10000.0,
    old_sale:      0.0,
    usd_baseline:  10.0,
    exchange_rate: $exchange_rate2,
    settings:      $settings_down
);
$result2 = $engine->calculate($price_ctx2);

$result2->new_regular == $price_ctx2->old_regular
    ? pass("Caso 2 (up_only con bajada): precio sin cambio={$result2->new_regular}")
    : fail("Caso 2 incorrecto: precio cambió a {$result2->new_regular} cuando no debía");

// Caso 3: Redondeo a 100 más cercano
$settings_round = array_merge($settings_for_calc, ['margin' => 0, 'rounding_type' => 'nearest', 'nearest_to' => '100']);
$exchange_rate3 = new Exchange_Rate(1000.0, 1000.0); // sin cambio, ratio=1
$price_ctx3 = new Price_Context(
    product_id:    3,
    old_regular:   10000.0,
    old_sale:      0.0,
    usd_baseline:  10.37,  // precio en USD no redondo
    exchange_rate: $exchange_rate3,
    settings:      $settings_round
);
$result3 = $engine->calculate($price_ctx3);
$raw = 10.37 * 1000.0;
$expected_rounded = round($raw / 100) * 100;
abs($result3->new_regular - $expected_rounded) < 1
    ? pass("Caso 3 (redondeo nearest 100): {$result3->new_regular} (esperado $expected_rounded)")
    : fail("Caso 3 redondeo incorrecto: {$result3->new_regular} vs $expected_rounded");

// ── 8. get_last_applied_rate ──────────────────────────────────────────────
section('get_last_applied_rate');

if ($run_id) {
    $last_rate = $repo->get_last_applied_rate('oficial', 'USD');
    // Puede ser 0 si este run no tiene reference_currency=USD matcheado exactamente
    // pero al menos no debe tirar error
    is_float($last_rate) || is_int($last_rate)
        ? pass("get_last_applied_rate() devolvió: $last_rate")
        : fail("get_last_applied_rate() devolvió tipo inválido: " . gettype($last_rate));
}

// ── 9. Aggregate stats ────────────────────────────────────────────────────
section('Aggregate Stats');

$stats = $repo->get_aggregate_stats();
is_array($stats) ? pass("get_aggregate_stats() devolvió array") : fail("get_aggregate_stats() falló");
if (is_array($stats)) {
    foreach (['total_runs','total_products','avg_pct','error_count','updated_count'] as $k) {
        array_key_exists($k, $stats)
            ? pass("stats['$k'] = " . json_encode($stats[$k]))
            : fail("stats['$k'] FALTA");
    }
}

// ── 10. Threshold Policy ──────────────────────────────────────────────────
section('Threshold Policy');

$policy = new Threshold_Policy();

$rate_up   = new Exchange_Rate(1100.0, 1000.0);  // +10%
$rate_down = new Exchange_Rate(900.0, 1000.0);   // -10%
$rate_tiny = new Exchange_Rate(1001.0, 1000.0);  // +0.1%

// threshold_min=5, threshold_max=0, bidirectional
$policy->should_update($rate_up, 5.0, 0, 'bidirectional', false)
    ? pass("10% > threshold 5%: aprueba actualización")
    : fail("10% > threshold 5%: rechazó actualización incorrectamente");

$policy->should_update($rate_tiny, 5.0, 0, 'bidirectional', false)
    ? fail("0.1% < threshold 5%: debería rechazar pero aprobó")
    : pass("0.1% < threshold 5%: rechaza correctamente");

$policy->should_update($rate_up, 5.0, 8.0, 'bidirectional', false)
    ? fail("10% > threshold_max 8%: debería rechazar pero aprobó")
    : pass("10% > threshold_max 8%: rechaza por freno de seguridad");

$policy->should_update($rate_down, 0, 0, 'up_only', false)
    ? fail("up_only con bajada: debería rechazar pero aprobó")
    : pass("up_only con bajada -10%: rechaza correctamente");

// ── 11. Limpiar datos de test ─────────────────────────────────────────────
section('Limpieza');

if ($run_id) {
    $wpdb->delete("{$wpdb->prefix}prixy_run_items", ['run_id' => $run_id]);
    $wpdb->delete("{$wpdb->prefix}prixy_runs", ['id' => $run_id]);
    $deleted_run = $repo->get_run($run_id);
    !$deleted_run ? pass("Datos de test eliminados OK") : fail("No se pudieron eliminar los datos de test");
}

// ── Resumen ───────────────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 50) . "\n";
if ($errors === 0) {
    echo "✓ TODOS LOS TESTS PASARON\n";
} else {
    echo "✗ $errors TEST(S) FALLARON\n";
}
echo str_repeat('─', 50) . "\n";
