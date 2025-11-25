<?php
if (!defined('ABSPATH')) exit;

class Price_Updater
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

    public function update_all($simulate = false)
    {
        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
        
        if ($baseline <= 0) {
            return ['error' => 'baseline_dollar_missing', 'message' => 'Falta configurar el dólar base histórico'];
        }

        $type = $opts['dollar_type'] ?? 'oficial';
        $api_res = API_Client::get_instance()->get_rate($type);
        
        if ($api_res === false) {
            return ['error' => 'no_rate_available', 'message' => 'No se pudo obtener el valor del dólar actual'];
        }

        $current_rate = floatval($api_res['value']);
        
        // Obtener productos simples Y variables
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => ['simple', 'variable'],
                ]
            ]
        ]);

        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;

        foreach ($products as $pid) {
            $product = wc_get_product($pid);
            if (!$product) {
                $error_count++;
                continue;
            }

            // MANEJAR PRODUCTOS VARIABLES
            if ($product->is_type('variable')) {
                $variable_changes = $this->update_variable_product($product, $current_rate, $simulate);
                $changes = array_merge($changes, $variable_changes['changes']);
                $updated_count += $variable_changes['updated'];
                $error_count += $variable_changes['errors'];
                $skipped_count += $variable_changes['skipped'];
                continue;
            }

            // PRODUCTOS SIMPLES
            $calc_result = Price_Calculator::get_instance()->calculate_for_product($pid, $current_rate);
            
            if (isset($calc_result['error'])) {
                $changes[] = [
                    'product_id' => $pid,
                    'product_name' => $product->get_name(),
                    'product_sku' => $product->get_sku(),
                    'product_type' => 'simple',
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'percentage_change' => null,
                    'status' => 'error',
                    'reason' => $calc_result['error']
                ];
                $error_count++;
                continue;
            }

            $old_price = floatval($calc_result['old_regular']);
            $new_price = floatval($calc_result['new_price']);
            $percentage_change = floatval($calc_result['percentage_change']);

            $change_data = [
                'product_id' => $pid,
                'product_name' => $product->get_name(),
                'product_sku' => $product->get_sku(),
                'product_type' => 'simple',
                'old_regular_price' => $old_price,
                'new_regular_price' => $new_price,
                'percentage_change' => $percentage_change,
                'base_price' => $calc_result['base_price'],
                'ratio' => $calc_result['ratio'],
                'status' => 'pending'
            ];

            // Si el precio no cambió, marcar como skipped
            if ($old_price == $new_price) {
                $change_data['status'] = 'skipped';
                $change_data['reason'] = 'No change';
                $skipped_count++;
                $changes[] = $change_data;
                continue;
            }

            $changes[] = $change_data;

            if (!$simulate && $new_price > 0) {
                try {
                    $product->set_regular_price($new_price);
                    $product->save();
                    $change_data['status'] = 'updated';
                    $updated_count++;
                } catch (Exception $e) {
                    $change_data['status'] = 'error';
                    $change_data['reason'] = $e->getMessage();
                    $error_count++;
                }
            } else if ($simulate) {
                $change_data['status'] = 'simulated';
            }
        }

        // Si no es simulación, actualizar el último rate
        if (!$simulate) {
            $opts['last_rate'] = $current_rate;
            update_option('dpuwoo_settings', $opts);

            // Loggear la ejecución
            $run_id = $this->log_run($current_rate, $type, $changes, $simulate);
        }

        return [
            'rate' => $current_rate,
            'dollar_type' => $type,
            'baseline_rate' => $baseline,
            'ratio' => $current_rate / $baseline,
            'changes' => $changes,
            'run_id' => $run_id ?? null,
            'summary' => [
                'total_products' => count($products),
                'updated' => $updated_count,
                'errors' => $error_count,
                'skipped' => $skipped_count,
                'simulated' => $simulate
            ]
        ];
    }

    /**
     * Actualizar producto variable y todas sus variaciones
     */
    private function update_variable_product($variable_product, $current_rate, $simulate = false)
    {
        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;

        // Obtener todas las variaciones
        $variation_ids = $variable_product->get_children();
        
        if (empty($variation_ids)) {
            return [
                'changes' => $changes,
                'updated' => 0,
                'errors' => 0,
                'skipped' => 0
            ];
        }

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                $error_count++;
                continue;
            }

            $calc_result = Price_Calculator::get_instance()->calculate_for_product($variation_id, $current_rate);
            
            if (isset($calc_result['error'])) {
                $changes[] = [
                    'product_id' => $variation_id,
                    'parent_id' => $variable_product->get_id(),
                    'product_name' => $variable_product->get_name() . ' - ' . $this->get_variation_attributes($variation),
                    'product_sku' => $variation->get_sku(),
                    'product_type' => 'variation',
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'percentage_change' => null,
                    'status' => 'error',
                    'reason' => $calc_result['error']
                ];
                $error_count++;
                continue;
            }

            $old_price = floatval($calc_result['old_regular']);
            $new_price = floatval($calc_result['new_price']);
            $percentage_change = floatval($calc_result['percentage_change']);

            $change_data = [
                'product_id' => $variation_id,
                'parent_id' => $variable_product->get_id(),
                'product_name' => $variable_product->get_name() . ' - ' . $this->get_variation_attributes($variation),
                'product_sku' => $variation->get_sku(),
                'product_type' => 'variation',
                'old_regular_price' => $old_price,
                'new_regular_price' => $new_price,
                'percentage_change' => $percentage_change,
                'base_price' => $calc_result['base_price'],
                'ratio' => $calc_result['ratio'],
                'status' => 'pending'
            ];

            // Si el precio no cambió, marcar como skipped
            if ($old_price == $new_price) {
                $change_data['status'] = 'skipped';
                $change_data['reason'] = 'No change';
                $skipped_count++;
                $changes[] = $change_data;
                continue;
            }

            $changes[] = $change_data;

            if (!$simulate && $new_price > 0) {
                try {
                    $variation->set_regular_price($new_price);
                    $variation->save();
                    $change_data['status'] = 'updated';
                    $updated_count++;
                } catch (Exception $e) {
                    $change_data['status'] = 'error';
                    $change_data['reason'] = $e->getMessage();
                    $error_count++;
                }
            } else if ($simulate) {
                $change_data['status'] = 'simulated';
            }
        }

        return [
            'changes' => $changes,
            'updated' => $updated_count,
            'errors' => $error_count,
            'skipped' => $skipped_count
        ];
    }

    /**
     * Obtener atributos de la variación para el nombre
     */
    private function get_variation_attributes($variation)
    {
        $attributes = $variation->get_attributes();
        $attribute_names = [];
        
        foreach ($attributes as $key => $value) {
            $taxonomy = str_replace('attribute_', '', $key);
            $term = get_term_by('slug', $value, $taxonomy);
            if ($term) {
                $attribute_names[] = $term->name;
            } else {
                $attribute_names[] = $value;
            }
        }
        
        return implode(', ', $attribute_names);
    }

    protected function log_run($rate, $type, $changes, $simulate = false)
    {
        $logger = Logger::get_instance();
        
        $run_data = [
            'dollar_type' => $type,
            'dollar_value' => $rate,
            'rules' => get_option('dpuwoo_settings', []),
            'total_products' => count($changes),
            'user_id' => get_current_user_id(),
            'note' => $simulate ? 'Simulación' : 'Actualización automática'
        ];

        $run_id = $logger->create_run($run_data);

        foreach ($changes as $change) {
            // Solo loggear cambios que no sean skipped
            if ($change['status'] !== 'skipped') {
                $logger->insert_run_item($run_id, $change);
            }
        }

        return $run_id;
    }

    public function rollback_item($log_id)
    {
        $logger = Logger::get_instance();
        return $logger->rollback_item($log_id);
    }

    public function rollback_run($run_id)
    {
        $logger = Logger::get_instance();
        return $logger->rollback_run($run_id);
    }
}