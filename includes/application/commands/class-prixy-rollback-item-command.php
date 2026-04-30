<?php
if (!defined('ABSPATH')) exit;

/**
 * Command DTO: Solicitud de rollback de un item individual de log.
 */
class Rollback_Item_Command
{
    public function __construct(
        public readonly int $log_id
    ) {}
}
