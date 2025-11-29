<?php
if (!defined('ABSPATH')) exit;

class Price_Updater {
    protected static $instance;
    
    const BATCH_SIZE = 50;

    public static function init() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public static function get_instance() {
        return self::init();
    }

    /**
     * Obtener todos los IDs de productos
     */
    public function get_all_product_ids() {
        return get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => ['simple', 'variable'],
                ]
            ]
        ]);
    }

    /**
     * Procesar un lote de productos
     */
    public function process_batch($product_ids, $current_rate, $simulate = false) {
        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;

        foreach ($product_ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) {
                $error_count++;
                continue;
            }

            if ($product->is_type('variable')) {
                $variable_changes = $this->update_variable_product($product, $current_rate, $simulate);
                $changes = array_merge($changes, $variable_changes['changes']);
                $updated_count += $variable_changes['updated'];
                $error_count += $variable_changes['errors'];
                $skipped_count += $variable_changes['skipped'];
                continue;
            }

            // Producto simple
            $calc_result = Price_Calculator::get_instance()->calculate_for_product($pid, $current_rate);
            if (isset($calc_result['error'])) {
                $changes[] = [
                    'product_id' => $pid,
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'status' => 'error',
                    'reason' => $calc_result['error']
                ];
                $error_count++;
                continue;
            }

            $old_price = floatval($calc_result['old_regular']);
            $new_price = floatval($calc_result['new_price']);

            $change_data = [
                'product_id' => $pid,
                'old_regular_price' => $old_price,
                'new_regular_price' => $new_price,
                'status' => 'pending'
            ];

            if ($old_price == $new_price) {
                $change_data['status'] = 'skipped';
                $change_data['reason'] = 'Sin cambios';
                $skipped_count++;
                $changes[] = $change_data;
                continue;
            }

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

            $changes[] = $change_data;
        }

        return [
            'changes' => $changes,
            'updated' => $updated_count,
            'errors' => $error_count,
            'skipped' => $skipped_count
        ];
    }

    /**
     * Batch con simulación primero
     */
    public function update_all_batch($simulate = false, $batch = 0) {
        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
        
        if ($baseline <= 0 && !empty($opts['last_rate'])) {
            return ['error' => 'baseline_dollar_missing', 'message' => 'Falta configurar el dólar base histórico'];
        }

        $type = $opts['dollar_type'] ?? 'oficial';
        $api_res = API_Client::get_instance()->get_rate($type);
        
        if ($api_res === false) {
            return ['error' => 'no_rate_available', 'message' => 'No se pudo obtener el valor del dólar actual'];
        }

        $current_rate = floatval($api_res['value']);
        $all_product_ids = $this->get_all_product_ids();
        $total_products = count($all_product_ids);

        $offset = $batch * self::BATCH_SIZE;
        $batch_product_ids = array_slice($all_product_ids, $offset, self::BATCH_SIZE);
        $total_batches = ceil($total_products / self::BATCH_SIZE);

        // Procesar lote
        $batch_result = $this->process_batch($batch_product_ids, $current_rate, $simulate);

        // Calcular percentage_change global para el run
        $percentage_change = $this->calculate_global_percentage_change($current_rate, $baseline);

        // Solo loggear al final y si NO es simulación
        if (!$simulate && ($batch >= $total_batches - 1 || $total_products == 0)) {
            $opts['last_rate'] = $current_rate;
            update_option('dpuwoo_settings', $opts);

            $run_id = $this->log_run($current_rate, $type, $batch_result['changes'], $percentage_change);
        }

        return [
            'rate' => $current_rate,
            'dollar_type' => $type,
            'baseline_rate' => $baseline,
            'ratio' => $current_rate / $baseline,
            'percentage_change' => $percentage_change, // ← Incluir en respuesta
            'changes' => $batch_result['changes'],
            'run_id' => $run_id ?? null,
            'batch_info' => [
                'current_batch' => $batch,
                'total_batches' => $total_batches,
                'batch_size' => self::BATCH_SIZE,
                'total_products' => $total_products,
                'processed_in_batch' => count($batch_product_ids)
            ],
            'summary' => [
                'updated' => $batch_result['updated'],
                'errors' => $batch_result['errors'],
                'skipped' => $batch_result['skipped'],
                'simulated' => $simulate
            ]
        ];
    }

    /**
     * Calcular percentage_change global basado en tasa actual vs baseline
     */
    private function calculate_global_percentage_change($current_rate, $baseline_rate) {
        if ($baseline_rate <= 0) return 0;
        
        $change = (($current_rate - $baseline_rate) / $baseline_rate) * 100;
        return round($change, 2);
    }

    /**
     * Compatibilidad
     */
    public function update_all($simulate = false) {
        return $this->update_all_batch($simulate, 0);
    }

    /**
     * Variaciones
     */
    private function update_variable_product($variable_product, $current_rate, $simulate = false) {
        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;

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
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'status' => 'error',
                    'reason' => $calc_result['error']
                ];
                $error_count++;
                continue;
            }

            $old_price = floatval($calc_result['old_regular']);
            $new_price = floatval($calc_result['new_price']);

            $change_data = [
                'product_id' => $variation_id,
                'old_regular_price' => $old_price,
                'new_regular_price' => $new_price,
                'status' => 'pending'
            ];

            if ($old_price == $new_price) {
                $change_data['status'] = 'skipped';
                $change_data['reason'] = 'Sin cambios';
                $skipped_count++;
                $changes[] = $change_data;
                continue;
            }

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

            $changes[] = $change_data;
        }

        return [
            'changes' => $changes,
            'updated' => $updated_count,
            'errors' => $error_count,
            'skipped' => $skipped_count
        ];
    }

    /**
     * Log real (sin simulaciones) con percentage_change global
     */
    protected function log_run($rate, $type, $changes, $percentage_change) {
        $logger = Logger::get_instance();

        // Contar solo los cambios que se van a guardar (updated y error)
        $changes_to_save = array_filter($changes, function($change) {
            return in_array($change['status'], ['updated', 'error']);
        });

        $run_data = [
            'dollar_type' => $type,
            'dollar_value' => $rate,
            'rules' => get_option('dpuwoo_settings', []),
            'total_products' => count($changes_to_save), // Solo los que se guardarán
            'user_id' => get_current_user_id(),
            'note' => 'Actualización automática',
            'percentage_change' => $percentage_change
        ];

        // Iniciar transacción
        $run_id = $logger->begin_run_transaction($run_data);
        
        if (!$run_id) {
            error_log("DPUWOO: Failed to begin transaction");
            return false;
        }

        // Guardar items en transacción
        $success = $logger->add_items_to_transaction($run_id, $changes);
        
        if (!$success) {
            $logger->rollback_run_transaction();
            error_log("DPUWOO: Failed to add items to transaction");
            return false;
        }

        // Confirmar transacción
        $result = $logger->commit_run_transaction($run_id);
        
        if (!$result) {
            error_log("DPUWOO: Failed to commit transaction");
            return false;
        }

        error_log("DPUWOO: Run created with ID: " . $run_id . ", items saved: " . count($changes_to_save));
        return $run_id;
    }

    public function rollback_item($log_id) {
        return Logger::get_instance()->rollback_item($log_id);
    }

    public function rollback_run($run_id) {
        return Logger::get_instance()->rollback_run($run_id);
    }
}