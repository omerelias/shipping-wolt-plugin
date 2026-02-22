<?php
/**
 * OC_Woo_Ajax. AJAX Event Handlers.
 *
 */

use Carbon\Carbon;

defined( 'ABSPATH' ) || exit;

/**
 * OC_Woo_AJAX class.
 */
class OC_Woo_Ajax {

    /**
     * Hook in ajax handlers.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'define_ajax' ), 0 );
        self::add_ajax_events();
    }

    /**
     * Set OC_Woo_Ajax constant and headers.
     */
    public static function define_ajax() {

        if ( ! empty( $_GET['oc-woo-ajax'] ) ) {
            if ( ! defined( 'DOING_AJAX' ) ) {
                define( 'DOING_AJAX', true );
            }
            if ( ! WP_DEBUG || ( WP_DEBUG && ! WP_DEBUG_DISPLAY ) ) {
                @ini_set( 'display_errors', 0 ); // Turn off display_errors during AJAX events to prevent malformed JSON.
            }
            $GLOBALS['wpdb']->hide_errors();
        }
    }

    /**
     * Hook in methods - uses WordPress ajax handlers (admin-ajax).
     */
    public static function add_ajax_events() {
        $ajax_events_nopriv = array(
            'checkout_save_shipping_info',
            'set_shipping_city',
            'set_pickup_branch',
            'check_cart_availability_for_date',
            'fetch_slots_for_city',
            'fetch_slots_for_coords',
            'fetch_state_for_coords',
            'fetch_slots_for_aff',
            'get_streets',
            'do_ajax_cities_import'
        );

        foreach ( $ajax_events_nopriv as $ajax_event ) {
            add_action( 'wp_ajax_oc_woo_shipping_' . $ajax_event, array( __CLASS__, $ajax_event ) );
            add_action( 'wp_ajax_nopriv_oc_woo_shipping_' . $ajax_event, array( __CLASS__, $ajax_event ) );
        }

        $ajax_events = array(
            'groups_save_changes',
            'add_group',
            'group_add_location',
            'group_add_gm_city',
            'group_add_polygon',
            'group_edit_polygon',
            'group_edit_streets',
            'group_save_changes',
            'export_orders',
            'export_orders_for_production',
            'export_orders_for_packaging',
            'export_sales',
            'export_orders_report',
            'add_affiliate',
            'affiliates_save_changes',
            'affiliate_save_changes',
            'admin_add_city_suggestions'
        );

        if (OC_WOO_USE_COMPANIES) {
            $ajax_events[] = 'add_company';
            $ajax_events[] = 'companies_save_changes';
        }

        foreach ( $ajax_events as $ajax_event ) {
            add_action( 'wp_ajax_oc_woo_shipping_' . $ajax_event, array( __CLASS__, $ajax_event ) );
        }

    }

    public static function export_orders() {

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $export = new OC_Woo_Export();

        $export->process_shipping_data_export();

    }

    public static function export_orders_for_production() {

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        if (defined('OC_WOO_USE_OPENSEA_STYLE_EXPORT') && OC_WOO_USE_OPENSEA_STYLE_EXPORT) {
            $export = new OC_Woo_Export_For_Production_Opensea();
        }
        else {
            $export = new OC_Woo_Export_For_Production_Adv();
        }

        $export->process_shipping_data_export();

    }

    public static function export_sales() {

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $export = new OC_Woo_Export_Sales_Report();
        $export->process_shipping_data_export();

    }

    public static function export_orders_report() {

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $export = new OC_Woo_Export_Orders_Report();
        $export->process_shipping_data_export();

    }

    public static function export_orders_for_packaging() {

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $export = new OC_Woo_Export_For_Packaging();

        $export->process_shipping_data_export();

    }

    public static function checkout_save_shipping_info() {

        $shipping_info = '';
        if (isset($_POST['ocws_shipping_info']) && isset($_POST['ocws_shipping_info']['date'])) {
            $shipping_info = $_POST['ocws_shipping_info'];
            OC_Woo_Shipping_Info::save_shipping_info($shipping_info);

            $html = '';
            ob_start();

            ocws_render_shipping_additional_fields();

            $html .= ob_get_clean();

            wp_send_json_success(
                array(
                    'data' => $shipping_info,
                    'fragment' => $html,
                    //'wc_session' => WC()->checkout->get_value('billing_city')
                )
            );
        }
        else {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

    }

    public static function group_add_location() {

        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['group_id'], $_POST['location_code'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $group_id     = wc_clean( wp_unslash( $_POST['group_id'] ) );
        $group        = new OC_Woo_Shipping_Group( $group_id );
        $location_code = wc_clean( wp_unslash( $_POST['location_code'] ) );

        $location_code = explode(':', $location_code, 2);
        $location_code = $location_code[1];

        if (empty($location_code)) {
            wp_send_json_error( 'empty_location' );
            wp_die();
        }

        $location_name  = ocws_get_city_title($location_code);

        $group->save_new_location( $location_code, 0, $location_name, 'city' );

        $use_simple_cities = !ocws_use_google_cities_and_polygons();
        $use_polygons = ocws_use_google_cities_and_polygons();
        $use_google_cities = ocws_use_google_cities();
        wp_send_json_success(
            array(
                'location_code' => $location_code,
                'group_id'     => $group->get_id(),
                'group_name'   => $group->get_group_name(),
                'locations'     => $group->get_group_locations_response($use_simple_cities, $use_polygons, $use_google_cities),
            )
        );
    }

    public static function group_add_gm_city() {

        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['group_id'], $_POST['location_code'], $_POST['location_name'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $group_id     = wc_clean( wp_unslash( $_POST['group_id'] ) );
        $group        = new OC_Woo_Shipping_Group( $group_id );
        $location_code = wc_clean( wp_unslash( $_POST['location_code'] ) );
        $location_name = wc_clean( wp_unslash( $_POST['location_name'] ) );

        if (empty($location_code) || empty($location_name)) {
            wp_send_json_error( 'empty_location' );
            wp_die();
        }

        $group->save_new_gm_city( $location_code, 0, $location_name, 'city' );

        $use_simple_cities = !ocws_use_google_cities_and_polygons();
        $use_polygons = ocws_use_google_cities_and_polygons();
        $use_google_cities = ocws_use_google_cities();
        wp_send_json_success(
            array(
                'location_code' => $location_code,
                'group_id'     => $group->get_id(),
                'group_name'   => $group->get_group_name(),
                'locations'     => $group->get_group_locations_response($use_simple_cities, $use_polygons, $use_google_cities),
            )
        );
    }

    public static function group_add_polygon() {

        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['group_id'], $_POST['polygon_name'], $_POST['polygon_data'], $_POST['polygon_map_center'], $_POST['polygon_map_zoom'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        if (!is_array($_POST['polygon_data']) || !is_array($_POST['polygon_map_center']) || empty($_POST['polygon_map_zoom'])) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        $group_id     = wc_clean( wp_unslash( $_POST['group_id'] ) );
        $group        = new OC_Woo_Shipping_Group( $group_id );

        $polygon_name = wc_clean( wp_unslash( $_POST['polygon_name'] ) );

        if ($polygon_name == '') {
            $polygon_name = __('Custom polygon', 'ocws');
        }

        $location_code = md5(rand(0, 9999) . time() . rand(0, 9999));

        $polygon_data = array(
            'gm_shapes' => wc_clean( wp_unslash( $_POST['polygon_data'] ) ),
            'gm_center' => wc_clean( wp_unslash( $_POST['polygon_map_center'] ) ),
            'gm_zoom' => wc_clean( wp_unslash( $_POST['polygon_map_zoom'] ) )
        );

        $group->save_new_location( $location_code, 0, $polygon_name, 'polygon', true, serialize($polygon_data) );

        $use_simple_cities = !ocws_use_google_cities_and_polygons();
        $use_polygons = ocws_use_google_cities_and_polygons();
        $use_google_cities = ocws_use_google_cities();
        wp_send_json_success(
            array(
                'location_code' => $location_code,
                'group_id'     => $group->get_id(),
                'group_name'   => $group->get_group_name(),
                'locations'     => $group->get_group_locations_response($use_simple_cities, $use_polygons, $use_google_cities),
            )
        );
    }

    public static function group_edit_polygon() {

        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['group_id'], $_POST['location_code'], $_POST['polygon_name'], $_POST['polygon_data'], $_POST['polygon_map_center'], $_POST['polygon_map_zoom'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $location_code = wc_clean( wp_unslash( $_POST['location_code'] ) );

        if (!is_array($_POST['polygon_data']) || !is_array($_POST['polygon_map_center']) || empty($_POST['polygon_map_zoom']) || empty($location_code)) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        $group_id     = wc_clean( wp_unslash( $_POST['group_id'] ) );
        $group        = new OC_Woo_Shipping_Group( $group_id );

        $polygon_name = wc_clean( wp_unslash( $_POST['polygon_name'] ) );

        if ($polygon_name == '') {
            $polygon_name = __('Custom polygon', 'ocws');
        }

        $polygon_data = array(
            'gm_shapes' => wc_clean( wp_unslash( $_POST['polygon_data'] ) ),
            'gm_center' => wc_clean( wp_unslash( $_POST['polygon_map_center'] ) ),
            'gm_zoom' => wc_clean( wp_unslash( $_POST['polygon_map_zoom'] ) )
        );

        $group->update_location_data($location_code, array('polygon_name' => $polygon_name, 'gm_shapes' => serialize($polygon_data)));

        $use_simple_cities = !ocws_use_google_cities_and_polygons();
        $use_polygons = ocws_use_google_cities_and_polygons();
        $use_google_cities = ocws_use_google_cities();
        wp_send_json_success(
            array(
                'location_code' => $location_code,
                'group_id'     => $group->get_id(),
                'group_name'   => $group->get_group_name(),
                'locations'     => $group->get_group_locations_response($use_simple_cities, $use_polygons, $use_google_cities),
            )
        );
    }

    public static function group_edit_streets() {

        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['group_id'], $_POST['location_code'], $_POST['streets_data'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $location_code = wc_clean( wp_unslash( $_POST['location_code'] ) );

        if (!is_array($_POST['streets_data']) || empty($location_code)) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        $group_id     = wc_clean( wp_unslash( $_POST['group_id'] ) );
        $group        = new OC_Woo_Shipping_Group( $group_id );

        $streets_data = wc_clean( wp_unslash( $_POST['streets_data'] ) );

        error_log(print_r($_POST['streets_data'], 1));

        if (null === $streets_data || !is_array($streets_data)) {
            $streets_data = [];
        }

        $streets = array();

        foreach ($streets_data as $street_item) {
            if (isset($street_item['id']) && isset($street_item['name'])) {
                $streets[$street_item['id']] = $street_item['name'];
            }
        }

        asort($streets);

        error_log(print_r($streets, 1));

        $group->update_location_data($location_code, array('gm_streets' => serialize($streets)));

        $use_simple_cities = !ocws_use_google_cities_and_polygons();
        $use_polygons = ocws_use_google_cities_and_polygons();
        $use_google_cities = ocws_use_google_cities();
        wp_send_json_success(
            array(
                'location_code' => $location_code,
                'group_id'     => $group->get_id(),
                'group_name'   => $group->get_group_name(),
                'locations'     => $group->get_group_locations_response($use_simple_cities, $use_polygons, $use_google_cities),
            )
        );
    }

    /**
     * Handle submissions from admin/js/oc-woo-shipping-groups.js Backbone model.
     */
    public static function groups_save_changes() {
        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['changes'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $changes = wp_unslash( $_POST['changes'] );
        foreach ( $changes as $group_id => $data ) {
            if ( isset( $data['deleted'] ) ) {
                if ( isset( $data['newRow'] ) ) {
                    // So the user added and deleted a new row.
                    // That's fine, it's not in the database anyways. NEXT!
                    continue;
                }
                OC_Woo_Shipping_Groups::delete_group($group_id);
                continue;
            }

            $group_data = array_intersect_key(
                $data,
                array(
                    'group_id'    => 1,
                    'group_order' => 1,
                    'is_enabled' => 0
                )
            );

            //error_log(print_r($group_data, 1));

            if ( isset( $group_data['group_id'] ) ) {
                $group = new OC_Woo_Shipping_Group( $group_data['group_id'] );

                if ( isset( $group_data['group_order'] ) ) {
                    $group->set_group_order( $group_data['group_order'] );
                }

                if ( isset( $group_data['is_enabled'] ) ) {
                    //error_log(print_r($group, 1));
                    $group->set_is_enabled( $group_data['is_enabled'] );
                }

                $group->save();
            }
        }

        wp_send_json_success(
            array(
                'groups' => OC_Woo_Shipping_Groups::get_groups(),
            )
        );
    }

    /**
     * Handle submissions from admin/js/oc-woo-shipping-groups.js Backbone model.
     */
    public static function group_save_changes() {

        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['group_id'], $_POST['changes'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        global $wpdb;
        $log_request = array();

        $group_id = wc_clean( wp_unslash( $_POST['group_id'] ) );
        $group    = new OC_Woo_Shipping_Group( $group_id );
        $changes = wp_unslash( $_POST['changes'] );

        if ( isset( $changes['group_name'] ) ) {
            $group->set_group_name( wc_clean( $changes['group_name'] ) );
        }

        if ( isset( $changes['locations'] ) ) {
            foreach ( $changes['locations'] as $location_code => $data ) {

                if ( isset( $data['deleted'] ) ) {
                    $group->delete_location($location_code);
                    continue;
                }

                $location_data = array_intersect_key(
                    $data,
                    array(
                        'options' => array(),
                        'location_order' => 1,
                        'is_enabled'      => 1,
                    )
                );

                if ( isset( $location_data['location_order'] ) ) {
                    $group->update_location_data($location_code, array('location_order' => absint( $location_data['location_order'] )));
                }

                if ( isset( $location_data['is_enabled'] ) ) {
                    $is_enabled = absint( '1' === $location_data['is_enabled'] );
                    $group->update_location_data($location_code, array('is_enabled' => $is_enabled));
                }

                if (isset( $location_data['options'] ) && is_array( $location_data['options'] )) {
                    foreach ($location_data['options'] as $optname => $optdata) {

                        if (in_array( $optname, array('shipping_price', 'min_total', 'price_depending'))) {
                            if (isset( $optdata['use_default'])) {
                                OC_Woo_Shipping_Group_Option::update_location_option_ud($location_code, $optname, $optdata['use_default']);
                                $log_request[$optname.'_use_default'] = $optdata['use_default'];
                            }
                            if (isset( $optdata['option_value'])) {
                                OC_Woo_Shipping_Group_Option::update_location_option_value($location_code, $optname, $optdata['option_value']);
                                $log_request[$optname.'_option_value'] = $optdata['option_value'];
                            }
                        }
                    }
                    wp_cache_flush();
                }
            }
        }

        $group->save(false);

        $use_simple_cities = !ocws_use_google_cities_and_polygons();
        $use_polygons = ocws_use_google_cities_and_polygons();
        $use_google_cities = ocws_use_google_cities();
        wp_send_json_success(
            array(
                'group_id'     => $group->get_id(),
                'group_name'   => $group->get_group_name(),
                'locations'     => $group->get_group_locations_response($use_simple_cities, $use_polygons, $use_google_cities),
                'request_log'  => $log_request
            )
        );
    }

    /**
     * Handle submissions from admin/js/oc-woo-shipping-groups.js Backbone model.
     */
    public static function add_group() {
        if ( ! isset( $_POST['oc_woo_shipping_groups_nonce'], $_POST['group_name'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_groups_nonce'] ), 'oc_woo_shipping_groups_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $group_name = wp_unslash( $_POST['group_name'] );
        if (empty($group_name)) {
            $group_name = 'New group';
        }
        $group = new OC_Woo_Shipping_Group();
        $group->set_group_name($group_name);
        $group->save();

        wp_send_json_success(
            array(
                'groups' => OC_Woo_Shipping_Groups::get_groups(),
            )
        );
    }

    public static function set_shipping_city() {

        /*{
            "action": "oc_woo_shipping_set_shipping_city",
            "formData": "popup-shipping-method=oc_woo_advanced_shipping_method%3A5&selected-city=187&order_expedition_date=&order_expedition_slot_start=&order_expedition_slot_end=&slots_state="
        }*/

        /* alt. formData for polygon
        popup-shipping-method=oc_woo_advanced_shipping_method%3A5&
        billing_google_autocomplete=%D7%9E%D7%A9%D7%94%20%D7%A9%D7%A8%D7%AA%2064%2C%20%D7%A2%D7%A4%D7%95%D7%9C%D7%94%2C%20%D7%99%D7%A9%D7%A8%D7%90%D7%9C&
        billing_city=%D7%A2%D7%A4%D7%95%D7%9C%D7%94&
        billing_city_name=%D7%A2%D7%A4%D7%95%D7%9C%D7%94&
        billing_street=%D7%9E%D7%A9%D7%94%20%D7%A9%D7%A8%D7%AA&
        billing_house_num=64&
        billing_address_coords=(32.60592259999999%2C%2035.28192979999999)&
        order_expedition_date=28%2F11%2F2022&
        order_expedition_slot_start=10%3A00&
        order_expedition_slot_end=20%3A00&
        slots_state=
         * */

        parse_str($_POST['formData'], $formdata);

        $shipping = (isset($formdata['popup-shipping-method'])? $formdata['popup-shipping-method'] : '');
        $shipping = str_replace(':', '', $shipping);

        $city_id = 0;

        if (isset($formdata['selected-city'])) {
            $city_id = $formdata['selected-city'];
        }
        else if (isset($formdata['billing_city'])) {
            $city_id = $formdata['billing_city'];
        }

        if (isset($formdata['billing_address_coords'])) {
            WC()->session->set('chosen_address_coords', $formdata['billing_address_coords'] );
        }
        if (isset($formdata['billing_street'])) {
            WC()->session->set('chosen_street', $formdata['billing_street'] );
        }
        if (isset($formdata['billing_house_num'])) {
            WC()->session->set('chosen_house_num', $formdata['billing_house_num'] );
        }
        if (isset($formdata['billing_city_name'])) {
            WC()->session->set('chosen_city_name', $formdata['billing_city_name'] );
        }
        if (isset($formdata['billing_city_code'])) {
            WC()->session->set('chosen_city_code', $formdata['billing_city_code'] );
        }

        $date = (isset($formdata['order_expedition_date']) ? $formdata['order_expedition_date'] : '');

        $slot_start = (isset($formdata['order_expedition_slot_start']) ? $formdata['order_expedition_slot_start'] : '');

        $slot_end = (isset($formdata['order_expedition_slot_end']) ? $formdata['order_expedition_slot_end'] : '');

        $session_shipping_info = array(
            'date' => '',
            'slot_start' => '',
            'slot_end' => ''
        );

        if ($city_id) {

            WC()->customer->set_billing_city($city_id);
            WC()->customer->set_shipping_city($city_id);

            WC()->session->set('chosen_shipping_city', $city_id );

            if ($date) {
                $session_shipping_info['date'] = $date;
                if ($slot_start && $slot_end) {
                    $session_shipping_info['slot_start'] = $slot_start;
                    $session_shipping_info['slot_end'] = $slot_end;
                }
                else {
                    $session_shipping_info['slot_start'] = '';
                    $session_shipping_info['slot_end'] = '';
                }
            }
            else {
                $session_shipping_info['date'] = '';
                $session_shipping_info['slot_start'] = '';
                $session_shipping_info['slot_end'] = '';
            }
        }
        else {
            WC()->session->set('chosen_shipping_city', null );
            $session_shipping_info['date'] = '';
            $session_shipping_info['slot_start'] = '';
            $session_shipping_info['slot_end'] = '';
        }

        WC()->session->set('chosen_shipping_methods', ($shipping? array( $shipping ) : null) );

        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        OC_Woo_Shipping_Info::save_shipping_info($session_shipping_info);

        $removed_items = array();
        if ( $date && function_exists( 'ocws_remove_cart_items_unavailable_for_date' ) ) {
            $removed_items = ocws_remove_cart_items_unavailable_for_date( $date );
        }

        wp_send_json_success(
            array(
                'city'          => $city_id,
                'formdata'      => $formdata,
                'removed_items' => $removed_items,
                'refresh_page'  => true,
            )
        );
    }

    /**
     * Return cart product names that would be unavailable for the given date (for popup notice).
     */
    public static function check_cart_availability_for_date() {
        $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        if ( empty( $date ) || ! function_exists( 'ocws_get_cart_items_unavailable_for_date' ) ) {
            wp_send_json_success( array( 'unavailable_names' => array() ) );
            return;
        }
        $unavailable = ocws_get_cart_items_unavailable_for_date( $date );
        wp_send_json_success( array( 'unavailable_names' => $unavailable ) );
    }

    public static function set_pickup_branch() {

        /*{
            "action": "oc_woo_shipping_set_pickup_branch",
            "formData": "popup-shipping-method=oc_woo_local_pickup_method%3A7&ocws_lp_pickup_aff_id=2&ocws_lp_pickup_date=10%2F05%2F2022&ocws_lp_pickup_slot_start=10%3A00&ocws_lp_pickup_slot_end=12%3A00"
        }*/

        parse_str($_POST['formData'], $formdata);

        $shipping = (isset($formdata['popup-shipping-method'])? $formdata['popup-shipping-method'] : '');
        $shipping = str_replace(':', '', $shipping);

        $aff_id = 0;
        $_POST['ocws_lp_popup'] = array();

        if (isset($formdata['ocws_lp_pickup_aff_id'])) {
            $aff_id = intval($formdata['ocws_lp_pickup_aff_id']);
            $_POST['ocws_lp_popup']['ocws_lp_pickup_aff_id'] = $aff_id; // to be used in 'woocommerce_cart_shipping_packages' filter for ['destination']['ocws_lp_pickup_aff_id'] of a package
        }

        $date = (isset($formdata['ocws_lp_pickup_date']) ? $formdata['ocws_lp_pickup_date'] : '');

        $slot_start = (isset($formdata['ocws_lp_pickup_slot_start']) ? $formdata['ocws_lp_pickup_slot_start'] : '');

        $slot_end = (isset($formdata['ocws_lp_pickup_slot_end']) ? $formdata['ocws_lp_pickup_slot_end'] : '');

        $session_shipping_info = array(
            'aff_id' => 0,
            'date' => '',
            'slot_start' => '',
            'slot_end' => ''
        );

        if ($aff_id) {

            WC()->session->set('chosen_pickup_aff', $aff_id );
            $session_shipping_info['aff_id'] = $aff_id;

            if ($date) {
                $session_shipping_info['date'] = $date;
                if ($slot_start && $slot_end) {
                    $session_shipping_info['slot_start'] = $slot_start;
                    $session_shipping_info['slot_end'] = $slot_end;
                }
                else {
                    $session_shipping_info['slot_start'] = '';
                    $session_shipping_info['slot_end'] = '';
                }
            }
            else {
                $session_shipping_info['date'] = '';
                $session_shipping_info['slot_start'] = '';
                $session_shipping_info['slot_end'] = '';
            }
        }
        else {
            WC()->session->set('chosen_pickup_aff', null );
            $session_shipping_info['date'] = '';
            $session_shipping_info['slot_start'] = '';
            $session_shipping_info['slot_end'] = '';
        }

        WC()->session->set('chosen_shipping_methods', ($shipping? array( $shipping ) : null) );

        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        OCWS_LP_Pickup_Info::save_pickup_info($session_shipping_info);

        wp_send_json_success(
            array(
                'aff' => $aff_id,
                'formdata' => $formdata
            )
        );
    }

    public static function fetch_slots_for_city() {

        $resp = '';

        $shipping = isset($_POST['shipping_method'])? $_POST['shipping_method'] : '';
        $shipping = str_replace(':', '', $shipping);

        if (empty($shipping)) {
            wp_send_json_success(
                array(
                    'resp' => $resp,
                )
            );
        }

        WC()->session->set('chosen_shipping_methods', array( $shipping ) );

        ob_start();

        ocws_render_shipping_additional_fields();

        $resp = ob_get_clean();

        wp_send_json_success(
            array(
                'resp' => $resp,
            )
        );
    }

    public static function fetch_slots_for_coords() {

        $resp = '';

        $shipping = isset($_POST['shipping_method'])? $_POST['shipping_method'] : '';
        $shipping = str_replace(':', '', $shipping);

        if (empty($shipping)) {
            wp_send_json_success(
                array(
                    'resp' => $resp,
                )
            );
        }

        WC()->session->set('chosen_shipping_methods', array( $shipping ) );

        ob_start();

        ocws_render_shipping_additional_fields();

        $resp = ob_get_clean();

        wp_send_json_success(
            array(
                'resp' => $resp,
            )
        );
    }

    public static function fetch_state_for_coords() {

        $resp = '';

        $location_code = 0;
        if (ocws_use_google_cities_and_polygons()) {

            $location_code = OC_Woo_Shipping_Polygon::get_location_code_by_post_data($_POST);
        }
        if (empty($location_code) || !ocws_is_location_enabled($location_code)) {
            $oos_message = ocws_get_multilingual_option('ocws_common_out_of_service_area_message');
            if (empty($oos_message)) {
                $general_options_defaults = OC_Woo_Shipping_Group_Option::get_general_options_defaults();
                if (isset($general_options_defaults['out_of_service_area_message'])) {
                    $oos_message = $general_options_defaults['out_of_service_area_message'];
                }
            }
            wp_send_json_success(
                array(
                    'resp' => $oos_message,
                )
            );
        }
        wp_send_json_success(
            array(
                'resp' => __('We ship to your location. Press Continue to proceed.', 'ocws'),
                'post' => $_POST
            )
        );
    }


    public static function fetch_slots_for_aff() {

        $resp = '';

        $shipping = isset($_POST['shipping_method'])? $_POST['shipping_method'] : '';
        $shipping = str_replace(':', '', $shipping);

        if (empty($shipping)) {
            wp_send_json_success(
                array(
                    'resp' => $resp,
                )
            );
        }

        WC()->session->set('chosen_shipping_methods', array( $shipping ) );

        ob_start();

        OCWS_LP_Local_Pickup::render_pickup_additional_fields();

        $resp = ob_get_clean();

        wp_send_json_success(
            array(
                'resp' => $resp,
            )
        );
    }

    public static function get_streets() {

        $resp = [];

        //error_log(print_r($_GET, 1));

        $term = strtolower(trim( isset($_GET['search_term'])? $_GET['search_term'] : '' ));
        $location_code = trim(isset($_GET['city_code'])? $_GET['city_code'] : '');

        if (empty($location_code)) {
            wp_send_json_success(
                array(
                    'results' => $resp,
                )
            );
        }

        $data_store = new OC_Woo_Shipping_Group_Data_Store();
        $city_data = $data_store->read_location_data($location_code);

        if (false === $city_data) {
            wp_send_json_success(
                array(
                    'results' => $resp,
                )
            );
        }

        //error_log(print_r($city_data, 1));

        $streets_data = @unserialize($city_data->gm_streets);

        //error_log(print_r($streets_data, 1));

        if (false === $streets_data || !is_array($streets_data)) {
            wp_send_json_success(
                array(
                    'results' => $resp,
                )
            );
        }

        $res = array();

        if (empty($term)) {

            foreach ($streets_data as $key => $value) {

                $res[] = array(
                    'id' => $value, //$key,
                    'text' => $value
                );
            }

        }
        else {

            foreach ($streets_data as $key => $value) {

                if (strstr( strtolower($value), $term )) {
                    $res[] = array(
                        'id' => $value, //$key,
                        'text' => $value
                    );
                }
            }

        }



        wp_send_json_success(
            array(
                'results' => $res,
            )
        );
    }

    /**
     * Handle submissions from admin/js/oc-woo-shipping-groups.js Backbone model.
     */
    public static function add_company() {
        if ( ! isset( $_POST['oc_woo_shipping_companies_nonce'], $_POST['company_name'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_companies_nonce'] ), 'oc_woo_shipping_companies_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $company_name = wp_unslash( $_POST['company_name'] );
        if (!empty($company_name)) {
            OC_Woo_Shipping_Companies::add_company($company_name);
        }

        wp_send_json_success(
            array(
                'companies' => OC_Woo_Shipping_Companies::get_companies(),
            )
        );
    }

    /**
     * Handle submissions from admin/js/oc-woo-shipping-companies.js Backbone model.
     */
    public static function companies_save_changes() {
        if ( ! isset( $_POST['oc_woo_shipping_companies_nonce'], $_POST['changes'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['oc_woo_shipping_companies_nonce'] ), 'oc_woo_shipping_companies_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $changes = wp_unslash( $_POST['changes'] );
        foreach ( $changes as $company_id => $data ) {
            if ( isset( $data['deleted'] ) ) {
                if ( isset( $data['newRow'] ) ) {
                    // So the user added and deleted a new row.
                    // That's fine, it's not in the database anyways. NEXT!
                    continue;
                }
                OC_Woo_Shipping_Companies::delete_company($company_id);
                continue;
            }

            $company_data = array_intersect_key(
                $data,
                array(
                    'company_id'    => 1,
                    'company_name' => ''
                )
            );

            //error_log(print_r($group_data, 1));

            if ( isset( $company_data['company_id'] ) ) {

                if ( isset( $company_data['company_name'] ) || !empty($company_data['company_name']) ) {
                    OC_Woo_Shipping_Companies::update_company($company_id, $company_data['company_name']);
                }
            }
        }

        wp_send_json_success(
            array(
                'companies' => OC_Woo_Shipping_Companies::get_companies(),
            )
        );
    }

    /**
     * Ajax callback for importing one batch of cities from a CSV.
     */
    public static function do_ajax_cities_import() {
        global $wpdb;

        check_ajax_referer( 'ocws-cities-import', 'security' );

        if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['file'] ) || ! isset( $_POST['group_id'] ) ) { // PHPCS: input var ok.
            wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import products.', 'ocws' ) ) );
        }

        $group = new OC_Woo_Shipping_Group( intval($_POST['group_id']) );

        include_once OCWS_PATH . '/includes/importers/class-ocws-cities-csv-importer.php';
        include_once OCWS_PATH . '/includes/importers/class-ocws-cities-csv-importer-controller.php';

        $file   = wc_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
        $params = array(
            'delimiter'       => ! empty( $_POST['delimiter'] ) ? wc_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
            'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
            'mapping'         => isset( $_POST['mapping'] ) ? (array) wc_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
            'lines'           => 30,
            'parse'           => true,
        );

        // Log failures.
        if ( 0 !== $params['start_pos'] ) {
            $error_log = array_filter( (array) get_user_option( 'ocws_cities_import_error_log' ) );
        } else {
            $error_log = array();
        }

        $importer         = OCWS_Cities_CSV_Importer_Controller::get_importer( $file, $params );
        $results          = $importer->import($group->get_id());
        $percent_complete = $importer->get_percent_complete();
        $error_log        = array_merge( $error_log, $results['skipped'] );

        update_user_option( get_current_user_id(), 'ocws_cities_import_error_log', $error_log );

        if ( 100 === $percent_complete ) {

            // Send success.
            wp_send_json_success(
                array(
                    'position'   => 'done',
                    'percentage' => 100,
                    'url'        => add_query_arg( array( '_wpnonce' => wp_create_nonce( 'ocws-csv-importer' ) ), admin_url( 'admin.php?page=ocws&tab=group'. $group->get_id() . '&group-action=import&step=done' ) ),
                    'imported'   => count( $results['imported'] ),
                    'skipped'    => count( $results['skipped'] ),
                )
            );
        } else {
            wp_send_json_success(
                array(
                    'position'   => $importer->get_file_position(),
                    'percentage' => $percent_complete,
                    'imported'   => count( $results['imported'] ),
                    'skipped'    => count( $results['skipped'] ),
                )
            );
        }
    }


    /**
     * Handle submissions from admin/js/local-pickup/ocws-lp-affiliates.js Backbone model.
     */
    public static function add_affiliate() {
        if ( ! isset( $_POST['ocws_lp_affiliates_nonce'], $_POST['aff_name'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['ocws_lp_affiliates_nonce'] ), 'ocws_lp_affiliates_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $aff_name = wp_unslash( $_POST['aff_name'] );
        if (empty($aff_name)) {
            $aff_name = 'New branch';
        }
        $aff = new OCWS_LP_Affiliate(0);
        $aff->set_aff_name($aff_name);
        $aff->save();

        $aff_ds = new OCWS_LP_Affiliates();

        wp_send_json_success(
            array(
                'affiliates' => $aff_ds->get_affiliates()
            )
        );
    }


    /**
     * Handle submissions from admin/js/local-pickup/ocws-lp-affiliates.js Backbone model.
     */
    public static function affiliates_save_changes() {
        if ( ! isset( $_POST['ocws_lp_affiliates_nonce'], $_POST['changes'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['ocws_lp_affiliates_nonce'] ), 'ocws_lp_affiliates_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        $aff_ds = new OCWS_LP_Affiliates();

        $changes = wp_unslash( $_POST['changes'] );
        foreach ( $changes as $aff_id => $data ) {
            if ( isset( $data['deleted'] ) ) {
                if ( isset( $data['newRow'] ) ) {
                    // So the user added and deleted a new row.
                    // That's fine, it's not in the database anyways. NEXT!
                    continue;
                }

                $aff_ds->db_delete_affiliate_by_id($aff_id);
                continue;
            }

            $aff_data = array_intersect_key(
                $data,
                array(
                    'aff_id'    => 0,
                    'aff_order' => 1,
                    'is_enabled' => 0
                )
            );

            //error_log(print_r($aff_data, 1));

            if ( isset( $aff_data['aff_id'] ) ) {
                $aff_obj = $aff_ds->db_get_affiliate($aff_data['aff_id']);

                if ($aff_obj) {

                    $aff = new OCWS_LP_Affiliate( $aff_obj );

                    if ( isset( $aff_data['aff_order'] ) ) {
                        $aff->set_aff_order( $aff_data['aff_order'] );
                    }

                    if ( isset( $aff_data['is_enabled'] ) ) {
                        error_log(print_r($aff, 1));
                        $aff->set_is_enabled( $aff_data['is_enabled'] );
                    }

                    $aff->save();
                }
            }
        }

        wp_send_json_success(
            array(
                'affiliates' => $aff_ds->get_affiliates()
            )
        );
    }


    /**
     * Handle submissions from admin/js/local-pickup/ocws-lp-affiliat-edit.js Backbone model.
     */
    public static function affiliate_save_changes() {

        if ( ! isset( $_POST['ocws_lp_affiliates_nonce'], $_POST['aff_id'], $_POST['changes'] ) ) {
            wp_send_json_error( 'missing_fields' );
            wp_die();
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['ocws_lp_affiliates_nonce'] ), 'ocws_lp_affiliates_nonce' ) ) {
            wp_send_json_error( 'bad_nonce' );
            wp_die();
        }

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }

        global $wpdb;
        $log_request = array();

        $aff_id = intval( wc_clean( wp_unslash( $_POST['aff_id'] ) ) );

        $aff_ds = new OCWS_LP_Affiliates();
        $aff_obj = $aff_ds->db_get_affiliate($aff_id);

        if ( ! $aff_obj ) {
            wp_send_json_error( 'missing_affiliate' );
            wp_die();
        }

        $affiliate    = new OCWS_LP_Affiliate( $aff_obj );
        $changes = wp_unslash( $_POST['changes'] );

        if ( isset( $changes['aff_name'] ) ) {
            $affiliate->set_aff_name( wc_clean( $changes['aff_name'] ) );
        }

        if ( isset( $changes['aff_address'] ) ) {
            $affiliate->set_aff_address( wc_clean( $changes['aff_address'] ) );
        }

        if ( isset( $changes['aff_descr'] ) ) {
            $affiliate->set_aff_descr( wc_clean( $changes['aff_descr'] ) );
        }

        $affiliate->save();

        wp_send_json_success(
            array(
                'aff_id'     => $affiliate->get_id(),
                'aff_name'   => $affiliate->get_aff_name(),
                'aff_address'     => $affiliate->get_aff_address(),
                'aff_descr' => $affiliate->get_aff_descr(),
                'request_log'  => $log_request
            )
        );
    }

    public static function admin_add_city_suggestions() {

        // Check User Caps.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'missing_capabilities' );
            wp_die();
        }
        $term = (isset($_GET['trm'])? wc_clean( wp_unslash( $_GET['trm'] ) ) : '');
        $items = array();
        global $wpdb;
        if (!empty($term)) {
            $cities = $wpdb->get_results($wpdb->prepare(
                "SELECT base_cities.city_code, base_cities.city_name, base_cities.city_name_en, base_cities.is_imported
                  FROM {$wpdb->prefix}oc_woo_shipping_cities_base AS base_cities
                  LEFT JOIN {$wpdb->prefix}oc_woo_shipping_locations AS used_cities
                  ON (base_cities.city_code = used_cities.location_code)
                  WHERE used_cities.location_code IS NULL AND (base_cities.city_name LIKE %s OR base_cities.city_name_en LIKE %s)
                  ORDER BY city_name", $term.'%', $term.'%')
            );
            if ($cities) {
                foreach ($cities as $city_row) {
                    $items[] = array(
                        'id' => 'city:'.$city_row->city_code,
                        'text' => $city_row->city_name . ' (' . $city_row->city_name_en . ')'
                    );
                }
            }
        }
        wp_send_json_success(
            array(
                'items' => $items,
                'term' => $term
            )
        );
    }
}

OC_Woo_Ajax::init();