<?php

defined( 'ABSPATH' ) || exit;

class OC_Woo_Shipping_Product {

    public static function init() {

        // Display Fields (pickup only in product data panels)
        add_action('woocommerce_product_data_panels', array('OC_Woo_Shipping_Product', 'woocommerce_product_custom_fields'));
        // Save Fields
        add_action('woocommerce_process_product_meta', array('OC_Woo_Shipping_Product', 'woocommerce_product_custom_fields_save'));

        // Limit to days: meta box
        add_action('add_meta_boxes', array('OC_Woo_Shipping_Product', 'add_limit_to_days_meta_box'));
    }

    /**
     * Register meta box for "הגבל לימים ספציפיים".
     */
    public static function add_limit_to_days_meta_box() {
        add_meta_box(
            'ocws_limit_to_days',
            __( 'הגבל לימים משלוח', 'ocws' ),
            array('OC_Woo_Shipping_Product', 'render_limit_to_days_meta_box'),
            'product',
            'side'
        );
    }

    /**
     * Render the limit-to-days meta box content.
     */
    public static function render_limit_to_days_meta_box( $post ) {
        wp_nonce_field( 'ocws_limit_to_days_save', 'ocws_limit_to_days_nonce' );
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
        echo '<p class="description" style="margin: 0 0 10px;">' . esc_html__( 'אם נבחרו ימים, המוצר יהיה ניתן להזמנה רק בימים אלה (לפי תאריך המשלוח שנבחר).', 'ocws' ) . '</p>';
        echo '<p style="margin: 0; line-height: 2;">';
        foreach ( $day_labels as $day_num => $label ) {
            $checked = in_array( (string) $day_num, $saved_days, true ) ? ' checked="checked"' : '';
            echo '<label style="display: block; margin-bottom: 4px;"><input type="checkbox" name="_ocws_limit_to_days[]" value="' . esc_attr( $day_num ) . '"' . $checked . '> ' . esc_html( $label ) . '</label>';
        }
        echo '</p>';
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
        echo '</div>';
    }

    public static function woocommerce_product_custom_fields_save( $post_id ) {
        $custom_field_value = isset( $_POST['_ocws_pickup_only'] ) ? 'yes' : 'no';
        $product = wc_get_product( $post_id );
        if ( $product ) {
            update_post_meta( $product->get_id(), '_ocws_pickup_only', $custom_field_value );
        }

        // Limit to days (from meta box)
        if ( isset( $_POST['ocws_limit_to_days_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ocws_limit_to_days_nonce'] ) ), 'ocws_limit_to_days_save' ) ) {
            $limit_days = array();
            if ( ! empty( $_POST['_ocws_limit_to_days'] ) && is_array( $_POST['_ocws_limit_to_days'] ) ) {
                $limit_days = array_map( 'strval', array_intersect( array( '0', '1', '2', '3', '4', '5', '6' ), array_map( 'sanitize_text_field', wp_unslash( $_POST['_ocws_limit_to_days'] ) ) ) );
            }
            update_post_meta( $post_id, '_ocws_limit_to_days', $limit_days );
        }
    }

}