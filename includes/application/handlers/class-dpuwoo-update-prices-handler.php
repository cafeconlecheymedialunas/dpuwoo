<?php
if (!defined('ABSPATH')) exit;

/**
 * Handler del Command UpdatePricesCommand.
 * Orquesta todo el flujo de actualización de precios:
 * obtener tasa → validar threshold → procesar lote → persistir log.
 */
class Update_Prices_Handler
{
    public function __construct(
        private Settings_Repository          $settings,
        private API_Client                   $api,
        private Batch_Processor              $processor,
        private Product_Repository_Interface $product_repo,
        private Logger                       $logger,
        private Threshold_Policy             $threshold_policy,
        private Log_Repository_Interface     $log_repo
    ) {}

    /**
     * Ejecuta el comando de actualización/simulación.
     *
     * @param Update_Prices_Command $cmd
     * @return array Respuesta estructurada para el frontend.
     */
    public function handle(Update_Prices_Command $cmd): array
    {
        // Leer settings efectivos según el contexto: 'manual' usa configuración de la
        // página Ejecución Manual; 'cron' la de Automatización (con fallback a manual).
        $opts = $this->settings->get_for_context($cmd->context);

        // 1. Obtener tasa de cambio actual
        $type    = $opts['dollar_type'] ?? 'oficial';
        $api_res = $this->api->get_rate($type);

        if ($api_res === false) {
            return [
                'error'   => 'no_rate_available',
                'message' => 'No se pudo obtener el valor actual del dólar',
            ];
        }

        $current_rate = floatval($api_res['value']);

        // 2. Obtener tasa de referencia anterior.
        //    Prioridad: último run guardado → origin_exchange_rate (tasa a la que se
        //    cargaron los precios originalmente) → 0 (primera ejecución sin configurar).
        [$previous_rate, $is_first_run] = $this->get_previous_rate($opts);

        // 3. Construir Value Object de tasa de cambio
        $exchange_rate = ($previous_rate > 0)
            ? new Exchange_Rate($current_rate, $previous_rate)
            : Exchange_Rate::first_run($current_rate);

        // 4. Verificar umbrales y dirección (siempre pasa en primera ejecución)
        $threshold_min = floatval($opts['threshold']     ?? 0.5);
        $threshold_max = floatval($opts['threshold_max'] ?? 0);
        $direction     = $opts['update_direction'] ?? 'bidirectional';
        $abs_change    = $exchange_rate->get_abs_percentage_change();

        if (!$this->threshold_policy->should_update(
            $exchange_rate, $threshold_min, $threshold_max, $direction, $is_first_run
        )) {
            // Determinar el motivo específico para el frontend
            if ($direction !== 'bidirectional' && (
                ($direction === 'up_only'   && $exchange_rate->percentage_change <= 0) ||
                ($direction === 'down_only' && $exchange_rate->percentage_change >= 0)
            )) {
                $block_reason = 'Dirección bloqueada: el tipo de cambio se movió en sentido contrario al configurado';
            } elseif ($threshold_max > 0 && $abs_change > $threshold_max) {
                $block_reason = "Variación ({$abs_change}%) supera el umbral máximo ({$threshold_max}%) — freno de seguridad activado";
            } else {
                $block_reason = "Variación ({$abs_change}%) no alcanza el umbral mínimo ({$threshold_min}%)";
            }

            return array_merge($exchange_rate->to_array(), [
                'threshold_met'  => false,
                'threshold_min'  => $threshold_min,
                'threshold_max'  => $threshold_max,
                'direction'      => $direction,
                'message'        => $block_reason,
                'total_batches'  => 0,
                'changes'        => [],
                'summary'        => ['updated' => 0, 'errors' => 0, 'skipped' => 0, 'simulated' => $cmd->simulate],
            ]);
        }

        // 5. Calcular paginación del lote
        $total_products = $this->product_repo->count_all_products();
        $batch_size     = 50;
        $total_batches  = ($total_products === 0) ? 0 : (int) ceil($total_products / $batch_size);
        $offset         = $cmd->batch * $batch_size;

        if ($total_products === 0 || $cmd->batch >= $total_batches) {
            return array_merge($exchange_rate->to_array(), [
                'dollar_type'   => $type,
                'threshold_met' => true,
                'total_batches' => $total_batches,
                'changes'       => [],
                'summary'       => ['updated' => 0, 'errors' => 0, 'skipped' => 0, 'simulated' => $cmd->simulate],
            ]);
        }

        // 6. Obtener IDs del lote y procesar
        $batch_ids    = $this->product_repo->get_product_ids_batch($batch_size, $offset);
        $batch_result = $this->processor->process($batch_ids, $exchange_rate, $opts, $cmd->simulate);

        // 7. Persistir run en el último lote (solo si no es simulación)
        $run_id = null;
        if (!$cmd->simulate && $cmd->batch === ($total_batches - 1)) {
            $run_id = $this->persist_run($type, $current_rate, $exchange_rate, $batch_result, $opts, $total_products, $cmd->context);
        }

        return array_merge($exchange_rate->to_array(), [
            'rate'          => $current_rate,
            'previous_rate' => $previous_rate,
            'is_first_run'  => $is_first_run,
            'dollar_type'   => $type,
            'threshold_met' => true,
            'direction'     => $direction,
            'changes'       => $batch_result->get_changes(),
            'run_id'        => $run_id,
            'batch_info'    => [
                'current_batch'      => $cmd->batch,
                'total_batches'      => $total_batches,
                'processed_in_batch' => count($batch_ids),
                'total_products'     => $total_products,
            ],
            'summary'    => $batch_result->to_summary($cmd->simulate),
            'errors_map' => $batch_result->get_errors_map(),
        ]);
    }

    /*==========================================================
    =           Métodos Privados de Soporte                    =
    ==========================================================*/

    /**
     * Obtiene [tasa_anterior, es_primera_ejecucion].
     *
     * Prioridad:
     *  1. Último run guardado en BD (comparación con el último cambio real).
     *  2. origin_exchange_rate: la tasa a la que se cargaron los precios originalmente.
     *     Permite que la primera ejecución calcule el ratio correcto respecto al origen.
     *  3. 0 → primera ejecución sin configurar (ratio=1, no cambia nada).
     *
     * @return array{float, bool}  [tasa_anterior, es_primera_ejecucion]
     */
    private function get_previous_rate(array $opts): array
    {
        // Logs: ya hay al menos un run real guardado
        $last = $this->log_repo->get_last_applied_rate();
        if ($last > 0) {
            return [$last, false];
        }

        // Primera ejecución: usar origin_exchange_rate como baseline si está configurado
        $origin = floatval($opts['origin_exchange_rate'] ?? 0);
        if ($origin > 0) {
            return [$origin, true];
        }

        // Sin configurar: primera ejecución sin baseline → ratio=1
        return [0.0, true];
    }

    /**
     * Persiste la ejecución en la BD y actualiza la configuración con la última tasa.
     */
    private function persist_run(
        string        $type,
        float         $current_rate,
        Exchange_Rate $exchange_rate,
        Batch_Result  $result,
        array         $opts,
        int           $total_products,
        string        $context = 'manual'
    ): int|false {
        $summary = $result->to_summary(false);

        $run_data = [
            'dollar_type'       => $type,
            'dollar_value'      => $current_rate,
            'rules'             => $opts,
            'total_products'    => $total_products,
            'user_id'           => get_current_user_id(),
            'note'              => $context === 'cron' ? 'Actualización automática (cron)' : 'Actualización manual',
            'percentage_change' => $exchange_rate->percentage_change,
        ];

        $run_id = $this->logger->begin_run_transaction($run_data);

        if (!$run_id) {
            return false;
        }

        $items_saved = $this->logger->add_items_to_transaction($run_id, $result->get_changes());

        if (!$items_saved) {
            $this->logger->rollback_run_transaction();
            return false;
        }

        $this->logger->commit_run_transaction($run_id);

        return $run_id;
    }
}
