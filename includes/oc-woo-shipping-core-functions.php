<?php

use Carbon\Carbon;

defined('ABSPATH') || exit;

/**
 * Return the html selected attribute if stringified $value is found in array of stringified $options
 * or if stringified $value is the same as scalar stringified $options.
 *
 * @param string|int $value Value to find within options.
 * @param string|int|array $options Options to go through when looking for value.
 * @return string
 */

add_action('woocommerce_checkout_update_order_review', 'checkout_update_refresh_shipping_methods', 10, 1);
function checkout_update_refresh_shipping_methods($post_data)
{
    $packages = WC()->cart->get_shipping_packages();
    foreach ($packages as $package_key => $package) {
        WC()->session->set('shipping_for_package_' . $package_key, false); // Or true
    }
}

function ocws_is_admin_order_screen()
{

    global $pagenow;
    if (($pagenow == 'post.php') && (get_post_type() == 'shop_order')) {

        return true;

    }

    return false;
}

function ocws_selected($value, $options)
{
    if (is_array($options)) {
        $options = array_map('strval', $options);
        return selected(in_array((string)$value, $options, true), true, false);
    }

    return selected($value, $options, false);
}


function ocws_disabled($value, $options)
{
    if (is_array($options)) {
        $options = array_map('strval', $options);
        return disabled(in_array((string)$value, $options, true), true, false);
    }

    return disabled($value, $options, false);
}

/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 * Non-scalar values are ignored.
 *
 * @param string|array $var Data to sanitize.
 * @return string|array
 */
function ocws_clean($var)
{
    if (is_array($var)) {
        return array_map('ocws_clean', $var);
    } else {
        return is_scalar($var) ? sanitize_text_field($var) : $var;
    }
}

function ocws_add_shipping_method($methods)
{
    $methods['oc_woo_advanced_shipping_method'] = 'OC_Woo_Advanced_Shipping_Method';
    $methods['oc_woo_local_pickup_method'] = 'OC_Woo_Local_Pickup_Method';
    return $methods;
}

function ocws_shipping_method_init()
{
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-oc-woo-advanced-shipping-method.php';
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/local-pickup/class-oc-woo-local-pickup-method.php';
}

function ocws_dates_array_to_string($dates)
{
    return implode(',', $dates);
}

function ocws_dates_list_to_array($list, $remove_past_dates=false, $date_format = 'd/m/Y') {
    $datesArr = explode(',', trim($list));

    $trimmed = array_filter(array_map('trim', $datesArr), function ($item) use ($remove_past_dates, $date_format) {

        if (empty($item)) return false;
        try {
            $d = Carbon::createFromFormat($date_format, $item, ocws_get_timezone());
        }
        catch (InvalidArgumentException $e) {
            // not valid date
            return false;
        }
        if ($remove_past_dates) {
            return !(Carbon::now()->startOfDay()->gte($d));
        }
        return true;
    });
    $ret = array();
    foreach ($trimmed as $v) {
        $ret[] = $v;
    }
    return $ret;
}

function ocws_numbers_list_to_array($list)
{
    $datesArr = explode(',', trim($list));
    $trimmed = array_filter(array_map('trim', $datesArr), function ($item) {
        return !empty($item) || $item == 0;
    });
    return $trimmed;
}

function ocws_kses_notice($message)
{
    $allowed_tags = array_replace_recursive(
        wp_kses_allowed_html('post'),
        array(
            'a' => array(
                'tabindex' => true,
            ),
        )
    );

    return wp_kses($message, $allowed_tags);
}

/**
 * Render out of service area error message
 * @param string $location_code
 * @return string HTML content or empty string if no error
 */
function ocws_render_out_of_service_error($location_code = '') {
    if (!empty($location_code) && ocws_is_location_enabled($location_code)) {
        return '';
    }

    $oos_message = ocws_get_multilingual_option('ocws_common_out_of_service_area_message');
    if (empty($oos_message)) {
        $general_options_defaults = OC_Woo_Shipping_Group_Option::get_general_options_defaults();
        if (isset($general_options_defaults['out_of_service_area_message'])) {
            $oos_message = $general_options_defaults['out_of_service_area_message'];
        }
    }

    if (empty($oos_message)) {
        return '';
    }
//    var_dump(strtolower($oos_message));
    $is_error = str_contains(strtolower($oos_message), 'מצטערים');
    $classes = 'slot-message' . ($is_error ? ' error-check' : '');
    return '<div class="' . esc_attr($classes) . '">' . esc_html($oos_message) . '</div>';

}

function ocws_render_shipping_additional_fields()
{

    $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
    $checkout = WC()->checkout();

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

    if (!isset($post_data['billing_city']) || empty($post_data['billing_city'])) {
        $post_data['billing_city'] = WC()->checkout->get_value('billing_city');
    }

    $location_code = 0;
    if (ocws_use_google_cities_and_polygons()) {

        $location_code = OC_Woo_Shipping_Polygon::get_location_code_by_post_data($post_data);
    }
    else {
        $location_code = $post_data['billing_city'];
    }

    if (empty($chosen_methods)) {
        ?>
        <div id="oc-woo-shipping-additional"></div>
        <?php
        return;
    }

    /*if (!$location_code || !ocws_is_location_enabled($location_code)) {
        */?><!--
        <div id="oc-woo-shipping-additional"></div>
        --><?php
    /*        return;
        }*/
    $is_ocws = false;

    foreach ($chosen_methods as $shippingMethod) {
        if (substr($shippingMethod, 0, strlen('oc_woo_advanced_shipping_method')) == 'oc_woo_advanced_shipping_method') {
            $is_ocws = true;
            break;
        }
    }
    if (!$is_ocws) {
        ?>
        <div id="oc-woo-shipping-additional"></div>
        <?php
        return;
    }
    // Check for out of service area error
    $error_message = ocws_render_out_of_service_error($location_code);
    if(!$location_code){
        ?>
        <div id="oc-woo-shipping-additional">
        </div>
        <?php
        return;
    }
    else if (!empty($error_message)) {
        ?>
        <div id="oc-woo-shipping-additional">
            <?php echo $error_message; ?>
        </div>
        <?php
        return;
    }

    $show_as_slider = true; //isset($post_data['show_as_slider']);

    $show_dates_only = get_option('ocws_common_show_dates_only', '') != 1 ? false : true;

    $oc_slots = new OC_Woo_Shipping_Slots($location_code);
    $days = $oc_slots->calculate_slots_for_checkout();
    //print_r($days);
    $weekdays = array(
        __('Sunday', 'ocws'),
        __('Monday', 'ocws'),
        __('Tuesday', 'ocws'),
        __('Wednesday', 'ocws'),
        __('Thursday', 'ocws'),
        __('Friday', 'ocws'),
        __('Saturday', 'ocws')
    );

    // $state = (isset($_POST['ocws_shipping_info']['state']) && in_array($_POST['ocws_shipping_info']['state'], array('less', 'more')))? $_POST['ocws_shipping_info']['state'] : 'less';
    $state = 'more';

    $selected_slot = OC_Woo_Shipping_Info::get_shipping_info();
    $popup_shipping_info = OC_Woo_Shipping_Info::get_shipping_info_from_session();

    $selected_slot_arr = array(
        'date' => '',
        'slot_start' => '',
        'slot_end' => ''
    );
    if (null !== $selected_slot) {
        if (isset($selected_slot['date']) && $selected_slot['date']) {
            $selected_slot_arr['date'] = $selected_slot['date'];
        } else if ($popup_shipping_info['date']) {
            $selected_slot_arr['date'] = $popup_shipping_info['date'];
        }
        if (isset($selected_slot['slot_start']) && $selected_slot['slot_start']) {
            $selected_slot_arr['slot_start'] = $selected_slot['slot_start'];
        } else if ($popup_shipping_info['slot_start']) {
            $selected_slot_arr['slot_start'] = $popup_shipping_info['slot_start'];
        }
        if (isset($selected_slot['slot_end']) && $selected_slot['slot_end']) {
            $selected_slot_arr['slot_end'] = $selected_slot['slot_end'];
        } else if ($popup_shipping_info['slot_end']) {
            $selected_slot_arr['slot_end'] = $popup_shipping_info['slot_end'];
        }
    }

    $output = array();
    foreach ($days as $index => $day) {

        if (count($day['slots']) == 0) {
            continue;
        }
        $item = array();
        $item['formatted_date'] = $day['formatted_date'];
        $item['weekday'] = $weekdays[$day['day_of_week']];
        $item['day_of_week'] = $day['day_of_week'];
        $item['slots'] = array();
        foreach ($day['slots'] as $slot) {
            $slot['class'] = '';
            if ($selected_slot_arr['date'] == $day['formatted_date'] && $selected_slot_arr['slot_start'] == $slot['start'] && $selected_slot_arr['slot_end'] == $slot['end']) {
                $slot['class'] = 'selected';
            }
            $item['slots'][] = $slot;
        }
        $output[] = $item;
    }


    ?>

    <div style="display: none"><?php echo print_r($output, 1) ?></div>
    <div style="display: none"><?php echo print_r($selected_slot, 1) ?></div>
    <div style="display: none"><?php echo print_r($selected_slot_arr, 1) ?></div>
    <div id="oc-woo-shipping-additional">

        <?php
        $checkout = WC()->checkout();
        $fields = $checkout->get_checkout_fields('ocws');

        foreach ($fields as $key => $field) {
            woocommerce_form_field($key, $field, ocws_get_value($key, $post_data) ?: (isset($field['default']) ? $field['default'] : ''));
        }
        ?>

        <?php if (count($output) > 0) { ?>
            <?php
            $general_options_defaults = OC_Woo_Shipping_Group_Option::get_general_options_defaults();
            $slots_block_title = ocws_get_multilingual_option('ocws_common_checkout_slots_title');
            if (empty($slots_block_title)) {
                if (isset($general_options_defaults['checkout_slots_title'])) {
                    $slots_block_title = $general_options_defaults['checkout_slots_title'];
                }
            }
            $slots_block_descr = ocws_get_multilingual_option('ocws_common_checkout_slots_description');
            if (empty($slots_block_descr)) {
                if (isset($general_options_defaults['checkout_slots_description'])) {
                    $slots_block_descr = $general_options_defaults['checkout_slots_description'];
                }
            }
            ?>
            <h3><?php echo esc_html($slots_block_title); ?></h3>
            <div class="slot-message"><?php echo esc_html($slots_block_descr); ?></div>
            <?php if ($selected_slot_arr['date']) { ?>
                <div class="slot-message chosen-slot">
                    <?php echo __('בחרת תאריך למשלוח ', 'ocws') ?>
                    <span class="selected-date"><?php echo esc_html($selected_slot_arr['date']) ?></span>
                    <?php if (!$show_dates_only && $selected_slot_arr['slot_start'] && $selected_slot_arr['slot_end']) { ?>
                        <span class="selected-time"><?php echo esc_html($selected_slot_arr['slot_start']) . ' - ' . esc_html($selected_slot_arr['slot_end']) ?></span>
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="slot-list-container">
                <?php $slot_index = 0; ?>

                <?php if (get_option('ocws_common_dates_style', 'slider_style') == 'slider_style'): ?>
                    <?php if ($show_dates_only) { ?>
                    <div class="ocws-dates-only-list-slider owl-carousel">
                        <?php foreach ($output as $day) { ?>
                            <?php foreach ($day['slots'] as $slot) { ?>
                                <a style="" class="slot <?php echo $slot['class'] ?> " href="javascript:void(0)"
                                   data-date="<?php echo esc_attr($day['formatted_date']) ?>"
                                   data-weekday="<?php echo esc_attr($day['day_of_week']) ?>"
                                   data-slot-start="<?php echo esc_attr($slot['start']) ?>"
                                   data-slot-end="<?php echo esc_attr($slot['end']) ?>"
                                >
								<span class="slot-first-column">
									<span class="slot-weekday"><?php echo esc_html($day['weekday']) ?></span>
									<span class="slot-date"><?php echo esc_html($day['formatted_date']) ?></span>
								</span>
                                </a>
                                <?php $slot_index++; ?>
                            <?php } ?>
                        <?php } ?>
                    </div>
                <?php } else { ?>

                    <?php if ($show_as_slider) { ?>

                    <div class="ocws-days-list-slider">

                        <?php foreach ($output as $day) { ?>

                            <div style=""
                                 class="day-data <?php echo $selected_slot_arr['date'] == $day['formatted_date'] ? 'active' : '' ?>"
                                 data-id="<?php echo esc_html($day['formatted_date']) ?>">
                                <a href="javascript:void(0)" class="day-first-column">
                                    <span class="slot-weekday"><?php echo esc_html($day['weekday']) ?></span>
                                    <span class="slot-date"><?php echo esc_html($day['formatted_date']) ?></span>
                                </a>
                            </div>
                        <?php } ?>

                    </div>

                    <div class="ocws-days-with-slots-list-label"
                         style="<?php echo($selected_slot_arr['date'] ? '' : 'display: none') ?>"><?php echo esc_html(__('Choose an arrival time', 'ocws')) ?></div>

                    <div class="ocws-days-with-slots-list">

                        <?php foreach ($output as $day) { ?>

                            <div style="<?php echo $selected_slot_arr['date'] == $day['formatted_date'] ? '' : 'display:none' ?>"
                                 class="day-data <?php echo $selected_slot_arr['date'] == $day['formatted_date'] ? 'active' : '' ?>"
                                 data-rel-id="<?php echo esc_html($day['formatted_date']) ?>">

                                <?php foreach ($day['slots'] as $slot) { ?>
                                    <a class="slot slot-interval <?php echo $slot['class'] ?>"
                                       href="javascript:void(0)"
                                       data-date="<?php echo esc_attr($day['formatted_date']) ?>"
                                       data-weekday="<?php echo esc_attr($day['day_of_week']) ?>"
                                       data-slot-start="<?php echo esc_attr($slot['start']) ?>"
                                       data-slot-end="<?php echo esc_attr($slot['end']) ?>"
                                    >
                                        <?php echo esc_html($slot['start'] . ' - ' . $slot['end']) ?>
                                    </a>
                                <?php } ?>

                            </div>
                        <?php } ?>

                    </div>

                <?php } else { ?>

                    <?php foreach ($output as $day) { ?>

                    <div style="<?php echo ($slot_index > 2 && $state == 'less') ? 'display:none;' : '' ?>"
                         class="day-data <?php echo ($slot_index > 2) ? 'day-data-hidden' : '' ?>">
                        <a href="javascript:void(0)" class="day-first-column">
                            <span class="slot-weekday"><?php echo esc_html($day['weekday']) ?></span>
                            <span class="slot-date"><?php echo esc_html($day['formatted_date']) ?></span>
                        </a>
                        <?php foreach ($day['slots'] as $slot) { ?>
                            <a class="slot slot-interval <?php echo $slot['class'] ?>"
                               href="javascript:void(0)"
                               data-date="<?php echo esc_attr($day['formatted_date']) ?>"
                               data-weekday="<?php echo esc_attr($day['day_of_week']) ?>"
                               data-slot-start="<?php echo esc_attr($slot['start']) ?>"
                               data-slot-end="<?php echo esc_attr($slot['end']) ?>"
                            >
                                <?php echo esc_html($slot['start'] . ' - ' . $slot['end']) ?>
                            </a>
                        <?php } ?>

                    </div>
                <?php $slot_index++; ?>
                <?php } ?>

                <?php } ?>

                <?php } ?>
                <?php else: ?>

                    <div style="display: flex;flex-direction: row;">
                        <div style="display: flex; justify-content: flex-end; margin-left: 15px;">
                            <div style="display: flex;justify-content: flex-end;flex-direction: column;">
                                <label style="margin-bottom: 0;">
                                    <input type="text"
                                           id="datepicker_slider"
                                           class="date_picker_image"
                                           placeholder="<?php echo esc_html(__('Choose date', 'ocws')) ?>"
                                           style="position: relative; z-index: 999999999; width: 160px; height: 35px;">
                                </label>
                            </div>
                        </div>
                        <div class="datepicker_slider_slots" style="display: none; flex: 1;">
                            <div class="ocws-days-with-slots-list">

                            </div>
                        </div>
                    </div>
                    <script>
                        <?php
                        $begin_range = reset($output);
                        $end_range = end($output);
                        $available_dates = json_encode($output);
                        ?>
                        jQuery( function($) {
                            const PICK_DATE_ONLY = <?php echo intval($show_dates_only); ?>;
                            const VALIDATE_DATES = <?php echo $available_dates; ?>;

                            function dateFormat(date) {
                                return date.getDate().toString().padStart(2, '0') + '/' +
                                    (date.getMonth() + 1).toString().padStart(2, '0') + '/' +
                                    date.getFullYear().toString();
                            }

                            $( "#datepicker_slider" ).datepicker({
                                minDate: "<?php echo $begin_range['formatted_date']; ?>",
                                maxDate: "<?php echo $end_range['formatted_date']; ?>",
                                dateFormat: "dd/mm/yy",
                                beforeShowDay: function (date) {
                                    const current = dateFormat(date);
                                    const dates = VALIDATE_DATES.filter(function (item) {
                                        return current === item.formatted_date;

                                    })
                                    if (dates.length > 0) {
                                        return [true, '', ''];
                                    }
                                    return [false, '', ''];
                                },
                                onSelect: function (dateText, inst) {
                                    const root = $( "#datepicker_slider" ).closest('#oc-woo-shipping-additional');
                                    const $slots = $('.datepicker_slider_slots');
                                    const current = VALIDATE_DATES.filter(function (item) {
                                        return item.formatted_date === dateText;
                                    })
                                    if (current.length === 0) {
                                        throw new DOMException('Picked date disabled');
                                    }
                                    const slots = current[0]['slots'];
                                    if (slots.length === 0) {
                                        throw new DOMException('Picked date slots unavailable');
                                    }

                                    function reload() {
                                        // choose-shipping-popup
                                        const parent = $( "#datepicker_slider" ).parents('.choose-shipping-popup');
                                        if (parent.length === 0) {
                                            $('#oc-woo-shipping-additional').block({
                                                message: null,
                                                overlayCSS: {
                                                    background: '#fff',
                                                    opacity: 0.6
                                                }
                                            });

                                            $(document.body).trigger('update_checkout');
                                        }
                                    }

                                    if (root.find('.selected-date')) {
                                        root.find('.selected-date').text(dateText);
                                    }
                                    if (root.find('#order_expedition_date')) {
                                        if (root.find('#order_expedition_date').val() !== dateText) {
                                            if (root.find('#order_expedition_slot_start')) {
                                                root.find('#order_expedition_slot_start').val(slots[0].start);
                                            }
                                            if (root.find('#order_expedition_slot_end')) {
                                                root.find('#order_expedition_slot_end').val(slots[0].end);
                                            }
                                            reload();
                                        }
                                        root.find('#order_expedition_date').val(dateText);
                                    }

                                    if (!PICK_DATE_ONLY && slots.length >= 1) {
                                        $slots.find('.ocws-days-with-slots-list').html('');
                                        $slots.show();
                                        let $dayData = $(`<select style="width: 100%; color: unset;background: unset;height: 35px;color: unset;-webkit-appearance: auto;">
                                        <option
                                            data-date="${current[0].formatted_date}"
                                            data-weekday="${current[0].weekday}"
                                            data-slot-start="${slots[0]['start']}"
                                            data-slot-end="${slots[0]['end']}"
                                            class="slot slot-interval">
                                            <?php echo esc_html(__('Choose an arrival time', 'ocws')) ?>
                                        </option></select>`);

                                        for (const slot of current[0]['slots']) {
                                            let selected = '';
                                            if (slot['class'].includes('selected')) {
                                                selected = 'selected';
                                            }
                                            const $slot = $(`<option
                                                data-date="${current[0].formatted_date}"
                                                data-weekday="${current[0].weekday}"
                                                data-slot-start="${slot['start']}"
                                                data-slot-end="${slot['end']}"
                                                ${selected}
                                                class="slot slot-interval ${slot['class']}">${slot['start']} - ${slot['end']}</option>`);
                                            $dayData.append($slot);
                                        }
                                        $dayData.on('change', function (event) {
                                            const $this = $(this);
                                            const $item = $this.find('option:selected');
                                            $('input[name="order_expedition_date"]').val($item.data('date'));
                                            $('input[name="order_expedition_slot_start"]').val($item.data('slot-start'));
                                            $('input[name="order_expedition_slot_end"]').val($item.data('slot-end'));
                                            reload();
                                        });
                                        $slots.find('.ocws-days-with-slots-list').append($dayData);
                                    }
                                    else {
                                        $slots.hide();
                                    }
                                    // reload();
                                }
                            });

                            <?php if (ocws_get_value('order_expedition_date', $post_data)): ?>
                            $( "#datepicker_slider" ).datepicker('setDate', '<?php echo ocws_get_value('order_expedition_date', $post_data); ?>');
                            $(".ui-datepicker-current-day").trigger('click');
                            <?php endif; ?>
                        } );
                    </script>
                <?php endif; ?>

            </div>
            <?php if ($slot_index > 3 && !$show_as_slider) { ?>
                <div class="slot-list-buttons">
                    <button style="<?php echo ($state == 'more') ? 'display:none;' : '' ?>" type="button"
                            id="slot-list-button-show-all"><?php echo esc_html(__('Show all', 'ocws')) ?></button>
                    <button style="<?php echo ($state == 'less') ? 'display:none;' : '' ?>" type="button"
                            id="slot-list-button-show-less"><?php echo esc_html(__('Show less', 'ocws')) ?></button>
                </div>
            <?php } ?>
        <?php } else {

        } ?>

    </div>
    <?php
}

/**
 * @param \WC_Order $order
 * @return string
 */
function ocws_render_shipping_date_info($order)  // TODO : the same for pickup, forse show slot atart only + hide slot
{
    $force_hide_slot = (OC_Woo_Shipping_Group_Option::get_common_option('hide_slot_in_admin_mail', '') != 1 ? false : true);
    return OC_Woo_Shipping_Info::render_formatted_shipping_info($order, $force_hide_slot);
}


function ocws_get_city_title($city_id)
{
    $city_name = OCWS()->locations->get_city_name($city_id);
    if (!$city_name) {
        $group = OC_Woo_Shipping_Groups::get_group_by('location_code', $city_id);
        if (false === $group) return '';
        return $group->get_location_name_by_code($city_id);
    }
    return $city_name;
}

function ocws_get_city_title_translated($city_code, $city_name)
{
    return OCWS()->locations->translate_name($city_code, $city_name);
}

function ocws_get_group_id_by_city($city_id)
{
    $group = OC_Woo_Shipping_Groups::get_group_by('location_code', $city_id);
    if (false === $group) return 0;
    return $group->get_id();
}

function ocws_is_location_enabled($location_code)
{
    $data_store = new OC_Woo_Shipping_Group_Data_Store();
    $group_id = $data_store->get_group_by_location($location_code);
    $location_enabled = $data_store->is_location_enabled($location_code);
    $group_enabled = $data_store->is_group_enabled($group_id);

    return ($location_enabled && $group_enabled);
}

function ocws_is_affiliate_enabled($aff_id) {
    $affs_ds = new OCWS_LP_Affiliates();
    $aff = $affs_ds->db_get_affiliate($aff_id);
    if (!$aff) return false;
    return ($aff->is_enabled == 1);
}

function ocws_my_account_my_address_filter($address_arr, $customer_id, $address_type)
{

    if (isset($address_arr['city'])) {
        if (is_numeric($address_arr['city']) || ocws_is_hash($address_arr['city'])) {
            $address_arr['city'] = ocws_get_city_title($address_arr['city']);
        }
    }
    //error_log('ocws_my_account_my_address_filter: '.print_r($address_arr, 1));
    return $address_arr;
}

function ocws_get_acf_label($key, $post_id)
{
    if (!function_exists('get_field')) {
        return '';
    }
    $field = get_field($key, $post_id);
    if (!is_array($field)) {
        return $field;
    }
    if (!isset($field['label'])) {
        return '';
    }
    return $field['label'];
}

function ocws_get_acf_value($key, $post_id)
{
    if (!function_exists('get_field')) {
        return '';
    }
    $field = get_field($key, $post_id);
    if (!is_array($field)) {
        return $field;
    }
    if (!isset($field['value'])) {
        return '';
    }
    return $field['value'];
}


function ocws_get_template_part($file_name, $name = null)
{
    // Execute code for this part
    do_action('get_template_part_' . $file_name, $file_name, $name);

    // Setup possible parts
    $templates = array();
    $templates[] = $file_name;

    // Allow template parts to be filtered
    $templates = apply_filters('ocws_get_template_part', $templates, $file_name, $name);

    // Return the part that is found
    return ocws_locate_template($templates);
}

function ocws_locate_template($template_names)
{
    // No file found yet
    $located = false;

    // Try to find a template file
    foreach ((array)$template_names as $template_name) {

        // Continue if template is empty
        if (empty($template_name)) {
            continue;
        }

        // Trim off any slashes from the template name
        $template_name = ltrim($template_name, '/');
        // Check child theme first
        if (file_exists(trailingslashit(get_stylesheet_directory()) . 'ocws/' . $template_name)) {
            $located = trailingslashit(get_stylesheet_directory()) . 'ocws/' . $template_name;
            break;

            // Check parent theme next
        } else if (file_exists(trailingslashit(get_template_directory()) . 'ocws/' . $template_name)) {
            $located = trailingslashit(get_template_directory()) . 'ocws/' . $template_name;
            break;

            // Check theme compatibility last
        } else if (file_exists(trailingslashit(ocws_get_templates_dir()) . $template_name)) {
            $located = trailingslashit(ocws_get_templates_dir()) . $template_name;
            break;
        }
    }

    return $located;
}

function ocws_get_templates_dir()
{
    return OCWS_PATH . '/template-parts';
}

function ocws_get_value($key, $data)
{
    if (isset($data[$key]) && !empty($data[$key])) { // WPCS: input var ok, CSRF OK.
        return wc_clean(wp_unslash($data[$key])); // WPCS: input var ok, CSRF OK.
    }

    return '';
}

function ocws_get_languages()
{

    $languages = apply_filters('wpml_active_languages', NULL);
    $ret = array();

    if (!empty($languages)) {
        foreach ($languages as $l) {
            $ret[] = $l['language_code'];
        }
    }
    return $ret;
}

function ocws_get_multilingual_option($opt_name, $default = '')
{
    $l = ocws_get_languages();
    $locale = get_locale();

    if (!empty($l)) {
        if ($locale) {
            $curr_language = (strlen($locale) > 2) ? substr($locale, 0, 2) : $locale;
            return get_option($opt_name . '_' . $curr_language, $default);
        }
    }
    return get_option($opt_name, $default);
}

function ocws_translate_shipping_method_title($title, $shipping_id, $language = false)
{

    global $sitepress;

    if (has_filter('wpml_translate_single_string')) {

        $shipping_id = str_replace(':', '', $shipping_id);

        $translated_title = apply_filters(
            'wpml_translate_single_string',
            $title,
            'admin_texts_woocommerce_shipping',
            $shipping_id . '_shipping_method_title',
            $language ? $language : ($sitepress ? $sitepress->get_current_language() : false)
        );

        return $translated_title ?: $title;
    }
    return $title;
}

function ocws_is_advanced_shipping( $order ) {

    if (!$order || !($order instanceof WC_Order)) return false;
    $shipping_method_id = '';
    foreach ( $order->get_shipping_methods() as $shipping_method ) {
        $shipping_method_id = $shipping_method->get_method_id();
    }
    return (substr($shipping_method_id, 0, strlen(OCWS_Advanced_Shipping::SHIPPING_METHOD_ID)) == OCWS_Advanced_Shipping::SHIPPING_METHOD_ID);
}

function ocws_is_local_pickup( $order ) {

    if (!$order || !($order instanceof WC_Order)) return false;
    $shipping_method_id = '';
    foreach ( $order->get_shipping_methods() as $shipping_method ) {
        $shipping_method_id = $shipping_method->get_method_id();
    }
    return (substr($shipping_method_id, 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID);
}

function ocws_get_shipping_method_tag( $method_id ) {

    if (substr($method_id, 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID) {
        return OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG;
    }
    if (substr(method_id, 0, strlen(OCWS_Advanced_Shipping::SHIPPING_METHOD_ID)) == OCWS_Advanced_Shipping::SHIPPING_METHOD_ID) {
        return OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG;
    }
    return '';
}

function ocws_is_method_id_pickup( $method_id ) {

    return (substr($method_id, 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID);
}

function ocws_is_method_id_shipping( $method_id ) {

    return (substr($method_id, 0, strlen(OCWS_Advanced_Shipping::SHIPPING_METHOD_ID)) == OCWS_Advanced_Shipping::SHIPPING_METHOD_ID);
}

function ocws_get_google_maps_api_key()
{

    return get_option('ocws_common_google_maps_api_key');
}

function ocws_use_google_cities_and_polygons()
{

    return (get_option('ocws_common_use_google_cities_and_polygons') === '1' && ocws_get_google_maps_api_key());

}

function ocws_use_google_cities()
{

    return (get_option('ocws_common_use_google_cities') === '1' && ocws_get_google_maps_api_key());

}


function ocws_is_hash($hash)
{

    return (strlen($hash) === 32 && ctype_xdigit($hash));

}


function ocws_woocommerce_rest_prepare_shop_order_object_filter($response, $object, $request)
{

    if (empty($response->data))

        return $response;

    $order_data = $response->get_data();
    $order_id = $order_data['id'];

    $billing_city_name_meta = get_post_meta($order_id, '_billing_city_name', true);
    $shipping_city_name_meta = get_post_meta($order_id, '_billing_city_name', true);

    if ($billing_city_name_meta && isset($order_data['billing']) && isset($order_data['billing']['city'])) {

        $order_data['billing']['city'] = $billing_city_name_meta;

    }

    if ($shipping_city_name_meta && isset($order_data['shipping']) && isset($order_data['shipping']['city'])) {

        $order_data['shipping']['city'] = $shipping_city_name_meta;

    }

    if ($billing_city_name_meta && isset($order_data['billing_address']) && isset($order_data['billing_address']['city'])) {

        $order_data['billing_address']['city'] = $billing_city_name_meta;

    }

    if ($shipping_city_name_meta && isset($order_data['shipping_address']) && isset($order_data['shipping_address']['city'])) {

        $order_data['shipping_address']['city'] = $shipping_city_name_meta;

    }

    $response->data = $order_data;

    return $response;

}


function ocws_woocommerce_api_order_response_filter($order_data, $order) {

    $order_id = $order_data['id'];
    $billing_city_name_meta = get_post_meta($order_id, '_billing_city_name', true);
    $shipping_city_name_meta = get_post_meta($order_id, '_billing_city_name', true);

    if ($billing_city_name_meta && isset($order_data['billing']) && isset($order_data['billing']['city'])) {

        $order_data['billing']['city'] = $billing_city_name_meta;

    }

    if ($shipping_city_name_meta && isset($order_data['shipping']) && isset($order_data['shipping']['city'])) {

        $order_data['shipping']['city'] = $shipping_city_name_meta;

    }

    if ($billing_city_name_meta && isset($order_data['billing_address']) && isset($order_data['billing_address']['city'])) {

        $order_data['billing_address']['city'] = $billing_city_name_meta;

    }

    if ($shipping_city_name_meta && isset($order_data['shipping_address']) && isset($order_data['shipping_address']['city'])) {

        $order_data['shipping_address']['city'] = $shipping_city_name_meta;

    }

    return $order_data;
}

function ocws_get_formatted_address($street, $house_number, $apartment, $floor, $entercode) {

    //return $street . ' ' . $house_number . ', ' . ($apartment? __('Apartment', 'ocws') . ' ' . $apartment . ', ' : '') . ($floor? __('Floor', 'ocws') . ' ' . $floor . ', ' : '') . ($entercode? __('Enter code', 'ocws') . ' ' . $entercode : '');
    return $street . ' ' . $house_number; // . ', ' . ($apartment? __('Apartment', 'ocws') . ' ' . $apartment . ', ' : '') . ($floor? __('Floor', 'ocws') . ' ' . $floor . ', ' : '') . ($entercode? __('Enter code', 'ocws') . ' ' . $entercode : '');
}

/**
 * @param \WC_Order $order
 */
function ocws_save_full_address_to_order($order)	{

    $street = get_post_meta($order->get_id(), '_billing_street', true);
    $house_number = get_post_meta($order->get_id(), '_billing_house_num', true);
    $apartment = get_post_meta($order->get_id(), '_billing_apartment', true);
    $floor = get_post_meta($order->get_id(), '_billing_floor', true);
    $entercode = get_post_meta($order->get_id(), '_billing_enter_code', true);

    $address = ocws_get_formatted_address($street, $house_number, $apartment, $floor, $entercode);
    update_post_meta($order->get_id(), '_billing_address_1', $address);

    $street = get_post_meta($order->get_id(), '_shipping_street', true);
    $house_number = get_post_meta($order->get_id(), '_shipping_house_num', true);
    $apartment = get_post_meta($order->get_id(), '_shipping_apartment', true);
    $floor = get_post_meta($order->get_id(), '_shipping_floor', true);
    $entercode = get_post_meta($order->get_id(), '_shipping_enter_code', true);

    $address = ocws_get_formatted_address($street, $house_number, $apartment, $floor, $entercode);
    update_post_meta($order->get_id(), '_shipping_address_1', $address);
}

/**
 * @param string $billing_city
 * @param \WC_Order $order
 *
 * @return string
 */
function ocws_cardcom_parameter_billing_city_filter($billing_city, $order) {

    error_log('---------------------- ocws_cardcom_parameter_billing_city_filter ----------------------------');
    error_log('city to convert: "'.$billing_city.'"');
    if (!$order || !($order instanceof WC_Order)) {
        error_log('no order');
        return $billing_city;
    }
    $city = '';
    if (is_numeric($billing_city) || ocws_is_hash($billing_city)) {
        $city = get_post_meta( $order->get_id(), '_billing_city_name', true);
        if (!$city) {
            $city = ocws_get_city_title($billing_city);
        }
    }
    error_log('city: '.$city);
    error_log('---------------------------------------------------------');
    return ($city? $city : $billing_city);
}

/**
 * @param string $billing_city
 * @param \WC_Order $order
 *
 * @return string
 */
function ocws_order_billing_city_filter($billing_city, $order) {

    if (!$order || !($order instanceof WC_Order)) {
        return $billing_city;
    }
    $city = '';
    if (is_numeric($billing_city) || ocws_is_hash($billing_city)) {
        $city = get_post_meta( $order->get_id(), '_billing_city_name', true);
        if (!$city) {
            $city = ocws_get_city_title($billing_city);
        }
    }
    return ($city? $city : $billing_city);
}

/**
 * @param string $shipping_city
 * @param \WC_Order $order
 *
 * @return string
 */
function ocws_order_shipping_city_filter($shipping_city, $order) {

    if (!$order || !($order instanceof WC_Order)) {
        return $shipping_city;
    }
    $city = '';
    if (is_numeric($shipping_city) || ocws_is_hash($shipping_city)) {
        $city = get_post_meta( $order->get_id(), '_shipping_city_name', true);
        if (!$city) {
            $city = ocws_get_city_title($shipping_city);
        }
    }
    return ($city? $city : $shipping_city);
}

function ocws_get_day_of_week($date_str, $date_format = 'd/m/Y') {

    try {
        $dt = Carbon::createFromFormat($date_format, $date_str, ocws_get_timezone());
    }
    catch (InvalidArgumentException $e) {
        return '';
    }
    $weekdays = array(
        __('Sunday', 'ocws'),
        __('Monday', 'ocws'),
        __('Tuesday', 'ocws'),
        __('Wednesday', 'ocws'),
        __('Thursday', 'ocws'),
        __('Friday', 'ocws'),
        __('Saturday', 'ocws')
    );
    if (isset($weekdays[$dt->dayOfWeek])) {
        return $weekdays[$dt->dayOfWeek];
    }
    return '';
}

/**
 * Get numeric day of week (0=Sunday, 6=Saturday) from date string.
 *
 * @param string $date_str   Date string.
 * @param string $date_format Format, default d/m/Y.
 * @return int|null 0-6 or null on failure.
 */
function ocws_get_day_of_week_index( $date_str, $date_format = 'd/m/Y' ) {
    if ( empty( $date_str ) ) {
        return null;
    }
    try {
        $dt = \Carbon\Carbon::createFromFormat( $date_format, $date_str, ocws_get_timezone() );
        return (int) $dt->dayOfWeek;
    } catch ( \InvalidArgumentException $e ) {
        return null;
    }
}

/**
 * Get chosen delivery/pickup day index from session (0-6).
 * Uses shipping date or local pickup date if set.
 *
 * @return int|null 0-6 or null if no date chosen.
 */
function ocws_get_chosen_delivery_day_index() {
    if ( ! WC() || ! WC()->session ) {
        return null;
    }
    $date = '';
    if ( class_exists( 'OC_Woo_Shipping_Info' ) ) {
        $info = OC_Woo_Shipping_Info::get_shipping_info_from_session();
        if ( ! empty( $info['date'] ) ) {
            $date = $info['date'];
        }
    }
    if ( empty( $date ) && class_exists( 'OCWS_LP_Pickup_Info' ) ) {
        $lp_info = OCWS_LP_Pickup_Info::get_pickup_info_from_session();
        if ( ! empty( $lp_info['date'] ) ) {
            $date = $lp_info['date'];
        }
    }
    if ( empty( $date ) && function_exists( 'ocws_get_session_checkout_field' ) ) {
        $date = ocws_get_session_checkout_field( 'ocws_lp_pickup_date' );
    }
    return ocws_get_day_of_week_index( $date );
}

/**
 * After choosing delivery date: remove from cart items that are not available for that day and add notices.
 *
 * @param string $date Date string (e.g. d/m/Y).
 * @return array List of removed product names (for display).
 */
function ocws_remove_cart_items_unavailable_for_date( $date ) {
    if ( ! WC() || ! WC()->cart || empty( $date ) ) {
        return array();
    }
    $day_index = ocws_get_day_of_week_index( $date, 'd/m/Y' );
    if ( $day_index === null ) {
        return array();
    }
    $removed = array();
    $cart    = WC()->cart->get_cart();
    foreach ( $cart as $cart_key => $cart_item ) {
        $product = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] ? wc_get_product( $cart_item['variation_id'] ) : wc_get_product( $cart_item['product_id'] );
        if ( ! $product ) {
            continue;
        }
        if ( ! ocws_product_is_available_for_chosen_day( $product, $day_index ) ) {
            $removed[] = $product->get_name();
            WC()->cart->remove_cart_item( $cart_key );
        }
    }
    if ( ! empty( $removed ) ) {
        $list = implode( ', ', $removed );
        wc_add_notice(
            sprintf( __( 'המוצרים הבאים הוסרו מהסל כי אינם זמינים ביום המשלוח שנבחר: %s', 'ocws' ), $list ),
            'notice'
        );
    }
    return $removed;
}

/**
 * Get list of cart product names that would be unavailable for a given date (read-only, does not modify cart).
 *
 * @param string $date Date string (e.g. d/m/Y).
 * @return array List of product names.
 */
function ocws_get_cart_items_unavailable_for_date( $date ) {
    if ( ! WC() || ! WC()->cart || empty( $date ) ) {
        return array();
    }
    $day_index = ocws_get_day_of_week_index( $date, 'd/m/Y' );
    if ( $day_index === null ) {
        return array();
    }
    $names = array();
    $cart  = WC()->cart->get_cart();
    foreach ( $cart as $cart_item ) {
        $product = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] ? wc_get_product( $cart_item['variation_id'] ) : wc_get_product( $cart_item['product_id'] );
        if ( ! $product ) {
            continue;
        }
        if ( ! ocws_product_is_available_for_chosen_day( $product, $day_index ) ) {
            $names[] = $product->get_name();
        }
    }
    return $names;
}

/**
 * Get product's allowed delivery days (empty = not limited).
 *
 * @param int|WC_Product $product Product ID or object.
 * @return array List of day indices 0-6 (strings in meta).
 */
function ocws_get_product_limit_to_days( $product ) {
    $id = is_numeric( $product ) ? (int) $product : $product->get_id();
    $parent_id = $id;
    if ( ! is_numeric( $product ) && $product->is_type( 'variation' ) ) {
        $parent_id = $product->get_parent_id();
    }
    $days = get_post_meta( $parent_id, '_ocws_limit_to_days', true );
    return is_array( $days ) ? $days : array();
}

/**
 * Check if product is purchasable for the chosen delivery day.
 *
 * @param int|WC_Product $product Product ID or object.
 * @param int|null       $day_index Chosen day 0-6, or null if not chosen.
 * @return bool
 */
function ocws_product_is_available_for_chosen_day( $product, $day_index ) {
    $allowed = ocws_get_product_limit_to_days( $product );
    if ( empty( $allowed ) ) {
        return true;
    }
    if ( $day_index === null ) {
        return true;
    }
    return in_array( (string) $day_index, $allowed, true );
}

/**
 * Get "ניתן להזמין ל..." message for day-limited product when chosen day is not allowed.
 *
 * @param int|WC_Product $product Product ID or object.
 * @return string Empty if product is not day-limited or is available for chosen day.
 */
function ocws_product_limit_to_days_message( $product ) {
    $allowed = ocws_get_product_limit_to_days( $product );
    if ( empty( $allowed ) ) {
        return '';
    }
    $day_index = ocws_get_chosen_delivery_day_index();
    if ( $day_index === null ) {
        return ''; // No date chosen = all products available (don't show limit message).
    }
    if ( ocws_product_is_available_for_chosen_day( $product, $day_index ) ) {
        return '';
    }
    $day_names = array(
        '0' => __( 'Sunday', 'ocws' ),
        '1' => __( 'Monday', 'ocws' ),
        '2' => __( 'Tuesday', 'ocws' ),
        '3' => __( 'Wednesday', 'ocws' ),
        '4' => __( 'Thursday', 'ocws' ),
        '5' => __( 'Friday', 'ocws' ),
        '6' => __( 'Saturday', 'ocws' ),
    );
    $indices = array();
    foreach ( $allowed as $d ) {
        if ( isset( $day_names[ $d ] ) ) {
            $indices[] = (int) $d;
        }
    }
    if ( empty( $indices ) ) {
        return '';
    }
    $day_names_int = array( 0 => $day_names['0'], 1 => $day_names['1'], 2 => $day_names['2'], 3 => $day_names['3'], 4 => $day_names['4'], 5 => $day_names['5'], 6 => $day_names['6'] );
    $text = ocws_format_days_as_ranges( $indices, $day_names_int );
    return sprintf( __( 'ניתן להזמין ל%s', 'ocws' ), $text );
}

/**
 * Make product non-purchasable when day-limited and chosen delivery day not in allowed days.
 */
function ocws_filter_woocommerce_product_is_purchasable( $purchasable, $product ) {
    if ( ! $purchasable || ! $product ) {
        return $purchasable;
    }
    $day_index = ocws_get_chosen_delivery_day_index();
    if ( ocws_product_is_available_for_chosen_day( $product, $day_index ) ) {
        return $purchasable;
    }
    return false;
}

/**
 * Replace add to cart button with "ניתן להזמין ל..." in loop when product is day-limited and not available for chosen day.
 * Uses same markup as availability message on other sites (ocws-availability-message ocws-not-available).
 */
function ocws_filter_woocommerce_loop_add_to_cart_link( $link, $product, $args ) {
    $message = ocws_product_limit_to_days_message( $product );
    if ( $message === '' ) {
        return $link;
    }
    return '<div class="ocws-availability-message ocws-not-available">' . esc_html( $message ) . '</div>';
}

/**
 * Return day-specific class for product (e.g. ocws-day-msg-0, ocws-day-msg-0-1) for CSS targeting.
 *
 * @param WC_Product $product Product.
 * @return string '' or 'ocws-day-msg-{days}'
 */
function ocws_product_day_msg_class( $product ) {
    if ( ! $product ) {
        return '';
    }
    $allowed = ocws_get_product_limit_to_days( $product );
    if ( empty( $allowed ) ) {
        return '';
    }
    $message = ocws_product_limit_to_days_message( $product );
    if ( $message === '' ) {
        return '';
    }
    $sorted = array_values( array_map( 'strval', array_unique( array_map( 'intval', $allowed ) ) ) );
    sort( $sorted, SORT_NUMERIC );
    return 'ocws-day-msg-' . implode( '-', $sorted );
}

/**
 * Add class to product in loop when day-limited and not available for chosen day (for theme-agnostic JS).
 *
 * @param array      $classes Existing classes.
 * @param WC_Product $product Product object.
 * @return array
 */
function ocws_filter_woocommerce_post_class( $classes, $product ) {
    if ( ! $product ) {
        return $classes;
    }
    $message = ocws_product_limit_to_days_message( $product );
    if ( $message !== '' ) {
        $classes[] = 'ocws-day-limit-unavailable';
        $day_class = ocws_product_day_msg_class( $product );
        if ( $day_class !== '' ) {
            $classes[] = $day_class;
        }
    }
    return $classes;
}

/**
 * Also add day-limit class when theme uses post_class() / get_post_class() (so no theme change needed).
 *
 * @param array $classes Classes.
 * @param array $class   Extra class names.
 * @param int   $post_id Post ID.
 * @return array
 */
function ocws_filter_post_class( $classes, $class, $post_id = 0 ) {

    if ( ! $post_id || get_post_type( $post_id ) !== 'product' ) {
        return $classes;
    }
    $product = wc_get_product( $post_id );
    if ( ! $product ) {
        return $classes;
    }
    $message = ocws_product_limit_to_days_message( $product );
    if ( $message !== '' ) {
        $classes[] = 'ocws-day-limit-unavailable';
        $day_class = ocws_product_day_msg_class( $product );
        if ( $day_class !== '' ) {
            $classes[] = $day_class;
        }
    }
    return $classes;
}

/**
 * In loop: no output needed — overlay is CSS :before on the li (class added via post_class filters).
 */
function ocws_loop_day_limit_message_source() {
    // Design is purely CSS: li.ocws-day-msg-X gets :before with content.
}

/**
 * On single product, show "ניתן להזמין ל..." and hide add-to-cart form when product is day-limited and not available.
 */
function ocws_action_woocommerce_single_product_limit_to_days_message() {
    global $product;
    if ( ! $product ) {
        return;
    }
    $message = ocws_product_limit_to_days_message( $product );
    if ( $message !== '' ) {
        echo '<div class="ocws-availability-message ocws-not-available ocws-single-day-limit-message">' . esc_html( $message ) . '</div>';
        // Hide add-to-cart form so button is effectively cancelled (theme-agnostic).
        $form_sel = "form.cart";
        echo '<script>(function(){ function hide(){ var m = document.querySelector(".ocws-single-day-limit-message"); if (!m) return; var f = document.querySelector("' . esc_js( $form_sel ) . '"); if (!f) { var b = document.querySelector(".single_add_to_cart_button"); if (b && b.closest) f = b.closest("form"); } if (f) f.style.setProperty("display","none","important"); } if (document.readyState==="loading") document.addEventListener("DOMContentLoaded", hide); else hide(); })();</script>';
    }
}

/**
 * Register our handler for Iconic Quickview modal add-to-cart (run after Quickview init 11).
 * Quickview button is left on the hook; JS hides it only for day-limited unavailable products.
 */
function ocws_jckqv_day_limit_register() {
    if ( ! class_exists( 'JCKQV' ) ) {
        return;
    }
    $jckqv = JCKQV::instance();
    remove_action( 'jck_qv_summary', array( $jckqv, 'modal_part_add_to_cart' ), 30 );
    add_action( 'jck_qv_summary', 'ocws_jckqv_summary_add_to_cart_or_day_message', 30, 3 );
}

/**
 * Iconic Quickview: in modal, show "ניתן להזמין ל..." instead of add-to-cart when product is day-limited and not available.
 * Hooks into jck_qv_summary (priority 30) after Quickview registers; replaces their modal_part_add_to_cart output.
 *
 * @param int        $pid          Product ID.
 * @param WP_Post    $product_post Post object.
 * @param WC_Product $product      Product object.
 */
function ocws_jckqv_summary_add_to_cart_or_day_message( $pid, $product_post, $product ) {
    if ( ! $product ) {
        return;
    }
    $message = ocws_product_limit_to_days_message( $product );
    if ( $message !== '' ) {
        echo '<div class="ocws-availability-message ocws-not-available">' . esc_html( $message ) . '</div>';
        return;
    }
    if ( class_exists( 'JCKQV' ) ) {
        $jckqv = JCKQV::instance();
        if ( method_exists( $jckqv, 'modal_part_add_to_cart' ) ) {
            $jckqv->modal_part_add_to_cart( $pid, $product_post, $product );
        }
    }
}

/**
 * Hebrew day names for day-limit overlay :before content (0=Sunday … 6=Saturday).
 *
 * @return array
 */
function ocws_get_day_limit_day_names_he() {
    return array(
        0 => 'ראשון',
        1 => 'שני',
        2 => 'שלישי',
        3 => 'רביעי',
        4 => 'חמישי',
        5 => 'שישי',
        6 => 'שבת',
    );
}

/**
 * Format day indices as ranges: consecutive = "X עד Y", separate = " או ".
 * E.g. [1,2,3,5] => "שני עד רביעי או שבת"
 *
 * @param array $indices   Sorted day indices (0-6).
 * @param array $day_names Map index => name (e.g. 0=>'ראשון').
 * @return string
 */
function ocws_format_days_as_ranges( $indices, $day_names ) {
    if ( empty( $indices ) ) {
        return '';
    }
    $indices = array_values( array_map( 'intval', $indices ) );
    sort( $indices, SORT_NUMERIC );
    $ranges = array();
    $start  = $indices[0];
    $end    = $indices[0];
    for ( $i = 1; $i < count( $indices ); $i++ ) {
        if ( $indices[ $i ] === $end + 1 ) {
            $end = $indices[ $i ];
        } else {
            $ranges[] = ( $start === $end ) ? array( $start ) : array( $start, $end );
            $start = $indices[ $i ];
            $end   = $indices[ $i ];
        }
    }
    $ranges[] = ( $start === $end ) ? array( $start ) : array( $start, $end );
    $parts = array();
    foreach ( $ranges as $r ) {
        if ( count( $r ) === 1 ) {
            $parts[] = $day_names[ $r[0] ];
        } else {
            $parts[] = $day_names[ $r[0] ] . ' עד ' . $day_names[ $r[1] ];
        }
    }
    return implode( ' או ', $parts );
}

/**
 * Generate CSS for day-limit overlay :before — same structure as theme's outofstock rule.
 * li.product.outofstock .item-wrap.d-none.d-sm-block:before { … } → we use ocws-day-limit-unavailable + ocws-day-msg-{days}.
 *
 * @return string CSS.
 */
function ocws_get_day_limit_overlay_css() {
    $days_he = ocws_get_day_limit_day_names_he();
    // Same block as theme: li.product.outofstock .item-wrap.d-none.d-sm-block:before
    $block_lines = array(
        'content: "{{CONTENT}}"',
        'display: block',
        'position: absolute',
        'top: 125px',
        'text-align: center',
        'width: 100%',
        'background-color: rgb(255 255 255 / 80%)',
        'padding: 10px',
        'font-size: 16px',
        'color: #000',
        'z-index: 10',
    );
    $sel_base = 'li.product.ocws-day-limit-unavailable.ocws-day-msg-';
    $out      = "/* Day-limit overlay: if li has the class, show :before with content (like outofstock) */\n";
    $selectors_media = array();

    for ( $bits = 1; $bits < ( 1 << 7 ); $bits++ ) {
        $indices = array();
        for ( $i = 0; $i < 7; $i++ ) {
            if ( $bits & ( 1 << $i ) ) {
                $indices[] = $i;
            }
        }
        $class_suffix = implode( '-', $indices );
        $days_text    = ocws_format_days_as_ranges( $indices, $days_he );
        $content      = 'ניתן להזמין ל' . $days_text;
        $content_esc = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $content );
        $block_str   = str_replace( '{{CONTENT}}', $content_esc, implode( ";\n    ", $block_lines ) . ';' );
        $selector    = $sel_base . $class_suffix . ':before';
        $out        .= $selector . " {\n    " . $block_str . "\n}\n";
        $selectors_media[] = $selector;
    }

    $out .= "\n@media (max-width: 767px) {\n    " . implode( ",\n    ", $selectors_media ) . " {\n        top: 100px;\n    }\n}\n";

    return $out;
}

/**
 * Validate add to cart: block day-limited products when chosen delivery day is not allowed.
 */
function ocws_filter_woocommerce_add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
    if ( ! $passed ) {
        return $passed;
    }
    $product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
    if ( ! $product ) {
        return $passed;
    }
    $day_index = ocws_get_chosen_delivery_day_index();
    if ( ocws_product_is_available_for_chosen_day( $product, $day_index ) ) {
        return $passed;
    }
    wc_add_notice( ocws_product_limit_to_days_message( $product ), 'error' );
    return false;
}

function ocws_get_orders_count_report() {
    $totals = wp_count_posts( 'shop_order' );
    $data   = array();

    foreach ( wc_get_order_statuses() as $slug => $name ) {
        if ( ! isset( $totals->$slug ) ) {
            continue;
        }

        $data[] = array(
            'slug'  => str_replace( 'wc-', '', $slug ),
            'name'  => $name,
            'total' => (int) $totals->$slug,
        );
    }

    return $data;
}

function ocws_get_orders_count() {
    $totals = wp_count_posts( 'shop_order' );
    $count = 0;

    foreach ( wc_get_order_statuses() as $slug => $name ) {
        if ( ! isset( $totals->$slug ) ) {
            continue;
        }
        $count += (int) $totals->$slug;
    }

    return $count;
}

function ocws_b2bking_get_customer_group($user_id) {

    // first check if subaccount. If subaccount, user is equivalent with parent
    $account_type = get_user_meta($user_id, 'b2bking_account_type', true);
    if ($account_type === 'subaccount'){
        // get parent
        $is_subaccount = 'yes';
        $parent_account_id = get_user_meta ($user_id, 'b2bking_account_parent', true);
        $user_id = $parent_account_id;
    } else {
        $is_subaccount = 'no';
    }

    $user_is_b2b = get_the_author_meta( 'b2bking_b2buser', $user_id );
    if ($user_is_b2b === 'yes'){
        // do nothing
    } else {
        $user_is_b2b = 'no';
    }
    if ($user_is_b2b === 'yes'){
        if ($is_subaccount === 'yes'){
            return esc_html__('Subaccount of ','b2bking').esc_html(get_the_title(get_the_author_meta( 'b2bking_customergroup', $user_id )));
        } else {
            return esc_html(get_the_title(get_the_author_meta( 'b2bking_customergroup', $user_id )));
        }
    } else {
        return esc_html__('B2C Users', 'b2bking');
    }
}

function ocws_order_shipping_data_to_session($order_id) {

    $tag = get_post_meta( $order_id, 'ocws_shipping_tag', true );

    if ($tag == 'shipping') {

        $shipping_date = get_post_meta( $order_id, 'ocws_shipping_info_date', true );
        $slot_start = get_post_meta( $order_id, 'ocws_shipping_info_slot_start', true );
        $slot_end = get_post_meta( $order_id, 'ocws_shipping_info_slot_end', true );
        $city_id = get_post_meta( $order_id, '_billing_city', true);
        WC()->session->set('chosen_shipping_city', $city_id );
        $shipping_info = array(
            'date' => $shipping_date,
            'slot_start' => ($slot_start? : ''),
            'slot_end' => ($slot_end? : '')
        );
        WC()->session->set('ocws_shipping_info', serialize($shipping_info));
    }
    else if ($tag == 'pickup') {

        $pickup_date = get_post_meta( $order_id, 'ocws_shipping_info_date', true );
        $slot_start = get_post_meta( $order_id, 'ocws_shipping_info_slot_start', true );
        $slot_end = get_post_meta( $order_id, 'ocws_shipping_info_slot_end', true );
        $aff_id = get_post_meta( $order_id, 'ocws_lp_pickup_aff_id', true);

        WC()->session->set('chosen_pickup_aff', $aff_id );
        $shipping_info = array(
            'aff_id' => $aff_id,
            'date' => $pickup_date,
            'slot_start' => ($slot_start? : ''),
            'slot_end' => ($slot_end? : '')
        );
        WC()->session->set('ocws_shipping_info', serialize($shipping_info));

    }
}

function ocws_get_timezone() {
    error_log(wp_timezone_string());
    return wp_timezone_string();
}

function ocws_enabled_pickup_branches_exist() {

    $count = OCWS_LP_Affiliates::db_get_enabled_affiliates_count();
    return ($count > 0);
}

function ocws_enabled_shipping_locations_exist() {

    $count = OC_Woo_Shipping_Groups::db_get_enabled_locations_count();
    return ($count > 0);
}

function ocws_include_template_part($file_name, $name = null, $var = null, $return = false) {

    $located_path = ocws_get_template_part($file_name, $name);

    if ($located_path) {
        if ( $var && is_array( $var ) ) {
            extract( $var );
        }

        if( $return ) {
            ob_start();
        }

        // include file located
        include( $located_path );

        if( $return ) {
            return ob_get_clean();
        }
    }

    if ($return) {
        return '';
    }
}

function ocws_get_session_checkout_field( $field_name ) {

    if (!isset(WC()->session)) {
        return '';
    }
    $data = WC()->session->get( 'checkout_data' );
    if ( $data && isset($data[$field_name]) && !empty( $data[$field_name] ) ) {
        return is_bool( $data[$field_name] ) ? (int) $data[$field_name] : $data[$field_name];
    }
    return '';
}