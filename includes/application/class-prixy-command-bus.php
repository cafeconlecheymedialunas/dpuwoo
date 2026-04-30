<?php
if (!defined('ABSPATH')) exit;

/**
 * Command Bus: enruta Commands a sus Handlers registrados.
 * Desacopla la capa de Presentación (Ajax_Controller) de la capa de Aplicación (Handlers).
 *
 * Uso:
 *   $bus->register(Update_Prices_Command::class, $update_handler);
 *   $result = $bus->dispatch(new Update_Prices_Command(batch: 0));
 */
class Command_Bus
{
    /** @var array<string, object> Map de class_name => handler */
    private array $handlers = [];

    /**
     * Registra un handler para un tipo de Command.
     *
     * @param string $command_class FQCN de la clase del Command.
     * @param object $handler       Instancia con método handle($command).
     */
    public function register(string $command_class, object $handler): void
    {
        $this->handlers[$command_class] = $handler;
    }

    /**
     * Despacha un Command al Handler registrado y retorna el resultado.
     *
     * @param object $command Instancia del Command a despachar.
     * @return mixed Resultado del handler.
     * @throws \RuntimeException Si no hay handler registrado para el Command.
     */
    public function dispatch(object $command): mixed
    {
        $class = get_class($command);

        if (!isset($this->handlers[$class])) {
            throw new \RuntimeException("Command_Bus: No hay handler registrado para [{$class}]");
        }

        return $this->handlers[$class]->handle($command);
    }

    /**
     * Verifica si existe un handler registrado para el Command dado.
     */
    public function has_handler(string $command_class): bool
    {
        return isset($this->handlers[$command_class]);
    }
}
