<?php
if (!defined('ABSPATH')) exit;

/**
 * Value Object que contiene el resultado del cálculo de precio para un producto.
 * Generado por Price_Calculation_Engine::calculate().
 */
class Calculation_Result
{
    public readonly float $percentage_change;

    public function __construct(
        public readonly float $new_regular,
        public readonly float $new_sale,
        public readonly float $old_regular,
        public readonly float $old_sale,
        public readonly array $applied_rules = []
    ) {
        $this->percentage_change = $old_regular > 0
            ? round((($new_regular - $old_regular) / $old_regular) * 100, 2)
            : 0.0;
    }

    public function has_regular_change(): bool
    {
        return abs($this->new_regular - $this->old_regular) > 0.001;
    }

    public function has_sale_change(): bool
    {
        return abs($this->new_sale - $this->old_sale) > 0.001;
    }

    public function has_any_change(): bool
    {
        return $this->has_regular_change() || $this->has_sale_change();
    }

    public function get_rules_summary(): string
    {
        return implode(', ', $this->applied_rules) ?: 'Sin reglas especiales';
    }
}
