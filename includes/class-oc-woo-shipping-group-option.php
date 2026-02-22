<?php

defined( 'ABSPATH' ) || exit;


class OC_Woo_Shipping_Group_Option {

    public static function get_general_options_defaults() {

        return array(
                'popup_title' => __('בחרו סוג משלוח', 'ocws'),
                'popup_shipping_method_button_text' => __('שליח עד הבית', 'ocws'),
                'popup_choose_location_title' => __('בחרו עיר / יישוב למשלוח', 'ocws'),
                'popup_choose_location_sub_title' => '',
                'popup_button_text' => __('אישור', 'ocws'),
                'checkout_send_to_other_checkbox_label' => __('אני שולח למישהו אחר', 'ocws'),
                'checkout_slots_title' => __('מתי נוח לך שנגיע?', 'ocws'),
                'checkout_slots_description' => __('', 'ocws'),
                'out_of_service_area_message' => __('מצטערים, הכתובת שלך אינה באזור החלוקה שלנו', 'ocws'),

            );
    }

    public static function get_option($group_id, $option_name, $default='') {

        $group_option_name = 'ocws_group' . $group_id . '_' . $option_name;
        $use_default_opt = $group_option_name . '_ud';
        $use_default = get_option($use_default_opt, '1');

        $default_opt_name = 'ocws_default_' . $option_name;
        $default_value = get_option($default_opt_name, $default);
        if ($use_default === '1') {
            $option_value = $default_value;
        }
        else {
            $option_value = get_option($group_option_name, $default);
        }
        return array(
            'use_default' => $use_default,
            'option_value' => $option_value,
            'default' => $default_value,
            'option_name' => $group_option_name
        );
    }

    public static function register_option($group_id, $option_name) {

        $group_option_name = 'ocws_group' . $group_id . '_' . $option_name;
        $use_default_opt = $group_option_name . '_ud';
        register_setting( 'ocws_group' . $group_id, $group_option_name );
        register_setting( 'ocws_group' . $group_id, $use_default_opt, array( 'default' => '1' ) );
    }

    public static function register_default_option($option_name) {

        $default_opt_name = 'ocws_default_' . $option_name;
        register_setting( 'ocws_default', $default_opt_name );
    }

    public static function register_common_option($option_name, $default='') {

        if ($default !== '') {
            register_setting( 'ocws_common', 'ocws_common_' . $option_name, array('default' => $default) );
        }
        else {
            register_setting( 'ocws_common', 'ocws_common_' . $option_name );
        }
    }

    public static function get_common_option($option_name, $default='') {

        return get_option( 'ocws_common_' . $option_name, $default );
    }

    public static function get_group_option_prefix($group_id) {
        return 'ocws_group' . $group_id . '_';
    }

    public static function get_location_option_prefix($location_code) {
        return 'ocws_location' . $location_code . '_';
    }

    public static function register_location_option($location_code, $option_name) {

        $location_option_name = self::get_location_option_prefix($location_code) . $option_name;
        $use_default_opt = $location_option_name . '_ud';
        register_setting( 'ocws_location' . $location_code, $location_option_name );
        register_setting( 'ocws_location' . $location_code, $use_default_opt, array( 'default' => 1 ) );
    }

    public static function get_location_option($location_code, $group_id, $option_name, $default='') {

        $group_option = self::get_option($group_id, $option_name, $default);
        $location_option_name = self::get_location_option_prefix($location_code) . $option_name;
        if ($option_name == 'price_depending') {
            $use_default = '0';
            $option_value = get_option($location_option_name, '{"active":false,"rules":[]}');
        }
        else {
            $use_default_opt = $location_option_name . '_ud';
            $use_default = get_option($use_default_opt, '1');
            if ($use_default === '1') {
                $option_value = $group_option['option_value'];
            }
            else {
                $option_value = get_option($location_option_name, $group_option['option_value']);
            }
        }
        return array(
            'use_default' => $use_default,
            'option_value' => $option_value,
            'default' => $group_option['option_value'],
            'option_name' => $location_option_name
        );
    }

    public static function update_location_option_ud($location_code, $option_name, $use_default) {

        $location_option_name = self::get_location_option_prefix($location_code) . $option_name;
        $use_default_opt = $location_option_name . '_ud';

        update_option( $use_default_opt, ($use_default? '1' : ''));
    }

    public static function update_location_option_value($location_code, $option_name, $option_value) {

        $location_option_name = self::get_location_option_prefix($location_code) . $option_name;
        update_option( $location_option_name, $option_value);
    }

}
