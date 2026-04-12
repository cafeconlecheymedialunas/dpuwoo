<?php
if (!defined('ABSPATH')) exit;

/**
 * DPUWoo_Repository
 * Encapsula todo el acceso a base de datos para runs y run_items.
 * Implementa Log_Repository_Interface (capa de dominio).
 */
class Log_Repository implements Log_Repository_Interface
{
    protected static $instance;
    protected $wpdb;
    protected $table_runs;
    protected $table_items;

    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_runs  = $wpdb->prefix . 'dpuwoo_runs';
        $this->table_items = $wpdb->prefix . 'dpuwoo_run_items';
    }

    /* ---------------------------
     * Runs
     * --------------------------- */
    public function insert_run(array $data): int|false
    {
        $now = current_time('mysql');

        $insert_data = [
            'date'                => $now,
            'dollar_type'         => $data['currency'] ?? $data['dollar_type'] ?? '',
            'dollar_value'        => floatval($data['dollar_value'] ?? 0),
            'rules'               => maybe_serialize($data['rules'] ?? []),
            'total_products'       => intval($data['total_products'] ?? 0),
            'user_id'            => intval($data['user_id'] ?? 0),
            'note'               => $data['note'] ?? '',
            'percentage_change'   => isset($data['percentage_change']) ? floatval($data['percentage_change']) : null,
        ];

        if (isset($data['reference_currency']) && !empty($data['reference_currency'])) {
            $insert_data['reference_currency'] = $data['reference_currency'];
        }

        $this->wpdb->insert($this->table_runs, $insert_data);

        if ($this->wpdb->last_error) {
            error_log('DPUWoo insert_run error: ' . $this->wpdb->last_error);
            return false;
        }

        return $this->wpdb->insert_id;
    }

    public function update_run(int $run_id, array $data): bool
    {
        return $this->wpdb->update($this->table_runs, $data, ['id' => intval($run_id)]);
    }

    public function get_run($run_id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_runs} WHERE id = %d", intval($run_id)));
    }

    public function get_runs(int $limit = 100): array
    {
        return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_runs} ORDER BY date DESC LIMIT %d", intval($limit)));
    }

    public function get_last_applied_rate(string $currency, string $reference_currency): float
    {
        $value = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT dollar_value FROM {$this->table_runs} 
             WHERE dollar_type = %s AND reference_currency = %s
             ORDER BY id DESC LIMIT 1",
            $currency,
            $reference_currency
        ));
        return $value !== null ? floatval($value) : 0.0;
    }

    /* ---------------------------
     * Items
     * --------------------------- */
    public function insert_run_item($run_id, $item)
    {
        // Solo insertar updated o error
        if (!in_array($item['status'], ['updated', 'error'])) {
            return false;
        }

        $insert = [
            'run_id'            => intval($run_id),
            'product_id'        => intval($item['product_id']),
            'old_regular_price' => isset($item['old_regular_price']) ? $item['old_regular_price'] : null,
            'new_regular_price' => isset($item['new_regular_price']) ? $item['new_regular_price'] : null,
            'old_sale_price'    => isset($item['old_sale_price']) ? $item['old_sale_price'] : null,
            'new_sale_price'    => isset($item['new_sale_price']) ? $item['new_sale_price'] : null,
            'percentage_change' => isset($item['percentage_change']) ? floatval($item['percentage_change']) : null,
            'status'            => $item['status'],
            'reason'            => $item['reason'] ?? null,
        ];

        $this->wpdb->insert($this->table_items, $insert);
        if ($this->wpdb->last_error) {
            return false;
        }
        return $this->wpdb->insert_id;
    }

    public function insert_items_bulk(int $run_id, array $items): bool
    {
        // El Logger asegura que $items solo contiene updated/error
        foreach ($items as $item) {
            if (!$this->insert_run_item($run_id, $item)) {
                return false;
            }
        }
        return true;
    }

    public function get_run_items(int $run_id, int $limit = 500): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_items} WHERE run_id = %d ORDER BY id ASC LIMIT %d",
            intval($run_id),
            intval($limit)
        ));

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->enrich_item($row);
        }
        return $result;
    }

    private function enrich_item($row)
    {
        $product = wc_get_product($row->product_id);

        if (!$product) {
            $row->product_name  = 'Producto eliminado';
            $row->product_sku   = 'N/A';
            $row->product_type  = 'unknown';
            $row->current_regular = null;
            $row->current_sale    = null;
            return $row;
        }

        $row->product_name      = $product->get_name();
        $row->product_sku       = $product->get_sku();
        $row->product_type      = $product->get_type();
        $row->current_regular = $product->get_regular_price();
        $row->current_sale    = $product->get_sale_price();

        return $row;
    }

    public function get_item($item_id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_items} WHERE id = %d", intval($item_id)));
    }

    public function update_item_status($item_id, $status)
    {
        return $this->wpdb->update($this->table_items, ['status' => $status], ['id' => intval($item_id)]);
    }

    public function get_items_for_run($run_id)
    {
        return $this->wpdb->get_results($this->wpdb->prepare("SELECT id FROM {$this->table_items} WHERE run_id = %d", intval($run_id)));
    }

    public function rollback_item(int $item_id): array
    {
        $row = $this->get_item($item_id);
        if (!$row) {
            return ['success' => false, 'message' => 'Item no encontrado'];
        }

        $product = wc_get_product($row->product_id);
        if (!$product) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        if (!is_null($row->old_regular_price)) {
            $product->set_regular_price($row->old_regular_price);
        }
        if (!is_null($row->old_sale_price)) {
            $product->set_sale_price($row->old_sale_price);
        }
        $product->save();

        $this->update_item_status($item_id, 'reverted');

        return ['success' => true, 'message' => 'Precio revertido correctamente'];
    }

    public function rollback_run(int $run_id): array
    {
        $items    = $this->get_items_for_run($run_id);
        $reverted = 0;
        $errors   = 0;

        foreach ($items as $it) {
            $res = $this->rollback_item($it->id);
            $res['success'] ? $reverted++ : $errors++;
        }

        return ['success' => $errors === 0, 'reverted' => $reverted, 'errors' => $errors];
    }

    /* ---------------------------
     * Helpers: count & fetch product ids por paginado
     * --------------------------- */
    public function get_product_ids_batch($limit = 500, $offset = 0)
    {
        return $this->wpdb->get_col($this->wpdb->prepare("
            SELECT ID FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
            ORDER BY ID ASC
            LIMIT %d OFFSET %d
        ", intval($limit), intval($offset)));
    }

    public function count_all_products(): int
    {
        return (int) $this->wpdb->get_var("
            SELECT COUNT(ID) FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
        ");
    }

    public function get_aggregate_stats(): array
    {
        $total_runs = (int) $this->wpdb->get_var(
            "SELECT COUNT(id) FROM {$this->table_runs}"
        );
        $total_products = (int) $this->wpdb->get_var(
            "SELECT COALESCE(SUM(total_products), 0) FROM {$this->table_runs}"
        );
        $avg_pct = (float) $this->wpdb->get_var(
            "SELECT AVG(percentage_change) FROM {$this->table_runs}
             WHERE percentage_change IS NOT NULL"
        );
        $error_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(id) FROM {$this->table_items} WHERE status = 'error'"
        );
        $updated_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(id) FROM {$this->table_items} WHERE status = 'updated'"
        );
        return compact('total_runs', 'total_products', 'avg_pct', 'error_count', 'updated_count');
    }

    public function get_runs_for_chart(int $limit = 30): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, date, dollar_value, dollar_type, total_products,
                        percentage_change, context
                 FROM {$this->table_runs}
                 ORDER BY date ASC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }
}