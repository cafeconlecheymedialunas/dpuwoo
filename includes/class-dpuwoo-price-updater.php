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

                // Establecer baseline USD (solo una vez por producto)
                $calculator = Price_Calculator::get_instance();
                $calculator->establish_usd_baseline($pid, $old_regular_price, $previous_dollar_value);

                // Usar cálculo desde baseline USD (ignora precio actual manual)
                $baseline_price = $calculator->calculate_from_usd_baseline($pid, $current_rate);

                // Si el cálculo desde baseline es diferente del cálculo normal, usar baseline
                if ($baseline_price > 0 && abs($baseline_price - $new_regular_price) > 0.01) {
                    $new_regular_price = $baseline_price;
                    $change_data['baseline_used'] = true;
                    $change_data['calculated_from_baseline'] = true;
                }

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

                // En simulación también mostrar si se usaría baseline USD
                $calculator = Price_Calculator::get_instance();
                if ($calculator->has_usd_baseline($pid)) {
                    $baseline_price = $calculator->calculate_from_usd_baseline($pid, $current_rate);
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

    /**
     * Inicia y gestiona la ejecución completa de la actualización por lotes.
     */
    public function update_all_batch($simulate = false, $batch = 0)
    {
        // Use the new baseline manager for reliable baseline retrieval
        error_log('DPUWoo: Attempting to get baseline manager instance');

        if (!class_exists('DPUWOO_Baseline_Manager')) {
            error_log('DPUWoo: Baseline manager class not found');
            return ['error' => 'missing_dependencies', 'message' => 'Baseline manager class not found'];
        }

        try {
            $baseline_manager = DPUWOO_Baseline_Manager::get_instance();
            error_log('DPUWoo: Baseline manager instance obtained');
            $baseline = $baseline_manager->get_current_baseline('dollar');
            error_log('DPUWoo: Current baseline value: ' . var_export($baseline, true));
        } catch (Exception $e) {
            error_log('DPUWoo: Error getting baseline manager: ' . $e->getMessage());
            return ['error' => 'baseline_manager_error', 'message' => 'Error initializing baseline manager: ' . $e->getMessage()];
        }

        $opts = get_option('dpuwoo_settings', []);



        $baseline_manager->auto_setup_baseline();
        $baseline = $baseline_manager->get_current_baseline('dollar');








        // 2. GET CURRENT DOLLAR RATE
        $type = get_option('dpuwoo_settings', [])['dollar_type'] ?? 'oficial';

        if (!class_exists('API_Client')) {
            return ['error' => 'missing_dependencies', 'message' => 'Missing dependent classes (API_Client)'];
        }

        $api_res = API_Client::get_instance()->get_rate($type);

        if ($api_res === false) {
            return ['error' => 'no_rate_available', 'message' => 'Could not obtain current dollar value'];
        }

        $current_rate = floatval($api_res['value']);

        // 3. VERIFY ALL PRODUCTS HAVE BASELINES (ENSURE DATA CONSISTENCY)
        $calculator = Price_Calculator::get_instance();
        $calculator->ensure_missing_baselines($current_rate);

        // 4. GET LAST APPLIED DOLLAR OR USE BASELINE
        $last_applied_dollar = $this->get_last_applied_dollar();

        $previous_dollar_value = $last_applied_dollar > 0 ? $last_applied_dollar : $baseline;

        // 5. CHECK THRESHOLD - if change doesn't meet threshold, don't process
        $threshold = floatval($opts['threshold']);
        $threshold_check = self::check_threshold_met($current_rate, $previous_dollar_value, $threshold);

        // Set the instance property
        $this->set_threshold_met($threshold_check['meets_threshold']);

        if (!$this->is_threshold_met()) {
            return [
                'rate' => $current_rate,
                'baseline_rate' => $baseline,
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
                'baseline_rate' => $baseline, // Siempre devolver el baseline para referencia
                'previous_rate' => $previous_dollar_value, // Valor usado para el cálculo (último aplicado o baseline)
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
            'baseline_rate' => $baseline, // Siempre devolver el baseline
            'previous_rate' => $previous_dollar_value, // Valor usado para el cálculo
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
            error_log('DPUWoo: Auto-configured USD as reference currency during update process');
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
