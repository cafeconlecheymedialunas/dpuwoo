<?php
if (!defined('ABSPATH')) exit;

/**
 * Value Object que encapsula todo el contexto necesario para calcular el nuevo precio.
 * Evita pasar arrays de settings y múltiples parámetros por todas las reglas (Strategy).
 */
class Price_Context
{
    public function __construct(
        public readonly int           $product_id,
        public readonly float         $old_regular,
        public readonly float         $old_sale,
        public readonly Exchange_Rate $exchange_rate,
        public readonly array         $settings,
        public readonly array         $category_ids = []
    ) {}

    /**
     * Crea un contexto a partir de un producto WooCommerce y una tasa de cambio.
     */
    public static function from_product(
        \WC_Product   $product,
        Exchange_Rate $exchange_rate,
        array         $settings
    ): self {
        return new self(
            product_id:    $product->get_id(),
            old_regular:   floatval($product->get_regular_price()),
            old_sale:      floatval($product->get_sale_price()),
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
