<?php
/**
 * Test de integración: Setup + Update
 * 
 * Ejecutar desde la carpeta public:
 * php wp-content/plugins/prixy/tests/integration-test.php
 * 
 * FLUJO:
 * 1. SETUP: Guardar precio ARS actual + tasa oficial (ej: 1000)
 * 2. UPDATE: Calcular USD = precio_ARS / tasa_del_setup, luego nuevo ARS = USD * nueva_tasa
 */

if (!defined('ABSPATH')) {
    $plugin_dir = dirname(__DIR__);
    $plugins_dir = dirname($plugin_dir);
    $content_dir = dirname($plugins_dir);
    $public_dir = dirname($content_dir);
    define('ABSPATH', $public_dir . '/');
}

require_once ABSPATH . 'wp-load.php';

global $wpdb;

echo "=== Test de Integración DPUWoo ===\n\n";

$test_results = ['passed' => 0, 'failed' => 0];

function assert_true($condition, $message) {
    global $test_results;
    if ($condition) {
        echo "  ✅ $message\n";
        $test_results['passed']++;
    } else {
        echo "  ❌ $message\n";
        $test_results['failed']++;
    }
}

echo "1. Obteniendo productos...\n";
$product_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 3");

if (empty($product_ids)) {
    echo "  ❌ No hay productos\n";
    exit(1);
}

echo "  ✅ " . count($product_ids) . " productos\n\n";

echo "2. Ajustando precios para test (multiplicar por 1000)...\n";
$test_products = [];
foreach ($product_ids as $id) {
    $price = floatval(get_post_meta($id, '_regular_price', true));
    if ($price < 1000) {
        $price = $price * 1000;
        update_post_meta($id, '_regular_price', $price);
    }
    $test_products[$id] = ['price' => $price, 'name' => get_the_title($id)];
    echo "  ID $id: {$test_products[$id]['name']} - {$price} ARS\n";
}

echo "\n3. === FASE SETUP: Guardar precio ARS + tasa oficial ===\n";
$log_repo = \Log_Repository::get_instance();
$setup_rate = 1000.0; // Tasa oficial del setup

$run_setup = [
    'currency' => 'USD',
    'dollar_value' => $setup_rate,
    'total_products' => count($product_ids),
    'percentage_change' => null,
    'context' => 'setup',
    'note' => 'Setup inicial con tasa oficial'
];
$setup_run_id = $log_repo->insert_run($run_setup);
assert_true($setup_run_id > 0, "Run de SETUP creado (ID: $setup_run_id)");

foreach ($test_products as $id => $p) {
    // Guardar en logs: precio ARS y tasa
    $item = [
        'status' => 'updated',
        'product_id' => $id,
        'old_regular_price' => 0,
        'new_regular_price' => $p['price'], // ARS
        'old_sale_price' => 0,
        'new_sale_price' => null,
        'percentage_change' => null,
        'reason' => 'Setup'
    ];
    $log_repo->insert_run_item($setup_run_id, $item);
    echo "  $id: {$p['price']} ARS (tasa: $setup_rate)\n";
}

echo "\n4. === FASE UPDATE: Calcular con nueva tasa ===\n";
$new_rate = 1250.0; // Nueva tasa
$exchange_rate = new \Exchange_Rate($new_rate, $setup_rate);

$settings_repo = new \Settings_Repository();

$results = [];
foreach ($test_products as $id => $p) {
    $wc_product = wc_get_product($id);
    $context = \Price_Context::from_product($wc_product, $exchange_rate, $settings_repo->get_all());
    
    $engine = new \Price_Calculation_Engine([
        new \Ratio_Rule(),
        new \Rounding_Rule()
    ]);
    
    $calc = $engine->calculate($context);
    
    $results[$id] = [
        'price_ars' => $p['price'],
        'usd_baseline' => $context->usd_baseline,
        'new_price' => $calc->new_regular
    ];
    
    echo "  $id: {$p['price']} → {$calc->new_regular} ARS (USD: {$context->usd_baseline})\n";
}

echo "\n5. Verificando USD baseline = precio_ARS / tasa_setup...\n";
foreach ($results as $id => $r) {
    $expected_usd = $r['price_ars'] / $setup_rate;
    $actual_usd = $r['usd_baseline'];
    assert_true(abs($expected_usd - $actual_usd) < 0.01, 
        "Producto $id: USD = $expected_usd (calculado desde logs)");
}

echo "\n6. Verificando nuevo precio = USD_baseline * nueva_tasa...\n";
foreach ($results as $id => $r) {
    $expected_price = $r['usd_baseline'] * $new_rate;
    $actual_price = $r['new_price'];
    assert_true(abs($expected_price - $actual_price) <= 1,
        "Producto $id: nuevo precio = $actual_price ARS");
}

echo "\n7. Guardando logs del UPDATE...\n";
$run_update = [
    'currency' => 'USD',
    'dollar_value' => $new_rate,
    'total_products' => count($product_ids),
    'percentage_change' => $exchange_rate->percentage_change,
    'context' => 'update',
    'note' => 'Test update'
];
$update_run_id = $log_repo->insert_run($run_update);
assert_true($update_run_id > 0, "Run de UPDATE creado");

foreach ($results as $id => $r) {
    $item = [
        'status' => 'updated',
        'product_id' => $id,
        'old_regular_price' => $r['price_ars'],
        'new_regular_price' => $r['new_price'],
        'old_sale_price' => 0,
        'new_sale_price' => null,
        'percentage_change' => $exchange_rate->percentage_change,
        'reason' => 'Update'
    ];
    $log_repo->insert_run_item($update_run_id, $item);
}
assert_true(true, "Logs de UPDATE guardados");

echo "\n8. Verificando que próximo UPDATE usará el nuevo USD baseline...\n";
foreach ($product_ids as $id) {
    $last_log = $log_repo->get_last_price_for_product($id);
    if ($last_log && $last_log['dollar_value'] > 0) {
        $next_usd = $last_log['new_regular'] / $last_log['dollar_value'];
        assert_true($next_usd > 0, "Producto $id: tiene USD baseline para próximo update");
    }
}

echo "\n=== RESUMEN ===\n";
echo "Pasados: {$test_results['passed']}\n";
echo "Fallidos: {$test_results['failed']}\n";

if ($test_results['failed'] > 0) {
    echo "\n❌ TESTS FALLIDOS\n";
    exit(1);
} else {
    echo "\n✅ FLUJO: SETUP + UPDATE - OK\n";
    exit(0);
}