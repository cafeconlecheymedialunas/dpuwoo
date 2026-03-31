<?php
if (!defined('ABSPATH')) exit;

/**
 * Command DTO: Solicitud de actualización de precios para un lote específico.
 * Puede ser real (simulate=false) o de simulación (simulate=true).
 *
 * @property string $context 'manual' | 'cron'
 *   Determina qué configuración efectiva usará el Handler.
 *   'manual' → settings configurados en la página Ejecución Manual.
 *   'cron'   → settings configurados en la página Automatización (con fallback a manual).
 * @property int $run_id ID del run al que agregar items (para batches > 0)
 */
class Update_Prices_Command
{
    public function __construct(
        public readonly int    $batch    = 0,
        public readonly bool   $simulate = false,
        public readonly string $context  = 'manual',
        public readonly int    $run_id   = 0
    ) {}
}
