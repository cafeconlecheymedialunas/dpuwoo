<?php
if (!defined('ABSPATH')) exit;

/**
 * Product_Repository
 * Encapsula TODA la lectura/escritura de productos de WooCommerce.
 */
class Product_Repository
{
    protected static $instance;
    protected $wpdb;

    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_instance()
    {
        return self::init();
    }

    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /* =========================================================================
     * PRODUCT IDS – PAGINACIÓN (SÓLO SELECT, PERFORMANTE)
     * ========================================================================= */

    /**
     * Devuelve el total de productos padre (simples y variables) en estado 'publish'.
     * Los posts con post_type='product' son los que el Price_Updater itera.
     */
    public function count_all_products()
    {
        // **MODIFICACIÓN CLAVE:** Usar COUNT(ID) para mayor claridad y asegurar que la consulta es correcta.
        return (int) $this->wpdb->get_var("
            SELECT COUNT(ID) FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
        ");
    }

    public function get_product_ids_batch($limit = 500, $offset = 0)
    {
        // Se mantiene igual, ya que solo debe traer los IDs de productos padre.
        return $this->wpdb->get_col($this->wpdb->prepare("
            SELECT ID FROM {$this->wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
            ORDER BY ID ASC
            LIMIT %d OFFSET %d
        ", intval($limit), intval($offset)));
    }

    /* =========================================================================
     * PRODUCT LOADING
     * ========================================================================= */

    public function get_product($product_id)
    {
        return wc_get_product($product_id);
    }

    public function get_variations($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return [];
        }
        return $product->get_children(); // IDs de variaciones
    }

    public function get_variation_product($variation_id)
    {
        return wc_get_product($variation_id);
    }

    /* =========================================================================
     * PRICE UPDATE
     * ========================================================================= */

    public function save_regular_price($product, $new_price)
    {
        if (!$product) return false;

        try {
            $product->set_regular_price($new_price);
            $product->save();

            // Doble verificación anti-conflictos de hooks
            $stored = $product->get_regular_price();
            return ((string)$stored === (string)$new_price);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Guarda el precio de oferta (`_sale_price`) en el producto o variación.
     * @param WC_Product $product
     * @param float $new_price Si es 0.0, se limpia el precio de oferta.
     * @return bool
     */
    public function save_sale_price($product, $new_price)
    {
        if (!$product) return false;

        try {
            if ($new_price <= 0.0) {
                $product->set_sale_price(''); 
                $product->set_date_on_sale_to('');
                $product->set_date_on_sale_from('');
            } else {
                $product->set_sale_price($new_price);
            }
            
            $product->save();
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /* =========================================================================
     * HELPERS (MINIMALISTAS)
     * ========================================================================= */

    public function product_exists($product_id)
    {
        return $this->get_product($product_id) !== false;
    }

    public function get_current_regular_price($product)
    {
        return floatval($product->get_regular_price());
    }

    public function get_current_sale_price($product)
    {
        return floatval($product->get_sale_price());
    }
}