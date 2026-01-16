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
            'dpuwoo_baseline_dollar_value',
            'Tasa de Referencia Base',
            [__CLASS__, 'render_baseline_dollar'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_api_provider',
            'Proveedor de API',
            [__CLASS__, 'render_api_provider'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_dollar_type',
            'Tipo de Cotización',
            [__CLASS__, 'render_dollar_type'],
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

        add_settings_field(
            'dpuwoo_psychological_pricing',
            'Precios Psicológicos',
            [__CLASS__, 'render_psychological_pricing'],
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
            'dpuwoo_exclude_on_sale',
            'Excluir Productos en Oferta',
            [__CLASS__, 'render_exclude_on_sale'],
            'dpuwoo_settings',
            'dpuwoo_exclusion_section'
        );

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
        
        echo '<div class="dpuwoo-section-description">';
        echo '<p><strong>Configuración base del sistema de actualización:</strong></p>';
        
        echo '<div class="notice notice-info" style="padding: 10px; margin: 10px 0;">';
        echo '<strong>Moneda base:</strong> ' . esc_html($store_currency) . ' (configurada en WooCommerce)';
        echo '</div>';
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
        $out['baseline_dollar_value'] = floatval($input['baseline_dollar_value'] ?? 0);
        $out['api_provider'] = sanitize_text_field($input['api_provider'] ?? 'dolarsi');
        $out['dollar_type'] = sanitize_text_field($input['dollar_type'] ?? 'oficial');
        
        // Agregar campos de referencia de moneda
        $out['reference_currency'] = sanitize_text_field($input['reference_currency'] ?? 'USD');
        $out['last_rate'] = floatval($input['last_rate'] ?? 0);
        
        // Cálculo y Ajuste
        $out['margin'] = floatval($input['margin'] ?? 0);
        $out['threshold'] = floatval($input['threshold'] ?? 0.5);
        $out['update_direction'] = sanitize_text_field($input['update_direction'] ?? 'bidirectional');
        
        // Reglas de Redondeo
        $out['rounding_type'] = sanitize_text_field($input['rounding_type'] ?? 'integer');
        $out['nearest_to'] = sanitize_text_field($input['nearest_to'] ?? '1');
        $out['psychological_pricing'] = isset($input['psychological_pricing']) ? 1 : 0;
        $out['psychological_ending'] = sanitize_text_field($input['psychological_ending'] ?? '99');
        
        // Automatización
        $out['interval'] = intval($input['interval'] ?? 3600);
        
        // Exclusiones
        $out['exclude_on_sale'] = isset($input['exclude_on_sale']) ? 1 : 0;
        $out['exclude_categories'] = array_map('intval', $input['exclude_categories'] ?? []);

        // Validación principal
        if ($out['baseline_dollar_value'] <= 0) {
            add_settings_error(
                'dpuwoo_settings',
                'baseline_required',
                'Debes ingresar un valor de dólar base histórico para poder usar el plugin',
                'error'
            );
        }

        return $out;
    }

    // ========== FUNCIONES DE RENDER ==========

    public static function render_baseline_dollar()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['baseline_dollar_value'] ?? '';
        $current_rate = get_option('dpuwoo_current_rate', 0);
        $store_currency = get_woocommerce_currency();
        
        echo '<input type="number" step="0.01" min="0.01" name="dpuwoo_settings[baseline_dollar_value]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">';
        echo 'Valor del ' . esc_html($store_currency) . ' al momento de configurar los precios iniciales.<br>';
        
        if ($val > 0 && $current_rate > 0) {
            $variation = (($current_rate - $val) / $val) * 100;
            
            echo '<div style="background: #f5f5f5; padding: 8px; margin: 5px 0; border-radius: 4px;">';
            echo '<strong>Estado actual:</strong><br>';
            echo '• Base: ' . number_format($val, 2) . ' ARS<br>';
            echo '• Actual: ' . number_format($current_rate, 2) . ' ARS<br>';
            echo '• Variación: ' . number_format($variation, 2) . '%';
            echo '</div>';
        }
        echo '</p>';
    }

    public static function render_api_provider()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['api_provider'] ?? 'dolarsi';
        $providers = [
            'dolarsi' => 'DolarSi.com (Recomendado)',
            'bluelytics' => 'Bluelytics',
            'ambito' => 'Ámbito Financiero'
        ];
        
        echo '<select name="dpuwoo_settings[api_provider]" class="regular-text">';
        foreach ($providers as $k => $label) {
            printf('<option value="%s" %s>%s</option>', 
                esc_attr($k), 
                selected($val, $k, false), 
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">Servicio que provee las cotizaciones actualizadas.</p>';
    }

    public static function render_dollar_type()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['dollar_type'] ?? 'oficial';
        $types = [
            'oficial' => 'Oficial', 
            'blue' => 'Blue', 
            'mep' => 'MEP', 
            'ccl' => 'CCL', 
            'promedio' => 'Promedio'
        ];
        
        echo '<select name="dpuwoo_settings[dollar_type]" class="regular-text">';
        foreach ($types as $k => $label) {
            printf('<option value="%s" %s>%s</option>', 
                esc_attr($k), 
                selected($val, $k, false), 
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">Tipo de cambio a usar para los cálculos.</p>';
    }

    public static function render_margin()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['margin'] ?? 0;
        
        echo '<input type="number" step="0.1" name="dpuwoo_settings[margin]" value="' . esc_attr($val) . '" class="small-text"> %';
        echo '<p class="description">Porcentaje extra que se suma a la variación del dólar.</p>';
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
            printf('<option value="%s" %s>%s</option>', 
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
            printf('<option value="%s" %s>%s</option>', 
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
            printf('<option value="%s" %s>%s</option>', 
                esc_attr($k), 
                selected($val, $k, false), 
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">A qué valor se redondea el precio.</p>';
    }

    public static function render_psychological_pricing()
    {
        $opts = get_option('dpuwoo_settings', []);
        $checked = isset($opts['psychological_pricing']) ? $opts['psychological_pricing'] : 0;
        $ending = $opts['psychological_ending'] ?? '99';
        
        echo '<label>';
        echo '<input type="checkbox" name="dpuwoo_settings[psychological_pricing]" value="1" ' . checked(1, $checked, false) . '> ';
        echo 'Activar';
        echo '</label>';
        
        echo '<div style="margin-top: 5px; margin-left: 25px;">';
        echo 'Terminar en: <select name="dpuwoo_settings[psychological_ending]">';
        $endings = ['99' => '.99', '90' => '.90', '95' => '.95'];
        foreach ($endings as $k => $label) {
            printf('<option value="%s" %s>%s</option>', 
                esc_attr($k), 
                selected($ending, $k, false), 
                esc_html($label)
            );
        }
        echo '</select>';
        echo '</div>';
        
        echo '<p class="description">Ajusta precios para que terminen en valores psicológicos.</p>';
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
        echo '<p class="description">Frecuencia de consulta a la API (mínimo: 300 segundos).</p>';
    }

    public static function render_exclude_on_sale()
    {
        $opts = get_option('dpuwoo_settings', []);
        $checked = isset($opts['exclude_on_sale']) ? $opts['exclude_on_sale'] : 0;
        
        echo '<label>';
        echo '<input type="checkbox" name="dpuwoo_settings[exclude_on_sale]" value="1" ' . checked(1, $checked, false) . '> ';
        echo 'No actualizar productos que tienen precio de oferta';
        echo '</label>';
        echo '<p class="description">Mantiene los precios promocionales fijos.</p>';
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
}