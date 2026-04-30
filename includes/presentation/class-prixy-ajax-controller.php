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
        private Settings_Repository  $settings,
        private Log_Repository       $log_repo
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
        $run_id = intval($_POST['run_id'] ?? 0);
        $result = $this->bus->dispatch(new Update_Prices_Command($batch, simulate: $simulate, run_id: $run_id, context: 'manual'));

        if (isset($result['error'])) {
            wp_send_json_error($result);
        }

        wp_send_json_success($this->enrich_with_execution_config($result));
    }

    /*==========================================================
    =           Handlers de Logs / Historial                   =
    ==========================================================*/

    /**
     * POST: prixy_get_runs
     * Retorna el historial de ejecuciones.
     */
    public function handle_get_runs(): void
    {
        $this->verify_request();
        wp_send_json_success($this->logger->get_runs());
    }

    /**
     * POST: prixy_get_run_items
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
    =           Handlers de Baseline                             =
    ==========================================================*/

    /**
     * POST: dpuwoo_initialize_baseline
     * Obtiene la tasa actual y la persiste como origin_exchange_rate.
     * Lo que antes hacía Price_Updater::initialize_from_current_rate().
     */
    public function handle_initialize_baseline(): void
    {
        $this->verify_nonce_only();

        $currency = $this->settings->get('currency', 'oficial');
        $rate_data = $this->api->get_rate($currency);

        if (!$rate_data || empty($rate_data['value'])) {
            wp_send_json_error(['message' => 'No se pudo obtener la tasa de cambio']);
        }

        $value = floatval($rate_data['value']);

        if ($value <= 0) {
            wp_send_json_error(['message' => 'Tasa inválida: el valor debe ser mayor a 0']);
        }

        $this->settings->set('origin_exchange_rate', $value);

        wp_send_json_success([
            'formatted_value' => number_format($value, 4),
            'value'          => $value,
            'currency'      => $currency,
            'provider'       => $rate_data['provider'] ?? '',
        ]);
    }

    /*==========================================================
    =           Handlers de API / Proveedores                  =
    ==========================================================*/

    /**
     * POST: prixy_get_currencies
     * Retorna las monedas/tipos disponibles del proveedor indicado.
     */
    public function handle_get_currencies(): void
    {
        $this->verify_nonce_only();

        $provider = sanitize_text_field($_POST['provider'] ?? 'dolarapi');
        
        try {
            $data = $this->api->get_currencies($provider);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message'  => 'Error al obtener monedas: ' . $e->getMessage(),
                'provider' => $provider,
            ]);
            return;
        }

        if (empty($data)) {
            wp_send_json_error([
                'message'  => 'No se pudieron obtener datos del proveedor',
                'provider' => $provider,
                'debug'    => [
                    'store_country' => $this->api->get_store_country(),
                    'store_currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'unknown',
                ]
            ]);
            return;
        }

        wp_send_json_success([
            'currencies' => $data,
            'provider'   => $provider,
            'count'      => count($data),
        ]);
    }

    /**
     * POST: prixy_get_current_rate
     * Retorna la tasa de cambio actual desde el proveedor configurado.
     * Si no hay provider configurado, usa jsdelivr (gratuito).
     */
    public function handle_get_current_rate(): void
    {
        $this->verify_nonce_only();

        $currency = sanitize_text_field($_POST['currency'] ?? $this->settings->get('currency', 'oficial'));
        $provider = sanitize_text_field($_POST['provider'] ?? '');

        // Si no hay provider configurado, usar jsdelivr directamente
        $configured_provider = $this->settings->get('api_provider', '');
        
        if (empty($configured_provider)) {
            // Primera vez - usar jsdelivr que es gratuito
            $provider = 'jsdelivr';
            $currency = 'ars'; // ARS para Argentina por defecto
        } elseif (empty($provider)) {
            $provider = $configured_provider;
        }

        $rate_data = $this->api->get_rate($currency, $provider);
        
        // Si falla, intentar providers gratuitos alternativos
        if (!$rate_data || empty($rate_data['value']) || $rate_data['value'] <= 0) {
            $fallbacks = ['jsdelivr', 'moneyconvert', 'hexarate', 'foreignrate'];
            
            foreach ($fallbacks as $fb) {
                if ($fb === $provider) continue;
                
                $rate_data = $this->api->get_rate('ars', $fb);
                if ($rate_data && !empty($rate_data['value']) && $rate_data['value'] > 0) {
                    $provider = $fb;
                    break;
                }
            }
        }

        if (!$rate_data || empty($rate_data['value']) || $rate_data['value'] <= 0) {
            wp_send_json_error(['message' => 'No se pudo obtener la tasa de cambio. Configurá un provider.', 'currency' => $currency]);
            return;
        }

        // Guardar provider y currency seleccionados
        $this->settings->set('api_provider', $provider);
        $this->settings->set('currency', $currency);

        wp_send_json_success([
            'rate'     => floatval($rate_data['value'] ?? 0),
            'buy'      => floatval($rate_data['buy']   ?? $rate_data['value'] ?? 0),
            'sell'     => floatval($rate_data['sell']  ?? $rate_data['value'] ?? 0),
            'updated'  => $rate_data['updated']  ?? current_time('mysql'),
            'provider' => $rate_data['provider'] ?? $provider,
        ]);
    }

    /**
     * POST: prixy_get_providers_info
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

    /**
     * POST: dpuwoo_test_api
     * Prueba la conexión con una API específica usando la API key proporcionada.
     */
    public function handle_test_api(): void
    {
        $this->verify_nonce_only();

        $api_type = sanitize_text_field($_POST['api'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        $result = $this->api->test_specific_api($api_type, $api_key);

        if ($result['success'] ?? false) {
            wp_send_json_success(['message' => $result['message'] ?? 'Conexión exitosa']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Error de conexión']);
        }
    }

    /**
     * POST: prixy_get_dashboard_stats
     * Retorna todos los datos necesarios para el Dashboard Overview en un solo call.
     */
    public function handle_get_dashboard_stats(): void
    {
        $this->verify_nonce_only();

        $settings   = $this->settings->get_all();
        $last_runs  = $this->log_repo->get_runs(5);
        $chart_runs = $this->log_repo->get_runs_for_chart(30);
        $stats      = $this->log_repo->get_aggregate_stats();
        $next_cron  = Cron::get_next_scheduled_time();

        wp_send_json_success([
            'last_runs'    => $last_runs,
            'chart_runs'   => $chart_runs,
            'stats'        => $stats,
            'next_cron'    => $next_cron,
            'cron_enabled' => (bool) ($settings['cron_enabled'] ?? false),
            'api_provider' => $settings['api_provider'] ?? 'dolarapi',
        ]);
    }

    /**
     * POST: prixy_get_setup_progress
     * Retorna el estado de los 2 pasos de onboarding.
     */
    public function handle_get_setup_progress(): void
    {
        $this->verify_nonce_only();
        wp_send_json_success(Admin::get_setup_progress());
    }

    /**
     * POST: dpuwoo_save_origin_rate
     * Guarda la tasa de referencia inicial y procesa todos los productos.
     */
    public function handle_save_origin_rate(): void
    {
        $this->verify_request();

        $value = floatval($_POST['value'] ?? 0);
        if ($value <= 0) {
            wp_send_json_error(['message' => 'La tasa debe ser mayor a 0']);
        }

        // Guardar tasa y bloquear ANTES de procesar (bypass cache)
        $all_settings = (array) get_option('dpuwoo_settings', []);
        $all_settings['origin_exchange_rate'] = $value;
        $all_settings['origin_rate_locked'] = '1';
        update_option('dpuwoo_settings', $all_settings);
        wp_cache_delete('dpuwoo_settings', 'options');

        // Procesar todos los productos (si falla, igual la tasa queda guardada)
        $count = 0;
        $details = [];
        try {
            $result = $this->process_first_setup_products($value);
            $count = $result['count'];
            $details = $result['details'];
        } catch (\Throwable $e) {
            error_log('DPUWoo: Error en process_first_setup_products: ' . $e->getMessage());
        }

        // Devolver URL de redirección a configuración
        wp_send_json_success([
            'value' => $value,
            'processed' => $count,
            'products' => $details,
            'redirect' => admin_url('admin.php?page=dpuwoo_configuration'),
        ]);
    }
    
    /**
     * Procesa todos los productos para el setup inicial (crea logs, no calcula USD).
     */
    private function process_first_setup_products($rate): array
    {
        global $wpdb;
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $product_ids = get_posts($args);
        $processed = [];
        $count = 0;

        $run_data = [
            'currency' => 'USD',
            'dollar_value' => $rate,
            'total_products' => 0,
            'percentage_change' => null,
            'context' => 'setup',
            'note' => 'Setup inicial - Registro de precios base'
        ];
        $run_id = $this->log_repo->insert_run($run_data);
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            
            if ($regular_price > 0) {
                $usd = round($regular_price / $rate, 2);
                $item = [
                    'product_id' => $product_id,
                    'status' => 'updated',
                    'old_regular_price' => 0,
                    'new_regular_price' => $regular_price,
                    'old_sale_price' => 0,
                    'new_sale_price' => $sale_price ?: null,
                    'percentage_change' => null,
                    'reason' => 'Setup inicial'
                ];
                $item_id = $this->log_repo->insert_run_item($run_id, $item);
                
                if ($item_id) {
                    $processed[] = [
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'ars' => number_format($regular_price, 2),
                        'usd' => number_format($usd, 2),
                    ];
                    $count++;
                }
            }
        }

        $this->log_repo->update_run($run_id, ['total_products' => $count]);
        
        return ['count' => $count, 'details' => $processed];
    }

    /**
     * POST: prixy_get_rates
     * Obtiene las tasas de cambio disponibles desde la API.
     */
    public function handle_get_rates(): void
    {
        $this->verify_nonce_only();

        $currency = sanitize_text_field($_POST['currency'] ?? 'USD');
        $api_type = sanitize_text_field($_POST['api'] ?? 'dolarapi');

        $result = $this->api->get_rates($api_type, $currency);

        if ($result['success'] ?? false) {
            wp_send_json_success(['rates' => $result['rates'] ?? [], 'base' => $result['base'] ?? $currency]);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Error al obtener tasas']);
        }
    }

    /**
     * POST: dpuwoo_preview_products
     * Devuelve los primeros 10 productos para vista previa con ARS y USD calculado.
     */
    public function handle_preview_products(): void
    {
        $this->verify_nonce_only();

        $rate = floatval($_POST['rate'] ?? 0);
        if ($rate <= 0) {
            wp_send_json_error(['message' => 'Tasa inválida'], 400);
        }

        $products = wc_get_products([
            'limit' => 10,
            'status' => 'publish',
            'return' => 'objects',
        ]);

        $preview = [];
        foreach ($products as $product) {
            $regular_price = floatval($product->get_regular_price());
            if ($regular_price > 0) {
                $preview[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'ars' => number_format($regular_price, 2),
                    'usd' => number_format(round($regular_price / $rate, 2), 2),
                ];
            }
        }

        wp_send_json_success(['products' => $preview]);
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
            'currency'           => $this->settings->get('currency', 'oficial'),
            'reference_currency' => $this->settings->get('reference_currency', 'USD'),
            'api_provider'       => $this->settings->get('api_provider',       'dolarapi'),
            'margin'             => floatval($this->settings->get('margin',     0)),
            'threshold'          => floatval($this->settings->get('threshold',  0.5)),
            'update_direction'    => $this->settings->get('update_direction',   'bidirectional'),
            'rounding_type'      => $this->settings->get('rounding_type',      'integer'),
        ];

        return $result;
    }

    /*==========================================================
    =           First Setup Handler                            =
    ==========================================================*/

    /**
     * Procesa un lote de productos para el primer setup (crea logs, no meta USD).
     */
    public function handle_first_setup_batch(): void
    {
        $this->verify_nonce_only();

        $offset = intval($_POST['offset'] ?? 0);
        $limit = intval($_POST['limit'] ?? 10);
        $rate = floatval($_POST['rate'] ?? 0);

        if ($rate <= 0) {
            wp_send_json_error(['message' => 'Tasa inválida'], 400);
        }

        // Run_id persistente entre requests AJAX (static se pierde entre procesos)
        $run_id = get_option('dpuwoo_setup_run_id', 0);

        $products = wc_get_products([
            'limit' => $limit,
            'offset' => $offset,
            'status' => 'publish',
            'return' => 'objects',
        ]);

        if (count($products) === 0) {
            wp_send_json_success([
                'products' => [],
                'offset' => $offset,
                'limit' => $limit,
                'rate' => $rate,
                'done' => true,
            ]);
            return;
        }

        if ($run_id === 0) {
            $run_data = [
                'currency' => 'USD',
                'dollar_value' => $rate,
                'total_products' => 0,
                'percentage_change' => null,
                'context' => 'setup',
                'note' => 'Setup inicial - Registro de precios base'
            ];
            $run_id = $this->log_repo->insert_run($run_data);
            update_option('dpuwoo_setup_run_id', $run_id);
        }

        $processed = [];

        foreach ($products as $product) {
            $product_id = $product->get_id();
            $regular_price = floatval($product->get_regular_price());
            $sale_price = floatval($product->get_sale_price());

            if ($regular_price <= 0) {
                continue;
            }

            $usd = round($regular_price / $rate, 2);

            $item = [
                'product_id' => $product_id,
                'status' => 'updated',
                'old_regular_price' => 0,
                'new_regular_price' => $regular_price,
                'old_sale_price' => 0,
                'new_sale_price' => $sale_price > 0 ? $sale_price : null,
                'percentage_change' => null,
                'reason' => 'Setup inicial'
            ];
            $item_id = $this->log_repo->insert_run_item($run_id, $item);

            if ($item_id) {
                $processed[] = [
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'ars' => number_format($regular_price, 2),
                    'usd' => number_format($usd, 2),
                ];
            }

            update_post_meta($product_id, '_prixy_first_setup_done', current_time('mysql'));
        }

        // Limpiar option cuando se completó todo
        if (count($products) < $limit) {
            delete_option('dpuwoo_setup_run_id');
        }

        wp_send_json_success([
            'products' => $processed,
            'offset' => $offset,
            'limit' => $limit,
            'rate' => $rate,
            'done' => count($products) < $limit,
        ]);
    }
}
