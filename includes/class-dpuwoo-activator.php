<?php
if (!defined('ABSPATH')) exit;

class Activator
{
    public static function activate()
    {
        // Prevent multiple executions
        if (get_option('dpuwoo_initial_setup_done')) {
            return;
        }
        
        self::create_tables();
        
        // Initialize the new baseline manager
        $baseline_manager = DPUWOO_Baseline_Manager::get_instance();
        $baseline_manager->create_table();
        $baseline_manager->auto_setup_baseline();
        
        // 1. AUTO-CONFIGURE DOLLAR REFERENCE CURRENCY
        self::auto_configure_dollar_reference();
        
        // 2. FETCH INITIAL DOLLAR VALUE FOR BASELINE (now managed by baseline manager)
        $settings = get_option('dpuwoo_settings', []);
        
        // 3. MINIMUM ESSENTIAL CONFIGURATION
        $settings['interval'] = $settings['interval'] ?? 3600;
        $settings['threshold'] = $settings['threshold'] ?? 1.0;
        $settings['reference_currency'] = $settings['reference_currency'] ?? 'USD'; // Auto-set USD
        
        update_option('dpuwoo_settings', $settings);
        
        // 4. SAVE AS "LAST DOLLAR" FOR FUTURE CALCULATIONS
        $current_baseline = $baseline_manager->get_current_baseline('dollar');
        if ($current_baseline) {
            update_option('dpuwoo_last_dollar_value', $current_baseline);
        }
        
        // 5. ESTABLISH BASELINES FOR ALL EXISTING PRODUCTS
        self::establish_baselines_for_all_products($current_baseline);
        
        // 6. SCHEDULE AUTOMATIC UPDATES
        if (!wp_next_scheduled('dpuwoo_do_update')) {
            wp_schedule_event(time() + 300, 'hourly', 'dpuwoo_do_update');
        }
        
        // 7. MARK AS ACTIVATED
        update_option('dpuwoo_initial_setup_done', true);
        
        // 8. ADMIN NOTIFICATION
        self::add_activation_notice($current_baseline);
    }

    private static function auto_configure_dollar_reference()
    {
        // Auto-configure USD as reference currency if not set
        $reference_currency = get_option('dpuwoo_reference_currency', '');
        
        if (empty($reference_currency)) {
            update_option('dpuwoo_reference_currency', 'USD');
            error_log('DPUWoo: Auto-configured USD as reference currency');
        }
    }
    
    private static function establish_baselines_for_all_products($current_dollar_rate)
    {
        if (!function_exists('get_posts') || !function_exists('get_post_meta') || !function_exists('update_post_meta')) {
            error_log('DPUWoo: WordPress functions not available for baseline establishment');
            return;
        }
        
        // Get all published products
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ]);
        
        $baselines_established = 0;
        
        foreach ($products as $product_id) {
            // Check if baseline already exists
            if (get_post_meta($product_id, '_dpuwoo_original_price_usd', true)) {
                continue; // Skip products that already have baseline
            }
            
            // Get current product price
            $current_price = get_post_meta($product_id, '_price', true);
            
            if ($current_price && $current_price > 0 && $current_dollar_rate > 0) {
                // Calculate and store USD baseline (ALWAYS IN USD)
                $baseline_usd = $current_price / $current_dollar_rate;
                update_post_meta($product_id, '_dpuwoo_original_price_usd', $baseline_usd);
                update_post_meta($product_id, '_dpuwoo_baseline_date', current_time('mysql'));
                $baselines_established++;
            }
        }
        
        error_log("DPUWoo: Established baselines for {$baselines_established} products");
    }
    
    private static function fetch_initial_dollar_value()
    {
        // Check if wp_remote_get exists (WordPress environment)
        if (!function_exists('wp_remote_get')) {
            error_log('DPU WooCommerce - wp_remote_get not available, using default dollar value');
            return 1; // Return default value
        }

        $url = "https://dolarapi.com/v1/dolares/oficial";
        $args = ['timeout' => 15, 'sslverify' => false];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            error_log('DPU WooCommerce - Error fetching dollar: ' . $res->get_error_message());
            return 1; // Return default value on error
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);
        
        if (!isset($body['venta'])) {
            error_log('DPU WooCommerce - Invalid API response');
            return 1; // Return default value on invalid response
        }

        $rate = floatval($body['venta']);
        return ($rate > 0) ? $rate : 1; // Ensure positive value
    }

    private static function add_activation_notice($detected_rate)
    {
        // Check if esc_html exists
        $safe_detected_rate = function_exists('esc_html') ? esc_html($detected_rate) : $detected_rate;
        
        $message = $detected_rate ? 
            sprintf('DPU WooCommerce activado. Dólar base detectado: <strong>$%s</strong>', $safe_detected_rate) :
            'DPU WooCommerce activado. Configura el dólar base en los ajustes.';

        update_option('dpuwoo_admin_notice', [
            'message' => $message,
            'type' => 'info',
            'dismissible' => true
        ]);
    }

    private static function create_tables()
    {
        global $wpdb;

        // Check if $wpdb is available
        if (!isset($wpdb) || !$wpdb) {
            error_log('DPU WooCommerce - $wpdb not available, skipping table creation');
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $runs_table = $wpdb->prefix . 'dpuwoo_runs';
        $items_table = $wpdb->prefix . 'dpuwoo_run_items';

        $sql_runs = "CREATE TABLE $runs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date datetime NOT NULL,
            dollar_type varchar(50) NOT NULL,
            dollar_value decimal(10,4) NOT NULL,
            rules text,
            total_products int(11) NOT NULL,
            user_id bigint(20) NOT NULL,
            note text,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_items = "CREATE TABLE $items_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            run_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            old_regular_price decimal(10,2),
            new_regular_price decimal(10,2),
            old_sale_price decimal(10,2),
            new_sale_price decimal(10,2),
            percentage_change decimal(5,2),
            status varchar(50) NOT NULL,
            reason text,
            PRIMARY KEY (id),
            KEY run_id (run_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        // Check if dbDelta function exists
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        dbDelta($sql_runs);
        dbDelta($sql_items);
    }
}