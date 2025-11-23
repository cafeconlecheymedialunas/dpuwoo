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

        add_settings_section(
            'dpuwoo_main_section',
            'Main settings',
            null,
            'dpuwoo_settings'
        );

        add_settings_field(
            'dpuwoo_api_key',
            'API Key',
            [__CLASS__, 'render_api_key'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_dollar_type',
            'Dollar Type',
            [__CLASS__, 'render_dollar_type'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_update_interval',
            'Update Interval',
            [__CLASS__, 'render_interval'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_threshold',
            'Threshold %',
            [__CLASS__, 'render_threshold'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );

        add_settings_field(
            'dpuwoo_baseline_dollar_value',
            'Dólar Base (histórico)',
            [__CLASS__, 'render_baseline_dollar'],
            'dpuwoo_settings',
            'dpuwoo_main_section'
        );
    }

    public static function sanitize($input)
    {
        $out = [];
        $out['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $out['dollar_type'] = sanitize_text_field($input['dollar_type'] ?? 'oficial');
        $out['interval'] = intval($input['interval'] ?? 3600);
        $out['threshold'] = floatval($input['threshold'] ?? 0);
        $out['baseline_dollar_value'] = floatval($input['baseline_dollar_value'] ?? 0);

        if ($out['baseline_dollar_value'] <= 0) {
            add_settings_error(
                'dpuwoo_settings',
                'baseline_required',
                'Debes ingresar un valor de dólar base histórico para poder usar el plugin',
                'error'
            );
            $out['baseline_dollar_value'] = 0;
        }

        return $out;
    }

    public static function render_api_key()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['api_key'] ?? '';
        echo "<input type=\"text\" name=\"dpuwoo_settings[api_key]\" value=\"" . esc_attr($val) . "\" class=\"regular-text\">";
    }

    public static function render_dollar_type()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['dollar_type'] ?? 'oficial';
        $types = ['oficial' => 'Oficial', 'blue' => 'Blue', 'mep' => 'MEP', 'ccl' => 'CCL', 'promedio' => 'Promedio'];
        echo '<select name="dpuwoo_settings[dollar_type]">';
        foreach ($types as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($val, $k, false), esc_html($label));
        }
        echo '</select>';
    }

    public static function render_interval()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['interval'] ?? 3600;
        echo '<input type="number" name="dpuwoo_settings[interval]" value="' . esc_attr($val) . '" min="60"> segundos';
    }

    public static function render_threshold()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['threshold'] ?? 0;
        echo '<input type="number" step="0.1" name="dpuwoo_settings[threshold]" value="' . esc_attr($val) . '"> %';
    }

    public static function render_baseline_dollar()
    {
        $opts = get_option('dpuwoo_settings', []);
        $val = $opts['baseline_dollar_value'] ?? '';
        echo '<input type="number" step="0.01" name="dpuwoo_settings[baseline_dollar_value]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">Valor del dólar al momento de instalar el plugin. Requerido para calcular variaciones correctamente.</p>';
    }
}
