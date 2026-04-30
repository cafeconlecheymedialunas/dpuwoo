<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Aplica restricción de dirección de actualización.
 * Extrae Price_Calculator::apply_update_direction().
 * Soporta: bidirectional (default), up_only, down_only.
 */
class Direction_Rule implements Price_Rule_Interface
{
    private ?string $applied_direction = null;
    private bool $was_blocked = false;

    public function apply(float $price, Price_Context $context): float
    {
        $this->applied_direction = $context->get_setting('update_direction', 'bidirectional');
        $this->was_blocked = false;
        $old_price = $context->old_regular;

        switch ($this->applied_direction) {
            case 'up_only':
                if ($price < $old_price) {
                    $this->was_blocked = true;
                    return $old_price;
                }
                return $price;

            case 'down_only':
                if ($price > $old_price) {
                    $this->was_blocked = true;
                    return $old_price;
                }
                return $price;

            default:
                return $price;
        }
    }

    public function get_rule_key(): string
    {
        if ($this->applied_direction === null) {
            return 'direction';
        }

        if ($this->applied_direction === 'up_only') {
            return $this->was_blocked ? 'direction_up_only_blocked' : 'direction_up_only_allowed';
        }
        if ($this->applied_direction === 'down_only') {
            return $this->was_blocked ? 'direction_down_only_blocked' : 'direction_down_only_allowed';
        }
        return 'direction_bidirectional';
    }
}
