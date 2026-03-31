<?php
if (!defined('ABSPATH')) exit;

class Admin_Settings
{
    public static function register_settings()
    {
        // ── Opción única compartida por ambos formularios ────────────────────
        register_setting(
            'dpuwoo_settings_group',
            'dpuwoo_settings',
            ['sanitize_callback' => [__CLASS__, 'sanitize']]
        );

        // ════════════════════════════════════════════════════════════════════
        //  PAGE: dpuwoo_settings_page — página unificada de configuración
        // ════════════════════════════════════════════════════════════════════

        add_settings_section('dpuwoo_main_section',        '', '__return_false', 'dpuwoo_settings_page');
        add_settings_section('dpuwoo_calculation_section', '', '__return_false', 'dpuwoo_settings_page');
        add_settings_section('dpuwoo_rounding_section',    '', '__return_false', 'dpuwoo_settings_page');
        add_settings_section('dpuwoo_exclusion_section',   '', '__return_false', 'dpuwoo_settings_page');

        // — Origen ————————————————————————————————————————————————————————
        add_settings_field('dpuwoo_api_provider',           'Proveedor de API',            [__CLASS__, 'render_api_provider'],           'dpuwoo_settings_page', 'dpuwoo_main_section');
        add_settings_field('dpuwoo_currencyapi_api_key',    'API Key CurrencyAPI',          [__CLASS__, 'render_currencyapi_api_key'],     'dpuwoo_settings_page', 'dpuwoo_main_section');
        add_settings_field('dpuwoo_exchangerate_api_key',   'API Key ExchangeRate',         [__CLASS__, 'render_exchangerate_api_key'],    'dpuwoo_settings_page', 'dpuwoo_main_section');
        add_settings_field('dpuwoo_country',                'País de la tienda',            [__CLASS__, 'render_country_selector'],        'dpuwoo_settings_page', 'dpuwoo_main_section');
        add_settings_field('dpuwoo_base_currency',          'Moneda base',                  [__CLASS__, 'render_base_currency'],           'dpuwoo_settings_page', 'dpuwoo_main_section');
        add_settings_field('dpuwoo_reference_currency',     'Moneda de referencia',         [__CLASS__, 'render_reference_currency'],      'dpuwoo_settings_page', 'dpuwoo_main_section');
        add_settings_field('dpuwoo_origin_exchange_rate',   'Tasa de Cambio de Origen',     [__CLASS__, 'render_origin_exchange_rate'],    'dpuwoo_settings_page', 'dpuwoo_main_section');
        add_settings_field('dpuwoo_rate_generation_method', 'Método de Generación de Tasa', [__CLASS__, 'render_rate_generation_method'], 'dpuwoo_settings_page', 'dpuwoo_main_section');

        // — Cálculo ————————————————————————————————————————————————————————
        add_settings_field('dpuwoo_margin',           'Margen de Corrección',       [__CLASS__, 'render_margin'],           'dpuwoo_settings_page', 'dpuwoo_calculation_section');
        add_settings_field('dpuwoo_threshold',        'Variación Mínima',           [__CLASS__, 'render_threshold'],        'dpuwoo_settings_page', 'dpuwoo_calculation_section');
        add_settings_field('dpuwoo_threshold_max',    'Variación Máxima Permitida', [__CLASS__, 'render_threshold_max'],    'dpuwoo_settings_page', 'dpuwoo_calculation_section');
        add_settings_field('dpuwoo_update_direction', 'Sentido de Actualización',   [__CLASS__, 'render_update_direction'], 'dpuwoo_settings_page', 'dpuwoo_calculation_section');

        // — Redondeo ———————————————————————————————————————————————————————
        add_settings_field('dpuwoo_rounding_type', 'Tipo de Redondeo', [__CLASS__, 'render_rounding_type'], 'dpuwoo_settings_page', 'dpuwoo_rounding_section');
        add_settings_field('dpuwoo_nearest_to',    'Redondear a',      [__CLASS__, 'render_nearest_to'],    'dpuwoo_settings_page', 'dpuwoo_rounding_section');

        // — Exclusiones ————————————————————————————————————————————————————
        add_settings_field('dpuwoo_exclude_categories', 'Excluir Categorías', [__CLASS__, 'render_exclude_categories'], 'dpuwoo_settings_page', 'dpuwoo_exclusion_section');

        // ════════════════════════════════════════════════════════════════════
        //  PAGE: dpuwoo_settings_page
        //  Secciones y campos para Automatización / Cron
        // ════════════════════════════════════════════════════════════════════

        add_settings_section('dpuwoo_automation_section',  '', '__return_false', 'dpuwoo_settings_page');
        add_settings_section('dpuwoo_cron_rules_section',  '', '__return_false', 'dpuwoo_settings_page');
        add_settings_section('dpuwoo_cron_format_section', '', '__return_false', 'dpuwoo_settings_page');

        // — Programación ———————————————————————————————————————————————————
        add_settings_field('dpuwoo_cron_enabled',   'Habilitar automatización', [__CLASS__, 'render_cron_enabled'], 'dpuwoo_settings_page', 'dpuwoo_automation_section');
        add_settings_field('dpuwoo_update_interval','Frecuencia',               [__CLASS__, 'render_interval'],     'dpuwoo_settings_page', 'dpuwoo_automation_section');

        // — Reglas de cálculo cron —————————————————————————————————————————
        add_settings_field('dpuwoo_cron_margin',           'Margen de Corrección',       [__CLASS__, 'render_cron_margin'],           'dpuwoo_settings_page', 'dpuwoo_cron_rules_section');
        add_settings_field('dpuwoo_cron_threshold',        'Variación Mínima',           [__CLASS__, 'render_cron_threshold'],        'dpuwoo_settings_page', 'dpuwoo_cron_rules_section');
        add_settings_field('dpuwoo_cron_threshold_max',    'Variación Máxima Permitida', [__CLASS__, 'render_cron_threshold_max'],    'dpuwoo_settings_page', 'dpuwoo_cron_rules_section');
        add_settings_field('dpuwoo_cron_update_direction', 'Sentido de Actualización',   [__CLASS__, 'render_cron_update_direction'], 'dpuwoo_settings_page', 'dpuwoo_cron_rules_section');

        // — Redondeo y exclusiones cron ————————————————————————————————————
        add_settings_field('dpuwoo_cron_rounding_type',      'Tipo de Redondeo',   [__CLASS__, 'render_cron_rounding_type'],      'dpuwoo_settings_page', 'dpuwoo_cron_format_section');
        add_settings_field('dpuwoo_cron_nearest_to',         'Redondear a',        [__CLASS__, 'render_cron_nearest_to'],         'dpuwoo_settings_page', 'dpuwoo_cron_format_section');
        add_settings_field('dpuwoo_cron_exclude_categories', 'Excluir Categorías', [__CLASS__, 'render_cron_exclude_categories'], 'dpuwoo_settings_page', 'dpuwoo_cron_format_section');
    }

    // ════════════════════════════════════════════════════════════════════════
    //  SANITIZACIÓN
    // ════════════════════════════════════════════════════════════════════════

    public static function sanitize($input)
    {
        $out = [];

        // — Origen (compartido) ——————————————————————————————————————————
        $out['api_provider']           = sanitize_text_field($input['api_provider']           ?? 'dolarapi');
        $out['currencyapi_api_key']    = sanitize_text_field($input['currencyapi_api_key']    ?? '');
        $out['exchangerate_api_key']   = sanitize_text_field($input['exchangerate_api_key']   ?? '');
        $out['country']                = sanitize_text_field($input['country']                ?? 'AR');
        $out['base_currency']          = sanitize_text_field($input['base_currency']          ?? (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'ARS'));
        $out['reference_currency']     = sanitize_text_field($input['reference_currency']     ?? 'USD');
        $out['origin_exchange_rate']   = floatval($input['origin_exchange_rate'] ?? 1.0);
        $out['rate_generation_method'] = in_array($input['rate_generation_method'] ?? '', ['api', 'manual']) ? $input['rate_generation_method'] : 'manual';

        // Preservar dollar_type: no hay campo de formulario, se setea desde el dashboard.
        // Evitar que guardar settings lo pise con el default.
        $existing             = get_option('dpuwoo_settings', []);
        $out['dollar_type']   = sanitize_text_field($input['dollar_type'] ?? ($existing['dollar_type'] ?? 'oficial'));

        // — Cálculo manual ———————————————————————————————————————————————
        $out['margin']           = floatval($input['margin']           ?? 0);
        $out['threshold']        = floatval($input['threshold']        ?? 0.5);
        $out['threshold_max']    = floatval($input['threshold_max']    ?? 0);
        $out['update_direction'] = sanitize_text_field($input['update_direction'] ?? 'bidirectional');

        // — Redondeo manual ——————————————————————————————————————————————
        $out['rounding_type'] = sanitize_text_field($input['rounding_type'] ?? 'integer');
        $out['nearest_to']    = sanitize_text_field($input['nearest_to']    ?? '1');

        // — Exclusiones manual ———————————————————————————————————————————
        $out['exclude_categories'] = array_map('intval', $input['exclude_categories'] ?? []);

        // — Automatización / Cron ————————————————————————————————————————
        $out['cron_enabled'] = isset($input['cron_enabled']) ? 1 : 0;
        $out['interval']     = max(300, intval($input['interval'] ?? 3600));

        // — Reglas cron ('' = usar fallback manual) ——————————————————————
        $out['cron_margin']           = ($input['cron_margin']        ?? '') !== '' ? floatval($input['cron_margin'])        : '';
        $out['cron_threshold']        = ($input['cron_threshold']     ?? '') !== '' ? floatval($input['cron_threshold'])     : '';
        $out['cron_threshold_max']    = ($input['cron_threshold_max'] ?? '') !== '' ? floatval($input['cron_threshold_max']) : '';
        $out['cron_update_direction'] = sanitize_text_field($input['cron_update_direction'] ?? '');
        $out['cron_rounding_type']    = sanitize_text_field($input['cron_rounding_type']    ?? '');
        $out['cron_nearest_to']       = sanitize_text_field($input['cron_nearest_to']       ?? '');

        // Exclusiones cron: array vacío también significa "sin selección" = fallback manual
        $out['cron_exclude_categories'] = isset($input['cron_exclude_categories'])
            ? array_map('intval', $input['cron_exclude_categories'])
            : [];

        return $out;
    }

    // ════════════════════════════════════════════════════════════════════════
    //  RENDERS — Campos de Ejecución Manual
    // ════════════════════════════════════════════════════════════════════════

    public static function render_country_selector()
    {
        $base_country = get_option('woocommerce_default_country', '');
        if (strpos($base_country, ':') !== false) {
            $base_country = substr($base_country, 0, strpos($base_country, ':'));
        }
        $country_name = $base_country;
        if (function_exists('WC') && method_exists(WC()->countries, 'get_countries')) {
            $countries    = WC()->countries->get_countries();
            $country_name = $countries[$base_country] ?? $base_country;
        }
        echo '<input type="text" readonly value="' . esc_attr($country_name . ' (' . $base_country . ')') . '" class="regular-text" style="background-color:#f0f0f0;">';
        echo '<p class="description">Configurado en <strong>WooCommerce → Ajustes → Generales</strong>.</p>';
        echo '<input type="hidden" name="dpuwoo_settings[country]" value="' . esc_attr($base_country) . '">';
    }

    public static function render_base_currency()
    {
        $store_currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'ARS';
        echo '<input type="text" readonly value="' . esc_attr($store_currency) . '" class="regular-text" style="background-color:#f0f0f0;">';
        echo '<p class="description">Moneda configurada en <strong>WooCommerce → Ajustes → Generales</strong>.</p>';
        echo '<input type="hidden" name="dpuwoo_settings[base_currency]" value="' . esc_attr($store_currency) . '">';
    }

    public static function render_reference_currency()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['reference_currency'] ?? 'USD';
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
            'UYU' => 'Peso Uruguayo (UYU)',
        ];
        echo '<div id="dpuwoo_currency_selector_container">';
        echo '<select name="dpuwoo_settings[reference_currency]" id="dpuwoo_reference_currency" class="regular-text" data-saved-value="' . esc_attr($val) . '">';
        echo '<option value="">-- Seleccione una moneda --</option>';
        foreach ($common_currencies as $code => $name) {
            printf('<option value="%s"%s>%s</option>', esc_attr($code), selected($val, $code, false), esc_html($name));
        }
        echo '</select>';
        echo '<span id="dpuwoo_currency_loading" class="spinner is-active" style="float:none;margin-left:5px;display:none;"></span>';
        echo '</div>';
        echo '<p class="description">Moneda cuya cotización se usará para recalcular los precios.</p>';
        $base_country = get_option('woocommerce_default_country', '');
        if (strpos($base_country, ':') !== false) {
            $base_country = substr($base_country, 0, strpos($base_country, ':'));
        }
        echo '<input type="hidden" id="dpuwoo_base_country" value="' . esc_attr($base_country) . '">';
    }

    public static function render_origin_exchange_rate()
    {
        $opts               = get_option('dpuwoo_settings', []);
        $val                = $opts['origin_exchange_rate'] ?? 1.0;
        $reference_currency = $opts['reference_currency'] ?? '';
        $show_field         = !empty($reference_currency);

        echo '<div class="dpuwoo-origin-rate-container" id="dpuwoo_origin_rate_container" style="' . ($show_field ? '' : 'display:none;') . '">';
        if ($show_field) {
            echo '<div style="display:flex;align-items:center;gap:10px;max-width:400px;">';
            echo '  <div style="position:relative;flex-grow:1;">';
            echo '    <input type="number" step="0.0001" min="0.0001" name="dpuwoo_settings[origin_exchange_rate]" value="' . esc_attr($val) . '" class="regular-text" id="dpuwoo_origin_exchange_rate" readonly style="background-color:#f0f0f0;width:100%;padding-right:40px;border-radius:6px;border:1px solid #ccc;height:35px;">';
            echo '    <span id="dpuwoo_edit_rate_toggle" class="dashicons dashicons-edit" title="Editar manualmente" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#007cba;"></span>';
            echo '  </div>';
            echo '  <div id="dpuwoo_rate_sync_indicator" title="Sincronizado con la API" style="color:#46b450;display:flex;align-items:center;">';
            echo '    <span class="dashicons dashicons-cloud"></span>';
            echo '  </div>';
            echo '</div>';
            echo '<p class="description">Cotización histórica cuando estableciste los precios originales.</p>';
        } else {
            echo '<p class="description">Seleccioná primero una moneda de referencia.</p>';
        }
        echo '</div>';
    }

    public static function render_rate_generation_method()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['rate_generation_method'] ?? 'manual';
        echo '<div style="display:flex;gap:20px;">';
        echo '<label style="display:flex;align-items:center;cursor:pointer;font-weight:500;"><input type="radio" name="dpuwoo_settings[rate_generation_method]" value="api" ' . checked($val, 'api', false) . ' style="margin-right:8px;"> Sincronizado con API</label>';
        echo '<label style="display:flex;align-items:center;cursor:pointer;font-weight:500;"><input type="radio" name="dpuwoo_settings[rate_generation_method]" value="manual" ' . checked($val, 'manual', false) . ' style="margin-right:8px;"> Personalizado (Manual)</label>';
        echo '</div>';
        echo '<p class="description"><strong>Sincronizado:</strong> la tasa se actualiza al cambiar de moneda. <strong>Personalizado:</strong> bloqueado, editable con el lápiz.</p>';
    }

    public static function render_api_provider()
    {
        $opts      = get_option('dpuwoo_settings', []);
        $val       = $opts['api_provider'] ?? 'dolarapi';
        $providers = class_exists('API_Client') ? API_Client::get_available_providers() : [];
        echo '<select name="dpuwoo_settings[api_provider]" id="dpuwoo_api_provider" class="regular-text">';
        foreach ($providers as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label['name'] ?? $k));
        }
        echo '</select>';
        echo '<p class="description">Proveedor de tasas de cambio.</p>';
    }

    public static function render_currencyapi_api_key()
    {
        $opts             = get_option('dpuwoo_settings', []);
        $val              = $opts['currencyapi_api_key'] ?? '';
        $current_provider = $opts['api_provider'] ?? 'dolarapi';
        $visible          = ($current_provider === 'currencyapi') ? 'dpuwoo-api-key-visible' : 'dpuwoo-api-key-hidden';
        echo '<div id="dpuwoo_currencyapi_key_container" class="dpuwoo-api-key-field ' . esc_attr($visible) . '">';
        echo '<input type="password" name="dpuwoo_settings[currencyapi_api_key]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">API Key de CurrencyAPI.</p>';
        echo '</div>';
    }

    public static function render_exchangerate_api_key()
    {
        $opts             = get_option('dpuwoo_settings', []);
        $val              = $opts['exchangerate_api_key'] ?? '';
        $current_provider = $opts['api_provider'] ?? 'dolarapi';
        $visible          = ($current_provider === 'exchangerate-api') ? 'dpuwoo-api-key-visible' : 'dpuwoo-api-key-hidden';
        echo '<div id="dpuwoo_exchangerate_key_container" class="dpuwoo-api-key-field ' . esc_attr($visible) . '">';
        echo '<input type="password" name="dpuwoo_settings[exchangerate_api_key]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">API Key de ExchangeRate-API.</p>';
        echo '</div>';
    }

    public static function render_margin()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['margin'] ?? 0;
        echo '<input type="number" step="0.1" name="dpuwoo_settings[margin]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Porcentaje extra sumado a la variación del tipo de cambio.</p>';
    }

    public static function render_threshold()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['threshold'] ?? 0.5;
        echo '<input type="number" step="0.1" min="0" name="dpuwoo_settings[threshold]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Variación mínima requerida. Con <strong>0%</strong> siempre actualiza.</p>';
    }

    public static function render_threshold_max()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['threshold_max'] ?? 0;
        echo '<input type="number" step="0.1" min="0" name="dpuwoo_settings[threshold_max]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Freno de seguridad: si supera este valor se bloquea. Con <strong>0%</strong> no hay límite.</p>';
    }

    public static function render_update_direction()
    {
        $opts       = get_option('dpuwoo_settings', []);
        $val        = $opts['update_direction'] ?? 'bidirectional';
        $directions = [
            'bidirectional' => 'Bidireccional (sube y baja)',
            'up_only'       => 'Solo incremento',
            'down_only'     => 'Solo disminución',
        ];
        echo '<select name="dpuwoo_settings[update_direction]" class="regular-text">';
        foreach ($directions as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Dirección en que se actualizan los precios.</p>';
    }

    public static function render_rounding_type()
    {
        $opts  = get_option('dpuwoo_settings', []);
        $val   = $opts['rounding_type'] ?? 'integer';
        $types = [
            'none'    => 'Sin redondeo',
            'integer' => 'Enteros',
            'ceil'    => 'Al mayor (Ceiling)',
            'floor'   => 'Al menor (Floor)',
            'nearest' => 'Al más cercano',
        ];
        echo '<select name="dpuwoo_settings[rounding_type]" class="regular-text">';
        foreach ($types as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Cómo se redondean los decimales del precio.</p>';
    }

    public static function render_nearest_to()
    {
        $opts    = get_option('dpuwoo_settings', []);
        $val     = $opts['nearest_to'] ?? '1';
        $options = ['1' => 'Unidad', '10' => 'Decena', '50' => 'Cincuenta', '100' => 'Centena'];
        echo '<select name="dpuwoo_settings[nearest_to]" class="regular-text">';
        foreach ($options as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Solo aplica cuando el tipo de redondeo es "Al más cercano".</p>';
    }

    public static function render_exclude_categories()
    {
        $opts       = get_option('dpuwoo_settings', []);
        $selected   = $opts['exclude_categories'] ?? [];
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        if (!empty($categories) && !is_wp_error($categories)) {
            echo '<select name="dpuwoo_settings[exclude_categories][]" multiple="multiple" class="regular-text" style="height:150px;">';
            foreach ($categories as $cat) {
                printf('<option value="%s"%s>%s</option>', esc_attr($cat->term_id), selected(in_array($cat->term_id, $selected), true, false), esc_html($cat->name . ' (' . $cat->count . ')'));
            }
            echo '</select>';
            echo '<p class="description">Ctrl+clic para seleccionar múltiples categorías.</p>';
        } else {
            echo '<p>No hay categorías creadas.</p>';
        }
    }

    public static function render_interval()
    {
        $opts    = get_option('dpuwoo_settings', []);
        $val     = intval($opts['interval'] ?? 3600);
        $hours   = floor($val / 3600);
        $minutes = floor(($val % 3600) / 60);
        echo '<input type="number" name="dpuwoo_settings[interval]" value="' . esc_attr($val) . '" min="300" class="small-text"> segundos';
        echo '<p style="margin-top:4px;">Equivalente a: <strong>' . esc_html($hours) . 'h ' . esc_html($minutes) . 'm</strong></p>';
        echo '<p class="description">Mínimo 300 seg (5 min).</p>';
    }

    // ════════════════════════════════════════════════════════════════════════
    //  RENDERS — Campos propios del Cron
    // ════════════════════════════════════════════════════════════════════════

    public static function render_cron_enabled()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['cron_enabled'] ?? 1;
        echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">';
        echo '<input type="checkbox" name="dpuwoo_settings[cron_enabled]" value="1"' . checked(1, $val, false) . '>';
        echo '<span>Habilitar actualización automática por cron</span>';
        echo '</label>';
        echo '<p class="description">Desactivar pausa el cron sin borrar la configuración.</p>';
    }

    public static function render_cron_margin()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['cron_margin'] ?? '';
        echo '<input type="number" step="0.1" min="0" name="dpuwoo_settings[cron_margin]" value="' . esc_attr($val) . '" class="small-text" placeholder="(manual)"> %';
        echo '<p class="description">Margen extra para el cron. Vacío = usa el de Ejecución Manual.</p>';
    }

    public static function render_cron_threshold()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['cron_threshold'] ?? '';
        echo '<input type="number" step="0.1" min="0" name="dpuwoo_settings[cron_threshold]" value="' . esc_attr($val) . '" class="small-text" placeholder="(manual)"> %';
        echo '<p class="description">Variación mínima para el cron. Vacío = usa el de Ejecución Manual.</p>';
    }

    public static function render_cron_threshold_max()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val  = $opts['cron_threshold_max'] ?? '';
        echo '<input type="number" step="0.1" min="0" name="dpuwoo_settings[cron_threshold_max]" value="' . esc_attr($val) . '" class="small-text" placeholder="(manual)"> %';
        echo '<p class="description">Freno de seguridad del cron. Vacío = usa el de Ejecución Manual.</p>';
    }

    public static function render_cron_update_direction()
    {
        $opts       = get_option('dpuwoo_settings', []);
        $val        = $opts['cron_update_direction'] ?? '';
        $directions = [
            ''              => '(usar configuración manual)',
            'bidirectional' => 'Bidireccional (sube y baja)',
            'up_only'       => 'Solo incremento',
            'down_only'     => 'Solo disminución',
        ];
        echo '<select name="dpuwoo_settings[cron_update_direction]" class="regular-text">';
        foreach ($directions as $k => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Vacío = usa la dirección de Ejecución Manual.</p>';
    }

    public static function render_cron_rounding_type()
    {
        $opts  = get_option('dpuwoo_settings', []);
        $val   = $opts['cron_rounding_type'] ?? '';
        $types = [
            ''        => '(usar configuración manual)',
            'none'    => 'Sin redondeo',
            'integer' => 'Enteros',
            'ceil'    => 'Al mayor (Ceiling)',
            'floor'   => 'Al menor (Floor)',
            'nearest' => 'Al más cercano',
        ];
        echo '<select name="dpuwoo_settings[cron_rounding_type]" class="regular-text">';
        foreach ($types as $k => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Vacío = usa el redondeo de Ejecución Manual.</p>';
    }

    public static function render_cron_nearest_to()
    {
        $opts    = get_option('dpuwoo_settings', []);
        $val     = $opts['cron_nearest_to'] ?? '';
        $options = ['' => '(usar configuración manual)', '1' => 'Unidad', '10' => 'Decena', '50' => 'Cincuenta', '100' => 'Centena'];
        echo '<select name="dpuwoo_settings[cron_nearest_to]" class="regular-text">';
        foreach ($options as $k => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Solo aplica cuando el redondeo del cron es "Al más cercano".</p>';
    }

    public static function render_cron_exclude_categories()
    {
        $opts       = get_option('dpuwoo_settings', []);
        $selected   = $opts['cron_exclude_categories'] ?? [];
        $is_array   = is_array($selected);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);

        echo '<p class="description" style="margin-bottom:6px;">Sin selección = usa las exclusiones de Ejecución Manual.</p>';
        if (!empty($categories) && !is_wp_error($categories)) {
            echo '<select name="dpuwoo_settings[cron_exclude_categories][]" multiple="multiple" class="regular-text" style="height:130px;">';
            foreach ($categories as $cat) {
                $is_selected = $is_array && in_array($cat->term_id, $selected);
                printf('<option value="%s"%s>%s</option>', esc_attr($cat->term_id), selected($is_selected, true, false), esc_html($cat->name . ' (' . $cat->count . ')'));
            }
            echo '</select>';
            echo '<p class="description">Ctrl+clic para seleccionar múltiples.</p>';
        } else {
            echo '<p>No hay categorías creadas.</p>';
        }
    }
}
