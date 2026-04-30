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
     * Retorna el dollar_value del último run registrado para un tipo específico de dólar
     * y moneda de referencia.
     * Si no hay run para esa combinación, retorna 0.0.
     *
     * @param string $dollar_type Tipo de cambio (ej: 'oficial', 'blue', 'bolsa')
     * @param string $reference_currency Moneda de referencia (ej: 'USD', 'EUR')
     * @return float
     */
    public function get_last_applied_rate(string $dollar_type, string $reference_currency): float;

    /**
     * Retorna el último precio guardado para un producto específico,
     * filtrando por currency y reference_currency.
     *
     * @param int    $product_id         ID del producto.
     * @param string $currency          Tipo de cambio (ej: 'oficial', 'blue').
     * @param string $reference_currency Moneda de referencia (ej: 'USD', 'EUR').
     * @return array|null {old_regular, new_regular, old_sale, new_sale, date, dollar_value, dollar_type} o null.
     */
    public function get_last_price_for_product(
        int    $product_id,
        string $currency = '',
        string $reference_currency = ''
    ): ?array;

    /**
     * Verifica si existe algún log para el producto.
     *
     * @param int $product_id ID del producto.
     * @return bool
     */
    public function has_any_log_for_product(int $product_id): bool;

    /**
     * Inicia una transacción de base de datos.
     * @return bool
     */
    public function begin_transaction(): bool;

    /**
     * Confirma la transacción de base de datos.
     * @return bool
     */
    public function commit_transaction(): bool;

    /**
     * Revierte la transacción de base de datos.
     * @return bool
     */
    public function rollback_transaction(): bool;
}
