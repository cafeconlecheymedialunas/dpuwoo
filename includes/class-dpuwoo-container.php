<?php
if (!defined('ABSPATH')) exit;

/**
 * Contenedor de Inyección de Dependencias (DI Container).
 * Punto único donde se construye y conecta el grafo completo de objetos del plugin.
 *
 * Reemplaza el uso de Singletons estáticos acoplados entre clases.
 * Soporta registros 'bind' (nueva instancia por llamada) y 'singleton' (instancia compartida).
 */
class Prixy_Container
{
    /** @var array<string, callable> Fábricas registradas */
    private array $bindings = [];

    /** @var array<string, object> Cache de singletons ya creados */
    private array $instances = [];

    /**
     * Registra una fábrica que crea una nueva instancia en cada llamada a get().
     *
     * @param string   $id      Identificador del servicio.
     * @param callable $factory Función que recibe el container y retorna la instancia.
     */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = ['factory' => $factory, 'singleton' => false];
    }

    /**
     * Registra una fábrica que crea la instancia solo una vez (shared).
     *
     * @param string   $id      Identificador del servicio.
     * @param callable $factory Función que recibe el container y retorna la instancia.
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = ['factory' => $factory, 'singleton' => true];
    }

    /**
     * Resuelve y retorna la instancia para el ID dado.
     *
     * @param string $id Identificador del servicio.
     * @return mixed
     * @throws \RuntimeException Si el ID no está registrado.
     */
    public function get(string $id): mixed
    {
        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException("Prixy_Container: Servicio no registrado [{$id}]");
        }

        $binding = $this->bindings[$id];

        if ($binding['singleton']) {
            if (!isset($this->instances[$id])) {
                $this->instances[$id] = ($binding['factory'])($this);
            }
            return $this->instances[$id];
        }

        return ($binding['factory'])($this);
    }

    /**
     * Construye el container con todas las dependencias del plugin registradas.
     * Único método a llamar desde class-prixy.php en el bootstrap.
     */
    public static function build(): self
    {
        $c = new self();

        /*----------------------------------------------------------
         * Infraestructura
         *---------------------------------------------------------*/
        $c->singleton('settings', fn() => new Settings_Repository());

        $c->singleton('product_repo', fn() => new Product_Repository());

        $c->singleton('log_repo', fn() => new Log_Repository());

        $c->singleton('logger', fn($c) => new Logger($c->get('log_repo')));

        $c->singleton('api', fn() => new API_Client());

        /*----------------------------------------------------------
         * Dominio — Reglas de Precio (Strategy Pattern)
         * El orden importa: Ratio → Dirección → Margen → Redondeo
         * Nota: Category_Exclusion_Rule se maneja vía short-circuit en el engine.
         *---------------------------------------------------------*/
        $c->bind('price_rules', fn() => [
            new Ratio_Rule(),
            new Direction_Rule(),
            new Margin_Rule(),
            new Rounding_Rule(),
        ]);

        $c->singleton('threshold_policy', fn() => new Threshold_Policy());

        /*----------------------------------------------------------
         * Aplicación — Servicios
         *---------------------------------------------------------*/
        $c->singleton('price_engine', fn($c) => new Price_Calculation_Engine(
            $c->get('price_rules')
        ));

        $c->singleton('batch_processor', fn($c) => new Batch_Processor(
            $c->get('product_repo'),
            $c->get('price_engine'),
            $c->get('log_repo')
        ));

        /*----------------------------------------------------------
         * Aplicación — Handlers (Command Pattern)
         *---------------------------------------------------------*/
        $c->singleton('update_handler', fn($c) => new Update_Prices_Handler(
            $c->get('settings'),
            $c->get('api'),
            $c->get('batch_processor'),
            $c->get('product_repo'),
            $c->get('logger'),
            $c->get('threshold_policy'),
            $c->get('log_repo')
        ));

        $c->singleton('rollback_handler', fn($c) => new Rollback_Handler(
            $c->get('log_repo')
        ));

        /*----------------------------------------------------------
         * Aplicación — Command Bus
         *---------------------------------------------------------*/
        $c->singleton('command_bus', function ($c) {
            $bus = new Command_Bus();
            $bus->register(Update_Prices_Command::class,   $c->get('update_handler'));
            $bus->register(Rollback_Item_Command::class,   $c->get('rollback_handler'));
            $bus->register(Rollback_Run_Command::class,    $c->get('rollback_handler'));
            return $bus;
        });

        /*----------------------------------------------------------
         * Presentación
         *---------------------------------------------------------*/
        $c->singleton('ajax_controller', fn($c) => new Ajax_Controller(
            $c->get('command_bus'),
            $c->get('logger'),
            $c->get('api'),
            $c->get('settings'),
            $c->get('log_repo')
        ));

        return $c;
    }
}
