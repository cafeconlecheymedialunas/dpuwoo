<?php
if (!defined('ABSPATH')) exit;

/**
 * Logger (thin wrapper) - ahora delega todo en DPUWoo_Repository
 */
class Logger
{
    protected static $instance;
    protected $repo;

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
        $this->repo = Log_Repository::get_instance();
    }

    /**
     * Begin run (solo inserta la run, sin transacción)
     */
    public function begin_run_transaction($run_data)
    {
        // Se elimina la llamada a $this->repo->begin_transaction()
        
        $run_id = $this->repo->insert_run($run_data);

        if (!$run_id) {
            // Se elimina el rollback. Si falla el insert, falla y punto (autocommit).
            error_log("DPUWOO: Failed to insert run (Autocommit Mode)");
            return false;
        }

        return $run_id;
    }

    /**
     * Add items (no requiere cambios)
     */
    public function add_items_to_transaction($run_id, $items)
    {
        // 1. Filtrar solo los estados que queremos guardar (updated o error)
        $items_to_save = array_filter($items, function($item) {
            return in_array($item['status'], ['updated', 'error']);
        });

        // 2. Si la lista filtrada está vacía, retornar TRUE.
        if (empty($items_to_save)) {
            return true; 
        }

        // 3. Insertar solo el subconjunto de ítems filtrados
        $success = $this->repo->insert_items_bulk($run_id, $items_to_save);
        
        if (!$success) {
            error_log("DPUWOO: Failed to insert run items for run_id {$run_id}");
            return false;
        }
        return true;
    }

    /**
     * Commit (solo retorna el ID, sin hacer commit)
     */
    public function commit_run_transaction($run_id)
    {
        // Se elimina la llamada a $this->repo->commit()
        return $run_id;
    }

    /**
     * Rollback (solo retorna true, sin hacer rollback)
     */
    public function rollback_run_transaction()
    {
        // Se elimina la llamada a $this->repo->rollback()
        return true;
    }

    /* Compatibility methods (original names) */
    public function create_run($data)
    {
        return $this->repo->insert_run($data);
    }

    public function insert_run_item($run_id, $item)
    {
        return $this->repo->insert_run_item($run_id, $item);
    }

    public function rollback_item($item_id)
    {
        return $this->repo->rollback_item($item_id);
    }

    public function rollback_run($run_id)
    {
        return $this->repo->rollback_run($run_id);
    }

    public function get_run_items($run_id, $limit = 500)
    {
        return $this->repo->get_run_items($run_id, $limit);
    }

    public function get_runs($limit = 100)
    {
        return $this->repo->get_runs($limit);
    }

    public function get_run($run_id)
    {
        return $this->repo->get_run($run_id);
    }

    public function count_run_items($run_id)
    {
        $items = $this->repo->get_items_for_run($run_id);
        return is_array($items) ? count($items) : 0;
    }
}