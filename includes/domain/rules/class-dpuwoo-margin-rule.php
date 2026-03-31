<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Aplica el margen de corrección configurado al precio.
 * Extrae Price_Calculator::apply_global_extra().
 */
class Margin_Rule implements Price_Rule_Interface
{
    private float $applied_margin = 0.0;

    public function apply(float $price, Price_Context $context): float
    {
        $this->applied_margin = floatval($context->get_setting('margin', 0));

        if ($this->applied_margin == 0) {
            return $price;
        }

        return $price * (1 + ($this->applied_margin / 100));
    }

    public function get_rule_key(): string
    {
        if ($this->applied_margin == 0) {
            return 'margin';
        }
        return 'margin_' . round($this->applied_margin, 2) . '%';
    }
}
