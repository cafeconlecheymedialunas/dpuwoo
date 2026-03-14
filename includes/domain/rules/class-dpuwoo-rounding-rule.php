<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Aplica el redondeo configurado al precio calculado.
 * Extrae Price_Calculator::apply_configured_rounding().
 * Soporta: none, integer, ceil, floor, nearest.
 */
class Rounding_Rule implements Price_Rule_Interface
{
    public function apply(float $price, Price_Context $context): float
    {
        $type       = $context->get_setting('rounding_type', 'integer');
        $nearest_to = floatval($context->get_setting('nearest_to', 1));

        switch ($type) {
            case 'none':
                return $price;

            case 'integer':
                $rounded = (float) round($price);
                // Si el redondeo entero produce el mismo valor que el precio de referencia
                // (regular o de oferta), usar 2 decimales para no enmascarar cambios reales.
                $same_as_regular = $rounded === (float) round($context->old_regular);
                $same_as_sale    = $context->old_sale > 0 && $rounded === (float) round($context->old_sale);
                if (($same_as_regular || $same_as_sale) && abs($price - $rounded) > 0.001) {
                    return round($price, 2);
                }
                return $rounded;

            case 'ceil':
                return (float) ceil($price);

            case 'floor':
                return (float) floor($price);

            case 'nearest':
                if ($nearest_to <= 0) {
                    return (float) round($price);
                }
                return (float) (round($price / $nearest_to) * $nearest_to);

            default:
                return round($price, 2);
        }
    }

    public function get_rule_key(): string
    {
        return 'rounding';
    }
}
