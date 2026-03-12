<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Aplica restricción de dirección de actualización.
 * Extrae Price_Calculator::apply_update_direction().
 * Soporta: bidirectional (default), up_only, down_only.
 */
class Direction_Rule implements Price_Rule_Interface
{
    public function apply(float $price, Price_Context $context): float
    {
        $direction = $context->get_setting('update_direction', 'bidirectional');
        $old_price = $context->old_regular;

        switch ($direction) {
            case 'up_only':
                return ($price < $old_price) ? $old_price : $price;

            case 'down_only':
                return ($price > $old_price) ? $old_price : $price;

            default: // bidirectional
                return $price;
        }
    }

    public function get_rule_key(): string
    {
        return 'direction';
    }
}
