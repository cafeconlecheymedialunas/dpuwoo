<?php
if (!defined('ABSPATH')) exit;

/**
 * Controlador AJAX de la capa de Presentación.
 * Reemplaza Ajax_Manager con responsabilidades claras y únicas:
 *  1. Verificar autenticación y nonce.
 *  2. Extraer y sanitizar inputs del request.
 *  3. Construir el Command correspondiente.
 *  4. Despacharlo al Command_Bus.
 *  5. Transformar el resultado en JSON para el frontend.
 *
 * NO contiene lógica de negocio — todo se delega a Application Layer.
 */
class Ajax_Controller
{
    private const NONCE_ACTION = 'dpuwoo_ajax_nonce';
    private const NONCE_FIELD  = 'nonce';

    public function __construct(
        private Command_Bus          $bus,
        private Logger               $logger,
        private API_Client           $api,
        private Settings_Repository  $settings
    ) {}

    /*==========================================================
    =           Handlers de Precio (Update / Simulate)         =
    ==========================================================*/

    /**
     * POST: dpuwoo_simulate_batch
     * Simula la actualización de precios sin persistir cambios.
     */
    public function handle_simulate_batch(): void
    {
        $this->handle_batch(simulate: true);
    }

    /**
     * POST: dpuwoo_update_batch
     * Ejecuta la actualización real de precios para el lote dado.
     */
    public function handle_update_batch(): void
    {
        $this->handle_batch(simulate: false);
    }

    private function handle_batch(bool $simulate): void
    {
        $this->verify_request();

        $batch  = intval($_POST['batch'] ?? 0);
        $result = $this->bus->dispatch(new Update_Prices_Command($batch, simulate: $simulate));

        if (isset($result['error'])) {
            wp_send_json_error($result);
        }

        wp_send_json_success($this->enrich_with_execution_config($result));
    }

    /*==========================================================
    =           Handlers de Logs / Historial                   =
    ==========================================================*/

    /**
     * POST: dpuwoo_get_runs
     * Retorna el historial de ejecuciones.
     */
    public function handle_get_runs(): void
    {
        $this->verify_request();
        wp_send_json_success($this->logger->get_runs());
    }

    /**
     * POST: dpuwoo_get_run_items
     * Retorna los items individuales de una ejecución.
     */
    public function handle_get_run_items(): void
    {
        $this->verify_request();

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) {
            wp_send_json_error(['message' => 'ID de ejecución inválido']);
        }

        wp_send_json_success($this->logger->get_run_items($run_id, 500));
    }

    /*==========================================================
    =           Handlers de Rollback                           =
    ==========================================================*/

    /**
     * POST: dpuwoo_revert_item
     * Revierte el precio de un item individual.
     */
    public function handle_revert_item(): void
    {
        $this->verify_request();

        $log_id = intval($_POST['log_id'] ?? 0);
        if (!$log_id) {
            wp_send_json_error(['message' => 'ID de log inválido']);
        }

        $result = $this->bus->dispatch(new Rollback_Item_Command($log_id));

        if (!$result['success']) {
            wp_send_json_error($result);
        }

        wp_send_json_success(array_merge($result, ['log_id' => $log_id]));
    }

    /**
     * POST: dpuwoo_revert_run
     * Revierte todos los precios de una ejecución completa.
     */
    public function handle_revert_run(): void
    {
        $this->verify_request();

        $run_id = intval($_POST['run_id'] ?? 0);
        if (!$run_id) {
            wp_send_json_error(['message' => 'ID de ejecución inválido']);
        }

        $result = $this->bus->dispatch(new Rollback_Run_Command($run_id));
        wp_send_json_success(array_merge($result, ['run_id' => $run_id]));
    }

    /*==========================================================
    =           Handlers de API / Proveedores                  =
    ==========================================================*/

    /**
     * POST: dpuwoo_get_currencies
     * Retorna las monedas/tipos disponibles del proveedor indicado.
     */
    public function handle_get_currencies(): void
    {
        $this->verify_nonce_only();

        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $data     = $this->api->get_currencies($provider);

        if (!$data) {
            wp_send_json_error([
                'message'  => 'No se pudieron obtener datos del proveedor',
                'provider' => $provider,
            ]);
        }

        wp_send_json_success([
            'currencies' => $data,
            'provider'   => $provider,
            'count'      => count($data),
        ]);
    }

    /**
     * POST: dpuwoo_get_current_rate
     * Retorna la tasa de cambio actual desde el proveedor configurado.
     */
    public function handle_get_current_rate(): void
    {
        $this->verify_nonce_only();

        $type    = sanitize_text_field($_POST['type'] ?? $this->settings->get('dollar_type', 'oficial'));
        $provider = sanitize_text_field($_POST['provider'] ?? '');

        $rate_data = $this->api->get_rate($type, $provider ?: null);

        if (!$rate_data) {
            wp_send_json_error(['message' => 'No se pudo obtener la tasa de cambio', 'type' => $type]);
        }

        wp_send_json_success([
            'rate'     => floatval($rate_data['value'] ?? 0),
            'buy'      => floatval($rate_data['buy']   ?? $rate_data['value'] ?? 0),
            'sell'     => floatval($rate_data['sell']  ?? $rate_data['value'] ?? 0),
            'updated'  => $rate_data['updated']  ?? current_time('mysql'),
            'provider' => $rate_data['provider'] ?? '',
        ]);
    }

    /**
     * POST: dpuwoo_get_providers_info
     * Retorna la lista de proveedores disponibles.
     */
    public function handle_get_providers_info(): void
    {
        $this->verify_nonce_only();

        wp_send_json_success([
            'providers'          => API_Client::get_available_providers(),
            'current_provider'   => $this->settings->get('api_provider', 'dolarapi'),
            'woocommerce_currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
        ]);
    }

    /**
     * POST: dpuwoo_test_api_connection
     * Prueba la conectividad con el proveedor dado.
     */
    public function handle_test_api_connection(): void
    {
        $this->verify_nonce_only();

        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        $result   = $this->api->test_connection($provider);

        if ($result['success'] ?? false) {
            wp_send_json_success(array_merge($result, ['provider' => $provider]));
        } else {
            wp_send_json_error(array_merge($result, ['provider' => $provider]));
        }
    }

    /*==========================================================
    =           Métodos Privados de Seguridad                  =
    ==========================================================*/

    /**
     * Verifica permisos de administrador + nonce.
     * Para operaciones destructivas (actualizar precios, revertir).
     */
    private function verify_request(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos suficientes'], 403);
        }
        check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD);
    }

    /**
     * Verifica solo el nonce (sin require manage_options).
     * Para operaciones de solo lectura (obtener monedas, probar conexión).
     */
    private function verify_nonce_only(): void
    {
        if (!wp_verify_nonce($_POST[self::NONCE_FIELD] ?? '', self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Nonce inválido'], 403);
        }
    }

    /*==========================================================
    =           Helpers de Respuesta                           =
    ==========================================================*/

    /**
     * Agrega metadatos de configuración al resultado para el frontend.
     * Mantiene compatibilidad con el comportamiento del Ajax_Manager anterior.
     */
    private function enrich_with_execution_config(array $result): array
    {
        $result['execution_config'] = [
            'reference_currency' => $this->settings->get('reference_currency', 'USD'),
            'api_provider'       => $this->settings->get('api_provider',       'dolarapi'),
            'margin'             => floatval($this->settings->get('margin',     0)),
            'threshold'          => floatval($this->settings->get('threshold',  0.5)),
            'update_direction'   => $this->settings->get('update_direction',   'bidirectional'),
            'rounding_type'      => $this->settings->get('rounding_type',      'integer'),
        ];

        return $result;
    }
}
