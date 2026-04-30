<?php
if (!defined('ABSPATH')) exit;

class Admin_Settings
{
    public static function register_settings()
    {
        // ── Opción única compartida por ambos formularios ────────────────────
        register_setting(
            'prixy_settings_group',
            'prixy_settings',
            ['sanitize_callback' => [__CLASS__, 'sanitize']]
        );

        // Reprogramar cron cuando se guardan los settings
        add_action('update_option_prixy_settings', [__CLASS__, 'on_settings_updated'], 10, 3);

        // ════════════════════════════════════════════════════════════════════
        //  PAGE: prixy_settings_page — página unificada de configuración
        // ════════════════════════════════════════════════════════════════════

        add_settings_section('prixy_main_section',        '', '__return_false', 'prixy_settings_page');
        add_settings_section('prixy_calculation_section', '', '__return_false', 'prixy_settings_page');
        add_settings_section('prixy_rounding_section',    '', '__return_false', 'prixy_settings_page');
        add_settings_section('prixy_exclusion_section',   '', '__return_false', 'prixy_settings_page');

        // — Origen ————————————————————————————————————————————————————————
        add_settings_field('prixy_api_provider',           'Proveedor de API',            [__CLASS__, 'render_api_provider'],           'prixy_settings_page', 'prixy_main_section');
        add_settings_field('prixy_currencyapi_api_key',    'API Key CurrencyAPI',          [__CLASS__, 'render_currencyapi_api_key'],     'prixy_settings_page', 'prixy_main_section');
        add_settings_field('prixy_exchangerate_api_key',   'API Key ExchangeRate',         [__CLASS__, 'render_exchangerate_api_key'],    'prixy_settings_page', 'prixy_main_section');
        add_settings_field('prixy_country',                'País de la tienda',            [__CLASS__, 'render_country_selector'],        'prixy_settings_page', 'prixy_main_section');
        add_settings_field('prixy_base_currency',          'Moneda base',                  [__CLASS__, 'render_base_currency'],           'prixy_settings_page', 'prixy_main_section');
        add_settings_field('prixy_reference_currency',     'Moneda de referencia',         [__CLASS__, 'render_reference_currency'],      'prixy_settings_page', 'prixy_main_section');
        add_settings_field('prixy_origin_exchange_rate',   'Tasa de Cambio de Origen',     [__CLASS__, 'render_origin_exchange_rate'],    'prixy_settings_page', 'prixy_main_section');
        add_settings_field('prixy_rate_generation_method', 'Método de Generación de Tasa', [__CLASS__, 'render_rate_generation_method'], 'prixy_settings_page', 'prixy_main_section');

        // — Cálculo ————————————————————————————————————————————————————————
        add_settings_field('prixy_margin',           'Margen de Corrección',       [__CLASS__, 'render_margin'],           'prixy_settings_page', 'prixy_calculation_section');
        add_settings_field('prixy_threshold',        'Variación Mínima',           [__CLASS__, 'render_threshold'],        'prixy_settings_page', 'prixy_calculation_section');
        add_settings_field('prixy_threshold_max',    'Variación Máxima Permitida', [__CLASS__, 'render_threshold_max'],    'prixy_settings_page', 'prixy_calculation_section');
        add_settings_field('prixy_update_direction', 'Sentido de Actualización',   [__CLASS__, 'render_update_direction'], 'prixy_settings_page', 'prixy_calculation_section');

        // — Redondeo ———————————————————————————————————————————————————————
        add_settings_field('prixy_rounding_type', 'Tipo de Redondeo', [__CLASS__, 'render_rounding_type'], 'prixy_settings_page', 'prixy_rounding_section');
        add_settings_field('prixy_nearest_to',    'Redondear a',      [__CLASS__, 'render_nearest_to'],    'prixy_settings_page', 'prixy_rounding_section');

        // — Exclusiones ————————————————————————————————————————————————————
        add_settings_field('prixy_exclude_categories', 'Excluir Categorías', [__CLASS__, 'render_exclude_categories'], 'prixy_settings_page', 'prixy_exclusion_section');

        // ════════════════════════════════════════════════════════════════════
        //  PAGE: prixy_settings_page
        //  Secciones y campos para Automatización / Cron
        // ════════════════════════════════════════════════════════════════════

        add_settings_section('prixy_automation_section', '', '__return_false', 'prixy_settings_page');

        // — Programación ———————————————————————————————————————————————————
        add_settings_field('prixy_cron_enabled',      'Habilitar automatización', [__CLASS__, 'render_cron_enabled'],      'prixy_settings_page', 'prixy_automation_section');
        add_settings_field('prixy_update_interval',   'Frecuencia',               [__CLASS__, 'render_interval'],          'prixy_settings_page', 'prixy_automation_section');
        add_settings_field('prixy_cron_api_provider', 'API para Cron',            [__CLASS__, 'render_cron_api_provider'], 'prixy_settings_page', 'prixy_automation_section');
        add_settings_field('prixy_cron_dollar_type',  'Moneda para Cron',         [__CLASS__, 'render_cron_dollar_type'],  'prixy_settings_page', 'prixy_automation_section');
        add_settings_field('prixy_cron_notify_mode',  'Notificaciones',           [__CLASS__, 'render_cron_notify_mode'],  'prixy_settings_page', 'prixy_automation_section');
        add_settings_field('prixy_cron_notify_email', 'Email de notificación',    [__CLASS__, 'render_cron_notify_email'], 'prixy_settings_page', 'prixy_automation_section');
    }

    // ════════════════════════════════════════════════════════════════════════
    //  SANITIZACIÓN
    // ════════════════════════════════════════════════════════════════════════

    public static function sanitize($input)
    {
        $out = [];

        // Leer settings existentes primero para preservar campos no enviados en el POST
        $existing = get_option('prixy_settings', []);

        // — Origen (compartido) ——————————————————————————————————————————
        $out['api_provider']           = sanitize_text_field($input['api_provider']           ?? 'dolarapi');
        $out['currencyapi_api_key']    = sanitize_text_field($input['currencyapi_api_key']    ?? '');
        $out['exchangerate_api_key']   = sanitize_text_field($input['exchangerate_api_key']   ?? '');
        $out['country']                = sanitize_text_field($input['country']                ?? 'AR');
        $out['base_currency']          = sanitize_text_field($input['base_currency']          ?? (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'ARS'));
        $out['reference_currency']     = sanitize_text_field($input['reference_currency']     ?? 'USD');
        // Preservar si el campo no vino en el POST (campo oculto cuando ya está configurado)
        $existing_rate = floatval($existing['origin_exchange_rate'] ?? 0);
        $submitted_rate = isset($input['origin_exchange_rate']) ? floatval($input['origin_exchange_rate']) : null;
        $out['origin_exchange_rate'] = ($submitted_rate !== null && $submitted_rate > 0) ? $submitted_rate : ($existing_rate > 0 ? $existing_rate : 0);
        // Preservar el flag de bloqueo: tomar del input si viene, si no del existente
        $out['origin_rate_locked']   = !empty($input['origin_rate_locked']) ? $input['origin_rate_locked'] : ($existing['origin_rate_locked'] ?? false);
        $out['rate_generation_method'] = in_array($input['rate_generation_method'] ?? '', ['api', 'manual']) ? $input['rate_generation_method'] : 'manual';

        // Preservar currency y cron_currency
        $out['currency'] = sanitize_text_field($input['currency'] ?? ($existing['currency'] ?? 'oficial'));
        $out['cron_currency'] = sanitize_text_field($input['cron_currency'] ?? ($existing['cron_currency'] ?? ''));

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
        $out['update_interval'] = sanitize_text_field($input['update_interval'] ?? ($existing['update_interval'] ?? 'twicedaily'));

        // — API Cron (propia) ——————————————————————————————————————————
        $out['cron_api_provider']         = sanitize_text_field($input['cron_api_provider']         ?? '');
        $out['cron_country']             = sanitize_text_field($input['cron_country']             ?? '');
        $out['cron_reference_currency']  = sanitize_text_field($input['cron_reference_currency']  ?? '');
        $out['cron_dollar_type']         = sanitize_text_field($input['cron_dollar_type']         ?? '');

        // — Notificaciones cron ——————————————————————————————————————————————
        $valid_notify_modes = ['update_and_notify', 'simulate_only', 'disabled'];
        $out['cron_notify_mode'] = in_array($input['cron_notify_mode'] ?? '', $valid_notify_modes)
            ? $input['cron_notify_mode']
            : 'update_and_notify';
        $out['cron_notify_email'] = sanitize_email($input['cron_notify_email'] ?? get_option('admin_email'));

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
        echo '<input type="hidden" name="prixy_settings[country]" value="' . esc_attr($base_country) . '">';
    }

    public static function render_base_currency()
    {
        $store_currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'ARS';
        echo '<input type="text" readonly value="' . esc_attr($store_currency) . '" class="regular-text" style="background-color:#f0f0f0;">';
        echo '<p class="description">Moneda configurada en <strong>WooCommerce → Ajustes → Generales</strong>.</p>';
        echo '<input type="hidden" name="prixy_settings[base_currency]" value="' . esc_attr($store_currency) . '">';
    }

    public static function render_reference_currency()
    {
        $opts = get_option('prixy_settings', []);
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
        echo '<div id="prixy_currency_selector_container">';
        echo '<select name="prixy_settings[reference_currency]" id="prixy_reference_currency" class="regular-text" data-saved-value="' . esc_attr($val) . '">';
        echo '<option value="">-- Seleccione una moneda --</option>';
        foreach ($common_currencies as $code => $name) {
            printf('<option value="%s"%s>%s</option>', esc_attr($code), selected($val, $code, false), esc_html($name));
        }
        echo '</select>';
        echo '<span id="prixy_currency_loading" class="spinner is-active" style="float:none;margin-left:5px;display:none;"></span>';
        echo '</div>';
        echo '<p class="description">Moneda cuya cotización se usará para recalcular los precios.</p>';
        $base_country = get_option('woocommerce_default_country', '');
        if (strpos($base_country, ':') !== false) {
            $base_country = substr($base_country, 0, strpos($base_country, ':'));
        }
        echo '<input type="hidden" id="prixy_base_country" value="' . esc_attr($base_country) . '">';
    }

    public static function render_origin_exchange_rate()
    {
        $opts               = get_option('prixy_settings', []);
        $val                = $opts['origin_exchange_rate'] ?? 1.0;
        $reference_currency = $opts['reference_currency'] ?? '';
        $show_field         = !empty($reference_currency);

        echo '<div class="prixy-origin-rate-container" id="prixy_origin_rate_container" style="' . ($show_field ? '' : 'display:none;') . '">';
        if ($show_field) {
            echo '<div style="display:flex;align-items:center;gap:10px;max-width:400px;">';
            echo '  <div style="position:relative;flex-grow:1;">';
            echo '    <input type="number" step="0.0001" min="0.0001" name="prixy_settings[origin_exchange_rate]" value="' . esc_attr($val) . '" class="regular-text" id="prixy_origin_exchange_rate" readonly style="background-color:#f0f0f0;width:100%;padding-right:40px;border-radius:6px;border:1px solid #ccc;height:35px;">';
            echo '    <span id="prixy_edit_rate_toggle" class="dashicons dashicons-edit" title="Editar manualmente" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#007cba;"></span>';
            echo '  </div>';
            echo '  <div id="prixy_rate_sync_indicator" title="Sincronizado con la API" style="color:#46b450;display:flex;align-items:center;">';
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
        $opts = get_option('prixy_settings', []);
        $val  = $opts['rate_generation_method'] ?? 'manual';
        echo '<div style="display:flex;gap:20px;">';
        echo '<label style="display:flex;align-items:center;cursor:pointer;font-weight:500;"><input type="radio" name="prixy_settings[rate_generation_method]" value="api" ' . checked($val, 'api', false) . ' style="margin-right:8px;"> Sincronizado con API</label>';
        echo '<label style="display:flex;align-items:center;cursor:pointer;font-weight:500;"><input type="radio" name="prixy_settings[rate_generation_method]" value="manual" ' . checked($val, 'manual', false) . ' style="margin-right:8px;"> Personalizado (Manual)</label>';
        echo '</div>';
        echo '<p class="description"><strong>Sincronizado:</strong> la tasa se actualiza al cambiar de moneda. <strong>Personalizado:</strong> bloqueado, editable con el lápiz.</p>';
    }

    public static function render_api_provider()
    {
        $opts      = get_option('prixy_settings', []);
        $val       = $opts['api_provider'] ?? 'dolarapi';
        $providers = class_exists('API_Client') ? API_Client::get_available_providers() : [];
        echo '<select name="prixy_settings[api_provider]" id="prixy_api_provider" class="regular-text">';
        foreach ($providers as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label['name'] ?? $k));
        }
        echo '</select>';
        echo '<p class="description">Proveedor de tasas de cambio.</p>';
    }

    public static function render_currencyapi_api_key()
    {
        $opts             = get_option('prixy_settings', []);
        $val              = $opts['currencyapi_api_key'] ?? '';
        $current_provider = $opts['api_provider'] ?? 'dolarapi';
        $visible          = ($current_provider === 'currencyapi') ? 'prixy-api-key-visible' : 'prixy-api-key-hidden';
        echo '<div id="prixy_currencyapi_key_container" class="prixy-api-key-field ' . esc_attr($visible) . '">';
        echo '<input type="password" name="prixy_settings[currencyapi_api_key]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">API Key de CurrencyAPI.</p>';
        echo '</div>';
    }

    public static function render_exchangerate_api_key()
    {
        $opts             = get_option('prixy_settings', []);
        $val              = $opts['exchangerate_api_key'] ?? '';
        $current_provider = $opts['api_provider'] ?? 'dolarapi';
        $visible          = ($current_provider === 'exchangerate-api') ? 'prixy-api-key-visible' : 'prixy-api-key-hidden';
        echo '<div id="prixy_exchangerate_key_container" class="prixy-api-key-field ' . esc_attr($visible) . '">';
        echo '<input type="password" name="prixy_settings[exchangerate_api_key]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">API Key de ExchangeRate-API.</p>';
        echo '</div>';
    }

    public static function render_margin()
    {
        $opts = get_option('prixy_settings', []);
        $val  = $opts['margin'] ?? 0;
        echo '<input type="number" step="0.1" name="prixy_settings[margin]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Porcentaje extra sumado a la variación del tipo de cambio.</p>';
    }

    public static function render_threshold()
    {
        $opts = get_option('prixy_settings', []);
        $val  = $opts['threshold'] ?? 0.5;
        echo '<input type="number" step="0.1" min="0" name="prixy_settings[threshold]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Variación mínima requerida. Con <strong>0%</strong> siempre actualiza.</p>';
    }

    public static function render_threshold_max()
    {
        $opts = get_option('prixy_settings', []);
        $val  = $opts['threshold_max'] ?? 0;
        echo '<input type="number" step="0.1" min="0" name="prixy_settings[threshold_max]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Freno de seguridad: si supera este valor se bloquea. Con <strong>0%</strong> no hay límite.</p>';
    }

    public static function render_update_direction()
    {
        $opts       = get_option('prixy_settings', []);
        $val        = $opts['update_direction'] ?? 'bidirectional';
        $directions = [
            'bidirectional' => 'Bidireccional (sube y baja)',
            'up_only'       => 'Solo incremento',
            'down_only'     => 'Solo disminución',
        ];
        echo '<select name="prixy_settings[update_direction]" class="regular-text">';
        foreach ($directions as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Dirección en que se actualizan los precios.</p>';
    }

    public static function render_rounding_type()
    {
        $opts  = get_option('prixy_settings', []);
        $val   = $opts['rounding_type'] ?? 'integer';
        $types = [
            'none'    => 'Sin redondeo',
            'integer' => 'Enteros',
            'ceil'    => 'Al mayor (Ceiling)',
            'floor'   => 'Al menor (Floor)',
            'nearest' => 'Al más cercano',
        ];
        echo '<select name="prixy_settings[rounding_type]" class="regular-text">';
        foreach ($types as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Cómo se redondean los decimales del precio.</p>';
    }

    public static function render_nearest_to()
    {
        $opts    = get_option('prixy_settings', []);
        $val     = $opts['nearest_to'] ?? '1';
        $options = ['1' => 'Unidad', '10' => 'Decena', '50' => 'Cincuenta', '100' => 'Centena'];
        echo '<select name="prixy_settings[nearest_to]" class="regular-text">';
        foreach ($options as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">Solo aplica cuando el tipo de redondeo es "Al más cercano".</p>';
    }

    public static function render_exclude_categories()
    {
        $opts       = get_option('prixy_settings', []);
        $selected   = $opts['exclude_categories'] ?? [];
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        if (!empty($categories) && !is_wp_error($categories)) {
            echo '<select name="prixy_settings[exclude_categories][]" multiple="multiple" class="regular-text" style="height:150px;">';
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
        $opts    = get_option('prixy_settings', []);
        $val     = intval($opts['interval'] ?? 3600);
        $hours   = floor($val / 3600);
        $minutes = floor(($val % 3600) / 60);
        echo '<input type="number" name="prixy_settings[interval]" value="' . esc_attr($val) . '" min="300" class="small-text"> segundos';
        echo '<p style="margin-top:4px;">Equivalente a: <strong>' . esc_html($hours) . 'h ' . esc_html($minutes) . 'm</strong></p>';
        echo '<p class="description">Mínimo 300 seg (5 min).</p>';
    }

    // ════════════════════════════════════════════════════════════════════════
    //  RENDERS — Campos propios del Cron
    // ════════════════════════════════════════════════════════════════════════

    public static function render_cron_enabled()
    {
        $opts = get_option('prixy_settings', []);
        $val  = $opts['cron_enabled'] ?? 1;
        echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">';
        echo '<input type="checkbox" name="prixy_settings[cron_enabled]" value="1"' . checked(1, $val, false) . '>';
        echo '<span>Habilitar actualización automática por cron</span>';
        echo '</label>';
        echo '<p class="description">Desactivar pausa el cron sin borrar la configuración.</p>';
    }

    public static function render_cron_api_provider()
    {
        $opts = get_option('prixy_settings', []);
        $val = $opts['cron_api_provider'] ?? '';
        $providers = [
            '' => 'Usar configuración principal',
            'dolarapi' => 'DolarAPI',
            'jsdelivr' => 'Jsdelivr',
            'cryptoprice' => 'CoinGecko',
            'moneyconvert' => 'MoneyConvert',
            'hexarate' => 'HexaRate',
            'foreignrate' => 'ForeignRate',
        ];
        echo '<select name="prixy_settings[cron_api_provider]" class="regular-text">';
        foreach ($providers as $k => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">API específica para el cron. Vacío = usa la API configurada en principal.</p>';
    }

    public static function render_cron_dollar_type()
    {
        $opts = get_option('prixy_settings', []);
        $val = $opts['cron_dollar_type'] ?? '';
        echo '<input type="text" name="prixy_settings[cron_dollar_type]" value="' . esc_attr($val) . '" class="regular-text" placeholder="Ej: DOLAR_OFICIAL">';
        echo '<p class="description">Código de moneda para el cron. Vacío = usa la moneda de referencia principal.</p>';
    }

    public static function render_cron_notify_mode()
    {
        $opts = get_option('prixy_settings', []);
        $val = $opts['cron_notify_mode'] ?? 'update_and_notify';
        $modes = [
            'update_and_notify' => 'Actualizar y notificar',
            'simulate_only' => 'Solo simular (notificar sin actualizar)',
            'disabled' => 'Sin notificaciones',
        ];
        echo '<select name="prixy_settings[cron_notify_mode]" class="regular-text">';
        foreach ($modes as $k => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
    }

    public static function render_cron_notify_email()
    {
        $opts = get_option('prixy_settings', []);
        $val = $opts['cron_notify_email'] ?? get_option('admin_email');
        echo '<input type="email" name="prixy_settings[cron_notify_email]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">Email para recibir notificaciones del cron.</p>';
    }
    
    /**
     * Reprograma el cron cuando se guardan los settings.
     * El reschedule real lo maneja el hook registrado en class-prixy.php via Loader.
     */
    public static function on_settings_updated($old_value, $new_value, $option): void
    {
        // Noop: Cron::schedule() ya está registrado en define_admin_hooks() via Loader.
        // Este método se mantiene como punto de extensión para futuros cambios.
    }
}
