<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * OCWS_Admin_Profile Class.
 */
class OCWS_Admin_Profile {

    /**
     * Hook in tabs.
     */
    public function __construct() {
        add_action( 'show_user_profile', array( $this, 'before_woo_add_customer_meta_fields' ), 1 );
        add_action( 'edit_user_profile', array( $this, 'before_woo_add_customer_meta_fields' ), 1 );

        //add_action( 'personal_options_update', array( $this, 'save_customer_meta_fields' ) );
        //add_action( 'edit_user_profile_update', array( $this, 'save_customer_meta_fields' ) );

        add_filter( 'woocommerce_customer_meta_fields', array( $this, 'woocommerce_customer_meta_fields' ) );
    }

    public function woocommerce_customer_meta_fields( $fields ) {

        $use_simple_cities = !ocws_use_google_cities_and_polygons();
        $use_polygons = ocws_use_google_cities_and_polygons();
        $use_google_cities = ocws_use_google_cities();

        if (isset( $fields['billing']['fields']['billing_city'] )) {

            $city_args = wp_parse_args(array(
                'label' => __('Type a city name', 'ocws'),
                'type' => 'text',
            ), $fields['billing']['fields']['billing_city']);

            $fields['billing']['fields']['billing_city'] = $city_args;

            $city_code_args = wp_parse_args(array(
                'label' => __('Choose a city from the list', 'ocws'),
                'type' => 'select',
                'options' => [''=>''] + OC_Woo_Shipping_Groups::get_all_locations(true, $use_simple_cities, $use_polygons, $use_google_cities),
                'class' => 'ocws-enhanced-select',
            ), $fields['billing']['fields']['billing_city']);

            $fields['billing']['fields']['billing_city_code'] = $city_code_args;
        }

        if (isset( $fields['shipping']['fields']['shipping_city'] )) {

            $city_args = wp_parse_args(array(
                'label' => __('Type a city name', 'ocws'),
                'type' => 'text',
            ), $fields['shipping']['fields']['shipping_city']);

            $fields['shipping']['fields']['shipping_city'] = $city_args;

            $city_code_args = wp_parse_args(array(
                'label' => __('Choose a city from the list', 'ocws'),
                'type' => 'select',
                'options' => [''=>''] + OC_Woo_Shipping_Groups::get_all_locations(true, $use_simple_cities, $use_polygons, $use_google_cities),
                'class' => 'ocws-enhanced-select',
            ), $fields['shipping']['fields']['shipping_city']);

            $fields['shipping']['fields']['shipping_city_code'] = $city_code_args;
        }

        return $fields;
    }

    /**
     * Maybe change user meta before show address fields on edit user pages.
     *
     * @param WP_User $user
     */
    public function before_woo_add_customer_meta_fields( $user ) {
        //if ( ! apply_filters( 'woocommerce_current_user_can_edit_customer_meta_fields', current_user_can( 'manage_woocommerce' ), $user->ID ) ) {
        //    return;
        //}

        //error_log('before_woo_add_customer_meta_fields');
        $billing_city = get_user_meta( $user->ID, 'billing_city', true );
        $billing_city_code = get_user_meta( $user->ID, 'billing_city_code', true );
        //error_log('billing_city: '.$billing_city);
        //error_log('billing_city_code: '.$billing_city_code);
        //error_log('user:');
        //error_log(print_r(get_user_meta($user->ID), 1));

        if ( $billing_city ) {
            if (is_numeric( $billing_city ) || ocws_is_hash( $billing_city )) {
                $billing_city_code = $billing_city;
                $city = ocws_get_city_title( $billing_city );
                if ($city) {
                    $billing_city = $city;
                }
            }
            else {
                if (!$billing_city_code) {
                    $billing_city_code = $billing_city;
                }
            }
            update_user_meta( $user->ID, 'billing_city', $billing_city );
            update_user_meta( $user->ID, 'billing_city_code', $billing_city_code );
            //error_log('update_user_meta( '.$user->ID.', \'billing_city\', '.$billing_city.' );');
            //error_log('update_user_meta( '.$user->ID.', \'billing_city_code\', '.$billing_city_code.' );');
        }
        else {
            if ($billing_city_code) {
                $city = ocws_get_city_title( $billing_city_code );
                $billing_city = ($city? $city : $billing_city_code);
                update_user_meta( $user->ID, 'billing_city', $billing_city );
                //error_log('update_user_meta( '.$user->ID.', \'billing_city\', '.$billing_city.' );');
            }
        }

        $shipping_city = get_user_meta( $user->ID, 'shipping_city', true );
        $shipping_city_code = get_user_meta( $user->ID, 'shipping_city_code', true );

        if ( $shipping_city ) {
            if (is_numeric( $shipping_city ) || ocws_is_hash( $shipping_city )) {
                $shipping_city_code = $shipping_city;
                $city = ocws_get_city_title( $shipping_city );
                if ($city) {
                    $shipping_city = $city;
                }
            }
            else {
                if (!$shipping_city_code) {
                    $shipping_city_code = $shipping_city;
                }
            }
            update_user_meta( $user->ID, 'shipping_city', $shipping_city );
            update_user_meta( $user->ID, 'shipping_city_code', $shipping_city_code );
        }
        else {
            if ($shipping_city_code) {
                $city = ocws_get_city_title( $shipping_city_code );
                $shipping_city = ($city? $city : $shipping_city_code);
                update_user_meta( $user->ID, 'shipping_city', $shipping_city );
            }
        }
    }

    public function save_customer_meta_fields( $user_id ) {
        if ( ! apply_filters( 'woocommerce_current_user_can_edit_customer_meta_fields', current_user_can( 'manage_woocommerce' ), $user_id ) ) {
            return;
        }
    }
}
