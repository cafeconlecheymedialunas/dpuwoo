<?php
if (!defined('ABSPATH')) exit;

/**
 * Contrato para el repositorio de logs de ejecución.
 */
interface Log_Repository_Interface
{
    /**
     * Inserta un nuevo registro de ejecución (run) y retorna su ID.
     *
     * @param array $data Datos del run (dollar_type, dollar_value, rules, etc.).
     * @return int|false ID del run insertado, o false en error.
     */
    public function insert_run(array $data): int|false;

    /**
     * Actualiza un run existente.
     *
     * @param int   $run_id ID del run.
     * @param array $data   Campos a actualizar.
     * @return bool
     */
    public function update_run(int $run_id, array $data): bool;

    /**
     * Obtiene todos los runs ordenados por fecha descendente.
     *
     * @return array
     */
    public function get_runs(): array;

    /**
     * Obtiene los items de un run específico.
     *
     * @param int $run_id ID del run.
     * @param int $limit  Límite de resultados.
     * @return array
     */
    public function get_run_items(int $run_id, int $limit = 500): array;

    /**
     * Inserta múltiples items de un run en bulk.
     *
     * @param int   $run_id  ID del run.
     * @param array $items   Array de items.
     * @return bool
     */
    public function insert_items_bulk(int $run_id, array $items): bool;

    /**
     * Revierte el precio de un item individual (rollback).
     *
     * @param int $log_id ID del run_item.
     * @return array{success: bool, message: string}
     */
    public function rollback_item(int $log_id): array;

    /**
     * Revierte todos los precios de un run completo.
     *
     * @param int $run_id ID del run.
     * @return array{success: bool, reverted: int, errors: int}
     */
    public function rollback_run(int $run_id): array;

    /**
     * Retorna el dollar_value del último run registrado, o 0.0 si no hay ninguno.
     */
    public function get_last_applied_rate(): float;
}
