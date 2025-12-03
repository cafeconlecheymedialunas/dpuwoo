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
    public function process_batch($product_ids, $current_rate, $previous_dollar_value, $simulate = false) {
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
                    'product_name' => 'Producto no encontrado',
                    'product_sku' => 'N/A',
                    'product_type' => 'simple',
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'base_price' => 0,
                    'percentage_change' => 0,
                    'status' => 'error',
                    'reason' => 'Producto no encontrado'
                ];
                $errors_map['producto_no_encontrado'] = ($errors_map['producto_no_encontrado'] ?? 0) + 1;
                continue;
            }

            // Obtener información del producto
            $product_name = $product->get_name();
            $product_sku = $product->get_sku() ?: 'N/A';
            $product_type = $product->get_type();

            if ($product->is_type('variable')) {
                $variable_changes = $this->update_variable_product($product, $current_rate, $previous_dollar_value, $simulate);
                
                // Enriquecer cada variación con datos del producto padre
                foreach ($variable_changes['changes'] as $c) {
                    $enriched_change = array_merge($c, [
                        'product_name' => $product_name . ' - ' . ($c['variation_name'] ?? 'Variación'),
                        'product_sku' => $product_sku,
                        'product_type' => 'variation'
                    ]);
                    $changes[] = $enriched_change;
                }
                
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
            $calc_result = Price_Calculator::get_instance()->calculate_for_product(
                $pid, 
                $current_rate, 
                $previous_dollar_value
            );
            
            if (isset($calc_result['error'])) {
                $reason = $calc_result['error'];
                $changes[] = [
                    'product_id' => $pid,
                    'product_name' => $product_name,
                    'product_sku' => $product_sku,
                    'product_type' => $product_type,
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'base_price' => 0,
                    'percentage_change' => 0,
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
                'product_name' => $product_name,
                'product_sku' => $product_sku,
                'product_type' => $product_type,
                'old_regular_price' => $old_price,
                'new_regular_price' => $new_price,
                'base_price' => $calc_result['base_price'] ?? $old_price,
                'percentage_change' => isset($calc_result['percentage_change']) ? $calc_result['percentage_change'] : null,
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
        // Obtener configuración
        $opts = get_option('dpuwoo_settings', []);
        
        // 1. Obtener dólar base histórico
        $baseline = floatval($opts['baseline_dollar_value'] ?? 0);
        
        if ($baseline <= 0) {
            return ['error' => 'baseline_dollar_missing', 'message' => 'Falta configurar el dólar base histórico'];
        }
        
        // 2. Obtener tipo de dólar y valor actual
        $type = $opts['dollar_type'] ?? 'oficial';
        
        // Asumiendo que API_Client existe
        if (!class_exists('API_Client')) {
            return ['error' => 'missing_dependencies', 'message' => 'Faltan clases dependientes (API_Client)'];
        }
        
        $api_res = API_Client::get_instance()->get_rate($type);
        
        if ($api_res === false) {
            return ['error' => 'no_rate_available', 'message' => 'No se pudo obtener el valor del dólar actual'];
        }
        
        $current_rate = floatval($api_res['value']);
        
        // 3. DETERMINAR previous_dollar_value SEGÚN EL CASO
        if ($simulate) {
            // Para simulación: usar baseline como referencia
            $previous_dollar_value = $baseline;
            $reference_type = 'baseline';
        } else {
            // Para actualización real: usar last_rate (última tasa usada)
            $previous_dollar_value = floatval($opts['last_rate'] ?? $baseline);
            $reference_type = 'last_rate';
        }
        
        // Calcular ratio y porcentaje de cambio
        $ratio = ($previous_dollar_value > 0) ? ($current_rate / $previous_dollar_value) : 1;
        $percentage_change = ($previous_dollar_value > 0) ? (($current_rate - $previous_dollar_value) / $previous_dollar_value * 100) : 0;
        
        // Si el ratio es ~1.0 (dentro de un margen pequeño), no hay cambios significativos
        $margin = 0.0001;
        if (abs($ratio - 1.0) < $margin) {
            // Solo devolver error en el primer batch
            if ($batch === 0) {
                return [
                    'error' => 'insignificant_change',
                    'message' => sprintf(
                        'El cambio es insignificante: Referencia: $%s, Actual: $%s, Ratio: %s',
                        number_format($previous_dollar_value, 2),
                        number_format($current_rate, 2),
                        number_format($ratio, 4)
                    ),
                    'current_rate' => $current_rate,
                    'previous_rate' => $previous_dollar_value,
                    'baseline_rate' => $baseline,
                    'ratio' => $ratio,
                    'percentage_change' => $percentage_change,
                    'reference_type' => $reference_type,
                    'changes' => [],
                    'summary' => [
                        'updated' => 0,
                        'errors' => 0,
                        'skipped' => 0,
                        'simulated' => $simulate
                    ]
                ];
            } else {
                // Para batches posteriores, devolver cambios vacíos
                return [
                    'rate' => $current_rate,
                    'dollar_type' => $type,
                    'previous_rate' => $previous_dollar_value,
                    'baseline_rate' => $baseline,
                    'ratio' => $ratio,
                    'percentage_change' => $percentage_change,
                    'reference_type' => $reference_type,
                    'changes' => [],
                    'run_id' => null,
                    'batch_info' => [
                        'current_batch' => $batch,
                        'total_batches' => 0,
                        'batch_size' => self::BATCH_SIZE,
                        'total_products' => 0,
                        'processed_in_batch' => 0
                    ],
                    'summary' => [
                        'updated' => 0,
                        'errors' => 0,
                        'skipped' => 0,
                        'simulated' => $simulate
                    ]
                ];
            }
        }
        
        // 4. Procesar productos en lote
        $total_products = $this->product_repo->count_all_products();
        $total_batches = ($total_products === 0) ? 0 : (int) ceil($total_products / self::BATCH_SIZE);
        $offset = $batch * self::BATCH_SIZE;
        
        $batch_product_ids = $this->product_repo->get_product_ids_batch(self::BATCH_SIZE, $offset);
        $processed_in_batch = count($batch_product_ids);
        
        // 5. Procesar el batch con ambos valores
        $batch_result = $this->process_batch($batch_product_ids, $current_rate, $previous_dollar_value, $simulate);
        
        $run_id = null;
        
        // 6. Solo loggear al final y si NO es simulación
        if (!$simulate && ($batch >= max(0, $total_batches - 1) || $total_products == 0)) {
            
            // Actualizar last_rate con el valor usado
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
            
            // 1. INICIAR RUN
            $run_id = $this->logger->begin_run_transaction($run_data);
            
            if ($run_id) {
                // 2. INSERTAR ITEMS
                $items_saved = $this->logger->add_items_to_transaction($run_id, $batch_result['changes']);
                
                if ($items_saved) {
                    // ÉXITO: Actualizar run total_products
                    $saved_count = $this->count_saved_items_from_changes($batch_result['changes']);
                    $this->log_repo->update_run($run_id, ['total_products' => intval($saved_count)]);
                    
                    // Llamar a commit (ahora función vacía)
                    $this->logger->commit_run_transaction($run_id);
                } else {
                    // FALLO EN add_items_to_transaction
                    error_log("DPUWOO: FAILURE: Adding items failed for run_id {$run_id}");
                    $run_id = false;
                    $this->logger->rollback_run_transaction();
                }
            } else {
                // FALLO EN begin_run_transaction
                error_log("DPUWOO: CRITICAL FAILURE: Begin run failed.");
            }
        }
        
        return [
            'rate' => $current_rate,
            'dollar_type' => $type,
            'previous_rate' => $previous_dollar_value,
            'baseline_rate' => $baseline,
            'ratio' => $ratio,
            'percentage_change' => $percentage_change,
            'reference_type' => $reference_type,
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

    private function update_variable_product($variable_product, $current_rate, $previous_dollar_value, $simulate = false) {
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
                    'product_name' => 'Variación no encontrada',
                    'product_sku' => 'N/A',
                    'product_type' => 'variation',
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'base_price' => 0,
                    'percentage_change' => 0,
                    'status' => 'error',
                    'reason' => 'Variación no encontrada',
                    'parent_id' => $variable_product->get_id(),
                    'variation_name' => 'Variación'
                ];
                $errors_map['variacion_no_encontrada'] = ($errors_map['variacion_no_encontrada'] ?? 0) + 1;
                continue;
            }

            // Obtener nombre de la variación
            $variation_name = $variation->get_name();
            $parent_name = $variable_product->get_name();
            
            // Quitar el nombre del padre si está incluido
            if (strpos($variation_name, $parent_name) === 0) {
                $variation_name = trim(str_replace($parent_name, '', $variation_name), ' -');
            }

            $calc_result = Price_Calculator::get_instance()->calculate_for_product(
                $variation_id, 
                $current_rate, 
                $previous_dollar_value
            );

            if (isset($calc_result['error'])) {
                $changes[] = [
                    'product_id' => $variation_id,
                    'product_name' => $variable_product->get_name() . ' - ' . $variation_name,
                    'product_sku' => $variation->get_sku() ?: 'N/A',
                    'product_type' => 'variation',
                    'old_regular_price' => 0,
                    'new_regular_price' => 0,
                    'base_price' => 0,
                    'percentage_change' => 0,
                    'status' => 'error',
                    'reason' => $calc_result['error'],
                    'parent_id' => $variable_product->get_id(),
                    'variation_name' => $variation_name
                ];
                $errors_map[$calc_result['error']] = ($errors_map[$calc_result['error']] ?? 0) + 1;
                $error_count++;
                continue;
            }

            $old_price = floatval($calc_result['old_regular']);
            $new_price = floatval($calc_result['new_price']);

            $change_data = [
                'product_id' => $variation_id,
                'product_name' => $variable_product->get_name() . ' - ' . $variation_name,
                'product_sku' => $variation->get_sku() ?: 'N/A',
                'product_type' => 'variation',
                'parent_id' => $variable_product->get_id(),
                'old_regular_price' => $old_price,
                'new_regular_price' => $new_price,
                'base_price' => $calc_result['base_price'] ?? $old_price,
                'percentage_change' => isset($calc_result['percentage_change']) ? $calc_result['percentage_change'] : null,
                'status' => 'pending',
                'variation_name' => $variation_name
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
}