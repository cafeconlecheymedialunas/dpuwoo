<?php
if (!defined('ABSPATH')) exit;

/**
 * Value Object inmutable que representa la tasa de cambio actual y su relación con la anterior.
 * Encapsula la lógica que estaba duplicada en Price_Updater::check_threshold_met()
 * y Price_Calculator::get_last_applied_dollar().
 */
class Exchange_Rate
{
    public readonly float $ratio;
    public readonly float $percentage_change;

    public function __construct(
        public readonly float $current,
        public readonly float $previous
    ) {
        $this->ratio = ($previous > 0) ? ($current / $previous) : 1.0;
        $this->percentage_change = ($previous > 0)
            ? (($current - $previous) / $previous * 100)
            : 0.0;
    }

    /**
     * Crea una instancia cuando no hay tasa previa (primera ejecución).
     */
    public static function first_run(float $current): self
    {
        return new self($current, $current);
    }

    /**
     * Retorna el cambio porcentual absoluto.
     */
    public function get_abs_percentage_change(): float
    {
        return abs($this->percentage_change);
    }

    /**
     * Verifica si el cambio supera el umbral dado.
     *
     * @param float $threshold Porcentaje mínimo de cambio (0 = siempre actualizar).
     */
    public function meets_threshold(float $threshold): bool
    {
        return $threshold <= 0 || $this->get_abs_percentage_change() >= $threshold;
    }

    public function to_array(): array
    {
        return [
            'current'           => $this->current,
            'previous'          => $this->previous,
            'ratio'             => $this->ratio,
            'percentage_change' => $this->percentage_change,
        ];
    }
}
