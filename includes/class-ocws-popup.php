<?php

defined( 'ABSPATH' ) || exit;

class OCWS_Popup {

    public static function output_shipping_popup() {

        $methods = array();

        $shipping_zones = WC_Shipping_Zones::get_zones();
        $chosen_methods = false;
        if (isset(WC()->session)) {
            $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        }
        $chosen_shipping            = $chosen_methods[0] ?? '';
        $available_methods_number   = 0;
        $chosen_method_index        = 0;

        $affs_ds                    = new OCWS_LP_Affiliates();
        $branches_dropdown          = $affs_ds->get_affiliates_dropdown(true);

        $use_simple_cities          = !ocws_use_google_cities_and_polygons();
        $use_polygons               = ocws_use_google_cities_and_polygons();
        $use_google_cities          = ocws_use_google_cities();
        $city_options               = OC_Woo_Shipping_Groups::get_all_locations(true, $use_simple_cities, $use_polygons, $use_google_cities);


        if ($shipping_zones && is_array($shipping_zones)) {
            $count      = 0;
            $cart_total = WC()->cart->cart_contents_total;
            foreach ($shipping_zones as $shipping_zone) {
                $shipping_methods = $shipping_zone['shipping_methods'];
                foreach ($shipping_methods as $shipping_method) {

                    if ( !isset( $shipping_method->enabled ) || 'yes' !== $shipping_method->enabled ) {
                        continue; // not available
                    }

                    // exclude free shipping if cart sum < min shipping min amount
                    if ( $shipping_method->id == 'free_shipping' && $shipping_method->min_amount != 0 ){
                        if ( $cart_total < $shipping_method->min_amount ){
                            continue;
                        }
                    }
                    if (
                        $shipping_method->id == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID && count($branches_dropdown) == 0 ||
                        $shipping_method->id == OCWS_Advanced_Shipping::SHIPPING_METHOD_ID && count($city_options) == 0
                    ) {
                        continue; // considered not available
                    }
                    $is_chosen = ($chosen_shipping && ($chosen_shipping == $shipping_method->id.':'.$shipping_method->instance_id));
                    $methods[] = array(
                        'method_id' => $shipping_method->id,
                        'method_instance_id' => $shipping_method->instance_id,
                        'type' => ($shipping_method->id == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID? 'pickup' : 'shipping'),
                        'is_chosen' => $is_chosen,
                        'title' => ocws_translate_shipping_method_title( $shipping_method->title, $shipping_method->id.':'.$shipping_method->instance_id )
                    );
                    if ($is_chosen) {
                        $chosen_method_index = $count;
                    }
                    $count++;
                    $available_methods_number++;
                }
            }
        }

        if (!$chosen_method_index && count($methods) > 0) {
            $methods[0]['is_chosen'] = true;
        }

        $var = array(
            'available_methods_number' => $available_methods_number,
            'chosen_method_index' => $chosen_method_index,
            'methods' => $methods,
            'pickup_branches' => $branches_dropdown,
            'shipping_locations' => $city_options
        );

        ocws_include_template_part('public/popup.php', null, $var);
    }
}