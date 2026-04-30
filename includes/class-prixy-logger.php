<?php
if (!defined('ABSPATH')) exit;

/**
 * Logger (thin wrapper) — delega en Log_Repository_Interface.
 * Soporta inyección de dependencias (Container) manteniendo Singleton legado.
 */
class Logger
{
    protected static $instance;
    protected Log_Repository_Interface $repo;

    /**
     * Constructor público para inyección de dependencias vía DI Container.
     *
     * @param Log_Repository_Interface $repo
     */
    public function __construct(Log_Repository_Interface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Singleton de compatibilidad.
     * @deprecated Preferir inyección vía Prixy_Container.
     */
    public static function init(): static
    {
        if (null === self::$instance) {
            self::$instance = new self(Log_Repository::get_instance());
        }
        return self::$instance;
    }

    public static function get_instance(): static
    {
        return self::init();
    }

    /**
     * Begin run transaction (P0: CRÍTICO)
     * Implementa transacción real de BD para garantizar atomicidad entre batches.
     */
    public function begin_run_transaction($run_data)
    {
        if (!$this->repo->begin_transaction()) {
            error_log('DPUWoo: Fallo al iniciar transacción de BD');
            return false;
        }
        
        $run_id = $this->repo->insert_run($run_data);

        if (!$run_id) {
            $this->repo->rollback_transaction();
            error_log('DPUWoo: Fallo al insertar run');
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
            return false;
        }
        return true;
    }

    /**
     * Commit run transaction (P0: CRÍTICO)
     * Confirma todos los cambios de la transacción.
     */
    public function commit_run_transaction($run_id)
    {
        if (!$this->repo->commit_transaction()) {
            error_log('DPUWoo: Fallo al confirmar transacción (Run: ' . $run_id . ')');
            return false;
        }
        return $run_id;
    }

    /**
     * Rollback run transaction (P0: CRÍTICO)
     * Revierte todos los cambios de la transacción.
     */
    public function rollback_run_transaction()
    {
        if (!$this->repo->rollback_transaction()) {
            error_log('DPUWoo: Fallo al revertir transacción');
            return false;
        }
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