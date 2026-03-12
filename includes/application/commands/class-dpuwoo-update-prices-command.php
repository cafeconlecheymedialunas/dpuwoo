<?php
if (!defined('ABSPATH')) exit;

/**
 * Command DTO: Solicitud de actualización de precios para un lote específico.
 * Puede ser real (simulate=false) o de simulación (simulate=true).
 */
class Update_Prices_Command
{
    public function __construct(
        public readonly int  $batch    = 0,
        public readonly bool $simulate = false
    ) {}
}
