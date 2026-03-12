<?php
if (!defined('ABSPATH')) exit;

/**
 * Contrato para las reglas de cálculo de precio (Strategy Pattern).
 * Cada regla recibe el precio actual y el contexto, y retorna el precio modificado.
 */
interface Price_Rule_Interface
{
    /**
     * Aplica la regla al precio dado dentro del contexto de cálculo.
     *
     * @param float         $price   Precio actual (resultado de reglas anteriores en la cadena).
     * @param Price_Context $context Contexto con datos del producto, tasa de cambio y settings.
     * @return float Precio modificado por la regla.
     */
    public function apply(float $price, Price_Context $context): float;

    /**
     * Identificador único de la regla para auditoría y logging.
     */
    public function get_rule_key(): string;
}
