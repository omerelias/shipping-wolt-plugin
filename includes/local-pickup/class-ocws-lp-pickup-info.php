<?php

use Carbon\Carbon;

defined( 'ABSPATH' ) || exit;

class OCWS_LP_Pickup_Info {

    /**
     * @param array $pickup_info
     */
    public static function save_pickup_info($pickup_info)
    {
        $session = WC()->session;
        $session->set('ocws_lp_pickup_info', serialize($pickup_info));
    }

    /**
     * @return array
     */
    public static function get_pickup_info()
    {
        if ( isset( $_POST['post_data'] ) ) {

            parse_str( $_POST['post_data'], $post_data );

        } else {

            $post_data = $_POST; // fallback for final checkout (non-ajax)

        }

        $aff_id = ocws_get_value('ocws_lp_pickup_aff_id', $post_data);
        $aff_name = ocws_get_value('ocws_lp_pickup_aff_name', $post_data);

        if (!$aff_id) {
            $aff_id = ocws_get_session_checkout_field('ocws_lp_pickup_aff_id');
            $aff_name = ocws_get_session_checkout_field('ocws_lp_pickup_aff_name');
        }
        if (empty($aff_name)) {
            if ($aff_id) {
                $affs_ds = new OCWS_LP_Affiliates();
                $aff_name = $affs_ds->get_affiliate_name($aff_id);
            }
        }

        $date = ocws_get_value('ocws_lp_pickup_date', $post_data);
        $slot_start = ocws_get_value('ocws_lp_pickup_slot_start', $post_data);
        $slot_end = ocws_get_value('ocws_lp_pickup_slot_end', $post_data);

        return array(
            'aff_id' => $aff_id,
            'aff_name' => $aff_name,
            'date' => ($date? $date : ocws_get_session_checkout_field('ocws_lp_pickup_date')),
            'slot_start' => ($slot_start? $slot_start : ocws_get_session_checkout_field('ocws_lp_pickup_slot_start')),
            'slot_end' => ($slot_end? $slot_end : ocws_get_session_checkout_field('ocws_lp_pickup_slot_end'))
        );
    }

    /**
     * @return array
     */
    public static function get_pickup_info_from_session()
    {
        $pickup_info = unserialize(WC()->session->get('ocws_lp_pickup_info'));

        if (!$pickup_info || !is_array($pickup_info)) {
            return array(
                'aff_id' => '',
                'aff_name' => '',
                'date' => '',
                'slot_start' => '',
                'slot_end' => ''
            );
        }

        $info = array_map(function ($value) {
                return stripslashes($value);
            }, $pickup_info);

        $aff_id = isset($info['aff_id'])? $info['aff_id'] : '';
        $aff_name = isset($info['aff_name'])? $info['aff_name'] : '';
        $date = isset($info['date'])? $info['date'] : '';
        $slot_start = isset($info['slot_start'])? $info['slot_start'] : '';
        $slot_end = isset($info['slot_end'])? $info['slot_end'] : '';

        return array(
            'aff_id' => $aff_id,
            'aff_name' => $aff_name,
            'date' => $date,
            'slot_start' => $slot_start,
            'slot_end' => $slot_end
        );
    }

    /**
     * Clear pickup session data
     */
    public static function clear_pickup_info()
    {
        if (WC()->session) {
            WC()->session->set('ocws_lp_pickup_info', null);
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

        $pickup_info = self::get_pickup_info();
        if (null === $pickup_info || !isset($pickup_info['date'])) {
            return;
        }

        $saved_to_item = false;

        //error_log('Pickup - Saving to order');

        /* @var \WC_Order_Item_Shipping $shippingItem */
        foreach ($shipping_items as $shipping_item) {
            //error_log('Method: '. $shipping_item->get_method_id());

            $shipping_item->get_formatted_meta_data();

            //error_log(print_r($shipping_item, 1));

            $methodId = OCWS_LP_Local_Pickup::PICKUP_METHOD_ID;
            if (substr($shipping_item->get_method_id(), 0, strlen($methodId)) == $methodId) {

                if ($pickup_info) {
                    // add shipping info into shipping item
                    $shipping_item->add_meta_data('ocws_lp_pickup_info', serialize($pickup_info), true);
                    $shipping_item->save_meta_data();
                    $saved_to_item = true;
                }
            }
        }

        // TODO: update the method

        if ($saved_to_item) {

            error_log('Pickup - Saved to item');

            /*
             * For export and admin columns compatibility - saving 'ocws_shipping_info_date'
             * */
            update_post_meta( $order->get_id(), 'ocws_shipping_tag', OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG );
            update_post_meta( $order->get_id(), 'ocws_shipping_info_date', $pickup_info['date'] );

            update_post_meta( $order->get_id(), 'ocws_lp_pickup_date', $pickup_info['date'] );
            update_post_meta( $order->get_id(), 'ocws_lp_pickup_aff_id', $pickup_info['aff_id']);
            update_post_meta( $order->get_id(), 'ocws_lp_pickup_aff_name', $pickup_info['aff_name']);
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $pickup_info['date'], ocws_get_timezone());
                $formatted = $dt->format('Y/m/d');
                /*
                 * For export and admin columns compatibility - saving 'ocws_shipping_info_date_sortable'
                 * */
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $formatted);
                update_post_meta( $order->get_id(), 'ocws_lp_pickup_date_sortable', $formatted);
            }
            catch (InvalidArgumentException $e) {
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $pickup_info['date']);
                update_post_meta( $order->get_id(), 'ocws_lp_pickup_date_sortable', $pickup_info['date']);
            }

            if (isset($pickup_info['slot_start']) && isset($pickup_info['slot_end'])) {
                /*
                 * For export and admin columns compatibility - saving 'ocws_shipping_info_slot_start', 'ocws_shipping_info_slot_end'
                 * */
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_start', $pickup_info['slot_start'] );
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_end', $pickup_info['slot_end'] );

                update_post_meta( $order->get_id(), 'ocws_lp_pickup_slot_start', $pickup_info['slot_start'] );
                update_post_meta( $order->get_id(), 'ocws_lp_pickup_slot_end', $pickup_info['slot_end'] );
            }
        }

        self::clear_pickup_info();
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

        $pickup_info = self::get_pickup_info();
        if (null === $pickup_info || !isset($pickup_info['date'])) {
            return;
        }

        $saved_to_item = false;

        /* @var \WC_Order_Item_Shipping $shippingItem */
        foreach ($shipping_items as $shipping_item) {
            $shipping_item->get_formatted_meta_data();
            $methodId = OCWS_LP_Local_Pickup::PICKUP_METHOD_ID;
            if (substr($shipping_item->get_method_id(), 0, strlen($methodId)) == $methodId) {

                if ($pickup_info) {

                    $shipping_item->update_meta_data('ocws_lp_pickup_info', serialize($pickup_info));
                    $shipping_item->save_meta_data();
                    $saved_to_item = true;
                }
            }
        }

        if ($saved_to_item) {

            /*
             * For export and admin columns compatibility - saving 'ocws_shipping_info_date'
             * */
            update_post_meta( $order->get_id(), 'ocws_shipping_tag', OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG );
            update_post_meta( $order->get_id(), 'ocws_shipping_info_date', $pickup_info['date'] );

            update_post_meta( $order->get_id(), 'ocws_lp_pickup_date', $pickup_info['date'] );
            update_post_meta( $order->get_id(), 'ocws_lp_pickup_aff_id', $pickup_info['aff_id']);
            update_post_meta( $order->get_id(), 'ocws_lp_pickup_aff_name', $pickup_info['aff_name']);
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $pickup_info['date'], ocws_get_timezone());
                $formatted = $dt->format('Y/m/d');
                /*
                 * For export and admin columns compatibility - saving 'ocws_shipping_info_date_sortable'
                 * */
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $formatted);
                update_post_meta( $order->get_id(), 'ocws_lp_pickup_date_sortable', $formatted);
            }
            catch (InvalidArgumentException $e) {
                update_post_meta( $order->get_id(), 'ocws_shipping_info_date_sortable', $pickup_info['date']);
                update_post_meta( $order->get_id(), 'ocws_lp_pickup_date_sortable', $pickup_info['date']);
            }

            if (isset($pickup_info['slot_start']) && isset($pickup_info['slot_end'])) {
                /*
                 * For export and admin columns compatibility - saving 'ocws_shipping_info_slot_start', 'ocws_shipping_info_slot_end'
                 * */
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_start', $pickup_info['slot_start'] );
                update_post_meta( $order->get_id(), 'ocws_shipping_info_slot_end', $pickup_info['slot_end'] );

                update_post_meta( $order->get_id(), 'ocws_lp_pickup_slot_start', $pickup_info['slot_start'] );
                update_post_meta( $order->get_id(), 'ocws_lp_pickup_slot_end', $pickup_info['slot_end'] );
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
        $pickup_method_id = OCWS_LP_Local_Pickup::PICKUP_METHOD_ID;

        foreach ($order->get_shipping_methods() as $method) {

            if (substr($method->get_method_id(), 0, strlen($pickup_method_id)) == $pickup_method_id) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param $fields array
     * @param \WC_Order_Item_Shipping $item
     * @return array
     */
    public static function admin_add_pickup_info_fields($fields, $item) {

        /*$pickup_info = $item->get_meta('ocws_lp_pickup_info');
        if (!$pickup_info) {
            return $fields;
        }

        $pickup_info = unserialize($pickup_info);

        $aff_id = (isset($pickup_info['aff_id'])? $pickup_info['aff_id'] : 0);
        $aff_name = ((isset($pickup_info['aff_name']) && !empty($pickup_info['aff_name']))? $pickup_info['aff_name'] : '');
        if ($aff_name == '' && $aff_id) {
            $affs_ds = new OCWS_LP_Affiliates();
            $aff_name = $affs_ds->get_affiliate_name(intval($aff_id));
        }*/

        return $fields;
    }

    /**
     * @param \WC_Order_Item_Shipping $item
     * @return string
     */
    public static function admin_render_pickup_info($item)
    {
        $pickup_info = $item->get_meta('ocws_lp_pickup_info');
        if (!$pickup_info) {
            return '';
        }

        $pickup_info = unserialize($pickup_info);

        $aff_id = (isset($pickup_info['aff_id'])? $pickup_info['aff_id'] : 0);
        $aff_name = ((isset($pickup_info['aff_name']) && !empty($pickup_info['aff_name']))? $pickup_info['aff_name'] : '');
        if ($aff_name == '' && $aff_id) {
            $affs_ds = new OCWS_LP_Affiliates();
            $aff_name = $affs_ds->get_affiliate_name(intval($aff_id));
        }

        ob_start();

        ?>

        <?php if ($pickup_info) : ?>
        <?php
            $weekday = ocws_get_day_of_week($pickup_info['date']);
        ?>
        <div class="pickup-info">
            <strong><?php echo __('Pickup branch', 'ocws') ?>:</strong> <?php echo (esc_html($aff_name)) ?> <br />
            <strong><?php echo __('Pickup date', 'ocws') ?>:</strong> <?php echo (($weekday? $weekday.', ' : '') . $pickup_info['date']) ?> <br />
            <?php
            $show_dates_only = get_option('ocws_lp_common_show_dates_only', '') != 1 ? false : true;
            $show_slot_start_only = get_option('ocws_lp_common_show_slot_start_only', '') != 1 ? false : true;
            ?>
            <?php if (!$show_dates_only) { ?>
            <strong><?php echo __('Pickup hour', 'ocws') ?>:</strong> <?php echo ($pickup_info['slot_start']) ?><?php if(!$show_slot_start_only): ?> - <?php echo ($pickup_info['slot_end']) ?> <?php endif; ?> <br />
            <?php } ?>

        </div>
        <script>
            jQuery(document).ready(function () {

                jQuery("#ocws_lp_pickup_date").datepicker({

                    dateFormat: 'dd/mm/yy',
                    numberOfMonths: 1,
                    showButtonPanel: true
                });

                jQuery.noConflict();
                jQuery('.ocws_lp_pickup_timepicker').timepicker({
                    timeFormat: 'HH:mm',
                    interval: 30,
                    minTime: '00:00',
                    maxTime: '23:30',
                    //defaultTime: '18:00',
                    startTime: '07:00',
                    dynamic: false,
                    dropdown: true,
                    scrollbar: true
                });
            });

            jQuery( '.order_data_column a.edit_address' ).on( 'click', function() {
                var $this          = jQuery( this ),
                    $wrapper       = $this.closest( '.order_data_column' ),
                    $edit_pickup_info  = $wrapper.find( 'div.edit-pickup-info' ),
                    $pickup_info       = $wrapper.find( 'div.pickup-info' );

                $pickup_info.hide();
                $edit_pickup_info.show();
            } );

        </script>
        <div class="edit-pickup-info" style="display: none;">
            <p class="form-field">
                <label><?php echo __('Edit pickup date', 'ocws') ?>:</label>
                <input type="text" class="ocws-date-picker" id="ocws_lp_pickup_date" name="ocws_lp_pickup_date" maxlength="10" value="<?php echo ($pickup_info['date']) ?>" >
                <input type="text" class="ocws_lp_pickup_timepicker" name="ocws_lp_pickup_slot_start" value="<?php echo (isset($pickup_info['slot_start'])? esc_attr($pickup_info['slot_start']) : '') ?>">
                <input type="text" class="ocws_lp_pickup_timepicker" name="ocws_lp_pickup_slot_end" value="<?php echo (isset($pickup_info['slot_end'])? esc_attr($pickup_info['slot_end']) : '') ?>">
                <input type="hidden" name="ocws_lp_pickup_aff_id" value="<?php echo (isset($pickup_info['aff_id'])? esc_attr($pickup_info['aff_id']) : '') ?>">
                <input type="hidden" name="ocws_lp_pickup_aff_name" value="<?php echo (isset($pickup_info['aff_name'])? esc_attr($pickup_info['aff_name']) : '') ?>">
            </p>
            <p>
                <?php if (!$show_dates_only) { ?>
                    <strong><?php echo __('Pickup time slot', 'ocws') ?>:</strong> <?php echo ($pickup_info['slot_start']) ?> - <?php echo ($pickup_info['slot_end']) ?> <br />
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
    public static function render_formatted_pickup_info( $order, $force_hide_slot=false, $force_start_hour_only=false ) {

        $shipping_item = self::get_shipping_item( $order );

        if ($shipping_item) {
            $pickup_info = $shipping_item->get_meta('ocws_lp_pickup_info');
            if ($pickup_info) {
                $pickup_info = unserialize($pickup_info);
                $show_dates_only = get_option('ocws_lp_common_show_dates_only', '') != 1 ? false : true;

                $aff_id = (isset($pickup_info['aff_id'])? $pickup_info['aff_id'] : 0);
                $aff_name = ((isset($pickup_info['aff_name']) && !empty($pickup_info['aff_name']))? $pickup_info['aff_name'] : '');
                if ($aff_name == '' && $aff_id) {
                    $affs_ds = new OCWS_LP_Affiliates();
                    $aff_name = $affs_ds->get_affiliate_name(intval($aff_id));
                }

                $html = '<p class="shipping-date">';
                if ($aff_name) {
                    $html .= sprintf(
                        '<strong>%s:</strong> %s<br />',
                        __('Pickup branch', 'ocws'),
                        esc_html($aff_name)
                    );
                }

                if (isset( $pickup_info['date'] )) {
                    $weekday = ocws_get_day_of_week($pickup_info['date']);
                    if ($weekday) {
                        $html .= sprintf(
                            '<strong>%s:</strong> %s, %s<br />',
                            __('Pickup date', 'ocws'),
                            $weekday,
                            $pickup_info['date']
                        );
                    }
                    else {
                        $html .= sprintf(
                            '<strong>%s:</strong> %s<br />',
                            __('Pickup date', 'ocws'),
                            $pickup_info['date']
                        );
                    }
                }

                if (!$force_hide_slot && !$show_dates_only && isset( $pickup_info['slot_start'] ) && isset( $pickup_info['slot_end'] )) {

                    if (!$force_start_hour_only) {

                        $html .= sprintf(
                            '<strong>%s:</strong> %s - %s<br />',
                            __('Pickup time slot', 'ocws'),
                            $pickup_info['slot_start'],
                            $pickup_info['slot_end']
                        );
                    }
                    else {
                        $html .= sprintf(
                            '<strong>%s:</strong> %s <br />',
                            __('Pickup time slot', 'ocws'),
                            $pickup_info['slot_start']
                        );
                    }

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
            if (substr($shippingMethod, 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID) {
                $is_ocws = true;
                break;
            }
        }
        if (!$is_ocws) return;
        if (!isset($post_data['ocws_lp_pickup_aff_id']) || empty($post_data['ocws_lp_pickup_aff_id']) ) {
            $post_data['ocws_lp_pickup_aff_id'] = WC()->checkout()->get_value('ocws_lp_pickup_aff_id');
        }
        $chosen_aff_id = $post_data['ocws_lp_pickup_aff_id'];
        $selected_slot = OCWS_LP_Pickup_Info::get_pickup_info();
        if (null !== $selected_slot) {
            if (isset($selected_slot['aff_id']) && $selected_slot['aff_id']) {
                $chosen_aff_id = $selected_slot['aff_id'];
            }
        }
        $affs_ds = new OCWS_LP_Affiliates();
        $affiliates_dropdown = $affs_ds->get_affiliates_dropdown(true);
        if (count($affiliates_dropdown) <= 1) {
            foreach ($affiliates_dropdown as $key => $val) {
                $chosen_aff_id = $key;
                break;
            }
        }
        if (!ocws_is_affiliate_enabled($chosen_aff_id)) {
            $errors->add( 'shipping', __( 'Please select a pickup location.', 'ocws' ) );
            return;
        }

        $oc_slots = new OCWS_LP_Pickup_Slots($chosen_aff_id);
        $days = $oc_slots->calculate_slots_for_checkout();
        if (!$selected_slot['date']) {
            $errors->add( 'shipping', __( 'Please select a pickup date.', 'ocws' ) );
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
            $errors->add( 'shipping', __( 'Please select valid pickup date and time.', 'ocws' ) );
            WC()->session->set( 'reload_checkout', true );
        }
    }
}