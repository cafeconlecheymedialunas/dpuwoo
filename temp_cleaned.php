<?php
if (!defined('ABSPATH')) exit;

class Activator
{
    public static function activate()
    {
        if (get_option('dpuwoo_initial_setup_done')) {
            return;
        }
        
        self::create_tables();
        
        self::auto_configure_dollar_reference();
        
        $settings = get_option('dpuwoo_settings', []);
        $settings['interval'] = $settings['interval'] ?? 3600;
        $settings['threshold'] = $settings['threshold'] ?? 1.0;
        $settings['reference_currency'] = $settings['reference_currency'] ?? 'USD';
        $settings['last_rate'] = $settings['last_rate'] ?? 0;
        
        update_option('dpuwoo_settings', $settings);
        
        self::create_usd_price_fields_for_products();
        
        if (!wp_next_scheduled('dpuwoo_do_update')) {
            wp_schedule_event(time() + 300, 'hourly', 'dpuwoo_do_update');
        }
        
        update_option('dpuwoo_initial_setup_done', true);
        
        self::add_activation_notice();
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
        $reference_currency = get_option('dpuwoo_reference_currency', '');
        
        if (empty($reference_currency)) {
            update_option('dpuwoo_reference_currency', 'USD');
        }
    }
    
    private static function fetch_initial_dollar_value()
    {
        if (!function_exists('wp_remote_get')) {
            return 1;
        }

        $url = "https://dolarapi.com/v1/dolares/oficial";
        $args = ['timeout' => 15, 'sslverify' => false];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return 1;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);
        
        if (!isset($body['venta'])) {
            return 1;
        }

        $rate = floatval($body['venta']);
        return ($rate > 0) ? $rate : 1;
    }

    private static function add_activation_notice()
    {
        $message = 'DPU WooCommerce activado. Configura la tasa de cambio en los ajustes.';

        update_option('dpuwoo_admin_notice', [
            'message' => $message,
            'type' => 'info',
            'dismissible' => true
        ]);
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

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        dbDelta($sql_runs);
        dbDelta($sql_items);
    }
}
<?php
if (!defined('ABSPATH')) exit;

class Admin_Settings
{
    public static function register_settings()
    {
        register_setting(
            'dpuwoo_settings_group',
            'dpuwoo_settings',
            ['sanitize_callback' => [__CLASS__, 'sanitize']]
        );

        // ========== SECCIONES ==========
        
        // Sección de Configuración de Origen
        add_settings_section(
            'dpuwoo_main_section',
            '📌 Configuración de Origen',
            [__CLASS__, 'render_main_section_description'],
            'dpuwoo_settings'
        );

        // Sección de Cálculo y Ajuste
        add_settings_section(
            'dpuwoo_calculation_section',
            '🧮 Lógica de Cálculo',
            [__CLASS__, 'render_calculation_section_description'],
            'dpuwoo_settings'
        );

        // Sección de Reglas de Redondeo
        add_settings_section(
            'dpuwoo_rounding_section',
            '🔢 Reglas de Redondeo',
            [__CLASS__, 'render_rounding_section_description'],
            'dpuwoo_settings'
        );

        // Sección de Automatización
        add_settings_section(
            'dpuwoo_automation_section',
            '⚡ Automatización',
            [__CLASS__, 'render_automation_section_description'],
            'dpuwoo_settings'
        );

        // Sección de Exclusiones
        add_settings_section(
            'dpuwoo_exclusion_section',
            '🚫 Exclusiones',
            [__CLASS__, 'render_exclusion_section_description'],
            'dpuwoo_settings'
        );

        // ========== CAMPOS DE CONFIGURACIÓN DE ORIGEN ==========
        
        add_settings_field(
            'dpuwoo_api_provider',
            'Proveedor de API',
            [__CLASS__, 'render_api_provider'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_currencyapi_api_key',
            'Api Key de CurrencyAPI',
            [__CLASS__, 'render_currencyapi_api_key'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_exchangerate_api_key',
            'Api Key de Exchange Rate',
            [__CLASS__, 'render_exchangerate_api_key'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );
          
        add_settings_field(
            'dpuwoo_country',
            'País de la tienda',
            [__CLASS__, 'render_country_selector'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_base_currency',
            'Moneda base',
            [__CLASS__, 'render_base_currency'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_reference_currency',
            'Moneda de referencia',
            [__CLASS__, 'render_reference_currency'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );
        
        add_settings_field(
            'dpuwoo_origin_exchange_rate',
            'Tasa de Cambio de Origen',
            [__CLASS__, 'render_origin_exchange_rate'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );
        // ========== CAMPOS DE CÁLCULO Y AJUSTE ==========
        
        add_settings_field(
            'dpuwoo_margin',
            'Margen de Corrección',
            [__CLASS__, 'render_margin'],
            'dpuwoo_settings',
            'dpuwoo_calculation_section'
        );

        add_settings_field(
            'dpuwoo_threshold',
            'Umbral de Cambio',
            [__CLASS__, 'render_threshold'],
            'dpuwoo_settings',
            'dpuwoo_calculation_section'
        );

        add_settings_field(
            'dpuwoo_update_direction',
            'Sentido de Actualización',
            [__CLASS__, 'render_update_direction'],
            'dpuwoo_settings',
            'dpuwoo_calculation_section'
        );

        // ========== CAMPOS DE REDONDEO ==========
        
        add_settings_field(
            'dpuwoo_rounding_type',
            'Tipo de Redondeo',
            [__CLASS__, 'render_rounding_type'],
            'dpuwoo_settings',
            'dpuwoo_rounding_section'
        );

        add_settings_field(
            'dpuwoo_nearest_to',
            'Redondear a',
            [__CLASS__, 'render_nearest_to'],
            'dpuwoo_settings',
            'dpuwoo_rounding_section'
        );

        // ========== CAMPOS DE AUTOMATIZACIÓN ==========
        
        add_settings_field(
            'dpuwoo_update_interval',
            'Frecuencia de Actualización',
            [__CLASS__, 'render_interval'],
            'dpuwoo_settings',
            'dpuwoo_automation_section'
        );

        // ========== CAMPOS DE EXCLUSIÓN ==========
    

        add_settings_field(
            'dpuwoo_exclude_categories',
            'Excluir Categorías',
            [__CLASS__, 'render_exclude_categories'],
            'dpuwoo_settings',
            'dpuwoo_exclusion_section'
        );
    }

    // ========== DESCRIPCIONES DE SECCIONES ==========
    
    public static function render_main_section_description()
    {
        $store_currency = get_woocommerce_currency();
        
        // Obtener país base de WooCommerce
        $base_country = get_option('woocommerce_default_country', '');
        if (strpos($base_country, ':') !== false) {
            $base_country = substr($base_country, 0, strpos($base_country, ':'));
        }
        
        // Obtener nombre del país
        $country_name = '';
        if (function_exists('WC') && method_exists(WC()->countries, 'get_countries')) {
            $countries = WC()->countries->get_countries();
            $country_name = $countries[$base_country] ?? $base_country;
        } else {
            $country_name = $base_country;
        }
        
        $opts = get_option('dpuwoo_settings', []);
        
        echo '<div class="dpuwoo-section-description">';
        echo '<p><strong>Configuración base del sistema de actualización:</strong></p>';
        
        echo '<div class="notice notice-info" style="padding: 10px; margin: 10px 0;">';
        echo '<strong>País de la tienda:</strong> ' . esc_html($country_name . ' (' . $base_country . ')') . '<br>';
        echo '<strong>Moneda base:</strong> ' . esc_html($store_currency) . ' (precios actuales del catálogo)<br>';
        echo '<strong>Moneda de referencia:</strong> ' . esc_html($opts['reference_currency'] ?? 'USD') . ' (para el cálculo)<br>';
        echo '<strong>Tasa de cambio de origen:</strong> ' . esc_html($opts['origin_exchange_rate'] ?? '1.0') . ' (tasa histórica base)';
        echo '</div>';
        
        echo '<p class="description">El país y la moneda base se obtienen de la configuración principal de WooCommerce. Para modificarlos, ve a <strong>WooCommerce → Ajustes → Generales</strong>.</p>';
        echo '</div>';
    }

    public static function render_calculation_section_description()
    {
        echo '<div class="dpuwoo-section-description">';
        echo '<p><strong>Configura cómo se calculan los nuevos precios:</strong></p>';
        echo '</div>';
    }

    public static function render_rounding_section_description()
    {
        echo '<div class="dpuwoo-section-description">';
        echo '<p><strong>Define el formato final de los precios:</strong></p>';
        echo '</div>';
    }

    public static function render_automation_section_description()
    {
        echo '<div class="dpuwoo-section-description">';
        echo '<p><strong>Configura la frecuencia de actualización automática:</strong></p>';
        echo '</div>';
    }

    public static function render_exclusion_section_description()
    {
        echo '<div class="dpuwoo-section-description">';
        echo '<p><strong>Productos que no se actualizarán automáticamente:</strong></p>';
        echo '</div>';
    }

    // ========== FUNCIÓN DE SANITIZACIÓN ==========
    
    public static function sanitize($input)
    {
        $out = [];
        
        // Configuración de Origen
        $out['api_provider'] = sanitize_text_field($input['api_provider'] ?? 'dolarsi');
        $out['currencyapi_api_key'] = sanitize_text_field($input['currencyapi_api_key'] ?? '');
        $out['exchangerate_api_key'] = sanitize_text_field($input['exchangerate_api_key'] ?? '');
        $out['country'] = sanitize_text_field($input['country'] ?? 'AR');
        $out['base_currency'] = sanitize_text_field($input['base_currency'] ?? get_woocommerce_currency());
        $out['reference_currency'] = sanitize_text_field($input['reference_currency'] ?? 'USD');
        $out['origin_exchange_rate'] = floatval($input['origin_exchange_rate'] ?? 1.0);
        
        // Agregar campos de referencia de moneda
        $out['last_rate'] = floatval($input['last_rate'] ?? 0);
        
        // Cálculo y Ajuste
        $out['margin'] = floatval($input['margin'] ?? 0);
        $out['threshold'] = floatval($input['threshold'] ?? 0.5);
        $out['update_direction'] = sanitize_text_field($input['update_direction'] ?? 'bidirectional');
        
        // Reglas de Redondeo
        $out['rounding_type'] = sanitize_text_field($input['rounding_type'] ?? 'integer');
        $out['nearest_to'] = sanitize_text_field($input['nearest_to'] ?? '1');
       
        // Automatización
        $out['interval'] = intval($input['interval'] ?? 3600);
        
        // Exclusiones
        $out['exclude_categories'] = array_map('intval', $input['exclude_categories'] ?? []);

        return $out;
    }

    // ========== FUNCIONES DE RENDER ==========

    public static function render_country_selector()
    {
        // Obtener el país base desde las opciones generales de WooCommerce
        $base_country = get_option('woocommerce_default_country', '');
        
        // Extraer solo el código del país (ej: 'AR' de 'AR:C')
        if (strpos($base_country, ':') !== false) {
            $base_country = substr($base_country, 0, strpos($base_country, ':'));
        }
        
        // Obtener el nombre del país a partir de su código
        $country_name = $base_country;
        if (function_exists('WC') && method_exists(WC()->countries, 'get_countries')) {
            $countries = WC()->countries->get_countries();
            $country_name = $countries[$base_country] ?? $base_country;
        }
        
        // Mostrar la información (solo lectura)
        echo '<input type="text" readonly value="' . esc_attr($country_name . ' (' . $base_country . ')') . '" class="regular-text" style="background-color:#f0f0f0;">';
        echo '<p class="description">Configurado en <strong>WooCommerce → Ajustes → Generales → Ubicación de la tienda</strong>. Solo se puede modificar desde allí.</p>';
        
        // Campo oculto para guardar el valor en nuestra configuración
        echo '<input type="hidden" name="dpuwoo_settings[country]" value="' . esc_attr($base_country) . '">';
    }

    public static function render_base_currency()
    {
        // Obtener la moneda base de WooCommerce
        $store_currency = get_woocommerce_currency();
        
        // Mostrar la información (solo lectura)
        echo '<input type="text" readonly value="' . esc_attr($store_currency) . '" class="regular-text" style="background-color:#f0f0f0;">';
        echo '<p class="description">Moneda configurada en <strong>WooCommerce → Ajustes → Generales → Opciones de moneda</strong>. Es la moneda en la que están expresados los precios de tu catálogo.</p>';
        
        // Campo oculto para guardar el valor
        echo '<input type="hidden" name="dpuwoo_settings[base_currency]" value="' . esc_attr($store_currency) . '">';
    }

    public static function render_reference_currency()
    {
        $opts = get_option('dpuwoo_settings', []);
        $store_currency = get_woocommerce_currency();
        $val = $opts['reference_currency'] ?? 'USD';

        // Contenedor para el campo dinámico y el indicador de carga
        echo '<div id="dpuwoo_currency_selector_container">';
        echo '<select name="dpuwoo_settings[reference_currency]" id="dpuwoo_reference_currency" class="regular-text" disabled>';
        echo '<option value="' . esc_attr($val) . '">' . esc_html($val . ' - Cargando...') . '</option>';
        echo '</select>';
        echo '<span id="dpuwoo_currency_loading" class="spinner is-active" style="float: none; margin-left: 5px;"></span>';
        echo '</div>';

        echo '<p class="description" id="dpuwoo_currency_description">';
        echo 'Moneda cuya cotización se usará para recalcular los precios. Selecciona primero el proveedor de API.';
        echo '</p>';

        // Campo oculto para el país base (obtenido de WooCommerce)
        $base_country = get_option('woocommerce_default_country', '');
        if (strpos($base_country, ':') !== false) {
            $base_country = substr($base_country, 0, strpos($base_country, ':'));
        }
        echo '<input type="hidden" id="dpuwoo_base_country" value="' . esc_attr($base_country) . '">';
    }
    
    public static function render_origin_exchange_rate()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['origin_exchange_rate'] ?? 1.0;
        $base_currency = get_woocommerce_currency();
        $reference_currency = $opts['reference_currency'] ?? '';
        
        // Solo mostrar el campo si hay una moneda de referencia seleccionada
        $show_field = !empty($reference_currency);
        
        echo '<div class="dpuwoo-origin-rate-container" id="dpuwoo_origin_rate_container" style="' . ($show_field ? '' : 'display: none;') . '">';
        
        if ($show_field) {
            echo '<input type="number" step="0.0001" min="0.0001" name="dpuwoo_settings[origin_exchange_rate]" value="' . esc_attr($val) . '" class="regular-text" id="dpuwoo_origin_exchange_rate">';
            
            echo '<div class="dpuwoo-rate-info" style="margin-top: 8px; padding: 10px; background-color: #f0f8ff; border-left: 4px solid #007cba; border-radius: 4px;">';
            echo '<strong>ℹ️ ¿Qué es este campo?</strong><br>';
            echo 'Esta es la tasa de cambio en la que fueron establecidos originalmente los precios de tus productos.<br>';
            echo '<strong>Ejemplo:</strong> Si tus productos están en ' . esc_html($base_currency) . ' y originalmente valían 1000 ' . esc_html($base_currency) . ' cuando 1 USD = 100 ' . esc_html($base_currency) . ', entonces esta tasa sería <strong>100</strong>.<br>';
            echo '<strong>Importante:</strong> Este valor se usa como base para calcular todos los futuros cambios de precio.';
            echo '</div>';
            
            echo '<div class="dpuwoo-auto-set-info" style="margin-top: 8px; padding: 10px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
            echo '<strong>🔄 Configuración automática:</strong><br>';
            echo 'Cuando selecciones una moneda en "Moneda de referencia", este campo se actualizará automáticamente con la tasa actual obtenida de la API.<br>';
            echo 'También puedes modificarlo manualmente si conoces la tasa histórica exacta en la que se establecieron los precios originales.';
            echo '</div>';
            
            echo '<p class="description">Tasa de cambio utilizada como punto de partida para calcular variaciones de precios.</p>';
        } else {
            echo '<div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;">';
            echo '<p><strong>ℹ️ Campo disponible:</strong> Selecciona primero una "Moneda de referencia" para configurar la tasa de cambio de origen.</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    public static function render_api_provider()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['api_provider'] ?? 'dolarapi'; // Valor por defecto DolarAPI

        // Proveedores permitidos
        $providers = API_Client::get_available_providers();

        echo '<select name="dpuwoo_settings[api_provider]" id="dpuwoo_api_provider" class="regular-text">';
        foreach ($providers as $k => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($k),
                selected($val, $k, false),
                esc_html($label["name"])
            );
        }
        echo '</select>';
        echo '<p class="description">Elige el proveedor de tasas de cambio. Las monedas disponibles se cargarán según tu selección.</p>';
    }

   public static function render_currencyapi_api_key()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['currencyapi_api_key'] ?? '';
        $current_provider = $opts['api_provider'] ?? 'dolarapi';
        $is_visible = ($current_provider === 'currencyapi') ? 'dpuwoo-api-key-visible' : 'dpuwoo-api-key-hidden';
                
        echo '<div id="dpuwoo_currencyapi_key_container" class="dpuwoo-api-key-field ' . esc_attr($is_visible) . '">';
        echo '<input type="password" name="dpuwoo_settings[currencyapi_api_key]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">Ingrese su API Key de CurrencyAPI. <a href="https://currencyapi.com/" target="_blank">Obtener clave</a></p>';
    }

    public static function render_exchangerate_api_key()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['exchangerate_api_key'] ?? '';
        $current_provider = $opts['api_provider'] ?? 'dolarapi';
        $is_visible = ($current_provider === 'exchangerate-api') ? 'dpuwoo-api-key-visible' : 'dpuwoo-api-key-hidden';
        
        echo '<div id="dpuwoo_exchangerate_key_container" class="dpuwoo-api-key-field ' . esc_attr($is_visible) . '">';
        echo '<input type="password" name="dpuwoo_settings[exchangerate_api_key]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">Ingrese su API Key de ExchangeRate-API. <a href="https://www.exchangerate-api.com/" target="_blank">Obtener clave</a></p>';
        echo '</div>';
    }

    public static function render_margin()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['margin'] ?? 0;
        
        echo '<input type="number" step="0.1" name="dpuwoo_settings[margin]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Porcentaje extra que se suma a la variación del tipo de cambio.</p>';
    }

    public static function render_threshold()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['threshold'] ?? 0.5;
        
        echo '<input type="number" step="0.1" min="0" name="dpuwoo_settings[threshold]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Variación mínima requerida para actualizar precios (recomendado: 0.5%).</p>';
    }

    public static function render_update_direction()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['update_direction'] ?? 'bidirectional';
        $directions = [
            'bidirectional' => 'Bidireccional (Sube y baja)',
            'up_only' => 'Solo incremento',
            'down_only' => 'Solo disminución'
        ];
        
        echo '<select name="dpuwoo_settings[update_direction]" class="regular-text">';
        foreach ($directions as $k => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($k),
                selected($val, $k, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">Dirección en la que se actualizan los precios.</p>';
    }

    public static function render_rounding_type()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['rounding_type'] ?? 'integer';
        $types = [
            'none' => 'Sin redondeo',
            'integer' => 'Enteros',
            'ceil' => 'Al mayor (Ceiling)',
            'floor' => 'Al menor (Floor)',
            'nearest' => 'Al más cercano'
        ];
        
        echo '<select name="dpuwoo_settings[rounding_type]" class="regular-text">';
        foreach ($types as $k => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($k),
                selected($val, $k, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">Cómo se redondean los decimales.</p>';
    }

    public static function render_nearest_to()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['nearest_to'] ?? '1';
        $options = [
            '1' => 'Unidad',
            '10' => 'Decena',
            '50' => 'Cincuenta',
            '100' => 'Centena'
        ];
        
        echo '<select name="dpuwoo_settings[nearest_to]" class="regular-text">';
        foreach ($options as $k => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($k),
                selected($val, $k, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">A qué valor se redondea el precio.</p>';
    }

    public static function render_interval()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['interval'] ?? 3600;
        
        $hours = floor($val / 3600);
        $minutes = floor(($val % 3600) / 60);
        
        echo '<input type="number" name="dpuwoo_settings[interval]" value="' . esc_attr($val) . '" min="300" class="small-text"> segundos';
        echo '<div style="margin-top: 5px;">';
        echo 'Equivalente a: ' . esc_html($hours) . 'h ' . esc_html($minutes) . 'm';
        echo '</div>';
        echo '<p class="description">Frecuencia de consulta a la API (mínimo: 300 segundos = 5 minutos).</p>';
    }

    public static function render_exclude_categories()
    {
        $opts = get_option('dpuwoo_settings', []);
        $selected = $opts['exclude_categories'] ?? [];
        
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
        ]);
        
        if (!empty($categories) && !is_wp_error($categories)) {
            echo '<select name="dpuwoo_settings[exclude_categories][]" multiple="multiple" class="regular-text" style="height: 150px;">';
            foreach ($categories as $category) {
                $is_selected = in_array($category->term_id, $selected);
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($category->term_id),
                    selected($is_selected, true, false),
                    esc_html($category->name . ' (' . $category->count . ')')
                );
            }
            echo '</select>';
            echo '<p class="description">Mantén presionada la tecla Ctrl para seleccionar múltiples categorías.</p>';
        } else {
            echo '<p>No hay categorías creadas.</p>';
        }
    }
    
    private static function get_country_name($country_code)
    {
        if (function_exists('WC') && method_exists(WC()->countries, 'get_countries')) {
            $countries = WC()->countries->get_countries();
            return $countries[$country_code] ?? $country_code;
        }
        return $country_code;
    }
}
<?php
if (!defined('ABSPATH')) exit;

class Ajax_Manager
{
    /**
     * Nueva simulación por lotes
     */
    public function ajax_simulate_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');
        
        $batch = intval($_POST['batch'] ?? 0);
        $updater = Price_Updater::get_instance();
        
        // SIMULACIÓN: forzar a que use baseline como previous_dollar_value
        $res = $updater->update_all_batch(true, $batch);
        
        if (isset($res['error'])) {
            wp_send_json_error($res);
        }
        
        // Añadir información detallada de configuración
        $opts = get_option('dpuwoo_settings', []);
        $res['reference_used'] = 'baseline'; // Para simulación
        
        // Usar promedio de baselines USD de productos
        global $wpdb;
        $avg_baseline = $wpdb->get_var("SELECT AVG(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = '_dpuwoo_original_price_usd' AND meta_value > 0");
        $res['baseline_rate'] = $avg_baseline ? floatval($avg_baseline) : 0;
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
            'margin' => floatval($opts['margin'] ?? 0),
            'threshold' => floatval($opts['threshold'] ?? 0.5),
            'update_direction' => $opts['update_direction'] ?? 'bidirectional',
            'rounding_type' => $opts['rounding_type'] ?? 'integer',
            'last_rate' => floatval($opts['last_rate'] ?? 0)
        ];
        
        wp_send_json_success($res);
    }

    public function ajax_get_runs()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');
        $logger = Logger::get_instance();
        $rows = $logger->get_runs();

        wp_send_json_success($rows);
    }

    public function ajax_get_run_items()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) wp_send_json_error('Invalid run');

        // Usar el Logger que ya tiene la lógica de enriquecimiento
        $logger = Logger::get_instance();
        $items = $logger->get_run_items($run_id, 500);

        wp_send_json_success($items);
    }

    /**
     * Actualización real por lotes (después de simulación)
     */
    public function ajax_update_batch() {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');
        
        $batch = intval($_POST['batch'] ?? 0);
        $updater = Price_Updater::get_instance();
        $res = $updater->update_all_batch(false, $batch);
        
        if (isset($res['error'])) {
            wp_send_json_error($res);
        }
        
        // Añadir información sobre qué referencia se usó
        $opts = get_option('dpuwoo_settings', []);
        $res['reference_used'] = 'last_rate'; // Para actualización real
        $res['last_rate'] = floatval($opts['last_rate'] ?? 0);
        
        // Usar promedio de baselines USD de productos
        global $wpdb;
        $avg_baseline = $wpdb->get_var("SELECT AVG(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = '_dpuwoo_original_price_usd' AND meta_value > 0");
        $res['baseline_rate'] = $avg_baseline ? floatval($avg_baseline) : 0;
        
        // Añadir configuración completa para mostrar en resultados
        $res['execution_config'] = [
            'reference_currency' => $opts['reference_currency'] ?? 'USD',
            'api_provider' => $opts['api_provider'] ?? 'dolarapi_argentina',
            'margin' => floatval($opts['margin'] ?? 0),
            'threshold' => floatval($opts['threshold'] ?? 0.5),
            'update_direction' => $opts['update_direction'] ?? 'bidirectional',
            'rounding_type' => $opts['rounding_type'] ?? 'integer',
            'last_rate' => floatval($opts['last_rate'] ?? 0)
        ];
        
        wp_send_json_success($res);
    }

    public function ajax_revert_item()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');

        $log_id = intval($_POST['log_id'] ?? 0);
        if (!$log_id) wp_send_json_error('invalid_log');
        $ok = Logger::get_instance()->rollback_item($log_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'reverted', 'log_id' => $log_id]);
    }

    public function ajax_revert_run()
    {
        if (!current_user_can('manage_options')) wp_send_json_error('No permissions');
        check_ajax_referer('dpuwoo_ajax_nonce', 'nonce');

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) wp_send_json_error('invalid_run');

        $ok = Logger::get_instance()->rollback_run($run_id);
        if (is_wp_error($ok)) wp_send_json_error($ok->get_error_message());

        wp_send_json_success(['message' => 'run_everted', 'run_id' => $run_id]);
    }

    /**
     * AJAX Handler: Obtener monedas según proveedor
     */
    public static function ajax_get_currencies()
    {
        // Verificar nonce para seguridad
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $country = sanitize_text_field($_POST['country'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        
        // Configurar proveedor específico si se solicita
      
        
        // Si se especifica país, actualizar configuración temporalmente
        if (!empty($country)) {
            update_option('woocommerce_default_country', $country);
        }
        
        // Obtener cotizaciones usando el proveedor especificado
        $currencies_data = $api_client->get_currencies($provider);
        
        if (!$currencies_data) {
            wp_send_json_error([
                'message' => 'No se pudieron obtener datos del proveedor: ' . $provider,
                'provider' => $provider,
                'country' => $country
            ]);
        }
        
        // Formatear respuesta según el tipo de proveedor
        $formatted_currencies = [];
        $provider_info = $api_client->get_provider($provider);
    
        
        wp_send_json_success([
            'currencies' => $currencies_data,
            'provider' => $provider,
            'count' => count($currencies_data)
        ]);
    }

    /**
     * AJAX Handler: Obtener tasa de cambio actual
     */
    public static function ajax_get_current_rate()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'oficial');
        $currency_pair = sanitize_text_field($_POST['currency_pair'] ?? '');
        
        // Configurar proveedor si se especifica
        if (!empty($provider)) {
            $api_client->set_provider($provider);
        }
        
        // Determinar qué tipo de rate obtener
        $rate_type = !empty($currency_pair) ? $currency_pair : $type;
        
        // Obtener la tasa
        $rate_data = $api_client->get_rate($rate_type);
        
        if ($rate_data) {
            $response = [
                'success' => true,
                'rate' => $rate_data['value'] ?? 0,
                'buy' => $rate_data['buy'] ?? $rate_data['value'] ?? 0,
                'sell' => $rate_data['sell'] ?? $rate_data['value'] ?? 0,
                'updated' => $rate_data['updated'] ?? current_time('mysql'),
                'provider' => $rate_data['provider'] ?? $provider,
                'raw' => $rate_data['raw'] ?? []
            ];
            
            // Actualizar última tasa en opciones si es exitosa
            if (isset($rate_data['value']) && $rate_data['value'] > 0) {
                $opts = get_option('dpuwoo_settings', []);
                $opts['last_rate'] = floatval($rate_data['value']);
                update_option('dpuwoo_settings', $opts);
            }
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error([
                'message' => 'No se pudo obtener la tasa de cambio',
                'provider' => $provider,
                'type' => $rate_type
            ]);
        }
    }

    /**
     * AJAX Handler: Obtener todas las cotizaciones disponibles
     */
    public static function ajax_get_all_rates()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        
        $rates = $api_client->get_currencies();
        
        if ($rates) {
            wp_send_json_success([
                'success' => true,
                'rates' => $rates,
                'count' => count($rates),
                'provider' => $provider,
                'timestamp' => current_time('mysql')
            ]);
        } else {
            wp_send_json_error([
                'message' => 'No se pudieron obtener las cotizaciones',
                'provider' => $provider
            ]);
        }
    }

    /**
     * AJAX Handler: Probar conexión con API
     */
    public static function ajax_test_api_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $api_client = API_Client::get_instance();
        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        // Guardar API key temporalmente para la prueba
        if (!empty($api_key)) {
            $opts = get_option('dpuwoo_settings', []);
            $opts['api_key'] = $api_key;
            $opts['api_provider'] = $provider;
            update_option('dpuwoo_settings', $opts);
        }
        
        // Probar conexión usando el método del proveedor
        $test_result = $api_client->test_connection($provider);
        
        if ($test_result['success']) {
            $result = [
                'provider' => $provider,
                'status' => 'connected',
                'timestamp' => current_time('mysql'),
                'message' => $test_result['message'] ?? 'Conexión exitosa',
                'http_code' => $test_result['http_code'] ?? 200,
                'url' => $test_result['url'] ?? ''
            ];
            
            // Si la prueba fue exitosa, limpiar cache
            $api_client->clear_cache();
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error([
                'provider' => $provider,
                'status' => 'failed',
                'timestamp' => current_time('mysql'),
                'message' => $test_result['error'] ?? 'Error de conexión',
                'http_code' => $test_result['http_code'] ?? 0,
                'url' => $test_result['url'] ?? ''
            ]);
        }
    }

    /**
     * AJAX Handler: Obtener información de proveedores disponibles
     */
    public static function ajax_get_providers_info()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $providers = API_Client::get_available_providers();
        $current_provider = get_option('dpuwoo_settings', [])['api_provider'] ?? 'dolarapi';
        
        wp_send_json_success([
            'providers' => $providers,
            'current_provider' => $current_provider,
            'woocommerce_currency' => get_woocommerce_currency(),
            'woocommerce_country' => get_option('woocommerce_default_country', 'AR')
        ]);
    }

    /**
     * AJAX Handler: Actualizar configuración del proveedor
     */
    public static function ajax_update_provider_config()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($provider)) {
            wp_send_json_error(['message' => 'Proveedor no especificado']);
        }
        
        $opts = get_option('dpuwoo_settings', []);
        $opts['api_provider'] = $provider;
        
        if (!empty($api_key)) {
            $opts['api_key'] = $api_key;
        }
        
        update_option('dpuwoo_settings', $opts);
        
        wp_send_json_success([
            'message' => 'Configuración actualizada',
            'provider' => $provider,
        ]);
    }

    /**
     * AJAX Handler: Guardar configuración de settings
     */
    public static function ajax_save_settings()
    {
        
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
       
            wp_send_json_error(['message' => 'Acceso no autorizado - Token inválido']);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
    
        
        // Check specifically for dpuwoo_settings keys
        $dpuwoo_keys = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'dpuwoo_settings[') === 0) {
                $dpuwoo_keys[] = $key;
            }
        }
        );
        
        // Collect settings data - try multiple methods
        $settings_data = [];
        
        // Method 1: Check for nested settings array (new format)
        if (isset($_POST['dpuwoo_settings']) && is_array($_POST['dpuwoo_settings'])) {
            $settings_data = $_POST['dpuwoo_settings'];
             . ' items');
        }
        
        // Method 2: Check for alternative nested format
        if (empty($settings_data) && isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings_data = $_POST['settings'];
             . ' items');
        }
        
        // Method 3: Direct collection from flattened format (old format)
        if (empty($settings_data)) {
            
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'dpuwoo_settings[') === 0) {
                    // Extract field name: dpuwoo_settings[field_name] -> field_name
                    $field_name = preg_replace('/^dpuwoo_settings\[([^\]]+)\]$/', '$1', $key);
                    $settings_data[$field_name] = $value;
                     ? 'ARRAY' : $value));
                }
            }
             . ' fields');
        }
        
        // Method 3: If still empty, log everything we received
        if (empty($settings_data)) {
            );
        }
        
        if (empty($settings_data) || !is_array($settings_data)) {
            
            , true));
            wp_send_json_error([
                'message' => 'Datos de configuración inválidos - No se recibieron datos del formulario'
            ]);
        }
        
        // Sanitizar y validar los datos
        $sanitized_data = [];
        
        // API Keys
        $sanitized_data['currencyapi_api_key'] = sanitize_text_field($settings_data['currencyapi_api_key'] ?? '');
        $sanitized_data['exchangerate_api_key'] = sanitize_text_field($settings_data['exchangerate_api_key'] ?? '');
        
        // Configuración básica
        $sanitized_data['api_provider'] = sanitize_text_field($settings_data['api_provider'] ?? 'dolarapi');
        $sanitized_data['country'] = sanitize_text_field($settings_data['country'] ?? 'AR');
        $sanitized_data['base_currency'] = sanitize_text_field($settings_data['base_currency'] ?? get_woocommerce_currency());
        $sanitized_data['reference_currency'] = sanitize_text_field($settings_data['reference_currency'] ?? 'USD');
        
        // Valores de referencia
        $sanitized_data['last_rate'] = floatval($settings_data['last_rate'] ?? 0);
        
        // Cálculo y ajuste
        $sanitized_data['margin'] = floatval($settings_data['margin'] ?? 0);
        $sanitized_data['threshold'] = floatval($settings_data['threshold'] ?? 0.5);
        $sanitized_data['update_direction'] = sanitize_text_field($settings_data['update_direction'] ?? 'bidirectional');
        
        // Redondeo
        $sanitized_data['rounding_type'] = sanitize_text_field($settings_data['rounding_type'] ?? 'integer');
        $sanitized_data['nearest_to'] = sanitize_text_field($settings_data['nearest_to'] ?? '1');
        $sanitized_data['psychological_ending'] = sanitize_text_field($settings_data['psychological_ending'] ?? '99');
        
        // Automatización
        $sanitized_data['interval'] = intval($settings_data['interval'] ?? 3600);
        
        // Exclusiones
        $exclude_categories = $settings_data['exclude_categories'] ?? [];
        $sanitized_data['exclude_categories'] = is_array($exclude_categories) && !empty($exclude_categories)
            ? array_map('intval', $exclude_categories)
            : [];

        
        // Test direct database write first
        global $wpdb;
        $option_name = 'dpuwoo_settings';
        $option_value = serialize($sanitized_data);
        
        // Check if option exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $option_name));
        
        // Try direct insert/update
        if ($exists) {
            $result_direct = $wpdb->update(
                $wpdb->options,
                ['option_value' => $option_value],
                ['option_name' => $option_name],
                ['%s'],
                ['%s']
            );
        } else {
            $result_direct = $wpdb->insert(
                $wpdb->options,
                [
                    'option_name' => $option_name,
                    'option_value' => $option_value,
                    'autoload' => 'yes'
                ],
                ['%s', '%s', '%s']
            );
        }
     
        
        // Also try update_option
        $result = update_option('dpuwoo_settings', $sanitized_data);
    
        if ($result !== false) { // update_option returns false on failure
            
            // Verify the save actually worked
            $verify_settings = get_option('dpuwoo_settings', []);
            
            wp_send_json_success([
                'message' => 'Configuración guardada correctamente',
                'data' => $sanitized_data,
                'timestamp' => current_time('mysql')
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Error al guardar la configuración - Verifique permisos y datos'
            ]);
        }
    }
    
    /**
     * AJAX Handler: Limpiar cache de API
     */
    public static function ajax_clear_api_cache()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dpuwoo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Acceso no autorizado']);
        }
        
        // Como no hay implementación de cache, devolvemos éxito
        wp_send_json_success([
            'message' => 'Función de cache no implementada',
            'success' => true
        ]);
    }
    
    /**
     * AJAX Handler: Get current product price
     */
    public function ajax_get_product_current_price() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permissions']);
        }
        
        if (!check_ajax_referer('dpuwoo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID']);
        }
        
        if (!function_exists('wc_get_product')) {
            wp_send_json_error(['message' => 'WooCommerce not available']);
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product not found']);
        }
        
        $current_price = floatval($product->get_regular_price());
        
        wp_send_json_success([
            'price' => $current_price,
            'formatted_price' => wc_price($current_price),
            'product_id' => $product_id,
            'product_title' => $product->get_title()
        ]);
    }
    
    /**
     * AJAX Handler: Get current exchange rate
     */
    public function ajax_get_current_exchange_rate() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permissions']);
        }
        
        if (!check_ajax_referer('dpuwoo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        try {
            if (class_exists('API_Client')) {
                $api_client = API_Client::get_instance();
                $settings = get_option('dpuwoo_settings', []);
                $dollar_type = $settings['dollar_type'] ?? 'oficial';
                
                $rate_data = $api_client->get_rate($dollar_type);
                
                if ($rate_data && isset($rate_data['value'])) {
                    wp_send_json_success([
                        'rate' => floatval($rate_data['value']),
                        'formatted_rate' => '$' . number_format(floatval($rate_data['value']), 2),
                        'type' => $dollar_type,
                        'provider' => $rate_data['provider'] ?? 'unknown'
                    ]);
                } else {
                    wp_send_json_error(['message' => 'Could not get exchange rate from API']);
                }
            } else {
                wp_send_json_error(['message' => 'API Client not available']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error getting exchange rate: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX Handler: Initialize baseline using current API exchange rate
     */
    public function ajax_initialize_baseline_api() {
        // Esta funcionalidad ha sido simplificada
        // Los baselines se establecen automáticamente durante la activación
        wp_send_json_error([
            'message' => 'Esta funcionalidad ha sido simplificada. Los baselines se establecen automáticamente durante la activación del plugin.'
        ]);
    }
    
}
<?php
/**
 * Formateador de respuestas estandarizadas para todos los proveedores de API
 * Implementa el patrón Factory para crear objetos de respuesta consistentes
 */

class API_Response_Formatter {
    
    /**
     * Crear respuesta estandarizada para tasas de cambio individuales
     */
    public static function create_rate_response($data) {
        return [
            'value' => self::get_numeric_value($data, 'value', 0),
            'buy' => self::get_numeric_value($data, 'buy', 0),
            'sell' => self::get_numeric_value($data, 'sell', 0),
            'mid' => self::get_numeric_value($data, 'mid', 0),
            'updated' => self::get_updated_timestamp($data),
            'raw' => $data['raw'] ?? $data,
            'provider' => $data['provider'] ?? 'unknown',
            'base_currency' => strtoupper($data['base_currency'] ?? ''),
            'target_currency' => strtoupper($data['target_currency'] ?? ''),
            'pair' => $data['pair'] ?? '',
            'type' => $data['type'] ?? 'spot',
            'timestamp' => current_time('mysql'),
            'valid' => isset($data['value']) && $data['value'] > 0
        ];
    }
    
    /**
     * Crear respuesta estandarizada para listas de monedas
     */
    public static function create_currency_response($data) {
        return [
            'code' => strtoupper($data['code'] ?? ''),
            'name' => $data['name'] ?? '',
            'type' => $data['type'] ?? 'currency',
            'key' => $data['key'] ?? '',
            'value' => self::get_numeric_value($data, 'value', 0),
            'buy' => self::get_numeric_value($data, 'buy', 0),
            'sell' => self::get_numeric_value($data, 'sell', 0),
            'mid' => self::get_numeric_value($data, 'mid', 0),
            'updated' => self::get_updated_timestamp($data),
            'raw' => $data['raw'] ?? $data,
            'provider' => $data['provider'] ?? 'unknown',
            'base_currency' => strtoupper($data['base_currency'] ?? ''),
            'target_currency' => strtoupper($data['target_currency'] ?? ''),
            'pair' => $data['pair'] ?? '',
            'category' => $data['category'] ?? 'forex',
            'timestamp' => current_time('mysql'),
            'valid' => isset($data['value']) && $data['value'] > 0
        ];
    }
    
    /**
     * Crear respuesta estandarizada para conexión/test
     */
    public static function create_test_response($data) {
        return [
            'success' => (bool) ($data['success'] ?? false),
            'http_code' => (int) ($data['http_code'] ?? 0),
            'url' => $data['url'] ?? '',
            'message' => $data['message'] ?? '',
            'error' => $data['error'] ?? '',
            'response_time' => $data['response_time'] ?? 0,
            'timestamp' => current_time('mysql'),
            'provider' => $data['provider'] ?? 'unknown'
        ];
    }
    
    /**
     * Helper para obtener valores numéricos con fallback
     */
    private static function get_numeric_value($data, $key, $default = 0) {
        if (!isset($data[$key])) {
            return $default;
        }
        
        $value = $data[$key];
        
        // Si ya es número, devolverlo
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        // Si es string, intentar parsearlo
        if (is_string($value)) {
            // Manejar formatos como "1.234,56" o "1234.56"
            $cleaned = str_replace(['.', ','], ['', '.'], $value);
            return is_numeric($cleaned) ? floatval($cleaned) : $default;
        }
        
        return $default;
    }
    
    /**
     * Helper para obtener timestamp de actualización
     */
    private static function get_updated_timestamp($data) {
        $updated = $data['updated'] ?? $data['timestamp'] ?? $data['time_last_update_utc'] ?? '';
        
        if (empty($updated)) {
            return current_time('mysql');
        }
        
        // Si es timestamp ISO, convertirlo
        if (strtotime($updated)) {
            return date('Y-m-d H:i:s', strtotime($updated));
        }
        
        return $updated;
    }
    
    /**
     * Validar estructura de respuesta de tasa
     */
    public static function validate_rate_response($response) {
        $required_fields = ['value', 'provider', 'base_currency', 'target_currency'];
        foreach ($required_fields as $field) {
            if (!isset($response[$field])) {
                return false;
            }
        }
        return $response['value'] > 0;
    }
    
    /**
     * Validar estructura de respuesta de moneda
     */
    public static function validate_currency_response($response) {
        $required_fields = ['code', 'name', 'value', 'provider'];
        foreach ($required_fields as $field) {
            if (!isset($response[$field])) {
                return false;
            }
        }
        return $response['value'] > 0;
    }
}
<?php
if (!defined('ABSPATH')) exit;

// Cliente principal
class API_Client
{
    protected static $instance;
    protected $providers = [];
    
    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_instance()
    {
        return self::init();
    }
    
    private function __construct()
    {
        // Inicializar proveedores
        $this->providers = [
            'currencyapi' => new CurrencyAPI_Provider(),
            'dolarapi' => new DolarAPI_Provider(),
            'exchangerate-api' => new ExchangeRateAPI_Provider()
        ];
    }
    
    /**
     * Obtener proveedor específico
     */
    public function get_provider($provider_key = null)
    {
        // Si no se especifica proveedor, usar el de la configuración
        if (empty($provider_key)) {
            $opts = get_option('dpuwoo_settings', []);
            $provider_key = $opts['api_provider'] ?? 'dolarapi';
        }
        
        // Validar que el proveedor exista
        if (!isset($this->providers[$provider_key])) {
            
            $provider_key = 'dolarapi';
        }
        
        return $this->providers[$provider_key];
    }
    
    /**
     * Obtener todos los proveedores disponibles
     */
    public static function get_available_providers()
    {
        return [
            'currencyapi' => [
                'name' => 'CurrencyAPI.com',
                'description' => 'API con soporte para 170+ monedas globales (requiere API Key)',
                'url' => 'https://currencyapi.com/',
                'requires_key' => true,
                'types' => ['latest', 'historical'],
                'supports_currencies' => true,
                'currency_endpoint' => 'currencies'
            ],
            'dolarapi' => [
                'name' => 'DolarAPI.com',
                'description' => 'API pública de cotizaciones del dólar en Argentina',
                'domain' => 'dolarapi.com',
                'url' => 'https://dolarapi.com/v1',
                'requires_key' => false,
                'types' => ['oficial', 'blue', 'bolsa', 'contadoconliqui', 'tarjeta', 'mayorista', 'cripto', 'mep', 'solidario'],
                'supports_currencies' => false,
                'currency_endpoint' => null
            ],
            'exchangerate-api' => [
                'name' => 'ExchangeRate-API.com',
                'description' => 'API con soporte para 165+ monedas globales',
                'url' => 'https://www.exchangerate-api.com/',
                'requires_key' => true,
                'types' => ['latest', 'convert'],
                'supports_currencies' => true,
                'currency_endpoint' => 'codes'
            ]
        ];
    }
    
    /**
     * Obtener tasa según proveedor (formato estandarizado)
     */
    public function get_rate($type = 'oficial', $provider_key = null)
    {
        $provider = $this->get_provider($provider_key);
        $result = $provider->get_rate($type);
        
        // Agregar información común si existe
        if ($result) {
            $result['store_currency'] = strtoupper(get_woocommerce_currency());
            $result['store_country'] = $this->get_store_country();
            $result['timestamp'] = current_time('mysql');
        }
        
        return $result;
    }
    
    /**
     * Obtener monedas según proveedor (formato estandarizado)
     */
    public function get_currencies($provider_key = null)
    {
        $provider = $this->get_provider($provider_key);
        $currencies = $provider->get_currencies();
        
        // Agregar información común a cada moneda
        if (is_array($currencies)) {
            foreach ($currencies as &$currency) {
                $currency['store_currency'] = strtoupper(get_woocommerce_currency());
                $currency['store_country'] = $this->get_store_country();
                $currency['timestamp'] = current_time('mysql');
            }
        }
        
        return $currencies;
    }
    
    /**
     * Probar conexión de proveedor específico
     */
    public function test_connection($provider_key = null)
    {
        $provider = $this->get_provider($provider_key);
        $result = $provider->test_connection();
        
        // Agregar información común
        if ($result) {
            $result['store_currency'] = strtoupper(get_woocommerce_currency());
            $result['store_country'] = $this->get_store_country();
        }
        
        return $result;
    }
    
    /**
     * Obtener código de país de la tienda
     */
    private function get_store_country()
    {
        $base_country = get_option('woocommerce_default_country', 'AR:AR');
        $country_parts = explode(':', $base_country);
        return strtolower($country_parts[0]);
    }
}
<?php
if (!defined('ABSPATH')) exit;

class Cron
{
    const HOOK = 'dpuwoo_do_update';

    public static function schedule()
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'hourly', self::HOOK);
        }
    }

    public static function unschedule()
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) wp_unschedule_event($timestamp, self::HOOK);
    }

    public static function run_cron()
    {
        // Sistema de actualización basado en tasas de cambio directas

        $type = get_option('dpuwoo_settings', [])['dollar_type'] ?? 'oficial';
        $api = API_Client::get_instance();
        $rate = $api->get_rate($type);
        
        if ($rate === false) {
            $rate = Fallback::get_instance()->get_fallback_rate();
        }
        
        if ($rate === false) {
            return;
        }

        $current_rate = floatval($rate['value']);
        $last_rate = floatval(get_option('dpuwoo_settings', [])['last_rate'] ?? 0);
        $threshold = floatval(get_option('dpuwoo_settings', [])['threshold'] ?? 0);

        // Comparar con el último rate aplicado (no usar baseline general)
        $reference_rate = $last_rate > 0 ? $last_rate : $current_rate;
        
        // Calcular variación respecto a la referencia
        $changed = ($reference_rate > 0) ? abs(($current_rate - $reference_rate) / $reference_rate) * 100 : 100;

        if ($threshold > 0 && $changed < $threshold) {
            return;
        }

        // Actualizar precios
        $updater = Price_Updater::get_instance();
        $result = $updater->update_all_batch(false);
        
        if (isset($result['error'])) {
            return;
        }

        // Guardar el rate actual para la próxima comparación
        $opts = get_option('dpuwoo_settings', []);
        $opts['last_rate'] = $current_rate;
        update_option('dpuwoo_settings', $opts);
    }
}
<?php
// Implementación para CurrencyAPI
class CurrencyAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://api.currencyapi.com/v3';
    protected $auth_header = 'apikey';
    
    public function get_rate($currency_pair) {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for CurrencyAPI');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        
        // Determinar la moneda objetivo
        if ($currency_pair === 'latest') {
            // Obtener todas las tasas desde la moneda base
            $url = add_query_arg([
                'base_currency' => $store_currency,
            ], "{$this->base_url}/latest");
        } else {
            // Obtener par específico
            $currencies = explode('_', $currency_pair);
            if (count($currencies) !== 2) {
                $this->log_error('Invalid currency pair format: ' . $currency_pair);
                return false;
            }
            
            $target_currency = strtoupper($currencies[1]);
            $url = add_query_arg([
                'base_currency' => $store_currency,
                'currencies' => $target_currency,
            ], "{$this->base_url}/latest");
        }
        
        $response = $this->make_request($url, [
            'headers' => ['apikey' => $api_key],
        ]);
        
        if (!$response || !isset($response['data']['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        if ($currency_pair === 'latest') {
            return [
                'value' => $data['data'],
                'updated' => $data['meta']['last_updated_at'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'currencyapi',
                'base_currency' => $store_currency
            ];
        } else {
            $target_currency = strtoupper(explode('_', $currency_pair)[1]);
            if (!isset($data['data'][$target_currency])) {
                return false;
            }
            
            $rate_data = $data['data'][$target_currency];
            return [
                'value' => floatval($rate_data['value']),
                'buy' => floatval($rate_data['value']),
                'sell' => floatval($rate_data['value']),
                'updated' => $data['meta']['last_updated_at'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'currencyapi',
                'base_currency' => $store_currency,
                'target_currency' => $target_currency,
                'pair' => $store_currency . '_' . $target_currency
            ];
        }
    }
    
    public function get_currencies() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for CurrencyAPI');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        $woocommerce_currencies = get_woocommerce_currencies();
        
        $url = add_query_arg([
            'base_currency' => $store_currency,
        ], "{$this->base_url}/latest");
        
        $response = $this->make_request($url, [
            'headers' => ['apikey' => $api_key],
        ]);
        
        if (!$response || $response['code'] !== 200 || !isset($response['data']['data'])) {
            $this->log_error('CurrencyAPI Error: ' . print_r($response, true));
            return [];
        }
        
        $data = $response['data'];
        $updated = $data['meta']['last_updated_at'] ?? current_time('mysql');
        $currencies_list = [];
        
        foreach ($data['data'] as $currency_code => $rate_data) {
            if (!isset($rate_data['value']) || $currency_code === $store_currency) {
                continue;
            }
            
            $value = (float) $rate_data['value'];
            $currency_name = $woocommerce_currencies[$currency_code] ?? $currency_code;
            
            $currencies_list[] = [
                'code' => $store_currency . '_' . $currency_code,
                'name' => $currency_name,
                'type' => strtolower($currency_code),
                'value' => $value,
                'buy' => $value,
                'sell' => $value,
                'updated' => $updated,
                'provider' => 'currencyapi',
                'base_currency' => $store_currency,
                'target_currency' => $currency_code,
                'raw' => $rate_data
            ];
        }
        
        return $currencies_list;
    }
    
    public function test_connection() {
        $api_key = $this->get_api_key();
        $store_currency = $this->get_store_currency();
        
        if (empty($api_key)) {
            return ['success' => false, 'error' => 'API Key no configurada'];
        }
        
        $url = "{$this->base_url}/latest?base_currency={$store_currency}&currencies=USD";
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['apikey' => $api_key]
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        return [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'Conexión exitosa con CurrencyAPI' : "Error HTTP: {$code}"
        ];
    }
}
<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Dpuwoo
 * @subpackage Dpuwoo/includes
 * @author     Mauro Gaitan <maurogaitansouvaje@gmail.com>
 */
class Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		Cron::unschedule();
	}

}
<?php
class DolarAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://{country}dolarapi.com/v1';
    protected $auth_header = null;
    private $current_country = null;

    public function set_country($country) {
        $this->current_country = strtolower($country);
    }
    
    private function build_url($endpoint) {
        // Usar el país establecido dinámicamente, o el de la tienda por defecto
        $country = $this->current_country ?: $this->get_store_country();
        
        // Argentina no usa prefijo
        if ($country === 'ar') {
            $url = "https://dolarapi.com/v1" . $endpoint;
        } else {
            $url = str_replace('{country}', $country . '.', $this->base_url) . $endpoint;
        }
        
        return $url;
    }
    
    
    public function get_rate($type) {
        $store_currency = $this->get_store_currency();
        
        $url = $this->build_url("/dolares/" . rawurlencode($type));
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        // Formato estandarizado de respuesta
        return [
            'value' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'buy' => isset($data['compra']) ? $this->parse_numeric_value($data['compra']) : 0,
            'sell' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'updated' => $data['fechaActualizacion'] ?? current_time('mysql'),
            'raw' => $data,
            'provider' => 'dolarapi',
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'pair' => 'USD_ARS'
        ];
    }
    
    public function get_currencies() {
        $store_currency = $this->get_store_currency();
        
        // Para DolarAPI, solo funciona con USD como base
        if ($store_currency !== 'USD') {
            $this->log_error('DolarAPI solo funciona con USD como moneda base. Tienda usa: ' . $store_currency);
            return [];
        }
        
        // Obtener ambos endpoints
        $dolares_data = $this->get_dolares_raw();
        $cotizaciones_data = $this->get_cotizaciones_raw();
        
        // Consolidar todo en un solo array
        $all_currencies = [];
        
        // Procesar dólares
        if ($dolares_data && $dolares_data['http_code'] === 200 && is_array($dolares_data['data'])) {
            foreach ($dolares_data['data'] as $dolar) {
                if (!is_array($dolar) || !isset($dolar['casa'])) continue;
                
                $all_currencies[] = [
                    'type' => 'dolar',
                    'code' => $store_currency . '_' . strtoupper($dolar['casa']),
                    'key' => $dolar['casa'],
                    'name' => $dolar['nombre'] ?? ucfirst($dolar['casa']),
                    'value' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'buy' => isset($dolar['compra']) ? $this->parse_numeric_value($dolar['compra']) : 0,
                    'sell' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'updated' => $dolar['fechaActualizacion'] ?? current_time('mysql'),
                    'raw' => $dolar,
                    'provider' => 'dolarapi',
                    'base_currency' => $store_currency,
                    'target_currency' => 'ARS'
                ];
            }
        }
        
        // Procesar cotizaciones
        if ($cotizaciones_data && $cotizaciones_data['http_code'] === 200 && is_array($cotizaciones_data['data'])) {
            foreach ($cotizaciones_data['data'] as $moneda) {
                if (!is_array($moneda) || !isset($moneda['moneda'])) continue;
                
                // Filtrar solo monedas (no dólares)
                $moneda_code = strtoupper($moneda['moneda']);
                $moneda_nombre = strtolower($moneda['nombre'] ?? '');
                
                // Excluir dólares
                if ($moneda_code === 'USD' || 
                    strpos($moneda_nombre, 'dólar') !== false || 
                    strpos($moneda_nombre, 'dolar') !== false) {
                    continue;
                }
                
                $all_currencies[] = [
                    'type' => 'moneda',
                    'code' => $store_currency . '_' . $moneda_code,
                    'key' => strtolower($moneda_code),
                    'name' => $moneda['nombre'] ?? $moneda_code,
                    'value' => isset($moneda['venta']) ? $this->parse_numeric_value($moneda['venta']) : 0,
                    'buy' => isset($moneda['compra']) ? $this->parse_numeric_value($moneda['compra']) : 0,
                    'sell' => isset($moneda['venta']) ? $this->parse_numeric_value($moneda['venta']) : 0,
                    'updated' => $moneda['fechaActualizacion'] ?? current_time('mysql'),
                    'raw' => $moneda,
                    'provider' => 'dolarapi',
                    'base_currency' => $store_currency,
                    'target_currency' => $moneda_code
                ];
            }
        }
        
        return $all_currencies;
    }
    
    private function get_dolares_raw() {
        $url = $this->build_url("/dolares");
        $response = $this->make_request($url);
        
        if (!$response || $response['code'] !== 200) {
            return false;
        }
        
        return [
            'data' => $response['data'],
            'raw' => $response['raw'],
            'http_code' => $response['code'],
            'url' => $url
        ];
    }
    
    private function get_cotizaciones_raw() {
        $url = $this->build_url("/cotizaciones");
        $response = $this->make_request($url);
        
        if (!$response || $response['code'] !== 200) {
            return false;
        }
        
        return [
            'data' => $response['data'],
            'raw' => $response['raw'],
            'http_code' => $response['code'],
            'url' => $url
        ];
    }
    
    public function test_connection() {
        $country = $this->get_store_country();
        
        // Argentina no usa prefijo
        if ($country === 'ar') {
            $url = "https://dolarapi.com/v1/dolares";
        } else {
            $url = "https://{$country}.dolarapi.com/v1/dolares";
        }
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => false,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'url' => $url
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        return [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'OK' : "Error: {$code}"
        ];
    }
}
<?php
// Incluir el formateador de respuestas
require_once 'class-dpuwoo-api-response-formatter.php';

class DolarAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://{country}dolarapi.com/v1';
    protected $auth_header = null;
    private $current_country = null;

    public function set_country($country) {
        $this->current_country = $country;
    }
    
    protected function build_url($endpoint) {
        // Usar el país establecido dinámicamente, o el de la tienda por defecto
        $country = $this->current_country ?: $this->get_store_country();
        
        // Convertir código de país al formato ISO 3166-1 alpha-3 SOLO para DolarAPI
        $iso_country = $this->map_to_iso_country($country);
        
        // Convertir código de país al formato que usa DolarAPI (2 letras)
        $country_2letter = $this->convert_country_code_to_2letter($iso_country);
        
        // Argentina no usa prefijo
        if ($country_2letter === 'ar') {
            $url = "https://dolarapi.com/v1" . $endpoint;
        } else {
            $url = "https://{$country_2letter}.dolarapi.com/v1" . $endpoint;
        }
        
        return $url;
    }
    
    /**
     * Mapear código de país a formato ISO 3166-1 alpha-3 (SOLO para URLs de DolarAPI)
     */
    private function map_to_iso_country($country_code) {
        $country_lower = strtolower($country_code);
        
        // Mapeo específico para DolarAPI (ISO 3166-1 alpha-3)
        $country_mapping = [
            'ar' => 'ARG',
            'cl' => 'CHL',
            'uy' => 'URY',
            'br' => 'BRA',
            'mx' => 'MEX',
            'co' => 'COL',
            'pe' => 'PER'
        ];
        
        return $country_mapping[$country_lower] ?? strtoupper($country_code);
    }
    
    /**
     * Convertir código de país de 3 letras a 2 letras para DolarAPI
     */
    private function convert_country_code_to_2letter($country_code) {
        $mapping = [
            'ARG' => 'ar',
            'CHL' => 'cl',
            'URY' => 'uy',
            'BRA' => 'br',
            'MEX' => 'mx',
            'COL' => 'co',
            'PER' => 'pe'
        ];
        
        $country_upper = strtoupper($country_code);
        return $mapping[$country_upper] ?? strtolower(substr($country_code, 0, 2));
    }
    
    
    public function get_rate($type) {
        $store_currency = $this->get_store_currency();
        
        $url = $this->build_url("/dolares/" . rawurlencode($type));
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        // Usar formateador estandarizado
        $dollar_type = strtoupper($type);
        $formatted_data = [
            'value' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'buy' => isset($data['compra']) ? $this->parse_numeric_value($data['compra']) : 0,
            'sell' => isset($data['venta']) ? $this->parse_numeric_value($data['venta']) : 0,
            'updated' => $data['fechaActualizacion'] ?? current_time('mysql'),
            'raw' => $data,
            'provider' => 'dolarapi',
            'base_currency' => $store_currency,
            'target_currency' => 'USD',
            'pair' => $store_currency . '_DOLAR_' . $dollar_type,
            'type' => $type,
            'api_code' => $type,
            'dollar_type' => $dollar_type
        ];
        
        return API_Response_Formatter::create_rate_response($formatted_data);
    }
    
    public function get_currencies() {
        $store_currency = $this->get_store_currency();
        
        // Obtener ambos endpoints
        $dolares_data = $this->get_dolares_raw();
        $cotizaciones_data = $this->get_cotizaciones_raw();
        
        // Consolidar todo en un solo array
        $all_currencies = [];
        
        // Procesar dólares
        if ($dolares_data && $dolares_data['http_code'] === 200 && is_array($dolares_data['data'])) {
            foreach ($dolares_data['data'] as $dolar) {
                if (!is_array($dolar) || !isset($dolar['casa'])) continue;
                
                // Usar formateador estandarizado para dólares
                // Código: TIENDA_DOLAR_TIPO (ej: CLP_DOLAR_OFICIAL)
                $dollar_type = strtoupper($dolar['casa']);
                $formatted_data = [
                    'type' => 'dolar',
                    'code' => $store_currency . '_DOLAR_' . $dollar_type,
                    'key' => $dolar['casa'],
                    'name' => $dolar['nombre'] ?? ucfirst($dolar['casa']),
                    'value' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'buy' => isset($dolar['compra']) ? $this->parse_numeric_value($dolar['compra']) : 0,
                    'sell' => isset($dolar['venta']) ? $this->parse_numeric_value($dolar['venta']) : 0,
                    'updated' => $dolar['fechaActualizacion'] ?? current_time('mysql'),
                    'raw' => $dolar,
                    'provider' => 'dolarapi',
                    'base_currency' => $store_currency,
                    'target_currency' => 'USD',
                    'category' => 'dollar_types',
                    'api_code' => $dolar['casa'], // Código que devuelve la API
                    'dollar_type' => $dollar_type  // Tipo de dólar específico
                ];
                
                $all_currencies[] = API_Response_Formatter::create_currency_response($formatted_data);
            }
        }
        
        // Procesar cotizaciones
        if ($cotizaciones_data && $cotizaciones_data['http_code'] === 200 && is_array($cotizaciones_data['data'])) {
            foreach ($cotizaciones_data['data'] as $moneda) {
                if (!is_array($moneda) || !isset($moneda['moneda'])) continue;
                
                // Filtrar solo monedas (no dólares)
                $moneda_code = strtoupper($moneda['moneda']);
                $moneda_nombre = strtolower($moneda['nombre'] ?? '');
                
                // Excluir dólares
                if ($moneda_code === 'USD' || 
                    strpos($moneda_nombre, 'dólar') !== false || 
                    strpos($moneda_nombre, 'dolar') !== false) {
                    continue;
                }
                
                // Usar formateador estandarizado para otras monedas
                $formatted_data = [
                    'type' => 'moneda',
                    'code' => $store_currency . '_' . $moneda_code,
                    'key' => strtolower($moneda_code),
                    'name' => $moneda['nombre'] ?? $moneda_code,
                    'value' => isset($moneda['venta']) ? $this->parse_numeric_value($moneda['venta']) : 0,
                    'buy' => isset($moneda['compra']) ? $this->parse_numeric_value($moneda['compra']) : 0,
                    'sell' => isset($moneda['venta']) ? $this->parse_numeric_value($moneda['venta']) : 0,
                    'updated' => $moneda['fechaActualizacion'] ?? current_time('mysql'),
                    'raw' => $moneda,
                    'provider' => 'dolarapi',
                    'base_currency' => $store_currency,
                    'target_currency' => $moneda_code,
                    'category' => 'foreign_currencies'
                ];
                
                $all_currencies[] = API_Response_Formatter::create_currency_response($formatted_data);
            }
        }
        
        return $all_currencies;
    }
    
    private function get_dolares_raw() {
        $url = $this->build_url("/dolares");
        $response = $this->make_request($url);
        
        if (!$response || $response['code'] !== 200) {
            return false;
        }
        
        return [
            'data' => $response['data'],
            'raw' => $response['raw'],
            'http_code' => $response['code'],
            'url' => $url
        ];
    }
    
    private function get_cotizaciones_raw() {
        $url = $this->build_url("/cotizaciones");
        $response = $this->make_request($url);
        
        if (!$response || $response['code'] !== 200) {
            return false;
        }
        
        return [
            'data' => $response['data'],
            'raw' => $response['raw'],
            'http_code' => $response['code'],
            'url' => $url
        ];
    }
    
    public function test_connection() {
        // Usar build_url para mantener consistencia
        $url = $this->build_url("/dolares");
        
        $start_time = microtime(true);
        
        // Usar curl en lugar de wp_remote_get para mejor compatibilidad
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: DPUWoo-DolarAPI-Test/1.0'
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $response_time = round((microtime(true) - $start_time) * 1000, 2); // ms
        
        $test_data = [
            'success' => !$error && $code === 200,
            'http_code' => $code,
            'url' => $url,
            'message' => !$error && $code === 200 ? 'OK' : ($error ?: "Error: {$code}"),
            'error' => $error,
            'response_time' => $response_time,
            'provider' => 'dolarapi'
        ];
        
        return API_Response_Formatter::create_test_response($test_data);
    }
}
<?php
// Incluir el formateador de respuestas
require_once 'class-dpuwoo-api-response-formatter.php';

// Implementación para ExchangeRate-API
class ExchangeRateAPI_Provider extends Base_API_Provider {
    protected $base_url = 'https://v6.exchangerate-api.com/v6';
    protected $auth_header = null;
    
    public function get_rate($currency_pair) {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for ExchangeRate-API');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        
        if ($currency_pair === 'latest') {
            $url = "{$this->base_url}/{$api_key}/latest/{$store_currency}";
        } else {
            $currencies = explode('_', $currency_pair);
            if (count($currencies) !== 2) {
                $this->log_error('Invalid currency pair format: ' . $currency_pair);
                return false;
            }
            
            $base_currency = strtoupper($currencies[0]);
            $target_currency = strtoupper($currencies[1]);
            $url = "{$this->base_url}/{$api_key}/pair/{$base_currency}/{$target_currency}";
        }
        
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data'])) {
            return false;
        }
        
        $data = $response['data'];
        
        if ($currency_pair === 'latest') {
            if (!isset($data['conversion_rates'])) {
                return false;
            }
            
            // Usar formateador estandarizado
            $formatted_data = [
                'value' => $data['conversion_rates'],
                'updated' => $data['time_last_update_utc'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'exchangerate-api',
                'base_currency' => $store_currency,
                'type' => 'latest'
            ];
            
            return API_Response_Formatter::create_rate_response($formatted_data);
        } else {
            if (!isset($data['conversion_rate'])) {
                return false;
            }
            
            // Usar formateador estandarizado
            $formatted_data = [
                'value' => floatval($data['conversion_rate']),
                'buy' => floatval($data['conversion_rate']),
                'sell' => floatval($data['conversion_rate']),
                'updated' => $data['time_last_update_utc'] ?? current_time('mysql'),
                'raw' => $data,
                'provider' => 'exchangerate-api',
                'base_currency' => $store_currency,
                'target_currency' => $target_currency,
                'pair' => $store_currency . '_' . $target_currency,
                'type' => 'spot'
            ];
            
            return API_Response_Formatter::create_rate_response($formatted_data);
        }
    }
    
    public function get_currencies() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->log_error('API Key required for ExchangeRate-API currencies');
            return false;
        }
        
        $store_currency = $this->get_store_currency();
        $woocommerce_currencies = get_woocommerce_currencies();
        
        $url = "{$this->base_url}/{$api_key}/latest/{$store_currency}";
        $response = $this->make_request($url);
        
        if (!$response || !isset($response['data']['conversion_rates'])) {
            $this->log_error('Failed to get currency rates from ExchangeRate-API');
            return false;
        }
        
        $data = $response['data'];
        $rates = $data['conversion_rates'];
        $updated = $data['time_last_update_utc'] ?? current_time('mysql');
        $currencies_list = [];
        
        foreach ($rates as $currency_code => $rate_value) {
            // Saltar moneda base
            if ($currency_code === $store_currency) {
                continue;
            }
            
            $exchange_rate = (float) $rate_value;
            $currency_name = $woocommerce_currencies[$currency_code] ?? $currency_code;
            
            // Usar formateador estandarizado
            $formatted_data = [
                'code' => $currency_code,
                'name' => $currency_name,
                'type' => strtolower($currency_code),
                'value' => $exchange_rate,
                'buy' => $exchange_rate,
                'sell' => $exchange_rate,
                'updated' => $updated,
                'provider' => 'exchangerate-api',
                'base_currency' => $store_currency,
                'target_currency' => $currency_code,
                'raw' => ['rate' => $exchange_rate],
                'category' => 'global_currencies'
            ];
            
            $currencies_list[] = API_Response_Formatter::create_currency_response($formatted_data);
        }
        
        return $currencies_list;
    }
    
    public function test_connection() {
        $api_key = $this->get_api_key();
        $store_currency = $this->get_store_currency();
        
        if (empty($api_key)) {
            $test_data = [
                'success' => false, 
                'error' => 'API Key no configurada',
                'provider' => 'exchangerate-api'
            ];
            return API_Response_Formatter::create_test_response($test_data);
        }
        
        $url = "{$this->base_url}/{$api_key}/pair/{$store_currency}/USD";
        $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
        
        if (is_wp_error($response)) {
            $test_data = [
                'success' => false, 
                'error' => $response->get_error_message(),
                'provider' => 'exchangerate-api'
            ];
            return API_Response_Formatter::create_test_response($test_data);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $success = $code === 200;
        
        $test_data = [
            'success' => $success,
            'http_code' => $code,
            'url' => $url,
            'message' => $success ? 'Conexión exitosa con ExchangeRate-API' : "Error HTTP: {$code}",
            'provider' => 'exchangerate-api'
        ];
        
        return API_Response_Formatter::create_test_response($test_data);
    }
}
<?php

if (!defined('ABSPATH')) exit;


class Fallback
{
    protected static $instance;


    public static function init()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }


    public static function get_instance()
    {
        return self::init();
    }


    public function get_fallback_rate()
    {
        $opts = get_option('dpuwoo_settings', []);
        if (!empty($opts['last_rate'])) return floatval($opts['last_rate']);
        return false;
    }
}
<?php
/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Dpuwoo
 * @subpackage Dpuwoo/includes
 * @author     Mauro Gaitan <maurogaitansouvaje@gmail.com>
 */
class I18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'dpuwoo',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
<?php

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Dpuwoo
 * @subpackage Dpuwoo/includes
 * @author     Mauro Gaitan <maurogaitansouvaje@gmail.com>
 */
class Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
	 */
	protected $filters;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->actions = array();
		$this->filters = array();

	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress action that is being registered.
	 * @param    object               $component        A reference to the instance of the object on which the action is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress filter that is being registered.
	 * @param    object               $component        A reference to the instance of the object on which the filter is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks into a single
	 * collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array                $hooks            The collection of hooks that is being registered (that is, actions or filters).
	 * @param    string               $hook             The name of the WordPress filter that is being registered.
	 * @param    object               $component        A reference to the instance of the object on which the filter is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         The priority at which the function should be fired.
	 * @param    int                  $accepted_args    The number of arguments that should be passed to the $callback.
	 * @return   array                                  The collection of actions and filters registered with WordPress.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {

		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		);

		return $hooks;

	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

	}

}
<?php
if (!defined('ABSPATH')) exit;

/**
 * DPUWoo_Repository
 * Encapsula todo el acceso a base de datos para runs y run_items.
 */
class Log_Repository
{
    protected static $instance;
    protected $wpdb;
    protected $table_runs;
    protected $table_items;

    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_runs  = $wpdb->prefix . 'dpuwoo_runs';
        $this->table_items = $wpdb->prefix . 'dpuwoo_run_items';
    }

    /* ---------------------------
     * Runs
     * --------------------------- */
    public function insert_run(array $data)
    {
        $now = current_time('mysql');

        $insert_data = [
            'date'              => $now,
            'dollar_type'       => $data['dollar_type'] ?? '',
            'dollar_value'      => floatval($data['dollar_value'] ?? 0),
            'rules'             => maybe_serialize($data['rules'] ?? []),
            'total_products'    => intval($data['total_products'] ?? 0),
            'user_id'           => intval($data['user_id'] ?? 0),
            'note'              => $data['note'] ?? '',
            'percentage_change' => isset($data['percentage_change']) ? floatval($data['percentage_change']) : null,
        ];

        $this->wpdb->insert($this->table_runs, $insert_data);

        if ($this->wpdb->last_error) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    public function update_run($run_id, array $data)
    {
        return $this->wpdb->update($this->table_runs, $data, ['id' => intval($run_id)]);
    }

    public function get_run($run_id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_runs} WHERE id = %d", intval($run_id)));
    }

    public function get_runs($limit = 100)
    {
        return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_runs} ORDER BY date DESC LIMIT %d", intval($limit)));
    }

    /* ---------------------------
     * Items
     * --------------------------- */
    public function insert_run_item($run_id, $item)
    {
        // Solo insertar updated o error
        if (!in_array($item['status'], ['updated', 'error'])) {
            return false;
        }

        $insert = [
            'run_id'            => intval($run_id),
            'product_id'        => intval($item['product_id']),
            'old_regular_price' => isset($item['old_regular_price']) ? $item['old_regular_price'] : null,
            'new_regular_price' => isset($item['new_regular_price']) ? $item['new_regular_price'] : null,
            'old_sale_price'    => isset($item['old_sale_price']) ? $item['old_sale_price'] : null,
            'new_sale_price'    => isset($item['new_sale_price']) ? $item['new_sale_price'] : null,
            'status'            => $item['status'],
            'reason'            => $item['reason'] ?? null,
        ];

        $this->wpdb->insert($this->table_items, $insert);
        if ($this->wpdb->last_error) {
            return false;
        }
        return $this->wpdb->insert_id;
    }

    public function insert_items_bulk($run_id, $items)
    {
        // El Logger asegura que $items solo contiene updated/error
        foreach ($items as $item) {
            if (!$this->insert_run_item($run_id, $item)) {
                return false;
            }
        }
        return true;
    }

    public function get_run_items($run_id, $limit = 500)
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_items} WHERE run_id = %d ORDER BY id ASC LIMIT %d",
            intval($run_id),
            intval($limit)
        ));

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->enrich_item($row);
        }
        return $result;
    }

    private function enrich_item($row)
    {
        $product = wc_get_product($row->product_id);

        if (!$product) {
            $row->product_name  = 'Producto eliminado';
            $row->product_sku   = 'N/A';
            $row->product_type  = 'unknown';
            $row->current_regular = null;
            $row->current_sale    = null;
            return $row;
        }

        $row->product_name      = $product->get_name();
        $row->product_sku       = $product->get_sku();
        $row->product_type      = $product->get_type();
        $row->current_regular = $product->get_regular_price();
        $row->current_sale    = $product->get_sale_price();

        return $row;
    }

    public function get_item($item_id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_items} WHERE id = %d", intval($item_id)));
    }

    public function update_item_status($item_id, $status)
    {
        return $this->wpdb->update($this->table_items, ['status' => $status], ['id' => intval($item_id)]);
    }

    public function get_items_for_run($run_id)
    {
        return $this->wpdb->get_results($this->wpdb->prepare("SELECT id FROM {$this->table_items} WHERE run_id = %d", intval($run_id)));
    }

    public function rollback_item($item_id)
    {
        $row = $this->get_item($item_id);
        if (!$row) return new WP_Error('not_found', 'Item not found');

        $product = wc_get_product($row->product_id);
        if (!$product) return new WP_Error('no_product', 'Product not found');

        if (!is_null($row->old_regular_price)) {
            $product->set_regular_price($row->old_regular_price);
        }
        if (!is_null($row->old_sale_price)) {
            $product->set_sale_price($row->old_sale_price);
        }
        $product->save();

        $this->update_item_status($item_id, 'reverted');

        return true;
    }

    public function rollback_run($run_id)
    {
        $items = $this->get_items_for_run($run_id);
        foreach ($items as $it) {
            $this->rollback_item($it->id);
        }
        return true;
    }

    /* ---------------------------
     * Helpers: count & fetch product ids por paginado
     * --------------------------- */
    public function get_product_ids_batch($limit = 500, $offset = 0)
    {
        return $this->wpdb->get_col($this->wpdb->prepare("
            SELECT ID FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
            ORDER BY ID ASC
            LIMIT %d OFFSET %d
        ", intval($limit), intval($offset)));
    }

    public function count_all_products()
    {
        return (int) $this->wpdb->get_var("
            SELECT COUNT(*) FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
        ");
    }
}
<?php
if (!defined('ABSPATH')) exit;

/**
 * Logger (thin wrapper) - ahora delega todo en DPUWoo_Repository
 */
class Logger
{
    protected static $instance;
    protected $repo;

    public static function init()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    private function __construct()
    {
        $this->repo = Log_Repository::get_instance();
    }

    /**
     * Begin run (solo inserta la run, sin transacción)
     */
    public function begin_run_transaction($run_data)
    {
        // Se elimina la llamada a $this->repo->begin_transaction()
        
        $run_id = $this->repo->insert_run($run_data);

        if (!$run_id) {
            return false;
        }

        return $run_id;
    }

    /**
     * Add items (no requiere cambios)
     */
    public function add_items_to_transaction($run_id, $items)
    {
        // 1. Filtrar solo los estados que queremos guardar (updated o error)
        $items_to_save = array_filter($items, function($item) {
            return in_array($item['status'], ['updated', 'error']);
        });

        // 2. Si la lista filtrada está vacía, retornar TRUE.
        if (empty($items_to_save)) {
            return true; 
        }

        // 3. Insertar solo el subconjunto de ítems filtrados
        $success = $this->repo->insert_items_bulk($run_id, $items_to_save);
        
        if (!$success) {
            return false;
        }
        return true;
    }

    /**
     * Commit (solo retorna el ID, sin hacer commit)
     */
    public function commit_run_transaction($run_id)
    {
        // Se elimina la llamada a $this->repo->commit()
        return $run_id;
    }

    /**
     * Rollback (solo retorna true, sin hacer rollback)
     */
    public function rollback_run_transaction()
    {
        // Se elimina la llamada a $this->repo->rollback()
        return true;
    }

    /* Compatibility methods (original names) */
    public function create_run($data)
    {
        return $this->repo->insert_run($data);
    }

    public function insert_run_item($run_id, $item)
    {
        return $this->repo->insert_run_item($run_id, $item);
    }

    public function rollback_item($item_id)
    {
        return $this->repo->rollback_item($item_id);
    }

    public function rollback_run($run_id)
    {
        return $this->repo->rollback_run($run_id);
    }

    public function get_run_items($run_id, $limit = 500)
    {
        return $this->repo->get_run_items($run_id, $limit);
    }

    public function get_runs($limit = 100)
    {
        return $this->repo->get_runs($limit);
    }

    public function get_run($run_id)
    {
        return $this->repo->get_run($run_id);
    }

    public function count_run_items($run_id)
    {
        $items = $this->repo->get_items_for_run($run_id);
        return is_array($items) ? count($items) : 0;
    }
}
<?php
if (!defined('ABSPATH')) exit;

/**
 * MultiCurrency_Manager
 * Gestiona el sistema multi-moneda que ignora cambios manuales 
 * y usa logs históricos como fuente de verdad
 */
class MultiCurrency_Manager
{
    protected static $instance;
    
    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_instance()
    {
        return self::init();
    }
    
    private function __construct()
    {
        // Constructor privado para singleton
    }
    
    /**
     * Establece el precio base multi-moneda para un producto
     * Solo se ejecuta una vez por producto
     * @param int $product_id ID del producto
     * @param float $current_price Precio actual del producto
     * @param float $current_rate Tasa de cambio actual
     */
    public function establish_baseline($product_id, $current_price, $current_rate)
    {
        // Solo ejecutar una vez por producto
        if (get_post_meta($product_id, '_dpuwoo_base_price_reference', true)) {
            return; // Ya existe baseline
        }
        
        $opts = get_option('dpuwoo_settings', []);
        $reference_currency = $opts['reference_currency'] ?? 'USD';
        
        // Calcular precio base en moneda de referencia
        $base_price_reference = $current_price / $current_rate;
        
        // Guardar metadata
        update_post_meta($product_id, '_dpuwoo_base_price_reference', $base_price_reference);
        update_post_meta($product_id, '_dpuwoo_reference_currency', $reference_currency);
        update_post_meta($product_id, '_dpuwoo_baseline_rate', $current_rate);
        update_post_meta($product_id, '_dpuwoo_baseline_date', current_time('mysql'));
        
        
    }
    
    /**
     * Calcula nuevo precio usando baseline multi-moneda
     * Ignora completamente el precio actual del producto
     * @param int $product_id ID del producto
     * @param float $new_rate Nueva tasa de cambio
     * @return float Nuevo precio calculado desde baseline
     */
    public function calculate_price_from_baseline($product_id, $new_rate)
    {
        // Obtener precio base en moneda de referencia
        $base_price = get_post_meta($product_id, '_dpuwoo_base_price_reference', true);
        $reference_currency = get_post_meta($product_id, '_dpuwoo_reference_currency', true);
        
        if ($base_price && $reference_currency) {
            // Aplicar nueva tasa para la moneda de referencia
            $new_price = $base_price * $new_rate;
            
            // Registrar en logs que se usó baseline
            $this->log_baseline_usage($product_id, $base_price, $reference_currency, $new_rate, $new_price);
            
            return $new_price;
        }
        
        // Fallback: usar precio actual si no hay baseline
        $product = wc_get_product($product_id);
        return floatval($product->get_regular_price());
    }
    
    /**
     * Verifica si un producto tiene baseline establecido
     * @param int $product_id ID del producto
     * @return bool
     */
    public function has_baseline($product_id)
    {
        return (bool) get_post_meta($product_id, '_dpuwoo_base_price_reference', true);
    }
    
    /**
     * Obtiene información del baseline de un producto
     * @param int $product_id ID del producto
     * @return array|null Información del baseline o null si no existe
     */
    public function get_baseline_info($product_id)
    {
        $base_price = get_post_meta($product_id, '_dpuwoo_base_price_reference', true);
        $currency = get_post_meta($product_id, '_dpuwoo_reference_currency', true);
        $baseline_rate = get_post_meta($product_id, '_dpuwoo_baseline_rate', true);
        $baseline_date = get_post_meta($product_id, '_dpuwoo_baseline_date', true);
        
        if (!$base_price || !$currency) {
            return null;
        }
        
        return [
            'base_price' => floatval($base_price),
            'currency' => $currency,
            'baseline_rate' => floatval($baseline_rate),
            'established_date' => $baseline_date
        ];
    }
    
    /**
     * Registra uso de baseline en logs del sistema
     */
    private function log_baseline_usage($product_id, $base_price, $currency, $rate_applied, $calculated_price)
    {
        
        
        // Registrar también en la tabla de logs del plugin
        $this->record_baseline_calculation($product_id, $base_price, $currency, $rate_applied, $calculated_price);
    }
    
    /**
     * Registra el cálculo de baseline en la base de datos
     */
    private function record_baseline_calculation($product_id, $base_price, $currency, $rate_applied, $calculated_price)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        
        // Crear tabla si no existe
        $this->ensure_baseline_table_exists();
        
        $wpdb->insert($table_name, [
            'product_id' => $product_id,
            'base_price' => $base_price,
            'currency' => $currency,
            'rate_applied' => $rate_applied,
            'calculated_price' => $calculated_price,
            'date_created' => current_time('mysql')
        ]);
    }
    
    /**
     * Asegura que la tabla de logs de baseline exista
     */
    private function ensure_baseline_table_exists()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            base_price decimal(10,4) NOT NULL,
            currency varchar(10) NOT NULL,
            rate_applied decimal(10,4) NOT NULL,
            calculated_price decimal(10,2) NOT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY date_created (date_created)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obtiene el historial de cálculos de baseline para un producto
     * @param int $product_id ID del producto
     * @param int $limit Límite de registros
     * @return array Historial de cálculos
     */
    public function get_baseline_history($product_id, $limit = 50)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        
        // Asegurar que la tabla exista
        $this->ensure_baseline_table_exists();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d ORDER BY date_created DESC LIMIT %d",
            $product_id,
            $limit
        ));
    }
    
    /**
     * Elimina todos los baselines (para reiniciar el sistema)
     * @return int Número de productos afectados
     */
    public function reset_all_baselines()
    {
        global $wpdb;
        
        // Eliminar metadata de productos
        $deleted_meta = $wpdb->query("
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key IN ('_dpuwoo_base_price_reference', '_dpuwoo_reference_currency', '_dpuwoo_baseline_rate', '_dpuwoo_baseline_date')
        ");
        
        // Eliminar tabla de logs
        $table_name = $wpdb->prefix . 'dpuwoo_baseline_logs';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        return $deleted_meta;
    }
}
<?php
if (!defined('ABSPATH')) exit;

/**
 * Price_Calculator - Calculadora de precios simplificada sin baseline
 */
class Price_Calculator
{
    private static $instance = null;
    private $rules = [];

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Constructor privado para singleton
    }

    /**
     * Calcula nuevo precio usando tasa de cambio directa (sin baseline)
     * @param int $product_id ID del producto
     * @param float $current_price Precio actual del producto
     * @param float $new_rate Nueva tasa de cambio
     * @return float Nuevo precio calculado
     */
    public function calculate_new_price($product_id, $current_price, $new_rate)
    {
        // Usar precio actual multiplicado por la nueva tasa
        $new_price = $current_price * $new_rate;
        
        return round($new_price, 2);
    }
    
    /**
     * Verifica si se puede calcular precio (producto tiene precio válido)
     * @param int $product_id ID del producto
     * @return bool
     */
    public function can_calculate_price($product_id)
    {
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            $price = floatval($product->get_regular_price());
            return $price > 0;
        }
        return false;
    }

    /**
     * Obtiene el precio base para cálculos
     * @param int $product_id ID del producto
     * @return float Precio base del producto
     */
    public function get_base_price($product_id)
    {
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            return floatval($product->get_regular_price());
        }
        return 0;
    }

    /**
     * Obtiene el precio base para cálculos (versión pública)
     * @param int $product_id ID del producto
     * @return float Precio base del producto
     */
    public function get_base_price_public($product_id)
    {
        return $this->get_base_price($product_id);
    }

    /**
     * Obtiene la tasa de cambio base para cálculos
     * @return float Tasa de cambio base
     */
    public function get_base_rate()
    {
        // Usar el último dólar aplicado de la tabla wp_dpuwoo_runs
        $last_applied_dollar = $this->get_last_applied_dollar();

        // Siempre usar el último dólar aplicado si existe
        if ($last_applied_dollar > 0) {
            return $last_applied_dollar;
        }

        // Si no hay registros anteriores, usar el rate actual como referencia inicial
        return 1.0; // Valor por defecto
    }

    /**
     * Obtiene el último dólar aplicado de la tabla wp_dpuwoo_runs
     * @return float El valor del último dólar aplicado, o 0 si no hay ejecuciones
     */
    private function get_last_applied_dollar()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'dpuwoo_runs';

        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

        if (!$table_exists) {
            return 0;
        }

        // Obtener el dollar_value de la última ejecución
        $query = $wpdb->prepare("SELECT dollar_value FROM {$table_name} ORDER BY id DESC LIMIT 1");
        $last_dollar = $wpdb->get_var($query);

        return $last_dollar ? floatval($last_dollar) : 0;
    }

    protected function apply_configured_rounding($price, $opts)
    {
        $rounding_type = $opts['rounding_type'] ?? 'integer';
        $nearest_to = floatval($opts['nearest_to'] ?? 1);

        switch ($rounding_type) {
            case 'none':
                // Sin redondeo
                $this->rules[] = 'rounding_none';
                return $price;

            case 'integer':
                // Redondeo a enteros
                $rounded = round($price);
                $this->rules[] = 'rounding_integer';
                return $rounded;

            case 'ceil':
                // Redondeo hacia arriba
                $rounded = ceil($price);
                $this->rules[] = 'rounding_ceil';
                return $rounded;

            case 'floor':
                // Redondeo hacia abajo
                $rounded = floor($price);
                $this->rules[] = 'rounding_floor';
                return $rounded;

            case 'nearest':
                // Redondeo al más cercano (múltiplo)
                $rounded = round($price / $nearest_to) * $nearest_to;
                $this->rules[] = "rounding_nearest_{$nearest_to}";
                return $rounded;

            default:
                // Por defecto redondeo normal
                $this->rules[] = 'rounding_default';
                return round($price, 2);
        }
    }

    /**
     * Aplica margen de corrección configurado
     */
    protected function apply_global_extra($price, $opts)
    {
        $margin = floatval($opts['margin'] ?? 0);

        if ($margin != 0) {
            $adjusted_price = $price * (1 + ($margin / 100));
            $this->rules[] = "margin_{$margin}%";
            return $adjusted_price;
        }

        return $price;
    }

    /**
     * Aplica reglas de dirección de actualización
     */
    protected function apply_update_direction($new_price, $old_price, $opts)
    {
        $direction = $opts['update_direction'] ?? 'bidirectional';

        switch ($direction) {
            case 'up_only':
                // Solo permitir aumentos
                if ($new_price < $old_price) {
                    $this->rules[] = 'direction_up_only_blocked';
                    return $old_price; // Mantener precio original
                }
                $this->rules[] = 'direction_up_only_allowed';
                return $new_price;

            case 'down_only':
                // Solo permitir disminuciones
                if ($new_price > $old_price) {
                    $this->rules[] = 'direction_down_only_blocked';
                    return $old_price; // Mantener precio original
                }
                $this->rules[] = 'direction_down_only_allowed';
                return $new_price;

            default:
                // Bidireccional - permitir ambos
                $this->rules[] = 'direction_bidirectional';
                return $new_price;
        }
    }

    /**
     * Aplica reglas de categoría (exclusiones)
     */
    protected function apply_category_rules($price, $product, $product_id)
    {
        $opts = get_option('dpuwoo_settings', []);
        $excluded_categories = $opts['exclude_categories'] ?? [];

        // Verificar si el producto pertenece a categorías excluidas
        if (!empty($excluded_categories)) {
            // Usar método de WooCommerce para obtener categorías
            $product_categories = $product->get_category_ids();

            // Verificar que sea un array válido
            if (is_array($product_categories) && !empty($product_categories)) {
                // Si hay intersección entre categorías del producto y categorías excluidas
                if (array_intersect($product_categories, $excluded_categories)) {
                    $this->rules[] = 'category_excluded';
                    // Podríamos mantener el precio original o aplicar una regla especial
                    // Por ahora mantenemos el precio con una regla de exclusión
                }
            }
        }

        return $price;
    }

    /*==============================================================
    =           Helpers (incluyendo rules acumulativas)
    ==============================================================*/

    protected function error($code)
    {
        return ['error' => $code];
    }

    protected function apply_ratio($price, $ratio, $rule_key = 'ratio')
    {
        // Solo agregar a rules si es el cálculo principal (regular)
        if ($rule_key === 'ratio') {
            $this->rules[] = 'ratio_' . round($ratio, 4);
        }
        return $price * $ratio;
    }

    protected function apply_global_rounding($price, $opts, $rule_key = 'global_rounding')
    {
        return $this->apply_rounding(
            $price,
            $opts['rounding'] ?? 'none',
            $opts['round_multiple'] ?? 10,
            $rule_key
        );
    }

    protected function calculate_percentage_change($old, $new)
    {
        return $old > 0
            ? round((($new - $old) / $old) * 100, 2)
            : 0;
    }

    /*==============================================================
    =           Redondeo
    ==============================================================*/
    protected function apply_rounding($price, $method, $multiple = 10, $rule_key = 'rounding')
    {
        if ($rule_key === 'global_rounding' && $method !== 'none') {
            $this->rules[] = "global_round_{$method}_{$multiple}";
        }

        switch ($method) {
            case 'up':
                return ceil($price / $multiple) * $multiple;
            case 'down':
                return floor($price / $multiple) * $multiple;
            case 'multiple':
                return round($price / $multiple) * $multiple;
            default:
                return round($price, 2);
        }
    }
}
<?php
if (!defined('ABSPATH')) exit;

/**
 * Price_Calculator - Calculadora de precios simplificada sin baseline
 */
class Price_Calculator
{
    private static $instance = null;
    private $rules = [];

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Constructor privado para singleton
    }

    /**
     * Calcula nuevo precio usando tasa de cambio directa (sin baseline)
     * @param int $product_id ID del producto
     * @param float $current_price Precio actual del producto
     * @param float $new_rate Nueva tasa de cambio
     * @return float Nuevo precio calculado
     */
    public function calculate_new_price($product_id, $current_price, $new_rate)
    {
        // Usar precio actual multiplicado por la nueva tasa
        $new_price = $current_price * $new_rate;
        
        return round($new_price, 2);
    }
    
    /**
     * Verifica si se puede calcular precio (producto tiene precio válido)
     * @param int $product_id ID del producto
     * @return bool
     */
    public function can_calculate_price($product_id)
    {
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            $price = floatval($product->get_regular_price());
            return $price > 0;
        }
        return false;
    }

    /**
     * Obtiene el precio base para cálculos
     * @param int $product_id ID del producto
     * @return float Precio base del producto
     */
    public function get_base_price($product_id)
    {
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            return floatval($product->get_regular_price());
        }
        return 0;
    }

    /**
     * Obtiene el precio base para cálculos (versión pública)
     * @param int $product_id ID del producto
     * @return float Precio base del producto
     */
    public function get_base_price_public($product_id)
    {
        return $this->get_base_price($product_id);
    }

    /**
     * Obtiene la tasa de cambio base para cálculos
     * @return float Tasa de cambio base
     */
    public function get_base_rate()
    {
        // Usar el último dólar aplicado de la tabla wp_dpuwoo_runs
        $last_applied_dollar = $this->get_last_applied_dollar();

        // Siempre usar el último dólar aplicado si existe
        if ($last_applied_dollar > 0) {
            return $last_applied_dollar;
        }

        // Si no hay registros anteriores, usar el rate actual como referencia inicial
        return 1.0; // Valor por defecto
    }

    /**
     * Obtiene el último dólar aplicado de la tabla wp_dpuwoo_runs
     * @return float El valor del último dólar aplicado, o 0 si no hay ejecuciones
     */
    private function get_last_applied_dollar()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'dpuwoo_runs';

        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

        if (!$table_exists) {
            return 0;
        }

        // Obtener el dollar_value de la última ejecución
        $query = $wpdb->prepare("SELECT dollar_value FROM {$table_name} ORDER BY id DESC LIMIT 1");
        $last_dollar = $wpdb->get_var($query);

        return $last_dollar ? floatval($last_dollar) : 0;
    }

    protected function apply_configured_rounding($price, $opts)
    {
        $rounding_type = $opts['rounding_type'] ?? 'integer';
        $nearest_to = floatval($opts['nearest_to'] ?? 1);

        switch ($rounding_type) {
            case 'none':
                // Sin redondeo
                $this->rules[] = 'rounding_none';
                return $price;

            case 'integer':
                // Redondeo a enteros
                $rounded = round($price);
                $this->rules[] = 'rounding_integer';
                return $rounded;

            case 'ceil':
                // Redondeo hacia arriba
                $rounded = ceil($price);
                $this->rules[] = 'rounding_ceil';
                return $rounded;

            case 'floor':
                // Redondeo hacia abajo
                $rounded = floor($price);
                $this->rules[] = 'rounding_floor';
                return $rounded;

            case 'nearest':
                // Redondeo al más cercano (múltiplo)
                $rounded = round($price / $nearest_to) * $nearest_to;
                $this->rules[] = "rounding_nearest_{$nearest_to}";
                return $rounded;

            default:
                // Por defecto redondeo normal
                $this->rules[] = 'rounding_default';
                return round($price, 2);
        }
    }

    /**
     * Aplica margen de corrección configurado
     */
    protected function apply_global_extra($price, $opts)
    {
        $margin = floatval($opts['margin'] ?? 0);

        if ($margin != 0) {
            $adjusted_price = $price * (1 + ($margin / 100));
            $this->rules[] = "margin_{$margin}%";
            return $adjusted_price;
        }

        return $price;
    }

    /**
     * Aplica reglas de dirección de actualización
     */
    protected function apply_update_direction($new_price, $old_price, $opts)
    {
        $direction = $opts['update_direction'] ?? 'bidirectional';

        switch ($direction) {
            case 'up_only':
                // Solo permitir aumentos
                if ($new_price < $old_price) {
                    $this->rules[] = 'direction_up_only_blocked';
                    return $old_price; // Mantener precio original
                }
                $this->rules[] = 'direction_up_only_allowed';
                return $new_price;

            case 'down_only':
                // Solo permitir disminuciones
                if ($new_price > $old_price) {
                    $this->rules[] = 'direction_down_only_blocked';
                    return $old_price; // Mantener precio original
                }
                $this->rules[] = 'direction_down_only_allowed';
                return $new_price;

            default:
                // Bidireccional - permitir ambos
                $this->rules[] = 'direction_bidirectional';
                return $new_price;
        }
    }

    /**
     * Aplica reglas de categoría (exclusiones)
     */
    protected function apply_category_rules($price, $product, $product_id)
    {
        $opts = get_option('dpuwoo_settings', []);
        $excluded_categories = $opts['exclude_categories'] ?? [];

        // Verificar si el producto pertenece a categorías excluidas
        if (!empty($excluded_categories)) {
            // Usar método de WooCommerce para obtener categorías
            $product_categories = $product->get_category_ids();

            // Verificar que sea un array válido
            if (is_array($product_categories) && !empty($product_categories)) {
                // Si hay intersección entre categorías del producto y categorías excluidas
                if (array_intersect($product_categories, $excluded_categories)) {
                    $this->rules[] = 'category_excluded';
                    // Podríamos mantener el precio original o aplicar una regla especial
                    // Por ahora mantenemos el precio con una regla de exclusión
                }
            }
        }

        return $price;
    }

    /*==============================================================
    =           Helpers (incluyendo rules acumulativas)
    ==============================================================*/

    protected function error($code)
    {
        return ['error' => $code];
    }

    protected function apply_ratio($price, $ratio, $rule_key = 'ratio')
    {
        // Solo agregar a rules si es el cálculo principal (regular)
        if ($rule_key === 'ratio') {
            $this->rules[] = 'ratio_' . round($ratio, 4);
        }
        return $price * $ratio;
    }

    protected function apply_global_rounding($price, $opts, $rule_key = 'global_rounding')
    {
        return $this->apply_rounding(
            $price,
            $opts['rounding'] ?? 'none',
            $opts['round_multiple'] ?? 10,
            $rule_key
        );
    }

    protected function calculate_percentage_change($old, $new)
    {
        return $old > 0
            ? round((($new - $old) / $old) * 100, 2)
            : 0;
    }

    /*==============================================================
    =           Redondeo
    ==============================================================*/
    protected function apply_rounding($price, $method, $multiple = 10, $rule_key = 'rounding')
    {
        if ($rule_key === 'global_rounding' && $method !== 'none') {
            $this->rules[] = "global_round_{$method}_{$multiple}";
        }

        switch ($method) {
            case 'up':
                return ceil($price / $multiple) * $multiple;
            case 'down':
                return floor($price / $multiple) * $multiple;
            case 'multiple':
                return round($price / $multiple) * $multiple;
            default:
                return round($price, 2);
        }
    }
}
<?php
if (!defined('ABSPATH')) exit;

class Price_Updater
{
    protected static $instance;

    const BATCH_SIZE = 50;

    protected $log_repo;
    protected $product_repo;
    protected $logger;

    public static function init()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    private function __construct()
    {
        // Asumiendo la existencia de estas dependencias
        // Asegúrate de que Log_Repository, Product_Repository y Logger estén disponibles.
        $this->log_repo = Log_Repository::get_instance();
        $this->product_repo = Product_Repository::get_instance();
        $this->logger = Logger::get_instance();
    }

    /*==============================================================
    =           Procesamiento de Lotes (API Pública)
    ==============================================================*/

    /**
     * Procesa un array de IDs (batch), actualizando precios de productos simples y variables.
     * @param array $product_ids IDs de productos (simples o variables) a procesar.
     * @param float $current_rate Tasa de dólar actual.
     * @param float $previous_dollar_value Tasa de dólar de referencia.
     * @param bool $simulate Si es una simulación.
     * @return array Resultado del lote (cambios, contadores, errores).
     */
    public function process_batch($product_ids, $current_rate, $previous_dollar_value, $simulate = false)
    {
        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $errors_map = [];

        foreach ($product_ids as $pid) {
            $product = $this->product_repo->get_product($pid);
            if (!$product) {
                $error_count++;
                $errors_map['producto_no_encontrado'] = ($errors_map['producto_no_encontrado'] ?? 0) + 1;
                $changes[] = $this->create_change_data($pid, 'Producto no encontrado', 'N/A', 'simple', 0, 0, 0, 0, 0, 0, 'error', 'Producto no encontrado');
                continue;
            }

            $product_name = $product->get_name();
            $product_sku = $product->get_sku() ?: 'N/A';
            $product_type = $product->get_type();

            if ($product->is_type('variable')) {
                $variable_changes = $this->update_variable_product($product, $current_rate, $previous_dollar_value, $simulate);

                foreach ($variable_changes['changes'] as $c) {
                    $changes[] = array_merge($c, [
                        'product_name' => $product_name . ' - ' . ($c['variation_name'] ?? 'Variación'),
                        'product_sku' => $product_sku,
                        'product_type' => 'variation'
                    ]);
                }

                $updated_count += $variable_changes['updated'];
                $error_count += $variable_changes['errors'];
                $skipped_count += $variable_changes['skipped'];
                foreach ($variable_changes['errors_map'] as $k => $v) {
                    $errors_map[$k] = ($errors_map[$k] ?? 0) + $v;
                }
                continue;
            }

            // Producto simple
            $calc_result = Price_Calculator::get_instance()->calculate_for_product(
                $pid,
                $current_rate,
                $previous_dollar_value,
                $simulate
            );

            if (isset($calc_result['error'])) {
                $reason = $calc_result['error'];
                $errors_map[$reason] = ($errors_map[$reason] ?? 0) + 1;
                $error_count++;
                $changes[] = $this->create_change_data($pid, $product_name, $product_sku, $product_type, $calc_result['old_regular'] ?? 0, $calc_result['new_price'] ?? 0, $calc_result['old_sale'] ?? 0, $calc_result['new_sale_price'] ?? 0, 0, 0, 'error', $reason);
                continue;
            }

            $old_regular_price = floatval($calc_result['old_regular']);
            $new_regular_price = floatval($calc_result['new_price']);
            $old_sale_price = floatval($calc_result['old_sale']);
            $new_sale_price = floatval($calc_result['new_sale_price']);

            $change_data = $this->create_change_data(
                $pid,
                $product_name,
                $product_sku,
                $product_type,
                $old_regular_price,
                $new_regular_price,
                $old_sale_price,
                $new_sale_price,
                $calc_result['base_price'] ?? $old_regular_price,
                $calc_result['percentage_change'] ?? null,
                'pending'
            );

            // Agregar información de reglas aplicadas
            $change_data['applied_rules'] = $calc_result['applied_rules'] ?? [];
            $change_data['rules_summary'] = $this->format_rules_summary($calc_result['applied_rules'] ?? []);

            // Determinar si hay algún cambio significativo (regular O oferta)
            $regular_changed = $old_regular_price != $new_regular_price;
            $sale_changed = $old_sale_price != $new_sale_price;

            if (!$regular_changed && !$sale_changed) {
                $change_data['status'] = 'skipped';
                $change_data['reason'] = 'Sin cambios';
                $skipped_count++;
                $changes[] = $change_data;
                continue;
            }

            // **Lógica de actualización de precio MULTI-MONEDA**
            if (!$simulate) {
                $saved_ok_regular = true;
                $saved_ok_sale = true;
                $update_happened = false;

                // Calcular nuevo precio usando tasa de cambio directa
                $new_regular_price = $old_regular_price * $ratio;
                $new_sale_price = $sale_price_enabled 
                    ? $old_sale_price * $ratio
                    : false;

                if ($regular_changed && $new_regular_price > 0) {
                    $saved_ok_regular = $this->product_repo->save_regular_price($product, $new_regular_price);
                    $update_happened = true;
                }

                // Guardar precio de oferta (si hay cambio)
                if ($sale_changed) {
                    // new_sale_price de 0.0 limpiará el precio de oferta en el repositorio.
                    $saved_ok_sale = $this->product_repo->save_sale_price($product, $new_sale_price);
                    $update_happened = true;
                }

                if ($update_happened && $saved_ok_regular && $saved_ok_sale) {
                    $change_data['status'] = 'updated';
                    $updated_count++;
                } else {
                    $change_data['status'] = 'error';
                    $change_data['reason'] = 'WooCommerce no guardó el precio (Regular: ' . ($saved_ok_regular ? 'OK' : 'FAIL') . ', Oferta: ' . ($saved_ok_sale ? 'OK' : 'FAIL') . ')';
                    $error_count++;
                }
            } else {
                $change_data['status'] = 'simulated';
                // En simulación mostrar precio calculado
                $simulated_regular = $old_regular_price * $ratio;
                $simulated_sale = $sale_price_enabled ? $old_sale_price * $ratio : false;
                $change_data['simulated_regular'] = $simulated_regular;
                if ($simulated_sale !== false) {
                    $change_data['simulated_sale'] = $simulated_sale;
                }
            }

            $changes[] = $change_data;
        }

        return [
            'changes' => $changes,
            'updated' => $updated_count,
            'errors' => $error_count,
            'skipped' => $skipped_count,
            'errors_map' => $errors_map
        ];
    }

    /**
     * Inicia y gestiona la ejecución completa de la actualización por lotes.
     */
    public function update_all_batch($simulate = false, $batch = 0)
    {
        // 1. GET SETTINGS AND CURRENT RATE
        $opts = get_option('dpuwoo_settings', []);
        $type = $opts['dollar_type'] ?? 'oficial';

        if (!class_exists('API_Client')) {
            return ['error' => 'missing_dependencies', 'message' => 'Missing dependent classes (API_Client)'];
        }

        $api_res = API_Client::get_instance()->get_rate($type);

        if ($api_res === false) {
            return ['error' => 'no_rate_available', 'message' => 'Could not obtain current dollar value'];
        }

        $current_rate = floatval($api_res['value']);

        // 2. GET LAST APPLIED DOLLAR OR USE LAST RATE FROM SETTINGS
        $last_applied_dollar = $this->get_last_applied_dollar();
        $last_rate_from_settings = floatval($opts['last_rate'] ?? 0);
        
        // Use last applied dollar if available, otherwise use last rate from settings
        $previous_dollar_value = $last_applied_dollar > 0 ? $last_applied_dollar : $last_rate_from_settings;

        // 3. CHECK THRESHOLD - if change doesn't meet threshold, don't process
        $threshold = floatval($opts['threshold']);
        $threshold_check = self::check_threshold_met($current_rate, $previous_dollar_value, $threshold);

        // Set the instance property
        $this->set_threshold_met($threshold_check['meets_threshold']);

        if (!$this->is_threshold_met()) {
            return [
                'rate' => $current_rate,
                'previous_rate' => $previous_dollar_value,
                'ratio' => $threshold_check['ratio'],
                'percentage_change' => $threshold_check['percentage_change'],
                'threshold_met' => false,
                'threshold' => $threshold,
                'message' => 'Umbral de cambio no alcanzado',
                'total_batches' => 0,
                'changes' => [],
                'summary' => ['updated' => 0, 'errors' => 0, 'skipped' => 0, 'simulated' => $simulate]
            ];
        }

        // Use the calculated values from threshold check
        $ratio = $threshold_check['ratio'];
        $percentage_change = $threshold_check['percentage_change'];

        $total_products = $this->product_repo->count_all_products();
        $total_batches = ($total_products === 0) ? 0 : (int) ceil($total_products / self::BATCH_SIZE);
        $offset = $batch * self::BATCH_SIZE;

        if ($total_products === 0 || $batch >= $total_batches) {
            return [
                'rate' => $current_rate,
                'previous_rate' => $previous_dollar_value,
                'ratio' => $ratio,
                'percentage_change' => $percentage_change,
                'total_batches' => $total_batches,
                'changes' => [],
                'summary' => ['updated' => 0, 'errors' => 0, 'skipped' => 0, 'simulated' => $simulate]
            ];
        }

        $batch_product_ids = $this->product_repo->get_product_ids_batch(self::BATCH_SIZE, $offset);
        $processed_in_batch = count($batch_product_ids);

        $batch_result = $this->process_batch($batch_product_ids, $current_rate, $previous_dollar_value, $simulate);

        $run_id = null;

        // Persistencia solo en el último batch si no es simulación
        if (!$simulate && $batch === ($total_batches - 1)) {
            $run_id = $this->handle_run_persistence($type, $current_rate, $percentage_change, $batch_result['changes'], $opts, $total_products);
        }

        return [
            'rate' => $current_rate,
            'previous_rate' => $previous_dollar_value,
            'dollar_type' => $type,
            'ratio' => $ratio,
            'percentage_change' => $percentage_change,
            'changes' => $batch_result['changes'],
            'run_id' => $run_id,
            'batch_info' => [
                'current_batch' => $batch,
                'total_batches' => $total_batches,
                'processed_in_batch' => $processed_in_batch,
                'total_products' => $total_products
            ],
            'summary' => [
                'updated' => $batch_result['updated'],
                'errors' => $batch_result['errors'],
                'skipped' => $batch_result['skipped'],
                'simulated' => $simulate
            ],
            'errors_map' => $batch_result['errors_map'] ?? []
        ];
    }

    /**
     * Check if the percentage change meets the configured threshold
     * @param float $current_rate Current exchange rate
     * @param float $previous_rate Previous reference rate
     * @param float $threshold Threshold percentage (default 0.5)
     * @return array Contains 'meets_threshold' (bool), 'percentage_change' (float), 'abs_change' (float)
     */
    public static function check_threshold_met($current_rate, $previous_rate, $threshold = 0.5)
    {
        $ratio = ($previous_rate > 0) ? ($current_rate / $previous_rate) : 1;
        $percentage_change = ($previous_rate > 0) ? (($current_rate - $previous_rate) / $previous_rate * 100) : 0;
        $abs_percentage_change = abs($percentage_change);

        // Threshold is MET when the change EXCEEDS the threshold
        return [
            'meets_threshold' => ($threshold <= 0) || ($abs_percentage_change >= $threshold),
            'percentage_change' => $percentage_change,
            'abs_change' => $abs_percentage_change,
            'ratio' => $ratio
        ];
    }

    /**
     * Boolean property indicating if current rate change meets threshold
     * @var bool
     */
    private $threshold_met = false;

    /**
     * Get the threshold met status
     * @return bool
     */
    public function is_threshold_met()
    {
        return $this->threshold_met;
    }

    /**
     * Set the threshold met status
     * @param bool $met
     */
    public function set_threshold_met($met)
    {
        $this->threshold_met = (bool) $met;
    }

    private function verify_and_auto_configure_reference_currency()
    {
        $reference_currency = get_option('dpuwoo_reference_currency', '');

        // Auto-configure USD if not set
        if (empty($reference_currency)) {
            update_option('dpuwoo_reference_currency', 'USD');
            
            return 'USD';
        }

        return $reference_currency;
    }

    /**
     * Obtiene el último dólar aplicado de la tabla wp_dpuwoo_runs
     * @return float El valor del último dólar aplicado, o 0 si no hay ejecuciones
     */
    private function get_last_applied_dollar()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'dpuwoo_runs';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

        if (!$table_exists) {
            return 0;
        }
        $query = $wpdb->prepare(
            "SELECT dollar_value FROM {$table_name} ORDER BY id DESC LIMIT 1"
        );
        $last_dollar = $wpdb->get_var($query);

        return $last_dollar ? floatval($last_dollar) : 0;
    }
    
    /*==============================================================
    =           Lógica para Productos Variables
    ==============================================================*/

    /**
     * Maneja la actualización de precios para productos variables.
     */
    private function update_variable_product($variable_product, $current_rate, $previous_dollar_value, $simulate = false)
    {
        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $errors_map = [];

        $variation_ids = $variable_product->get_children();
        if (empty($variation_ids)) {
            return ['changes' => $changes, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'errors_map' => []];
        }

        foreach ($variation_ids as $variation_id) {
            $variation = $this->product_repo->get_variation_product($variation_id);
            if (!$variation) {
                $error_count++;
                $errors_map['variation_not_found'] = ($errors_map['variation_not_found'] ?? 0) + 1;
                continue;
            }

            $variation_name = $variation->get_name();
            $parent_name = $variable_product->get_name();
            if (strpos($variation_name, $parent_name) === 0) {
                $variation_name = trim(str_replace($parent_name, '', $variation_name), ' -');
            }

            $calc_result = Price_Calculator::get_instance()->calculate_for_product(
                $variation_id,
                $current_rate,
                $previous_dollar_value,
                $simulate
            );

            if (isset($calc_result['error'])) {
                $errors_map[$calc_result['error']] = ($errors_map[$calc_result['error']] ?? 0) + 1;
                $error_count++;
                // Puedes agregar un registro de error si lo necesitas
                continue;
            }

            $old_regular_price = floatval($calc_result['old_regular']);
            $new_regular_price = floatval($calc_result['new_price']);
            $old_sale_price = floatval($calc_result['old_sale']);
            $new_sale_price = floatval($calc_result['new_sale_price']);

            $change_data = $this->create_change_data(
                $variation_id,
                $variable_product->get_name() . ' - ' . $variation_name,
                $variation->get_sku() ?: 'N/A',
                'variation',
                $old_regular_price,
                $new_regular_price,
                $old_sale_price,
                $new_sale_price,
                $calc_result['base_price'] ?? $old_regular_price,
                $calc_result['percentage_change'] ?? null,
                'pending'
            );
            $change_data['parent_id'] = $variable_product->get_id();
            $change_data['variation_name'] = $variation_name;

            // Agregar información de reglas aplicadas para variaciones
            $change_data['applied_rules'] = $calc_result['applied_rules'] ?? [];
            $change_data['rules_summary'] = $this->format_rules_summary($calc_result['applied_rules'] ?? []);

            // Determinar si hay algún cambio significativo
            $regular_changed = $old_regular_price != $new_regular_price;
            $sale_changed = $old_sale_price != $new_sale_price;

            if (!$regular_changed && !$sale_changed) {
                $change_data['status'] = 'skipped';
                $change_data['reason'] = 'Sin cambios';
                $skipped_count++;
                $changes[] = $change_data;
                continue;
            }

            // **Lógica de actualización de precio MULTI-MONEDA**
            if (!$simulate) {
                $saved_ok_regular = true;
                $saved_ok_sale = true;
                $update_happened = false;

                // Establecer baseline USD (solo una vez por variación)
                $calculator = Price_Calculator::get_instance();
                $calculator->establish_usd_baseline($variation_id, $old_regular_price, $previous_dollar_value);

                // Usar cálculo desde baseline USD (ignora precio actual manual)
                $baseline_price = $calculator->calculate_from_usd_baseline($variation_id, $current_rate);

                // Si el cálculo desde baseline es diferente del cálculo normal, usar baseline
                if ($baseline_price > 0 && abs($baseline_price - $new_regular_price) > 0.01) {
                    $new_regular_price = $baseline_price;
                    $change_data['baseline_used'] = true;
                    $change_data['calculated_from_baseline'] = true;
                }

                if ($regular_changed && $new_regular_price > 0) {
                    $saved_ok_regular = $this->product_repo->save_regular_price($variation, $new_regular_price);
                    $update_happened = true;
                }

                if ($sale_changed) {
                    $saved_ok_sale = $this->product_repo->save_sale_price($variation, $new_sale_price);
                    $update_happened = true;
                }

                if ($update_happened && $saved_ok_regular && $saved_ok_sale) {
                    $change_data['status'] = 'updated';
                    $updated_count++;
                } else {
                    $change_data['status'] = 'error';
                    $change_data['reason'] = 'WooCommerce no guardó el precio (Regular: ' . ($saved_ok_regular ? 'OK' : 'FAIL') . ', Oferta: ' . ($saved_ok_sale ? 'OK' : 'FAIL') . ')';
                    $error_count++;
                }
            } else {
                $change_data['status'] = 'simulated';

                // En simulación también mostrar si se usaría baseline USD
                $calculator = Price_Calculator::get_instance();
                if ($calculator->has_usd_baseline($variation_id)) {
                    $baseline_price = $calculator->calculate_from_usd_baseline($variation_id, $current_rate);
                    if (abs($baseline_price - $new_regular_price) > 0.01) {
                        $change_data['would_use_baseline'] = true;
                        $change_data['baseline_price'] = $baseline_price;
                    }
                }
            }

            $changes[] = $change_data;
        }

        return [
            'changes' => $changes,
            'updated' => $updated_count,
            'errors' => $error_count,
            'skipped' => $skipped_count,
            'errors_map' => $errors_map
        ];
    }

    /*==============================================================
    =           Helpers (Persistencia y Datos)
    ==============================================================*/

    /**
     * Helper para crear el array de datos de cambio.
     */
    private function create_change_data($id, $name, $sku, $type, $old_reg, $new_reg, $old_sale, $new_sale, $base, $percent_change, $status, $reason = null)
    {
        return [
            'product_id' => $id,
            'product_name' => $name,
            'product_sku' => $sku,
            'product_type' => $type,
            'old_regular_price' => floatval($old_reg),
            'new_regular_price' => floatval($new_reg),
            'old_sale_price' => floatval($old_sale),
            'new_sale_price' => floatval($new_sale),
            'base_price' => floatval($base),
            'percentage_change' => $percent_change,
            'status' => $status,
            'reason' => $reason
        ];
    }

    /**
     * Maneja la persistencia de la ejecución de la actualización.
     */
    private function handle_run_persistence($type, $current_rate, $percentage_change, $changes, $opts, $total_products)
    {

        $opts['last_rate'] = $current_rate;
        update_option('dpuwoo_settings', $opts);

        $run_data = [
            'dollar_type' => $type,
            'dollar_value' => $current_rate,
            'rules' => $opts,
            'total_products' => 0,
            'user_id' => get_current_user_id(),
            'note' => 'Actualización automática',
            'percentage_change' => $percentage_change
        ];

        $run_id = $this->logger->begin_run_transaction($run_data);

        if ($run_id) {
            $items_saved = $this->logger->add_items_to_transaction($run_id, $changes);

            if ($items_saved) {
                $saved_count = $this->count_saved_items_from_changes($changes);
                $this->log_repo->update_run($run_id, ['total_products' => intval($saved_count)]);

                $this->logger->commit_run_transaction($run_id);
                return $run_id;
            } else {
                $this->logger->rollback_run_transaction();
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Cuenta cuántos items fueron marcados como 'updated' o 'simulated'.
     */
    private function count_saved_items_from_changes($changes)
    {
        $count = 0;
        foreach ($changes as $change) {
            if (in_array($change['status'], ['updated', 'simulated'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Formatea las reglas aplicadas para mostrar en la interfaz
     */
    private function format_rules_summary($rules)
    {
        if (empty($rules)) {
            return 'Sin reglas especiales';
        }

        $rule_descriptions = [];

        foreach ($rules as $rule) {
            switch ($rule) {
                case 'rounding_none':
                    $rule_descriptions[] = 'Sin redondeo';
                    break;
                case 'rounding_integer':
                    $rule_descriptions[] = 'Redondeo a enteros';
                    break;
                case 'rounding_ceil':
                    $rule_descriptions[] = 'Redondeo hacia arriba';
                    break;
                case 'rounding_floor':
                    $rule_descriptions[] = 'Redondeo hacia abajo';
                    break;
                case 'direction_up_only_blocked':
                    $rule_descriptions[] = 'Bloqueado (solo aumentos permitidos)';
                    break;
                case 'direction_up_only_allowed':
                    $rule_descriptions[] = 'Incremento permitido';
                    break;
                case 'direction_down_only_blocked':
                    $rule_descriptions[] = 'Bloqueado (solo disminuciones permitidas)';
                    break;
                case 'direction_down_only_allowed':
                    $rule_descriptions[] = 'Disminución permitida';
                    break;
                case 'direction_bidirectional':
                    $rule_descriptions[] = 'Actualización bidireccional';
                    break;
                case 'category_excluded':
                    $rule_descriptions[] = 'Categoría excluida';
                    break;
                case 'sale_price_cleared':
                    $rule_descriptions[] = 'Oferta eliminada (precio oferta ≥ precio regular)';
                    break;
                case 'invalid_old_sale_cleared':
                    $rule_descriptions[] = 'Oferta inválida eliminada';
                    break;
                default:
                    // Para reglas con valores dinámicos (margin_X%, rounding_nearest_X)
                    if (strpos($rule, 'margin_') === 0) {
                        $margin_value = str_replace('margin_', '', $rule);
                        $rule_descriptions[] = "Margen aplicado: {$margin_value}";
                    } elseif (strpos($rule, 'rounding_nearest_') === 0) {
                        $nearest_value = str_replace('rounding_nearest_', '', $rule);
                        $rule_descriptions[] = "Redondeo al más cercano: {$nearest_value}";
                    } elseif (strpos($rule, 'ratio_') === 0) {
                        $ratio_value = str_replace('ratio_', '', $rule);
                        $rule_descriptions[] = "Ratio de cambio: {$ratio_value}";
                    } else {
                        $rule_descriptions[] = $rule;
                    }
                    break;
            }
        }

        return implode(', ', $rule_descriptions);
    }
}
<?php
if (!defined('ABSPATH')) exit;

/**
 * Product_Repository
 * Encapsula TODA la lectura/escritura de productos de WooCommerce.
 */
class Product_Repository
{
    protected static $instance;
    protected $wpdb;

    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /* =========================================================================
     * PRODUCT IDS – PAGINACIÓN (SÓLO SELECT, PERFORMANTE)
     * ========================================================================= */

    /**
     * Devuelve el total de productos padre (simples y variables) en estado 'publish'.
     * Los posts con post_type='product' son los que el Price_Updater itera.
     */
    public function count_all_products()
    {
        // **MODIFICACIÓN CLAVE:** Usar COUNT(ID) para mayor claridad y asegurar que la consulta es correcta.
        return (int) $this->wpdb->get_var("
            SELECT COUNT(ID) FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
        ");
    }

    public function get_product_ids_batch($limit = 500, $offset = 0)
    {
        // Se mantiene igual, ya que solo debe traer los IDs de productos padre.
        return $this->wpdb->get_col($this->wpdb->prepare("
            SELECT ID FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
            ORDER BY ID ASC
            LIMIT %d OFFSET %d
        ", intval($limit), intval($offset)));
    }

    /* =========================================================================
     * PRODUCT LOADING
     * ========================================================================= */

    public function get_product($product_id)
    {
        return wc_get_product($product_id);
    }

    public function get_variations($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return [];
        }
        return $product->get_children(); // IDs de variaciones
    }

    public function get_variation_product($variation_id)
    {
        return wc_get_product($variation_id);
    }

    /* =========================================================================
     * PRICE UPDATE
     * ========================================================================= */

    public function save_regular_price($product, $new_price)
    {
        if (!$product) return false;

        try {
            $product->set_regular_price($new_price);
            $product->save();

            // Doble verificación anti-conflictos de hooks
            $stored = $product->get_regular_price();
            return ((string)$stored === (string)$new_price);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Guarda el precio de oferta (`_sale_price`) en el producto o variación.
     * @param WC_Product $product
     * @param float $new_price Si es 0.0, se limpia el precio de oferta.
     * @return bool
     */
    public function save_sale_price($product, $new_price)
    {
        if (!$product) return false;

        try {
            if ($new_price <= 0.0) {
                $product->set_sale_price(''); 
                $product->set_date_on_sale_to('');
                $product->set_date_on_sale_from('');
            } else {
                $product->set_sale_price($new_price);
            }
            
            $product->save();
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /* =========================================================================
     * HELPERS (MINIMALISTAS)
     * ========================================================================= */

    public function product_exists($product_id)
    {
        return $this->get_product($product_id) !== false;
    }

    public function get_current_regular_price($product)
    {
        return floatval($product->get_regular_price());
    }

    public function get_current_sale_price($product)
    {
        return floatval($product->get_sale_price());
    }
}
<?php 
// Trait para manejar peticiones HTTP comunes
trait HTTP_Request_Trait {
    /**
     * Realizar petición HTTP
     */
    protected function make_request($url, $args = [], $method = 'GET') {
        $default_args = [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
            'method' => $method
        ];
        
        // Combinar argumentos
        $args = wp_parse_args($args, $default_args);
        
        // Añadir API key si está disponible
        $api_key = $this->get_api_key();
        if (!empty($api_key) && isset($this->auth_header)) {
            $args['headers'][$this->auth_header] = $this->auth_header === 'Authorization' 
                ? 'Bearer ' . trim($api_key) 
                : trim($api_key);
        }
        
        // Realizar la solicitud
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('HTTP Request Error: ' . $response->get_error_message() . ' - URL: ' . $url);
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $this->log_error("HTTP Error {$code} - URL: {$url} - Body: " . substr($body, 0, 200));
            return false;
        }
        
        if (empty($body)) {
            $this->log_error("Empty response - URL: {$url}");
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            $this->log_error('Invalid JSON Response - URL: ' . $url . ' - Body: ' . substr($body, 0, 200));
            return false;
        }
        
        return [
            'code' => $code,
            'data' => $data,
            'raw' => $body
        ];
    }
    
    /**
     * Parsear valor numérico de diferentes formatos
     */
    protected function parse_numeric_value($value) {
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        if (is_string($value)) {
            // Manejar formatos como "1.234,56" o "1234.56"
            $cleaned = str_replace(['.', ','], ['', '.'], $value);
            return is_numeric($cleaned) ? floatval($cleaned) : 0;
        }
        
        return 0;
    }
    
    /**
     * Obtener API key de configuración
     */
    protected function get_api_key() {
        $opts = get_option('dpuwoo_settings', []);
        
        // Obtener la API key específica del proveedor actual
        if (get_class($this) === 'CurrencyAPI_Provider') {
            return $opts['currencyapi_api_key'] ?? '';
        } elseif (get_class($this) === 'ExchangeRateAPI_Provider') {
            return $opts['exchangerate_api_key'] ?? '';
        }
        
        // API key general para otros proveedores
        return $opts['api_key'] ?? '';
    }
    
    /**
     * Log de errores
     */
    protected function log_error($message) {
        
    }
    
    /**
     * Obtener código de país base de la tienda
     */
    protected function get_store_country() {
        $base_country = get_option('woocommerce_default_country', 'AR:AR');
        $country_parts = explode(':', $base_country);
        return strtolower($country_parts[0]);
    }
    
    /**
     * Obtener código de moneda base de la tienda
     */
    protected function get_store_currency() {
        return strtoupper(get_woocommerce_currency());
    }
    
    /**
     * Obtener nombre de moneda desde WooCommerce
     */
    protected function get_currency_name_from_woocommerce($currency_code) {
        $woocommerce_currencies = get_woocommerce_currencies();
        return $woocommerce_currencies[$currency_code] ?? $currency_code;
    }
}

// Clase base abstracta para proveedores
abstract class Base_API_Provider {
    use HTTP_Request_Trait;
    
    protected $auth_header = 'Authorization'; // Header por defecto para auth
    
    abstract public function get_rate($type);
    abstract public function get_currencies();
    abstract public function test_connection();
}
<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Dpuwoo
 * @subpackage Dpuwoo/includes
 * @author     Mauro Gaitan <maurogaitansouvaje@gmail.com>
 */
class Dpuwoo {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Dpuwoo_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'DPUWOO_VERSION' ) ) {
			$this->version = DPUWOO_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'dpuwoo';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Dpuwoo_Loader. Orchestrates the hooks of the plugin.
	 * - Dpuwoo_i18n. Defines internationalization functionality.
	 * - Dpuwoo_Admin. Defines all hooks for the admin area.
	 * - Dpuwoo_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-dpuwoo-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-dpuwoo-public.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-log-repository.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-product-repository.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-trait-request.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-currencyapi-provider.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-exhangerateapi-provider.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-dolarapi-provider.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-api.php';
	
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-price-calculator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-price-updater.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-fallback.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-logger.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-cron.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-admin-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dpuwoo-ajax-manager.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/functions.php';
		$this->loader = new Loader();


	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Dpuwoo_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		$this->loader->add_action('admin_menu', $plugin_admin, 'register_menu');
		
		$plugin_setting = new Admin_Settings();
		$this->loader->add_action('admin_init', $plugin_setting, 'register_settings');
		
		// === INSTANCIAR Ajax_Manager COMO LAS DEMÁS CLASES ===
		$ajax_manager = new Ajax_Manager();
		
		// Registrar métodos de AJAX
		$this->loader->add_action('wp_ajax_dpuwoo_simulate_batch', $ajax_manager, 'ajax_simulate_batch');
		$this->loader->add_action('wp_ajax_dpuwoo_update_batch', $ajax_manager, 'ajax_update_batch');
		$this->loader->add_action('wp_ajax_dpuwoo_update_now', $ajax_manager, 'ajax_update_now');
		$this->loader->add_action('wp_ajax_dpuwoo_get_runs', $ajax_manager, 'ajax_get_runs');
		$this->loader->add_action('wp_ajax_dpuwoo_get_run_items', $ajax_manager, 'ajax_get_run_items');
		$this->loader->add_action('wp_ajax_dpuwoo_revert_item', $ajax_manager, 'ajax_revert_item');
		$this->loader->add_action('wp_ajax_dpuwoo_revert_run', $ajax_manager, 'ajax_revert_run');
		$this->loader->add_action('wp_ajax_dpuwoo_get_currencies', $ajax_manager, 'ajax_get_currencies');
		$this->loader->add_action('wp_ajax_dpuwoo_save_settings', $ajax_manager, 'ajax_save_settings');
	}
	

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Dpuwoo_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );


	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Dpuwoo_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
<?php // Silence is golden
