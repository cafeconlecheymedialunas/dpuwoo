<?php
if (!defined('ABSPATH')) exit;

// Load base classes first
require_once __DIR__ . '/../class-dpuwoo-trait-request.php';
require_once __DIR__ . '/api/class-dpuwoo-api-response-formatter.php';

// Load provider classes
require_once __DIR__ . '/api/providers/class-dpuwoo-dolarapi-provider.php';
require_once __DIR__ . '/api/providers/class-dpuwoo-jsdelivr-provider.php';
require_once __DIR__ . '/api/providers/class-dpuwoo-cryptoprice-provider.php';
require_once __DIR__ . '/api/providers/class-dpuwoo-currencyapi-provider.php';
require_once __DIR__ . '/api/providers/class-dpuwoo-exhangerateapi-provider.php';
require_once __DIR__ . '/api/providers/class-dpuwoo-moneyconvert-provider.php';
require_once __DIR__ . '/api/providers/class-dpuwoo-hexarate-provider.php';
require_once __DIR__ . '/api/providers/class-dpuwoo-foreignrate-provider.php';

/**
 * Factory Pattern para instanciar proveedores de API de tasa de cambio.
 * Elimina el if/switch en API_Client::get_provider() y permite registrar
 * nuevos providers sin modificar el cliente principal (Open/Closed Principle).
 *
 * Uso:
 *   $provider = API_Provider_Factory::create('dolarapi');
 *   API_Provider_Factory::register('miprovider', My_Custom_Provider::class);
 */
class API_Provider_Factory
{
    /** @var array<string, string> Mapa de clave => clase del provider. */
    private static array $registry = [
        'dolarapi'         => DolarAPI_Provider::class,
        'jsdelivr'        => Jsdelivr_Provider::class,
        'cryptoprice'     => CryptoPrice_Provider::class,
        'currencyapi'    => CurrencyAPI_Provider::class,
        'exchangerate-api' => ExchangeRateAPI_Provider::class,
        'moneyconvert'    => MoneyConvert_Provider::class,
        'hexarate'        => HexaRate_Provider::class,
        'foreignrate'     => ForeignRate_Provider::class,
    ];

    /**
     * Crea e instancia un provider por su clave.
     *
     * @param string $provider_name Clave del provider (ej: 'dolarapi').
     * @return API_Provider_Interface
     * @throws \InvalidArgumentException Si la clave no está registrada.
     */
    public static function create(string $provider_name): API_Provider_Interface
    {
        if (!isset(self::$registry[$provider_name])) {
            throw new \InvalidArgumentException(
                "API_Provider_Factory: Provider desconocido [{$provider_name}]. " .
                "Disponibles: " . implode(', ', array_keys(self::$registry))
            );
        }

        $class = self::$registry[$provider_name];
        return new $class();
    }

    /**
     * Registra un provider externo sin modificar esta clase.
     * Útil para extensiones o mocks en tests.
     *
     * @param string $name  Clave única del provider.
     * @param string $class Nombre de la clase que implementa API_Provider_Interface.
     */
    public static function register(string $name, string $class): void
    {
        self::$registry[$name] = $class;
    }

    /**
     * Retorna las claves de todos los providers registrados.
     *
     * @return string[]
     */
    public static function get_registered_keys(): array
    {
        return array_keys(self::$registry);
    }
}
