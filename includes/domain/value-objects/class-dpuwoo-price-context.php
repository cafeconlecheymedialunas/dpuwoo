<?php
if (!defined('ABSPATH')) exit;

/**
 * Value Object que encapsula todo el contexto necesario para calcular el nuevo precio.
 * Usa los logs como fuente de verdad para el precio base (no el meta USD).
 */
class Price_Context
{
    public function __construct(
        public readonly int           $product_id,
        public readonly float         $old_regular,
        public readonly float         $old_sale,
        public readonly float         $usd_baseline,
        public readonly Exchange_Rate $exchange_rate,
        public readonly array         $settings,
        public readonly array         $category_ids = []
    ) {}

    /**
     * Crea un contexto a partir de un producto WooCommerce y una tasa de cambio.
     * 
     * Flujo de USD Baseline (P1: Consistencia crítica):
     * - SETUP: precio_ars se guarda con tasa_del_setup en logs
     * - PRIMER UPDATE: baseline = precio_ars_del_setup / tasa_del_setup
     * - UPDATE sig: usa baseline del último log (fuente de verdad)
     * - FALLBACK: usa precio_actual / tasa_anterior (para primeras updates sin log)
     */
    public static function from_product(
        \WC_Product            $product,
        Exchange_Rate          $exchange_rate,
        array                 $settings,
        ?Log_Repository_Interface $log_repo = null
    ): self {
        $product_id = $product->get_id();
        $old_regular = floatval($product->get_regular_price());
        $old_sale = floatval($product->get_sale_price());
        $usd_baseline = 0.0;

        $currency = $settings['currency'] ?? '';
        $ref_currency = $settings['reference_currency'] ?? 'USD';

        $log_repo ??= Log_Repository::get_instance();
        $last_log = $log_repo->get_last_price_for_product($product_id, $currency, $ref_currency);
        
        if ($last_log && $last_log['dollar_value'] > 0) {
            // P1: Usa baseline del log como fuente de verdad
            $usd_baseline = $last_log['new_regular'] / $last_log['dollar_value'];
        } elseif ($old_regular > 0 && $exchange_rate->previous > 0) {
            // P1: FALLBACK consistente: usa tasa ANTERIOR para calcular baseline
            // Esto es más consistente que usar exchange_rate->ratio que es current/previous
            $usd_baseline = $old_regular / $exchange_rate->previous;
        } elseif ($old_regular > 0 && $exchange_rate->current > 0) {
            // P1: ÚLTIMO RECURSO: si no hay tasa anterior, usa actual
            // (esto ocurre solo en primera ejecución sin histórico)
            $usd_baseline = $old_regular / $exchange_rate->current;
        }

        return new self(
            product_id:    $product_id,
            old_regular:   $old_regular,
            old_sale:      $old_sale,
            usd_baseline:  $usd_baseline,
            exchange_rate: $exchange_rate,
            settings:      $settings,
            category_ids:  $product->get_category_ids()
        );
    }

    public function get_setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function has_sale_price(): bool
    {
        return $this->old_sale > 0;
    }
}
