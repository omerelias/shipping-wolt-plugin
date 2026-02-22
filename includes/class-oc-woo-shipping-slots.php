<?php

use Carbon\Carbon;

defined( 'ABSPATH' ) || exit;

class OC_Woo_Shipping_Slots {

    private $scheduling_type = 'weekly';

    private $date_format = 'd/m/Y';

    private $time_format = 'G:i';

    protected $tz = '';

    protected $days = array();

    protected $location_code = false;

    protected $group_id = false;

    protected $min_total_to_enable = false;

    protected $preorder_days = 7;

    protected $minutes_to_prepare_order = 0;

    protected $shipping_price = 0;

    protected $min_total_for_free_shipping = false;

    /*
     * OC_Woo_Shipping_Schedule $schedule
     */
    protected $schedule;

    protected $max_hour_for_today = '23:59';

    protected $max_products_per_slot = false;

    protected $closing_weekdays = array();

    protected $closing_dates = array();

    protected $allowed_scheduling_types = array('weekly', 'dates');

    protected $repeat_every = 1;

    protected $repeat_start_date = '';

    public function __construct($location_code) {

        $this->tz = ocws_get_timezone();
        $data_store = new OC_Woo_Shipping_Group_Data_Store();
        $group_id = $data_store->get_group_by_location( $location_code );
        $this->schedule = new OC_Woo_Shipping_Schedule();
        if (false !== $group_id) {
            $this->group_id = $group_id;
            $this->location_code = $location_code;
            $this->init_options();
        }
    }

    public function init_options() {
        
        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'min_total', false);
        if (!empty($data['option_value'])) {
            $this->min_total_to_enable = floatval($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'preorder_days', 7);
        if (!empty($data['option_value'])) {
            $this->preorder_days = intval($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'min_wait_times', 0);
        if (!empty($data['option_value'])) {
            $this->minutes_to_prepare_order = intval($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_location_option($this->location_code, $this->group_id, 'shipping_price', 0);
        if (!empty($data['option_value'])) {
            $this->shipping_price = floatval($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'min_total_for_free_shipping', false);
        if (!empty($data['option_value'])) {
            $this->min_total_for_free_shipping = floatval($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'delivery_scheduling_type', 'weekly');
        if (in_array( $data['option_value'], $this->allowed_scheduling_types )) {
            $this->scheduling_type = $data['option_value'];
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'delivery_schedule_' . $this->scheduling_type, array());
        if (is_array( $data['option_value'] )) {

            $this->schedule->set_scheduling_type($this->scheduling_type);
            $this->schedule->set_days($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'closing_weekdays', '');
        if (!empty($data['option_value']) && $data['option_value'] != 0) {
            $this->closing_weekdays = ocws_numbers_list_to_array($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'closing_dates', '');
        if (!empty($data['option_value'])) {
            $this->closing_dates = ocws_dates_list_to_array($data['option_value']);
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'max_hour_for_today', '');
        if (!empty($data['option_value'])) {
            $this->max_hour_for_today = $data['option_value'];
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'delivery_schedule_repeat', 0);
        if (!empty($data['option_value'])) {
            $this->repeat_every = (int) $data['option_value'];
        }
        else {
            $this->repeat_every = 1;
        }

        $data = OC_Woo_Shipping_Group_Option::get_option($this->group_id, 'delivery_schedule_repeat_start', '');
        if (!empty($data['option_value'])) {
            $this->repeat_start_date = $data['option_value'];
            try {
                $d = Carbon::createFromFormat($this->date_format, $this->repeat_start_date, $this->tz);
            }
            catch (InvalidArgumentException $e) {
                $this->repeat_start_date = '';
            }
        }

        // TODO: $max_products_per_slot
    }

    public function calculate_slots_for_checkout() {

        $this->days = array();
        if (!$this->schedule) return $this->days;
//        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        $today = Carbon::now($this->tz);
        $today_formatted = $today->format($this->date_format);

        $dates_to_walk_on = array();
        if ($this->scheduling_type == 'dates') {
            $dates = $this->schedule->get_dates();
            foreach ($dates as $date) {
                $dt = Carbon::createFromFormat($this->date_format, $date, $this->tz);
                if ($dt->greaterThanOrEqualTo($today)) {

                    $filter_picker = $this->schedule->get_filter_picker_by_date( $date );
                    if ($filter_picker == 'before_day') {
                        $filter_picker = 1;
                    }
                    //error_log('filter picker by date ' . $date . ': ' . $filter_picker);
                    if (!$this->is_date_excluded( $date, $this->schedule->get_max_hour_by_date( $date ), ($filter_picker == 'same_day'? false : $filter_picker) )) {
                        $dates_to_walk_on[] = array(
                            'formatted_date' => $date,
                            'day_of_week' => $dt->dayOfWeek,
                            'slots' => $this->schedule->get_slots_by_date( $date )
                        );
                    }
                }
            }
        }
        else {
            $end = Carbon::now($this->tz)->addDays($this->preorder_days);
            $period = \Carbon\CarbonPeriod::between($today, $end);

            foreach ($period as $dt) {

                $date = $dt->format($this->date_format);
                /* repeating option */
                if (! $this->is_date_in_active_week($date)) {
                    continue;
                }
                $filter_picker = $this->schedule->get_filter_picker_by_weekday( $dt->dayOfWeek );
                if ($filter_picker == 'before_day') {
                    $filter_picker = 1;
                }
                $max_hour = $this->schedule->get_max_hour_by_weekday( $dt->dayOfWeek );
                if (!$max_hour) {
                    $max_hour = '23:59';
                }

                if (is_numeric($filter_picker) && intval($filter_picker) > 0) {
                    if ($this->is_date_excluded($date, '')) {
                        continue;
                    }

                    $pDay = clone $dt;
                    $pDay->subDays(intval($filter_picker));
                    $now = Carbon::now($this->tz);
                    $max = Carbon::createFromFormat(
                        sprintf('%s %s', $this->date_format, $this->time_format),
                        sprintf('%s %s', $pDay->format($this->date_format), $max_hour),
                        $this->tz
                    );

                    if ($now->greaterThan($max)) {
                        continue;
                    }

                    $dates_to_walk_on[] = array(
                        'formatted_date' => $date,
                        'day_of_week' => $dt->dayOfWeek,
                        'slots' => $this->schedule->get_slots_by_weekday($dt->dayOfWeek)
                    );
                }
                else {
                    if (!$this->is_date_excluded($date, $max_hour)) {
                        $dates_to_walk_on[] = array(
                            'formatted_date' => $date,
                            'day_of_week' => $dt->dayOfWeek,
                            'slots' => $this->schedule->get_slots_by_weekday($dt->dayOfWeek)
                        );
                    }
                }

//                if (!$this->is_date_excluded( $date, $this->schedule->get_max_hour_by_weekday( $dt->dayOfWeek ) )) {
//                    if ($filter_picker == 'before_day') {
//                        $ptd = $dt->subDay();
//                        $pDate = $ptd->format($this->date_format);
//
//                        /*if ($this->is_date_excluded( $pDate, $this->schedule->get_max_hour_by_weekday( $ptd->dayOfWeek ) )) {
//                            continue;
//                        }
//                        else {
//                            $new_slots = array();
//                            $slots = $this->schedule->get_slots_by_weekday($dt->dayOfWeek);
//                            foreach ($slots as $slot) {
//                                if ($this->is_today_slot_relevant($slot, $today, $today_formatted)) {
//                                    $new_slots[] = $slot;
//                                }
//                            }
//                            if (count($new_slots) == 0) {
//                                unset($new_slots, $slots);
//                                continue;
//                            }
//                            unset($new_slots, $slots);
//                        }*/
//                    }
//                    $dates_to_walk_on[] = array(
//                        'formatted_date' => $date,
//                        'day_of_week' => $dt->dayOfWeek,
//                        'slots' => $this->schedule->get_slots_by_weekday($dt->dayOfWeek)
//                    );
//                }
            }
        }

        $days = array();

        foreach ($dates_to_walk_on as $data) {

            if ( count($data['slots']) > 0 ) {

                if ( $data['formatted_date'] == $today_formatted ) {
                    $new_slots = array();
                    foreach ($data['slots'] as $slot) {
                        if ($this->is_today_slot_relevant($slot, $today, $today_formatted)) {
                            $new_slots[] = $slot;
                        }
                    }
                    if (count($new_slots) > 0) {
                        $data['slots'] = $new_slots;
                        $days[] = $data;
                    }
                }
                else {
                    $days[] = $data;
                }
            }
        }

        foreach ($days as $data) {

            if ( count($data['slots']) > 0 ) {

                $new_slots = array();

                foreach ($data['slots'] as $slot) {

                    $max_orders = (isset( $slot['data'] ) && isset( $slot['data']['orders'] )? $slot['data']['orders'] : 0);
                    $max_products = (isset( $slot['data'] ) && isset( $slot['data']['products'] )? $slot['data']['products'] : 0);

                    if ( $this->is_slot_rules_fit( $data['formatted_date'], $slot['start'], $slot['end'], $max_orders, $max_products ) ) {
                        $new_slots[] = $slot;
                    }

                }
                if (count($new_slots) > 0) {
                    $data['slots'] = $new_slots;
                    $this->days[] = $data;
                }
            }
        }
        return $this->days;
    }


    public function is_today_slot_relevant($slot, $now, $today_formatted) {

        $show_slot_after_start = (isset($slot['data']['show_slot_after_start'])? !!$slot['data']['show_slot_after_start'] : false);
        $hide_slot_x_min_before_end = (isset($slot['data']['hide_slot_x_min_before_end'])? (int) $slot['data']['hide_slot_x_min_before_end'] : 0);
        if ($show_slot_after_start && $slot['end']) {
            try {
                $slotEnd = Carbon::createFromFormat($this->date_format . ' ' . $this->time_format, $today_formatted . ' ' . $slot['end'], $this->tz);
                if ($hide_slot_x_min_before_end) {
                    $slotEnd->subMinutes($hide_slot_x_min_before_end);
                }
            }
            catch (InvalidArgumentException $e) {
                return false;
            }
            if ($now->greaterThanOrEqualTo($slotEnd)) {
                return false;
            }
            return true;
        }

        try {
            $slotStart = Carbon::createFromFormat($this->date_format . ' ' . $this->time_format, $today_formatted . ' ' . $slot['start'], $this->tz);
            $slotStart->subMinutes(intval($this->minutes_to_prepare_order));
        }
        catch (InvalidArgumentException $e) {
            return false;
        }
        if ($now->greaterThanOrEqualTo($slotStart)) {
            return false;
        }
        return true;
    }

    public function is_slot_valid( $date, $day_of_week, $start, $end, $orders=0, $products=0 ) {
        $max_hour = false;
        if ( $this->scheduling_type == 'weekly' ) {
            $max_hour = $this->schedule->get_max_hour_by_weekday( $day_of_week );
        }
        else {
            $max_hour = $this->schedule->get_max_hour_by_date( $date );
        }
        if ($this->is_date_excluded( $date, $max_hour )) {
            return false;
        }
        if ( $this->scheduling_type == 'weekly' ) {
            $slots = $this->schedule->get_slots_by_weekday( $day_of_week );
        }
        else {
            $slots = $this->schedule->get_slots_by_date( $date );
        }
        $found_slot = false;
        foreach ($slots as $slot) {
            if( $slot['start'] == $start && $slot['end'] == $end ) {
                $found_slot = $slot;
                break;
            }
        }
        if (false === $found_slot) {
            // not in schedule
            return false;
        }
        $now = Carbon::now($this->tz);
        $today_formatted = $now->format($this->date_format);
        if ($date == $today_formatted) {
            if (!$this->is_today_slot_relevant($found_slot, $now, $today_formatted)) {
                return false;
            }
        }

        if ( isset($found_slot['data']) && isset($found_slot['data']['orders']) && !empty($found_slot['data']['orders']) ) {
            if ( $orders >= intval($found_slot['orders']) ) {
                return false;
            }
        }
        if ( isset($found_slot['data']) && isset($found_slot['data']['products']) && !empty($found_slot['data']['products']) ) {
            if ( $products >= intval($found_slot['products']) ) {
                return false;
            }
        }
        return true;
    }

    protected function is_slot_rules_fit( $date, $start, $end, $max_orders=0, $max_products=0 ) {

        if (empty( $max_orders ) && empty( $max_products )) {
            return true;
        }

        $slot_products = 0;
        $slot_orders = 0;

        $orders = get_posts( array(
            'numberposts' => - 1,
            'post_type'   => 'shop_order', // WC orders post type
            'fields'      => 'ids',
            'meta_query'  => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ocws_shipping_info_date',
                    'value'   => $date,
                    'compare' => '=',
                ),
                array(
                    'key'     => 'ocws_shipping_info_slot_start',
                    'value'   => $start,
                    'compare' => '=',
                ),
                array(
                    'key'     => 'ocws_shipping_info_slot_end',
                    'value'   => $end,
                    'compare' => '=',
                ),
            ),
        ) );

        if ($orders) {
            $slot_orders = count($orders);

            foreach ( $orders as $ord ) {
                $order    = wc_get_order( $ord );
                $items    = $order->get_items();

                $slot_products += count($items);
            }
        }

        $passed_max_orders = false;
        $passed_max_products = false;

        if (!empty( $max_orders )) {
            if ($slot_orders < $max_orders) {
                $passed_max_orders = true;
            }
        }
        else {
            $passed_max_orders = true;
        }

        if (!empty( $max_products )) {
            if ($slot_products < $max_products) {
                $passed_max_products = true;
            }
        }
        else {
            $passed_max_products = true;
        }

        return ($passed_max_orders && $passed_max_products);

    }

    protected function is_date_excluded( $formatted_date, $max_hour=false, $days_before=false ) {

        //error_log('Is date excluded: '.$formatted_date.' , closing dates: '.implode(', ', $this->closing_dates));
        if (in_array( $formatted_date, $this->closing_dates )) {
            return true;
        }
        $dt = Carbon::createFromFormat($this->date_format, $formatted_date, $this->tz);

        if (in_array( $dt->dayOfWeek, $this->closing_weekdays )) {
            return true;
        }
        $now = Carbon::now($this->tz);

        if ($days_before) {

            if (false === $max_hour) {
                $max_hour = $this->max_hour_for_today;
            }

            $pDay = clone $dt;
            $pDay->subDays(intval($days_before));

            // get last date and time to order for $formatted_date
            $max = Carbon::createFromFormat(
                sprintf('%s %s', $this->date_format, $this->time_format),
                sprintf('%s %s', $pDay->format($this->date_format), $max_hour),
                $this->tz
            );

            if ($now->greaterThanOrEqualTo($max)) {
                return true;
            }
        }
        else {

            $today_formatted = $now->format($this->date_format);

            if ( $formatted_date == $today_formatted ) {
                // isn't too late?
                if (false === $max_hour) {
                    $max_hour = $this->max_hour_for_today;
                }
                if (!empty($max_hour)) {
                    $max = Carbon::createFromFormat($this->date_format . ' ' . $this->time_format, $today_formatted . ' ' . $max_hour, $this->tz);
                    if ($now->greaterThanOrEqualTo($max)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function is_date_in_active_week($date_str) {
        if ( $this->repeat_every == 1 || ( $this->repeat_every > 1 && empty( $this->repeat_start_date ) ) ) {
            // no starting date, consider delivery every week
            return true;
        }
        $repeat_start_date = Carbon::createFromFormat($this->date_format, $this->repeat_start_date, $this->tz);
        $date = Carbon::createFromFormat($this->date_format, $date_str, $this->tz);
        // find start of week
        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        $repeat_start_of_week = $repeat_start_date->startOfWeek();
        $date_start_of_week = $date->startOfWeek();
        $weeks = $date_start_of_week->diffInWeeks($repeat_start_of_week);
        return (fmod($weeks, $this->repeat_every)? false : true);
    }

}