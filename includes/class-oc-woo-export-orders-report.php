<?php

defined( 'ABSPATH' ) || exit;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class OC_Woo_Export_Orders_Report extends OC_Woo_Export {

    protected $first_title_row;
    protected $second_title_row;
    protected $pages;

    public function __construct() {
        parent::__construct();
        $pages = array(__('Main', 'ocws'));
        $this->pages = array();
        foreach ($pages as $page) {
            $this->pages[] = $page;
        }
        $this->first_title_row = array();
        $this->second_title_row = array();
    }

    public function process_shipping_data_export(){

        if ( ( $error = $this->check_export_location() ) !== true ) {
            wp_send_json_error( __( 'Filesystem ERROR: ' . $error, 'userswp' ) );
            wp_die();
        }

        $this->set_export_params();

        $response = array(
            'success' => false,
            'msg' => ''
        );

        $return = $this->process_export();

        if ( $return ) {

            $response['success']    = true;
            $response['msg']        = '';

            $new_filename   = 'ocws-export-orders-report' . date( 'y-m-d-H-i' ) . '.' . $this->file_ext;
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
        $this->filename = 'ocws-export-orders-report-temp.' . $this->file_ext;
        $this->file     = $this->export_dir . $this->filename;
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
            'customer_email' => __('Customer email column', 'ocws'),
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
            'order_number',
            'order_date',
            'order_completed_date',
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
            'customer_email',
            'city',
            'street',
            'house',
            'flat',
            'floor',
            'enter_code',
            'notes',
            'b2b'
        );

        foreach ($default_columns as $c) {
            if (
                $c == 'time_slot' && !$this->show_time_slots ||
                $c == 'order_items' && !$this->show_order_items ||
                $c == 'items_quantity' && !$this->show_items_number ||
                $c == 'notes' && !$this->show_order_notes ||
                $c == 'shipping_method' && !$this->show_shipping_method ||
                $c == 'order_total' && !$this->show_order_total ||
                $c == 'order_completed_date' && !$this->show_order_completed_date ||
                $c == 'b2b' && !$this->show_is_b2b_enabled ||
                $c == 'customer_email' && !$this->show_customer_email
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

    protected function init_title_rows() {

        $this->first_title_row = array();
        $this->second_title_row = array();

        $columns = $this->get_columns();
        $titles = array(
            __('Start order date', 'ocws'),
            __('End order date', 'ocws'),
            __('Start completed date', 'ocws'),
            __('End completed date', 'ocws')
        );

        $index = 0;
        foreach ($columns as $column_name) {
            if ($index < count($titles)) {
                $this->first_title_row[$column_name] = $titles[$index];
                $this->second_title_row[$column_name] = '';
            }
            $index++;
        }
    }

    public function get_rows() {

        $this->init_title_rows();

        $from = $to = '';
        $cfrom = $cto = '';

        $titles = array_keys($this->second_title_row);
        $titles_index = 0;

        if (isset($_POST['order_date_from']) && !empty($_POST['order_date_from'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_POST['order_date_from'], $this->tz);
                $dt->hour(0);
                $dt->minute(0);
                $dt->second(0);
                $from = $dt->format('Y-m-d H:i:s');
                $this->second_title_row[$titles[$titles_index]] = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        $titles_index++;
        if (isset($_POST['order_date_to']) && !empty($_POST['order_date_to'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_POST['order_date_to'], $this->tz);
                $dt->hour(23);
                $dt->minute(59);
                $dt->second(59);
                $to = $dt->format('Y-m-d H:i:s');
                $this->second_title_row[$titles[$titles_index]] = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        $titles_index++;
        if (isset($_POST['order_completed_date_from']) && !empty($_POST['order_completed_date_from'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_POST['order_completed_date_from'], $this->tz);
                $dt->hour(0);
                $dt->minute(0);
                $dt->second(0);
                $cfrom = $dt->format('Y-m-d H:i:s');
                $this->second_title_row[$titles[$titles_index]] = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        $titles_index++;
        if (isset($_POST['order_completed_date_to']) && !empty($_POST['order_completed_date_to'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_POST['order_completed_date_to'], $this->tz);
                $dt->hour(23);
                $dt->minute(59);
                $dt->second(59);
                $cto = $dt->format('Y-m-d H:i:s');
                $this->second_title_row[$titles[$titles_index]] = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        if (!$from || !$to) {
            wp_send_json_error( 'wrong parameters' );
            wp_die();
        }

        $selected_statuses = get_option('ocws_common_export_sales_order_statuses', false);
        if (!$selected_statuses || !is_array($selected_statuses)) {
            $selected_statuses = array('wc-completed');
        }

        $args = array(
            'posts_per_page'    => -1,
            'return'   => 'ids',
            'post_status' => $selected_statuses,
            'post_type' => 'shop_order',
            'date_query' => array(
                'column' => 'post_date',
                'after' => $from,
                'before' => $to
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
            'suppress_filters' => true,
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

        if ($cfrom || $cto) {
            $main_meta['date_completed_clause'] = array(
                'key'     => '_completed_date',
                'compare' => 'EXISTS'
            );
        }
        if ($cfrom) {
            $main_meta[] = array(
                'key'     => '_completed_date',
                'value'   => $cfrom,
                'compare' => '>='
            );
        }
        if ($cto) {
            $main_meta[] = array(
                'key'     => '_completed_date',
                'value'   => $cto,
                'compare' => '<='
            );
        }

        $args['meta_query'] = $main_meta;
        $orders = get_posts($args);

        global $wpdb;
        error_log('the query: ---------------------------------------------------');
        //error_log($wpdb->last_query);
        error_log(count($orders));

        //return array();

        $report = array();
        $groups = OC_Woo_Shipping_Groups::get_groups_assoc();

        foreach ($orders as $ord) {
            $order = wc_get_order( $ord->ID );
            if (!$order) continue;
            $shipping_date = get_post_meta($order->get_id(), 'ocws_shipping_info_date', true);
            $shipping_tag = get_post_meta($order->get_id(), 'ocws_shipping_tag', true);
            $group_id = 0;
            $group_name = '';
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
            }
            else {
                // advanced shipping
                $city = get_post_meta($order->get_id(), '_billing_city', true);
                if ($city) {
                    $group_id = ocws_get_group_id_by_city($city);
                }
                if ($group_id && isset($groups[$group_id]) && !empty($groups[$group_id])) {
                    $group_name = $groups[$group_id];
                }
            }

            $order_entry = array();
            $order_entry['order_number'] = $order->get_id();
            $order_entry['order_items'] = array();
            $order_entry['items_quantity'] = 0;
            $order_entry['group_name'] = $group_name;
            $order_entry['shipping_date'] = $shipping_date;
            $order_entry['shipping_method'] = $shipping_tag;
            $order_entry['order_total'] = $order_total;
            $order_entry['b2b'] = $b2b_group_name;
            $order_entry['order_date'] = $order_date;
            $order_entry['order_completed_date'] = $order_completed_date;
            $order_entry['time_slot'] = get_post_meta($order->get_id(), 'ocws_shipping_info_slot_start', true);
            $order_entry['time_slot'] .= ' - ' . get_post_meta($order->get_id(), 'ocws_shipping_info_slot_end', true);

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

                $order_entry['order_items'][] = array(
                    'text' => $items_text,
                    'product_id' => $item->get_product_id(),
                    'variation_id' => $item->get_variation_id()
                );

                $order_entry['items_quantity'] += ($units_ordered? $units_ordered : 1);

            }

            $order_entry['customer_firstname'] = $order->get_billing_first_name();
            $order_entry['customer_lastname'] = $order->get_billing_last_name();
            $order_entry['customer_phone'] = $order->get_billing_phone();
            $order_entry['customer_email'] = $order->get_billing_email();

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
                $order_entry['flat'] = get_post_meta( $order->get_id() , "_billing_apartment", true );
                $order_entry['floor'] = get_post_meta( $order->get_id() , "_billing_floor", true );
                $order_entry['enter_code'] = get_post_meta( $order->get_id() , "_billing_enter_code", true );
            }

            if(!$order_entry['city'])
            {
                $order_entry['city'] = $order->get_billing_city();
            }
            $order_entry['notes'] = get_post_meta( $order->get_id() , "_billing_notes", true );

            $report[] = $order_entry;

        }

        $rows = array();

        foreach ($report as $order_entry) {

            $items = array();
            foreach ($order_entry['order_items'] as $item) {
                $items[] = $item['text'];
            }

            $row = array();

            $default_row = array(
                'order_number' => $order_entry['order_number'],
                'order_date' => $order_entry['order_date'],
                'order_completed_date' => $order_entry['order_completed_date'],
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
                'customer_email' => $order_entry['customer_email'],
                'city' => $order_entry['city'],
                'street' => $order_entry['street'],
                'house' => $order_entry['house'],
                'flat' => $order_entry['flat'],
                'floor' => $order_entry['floor'],
                'enter_code' => $order_entry['enter_code'],
                'notes' => $order_entry['notes'],
                'b2b' => $order_entry['b2b']
            );

            foreach ($default_row as $c => $value) {
                if (
                    $c == 'time_slot' && !$this->show_time_slots ||
                    $c == 'order_items' && !$this->show_order_items ||
                    $c == 'items_quantity' && !$this->show_items_number ||
                    $c == 'notes' && !$this->show_order_notes ||
                    $c == 'shipping_method' && !$this->show_shipping_method ||
                    $c == 'order_total' && !$this->show_order_total ||
                    $c == 'order_completed_date' && !$this->show_order_completed_date ||
                    $c == 'b2b' && !$this->show_is_b2b_enabled ||
                    $c == 'customer_email' && !$this->show_customer_email
                ) {
                    continue;
                }
                $row[$c] = $value;
            }

            $rows[] = $row;
        }

        //error_log('exported '.count($rows).' rows');

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

    public function process_export() {

        if ($this->file) {
            @unlink( $this->file );
        }

        if ($this->export_to_xlsx) {

            $current_user = wp_get_current_user();
            $current_user_name = ($current_user? $current_user->first_name . ' ' . $current_user->last_name : '');
            $this->spreadsheet = new Spreadsheet();

            $rows = $this->get_rows();

            $this->spreadsheet->getProperties()
                ->setCreator($current_user_name)
                ->setLastModifiedBy($current_user_name)
                ->setTitle(__('Sales report', 'ocws'))
                ->setSubject(__('Sales report', 'ocws'));

            // error_log(print_r($this->pages, 1));

            foreach ($this->pages as $ind => $title) {
                if ($ind > 0) {
                    $this->spreadsheet->createSheet();
                }
                $this->spreadsheet->setActiveSheetIndex($ind);
                $this->spreadsheet->getActiveSheet()->setRightToLeft(true);
                $this->spreadsheet->getActiveSheet()->setTitle($title);

                $this->print_columns_to_spreadsheet();
            }

            $return = $this->print_rows_to_spreadsheet_adv($rows);
        }

        else {
            return false;
        }

        if ( $return ) {
            return true;
        } else {
            return false;
        }
    }

    public function print_columns_to_spreadsheet() {
        $column_data = '';
        $columns = $this->get_columns();
        $titles = $this->get_columns_titles();
        $alphas = range('A', 'Z');
        $first_row_index = $this->print_title_rows_to_spreadsheet();
        $first = 'A'.$first_row_index;
        $last = '';
        $i = 0;
        foreach( $columns as $key => $column ) {

            if ($i < count($alphas)) {
                $column_data = (isset($titles[$column]) ? $titles[$column] : $column);
                $this->spreadsheet->getActiveSheet()->setCellValue($alphas[$i] . $first_row_index, $column_data);
                $last = $alphas[$i] . $first_row_index;
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

    public function print_title_rows_to_spreadsheet() {

        if (count($this->first_title_row) > 0) {

            $title_rows = array(
                $this->first_title_row,
                $this->second_title_row,
                //$this->get_separator_row()
            );

            $alphas = range('A', 'Z');
            $first = $last = '';
            $j = 1; // row index
            foreach( $title_rows as $title_row ) {
                $i = 0; // column index
                $first = $alphas[$i] . $j;
                foreach ($title_row as $column_code => $value) {
                    if ($i < count($alphas)) {
                        $this->spreadsheet->getActiveSheet()->setCellValue($alphas[$i] . $j, $value);
                        //error_log('set cell '. $alphas[$i] . $j . ' value ' . $value);
                        $last = $alphas[$i] . $j;
                        $i++;
                    }
                }
                if ($first && $last) {
                    $this->spreadsheet->getActiveSheet()
                        ->getStyle($first . ':' . $last)
                        ->applyFromArray(
                            [
                                'fill' => [
                                    'fillType' => ($j == 1? Fill::FILL_SOLID : Fill::FILL_NONE),
                                    'color' => ['rgb' => ($j == 1? '363636' : 'FFFFFF')],
                                ],
                                'borders' => [
                                    'bottom' => ['borderStyle' => Border::BORDER_THIN]
                                ],
                                'font'  => [
                                    'bold'  => ($j == 1),
                                    'color' => array('rgb' => ($j == 1? 'FFFFFF' : '363636')),
                                ]
                            ]
                        );
                }
                $j++;
            }
        }
        return count($title_rows) + 1;
    }

    public function print_rows_to_spreadsheet_adv($data) {

        $alphas = range('A', 'Z');
        $columns = $this->get_columns();

        $page_row_index = array();
        foreach ($this->pages as $k => $page) {
            $page_row_index[$k] = (count($this->first_title_row) > 0 ? 3 : 0);
        }
        $current_page = 0;

        if ( $data ) {
            foreach ( $data as $row_index => $row ) {
                $i = 0;

                if (isset($row['export_page']) && $row['export_page'] < count($this->pages) && $row['export_page'] >= 0) {
                    $current_page = $row['export_page'];
                    $this->spreadsheet->setActiveSheetIndex($row['export_page']);
                }
                else {
                    $current_page = 0;
                    $this->spreadsheet->setActiveSheetIndex(0);
                }
                foreach ( $columns as $column ) {

                    if ($i < count($alphas)) {
                        if (isset($row[$column]))
                            $this->spreadsheet->getActiveSheet()->setCellValue($alphas[$i] . ($page_row_index[$current_page] + 2), $row[$column]);
                        $i++;
                    }
                }
                $page_row_index[$current_page]++;
            }
        }

        // error_log('print_rows_to_spreadsheet_adv');

        return $this->write_spreadsheet();
    }

    public function print_rows_to_spreadsheet() {

        return false;
    }
}
