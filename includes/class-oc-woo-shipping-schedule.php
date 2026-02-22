<?php

use Carbon\Carbon;

defined( 'ABSPATH' ) || exit;

class OC_Woo_Shipping_Schedule {

    private $scheduling_type = 'weekly';

    private $date_format = 'd/m/Y';

    private $time_format = 'G:i';

    private $tz = '';

    protected $days = array();

    protected $allowed_scheduling_types = array('weekly', 'dates');

    public function __construct() {
        $this->tz = ocws_get_timezone();
    }

    public function set_scheduling_type ($type) {
        if (in_array($type, $this->allowed_scheduling_types)) {
            $this->scheduling_type = $type;
        }
    }

    public function get_dates() {
        $dates = array();
        if ($this->scheduling_type == 'dates') {
            foreach ($this->days as $d) {
                $dates[] = $d['day'];
            }
        }
        return $dates;
    }

    public function get_slots_by_date( $formatted_date ) {

        $slots = array();
        foreach ($this->days as $d) {
            if ($d['day'] == $formatted_date) {
                return $d['periods'];
            }
        }
        return $slots;
    }

    public function get_max_hour_by_date( $formatted_date ) {

        $slots = array();
        foreach ($this->days as $d) {
            if ($d['day'] == $formatted_date) {
                return (isset($d['max_hour'])? $d['max_hour'] : false);
            }
        }
        return $slots;
    }

    public function get_filter_picker_by_weekday( $day_of_week ) {
        foreach ($this->days as $d) {
            if ($d['day'] == $day_of_week) {
                return isset($d['filter_picker']) ? $d['filter_picker'] : 'same_day';
            }
        }
        return 'same_day';
    }

    public function get_filter_picker_by_date( $formatted_date ) {
        foreach ($this->days as $d) {
            if ($d['day'] == $formatted_date) {
                return isset($d['filter_picker']) ? $d['filter_picker'] : 'same_day';
            }
        }
        return 'same_day';
    }

    public function get_slots_by_weekday( $day_of_week ) {

        $slots = array();
        foreach ($this->days as $d) {
            if ($d['day'] == $day_of_week) {
                return $d['periods'];
            }
        }
        return $slots;
    }

    public function get_max_hour_by_weekday( $day_of_week ) {

        $slots = array();
        foreach ($this->days as $d) {
            if ($d['day'] == $day_of_week) {
                return (isset($d['max_hour'])? $d['max_hour'] : false);
            }
        }
        return $slots;
    }

    public function set_days( $data, $source = 'db' ) {

        $imported_data = array();

        if (NULL === $data || !is_array($data)) return $imported_data;

        //error_log('OC_Woo_Shipping_Schedule::set_days');
        //error_log(print_r($data, true));

        foreach ($data as $day_data) {
            $d = array();
            if ( isset($day_data['day']) ) {
                if ( $this->is_valid_day($day_data['day']) ) {
                    $d['day'] = $day_data['day'];
                    if (isset($day_data['max_hour']) && $this->is_valid_datetime( $this->time_format, $day_data['max_hour'] )) {
                        $d['max_hour'] = $day_data['max_hour'];
                    }
                    else {
                        $d['max_hour'] = false;
                    }
                    if (isset($day_data['filter_picker'])) {
                        $d['filter_picker'] = $day_data['filter_picker'];
                    }
                    else {
                        $d['filter_picker'] = 'same_day';
                    }
                    $d['periods'] = array();
                    if (isset( $day_data['periods'] ) && is_array( $day_data['periods'] )) {
                        foreach ( $day_data['periods'] as $period ) {
                            if (
                                isset( $period['start'] ) && $this->is_valid_datetime( $this->time_format, $period['start'] ) &&
                                isset( $period['end'] ) && $this->is_valid_datetime( $this->time_format, $period['end'] )
                            ) {
                                if ($source == 'json') {
                                    $period_data = array(
                                        'orders' => '',
                                        'products' => '',
                                        'show_slot_after_start' => false,
                                        'hide_slot_x_min_before_end' => 0
                                    );
                                    if (isset( $period['data'] ) && is_array( $period['data']) ) {
                                        foreach ($period['data'] as $item) {
                                            if (isset($item['name']) && isset($item['value'])) {
                                                if ($item['name'] == 'products') {
                                                    $period_data['products'] = ($item['value']);
                                                }
                                                else if ($item['name'] == 'orders') {
                                                    $period_data['orders'] = ($item['value']);
                                                }
                                                else if ($item['name'] == 'show_slot_after_start') {
                                                    $period_data['show_slot_after_start'] = !!($item['value']);
                                                }
                                                else if ($item['name'] == 'hide_slot_x_min_before_end') {
                                                    $period_data['hide_slot_x_min_before_end'] = ($item['value']);
                                                }
                                            }
                                        }
                                    }
                                }
                                else {
                                    if (isset($period['data'])) {
                                        $period_data = array(
                                            'orders' => (isset($period['data']['orders'])? intval($period['data']['orders']) : ''),
                                            'products' => (isset($period['data']['products'])? intval($period['data']['products']) : ''),
                                            'show_slot_after_start' => (isset($period['data']['show_slot_after_start'])? !!($period['data']['show_slot_after_start']) : false),
                                            'hide_slot_x_min_before_end' => (isset($period['data']['hide_slot_x_min_before_end'])? intval($period['data']['hide_slot_x_min_before_end']) : 0),
                                        );
                                    }
                                    else {
                                        $period_data = array(
                                            'orders' => '',
                                            'products' => '',
                                            'show_slot_after_start' => false,
                                            'hide_slot_x_min_before_end' => 0);
                                    }
                                }

                                $d['periods'][] = array(
                                    'start' => $period['start'],
                                    'end' => $period['end'],
                                    'data' => $period_data,
                                    'raw_data' => $period['data']
                                );
                            }
                        }
                        uasort($d['periods'], function($period1, $period2) {
                            return ( $period1['start'] > $period2['start']? 1 : ($period1['start'] < $period2['start'] ? -1 : 0) );
                        });
                    }
                    $imported_data[] = $d;
                }
            }
        }
        //error_log('Imported:');
        //error_log(print_r($imported_data, true));
        $this->days = $imported_data;
        return $this->days;
    }

    public function import_from_json ($json) {

        $json = stripslashes($json);
        $data = json_decode( $json, true );

        return $this->set_days($data, 'json');
    }

    public static function export_to_json ($data) {

        $exported_data = array();

        foreach ($data as $day_data) {
            $d = array();
            if ( isset($day_data['day']) ) {

                $d['day'] = $day_data['day'];
                if (isset($day_data['max_hour'])) {
                    $d['max_hour'] = $day_data['max_hour'];
                }
                else {
                    $d['max_hour'] = false;
                }
                if (isset($day_data['filter_picker'])) {
                    $d['filter_picker'] = $day_data['filter_picker'];
                }
                else {
                    $d['filter_picker'] = 'same_day';
                }
                $d['periods'] = array();
                if (isset( $day_data['periods'] ) && is_array( $day_data['periods'] )) {
                    foreach ( $day_data['periods'] as $period ) {
                        if (
                            isset( $period['start'] ) && isset( $period['end'] )
                        ) {
                            $period_data = array();
                            if (isset( $period['data'] ) && is_array( $period['data']) ) {
                                foreach ($period['data'] as $key => $item) {
                                    $period_data[] = array(
                                        'name' => $key,
                                        'value' => $item
                                    );
                                }
                            }
                            $d['periods'][] = array(
                                'start' => $period['start'],
                                'end' => $period['end'],
                                'data' => $period_data
                            );
                        }
                    }
                }
                $exported_data[] = $d;
            }
        }
        return json_encode($exported_data);
    }

    protected function is_valid_day( $day, $not_allow_past_date=true ) {

        if ($this->scheduling_type == 'weekly') {
            if (is_numeric($day)) {
                $day = intval($day);
                if ($day >= 0 && $day <= 6) {
                    return true;
                }
            }
        }
        else if ($this->scheduling_type == 'dates') {
            try {
                $d = Carbon::createFromFormat($this->date_format, $day, $this->tz);
            }
            catch (InvalidArgumentException $e) {
                // not valid date
                return false;
            }
            if ($not_allow_past_date) {
                return !(Carbon::now()->startOfDay()->gte($d));
            }
            return true;
        }
        return false;
    }

    public function is_valid_datetime( $format, $datetime ) {
        if ( substr( $datetime, 0, 1 ) === '-' ) {
            return false;
        }

        //try {
            return DateTime::createFromFormat( $format, $datetime ) !== false;
        //} catch ( Exception $exception ) {
        //    error_log($exception->getMessage());
        //    return false;
        //}
    }

}