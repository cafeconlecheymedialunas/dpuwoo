<?php
if (!defined('ABSPATH')) exit;

/**
 * Strategy: Revierte el precio al original si el producto pertenece a una categoría excluida.
 * Extrae Price_Calculator::apply_category_rules() pero sin consultar la BD directamente;
 * recibe las categorías excluidas desde el contexto (settings).
 */
class Category_Exclusion_Rule implements Price_Rule_Interface
{
    public function apply(float $price, Price_Context $context): float
    {
        $excluded = $context->get_setting('exclude_categories', []);

        if (empty($excluded) || empty($context->category_ids)) {
            return $price;
        }

        if (!empty(array_intersect($context->category_ids, (array) $excluded))) {
            // Producto excluido → retorna precio original sin modificar
            return $context->old_regular;
        }

        return $price;
    }

    public function get_rule_key(): string
    {
        return 'category_exclusion';
    }
}
