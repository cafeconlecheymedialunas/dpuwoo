<?php
/**
 * Reset de la base de datos para tests
 * 
 * Ejecutar desde la carpeta public:
 * php wp-content/plugins/prixy/tests/reset-test.php
 */

if (!defined('ABSPATH')) {
    $plugin_dir = dirname(__DIR__); // prixy
    $plugins_dir = dirname($plugin_dir); // plugins
    $content_dir = dirname($plugins_dir); // wp-content
    $public_dir = dirname($content_dir); // public
    
    define('ABSPATH', $public_dir . '/');
}

require_once ABSPATH . 'wp-load.php';

global $wpdb;

echo "=== RESET DE BASE DE DATOS PARA TESTS ===\n\n";

// 1. Limpiar logs de prixy
echo "1. Limpiando tablas de logs...\n";

$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}prixy_run_items");
echo "  ✅ prixy_run_items vaciada\n";

$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}prixy_runs");
echo "  ✅ prixy_runs vaciada\n";

// 2. Limpiar meta de productos (opcional - solo si quiere resetear precios USD)
$product_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");

echo "\n2. Limpiando метаs de productos...\n";
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_prixy_regular_price_usd'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_prixy_sale_price_usd'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_prixy_first_setup_done'");
echo "  ✅ Metas de precios USD eliminadas\n";

// 3. Contar productos
$product_count = count($product_ids);
echo "\n3. Productos encontrados: $product_count\n";

// 4. Mostrar precios actuales
echo "\n4. Precios actuales de productos:\n";
foreach ($product_ids as $id) {
    $price = get_post_meta($id, '_regular_price', true);
    $name = get_the_title($id);
    echo "  ID $id: $name - $price ARS\n";
}

echo "\n=== RESET COMPLETO ===\n";
echo "La base está lista para hacer el SETUP inicial.\n";
echo "\nAhora podés ejecutar el test de integración:\n";
echo "php wp-content/plugins/prixy/tests/integration-test.php\n";