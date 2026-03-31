<?php
if (!defined('ABSPATH')) exit;

/**
 * Servicio que procesa un lote de IDs de productos aplicando cálculos de precio.
 * Extrae Price_Updater::process_batch() y update_variable_product().
 * Recibe sus dependencias por inyección.
 */
class Batch_Processor
{
    public function __construct(
        private Product_Repository_Interface $product_repo,
        private Price_Calculation_Engine     $engine
    ) {}

    /**
     * Procesa un array de IDs de productos y retorna el resultado agregado del lote.
     *
     * @param int[]         $product_ids    IDs de productos a procesar.
     * @param Exchange_Rate $exchange_rate  Tasa de cambio actual vs anterior.
     * @param array         $settings       Configuración del plugin.
     * @param bool          $simulate       Si true, no guarda precios.
     * @return Batch_Result
     */
    public function process(
        array         $product_ids,
        Exchange_Rate $exchange_rate,
        array         $settings,
        bool          $simulate = false
    ): Batch_Result {
        $result = new Batch_Result();

        $margin          = floatval($settings['margin'] ?? 0);
        $theoretical_pct = round(($exchange_rate->ratio * (1 + $margin / 100) - 1) * 100, 2);

        foreach ($product_ids as $pid) {
            $product = $this->product_repo->get_product($pid);

            if (!$product) {
                $result->add_change($this->make_change_data(
                    $pid, 'Producto no encontrado', 'N/A', 'simple',
                    0, 0, 0, 0, 'error', 'Producto no encontrado'
                ));
                continue;
            }

            if ($product->is_type('variable')) {
                $this->process_variable($product, $exchange_rate, $settings, $simulate, $result, $theoretical_pct);
                continue;
            }

            $this->process_simple($product, $exchange_rate, $settings, $simulate, $result, $theoretical_pct);
        }

        return $result;
    }

    /*==========================================================
    =           Métodos Privados de Procesamiento              =
    ==========================================================*/

    private function process_simple(
        \WC_Product   $product,
        Exchange_Rate $exchange_rate,
        array         $settings,
        bool          $simulate,
        Batch_Result  $result,
        float         $theoretical_pct = 0.0
    ): void {
        $context    = Price_Context::from_product($product, $exchange_rate, $settings);
        $calc       = $this->engine->calculate($context);
        $change     = $this->make_change_data(
            $product->get_id(),
            $product->get_name(),
            $product->get_sku() ?: 'N/A',
            $product->get_type(),
            $calc->old_regular,
            $calc->new_regular,
            $calc->old_sale,
            $calc->new_sale,
            'pending',
            null,
            $calc->applied_rules,
            $theoretical_pct
        );

        if (!$calc->has_any_change()) {
            $change['status'] = 'skipped';
            $change['reason'] = 'Sin cambios';
            $result->add_change($change);
            return;
        }

        if ($simulate) {
            $change['status'] = 'simulated';
        } else {
            $change['status'] = $this->persist_prices($product, $calc);
        }

        $result->add_change($change);
    }

    private function process_variable(
        \WC_Product   $variable,
        Exchange_Rate $exchange_rate,
        array         $settings,
        bool          $simulate,
        Batch_Result  $result,
        float         $theoretical_pct = 0.0
    ): void {
        $variation_ids = $this->product_repo->get_variations($variable);

        foreach ($variation_ids as $variation_id) {
            $variation = $this->product_repo->get_variation_product($variation_id);

            if (!$variation) {
                $result->add_change($this->make_change_data(
                    $variation_id, $variable->get_name() . ' - Variación',
                    'N/A', 'variation', 0, 0, 0, 0, 'error', 'Variación no encontrada'
                ));
                continue;
            }

            $context = Price_Context::from_product($variation, $exchange_rate, $settings);
            $calc    = $this->engine->calculate($context);

            $variation_display = $this->get_variation_display_name($variable, $variation);
            $change = $this->make_change_data(
                $variation_id,
                $variable->get_name() . ' - ' . $variation_display,
                $variation->get_sku() ?: 'N/A',
                'variation',
                $calc->old_regular,
                $calc->new_regular,
                $calc->old_sale,
                $calc->new_sale,
                'pending',
                null,
                $calc->applied_rules,
                $theoretical_pct
            );
            $change['parent_id']      = $variable->get_id();
            $change['variation_name'] = $variation_display;

            if (!$calc->has_any_change()) {
                $change['status'] = 'skipped';
                $change['reason'] = 'Sin cambios';
                $result->add_change($change);
                continue;
            }

            if ($simulate) {
                $change['status'] = 'simulated';
            } else {
                $change['status'] = $this->persist_prices($variation, $calc);
            }

            $result->add_change($change);
        }
    }

    /**
     * Persiste los nuevos precios en WooCommerce y retorna el status resultante.
     */
    private function persist_prices(\WC_Product $product, Calculation_Result $calc): string
    {
        $saved_regular = true;
        $saved_sale    = true;

        if ($calc->has_regular_change() && $calc->new_regular > 0) {
            $saved_regular = $this->product_repo->save_regular_price($product, $calc->new_regular);
        }

        if ($calc->has_sale_change()) {
            $saved_sale = $this->product_repo->save_sale_price($product, $calc->new_sale);
        }

        return ($saved_regular && $saved_sale) ? 'updated' : 'error';
    }

    private function get_variation_display_name(\WC_Product $parent, \WC_Product $variation): string
    {
        $name        = $variation->get_name();
        $parent_name = $parent->get_name();

        if (strpos($name, $parent_name) === 0) {
            return trim(str_replace($parent_name, '', $name), ' -');
        }

        return $name;
    }

    private function make_change_data(
        int     $id,
        string  $name,
        string  $sku,
        string  $type,
        float   $old_reg,
        float   $new_reg,
        float   $old_sale,
        float   $new_sale,
        string  $status,
        ?string $reason          = null,
        array   $applied_rules   = [],
        float   $theoretical_pct = 0.0
    ): array {
        return [
            'product_id'        => $id,
            'product_name'      => $name,
            'product_sku'       => $sku,
            'product_type'      => $type,
            'old_regular_price' => $old_reg,
            'new_regular_price' => $new_reg,
            'old_sale_price'    => $old_sale,
            'new_sale_price'    => $new_sale,
            'percentage_change' => ($old_reg > 0 && $new_reg !== $old_reg) ? $theoretical_pct : 0.0,
            'status'            => $status,
            'reason'            => $reason,
            'applied_rules'     => $applied_rules,
            'rules_summary'     => implode(', ', $applied_rules) ?: 'Sin reglas especiales',
        ];
    }
}
