<?php

defined( 'ABSPATH' ) || exit;

class OC_Woo_Shipping_Product {

    public static function init() {

        // Display Fields
        // add_action('woocommerce_product_options_general_product_data', array('OC_Woo_Shipping_Product', 'woocommerce_product_custom_fields'));
        add_action('woocommerce_product_data_panels', array('OC_Woo_Shipping_Product', 'woocommerce_product_custom_fields'));
        // Save Fields
        add_action('woocommerce_process_product_meta', array('OC_Woo_Shipping_Product', 'woocommerce_product_custom_fields_save'));

    }

    public static function woocommerce_product_custom_fields()
    {
        global $woocommerce, $post;
        echo '<div class="woocommerce_options_panel">';
        echo '<div class="product_custom_field">';
        // echo '<div style="display: none;">'.print_r(['post_id' => $post->ID, '_ocws_pickup_only' => get_post_meta( $post->ID, '_ocws_pickup_only', true)], 1).'</div>';
        // Custom Product Text Field
        woocommerce_wp_checkbox(
            array(
                'id' => '_ocws_pickup_only',
                'label' => __('Local pickup only'), // Text in Label
                'class' => '',
                'style' => '',
                'wrapper_class' => '',
                'value' => get_post_meta( $post->ID, '_ocws_pickup_only', true), // if empty, retrieved from post meta where id is the meta_key
                'name' => '_ocws_pickup_only', //name will set from id if empty
                'cbvalue' => 'yes',
                'desc_tip' => '',
                'custom_attributes' => '', // array of attributes
                'description' => ''
            )
        );

        echo '</div>';

        // Limit to specific days (e.g. weekend products: Thu, Fri, Sat)
        $saved_days = get_post_meta( $post->ID, '_ocws_limit_to_days', true );
        if ( ! is_array( $saved_days ) ) {
            $saved_days = array();
        }
        $day_labels = array(
            0 => __( 'Sunday', 'ocws' ),
            1 => __( 'Monday', 'ocws' ),
            2 => __( 'Tuesday', 'ocws' ),
            3 => __( 'Wednesday', 'ocws' ),
            4 => __( 'Thursday', 'ocws' ),
            5 => __( 'Friday', 'ocws' ),
            6 => __( 'Saturday', 'ocws' ),
        );
        echo '<div class="options_group form-field _ocws_limit_to_days_field">';
        echo '<label style="display: block; margin-bottom: 8px;">' . esc_html__( 'הגבל לימים ספציפיים', 'ocws' ) . '</label>';
        echo '<p class="description" style="margin: 0 0 10px;">' . esc_html__( 'אם נבחרו ימים, המוצר יהיה ניתן להזמנה רק בימים אלה (לפי תאריך המשלוח שנבחר).', 'ocws' ) . '</p>';
        echo '<p style="margin: 0; line-height: 2;">';
        foreach ( $day_labels as $day_num => $label ) {
            $checked = in_array( (string) $day_num, $saved_days, true ) ? ' checked="checked"' : '';
            echo '<label style="margin-left: 0; margin-right: 12px; white-space: nowrap;"><input type="checkbox" name="_ocws_limit_to_days[]" value="' . esc_attr( $day_num ) . '"' . $checked . '> ' . esc_html( $label ) . '</label>';
        }
        echo '</p></div>';

        echo '</div>';
    }

    public static function woocommerce_product_custom_fields_save( $post_id )
    {
        $custom_field_value = isset( $_POST['_ocws_pickup_only'] ) ? 'yes' : 'no';

        $product = wc_get_product( $post_id );
        //$product->update_meta_data( '_ocws_pickup_only', $custom_field_value );
        update_post_meta( $product->get_id(), '_ocws_pickup_only', $custom_field_value );

        $limit_days = array();
        if ( ! empty( $_POST['_ocws_limit_to_days'] ) && is_array( $_POST['_ocws_limit_to_days'] ) ) {
            $limit_days = array_map( 'strval', array_intersect( array( '0', '1', '2', '3', '4', '5', '6' ), $_POST['_ocws_limit_to_days'] ) );
        }
        update_post_meta( $product->get_id(), '_ocws_limit_to_days', $limit_days );
    }

}