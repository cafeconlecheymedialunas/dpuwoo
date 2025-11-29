<?php
// includes/class-logger.php

if (!defined('ABSPATH')) exit;

class Logger
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

    /**
     * Iniciar transacción para un run
     */
    public function begin_run_transaction($run_data)
    {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        $table = $wpdb->prefix . 'dpuwoo_runs';
        $now = current_time('mysql');
        
        $wpdb->insert($table, [
            'date' => $now,
            'dollar_type' => $run_data['dollar_type'] ?? '',
            'dollar_value' => $run_data['dollar_value'] ?? 0,
            'rules' => maybe_serialize($run_data['rules'] ?? []),
            'total_products' => intval($run_data['total_products'] ?? 0),
            'user_id' => intval($run_data['user_id'] ?? 0),
            'note' => $run_data['note'] ?? '',
            'percentage_change' => $run_data['percentage_change'] ?? null,
        ]);
        
        $run_id = $wpdb->insert_id;
        
        if (!$run_id) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        
        return $run_id;
    }

    /**
     * Agregar items al run en transacción - SOLO updated y error
     */
    public function add_items_to_transaction($run_id, $items)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';
        
        foreach ($items as $item) {
            // SOLO guardar si es updated o error
            if (in_array($item['status'], ['updated', 'error'])) {
                $wpdb->insert($table, [
                    'run_id' => $run_id,
                    'product_id' => $item['product_id'],
                    'old_regular_price' => $item['old_regular_price'] ?? null,
                    'new_regular_price' => $item['new_regular_price'] ?? null,
                    'old_sale_price' => $item['old_sale_price'] ?? null,
                    'new_sale_price' => $item['new_sale_price'] ?? null,
                    'status' => $item['status'],
                    'reason' => $item['reason'] ?? null,
                ]);
                
                if ($wpdb->last_error) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Confirmar transacción
     */
    public function commit_run_transaction($run_id)
    {
        global $wpdb;
        
        if ($wpdb->query('COMMIT')) {
            return $run_id;
        }
        
        return false;
    }

    /**
     * Revertir transacción
     */
    public function rollback_run_transaction()
    {
        global $wpdb;
        return $wpdb->query('ROLLBACK');
    }

    /**
     * Método original de crear run (sin transacción)
     */
    public function create_run($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_runs';
        $now = current_time('mysql');
        
        $wpdb->insert($table, [
            'date' => $now,
            'dollar_type' => $data['dollar_type'] ?? '',
            'dollar_value' => $data['dollar_value'] ?? 0,
            'rules' => maybe_serialize($data['rules'] ?? []),
            'total_products' => intval($data['total_products'] ?? 0),
            'user_id' => intval($data['user_id'] ?? 0),
            'note' => $data['note'] ?? '',
            'percentage_change' => $data['percentage_change'] ?? null,
        ]);
        
        return $wpdb->insert_id;
    }

    /**
     * Método original de insertar item (sin transacción)
     */
    public function insert_run_item($run_id, $item)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';

        // SOLO guardar si es updated o error
        if (!in_array($item['status'], ['updated', 'error'])) {
            return false;
        }

        $wpdb->insert($table, [
            'run_id' => $run_id,
            'product_id' => $item['product_id'],
            'old_regular_price' => $item['old_regular_price'] ?? null,
            'new_regular_price' => $item['new_regular_price'] ?? null,
            'old_sale_price' => $item['old_sale_price'] ?? null,
            'new_sale_price' => $item['new_sale_price'] ?? null,
            'status' => $item['status'] ?? 'updated',
            'reason' => $item['reason'] ?? null,
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Rollback por item id
     */
    public function rollback_item($item_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $item_id));
        if (!$row) return new WP_Error('not_found', 'Item not found');

        $product = wc_get_product($row->product_id);
        if (!$product) return new WP_Error('no_product', 'Product not found');

        // Restore regular price
        if (!is_null($row->old_regular_price)) {
            $product->set_regular_price($row->old_regular_price);
        }
        // Restore sale price
        if (!is_null($row->old_sale_price)) {
            $product->set_sale_price($row->old_sale_price);
        }
        $product->save();

        // Update status in item
        $wpdb->update($table, ['status' => 'reverted'], ['id' => $item_id]);

        return true;
    }

    /**
     * Rollback por run_id (todos los items)
     */
    public function rollback_run($run_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE run_id = %d", $run_id));
        if (!$rows) return new WP_Error('no_items', 'No items found for run');

        foreach ($rows as $r) {
            $this->rollback_item($r->id);
        }

        return true;
    }

    /**
     * Obtener items por run para UI
     */
    public function get_run_items($run_id, $limit = 500)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';
        
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE run_id = %d ORDER BY id ASC LIMIT %d", $run_id, intval($limit)));
        
        // Enriquecer con datos actuales del producto
        $enriched_items = [];
        foreach ($items as $item) {
            $enriched_items[] = $this->enrich_item_with_product_data($item);
        }
        
        return $enriched_items;
    }

    /**
     * Enriquecer item con datos actuales del producto
     */
    private function enrich_item_with_product_data($item)
    {
        $product = wc_get_product($item->product_id);
        
        if (!$product) {
            $item->product_name = 'Producto eliminado';
            $item->product_sku = 'N/A';
            $item->product_type = 'unknown';
        } else {
            $item->product_name = $product->get_name();
            $item->product_sku = $product->get_sku();
            $item->product_type = $product->get_type();
        }
        
        return $item;
    }

    /**
     * Obtener todos los runs para el historial
     */
    public function get_runs($limit = 100)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_runs';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY date DESC LIMIT %d", intval($limit)));
    }

    /**
     * Obtener un run específico por ID
     */
    public function get_run($run_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_runs';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $run_id));
    }

    /**
     * Contar items de un run
     */
    public function count_run_items($run_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';
        return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE run_id = %d", $run_id));
    }
}