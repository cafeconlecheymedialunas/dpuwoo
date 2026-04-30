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
    private ?Rounding_Rule $rounding_rule;

    /**
     * @param Price_Rule_Interface[] $rules Cadena ordenada de reglas a aplicar.
     */
    public function __construct(private array $rules)
    {
        // Pre-filtrar una vez para evitar instanceof por cada producto en calculate_sale_price()
        $this->sale_rules = array_filter(
            $rules,
            fn($r) => $r instanceof Ratio_Rule || $r instanceof Margin_Rule
        );

        $this->rounding_rule = current(array_filter(
            $rules,
            fn($r) => $r instanceof Rounding_Rule
        )) ?: null;
    }

    /**
     * Calcula el nuevo precio regular y de oferta para el contexto dado.
     *
     * @param Price_Context $context Datos del producto, tasa de cambio y settings.
     * @return Calculation_Result Resultado con nuevos precios y reglas aplicadas.
     */
    public function calculate(Price_Context $context): Calculation_Result
    {
        // Short-circuit: categoría excluida → sin cambios en ningún precio
        if ($this->is_category_excluded($context)) {
            return new Calculation_Result(
                new_regular:   $context->old_regular,
                new_sale:      $context->old_sale,
                old_regular:   $context->old_regular,
                old_sale:      $context->old_sale,
                applied_rules: ['category_exclusion']
            );
        }

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
     * Calcula el precio de oferta aplicando ratio y margen, y luego redondeo final.
     *
     * Usa un contexto sin usd_baseline para que Ratio_Rule aplique
     * siempre el path de ratio (old_sale × ratio), no el del baseline
     * del precio regular (que pertenece exclusivamente al precio regular).
     */
    private function calculate_sale_price(Price_Context $context): float
    {
        if (!$context->has_sale_price()) {
            return 0.0;
        }

        if ($this->is_category_excluded($context)) {
            return $context->old_sale;
        }

        $sale_context = new Price_Context(
            product_id:    $context->product_id,
            old_regular:   $context->old_sale,
            old_sale:      0.0,
            usd_baseline:  0.0,
            exchange_rate: $context->exchange_rate,
            settings:      $context->settings,
            category_ids:  $context->category_ids
        );

        $sale_price = $context->old_sale;

        foreach ($this->sale_rules as $rule) {
            $sale_price = $rule->apply($sale_price, $sale_context);
        }

        if ($this->rounding_rule !== null) {
            $sale_price = $this->rounding_rule->apply($sale_price, $sale_context);
        }

        return $sale_price;
    }

    private function is_category_excluded(Price_Context $context): bool
    {
        $excluded = $context->get_setting('exclude_categories', []);

        if (empty($excluded) || empty($context->category_ids)) {
            return false;
        }

        return !empty(array_intersect($context->category_ids, (array) $excluded));
    }
}
