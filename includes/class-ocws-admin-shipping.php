<?php

class OCWS_Admin_Shipping {

    public static function init() {
        add_action('woocommerce_admin_order_items_after_shipping', array(__CLASS__, 'woocommerce_admin_order_items_after_shipping'), 20, 1);
    }

    public static function get_cart_items_from_order($order_id) {

        $order = wc_get_order( $order_id );
        if (!$order) {
            return false;
        }
        /**
         * WC_Order $order
         */
        $order_items = $order->get_items();
        $cart = array();
        try {
            foreach ( $order_items as $item ) {
                /**
                 * @var WC_Order_Item_Product $item
                 */
                $product_id     = $item->get_product_id();
                $quantity       = $item->get_quantity();
                $variation_id   = (int) $item->get_variation_id();
                $total = $item->get_total();
                $subtotal = $item->get_subtotal();
                $total_tax = $item->get_total_tax();
                $subtotal_tax = $item->get_subtotal_tax();

                // Add to cart directly.
                $cart_id          = WC()->cart->generate_cart_id( $product_id, $variation_id );
                $cart[ $cart_id ] = array(
                    'key'               => $cart_id,
                    'product_id'        => $product_id,
                    'variation_id'      => $variation_id,
                    'variation'         => array(),
                    'quantity'          => $quantity,
                    'data'              => array(),
                    'line_total'        => $total,
                    'line_subtotal'     => $subtotal,
                    'line_tax'          => $total_tax,
                    'line_subtotal_tax' => $subtotal_tax
                );
            }
        }
        catch (Exception $e) {
            return false;
        }

        return $cart;
    }

    public static function calculate_order_shipping($order_id, $shipping_method) {

        // Reset shipping first
        WC()->shipping()->reset_shipping();
        // Remove all current items from cart
        if ( sizeof( WC()->cart->get_cart() ) > 0 ) {
            WC()->cart->empty_cart();
        }
        if ( !$order_id ) {
            return false;
        }
        $cart_items = self::get_cart_items_from_order($order_id);
        if ( !$cart_items ) {
            return false;
        }
        WC()->cart->set_cart_contents($cart_items);
        // Calculate shipping
        $packages = WC()->cart->get_shipping_packages();
        $packages = self::woocommerce_cart_shipping_packages_filter($packages, $order_id);
        WC()->shipping()->calculate_shipping($packages);
        $available_methods = WC()->shipping()->get_packages();
        return $available_methods;
    }

    public static function woocommerce_cart_shipping_packages_filter( $packages, $order_id ) {

        $order = wc_get_order( $order_id );
        if (!$order) {
            return $packages;
        }
        $order_city = $order->get_shipping_city();
        if (isset($packages[0]) && isset($packages[0]['destination'])) {

            /* don't remove the comment lines below, they are for future polygon feature compatibility
             * if (ocws_use_google_cities_and_polygons()) {
                $packages[0]['destination']['address_coords'] = $order->get_meta('_billing_address_coords');
                $packages[0]['destination']['street'] = $order->get_meta('_billing_street');
                $packages[0]['destination']['house_num'] = $order->get_meta('_billing_house_num');
                $packages[0]['destination']['city_name'] = $order->get_meta('_billing_city_name');
                $packages[0]['destination']['city'] = $order->get_meta('_billing_city_name');
            } else {}*/
            $packages[0]['destination']['city'] = $order_city;

            error_log(print_r($packages[0]['destination'], 1));
        }

        return $packages;
    }

    public static function woocommerce_admin_order_items_after_shipping($order_id) {
        ?>
        <tr class="shipping_edit_options">
            <td colspan="6"><div class="edit">edit</div></td>
        </tr>
        <?php
    }
}