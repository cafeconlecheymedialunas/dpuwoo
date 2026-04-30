<?php
if (!defined('ABSPATH')) exit;

/**
 * Política de dominio que decide si el cambio en la tasa de cambio justifica
 * una actualización de precios, considerando:
 *
 *  - Dirección: solo procesa cambios en la dirección configurada.
 *  - Umbral mínimo (threshold):     el cambio debe ser >= este % para actualizar.
 *  - Umbral máximo (threshold_max): el cambio debe ser <= este % (freno de seguridad).
 *    0 = sin límite superior.
 *  - Primera ejecución: siempre actualiza (establece el baseline desde origin_exchange_rate).
 */
class Threshold_Policy
{
    /**
     * @param Exchange_Rate $rate           Tasa actual vs anterior.
     * @param float         $threshold_min  % mínimo de cambio requerido (0 = siempre).
     * @param float         $threshold_max  % máximo permitido (0 = sin límite).
     * @param string        $direction      'bidirectional' | 'up_only' | 'down_only'
     * @param bool          $first_run      Si true, omite umbrales (primera ejecución).
     * @return bool
     */
    public function should_update(
        Exchange_Rate $rate,
        float         $threshold_min,
        float         $threshold_max  = 0,
        string        $direction      = 'bidirectional',
        bool          $first_run      = false
    ): bool {
        // Primera ejecución: siempre actualizar para establecer el baseline.
        if ($first_run) {
            return true;
        }

        $change     = $rate->percentage_change;
        $abs_change = abs($change);

        // 1. Dirección: bloquear si el movimiento va en contra de la dirección configurada.
        if ($direction === 'up_only'   && $change <= 0) return false;
        if ($direction === 'down_only' && $change >= 0) return false;

        // 2. Umbral mínimo: el cambio debe superar el mínimo configurado.
        if ($threshold_min > 0 && $abs_change < $threshold_min) return false;

        // 3. Umbral máximo: freno de seguridad — cambios excesivos pueden ser errores de datos.
        if ($threshold_max > 0 && $abs_change > $threshold_max) return false;

        return true;
    }
}
