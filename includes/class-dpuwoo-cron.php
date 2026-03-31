<?php
if (!defined('ABSPATH')) exit;

class Cron
{
    const HOOK = 'dpuwoo_do_update';

    public static function schedule()
    {
        $opts    = get_option('dpuwoo_settings', []);
        $enabled = $opts['cron_enabled'] ?? 1;

        if (!$enabled) {
            // Si el cron fue desactivado, desagendarlo
            self::unschedule();
            return;
        }

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

        // Verificar que el cron esté activo antes de procesar
        $opts    = $dpuwoo_container->get('settings')->get_all();
        $enabled = $opts['cron_enabled'] ?? 1;
        if (!$enabled) {
            return;
        }

        // Procesar lotes secuencialmente con contexto 'cron'.
        // El Handler usará la configuración de la página Automatización
        // (cron_margin, cron_threshold, etc.) con fallback a la configuración manual.
        $batch  = 0;
        $result = $bus->dispatch(new Update_Prices_Command($batch, simulate: false, context: 'cron'));

        if (isset($result['error'])) {
            return;
        }

        $total_batches = $result['batch_info']['total_batches'] ?? 1;

        // Procesar lotes adicionales si hay más de uno
        for ($batch = 1; $batch < $total_batches; $batch++) {
            $bus->dispatch(new Update_Prices_Command($batch, simulate: false, context: 'cron'));
        }
    }
}