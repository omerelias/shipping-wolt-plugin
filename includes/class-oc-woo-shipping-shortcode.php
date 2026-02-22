<?php

defined( 'ABSPATH' ) || exit;

class OC_Woo_Shipping_Shortcode {



    public static function register_shortcodes() {

        // register shortcodes
        add_shortcode( 'ocws_render_shipping_info', array( 'OC_Woo_Shipping_Shortcode', 'render_shipping_info' ) );

        /* usage example
         *
         <?php if (defined( 'OC_WOO_SHIPPING_VERSION' )) { ?>
			<?php echo do_shortcode("[ocws_render_shipping_info order_id='".$order->get_id()."']"); ?>
		 <?php } ?>
         *
         * */
    }

    public static function render_shipping_info( $atts, $content = null ) {

        $current_order = ( isset( $atts['order_id'] ) ) ? wc_get_order( $atts['order_id'] ) : false;

        if (!$current_order) return '';

        $output = '';

        foreach ( $current_order->get_shipping_methods() as $shipping_method ) {
            $shipping_method_id = $shipping_method->get_method_id();
            $shipping = 'oc_woo_advanced_shipping_method';
            $pickup = OCWS_LP_Local_Pickup::PICKUP_METHOD_ID;

            if (substr($shipping_method_id, 0, strlen($shipping)) == $shipping) {

                /* render shipping info */
                $force_hide_slot = (OC_Woo_Shipping_Group_Option::get_common_option('hide_slot_in_admin_mail', '') != 1 ? false : true);
                $output = OC_Woo_Shipping_Info::render_formatted_shipping_info($current_order, $force_hide_slot);
            }
            else if (substr($shipping_method_id, 0, strlen($pickup)) == $pickup) {

                /* render pickup info */
                $force_hide_slot_opt = OCWS_LP_Affiliate_Option::get_common_option('hide_slot_in_admin_mail', '');
                $force_hide_slot = ($force_hide_slot_opt->option_value != 1 ? false : true);
                $force_start_hour_only_opt = OCWS_LP_Affiliate_Option::get_common_option('show_slot_start_only', '');
                $force_start_hour_only = ($force_start_hour_only_opt->option_value != 1 ? false : true);
                $output = OCWS_LP_Pickup_Info::render_formatted_pickup_info($current_order, $force_hide_slot, $force_start_hour_only);
            }
        }

        return $output;
    }


}