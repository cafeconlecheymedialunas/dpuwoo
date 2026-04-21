<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Aplica el ratio de la tasa de cambio al precio base.
 * Es la primera regla en la cadena de cálculo.
 */
class Ratio_Rule implements Price_Rule_Interface
{
    private float $applied_ratio = 1.0;

    public function apply(float $price, Price_Context $context): float
    {
        $this->applied_ratio = $context->exchange_rate->ratio;
        
        if ($context->usd_baseline > 0) {
            return $context->usd_baseline * $this->applied_ratio;
        }
        
        return $price * $this->applied_ratio;
    }

    public function get_rule_key(): string
    {
        return 'ratio_' . round($this->applied_ratio, 6);
    }
}
