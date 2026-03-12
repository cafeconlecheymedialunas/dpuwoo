<?php
if (!defined('ABSPATH')) exit;

/**
 * Contrato para el repositorio de productos WooCommerce.
 * Abstrae el acceso a datos de productos, permitiendo reemplazar la implementación en tests.
 */
interface Product_Repository_Interface
{
    /**
     * Cuenta el total de productos publicados y actualizables.
     */
    public function count_all_products(): int;

    /**
     * Obtiene un array de IDs de productos paginado.
     *
     * @param int $limit  Cantidad de IDs por lote.
     * @param int $offset Desplazamiento para paginación.
     * @return int[]
     */
    public function get_product_ids_batch(int $limit, int $offset): array;

    /**
     * Carga un producto simple por su ID.
     *
     * @param int $id ID del producto.
     * @return \WC_Product|null Null si no existe o no es válido.
     */
    public function get_product(int $id): ?\WC_Product;

    /**
     * Carga una variación de producto por su ID.
     *
     * @param int $id ID de la variación.
     * @return \WC_Product_Variation|null
     */
    public function get_variation_product(int $id): ?\WC_Product_Variation;

    /**
     * Obtiene los IDs de las variaciones de un producto variable.
     *
     * @param \WC_Product $product Producto variable.
     * @return int[]
     */
    public function get_variations(\WC_Product $product): array;

    /**
     * Guarda el precio regular de un producto.
     *
     * @param \WC_Product $product   Producto a modificar.
     * @param float       $new_price Nuevo precio regular.
     * @return bool True si se guardó correctamente.
     */
    public function save_regular_price(\WC_Product $product, float $new_price): bool;

    /**
     * Guarda el precio de oferta de un producto. Si $new_price es 0, limpia el precio de oferta.
     *
     * @param \WC_Product $product   Producto a modificar.
     * @param float       $new_price Nuevo precio de oferta (0 para limpiar).
     * @return bool True si se guardó correctamente.
     */
    public function save_sale_price(\WC_Product $product, float $new_price): bool;
}
