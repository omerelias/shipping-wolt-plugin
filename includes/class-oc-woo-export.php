<?php

defined( 'ABSPATH' ) || exit;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class OC_Woo_Export {
    protected $wp_filesystem;
    protected $export_dir;
    protected $export_url;
    protected $file;
    protected $export_to_xlsx = true;
    protected $file_ext = 'xlsx';
    protected $spreadsheet;

    protected $show_summary = true;
    protected $show_time_slots = true;
    protected $show_order_items = true;
    protected $show_items_number = true;
    protected $show_order_notes = true;
    protected $show_shipping_method = true;
    protected $show_order_total = true;
    protected $show_order_completed_date = false;

    protected $show_is_b2b_enabled = false;

    protected $show_customer_email = false;

    protected $tz = '';

    public function __construct() {
        global $wp_filesystem;

        $this->tz = ocws_get_timezone();

        if ( empty( $wp_filesystem ) ) {
            require_once( ABSPATH . '/wp-admin/includes/file.php' );
            WP_Filesystem();
            global $wp_filesystem;
        }

        $this->wp_filesystem    = $wp_filesystem;
        $this->export_dir       = $this->export_location();
        $this->export_url       = $this->export_location( true );

        if (get_option('ocws_common_export_hide_summary', '') === '1') {
            $this->show_summary = false;
        }
        if (get_option('ocws_common_export_hide_time_slots', '') === '1') {
            $this->show_time_slots = false;
        }
        if (get_option('ocws_common_export_hide_order_items', '') === '1') {
            $this->show_order_items = false;
        }
        if (get_option('ocws_common_export_hide_items_number', '') === '1') {
            $this->show_items_number = false;
        }
        if (get_option('ocws_common_export_hide_order_notes', '') === '1') {
            $this->show_order_notes = false;
        }
        if (get_option('ocws_common_export_hide_shipping_method', '') === '1') {
            $this->show_shipping_method = false;
        }
        if (get_option('ocws_common_export_hide_order_total', '') === '1') {
            $this->show_order_total = false;
        }
        if (get_option('ocws_common_export_show_order_completed_date', '') === '1') {
            $this->show_order_completed_date = true;
        }
        if (get_option('ocws_common_export_show_b2b', '') === '1') {
            $this->show_is_b2b_enabled = true;
        }
        if (get_option('ocws_common_export_show_customer_email', '') === '1') {
            $this->show_customer_email = true;
        }
     }

    public function export_location( $relative = false ) {
        $upload_dir         = wp_upload_dir();
        $export_location    = $relative ? trailingslashit( $upload_dir['baseurl'] ) . 'cache' : trailingslashit( $upload_dir['basedir'] ) . 'cache';

        return trailingslashit( $export_location );
    }

    public function process_shipping_data_export(){

        if ( ( $error = $this->check_export_location() ) !== true ) {
            wp_send_json_error( __( 'Filesystem ERROR: ' . $error, 'userswp' ) );
            wp_die();
        }

        $response = array(
            'success' => false,
            'msg' => ''
        );

        $this->set_export_params();

        $return = $this->process_export();

        if ( $return ) {

            $response['success']    = true;

            $new_filename   = 'ocws-shipping-data-export-' . date( 'y-m-d-H-i' ) . '.' . $this->file_ext;
            $new_file       = $this->export_dir . $new_filename;

            if ( file_exists( $this->file ) ) {
                $this->wp_filesystem->move( $this->file, $new_file, true );
            }

            if ( file_exists( $new_file ) ) {
                $response['data']['file'] = array( 'u' => $this->export_url . $new_filename, 's' => size_format( filesize( $new_file ), 2 ) );
            }
        } else {
            $response['msg']    = __( 'No data found for export.', 'ocws' );
        }

        wp_send_json( $response );

    }

    public function set_export_params() {
        $this->empty    = false;
        $this->filename = 'ocws-shipping-data-export-temp.' . $this->file_ext;
        $this->file     = $this->export_dir . $this->filename;
    }

    public function check_export_location() {
        try {
            if ( empty( $this->wp_filesystem ) ) {
                return __( 'Filesystem ERROR: Could not access filesystem.', 'userswp' );
            }

            if ( is_wp_error( $this->wp_filesystem ) ) {
                return __( 'Filesystem ERROR: ' . $this->wp_filesystem->get_error_message(), 'userswp' );
            }

            $is_dir         = $this->wp_filesystem->is_dir( $this->export_dir );
            $is_writeable   = $is_dir && is_writeable( $this->export_dir );

            if ( $is_dir && $is_writeable ) {
                return true;
            } else if ( $is_dir && !$is_writeable ) {
                if ( !$this->wp_filesystem->chmod( $this->export_dir, FS_CHMOD_DIR ) ) {
                    return wp_sprintf( __( 'Filesystem ERROR: Export location %s is not writable, check your file permissions.', 'userswp' ), $this->export_dir );
                }

                return true;
            } else {
                if ( !$this->wp_filesystem->mkdir( $this->export_dir, FS_CHMOD_DIR ) ) {
                    return wp_sprintf( __( 'Filesystem ERROR: Could not create directory %s. This is usually due to inconsistent file permissions.', 'userswp' ), $this->export_dir );
                }

                return true;
            }
        } catch ( Exception $e ) {
            return $e->getMessage();
        }
    }

    public function process_export() {

        if ($this->file) {
            @unlink( $this->file );
        }

        if ($this->export_to_xlsx) {

            $current_user = wp_get_current_user();
            $current_user_name = ($current_user? $current_user->first_name . ' ' . $current_user->last_name : '');
            $this->spreadsheet = new Spreadsheet();
            $this->spreadsheet->getActiveSheet()->setRightToLeft(true);
            $this->spreadsheet->getProperties()
                ->setCreator($current_user_name)
                ->setLastModifiedBy($current_user_name)
                ->setTitle(__('Summary for production', 'ocws'))
                ->setSubject(__('Summary for production', 'ocws'));

            $this->print_columns_to_spreadsheet();
            $return = $this->print_rows_to_spreadsheet();
        }

        else {
            $this->print_columns();
            $return = $this->print_rows();
        }

        if ( $return ) {
            return true;
        } else {
            return false;
        }
    }

    public function print_columns() {
        $column_data = '';
        $columns = $this->get_columns();
        $titles = $this->get_columns_titles();
        $i = 1;
        foreach( $columns as $key => $column ) {
            $column_data .= '"' . addslashes( isset($titles[$column])? $titles[$column] : $column ) . '"';
            $column_data .= $i == count( $columns ) ? '' : ',';
            $i++;
        }
        $column_data .= "\r\n";

        $this->attach_export_data( $column_data );

        return $column_data;
    }

    public function print_columns_to_spreadsheet() {
        $column_data = '';
        $columns = $this->get_columns();
        $titles = $this->get_columns_titles();
        $alphas = range('A', 'Z');
        $first = 'A1';
        $last = '';
        $i = 0;
        foreach( $columns as $key => $column ) {

            if ($i < count($alphas)) {
                $column_data = (isset($titles[$column]) ? $titles[$column] : $column);
                $this->spreadsheet->getActiveSheet()->setCellValue($alphas[$i] . '1', $column_data);
                $last = $alphas[$i] . '1';
                $i++;
            }
        }
        if ($first && $last) {
            $this->spreadsheet->getActiveSheet()
                ->getStyle($first . ':' . $last)
                ->applyFromArray(
                    [
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => '363636'],
                            ],
                        'borders' => [
                            'bottom' => ['borderStyle' => Border::BORDER_THIN]
                        ],
                        'font'  => [
                            'bold'  => true,
                            'color' => array('rgb' => 'FFFFFF'),
                        ]
                    ]
                );
        }
    }

    public function get_columns_titles() {

        $titles = array(
            'summary' => __('Summary column', 'ocws'),
            'group_name' => __('Group name column', 'ocws'),
            'shipping_date' => __('Shipping date column', 'ocws'),
            'shipping_method' => __('Shipping method column', 'ocws'),
            'order_total' => __('Order total column', 'ocws'),
            'time_slot' => __('Time slot column', 'ocws'),
            'order_number' => __('Order number column', 'ocws'),
            'order_items' => __('Order items column', 'ocws'),
            'items_quantity' => __('Items quantity column', 'ocws'),
            'customer_firstname' => __('Customer firstname column', 'ocws'),
            'customer_lastname' => __('Customer lastname column', 'ocws'),
            'customer_phone' => __('Customer phone column', 'ocws'),
            'city' => __('City column', 'ocws'),
            'street' => __('Street column', 'ocws'),
            'house' => __('House number column', 'ocws'),
            'flat' => __('Flat number column', 'ocws'),
            'floor' => __('Floor column', 'ocws'),
            'enter_code' => __('Enter code column', 'ocws'),
            'notes' => __('Order notes column', 'ocws'),
            'shipping_company' => __('Shipping company column', 'ocws'),
            'courier_name' => __('Courier name column', 'ocws'),
            'order_date' => __('Order creation date column', 'ocws'),
            'order_completed_date' => __('Order completion date column', 'ocws'),
            'b2b' => __('B2B column', 'ocws')
        );

        return $titles;
    }

    public function get_columns() {

        $columns = array();

        $default_columns = array(
            'summary',
            'order_number',
            'order_date',
            'shipping_date',
            'shipping_method',
            'order_total',
            'time_slot',
            'group_name',
            'order_items',
            'items_quantity',
            'customer_firstname',
            'customer_lastname',
            'customer_phone',
            'city',
            'street',
            'house',
            'flat',
            'floor',
            'enter_code',
            'notes',
            'order_completed_date',
            'b2b'
        );

        foreach ($default_columns as $c) {
            if (
                $c == 'summary' && !$this->show_summary ||
                $c == 'time_slot' && !$this->show_time_slots ||
                $c == 'order_items' && !$this->show_order_items ||
                $c == 'items_quantity' && !$this->show_items_number ||
                $c == 'notes' && !$this->show_order_notes ||
                $c == 'shipping_method' && !$this->show_shipping_method ||
                $c == 'order_total' && !$this->show_order_total ||
                $c == 'order_completed_date' && !$this->show_order_completed_date ||
                $c == 'b2b' && !$this->show_is_b2b_enabled
            ) {
                continue;
            }
            $columns[] = $c;
        }


        if (OC_WOO_USE_COMPANIES) {
            $columns = array_merge( array('shipping_company', 'courier_name'), $columns );
        }

        return $columns;

    }

    public function get_rows() {

        error_log('export - inside get_rows()');

        $type     = $_POST['type'];
        if (!in_array($type, array('today', 'from_to'))) {
            wp_send_json_error( 'wrong parameter' );
            wp_die();
        }

        $from = $to = '';
        $from_sortable = $to_sortable = '';

        if ($type == 'from_to') {
            if (isset($_POST['from']) && !empty($_POST['from'])) {
                try {
                    $dt = Carbon::createFromFormat('d/m/Y', $_POST['from'], $this->tz);
                    $from = $dt->format('d/m/Y');
                    $from_sortable = $dt->format('Y/m/d');
                } catch (InvalidArgumentException $e) {
                }
            }
            if (isset($_POST['to']) && !empty($_POST['to'])) {
                try {
                    $dt = Carbon::createFromFormat('d/m/Y', $_POST['to'], $this->tz);
                    $to = $dt->format('d/m/Y');
                    $to_sortable = $dt->format('Y/m/d');
                } catch (InvalidArgumentException $e) {
                }
            }
            if (!$from || !$to) {
                wp_send_json_error( 'wrong parameters' );
                wp_die();
            }
        }

        $selected_statuses = get_option('ocws_common_export_order_statuses', false);
        if (!$selected_statuses || !is_array($selected_statuses)) {
            $selected_statuses = array('wc-processing');
        }

        $args = array(
            'posts_per_page'    => -1,
            'return'   => 'ids',
            'post_status' => $selected_statuses,
            'post_type' => 'shop_order',
            'orderby'          => array('date_sortable_clause' => 'ASC', 'slot_start_clause' => 'ASC')
        );

        $main_meta = array();

        $posted_method = (isset($_POST['method'])? strtolower(trim($_POST['method'])) : 'all');
        if ($posted_method != 'all') {

            $main_meta[] = array(
                'key'     => 'ocws_shipping_tag',
                'value'   => ($posted_method == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID? OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG : OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG),
                'compare' => '='
            );
        }

        $main_meta[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'ocws_shipping_info_slot_start',
                'compare' => 'NOT EXISTS'
            ),
            'slot_start_clause' => array(
                'key'     => 'ocws_shipping_info_slot_start',
                'compare' => 'EXISTS'
            )
        );

        $main_meta[] = array(
            'key'     => 'ocws_shipping_info_date',
            'compare' => 'EXISTS'
        );

        $main_meta['date_sortable_clause'] = array(
            'key'     => 'ocws_shipping_info_date_sortable',
            'compare' => 'EXISTS'
        );

        if ($type == 'today') {
            $today_date = Carbon::now($this->tz);
            $date_to_compare = $today_date->format('d/m/Y');

            $main_meta[] = array(
                'key'     => 'ocws_shipping_info_date',
                'value'   => $date_to_compare,
                'compare' => '='
            );
        }
        else if ($type == 'from_to') {

            if ($from) {
                $main_meta[] = array(
                    'key'     => 'ocws_shipping_info_date_sortable',
                    'value'   => $from_sortable,
                    'compare' => '>='
                );
            }
            if ($to) {
                $main_meta[] = array(
                    'key'     => 'ocws_shipping_info_date_sortable',
                    'value'   => $to_sortable,
                    'compare' => '<='
                );
            }
        }

        $args['meta_query'] = $main_meta;

        error_log('export orders query arguments:');
        error_log(print_r($main_meta, 1));

        $orders = get_posts($args);

        global $wpdb;
        error_log($wpdb->last_query);

        $report = array();

        $groups = OC_Woo_Shipping_Groups::get_groups_assoc();

        foreach ($orders as $ord) {
            error_log('exporting order: '.$ord->ID);
        }

        foreach ($orders as $ord) {
            $order = wc_get_order( $ord->ID );
            if (!$order) continue;
            $shipping_date = get_post_meta($order->get_id(), 'ocws_shipping_info_date', true);
            if (!isset($report[$shipping_date])) {
                $report[$shipping_date] = array();
            }
            $shipping_tag = get_post_meta($order->get_id(), 'ocws_shipping_tag', true);
            $city = 0;
            $city_name = '';
            $group_id = 0;
            $group_name = '';
            $group_identifier = '';
            $order_total = $order->get_total();
            $order_completed_date = get_post_meta($order->get_id(), '_completed_date', true);
            $order_date = $order->get_date_created();
            $customer_id = $order->get_customer_id();
            $b2b_group_name = '-';
            if ($customer_id) {
                $b2b_group_name = ocws_b2bking_get_customer_group($customer_id);
            }

            if ($shipping_tag === OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG) {
                // pickup method
                $group_id = $city = get_post_meta( $order->get_id(), 'ocws_lp_pickup_aff_id', true );
                $group_name = $city_name = get_post_meta( $order->get_id(), 'ocws_lp_pickup_aff_name', true );
                if ($group_id) {
                    $group_identifier = $group_id . '_pickup';
                }
            }
            else {
                // advanced shipping
                $city = get_post_meta($order->get_id(), '_billing_city', true);
                $city_name = ocws_get_city_title($city);
                if ($city) {
                    $group_id = ocws_get_group_id_by_city($city);
                }
                if ($group_id && isset($groups[$group_id]) && !empty($groups[$group_id])) {
                    $group_name = $groups[$group_id];
                    $group_identifier = $group_id . '_shipping';
                }
            }

            if ($city) {

                if ($group_id && $group_identifier) {
                    if (!isset($report[$shipping_date][$group_identifier])) {
                        $report[$shipping_date][$group_identifier] = array();
                    }
                    if (!isset($report[$shipping_date][$group_identifier][$city])) {
                        $report[$shipping_date][$group_identifier][$city] = array();
                    }
                    if (!isset($report[$shipping_date][$group_identifier][$city]['order_list'])) {
                        $report[$shipping_date][$group_identifier][$city]['order_list'] = array();
                    }
                    if (!isset($report[$shipping_date][$group_identifier][$city]['city_name'])) {
                        $report[$shipping_date][$group_identifier][$city]['city_name'] = $city_name;
                    }
                    if (!isset($report[$shipping_date][$group_identifier][$city]['summary'])) {
                        $report[$shipping_date][$group_identifier][$city]['summary'] = array(
                            'total_packages' => 0,
                            'packages_by_time_slots' => array()
                        );
                    }
                    $order_entry = array();
                    $order_entry['group_name'] = $group_name;
                    $order_entry['shipping_date'] = $shipping_date;
                    $order_entry['shipping_method'] = $shipping_tag;
                    $order_entry['order_total'] = $order_total;
                    $order_entry['b2b'] = $b2b_group_name;
                    $order_entry['order_date'] = $order_date;
                    $order_entry['order_completed_date'] = $order_completed_date;
                    $order_entry['time_slot'] = get_post_meta($order->get_id(), 'ocws_shipping_info_slot_start', true);
                    $order_entry['time_slot'] .= ' - ' . get_post_meta($order->get_id(), 'ocws_shipping_info_slot_end', true);

                    if (!isset($report[$shipping_date][$group_identifier][$city]['summary']['packages_by_time_slots'][$order_entry['time_slot']])) {
                        $report[$shipping_date][$group_identifier][$city]['summary']['packages_by_time_slots'][$order_entry['time_slot']] = array(
                            'total_packages' => 0,
                            'total_items' => 0,
                            'items' => array()
                        );
                    }

                    $report[$shipping_date][$group_identifier][$city]['summary']['packages_by_time_slots'][$order_entry['time_slot']]['total_packages'] += 1;

                    $order_entry['order_number'] = $order->get_id();
                    $order_entry['order_items'] = array();
                    $order_entry['items_quantity'] = 0; //count( $order->get_items() );

                    //if ( $order_entry['items_quantity'] > 0 ) {
                        foreach ( $order->get_items() as $item ) {

                            $product = $item->get_product();
                            $product_name = '';
                            $weighable = false;
                            $units_ordered = 0;

                            if ($product) {
                                if (function_exists('ocwsu_is_item_weighable')) {
                                    $weighable = ocwsu_is_item_weighable($item);
                                }
                                if ( $product instanceof WC_Product_Variation ) {
                                    $attributes = $product->get_variation_attributes();
                                    $variation_name = urldecode(implode( ' - ', $attributes));
                                    $parent = wc_get_product($product->get_parent_id());
                                    if ($parent) {
                                        $product_name = $parent->get_name();
                                    }
                                    $product_name .= ' : ' . $variation_name;
                                }
                                else {
                                    $product_name = $product->get_name();
                                }
                            }

                            if (empty($product_name)) {
                                $product_name = $item->get_name();
                            }



                            $items_text = '';

                            if (function_exists('ocwsu_order_item_quantity_summary')) {
                                $item_qty_data = ocwsu_order_item_quantity_summary($item);
                                if (!$item_qty_data['weighable']) {
                                    $items_text .= $item_qty_data['units'] . ' ' . __('units', 'ocwsu');
                                    $units_ordered = $item_qty_data['units'];
                                }
                                else if ($item_qty_data['units']) {
                                    $items_text .= $item_qty_data['units'] . ' ' . __('units', 'ocwsu');
                                    if ($item_qty_data['unit_weight']) {
                                        $items_text .= ' (' . $item_qty_data['unit_weight'] . ' ' . $item_qty_data['unit_weight_label'] . ')';
                                    }
                                    $units_ordered = $item_qty_data['units'];
                                }
                                else {
                                    $items_text .= $item_qty_data['kg'] . ' ' . __('kg', 'ocwsu');
                                }
                            }
                            else {
                                $items_text .= $item->get_quantity();
                            }

                            $items_text .= ' X ';
                            $items_text .= $product_name;
                            if (!$product) {
                                $items_text .= '(' . __('The product was removed', 'ocws') . ')';
                            }
                            $items_text .= "\n";

                            /*$items_text .= strip_tags( wc_display_item_meta( $item, array(
                                'before'    => " - ",
                                'separator' => " - ",
                                'after'     => " - ",
                                'echo'      => false,
                                'autop'     => false,
                            ) ) );*/

                            $order_entry['order_items'][] = array(
                                'text' => $items_text,
                                'product_id' => $item->get_product_id(),
                                'variation_id' => $item->get_variation_id()
                            );

                            $order_entry['items_quantity'] += ($units_ordered? $units_ordered : 1);

                            $summary_items_key = $item->get_product_id() . '_' . $item->get_variation_id();

                            if (!isset( $report[$shipping_date][$group_identifier][$city]['summary']['packages_by_time_slots'][$order_entry['time_slot']]['items'][$summary_items_key] )) {
                                $report[$shipping_date][$group_identifier][$city]['summary']['packages_by_time_slots'][$order_entry['time_slot']]['items'][$summary_items_key] = array(
                                    'name' => $product_name,
                                    'quantity' => 0,
                                    'unit' => ($weighable? __('kg', 'ocwsu') : __('units', 'ocwsu'))
                                );
                            }
                            $report[$shipping_date][$group_identifier][$city]['summary']['packages_by_time_slots'][$order_entry['time_slot']]['items'][$summary_items_key]['quantity'] += $item->get_quantity();
                            $report[$shipping_date][$group_identifier][$city]['summary']['packages_by_time_slots'][$order_entry['time_slot']]['total_items'] += ($units_ordered? $units_ordered : 1);
                        }
                    //}

                    $order_entry['customer_firstname'] = $order->get_billing_first_name();
                    $order_entry['customer_lastname'] = $order->get_billing_last_name();
                    $order_entry['customer_phone'] = $order->get_billing_phone();

                    $alt_phone = get_post_meta( $order->get_id() , "_billing_alt_phone", true );

                    if ($alt_phone) {
                        $order_entry['customer_phone'] .= (!empty($order_entry['customer_phone'])? ', ' : '') . $alt_phone;
                    }

                    if ($shipping_tag === OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG) {
                        $order_entry['city'] = '';
                        $order_entry['street'] = '';
                        $order_entry['house'] = '';
                        $order_entry['flat'] = '';
                        $order_entry['floor'] = '';
                        $order_entry['enter_code'] = '';
                    }
                    else {
                        $order_entry['city'] = ocws_get_city_title($order->get_billing_city());
                        $order_entry['street'] = $order->get_billing_address_1();
                        $order_entry['house'] = get_post_meta( $order->get_id() , "_billing_house_num", true );
                        $order_entry['flat'] = get_post_meta( $order->get_id() , "_billing_apartment", true );;
                        $order_entry['floor'] = get_post_meta( $order->get_id() , "_billing_floor", true );
                        $order_entry['enter_code'] = get_post_meta( $order->get_id() , "_billing_enter_code", true );
                    }

                    $order_entry['notes'] = get_post_meta( $order->get_id() , "_billing_notes", true );

                    $report[$shipping_date][$group_identifier][$city]['order_list'][] = $order_entry;

                    $report[$shipping_date][$group_identifier][$city]['summary']['total_packages'] += 1;
                }

            }
        }

        $rows = array();

        error_log('found '.count($report).' shipping dates');
        error_log(print_r($report, 1));

        foreach ($report as $shipping_date => $shipping_date_data) {
            foreach ($shipping_date_data as $group_id => $group_data) {
                foreach ($group_data as $city => $city_data) {
                    foreach ($city_data['order_list'] as $order_entry) {

                        $items = array();
                        foreach ($order_entry['order_items'] as $item) {
                            $items[] = $item['text'];
                        }

                        $row = array();

                        $default_row = array(
                            'summary' => '',
                            'order_number' => $order_entry['order_number'],
                            'order_date' => $order_entry['order_date'],
                            'shipping_date' => $order_entry['shipping_date'],
                            'shipping_method' => $order_entry['shipping_method'],
                            'order_total' => $order_entry['order_total'],
                            'time_slot' => $order_entry['time_slot'],
                            'group_name' => $order_entry['group_name'],
                            'order_items' => implode("\n", $items),
                            'items_quantity' => $order_entry['items_quantity'],
                            'customer_firstname' => $order_entry['customer_firstname'],
                            'customer_lastname' => $order_entry['customer_lastname'],
                            'customer_phone' => $order_entry['customer_phone'],
                            'city' => $order_entry['city'],
                            'street' => $order_entry['street'],
                            'house' => $order_entry['house'],
                            'flat' => $order_entry['flat'],
                            'floor' => $order_entry['floor'],
                            'enter_code' => $order_entry['enter_code'],
                            'notes' => $order_entry['notes'],
                            'order_completed_date' => $order_entry['order_completed_date'],
                            'b2b' => $order_entry['b2b']
                        );

                        foreach ($default_row as $c => $value) {
                            if (
                                $c == 'summary' && !$this->show_summary ||
                                $c == 'time_slot' && !$this->show_time_slots ||
                                $c == 'order_items' && !$this->show_order_items ||
                                $c == 'items_quantity' && !$this->show_items_number ||
                                $c == 'notes' && !$this->show_order_notes ||
                                $c == 'shipping_method' && !$this->show_shipping_method ||
                                $c == 'order_total' && !$this->show_order_total ||
                                $c == 'order_completed_date' && !$this->show_order_completed_date ||
                                $c == 'b2b' && !$this->show_is_b2b_enabled
                            ) {
                                continue;
                            }
                            $row[$c] = $value;
                        }

                        if (OC_WOO_USE_COMPANIES) {
                            $row = array_merge(
                                array(
                                    'shipping_company' => get_post_meta($order_entry['order_number'], '_ocws_shipping_company_name', true),
                                    'courier_name' => ''
                                ),
                                $row );
                        }

                        $rows[] = $row;
                    }
                    $rows[] = $this->get_separator_row();

                    if ($this->show_summary) {

                        if (count($city_data['summary']['packages_by_time_slots']) == 0) {
                            $row = array(
                                'summary' => __('summary', 'ocws') . ' ' . $city_data['city_name'],
                                'order_number' => '',
                                'order_date' => '',
                                'shipping_date' => $city_data['summary']['total_packages'] . ' ' . __('packages', 'ocws'),
                                'shipping_method' => '',
                                'order_total' => '',
                                'time_slot' => '',
                                'group_name' => '',
                                'order_items' => '',
                                'items_quantity' => '',
                                'customer_firstname' => '',
                                'customer_lastname' => '',
                                'customer_phone' => '',
                                'city' => '',
                                'street' => '',
                                'house' => '',
                                'flat' => '',
                                'floor' => '',
                                'enter_code' => '',
                                'notes' => '',
                                'order_completed_date' => '',
                                'b2b'
                            );

                            if (OC_WOO_USE_COMPANIES) {
                                $row = array_merge(
                                    array(
                                        'shipping_company' => '',
                                        'courier_name' => ''
                                    ),
                                    $row);
                            }

                            $frow = array();
                            foreach ($row as $c => $value) {
                                if (
                                    $c == 'summary' && !$this->show_summary ||
                                    $c == 'time_slot' && !$this->show_time_slots ||
                                    $c == 'order_items' && !$this->show_order_items ||
                                    $c == 'items_quantity' && !$this->show_items_number ||
                                    $c == 'notes' && !$this->show_order_notes ||
                                    $c == 'shipping_method' && !$this->show_shipping_method ||
                                    $c == 'order_total' && !$this->show_order_total ||
                                    $c == 'order_completed_date' && !$this->show_order_completed_date ||
                                    $c == 'b2b' && !$this->show_is_b2b_enabled
                                ) {
                                    continue;
                                }
                                $frow[$c] = $value;
                            }

                            $rows[] = $frow;
                        }
                        else {
                            $first = true;
                            foreach ($city_data['summary']['packages_by_time_slots'] as $slot => $slot_data) {

                                $items = array();
                                foreach ($slot_data['items'] as $key => $item_data) {
                                    $items[] = $item_data['quantity'] . ' ' . $item_data['unit'] . ' X ' . $item_data['name'];
                                }

                                $slot_row = array(
                                    'summary' => $first? __('summary', 'ocws') . ' ' . $city_data['city_name'] : '',
                                    'order_number' => '',
                                    'order_date' => '',
                                    'shipping_date' => ($first? $city_data['summary']['total_packages'] . ' ' . __('packages', 'ocws') : ''),
                                    'shipping_method' => '',
                                    'order_total' => '',
                                    'time_slot' => $slot . "\n" . $slot_data['total_packages'] . ' ' . __('packages', 'ocws'),
                                    'group_name' => '',
                                    'order_items' => implode("\n", $items),
                                    'items_quantity' => $slot_data['total_items'],
                                    'customer_firstname' => '',
                                    'customer_lastname' => '',
                                    'customer_phone' => '',
                                    'city' => '',
                                    'street' => '',
                                    'house' => '',
                                    'flat' => '',
                                    'floor' => '',
                                    'enter_code' => '',
                                    'notes' => '',
                                    'order_completed_date' => '',
                                    'b2b' => ''
                                );
                                $first = false;

                                if (OC_WOO_USE_COMPANIES) {
                                    $slot_row = array_merge(
                                        array(
                                            'shipping_company' => '',
                                            'courier_name' => ''
                                        ),
                                        $slot_row);
                                }

                                $fslotrow = array();
                                foreach ($slot_row as $c => $value) {
                                    if (
                                        $c == 'summary' && !$this->show_summary ||
                                        $c == 'time_slot' && !$this->show_time_slots ||
                                        $c == 'order_items' && !$this->show_order_items ||
                                        $c == 'items_quantity' && !$this->show_items_number ||
                                        $c == 'notes' && !$this->show_order_notes ||
                                        $c == 'shipping_method' && !$this->show_shipping_method ||
                                        $c == 'order_total' && !$this->show_order_total ||
                                        $c == 'order_completed_date' && !$this->show_order_completed_date ||
                                        $c == 'b2b' && !$this->show_is_b2b_enabled
                                    ) {
                                        continue;
                                    }
                                    $fslotrow[$c] = $value;
                                }

                                $rows[] = $fslotrow;
                            }
                        }
                        $rows[] = $this->get_separator_row();

                    }
                }
            }
        }

        error_log('exported '.count($rows).' rows');

        return $rows;
    }

    public function get_separator_row() {
        $row = array();

        $first = true;
        foreach ($this->get_columns() as $column) {
            if ($first) {
                $row[$column] = '---------------';
                $first = false;
            }
        }

        return $row;
    }

    public function print_rows() {
        $row_data   = '';
        $data = $this->get_rows();
        $columns    = $this->get_columns();

        if ( $data ) {
            foreach ( $data as $row ) {
                $i = 1;
                foreach ( $row as $key => $column ) {
                    $row_data .= '"' . addslashes( preg_replace( "/\"/","'", $column ) ) . '"';
                    $row_data .= $i == count( $columns ) ? '' : ',';
                    $i++;
                }
                $row_data .= "\r\n";
            }

            $this->attach_export_data( $row_data );

            return $row_data;
        }

        return false;
    }

    public function print_rows_to_spreadsheet() {

        $data = $this->get_rows();
        $alphas = range('A', 'Z');

        if ( $data ) {
            foreach ( $data as $row_index => $row ) {
                $i = 0;
                foreach ( $row as $key => $column ) {

                    if ($i < count($alphas)) {
                        $this->spreadsheet->getActiveSheet()->setCellValue($alphas[$i] . ($row_index + 2), $column);
                        $i++;
                    }
                }
            }
        }

        error_log('print_rows_to_spreadsheet');

        return $this->write_spreadsheet();
    }

    protected function get_export_file() {
        $file = '';

        if ( $this->wp_filesystem->exists( $this->file ) ) {
            $file = $this->wp_filesystem->get_contents( $this->file );
        } else {
            $this->wp_filesystem->put_contents( $this->file, '' );
        }

        return $file;
    }

    protected function attach_export_data( $data = '' ) {
        $filedata   = $this->get_export_file();
        $filedata   .= $data;

        $this->wp_filesystem->put_contents( $this->file, $filedata );

        $rows       = file( $this->file, FILE_SKIP_EMPTY_LINES );
        $columns    = $this->get_columns();
        $columns    = empty( $columns ) ? 0 : 1;

        $this->empty = count( $rows ) == $columns ? true : false;
    }

    protected function write_spreadsheet() {

        if ($this->file && $this->spreadsheet) {

            try {
                $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
                $writer->save($this->file);
                return true;
            }
            catch (PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
                error_log($e->getMessage());
                return false;
            }
        }
        return false;
    }

}
