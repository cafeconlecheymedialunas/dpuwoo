<?php
if (!defined('ABSPATH')) exit;

class Price_Updater {
    protected static $instance;

    const BATCH_SIZE = 50; // puedes ajustar

    protected $log_repo; // runs/items repo
    protected $product_repo; // product repo
    protected $logger;

    public static function init() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public static function get_instance() {
        return self::init();
    }

    private function __construct()
    {
        $this->log_repo = Log_Repository::get_instance();
        $this->product_repo = Product_Repository::get_instance();
        $this->logger = Logger::get_instance();
    }

    /**
     * Procesar un array de IDs (batch) — internamente compatible con simples y variables
     */
    public function process_batch($product_ids, $current_rate, $simulate = false) {
        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $errors_map = [];

        foreach ($product_ids as $pid) {
            $product = $this->product_repo->get_product($pid);
            if (!$product) {
                $error_count++;
                $changes[] = [
                    'product_id' => $pid,
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'status' => 'error',
                    'reason' => 'Producto no encontrado'
                ];
                $errors_map['producto_no_encontrado'] = ($errors_map['producto_no_encontrado'] ?? 0) + 1;
                continue;
            }

            if ($product->is_type('variable')) {
                $variable_changes = $this->update_variable_product($product, $current_rate, $simulate);
                foreach ($variable_changes['changes'] as $c) $changes[] = $c;
                $updated_count += $variable_changes['updated'];
                $error_count += $variable_changes['errors'];
                $skipped_count += $variable_changes['skipped'];
                if (!empty($variable_changes['errors_map'])) {
                    foreach ($variable_changes['errors_map'] as $k => $v) {
                        $errors_map[$k] = ($errors_map[$k] ?? 0) + $v;
                    }
                }
                continue;
            }

            // Producto simple
            // Asumiendo que Price_Calculator existe y funciona
            $calc_result = Price_Calculator::get_instance()->calculate_for_product($pid, $current_rate);
            if (isset($calc_result['error'])) {
                $reason = $calc_result['error'];
                $changes[] = [
                    'product_id' => $pid,
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'status' => 'error',
                    'reason' => $reason
                ];
                $errors_map[$reason] = ($errors_map[$reason] ?? 0) + 1;
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
                $saved_ok = $this->product_repo->save_regular_price($product, $new_price);
                if ($saved_ok) {
                    $change_data['status'] = 'updated';
                    $updated_count++;
                } else {
                    $change_data['status'] = 'error';
                    $change_data['reason'] = 'WooCommerce no guardó el precio (hook conflict / exception)';
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
            'skipped' => $skipped_count,
            'errors_map' => $errors_map
        ];
    }

    /**
     * Update all in batches (now fetch IDs paginated from product_repo)
     */
    public function update_all_batch($simulate = false, $batch = 0) {
        // ... (Configuración y obtención de la tasa de cambio)

        $opts = get_option('dpuwoo_settings', []);
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);

        if ($baseline <= 0 && !empty($opts['last_rate'])) {
            return ['error' => 'baseline_dollar_missing', 'message' => 'Falta configurar el dólar base histórico'];
        }

        $type = $opts['dollar_type'] ?? 'oficial';
        // Asumiendo que API_Client existe y funciona
        if (!class_exists('API_Client') || !class_exists('Price_Calculator')) {
             return ['error' => 'missing_dependencies', 'message' => 'Faltan clases dependientes (API_Client/Price_Calculator)'];
        }

        $api_res = API_Client::get_instance()->get_rate($type);

        if ($api_res === false) {
            return ['error' => 'no_rate_available', 'message' => 'No se pudo obtener el valor del dólar actual'];
        }

        $current_rate = floatval($api_res['value']);

        $total_products = $this->product_repo->count_all_products();
        $total_batches = ($total_products === 0) ? 0 : (int) ceil($total_products / self::BATCH_SIZE);
        $offset = $batch * self::BATCH_SIZE;

        $batch_product_ids = $this->product_repo->get_product_ids_batch(self::BATCH_SIZE, $offset);
        $processed_in_batch = count($batch_product_ids);

        $batch_result = $this->process_batch($batch_product_ids, $current_rate, $simulate);

        $percentage_change = $this->calculate_global_percentage_change($current_rate, $baseline);

        $run_id = null;

        // Solo loggear al final y si NO es simulación
        if (!$simulate && ($batch >= max(0, $total_batches - 1) || $total_products == 0)) {
            
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

            // 1. INICIAR RUN (ahora sin transacción explícita)
            $run_id = $this->logger->begin_run_transaction($run_data);

            if ($run_id) {
                // 2. INSERTAR ITEMS
                $items_saved = $this->logger->add_items_to_transaction($run_id, $batch_result['changes']);
                
                // NOTA: Ya no hay COMMIT ni ROLLBACK explícito.
                // Si items_saved falla, se asume que las inserciones anteriores (run) persisten.
                
                if ($items_saved) {
                    // ÉXITO: Actualizar run total_products
                    $saved_count = $this->count_saved_items_from_changes($batch_result['changes']);
                    $this->log_repo->update_run($run_id, ['total_products' => intval($saved_count)]);
                    
                    // Llamamos a commit_run_transaction (ahora una función vacía) para mantener la estructura de llamadas
                    $this->logger->commit_run_transaction($run_id);

                } else {
                    // FALLO EN add_items_to_transaction
                    error_log("DPUWOO: FAILURE: Adding items failed for run_id {$run_id}. (Autocommit Mode)");
                    $run_id = false;
                    
                    // Llamamos a rollback_run_transaction (ahora una función vacía)
                    $this->logger->rollback_run_transaction();
                }

            } else {
                // FALLO EN begin_run_transaction
                error_log("DPUWOO: CRITICAL FAILURE: Begin run failed. (Autocommit Mode)");
            }
        }

        // ... (Return del resultado)
        return [
            'rate' => $current_rate,
            'dollar_type' => $type,
            'baseline_rate' => $baseline,
            'ratio' => ($baseline > 0) ? ($current_rate / $baseline) : null,
            'percentage_change' => $percentage_change,
            'changes' => $batch_result['changes'],
            'run_id' => $run_id,
            'batch_info' => [
                'current_batch' => $batch,
                'total_batches' => $total_batches,
                'batch_size' => self::BATCH_SIZE,
                'total_products' => $total_products,
                'processed_in_batch' => $processed_in_batch
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

    private function calculate_global_percentage_change($current_rate, $baseline_rate) {
        if ($baseline_rate <= 0) return 0;

        $change = (($current_rate - $baseline_rate) / $baseline_rate) * 100;
        return round($change, 2);
    }

    private function update_variable_product($variable_product, $current_rate, $simulate = false) {
        $changes = [];
        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $errors_map = [];

        $variation_ids = $variable_product->get_children();
        if (empty($variation_ids)) {
            return [
                'changes' => $changes,
                'updated' => 0,
                'errors' => 0,
                'skipped' => 0,
                'errors_map' => []
            ];
        }

        foreach ($variation_ids as $variation_id) {
            $variation = $this->product_repo->get_variation_product($variation_id);
            if (!$variation) {
                $error_count++;
                $changes[] = [
                    'product_id' => $variation_id,
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'status' => 'error',
                    'reason' => 'Variación no encontrada'
                ];
                $errors_map['variacion_no_encontrada'] = ($errors_map['variacion_no_encontrada'] ?? 0) + 1;
                continue;
            }

            // Asumiendo que Price_Calculator existe y funciona
            $calc_result = Price_Calculator::get_instance()->calculate_for_product($variation_id, $current_rate);

            if (isset($calc_result['error'])) {
                $changes[] = [
                    'product_id' => $variation_id,
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'status' => 'error',
                    'reason' => $calc_result['error']
                ];
                $errors_map[$calc_result['error']] = ($errors_map[$calc_result['error']] ?? 0) + 1;
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
                $saved_ok = $this->product_repo->save_regular_price($variation, $new_price);
                if ($saved_ok) {
                    $change_data['status'] = 'updated';
                    $updated_count++;
                } else {
                    $change_data['status'] = 'error';
                    $change_data['reason'] = 'WooCommerce no guardó el precio (hook conflict / exception)';
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
            'skipped' => $skipped_count,
            'errors_map' => $errors_map
        ];
    }

    private function count_saved_items_from_changes($changes)
    {
        $count = 0;
        foreach ($changes as $ch) {
            if (in_array($ch['status'], ['updated', 'error'])) $count++;
        }
        return $count;
    }

    public function rollback_item($log_id) {
        return Logger::get_instance()->rollback_item($log_id);
    }

    public function rollback_run($run_id) {
        return Logger::get_instance()->rollback_run($run_id);
    }
}