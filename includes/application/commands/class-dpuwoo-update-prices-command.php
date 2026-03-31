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
 */
class Update_Prices_Command
{
    public function __construct(
        public readonly int    $batch    = 0,
        public readonly bool   $simulate = false,
        public readonly string $context  = 'manual'
    ) {}
}
