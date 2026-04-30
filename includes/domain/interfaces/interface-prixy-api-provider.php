<?php
if (!defined('ABSPATH')) exit;

/**
 * Contrato para los proveedores de tasa de cambio.
 * Todos los providers deben implementar esta interfaz para ser intercambiables.
 */
interface API_Provider_Interface
{
    /**
     * Obtiene la tasa de cambio actual para el tipo indicado.
     *
     * @param string $type Tipo de tasa (ej: 'oficial', 'blue', 'USD').
     * @return array|false Array con 'value', 'type', etc., o false en error.
     */
    public function get_rate($type);

    /**
     * Obtiene la lista de monedas/tipos disponibles.
     *
     * @return array|false Lista de monedas disponibles, o false en error.
     */
    public function get_currencies();

    /**
     * Verifica la conectividad con la API.
     *
     * @return array|bool Array con 'success' y detalles, o false en error.
     */
    public function test_connection();
}
