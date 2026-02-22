<?php

use Carbon\Carbon;

defined( 'ABSPATH' ) || exit;

class OC_Woo_Shipping_Info {

    /**
     * @param array $shipping_info
     */
    public static function save_shipping_info($shipping_info)
    {
        $session = WC()->session;
        $session->set('ocws_shipping_info', serialize($shipping_info));
    }

    /**
     * @return array
     */
    public static function get_shipping_info()
    {
        if ( isset( $_POST['post_data'] ) ) {

            parse_str( $_POST['post_data'], $post_data );

        } else {

            $post_data = $_POST; // fallback for final checkout (non-ajax)

        }

        $date = ocws_get_value('order_expedition_date', $post_data);
        $slot_start = ocws_get_value('order_expedition_slot_start', $post_data);
        $slot_end = ocws_get_value('order_expedition_slot_end', $post_data);

        return array(
            'date' => ($date? $date : ocws_get_session_checkout_field('order_expedition_date')),
            'slot_start' => ($slot_start? $slot_start : ocws_get_session_checkout_field('order_expedition_slot_start')),
            'slot_end' => ($slot_end? $slot_end : ocws_get_session_checkout_field('order_expedition_slot_end'))
        );
    }

    /**
     * @return array
     */
    public static function get_shipping_info_from_session()
    {
        $shipping_info = unserialize(WC()->session->get('ocws_shipping_info'));

        if (!$shipping_info || !is_array($shipping_info)) {
            return array(
                'date' => '',
                'slot_start' => '',
                'slot_end' => ''
            );
        }

        $info = array_map(function ($value) {
                return stripslashes($value);
            }, $shipping_info);

        $date = isset($info['date'])? $info['date'] : '';
        $slot_start = isset($info['slot_start'])? $info['slot_start'] : '';
        $slot_end = isset($info['slot_end'])? $info['slot_end'] : '';

        return array(
            'date' => $date,
            'slot_start' => $slot_start,
            'slot_end' => $slot_end
        );
    }

    /**
     * Clear shipping session data
     */
    public static function clear_shipping_info()
    {
        if (WC()->session) {
            WC()->session->set('ocws_shipping_info', null);
        }
    }

    /**
     * @param \WC_Order $order
     */
    public static function save_to_order($order)
    {
        $shipping_items = $order->get_shipping_methods();

        if (!count($shipping_items)) {
            return;
        }

        $shipping_info = self::get_shipping_info();
        if (null === $shipping_info || !isset($shipping_info['date'])) {
            return;
        }

        $saved_to_item = false;

        /* @var \WC_Order_Item_Shipping $shippingItem */
        foreach ($shipping_items as $shipping_item) {
            $shipping_item->get_formatted_meta_data();
            $methodId = 'oc_woo_advanced_shipping_method';
            if (substr($shipping_item->get_method_id(), 0, strlen($methodId)) == $methodId) {

                if ($shipping_info) {
                    // add shipping info into shipping item
                    $shipping_item->add_meta_data('ocws_shipping_info', serialize($shipping_info), true);
                    $shipping_item->save_meta_data();
                    $saved_to_item = true;
                }
            }
        }

        if ($saved_to_item) {

            /*
             * update: save shipping and billing city name
             *
             * */

            $billing_city = $order->get_billing_city();
            if (is_numeric($billing_city) || ocws_is_hash($billing_city)) {
                update_post_meta( $order->get_id(), '_billing_city_code', $billing_city );
                $city_title = ocws_get_city_title( $billing_city );
                update_post_meta( $order->get_id(), '_billing_city_name', $city_title);
                update_post_meta( $order->get_id(), '_billing_city', $city_title);
            }
            $shipping_city = $order->get_shipping_city();
            if (is_numeric($shipping_city) || ocws_is_hash($shipping_city)) {
                update_post_meta( $order->get_id(), '_shipping_city_code', $shipping_city );
                $city_title = ocws_get_city_title( $shipping_city );
                update_post_meta( $order->get_id(), '_shipping_city_name', $city_title );
                update_post_meta( $order->get_id(), '_shipping_city', $city_title );
            }

            update_post_meta( $order->get_id(), 'ocws_shipping_tag', OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG );
            update_post_meta( $order->get_id(), 'ocws_shipping_info_date', $shipping_info['date'] );
            // TODO: update meta for a polygon case
            $location_code = 0;
            if (ocws_use_google_cities_and_polygons()) {
                $coords = $order->get_meta('_billing_address_coords');
                $city_code = $order->get_meta('_billing_city_code');
                if ($coords) {
                    $location_code = OC_Woo_Shipping_Polygon::get_location_code_by_post_data(
                        array('billing_address_coords' => $coords, 'billing_city_code' => $city_code)
                    );
                }
            }
            else {
                $location_code = $billing_city;
            }
            update_post_meta( $order->get_id(), 'ocws_shipping_group', ocws_get_group_id_by_city( $location_code ));

            try {
                $dt = Carbon::createFromFormat('d/m/Y', $shipping_info['date'], ocws_get_timezone());
                $formatted = $dt->format('Y/m/d');
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $formatted);
            }
            catch (InvalidArgumentException $e) {
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $shipping_info['date']);
            }

            if (isset($shipping_info['slot_start']) && isset($shipping_info['slot_end'])) {
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_start', $shipping_info['slot_start'] );
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_end', $shipping_info['slot_end'] );
            }
        }

        self::clear_shipping_info();
    }

    /**
     * @param \WC_Order $order
     */
    public static function update_in_order($order)
    {
        $shipping_items = $order->get_shipping_methods();

        if (!count($shipping_items)) {
            return;
        }

        $shipping_info = self::get_shipping_info();
        if (null === $shipping_info || !isset($shipping_info['date'])) {
            return;
        }

        $saved_to_item = false;

        /* @var \WC_Order_Item_Shipping $shippingItem */
        foreach ($shipping_items as $shipping_item) {
            $shipping_item->get_formatted_meta_data();
            $methodId = 'oc_woo_advanced_shipping_method';
            if (substr($shipping_item->get_method_id(), 0, strlen($methodId)) == $methodId) {

                if ($shipping_info) {
                    // add shipping info into shipping item
                    //error_log('Saving data to order');
                    $shipping_item->update_meta_data('ocws_shipping_info', serialize($shipping_info));
                    $shipping_item->save_meta_data();
                    $saved_to_item = true;
                }
            }
        }

        if ($saved_to_item) {
            update_post_meta( $order->get_id(), 'ocws_shipping_tag', OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG );
            update_post_meta( $order->get_id(), 'ocws_shipping_info_date', $shipping_info['date'] );

            $location_code = 0;
            $billing_city = $order->get_billing_city();
            $billing_city_code = $order->get_meta('_billing_city_code');
            if (ocws_use_google_cities_and_polygons()) {
                $coords = $order->get_meta('_billing_address_coords');
                if ($coords) {
                    $location_code = OC_Woo_Shipping_Polygon::get_location_code_by_post_data(
                        array('billing_address_coords' => $coords)
                    );
                }
            }
            else {
                $location_code = ($billing_city_code? $billing_city_code : $billing_city);
            }
            update_post_meta( $order->get_id(), 'ocws_shipping_group', ocws_get_group_id_by_city( $location_code ));

            try {
                $dt = Carbon::createFromFormat('d/m/Y', $shipping_info['date'], ocws_get_timezone());
                $formatted = $dt->format('Y/m/d');
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $formatted);
            }
            catch (InvalidArgumentException $e) {
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $shipping_info['date']);
            }

            if (isset($shipping_info['slot_start']) && isset($shipping_info['slot_end'])) {
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_start', $shipping_info['slot_start'] );
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_end', $shipping_info['slot_end'] );
            }
        }


    }

    /**
     * @param \WC_Order $order
     * @return \WC_Order_Item_Shipping|null
     */
    public static function get_shipping_item($order)
    {
        /* @var \WC_Order_Item_Shipping $method */
        foreach ($order->get_shipping_methods() as $method) {
            $methodId = substr($method->get_method_id(), 0, strlen('oc_woo_advanced_shipping_method'));
            if ($methodId == 'oc_woo_advanced_shipping_method') {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param \WC_Order_Item_Shipping $item
     * @return string
     */
    public static function admin_render_shipping_info($item)
    {
        $shipping_info = $item->get_meta('ocws_shipping_info');
        if (!$shipping_info) {
            return '';
        }

        $shipping_info = unserialize($shipping_info);

        ob_start();

        ?>

        <?php if ($shipping_info) : ?>
        <?php
            $weekday = ocws_get_day_of_week($shipping_info['date']);
        ?>
        <div class="shipping-info">
            <strong><?php echo __('Shipping date', 'ocws') ?>:</strong> <?php echo (($weekday? $weekday.', ' : '') . $shipping_info['date']) ?> <br />
            <?php
            $show_dates_only = get_option('ocws_common_show_dates_only', '') != 1 ? false : true;
            ?>
            <?php if (!$show_dates_only) { ?>
            <strong><?php echo __('Time slot', 'ocws') ?>:</strong> <?php echo ($shipping_info['slot_start']) ?> - <?php echo ($shipping_info['slot_end']) ?> <br />
            <?php } ?>

        </div>
        <script>
            jQuery(document).ready(function () {

                jQuery("#ocws_delivery_date").datepicker({

                    dateFormat: 'dd/mm/yy',
                    numberOfMonths: 1,
                    showButtonPanel: true
                });
            });

            jQuery( '.order_data_column a.edit_address' ).on( 'click', function() {
                var $this          = jQuery( this ),
                    $wrapper       = $this.closest( '.order_data_column' ),
                    $edit_shipping_info  = $wrapper.find( 'div.edit-shipping-info' ),
                    $shipping_info       = $wrapper.find( 'div.shipping-info' );

                $shipping_info.hide();
                $edit_shipping_info.show();
            } );

        </script>
        <div class="edit-shipping-info" style="display: none;">
            <p class="form-field">
                <label><?php echo __('Edit shipping date', 'ocws') ?>:</label>
                <input type="text" class="ocws-date-picker" id="ocws_delivery_date" name="order_expedition_date" maxlength="10" value="<?php echo ($shipping_info['date']) ?>" >
                <input type="hidden" name="order_expedition_slot_start" value="<?php echo (isset($shipping_info['slot_start'])? esc_attr($shipping_info['slot_start']) : '') ?>">
                <input type="hidden" name="order_expedition_slot_end" value="<?php echo (isset($shipping_info['slot_end'])? esc_attr($shipping_info['slot_end']) : '') ?>">
            </p>
            <p>
                <?php if (!$show_dates_only) { ?>
                    <strong><?php echo __('Time slot', 'ocws') ?>:</strong> <?php echo ($shipping_info['slot_start']) ?> - <?php echo ($shipping_info['slot_end']) ?> <br />
                <?php } ?>
            </p>
        </div>
        <?php endif ?>

        <?php

        $html = ob_get_clean();

        return $html;
    }

    /**
     * @param \WC_Order $order
     * @param boolean $force_hide_slot
     * @return string
     */
    public static function render_formatted_shipping_info( $order, $force_hide_slot=false ) {

        $shipping_item = self::get_shipping_item( $order );

        if ($shipping_item) {
            $shipping_info = $shipping_item->get_meta('ocws_shipping_info');
            if ($shipping_info) {
                $shipping_info = unserialize($shipping_info);
                $show_dates_only = get_option('ocws_common_show_dates_only', '') != 1 ? false : true;

                $html = '<p class="shipping-date" style="margin:0;margin-bottom: 25px; padding:0;">';
                if (isset( $shipping_info['date'] )) {
                    $weekday = ocws_get_day_of_week($shipping_info['date']);
                    if ($weekday) {
                        $html .= sprintf(
                            '<strong>%s:</strong> %s, %s<br />',
                            __('Shipping date', 'ocws'),
                            $weekday,
                            $shipping_info['date']
                        );
                    }
                    else {
                        $html .= sprintf(
                            '<strong>%s:</strong> %s<br />',
                            __('Shipping date', 'ocws'),
                            $shipping_info['date']
                        );
                    }
                }

                if (!$force_hide_slot && !$show_dates_only && isset( $shipping_info['slot_start'] ) && isset( $shipping_info['slot_end'] )) {

                    $html .= sprintf(
                        '<strong>%s:</strong> %s - %s<br />',
                        __('Time slot', 'ocws'),
                        $shipping_info['slot_start'],
                        $shipping_info['slot_end']
                    );
                }

                $html .= '</p>';

                return $html;
            }

        }

        return '';
    }

    /**
     * Validates that the checkout has enough info to proceed.
     *
     * @param  array    $data   An array of posted data.
     * @param  WP_Error $errors Validation errors.
     */
    public static function validate_checkout_posted_data( &$data, &$errors) {

        $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
        } else {
            $post_data = $_POST; // fallback for final checkout (non-ajax)
        }
        if (empty($chosen_methods)) {
            if (isset($post_data['shipping_method']) && is_array($post_data['shipping_method'])) {
                $chosen_methods = $post_data['shipping_method'];
            }
        }
        if (empty($chosen_methods)) return;
        $is_ocws = false;

        foreach ($chosen_methods as $shippingMethod) {
            if (substr($shippingMethod, 0, strlen('oc_woo_advanced_shipping_method')) == 'oc_woo_advanced_shipping_method') {
                $is_ocws = true;
                break;
            }
        }
        if (!$is_ocws) return;

        $location_code = 0;
        if (ocws_use_google_cities_and_polygons()) {

            $location_code = OC_Woo_Shipping_Polygon::get_location_code_by_post_data($post_data);
        } else {
            if (isset($post_data['billing_city']) && !empty($post_data['billing_city'])) {
                $location_code = $post_data['billing_city'];
            }
        }


        if (!$location_code || !ocws_is_location_enabled($location_code)) {
            $errors->add( 'shipping', __( 'Please enter a city to continue.', 'ocws' ) );
            return;
        }

        ##################
        // $skip_slot_selection = true;
        ##################
        
        // additional development for meatchoice for user that didn`t selected date and time slot | IF ELSE IS NOT REQUIRED IN original branch
        $data_store                = new OC_Woo_Shipping_Group_Data_Store();
        $group_id                  = $data_store->get_group_by_location( $location_code );
        $allow_checkout_validation = OC_Woo_Shipping_Group_Option::get_location_option($location_code, $group_id, 'allow_checkout_validation_without_slots', 0);
        $skip_slot_selection       = isset( $allow_checkout_validation['option_value'] ) && $allow_checkout_validation['option_value'] == '1';

        // using slots !!!!!
        if ( $skip_slot_selection ){
            // $errors->add( 'shipping', $location_code . ': location_code' );
            // $errors->add( 'shipping', $skip_slot_selection . ': skip_slot_selection' );
            // $errors->add( 'shipping', $allow_checkout_validation . ': allow_checkout_validation' );
            // $errors->add( 'shipping', $str . ': str' );
            return;
        } else {

            $oc_slots = new OC_Woo_Shipping_Slots($location_code);
            $days = $oc_slots->calculate_slots_for_checkout();

            $selected_slot = OC_Woo_Shipping_Info::get_shipping_info();
            if (!$selected_slot['date']) {
                $errors->add( 'shipping', __( 'Please select a shipping date.', 'ocws' ) );
                return;
            }
            $selected_slot_valid = false;

            foreach ($days as $index => $day) {

                foreach ($day['slots'] as $slot) {
                    if ($selected_slot['date'] == $day['formatted_date'] && $selected_slot['slot_start'] == $slot['start'] && $selected_slot['slot_end'] == $slot['end']) {
                        $selected_slot_valid = true;
                        break;
                    }
                }
                if ($selected_slot_valid) break;
            }
            if (!$selected_slot_valid) {
                $errors->add( 'shipping', __( 'Please select valid shipping date and time.', 'ocws' ) );
                WC()->session->set( 'reload_checkout', true );
            }

       }


    }
}