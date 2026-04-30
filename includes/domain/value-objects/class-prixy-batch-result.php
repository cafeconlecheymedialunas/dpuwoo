<?php
if (!defined('ABSPATH')) exit;

/**
 * Value Object que agrega los resultados del procesamiento de un lote de productos.
 */
class Batch_Result
{
    private array $changes    = [];
    private int   $updated    = 0;
    private int   $errors     = 0;
    private int   $skipped    = 0;
    private array $errors_map = [];

    public function add_change(array $change_data): void
    {
        $this->changes[] = $change_data;

        switch ($change_data['status'] ?? '') {
            case 'updated':
            case 'simulated':
                $this->updated++;
                break;
            case 'error':
                $this->errors++;
                $reason = $change_data['reason'] ?? 'unknown';
                $this->errors_map[$reason] = ($this->errors_map[$reason] ?? 0) + 1;
                break;
            case 'skipped':
                $this->skipped++;
                break;
        }
    }

    /**
     * Combina el resultado de otro lote en este (para acumular múltiples lotes).
     */
    public function merge(Batch_Result $other): void
    {
        foreach ($other->get_changes() as $change) {
            $this->changes[] = $change;
        }
        $this->updated += $other->get_updated();
        $this->errors  += $other->get_errors();
        $this->skipped += $other->get_skipped();
        foreach ($other->get_errors_map() as $key => $count) {
            $this->errors_map[$key] = ($this->errors_map[$key] ?? 0) + $count;
        }
    }

    public function get_changes(): array    { return $this->changes; }
    public function get_updated(): int      { return $this->updated; }
    public function get_errors(): int       { return $this->errors; }
    public function get_skipped(): int      { return $this->skipped; }
    public function get_errors_map(): array { return $this->errors_map; }

    public function to_summary(bool $simulate = false): array
    {
        return [
            'updated'   => $this->updated,
            'errors'    => $this->errors,
            'skipped'   => $this->skipped,
            'simulated' => $simulate,
        ];
    }
}
