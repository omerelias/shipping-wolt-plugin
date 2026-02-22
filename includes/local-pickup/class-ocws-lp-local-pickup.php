<?php

defined( 'ABSPATH' ) || exit;

class OCWS_LP_Local_Pickup {

    const PICKUP_METHOD_ID = 'oc_woo_local_pickup_method';

    const PICKUP_METHOD_TAG = 'pickup';

    public static function init() {

        self::define_public_hooks();
    }

    public static function enqueue_scripts() {

        wp_enqueue_script( 'ocws_lp', OCWS_ASSESTS_URL . 'js/oc-woo-pickup-public.js', array( 'jquery' ) ); // TODO: define the valid path

        wp_localize_script( 'ocws_lp', 'ocws_lp',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'localize' => array(
                    'loading' => __('Loading', 'ocws')
                )
            ));
    }

    public static function define_public_hooks() {

        add_action( 'init', array( 'OCWS_LP_Local_Pickup', 'init_settings' ) );
        add_action( 'wp_enqueue_scripts', array('OCWS_LP_Local_Pickup', 'enqueue_scripts') );
        add_filter( 'woocommerce_checkout_fields', array('OCWS_LP_Local_Pickup', 'custom_override_checkout_fields'), 1000 );
        add_action( 'woocommerce_after_checkout_billing_form', array('OCWS_LP_Local_Pickup', 'render_pickup_additional_fields'), 10, 1 );
        add_action( 'woocommerce_checkout_order_processed', array('OCWS_LP_Local_Pickup', 'save_shipping_to_order'), 10, 3 );
        add_action( 'woocommerce_before_checkout_process', array('OCWS_LP_Local_Pickup', 'validate_shipping_info') );
        add_filter( 'woocommerce_order_shipping_to_display', array('OCWS_LP_Local_Pickup', 'email_shipping_info'), 10, 2);

        add_action( 'woocommerce_after_checkout_validation', array('OCWS_LP_Pickup_Info', 'validate_checkout_posted_data'), 20, 2 );

    }

    public static function init_settings() {


        $affOpts = new OCWS_LP_Affiliate_Option();
        $affOpts->init_pickup_options();
        $affOpts->register_pickup_options();
    }

    public static function custom_override_checkout_fields($fields) {

        if (!class_exists('OC_Woo_Local_Pickup_Method')) {
            //return $fields;
        }

        $hide_fields = array(
            'billing_address_1',
            'billing_street',
            'billing_city',
            'billing_postcode' ,
            'billing_house_num' ,
            'billing_apartment' ,
            'billing_enter_code' ,
            'billing_floor',
            'billing_city_name',
            'billing_google_autocomplete'
        );

        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        $chosen_shipping = $chosen_methods[0];
        $local_pickup_chosen = ($chosen_shipping && (substr($chosen_shipping, 0, strlen(self::PICKUP_METHOD_ID)) === self::PICKUP_METHOD_ID));

        if ($local_pickup_chosen) {

            foreach ($hide_fields as $field) {

                //unset( $fields['billing'][$field] );
                $fields['billing'][$field]['required'] = false;
                $fields['billing'][$field]['class'][] = 'ocws-hidden-form-field';
            }

            $affs_ds = new OCWS_LP_Affiliates();

            $affiliates_dropdown = $affs_ds->get_affiliates_dropdown(true);

            if (count($affiliates_dropdown) <= 1) {

                $aff_args = wp_parse_args( array(
                    'type' => 'select',
                    'options' => $affs_ds->get_affiliates_dropdown(true),
                    'input_class' => array(
                        'ocws-lp-enhanced-select',
                    ),
                    'placeholder' => '',
                    'label'     => __('Choose pickup location', 'ocws'),
                    'required'  => true,
                    'class'     => array('form-row', 'ocws-hidden-form-field'),
                    'clear'     => false
                ) );
            }
            else {

                // add affiliates dropdown
                $aff_args = wp_parse_args( array(
                    'type' => 'select',
                    'options' => ['' => ''] + $affs_ds->get_affiliates_dropdown(true),
                    'input_class' => array(
                        'ocws-lp-enhanced-select',
                    ),
                    'placeholder' => '',
                    'label'     => __('Choose pickup location', 'ocws'),
                    'required'  => true,
                    'class'     => array('form-row'),
                    'clear'     => false
                ) );
            }

            if (!isset($fields['ocws_lp'])) {
                $fields['ocws_lp'] = array();
            }
            $fields['ocws_lp']['ocws_lp_pickup_aff_id'] = $aff_args;

            $post_data = self::get_posted_data();

            if (!isset($post_data['ocws_lp_pickup_aff_id']) || empty($post_data['ocws_lp_pickup_aff_id'])) {
                $post_data['ocws_lp_pickup_aff_id'] = WC()->checkout()->get_value('ocws_lp_pickup_aff_id');
            }

            $selected_slot = OCWS_LP_Pickup_Info::get_pickup_info();
            $popup_pickup_info = OCWS_LP_Pickup_Info::get_pickup_info_from_session();

            $chosen_aff_id = $post_data['ocws_lp_pickup_aff_id'];

            if (null !== $selected_slot) {
                if (isset($selected_slot['aff_id']) && $selected_slot['aff_id']) {
                    $chosen_aff_id = $selected_slot['aff_id'];
                } else if ($popup_pickup_info['aff_id']) {
                    $chosen_aff_id = $popup_pickup_info['aff_id'];
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

            if ($chosen_aff_id) {

                $selected_slot_arr = array(
                    'date' => '',
                    'slot_start' => '',
                    'slot_end' => ''
                );
                if (null !== $selected_slot) {
                    if (isset($selected_slot['date']) && $selected_slot['date']) {
                        $selected_slot_arr['date'] = $selected_slot['date'];
                    }
                    else if ($popup_pickup_info['date']) {
                        $selected_slot_arr['date'] = $popup_pickup_info['date'];
                    }
                    if (isset($selected_slot['slot_start']) && $selected_slot['slot_start']) {
                        $selected_slot_arr['slot_start'] = $selected_slot['slot_start'];
                    }
                    else if ($popup_pickup_info['slot_start']) {
                        $selected_slot_arr['slot_start'] = $popup_pickup_info['slot_start'];
                    }
                    if (isset($selected_slot['slot_end']) && $selected_slot['slot_end']) {
                        $selected_slot_arr['slot_end'] = $selected_slot['slot_end'];
                    }
                    else if ($popup_pickup_info['slot_end']) {
                        $selected_slot_arr['slot_end'] = $popup_pickup_info['slot_end'];
                    }
                }

                $field_names = array(
                    'ocws_lp_pickup_date' => $selected_slot_arr['date'],
                    'ocws_lp_pickup_slot_start' => $selected_slot_arr['slot_start'],
                    'ocws_lp_pickup_slot_end' => $selected_slot_arr['slot_end'],
                );

                foreach ($field_names as $field_name => $field_value) {

                    $fields['ocws_lp'][$field_name] = array(
                        'required' => false,
                        'type' => 'hidden',
                        'default' => $field_value,
                        'class'     => array('ocws-hidden-form-field'),
                    );
                }
            }
        }
        return $fields;

    }



    public static function render_pickup_additional_fields($checkout=null) {

        $chosen_methods = WC()->session->get('chosen_shipping_methods', array());

        $post_data = self::get_posted_data();

        if (empty($chosen_methods)) {
            if (isset($post_data['shipping_method']) && is_array($post_data['shipping_method'])) {
                $chosen_methods = $post_data['shipping_method'];
            }
        }

        if (!isset($post_data['ocws_lp_pickup_aff_id']) || empty($post_data['ocws_lp_pickup_aff_id'])) {
            $post_data['ocws_lp_pickup_aff_id'] = WC()->checkout()->get_value('ocws_lp_pickup_aff_id');
        }

        if (empty($chosen_methods)) {
            ?>
            <!-- empty methods -->
            <div id="oc-woo-pickup-additional"></div>
            <?php
            return;
        }

        $is_local_pickup = false;

        foreach ($chosen_methods as $shippingMethod) {
            if (substr($shippingMethod, 0, strlen(self::PICKUP_METHOD_ID)) == self::PICKUP_METHOD_ID) {
                $is_local_pickup = true;
                break;
            }
        }
        if (!$is_local_pickup) {
            ?>
            <!-- not local pickup -->
            <div id="oc-woo-pickup-additional"></div>
            <?php
            return;
        }

        $fields = WC()->checkout()->get_checkout_fields( 'ocws_lp' );

        ?>
        <div id="oc-woo-pickup-additional">

            <?php

            if (is_checkout()) {
                $slots_block_title_m = OCWS_LP_Affiliate_Option::get_common_option_ml('checkout_slots_title');
                $slots_block_title = $slots_block_title_m->option_value;
                $slots_block_descr_m = OCWS_LP_Affiliate_Option::get_common_option_ml('checkout_slots_description');
                $slots_block_descr = $slots_block_descr_m->option_value;
            }
            else {
                $slots_block_title_m = OCWS_LP_Affiliate_Option::get_common_option_ml('popup_slots_title');
                $slots_block_title = $slots_block_title_m->option_value;
                $slots_block_descr_m = OCWS_LP_Affiliate_Option::get_common_option_ml('popup_slots_description');
                $slots_block_descr = $slots_block_descr_m->option_value;
            }

            ?>
            <div style="display: none;"><?php var_dump($slots_block_title_m); ?></div>

            <div class="slot-message"><?php echo esc_html($slots_block_descr); ?></div>

            <?php

            $selected_slot = OCWS_LP_Pickup_Info::get_pickup_info();
            $popup_shipping_info = OCWS_LP_Pickup_Info::get_pickup_info_from_session();

            $chosen_aff_id = $post_data['ocws_lp_pickup_aff_id'];

            if (null !== $selected_slot) {
                if (isset($selected_slot['aff_id']) && $selected_slot['aff_id']) {
                    $chosen_aff_id = $selected_slot['aff_id'];
                } else if ($popup_shipping_info['aff_id']) {
                    $chosen_aff_id = $popup_shipping_info['aff_id'];
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

            woocommerce_form_field( 'ocws_lp_pickup_aff_id', $fields['ocws_lp_pickup_aff_id'], intval($chosen_aff_id) );
            echo '<span style="display: none;">chosen aff id: ' . $chosen_aff_id . '</span>';
            ?>

            <?php if ($chosen_aff_id) { ?>

                <?php
                woocommerce_form_field( 'ocws_lp_pickup_date', $fields['ocws_lp_pickup_date'] );
                woocommerce_form_field( 'ocws_lp_pickup_slot_start', $fields['ocws_lp_pickup_slot_start'] );
                woocommerce_form_field( 'ocws_lp_pickup_slot_end', $fields['ocws_lp_pickup_slot_end'] );
                ?>

                <?php

                $show_dates_only = get_option('ocws_lp_common_show_dates_only', '') != 1 ? false : true;
                $show_slot_start_only = get_option('ocws_lp_common_show_slot_start_only', '') != 1 ? false : true;

                $oc_slots = new OCWS_LP_Pickup_Slots($chosen_aff_id);
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

                echo '<span style="display: none;">$selected_slot_arr: ' . print_r($selected_slot_arr, 1) . '</span>';
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
                echo '<span style="display: none;">$output: ' . print_r($output, 1) . '</span>';
                // TODO: core functions line 251

                ?>

                <?php if (count($output) > 0) { ?>
                    <?php

                    $dates_display_style = get_option('ocws_lp_common_dates_style');
                    echo '<span style="display: none;">dates display style: ' . print_r($dates_display_style, 1) . '</span>';
                    ?>

                    <?php if ($selected_slot_arr['date']) { ?>
                        <div class="slot-message chosen-slot">
                            <?php echo __('Your pickup date', 'ocws') ?>
                            <span class="selected-date"><?php echo esc_html($selected_slot_arr['date']) ?></span>
                            <?php if (!$show_dates_only && $selected_slot_arr['slot_start'] && $selected_slot_arr['slot_end']) { ?>
                                <span class="selected-time"><?php echo esc_html($selected_slot_arr['slot_start']) . ($show_slot_start_only? '' : ' - ' . esc_html($selected_slot_arr['slot_end'])) ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <h3 class="oc-woo-pickup-additional-title"><?php echo esc_html($slots_block_title); ?></h3>
                    <div class="slot-list-container">
                        <?php $slot_index = 0; ?>

                        <?php if ($dates_display_style != 'calendar_style') { ?>
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
                                     style="<?php echo($selected_slot_arr['date'] ? '' : 'display: none') ?>"><?php echo esc_html(__('Choose pickup time', 'ocws')) ?></div>

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
                                                    <span><?php echo ($show_slot_start_only? esc_html($slot['start']) : esc_html($slot['start'] . ' - ' . $slot['end'])) ?></span>
                                                </a>
                                            <?php } ?>

                                        </div>
                                    <?php } ?>

                                </div>


                            <?php } ?>
                        <?php } else { // datepicker style?>

                            <div style="display: flex;flex-direction: row;">
                                <div style="display: flex; justify-content: flex-end; margin-left: 15px;">
                                    <div style="display: flex;justify-content: flex-end;flex-direction: column;">
                                        <label style="margin-bottom: 0;">
                                            <input type="text"
                                                   id="ocws_lp_datepicker_slider"
                                                   class="date_picker_image"
                                                   placeholder="<?php echo esc_html(__('Choose pickup date', 'ocws')) ?>"
                                                   style="position: relative; z-index: 99999; width: 160px; height: 35px;">
                                        </label>
                                    </div>
                                </div>
                                <div class="datepicker_slider_slots" style="display: none; flex: 1;">
                                    <div class="ocws-days-with-slots-list">

                                    </div>
                                </div>
                            </div>
                            <style>
                                #ui-datepicker-div {
                                    z-index: 1000001 !important;
                                }
                            </style>
                            <script>
                                <?php
                                $begin_range = reset($output);
                                $end_range = end($output);
                                $available_dates = json_encode($output);
                                ?>
                                jQuery( function($) {
                                    const PICK_DATE_ONLY = <?php echo intval($show_dates_only); ?>;
                                    const VALIDATE_DATES = <?php echo $available_dates; ?>;
                                    const SLOT_START_ONLY = <?php echo intval($show_slot_start_only); ?>;

                                    function dateFormat(date) {
                                        return date.getDate().toString().padStart(2, '0') + '/' +
                                            (date.getMonth() + 1).toString().padStart(2, '0') + '/' +
                                            date.getFullYear().toString();
                                    }

                                    $( "#ocws_lp_datepicker_slider" ).datepicker({
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
                                            const root = $( "#ocws_lp_datepicker_slider" ).closest('#oc-woo-pickup-additional');
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
                                                const parent = $( "#ocws_lp_datepicker_slider" ).parents('.choose-shipping-popup');
                                                if (parent.length === 0) {
                                                    $('#oc-woo-pickup-additional').block({
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
                                            if (root.find('#ocws_lp_pickup_date')) {
                                                if (root.find('#ocws_lp_pickup_date').val() !== dateText) {
                                                    if (root.find('#ocws_lp_pickup_slot_start')) {
                                                        root.find('#ocws_lp_pickup_slot_start').val(slots[0].start);
                                                    }
                                                    if (root.find('#ocws_lp_pickup_slot_end')) {
                                                        root.find('#ocws_lp_pickup_slot_end').val(slots[0].end);
                                                    }
                                                    reload();
                                                }
                                                root.find('#ocws_lp_pickup_date').val(dateText);
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
                                                    <?php echo esc_html(__('Choose pickup time', 'ocws')) ?>
                                                    </option></select>`);

                                                for (const slot of current[0]['slots']) {
                                                    let selected = '';
                                                    if (slot['class'].includes('selected')) {
                                                        selected = 'selected';
                                                    }
                                                    let slotFormattedTitle = (SLOT_START_ONLY? slot['start'] : slot['start'] + ' - ' + slot['end']);
                                                    const $slot = $(`<option
                                                    data-date="${current[0].formatted_date}"
                                                    data-weekday="${current[0].weekday}"
                                                    data-slot-start="${slot['start']}"
                                                    data-slot-end="${slot['end']}"
                                                    ${selected}
                                                class="slot slot-interval ${slot['class']}">${slotFormattedTitle}</option>`);
                                                    $dayData.append($slot);
                                                }
                                                $dayData.on('change', function (event) {
                                                    const $this = $(this);
                                                    const $item = $this.find('option:selected');
                                                    $('input[name="ocws_lp_pickup_date"]').val($item.data('date'));
                                                    $('input[name="ocws_lp_pickup_slot_start"]').val($item.data('slot-start'));
                                                    $('input[name="ocws_lp_pickup_slot_end"]').val($item.data('slot-end'));
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

                                    <?php if (ocws_get_value('ocws_lp_pickup_date', $post_data)): ?>
                                    $( "#ocws_lp_datepicker_slider" ).datepicker('setDate', '<?php echo ocws_get_value('ocws_lp_pickup_date', $post_data); ?>');
                                    $(".ui-datepicker-current-day").trigger('click');
                                    <?php endif; ?>
                                } );
                                //# sourceURL=browsertools://custom/localPickupSlots.js
                            </script>

                        <?php } ?>

                    </div>

                <?php } ?>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * @param int $orderId
     * @param array $data
     * @param \WC_Order $order
     */
    public static function save_shipping_to_order($orderId, $data, $order)
    {
        OCWS_LP_Pickup_Info::save_to_order($order);
    }

    public static function validate_shipping_info()
    {
        $message = '<ul class="woocommerce-error" role="alert"><li>%s</li></ul>';
        $response = array(
            'messages'  => '',
            'refresh'   => false,
            'reload'    => false,
            'result'    => 'failure'
        );

        if (!isset($_POST['shipping_method'])) {
            return;
        }

        $shipping_methods = $_POST['shipping_method'];
        $is_ocws_lp = false;

        foreach ($shipping_methods as $shipping_method) {
            if (substr($shipping_method, 0, strlen(self::PICKUP_METHOD_ID)) == self::PICKUP_METHOD_ID) {
                $is_ocws_lp = true;
                break;
            }
        }

        if ($is_ocws_lp) {
            $shipping_info = OCWS_LP_Pickup_Info::get_pickup_info();
            if (!$shipping_info || !$shipping_info['date'] || !$shipping_info['slot_start'] || !$shipping_info['slot_end'] ) {
                $response['messages'] = sprintf($message, __('Please choose time slot', 'ocws'));
                header('Content-type: application/json');
                echo json_encode($response);
                exit;
            }
        }
    }

    /**
     * @param string $text
     * @param \WC_Order $order
     * @return string
     */
    public static function email_shipping_info($text, $order)
    {
        $force_hide_slot_opt = OCWS_LP_Affiliate_Option::get_common_option('hide_slot_in_admin_mail', '');
        $force_hide_slot = ($force_hide_slot_opt->option_value != 1 ? false : true);
        $force_start_hour_only_opt = OCWS_LP_Affiliate_Option::get_common_option('show_slot_start_only', '');
        $force_start_hour_only = ($force_start_hour_only_opt->option_value != 1 ? false : true);
        // only in customer emails
        $html = OCWS_LP_Pickup_Info::render_formatted_pickup_info( $order, $force_hide_slot, $force_start_hour_only_opt );
        //$html = '';
        return ($text . $html);
    }


    protected static function get_posted_data() {

        if (isset($_POST['post_data'])) {

            parse_str($_POST['post_data'], $post_data);

        } else {

            $post_data = $_POST; // fallback for final checkout (non-ajax)

        }
        return $post_data;
    }
}

