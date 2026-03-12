<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Aplica el margen de corrección configurado al precio.
 * Extrae Price_Calculator::apply_global_extra().
 */
class Margin_Rule implements Price_Rule_Interface
{
    public function apply(float $price, Price_Context $context): float
    {
        $margin = floatval($context->get_setting('margin', 0));

        if ($margin == 0) {
            return $price;
        }

        return $price * (1 + ($margin / 100));
    }

    public function get_rule_key(): string
    {
        return 'margin';
    }
}
