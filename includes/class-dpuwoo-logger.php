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
     * Inserta un run y devuelve ID
     * $data: ['dollar_type'=>..,'dollar_value'=>..,'rules'=>json,'total_products'=>int,'user_id'=>int,'note'=>str]
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
        ]);
        return $wpdb->insert_id;
    }

    /**
     * Inserta item ligado al run
     */
    public function insert_run_item($run_id, $item)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dpuwoo_run_items';

        $wpdb->insert($table, [
            'run_id' => $run_id,
            'product_id' => $item['product_id'],
            'product_sku' => $item['product_sku'] ?? '',
            'product_name' => $item['product_name'] ?? '',
            'old_regular_price' => $item['old_regular_price'] ?? null,
            'new_regular_price' => $item['new_regular_price'] ?? null,
            'old_sale_price' => $item['old_sale_price'] ?? null,
            'new_sale_price' => $item['new_sale_price'] ?? null,
            'percentage_change' => $item['percentage_change'] ?? null,
            'category_name' => $item['category_name'] ?? null,
            'status' => $item['status'] ?? 'updated',
            'reason' => $item['reason'] ?? null,
            'extra' => isset($item['extra']) ? maybe_serialize($item['extra']) : null,
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
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE run_id = %d ORDER BY id ASC LIMIT %d", $run_id, intval($limit)));
    }
}
