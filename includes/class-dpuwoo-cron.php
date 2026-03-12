<?php
if (!defined('ABSPATH')) exit;

class Cron
{
    const HOOK = 'dpuwoo_do_update';

    public static function schedule()
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'hourly', self::HOOK);
        }
    }

    public static function unschedule()
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) wp_unschedule_event($timestamp, self::HOOK);
    }

    /**
     * Ejecuta la actualización automática vía Command Bus (capa de Aplicación).
     * Reemplaza la llamada directa a Price_Updater::get_instance().
     *
     * Si no existe un container bootstrapped (ej: WP Cron corre aislado),
     * construye uno propio de forma lazy.
     */
    public static function run_cron(): void
    {
        // Obtener el command bus desde el container global si está disponible,
        // o construir uno nuevo (Cron puede ejecutarse fuera del ciclo HTTP normal).
        global $dpuwoo_container;

        if (!$dpuwoo_container instanceof Dpuwoo_Container) {
            $dpuwoo_container = Dpuwoo_Container::build();
        }

        /** @var Command_Bus $bus */
        $bus = $dpuwoo_container->get('command_bus');

        // Refrescar settings por si el cron corre fuera del ciclo HTTP normal
        $dpuwoo_container->get('settings')->refresh();

        // Procesar lotes secuencialmente (batch 0..n)
        // El Handler internamente valida threshold y corta si no se cumple.
        $batch  = 0;
        $result = $bus->dispatch(new Update_Prices_Command($batch, simulate: false));

        if (isset($result['error'])) {
            return;
        }

        $total_batches = $result['batch_info']['total_batches'] ?? 1;

        // Procesar lotes adicionales si hay más de uno
        for ($batch = 1; $batch < $total_batches; $batch++) {
            $bus->dispatch(new Update_Prices_Command($batch, simulate: false));
        }
    }
}