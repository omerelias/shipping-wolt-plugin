<?php

defined( 'ABSPATH' ) || exit;

class OCWS_Advanced_Shipping {

    const SHIPPING_METHOD_ID = 'oc_woo_advanced_shipping_method';

    const SHIPPING_METHOD_TAG = 'shipping';

    public static function init() {

        add_action( 'woocommerce_after_checkout_validation', array('OC_Woo_Shipping_Info', 'validate_checkout_posted_data'), 20, 2 );
    }
}