<?php
if (!defined('ABSPATH')) exit;

class Activator
{
    /** Versión del esquema de BD. Incrementar cuando cambie la estructura de tablas. */
    const DB_VERSION = '1.3';

    public static function activate()
    {
        self::create_tables();
        self::migrate_run_items_columns();

        if (get_option('dpuwoo_initial_setup_done')) {
            return;
        }
        
        $initial_rate = self::fetch_initial_dollar_value();
        $auto_provider = self::get_auto_provider();
        
        $settings = get_option('dpuwoo_settings', []);
        $settings['interval']             = $settings['interval']             ?? 3600;
        $settings['threshold']           = $settings['threshold']           ?? 1.0;
        $settings['threshold_max']       = $settings['threshold_max']       ?? 0;
        $settings['reference_currency']  = $settings['reference_currency'] ?? 'USD';
        $settings['api_provider']        = $settings['api_provider']        ?? $auto_provider;
        if (empty($settings['origin_exchange_rate'])) {
            $settings['origin_exchange_rate'] = $initial_rate > 0 ? $initial_rate : 0;
        }

        update_option('dpuwoo_settings', $settings);

        self::create_usd_price_fields_for_products();
        Cron::schedule();
        update_option('dpuwoo_initial_setup_done', true);

        set_transient('dpuwoo_activation_redirect', true, 60);
        self::add_activation_notice($initial_rate > 0);
    }

    private static function create_usd_price_fields_for_products()
    {
        if (!function_exists('get_posts') || !function_exists('update_post_meta')) {
            return;
        }
        
        $products = get_posts([
            'post_type' => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ]);
        
        $fields_created = 0;
        
        foreach ($products as $product_id) {
            update_post_meta($product_id, '_dpuwoo_regular_price_usd', '');
            update_post_meta($product_id, '_dpuwoo_sale_price_usd', '');
            $fields_created++;
        }
    }
    
    private static function auto_configure_dollar_reference()
    {
        // No es necesario configurar aquí ya que se maneja en admin-settings
        // La configuración se inicializa correctamente en activate()
        return;
    }
    
    private static function fetch_initial_dollar_value(): float
    {
        if (!function_exists('wp_remote_get')) {
            return 0;
        }

        $currency = function_exists('get_woocommerce_currency')
            ? get_woocommerce_currency()
            : 'ARS';

        if ($currency === 'USD') {
            return 1.0;
        }

        // cdn.jsdelivr.net/@fawazahmed0/currency-api — gratuita, sin key, 170+ monedas
        $currency_lower = strtolower($currency);
        $url  = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json';
        $args = ['timeout' => 15, 'sslverify' => false];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return 0;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (empty($body['usd'][$currency_lower])) {
            return 0;
        }

$rate = floatval($body['usd'][$currency_lower]);
        return $rate > 0 ? $rate : 0;
    }
    
    public static function get_auto_provider(): string
    {
        $dolarapi_countries = ['ar', 'cl', 'uy', 'br', 'mx', 'co', 'pe', 've', 'bo'];
        
        $base_country = get_option('woocommerce_default_country', 'AR:AR');
        $country_code = strtolower(explode(':', $base_country)[0]);
        
        if (in_array($country_code, $dolarapi_countries)) {
            return 'dolarapi';
        }
        
        return 'jsdelivr';
    }
    
    private static function add_activation_notice(bool $rate_ok = false): void
    {
        update_option('dpuwoo_admin_notice', [
            'message'     => $rate_ok
                ? 'Dollar Sync activado. Tasa de referencia inicializada automáticamente. Revisá la Configuración.'
                : 'Dollar Sync activado. No se pudo obtener la tasa automáticamente — ingresala manualmente en Configuración.',
            'type'        => $rate_ok ? 'success' : 'warning',
            'dismissible' => true,
        ]);
    }

    /**
     * Ejecuta migraciones de BD si la versión almacenada es anterior a la actual.
     * Seguro llamar en cada carga de admin — dbDelta solo toca lo necesario.
     */
    public static function maybe_upgrade(): void
    {
        if (get_option('dpuwoo_db_version') !== self::DB_VERSION) {
            self::create_tables();
            self::migrate_run_items_columns();
            self::migrate_runs_reference_currency();
            update_option('dpuwoo_db_version', self::DB_VERSION);
        }
    }

    private static function create_tables()
    {
        global $wpdb;

        if (!isset($wpdb) || !$wpdb) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $runs_table = $wpdb->prefix . 'dpuwoo_runs';
        $items_table = $wpdb->prefix . 'dpuwoo_run_items';

        $sql_runs = "CREATE TABLE $runs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date datetime NOT NULL,
            dollar_type varchar(50) NOT NULL,
            reference_currency varchar(10) DEFAULT 'USD',
            dollar_value decimal(10,4) NOT NULL,
            rules text,
            total_products int(11) NOT NULL DEFAULT 0,
            user_id bigint(20) NOT NULL DEFAULT 0,
            note text,
            percentage_change decimal(10,4) DEFAULT NULL,
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

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        dbDelta($sql_runs);
        dbDelta($sql_items);
        self::migrate_run_items_columns();
    }

    /**
     * Ejecuta ALTER TABLE para agregar columnas faltantes en run_items.
     * Compatible con MySQL 5.x (verifica existencia antes de ALTER).
     */
    public static function migrate_run_items_columns(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';

        $migrations = [
            'percentage_change' => 'decimal(5,2) AFTER new_sale_price',
            'reason'           => 'text AFTER status',
        ];

        foreach ($migrations as $column => $definition) {
            $col_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $table, $column
            ));

            if (empty($col_exists)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }

    /**
     * Ejecuta ALTER TABLE para agregar reference_currency en runs.
     * Compatible con MySQL 5.x (verifica existencia antes de ALTER).
     */
    public static function migrate_runs_reference_currency(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_runs';

        $col_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table, 'reference_currency'
        ));

        if (empty($col_exists)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN reference_currency varchar(10) DEFAULT 'USD' AFTER dollar_type");
        }
    }
}