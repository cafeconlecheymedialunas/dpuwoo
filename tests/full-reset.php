<?php
/**
 * Reset COMPLETO del plugin DPUWoo
 * 
 * Ejecutar desde la carpeta public:
 * php wp-content/plugins/prixy/tests/full-reset.php
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

echo "=== RESET COMPLETO DPUWOO ===\n\n";

// 1. Limpiar tablas de logs
echo "1. Limpiando tablas de logs...\n";

$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}prixy_run_items");
echo "  ✅ prixy_run_items vaciada\n";

$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}prixy_runs");
echo "  ✅ prixy_runs vaciada\n";

// 2. Limpiar todos los metas de productos relacionados con prixy
echo "\n2. Limpiando metadatos de productos...\n";
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_prixy_%'");
echo "  ✅ Metas prixy eliminadas de productos\n";

// 3. Limpiar opciones/settings del plugin
echo "\n3. Limpiando settings del plugin...\n";
$options_to_delete = [
    'prixy_settings',
    'prixy_last_rate',
    'prixy_last_update',
    'prixy_origin_rate',
    'prixy_dollar_type',
    'prixy_reference_currency'
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}
echo "  ✅ Settings eliminados\n";

// 4. Verificar productos
echo "\n4. Productos en la tienda:\n";
$product_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");

foreach ($product_ids as $id) {
    $price = get_post_meta($id, '_regular_price', true);
    $name = get_the_title($id);
    echo "  ID $id: $name - $price ARS\n";
}

// 5. Verificar que no hay logs
echo "\n5. Verificando limpieza...\n";
$log_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_run_items");
echo "  Logs en prixy_run_items: $log_count\n";

$run_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prixy_runs");
echo "  Runs en prixy_runs: $run_count\n";

echo "\n=== RESET COMPLETO ✅ ===\n";
echo "El plugin está listo para configurar desde cero.\n";
echo "\nAhora ejecutá el test de integración:\n";
echo "php wp-content/plugins/prixy/tests/integration-test.php\n";