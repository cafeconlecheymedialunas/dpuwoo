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

        add_settings_field(
            'dpuwoo_rate_generation_method',
            'Método de Generación de Tasa',
            [__CLASS__, 'render_rate_generation_method'],
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
        
        // Campo de método de generación de tasa (mutuamente excluyente)
        $out['rate_generation_method'] = in_array($input['rate_generation_method'], ['api', 'manual']) ? $input['rate_generation_method'] : 'manual';
        
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
        echo '<select name="dpuwoo_settings[reference_currency]" id="dpuwoo_reference_currency" class="regular-text" data-saved-value="' . esc_attr($val) . '">';
        
        // Agregar todas las opciones comunes de monedas
        $common_currencies = [
            'USD' => 'Dólar Estadounidense (USD)',
            'EUR' => 'Euro (EUR)',
            'GBP' => 'Libra Esterlina (GBP)',
            'ARS' => 'Peso Argentino (ARS)',
            'BRL' => 'Real Brasileño (BRL)',
            'CLP' => 'Peso Chileno (CLP)',
            'MXN' => 'Peso Mexicano (MXN)',
            'COP' => 'Peso Colombiano (COP)',
            'PEN' => 'Sol Peruano (PEN)',
            'UYU' => 'Peso Uruguayo (UYU)'
        ];
        
        // Agregar opción por defecto
        echo '<option value="">-- Seleccione una moneda --</option>';
        
        // Agregar todas las monedas comunes
        foreach ($common_currencies as $code => $name) {
            $selected = ($val === $code) ? ' selected' : '';
            echo '<option value="' . esc_attr($code) . '"' . $selected . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<span id="dpuwoo_currency_loading" class="spinner is-active" style="float: none; margin-left: 5px; display: none;"></span>';
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
            echo '<div class="dpuwoo-rate-field-group" style="display: flex; align-items: center; gap: 10px; max-width: 400px;">';
            echo '  <div style="position: relative; flex-grow: 1;">';
            echo '    <input type="number" step="0.0001" min="0.0001" name="dpuwoo_settings[origin_exchange_rate]" value="' . esc_attr($val) . '" class="regular-text" id="dpuwoo_origin_exchange_rate" readonly style="background-color: #f0f0f0; width: 100%; padding-right: 40px; border-radius: 6px; border: 1px solid #ccc; height: 35px;">';
            echo '    <span id="dpuwoo_edit_rate_toggle" class="dashicons dashicons-edit" title="Editar manualmente" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #007cba;"></span>';
            echo '  </div>';
            echo '  <div id="dpuwoo_rate_sync_indicator" title="Sincronizado con la API" style="color: #46b450; display: flex; align-items: center;">';
            echo '    <span class="dashicons dashicons-cloud"></span>';
            echo '  </div>';
            echo '</div>';
            
            echo '<div class="dpuwoo-rate-info" style="margin-top: 12px; padding: 12px; background-color: #f0f8ff; border-left: 4px solid #007cba; border-radius: 4px; font-size: 13px; line-height: 1.5; max-width: 600px;">';
            echo '<strong>ℹ️ Tasa de Cambio de Origen:</strong> ';
            echo 'Cotización histórica cuando estableciste los precios originales de tus productos. ';
            echo 'Ejemplo: si tus productos costaban 1000 ' . esc_html($base_currency) . ' cuando 1 USD = 100 ' . esc_html($base_currency) . ', ';
            echo 'entonces esta tasa es <strong>100</strong>. Sirve como referencia para calcular variaciones de precio.';
            echo '</div>';
            
            echo '<p class="description">Tasa de cambio utilizada como punto de partida para calcular variaciones de precios.</p>';
        } else {
            echo '<div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;">';
            echo '<p><strong>ℹ️ Campo disponible:</strong> Selecciona primero una "Moneda de referencia" para configurar la tasa de cambio de origen.</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    public static function render_rate_generation_method()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['rate_generation_method'] ?? 'manual';
        
        echo '<div class="dpuwoo-method-selector" style="display: flex; gap: 20px;">';
        
        echo '<label style="display: flex; align-items: center; cursor: pointer; font-weight: 500;">';
        echo '<input type="radio" name="dpuwoo_settings[rate_generation_method]" value="api" ' . checked($val, 'api', false) . ' style="margin-right: 8px;"> ';
        echo 'Sincronizado con API';
        echo '</label>';
        
        echo '<label style="display: flex; align-items: center; cursor: pointer; font-weight: 500;">';
        echo '<input type="radio" name="dpuwoo_settings[rate_generation_method]" value="manual" ' . checked($val, 'manual', false) . ' style="margin-right: 8px;"> ';
        echo 'Personalizado (Manual)';
        echo '</label>';
        
        echo '</div>';
        
        echo '<p class="description" style="margin-top: 10px;">';
        echo '<strong>Sincronizado:</strong> La tasa se actualiza automáticamente al cambiar de moneda.<br>';
        echo '<strong>Personalizado:</strong> El valor se bloquea y solo tú puedes editarlo usando el icono del lápiz.';
        echo '</p>';
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