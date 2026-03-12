<?php
if (!defined('ABSPATH')) exit;

/**
 * Motor de cálculo de precios que aplica una cadena de reglas (Strategy Pattern).
 * Reemplaza Price_Calculator. Recibe las reglas por inyección de dependencias,
 * haciéndolo completamente configurable y testeable.
 */
class Price_Calculation_Engine
{
    /** @var Price_Rule_Interface[] Reglas aplicables al precio de oferta (subconjunto de $rules). */
    private array $sale_rules;

    /**
     * @param Price_Rule_Interface[] $rules Cadena ordenada de reglas a aplicar.
     */
    public function __construct(private array $rules)
    {
        // Pre-filtrar una vez para evitar instanceof por cada producto en calculate_sale_price()
        $this->sale_rules = array_filter(
            $rules,
            fn($r) => $r instanceof Ratio_Rule || $r instanceof Margin_Rule || $r instanceof Rounding_Rule
        );
    }

    /**
     * Calcula el nuevo precio regular y de oferta para el contexto dado.
     *
     * @param Price_Context $context Datos del producto, tasa de cambio y settings.
     * @return Calculation_Result Resultado con nuevos precios y reglas aplicadas.
     */
    public function calculate(Price_Context $context): Calculation_Result
    {
        // Calcular nuevo precio regular aplicando la cadena de reglas
        $new_regular  = $context->old_regular;
        $applied_keys = [];

        foreach ($this->rules as $rule) {
            $new_regular    = $rule->apply($new_regular, $context);
            $applied_keys[] = $rule->get_rule_key();
        }

        // Calcular nuevo precio de oferta (aplicando solo ratio + margen + redondeo)
        $new_sale = $this->calculate_sale_price($context);

        // Validar precio de oferta: si >= precio regular, limpiar
        if ($new_sale > 0 && $new_sale >= $new_regular) {
            $new_sale       = 0;
            $applied_keys[] = 'sale_price_cleared';
        }

        return new Calculation_Result(
            new_regular:    $new_regular,
            new_sale:       $new_sale,
            old_regular:    $context->old_regular,
            old_sale:       $context->old_sale,
            applied_rules:  $applied_keys
        );
    }

    /**
     * Calcula el precio de oferta aplicando ratio, margen y redondeo (sin dirección ni exclusión).
     */
    private function calculate_sale_price(Price_Context $context): float
    {
        if (!$context->has_sale_price()) {
            return 0.0;
        }

        $sale_price = $context->old_sale;

        foreach ($this->sale_rules as $rule) {
            $sale_price = $rule->apply($sale_price, $context);
        }

        return $sale_price;
    }
}
