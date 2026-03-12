<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Aplica el ratio de la tasa de cambio al precio base.
 * Es la primera regla en la cadena de cálculo.
 */
class Ratio_Rule implements Price_Rule_Interface
{
    public function apply(float $price, Price_Context $context): float
    {
        return $price * $context->exchange_rate->ratio;
    }

    public function get_rule_key(): string
    {
        return 'ratio';
    }
}
