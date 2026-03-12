<?php
if (!defined('ABSPATH')) exit;

/**
 * Repositorio de configuración del plugin.
 * Encapsula todos los accesos a get_option/update_option para 'dpuwoo_settings'.
 * Elimina la lectura directa de settings dispersa en Price_Updater, Price_Calculator y Cron.
 */
class Settings_Repository
{
    private const OPTION_KEY = 'dpuwoo_settings';

    /** @var array Cache en memoria para evitar múltiples get_option por request. */
    private array $cache = [];

    /**
     * Obtiene todos los settings como array.
     */
    public function get_all(): array
    {
        if (empty($this->cache)) {
            $this->cache = (array) get_option(self::OPTION_KEY, []);
        }
        return $this->cache;
    }

    /**
     * Obtiene un valor individual por clave.
     *
     * @param string $key     Clave del setting.
     * @param mixed  $default Valor por defecto si no existe.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get_all()[$key] ?? $default;
    }

    /**
     * Actualiza un valor en memoria y persiste en la BD.
     * Invalida el cache.
     */
    public function set(string $key, mixed $value): void
    {
        $all         = $this->get_all();
        $all[$key]   = $value;
        $this->cache = $all;
        update_option(self::OPTION_KEY, $all);
    }

    /**
     * Reemplaza todos los settings y persiste.
     */
    public function save(array $settings): void
    {
        $this->cache = $settings;
        update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Invalida el cache para forzar re-lectura desde la BD en la próxima llamada.
     */
    public function refresh(): void
    {
        $this->cache = [];
    }
}
