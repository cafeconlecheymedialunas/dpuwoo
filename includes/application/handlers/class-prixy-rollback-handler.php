<?php
if (!defined('ABSPATH')) exit;

/**
 * Handler para los Commands de rollback.
 * Gestiona la reversión de items individuales y runs completos.
 * Absorbe la lógica de ajax_revert_item() y ajax_revert_run() de Ajax_Manager.
 */
class Rollback_Handler
{
    public function __construct(
        private Log_Repository_Interface $log_repo
    ) {}

    /**
     * Despacha al método correcto según el tipo de Command.
     */
    public function handle(Rollback_Item_Command|Rollback_Run_Command $cmd): array
    {
        if ($cmd instanceof Rollback_Item_Command) {
            return $this->revert_item($cmd);
        }

        return $this->revert_run($cmd);
    }

    private function revert_item(Rollback_Item_Command $cmd): array
    {
        return $this->log_repo->rollback_item($cmd->log_id);
    }

    private function revert_run(Rollback_Run_Command $cmd): array
    {
        return $this->log_repo->rollback_run($cmd->run_id);
    }
}
