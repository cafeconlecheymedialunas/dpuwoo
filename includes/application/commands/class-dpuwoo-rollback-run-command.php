<?php
if (!defined('ABSPATH')) exit;

/**
 * Command DTO: Solicitud de rollback de todos los items de un run completo.
 */
class Rollback_Run_Command
{
    public function __construct(
        public readonly int $run_id
    ) {}
}
