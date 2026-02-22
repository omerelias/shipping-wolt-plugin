<?php


class OC_Woo_Local_Pickup_Method extends WC_Shipping_Method {

    const METHOD_ID = 'oc_woo_local_pickup_method';

    const NOTICE_TYPE = 'ocws_lp_notice';

    public function __construct( $instance_id = 0 ) {
        $this->id = self::METHOD_ID;
        $this->instance_id = absint( $instance_id );
        $this->method_title = __( 'OC Local Pickup Method', 'ocws' );

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // Define user set variables
        $this->enabled  = $this->get_option( 'enabled' );
        $this->title     = $this->get_option( 'title' );


        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {

        $this->instance_form_fields = array(
            'enabled' => array(
                'title'     => __( 'Enable/Disable', 'ocws' ),
                'type'       => 'checkbox',
                'label'     => __( 'Enable OC Local Pickup', 'ocws' ),
                'default'     => 'yes'
            ),
            'title' => array(
                'title'     => __( 'Method Title', 'ocws' ),
                'type'       => 'text',
                'description'   => __( 'Local Pickup.', 'ocws' ),
                'default'    => __( 'Local Pickup', 'ocws' ),

            )
        );
    }

    public function is_available( $package ) {
        $is_available = (('yes' === $this->enabled) && ocws_enabled_pickup_branches_exist());
        if (!$is_available) {
            $this->clear_notices();
            return false;
        }
        //error_log('is pickup available: '. ($is_available? 'true' : 'false'));
        return $this->is_applicable( $package );
    }

    public function is_applicable( $package ) {
        error_log('inside pickup is_applicable()');
        //error_log(print_r($package, 1));

        $this->clear_notices();

        $affiliates_ds = new OCWS_LP_Affiliates();
        $affiliates = $affiliates_ds->get_affiliates();

        $enabled_affiliates = false;

        if (is_array($affiliates)) {

            foreach ($affiliates as $affiliate) {
                if ($affiliate['is_enabled'] == '1') {
                    $enabled_affiliates = true;
                    break;
                }
            }
        }

        return $enabled_affiliates;
    }

    public function calculate_shipping( $package = array() ) {

        error_log('pickup calculate_shipping:');
        $default_price = OCWS_LP_Affiliate_Option::get_default_option('pickup_price', 0);
        //error_log( print_r( $package, 1 ) );
        if (!isset($package['destination']) || !isset($package['destination']['ocws_lp_affiliate_id'])) {
            $message = sprintf( __( 'Please select a pickup location to calculate shipping cost', 'ocws' ), $this->title );
            //$this->add_notice( $message, 'notice' );
            error_log('calculate_shipping: no pickup affiliate');
            $this->add_rate( array(
                'id'    => $this->id . $this->instance_id,
                'label' => $this->title,
                'cost'  => $default_price->option_value,
            ) );
            return;
        }

        $aff_id = intval( $package['destination']['ocws_lp_affiliate_id'] );

        $aff_ds = new OCWS_LP_Affiliates();
        $aff = $aff_ds->db_get_affiliate($aff_id);
        if ( ! $aff ) {
            $message = sprintf( __( 'Please select a pickup location to calculate shipping cost', 'ocws' ), $this->title );
            //$this->add_notice( $message, 'notice' );
            error_log('calculate_shipping: no pickup affiliate');
            $this->add_rate( array(
                'id'    => $this->id . $this->instance_id,
                'label' => $this->title,
                'cost'  => $default_price->option_value,
            ) );
            return;
        }

        $aff_opt_model = OCWS_LP_Affiliate_Option::get_option($aff_id, 'pickup_price', 0);
        $opt_price = trim($aff_opt_model->option_value);

        $pickup_price = round( $opt_price, wc_get_price_decimals() );
        $this->add_rate( array(
            'id'    => $this->id . $this->instance_id,
            'label' => $this->title,
            'cost'  => $pickup_price,
        ) );

    }

    /**
     * @param string $method
     * @return bool
     */
    public static function is_ocws_lp($method) {
        return substr($method, 0, strlen(self::METHOD_ID)) == self::METHOD_ID;
    }



    public static function validate_order( $posted ) {

        $packages = WC()->shipping->get_packages();
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

        if( is_array( $chosen_methods ) && in_array( self::METHOD_ID, $chosen_methods ) ) {

            foreach ( $packages as $i => $package ) {
                if ( $chosen_methods[ $i ] != self::METHOD_ID ) {

                    continue;

                }
                $shipping_method = new OC_Woo_Local_Pickup_Method();
                $shipping_method->is_applicable( $package );

            }
        }
    }

    public function add_notice( $message, $notice_type ) {
        if (!OC_Woo_Shipping_Notices::has_notice( $message, $notice_type, 'ocws_lp_notices' )) {
            OC_Woo_Shipping_Notices::add_notice( $message, $notice_type, 'ocws_lp_notices' );
        }
        error_log(print_r( WC()->session->get( 'ocws_lp_notices', array() ), 1));
    }

    public function clear_notices() {

        OC_Woo_Shipping_Notices::clear_notices( true, 'ocws_lp_notices' );
    }
}