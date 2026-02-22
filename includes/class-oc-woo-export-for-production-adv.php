<?php

defined( 'ABSPATH' ) || exit;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class OC_Woo_Export_For_Production_Adv extends OC_Woo_Export {

    private $selected_attributes;
    protected $attributes_combinations_columns;
    protected $pages;
    protected $categories;

    public function __construct() {
        parent::__construct();
        $selected = get_option('ocws_common_export_production_attributes_to_show', false);
        if (!$selected || !is_array($selected)) {
            $selected = array();
        }
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes_assoc = array();
        if ( $attribute_taxonomies ) {

            // error_log('export for production:');
            // error_log('attribute taxonomies:');
            // error_log(print_r($attribute_taxonomies, 1));
            foreach ( $attribute_taxonomies as $tax ) {
                if (in_array( $tax->attribute_name, $selected )) {
                    $attributes_assoc[$tax->attribute_name] = $tax->attribute_label;
                }
            }
        }
        $this->selected_attributes = $attributes_assoc;
        $this->attributes_combinations_columns = array();

        $orderby = 'name';
        $order = 'asc';
        $hide_empty = false ;
        $cat_args = array(
            'orderby'    => $orderby,
            'order'      => $order,
            'hide_empty' => $hide_empty,
            'parent' => 0
        );

        $product_categories = get_terms( 'product_cat', $cat_args );

        $pages = get_option('ocws_common_export_production_details_pages', false);
        if (!$pages || !is_array($pages)) {
            $pages = array(__('Main', 'ocws'));
        }
        $this->pages = array();
        foreach ($pages as $page) {
            $this->pages[] = $page;
        }

        foreach ($product_categories as $category) {
            $export_page = get_term_meta($category->term_id, 'ocws_export_page', true);
            if (!$export_page) {
                $this->categories[$category->term_id] = 0;
            }
            else {
                if (isset($this->pages[intval($export_page)])) {
                    $this->categories[$category->term_id] = intval($export_page);
                }
                else {
                    $this->categories[$category->term_id] = 0;
                }
            }
        }
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

            $new_filename   = 'ocws-shipping-data-export-for-production-' . date( 'y-m-d-H-i' ) . '.' . $this->file_ext;
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
        $this->filename = 'ocws-shipping-data-export-for-production-temp.' . $this->file_ext;
        $this->file     = $this->export_dir . $this->filename;
    }


    public function get_columns_titles() {

        $titles = array(
            'shipping_date' => __('Shipping date column', 'ocws'),
            'product_name' => __('Product name column', 'ocws'),
            'quantity' => __('Quantity column', 'ocws'),
            'notes' => __('Notes column', 'ocws'),
        );

        foreach ($this->attributes_combinations_columns as $key => $title) {
            $titles[$key] = $title;
        }

        return $titles;
    }

    public function get_columns() {

        $columns = array(
            'shipping_date',
            'product_name',
        );

        $columns[] = 'quantity';

        foreach ($this->attributes_combinations_columns as $key => $t) {
            $columns[] = $key;
        }

        $columns[] = 'notes';

        return $columns;

    }

    public function get_rows() {

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

        $orders = get_posts($args);

        // attributes to export
        $separate_all = false;
        $selected = get_option('ocws_common_export_production_attributes_to_show', false);
        if (!$selected || !is_array($selected)) {
            $selected = array();
        }
        // error_log('Attributes selected fot import:');
        // error_log(print_r($selected, 1));
        foreach ( $selected as $attr_name ) {
            $attr_id = wc_attribute_taxonomy_id_by_name( $attr_name );
            $separate_on_export = get_option( "wc_attribute_separate_on_export-$attr_id", false );
            if ($separate_on_export) {
                $separate_all = true;
                break;
            }
        }

        $report = array();

        foreach ($orders as $ord) {
            $order = wc_get_order( $ord->ID );
            if (!$order) continue;
            $shipping_date = get_post_meta($order->get_id(), 'ocws_shipping_info_date', true);
            if (!isset($report[$shipping_date])) {
                $report[$shipping_date] = array();
            }

            foreach ( $order->get_items() as $item_id => $item_product ) {

                $product = $item_product->get_product();
                $parent_id = 0;
                $variation_id = 0;
                $product_name = '';
                $attributes = array();
                $all_variation_attributes = array();
                foreach ($this->selected_attributes as $attr_name => $attr_label) {
                    $attributes[urldecode($attr_name)] = '';
                }
                $prod_type = '';
                $units = '';
                $quantity = $item_product->get_quantity();
                $item_qty_data = array(
                    'units' => $quantity,
                    'kg' => '',
                    'grams' => '',
                    'unit_weight' => '',
                    'unit_weight_label' => '',
                    'weighable' => false
                );
                $units_ordered = $quantity;
                $items_text = '';

                if (function_exists('ocwsu_order_item_quantity_summary')) {
                    $item_qty_data = ocwsu_order_item_quantity_summary($item_product);
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
                    $items_text .= $item_product->get_quantity();
                }
                $product_notes = wc_get_order_item_meta($item_id, __( 'Customer notes about the order', 'woocommerce' ), true);
                //$item->add_meta_data( __( 'Customer notes about the order', 'woocommerce' ), $values['product_note'] );

                $has_size_attribute = false;
                $size_attribute_value = '';

                if ($product) {
                    if ( $product instanceof WC_Product_Variation ) {
                        $attr = $product->get_variation_attributes();
                        $all_variation_attributes = $attr;
                        foreach ($attr as $key => $val) {
                            $attr_key = $key;
                            if ( 0 === strpos( $key, 'attribute_pa_' ) ) {
                                $attr_key = urldecode( substr( $key, 13 ) );
                            }
                            else if ( 0 === strpos( $key, 'attribute_' ) ) {
                                $attr_key = urldecode( substr( $key, 10 ) );
                            }
                            if (isset($attributes[$attr_key])) {
                                $attributes[$attr_key] = urldecode($val);

                                if ($attributes[$attr_key] == '') {
                                    // chosen attribute value is stored in order item meta
                                    // error_log('chosen attribute value is stored in order item meta: pa_'.$attr_key);
                                    // error_log($item_product->get_meta('pa_'.$attr_key));
                                    $v = $item_product->get_meta('pa_'.$attr_key);
                                    if ($v) {
                                        $attributes[$attr_key] = urldecode($v);
                                    }
                                }
                            }
                            if ($attr_key == 'size') {
                                $size_attribute_value = trim(urldecode($val));
                                $has_size_attribute = true;
                                // error_log('Variation attributes of: ' . $product->get_name());
                                // error_log('$size_attribute_value: ' . '/' . $size_attribute_value . '/');
                                // error_log(print_r($attr, 1));
                            }
                        }

                        $parent_id = $product->get_parent_id();
                        $variation_id = $product->get_id();
                        $parent = wc_get_product($parent_id);
                        if ($parent) {
                            $product_name = $parent->get_name();
                        }
                        $prod_type = 'variable';
                    }
                    else {
                        $product_name = $product->get_name();
                        $parent_id = $product->get_id();
                    }

                }

                if (empty($product_name)) {
                    $product_name = $item_product->get_name();
                }

                if (!isset($report[$shipping_date][$parent_id])) {
                    $report[$shipping_date][$parent_id] = array();
                }

                if ($has_size_attribute && !empty($size_attribute_value)) {

                    if (!isset($report[$shipping_date][$parent_id]['sizes_data'])) {
                        $report[$shipping_date][$parent_id]['sizes_data'] = array();
                    }
                    if (!empty($size_attribute_value) && !isset($report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value])) {
                        $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value] = array();
                    }
                    error_log('created entry for $size_attribute_value: ' . $size_attribute_value);
                }

                if (!isset($report[$shipping_date][$parent_id]['order_items'])) {
                    $report[$shipping_date][$parent_id]['order_items'] = array();
                }

                if (!isset($report[$shipping_date][$parent_id]['product_name'])) {
                    $report[$shipping_date][$parent_id]['product_name'] = $product_name;
                }

                if (!isset($report[$shipping_date][$parent_id]['product_type'])) {
                    $report[$shipping_date][$parent_id]['product_type'] = $prod_type;
                }

                if (!isset($report[$shipping_date][$parent_id]['attributes_summary'])) {
                    $report[$shipping_date][$parent_id]['attributes_summary'] = array();
                }

                if ($has_size_attribute && !empty($size_attribute_value) && !isset($report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'])) {
                    $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'] = array();

                    error_log('created entry for $size_attribute_value: ' . $size_attribute_value);
                }

                if (!isset($report[$shipping_date][$parent_id]['export_page'])) {
                    $report[$shipping_date][$parent_id]['export_page'] = 0;
                    $categories = wp_get_post_terms( $parent_id, 'product_cat', array( 'parent' => 0, 'fields' => 'ids' ) );
                    if (is_array($categories)) {
                        foreach ($categories as $cat_id) {
                            if (isset($this->categories[$cat_id])) {
                                $report[$shipping_date][$parent_id]['export_page'] = $this->categories[$cat_id];
                                break;
                            }
                        }
                    }
                }

                if (!isset( $report[$shipping_date][$parent_id]['units'] )) {

                    $report[$shipping_date][$parent_id]['units'] = $item_qty_data['weighable']? __('kg', 'ocwsu') : __('units', 'ocwsu');

                }

                if (!isset( $report[$shipping_date][$parent_id]['weightable'] )) {

                    $report[$shipping_date][$parent_id]['weightable'] = $item_qty_data['weighable'];

                }

                $quantity = $item_product->get_quantity();

                $report[$shipping_date][$parent_id]['order_items'][] = array(
                    'order_id' => $order->get_id(),
                    'product_id' => $parent_id,
                    'variation_id' => $variation_id,
                    'name' => $product_name,
                    'attributes' => $attributes,
                    'units' => $item_qty_data['weighable']? __('kg', 'ocwsu') : __('units', 'ocwsu'),
                    'quantity' => $quantity,
                    'prod_type' => $prod_type,
                    'notes' => $product_notes,
                    'quantity_summary_text' => $items_text
                );

                // error_log('Attributes: -------');
                // error_log(print_r($attributes, 1));
                $attributes_summary_key = implode('|', $attributes);

                //if (!$product_notes) {
                if (!isset( $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key] )) {

                    $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key] = array();
                }

                if ($has_size_attribute && !empty($size_attribute_value) && !isset( $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key] )) {

                    $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key] = array();

                    error_log('created entry for $size_attribute_value: ' . $size_attribute_value);
                }


                if (!isset( $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity'] )) {

                    $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity'] = array();
                }
                $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity'][] = $quantity;


                if ($has_size_attribute && !empty($size_attribute_value) && !isset( $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key]['quantity'] )) {

                    $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key]['quantity'] = array();
                }
                if ($has_size_attribute && !empty($size_attribute_value)) {
                    $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key]['quantity'][] = $quantity;
                }

                if (!isset( $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity_str'] )) {

                    $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity_str'] = array();
                }

                if ($has_size_attribute && !empty($size_attribute_value) && !isset( $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key]['quantity_str'] )) {

                    $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key]['quantity_str'] = array();
                }

                if (!$product_notes) {
                    $quantity_str = $items_text;
                }
                else {
                    $quantity_str = '' . $items_text . ' ( ' . $product_notes . ' )';
                }
                $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity_str'][] = $quantity_str;

                if ($has_size_attribute && !empty($size_attribute_value)) {

                    $report[$shipping_date][$parent_id]['sizes_data'][$size_attribute_value]['attributes_summary'][$attributes_summary_key]['quantity_str'][] = $quantity_str;
                }

                if (!isset( $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['order_data'] )) {

                    $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['order_data'] = array();
                }
                $order_d = 'Order: ' . $order->get_id() . '; Attributes: ' . print_r($all_variation_attributes, 1);
                $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['order_data'][] = $order_d;
                //}

            }



        }


        foreach ( $report as $shipping_date => $data ) {

            //error_log('Report - ' . $shipping_date);
            //error_log(print_r($data, 1));

            $product_ids = array();
            foreach ($data as $prod_id => $entry) {
                $product_ids[] = $prod_id;
            }
            if (count($product_ids)) {
                $args = array(
                    'posts_per_page' => -1,
                    'post_type' => 'product',
                    'orderby' => 'name',
                    'post__in' => $product_ids,
                    'fields' => 'ids',
                    'order' => 'ASC'
                );
                $product_ids = get_posts($args);
            }
            $new_data = array();
            foreach ($product_ids as $prod_id) {
                if (isset( $data[$prod_id] )) {
                    $new_data[$prod_id] = $data[$prod_id];
                }
            }
            $report[$shipping_date] = $new_data;
        }

        $rows = array();

        foreach ( $report as $shipping_date => $products_list ) {

            foreach ( $products_list as $product_id => $product_data ) {

                if (!$separate_all) {

                    $export_page = $product_data['export_page'];



                    if (isset($product_data['sizes_data']) && !empty($product_data['sizes_data'])) {

                        foreach ($product_data['sizes_data'] as $size_value => $size_data) {

                            $size_row = array(
                                'shipping_date' => $shipping_date,
                                'product_name' => $product_data['product_name'] . ' ' . $size_value,
                                'quantity' => 0,
                                'notes' => '',
                                'export_page' => $export_page
                            );

                            foreach ($size_data['attributes_summary'] as $key => $attributes_summary_data) {

                                $quantity = $attributes_summary_data['quantity'];
                                // error_log('$attributes_summary_data:' . $size_row['product_name']);
                                // error_log(print_r($attributes_summary_data, 1));
                                $quantity_str = $attributes_summary_data['quantity_str'];
                                $order_d = $product_data['attributes_summary'][$key]['order_data'];

                                if (!isset($this->attributes_combinations_columns[$key])) {

                                    $attribute_values = explode('|', $key, count($this->selected_attributes));
                                    $combination_column_title = array();

                                    $ind = 0;
                                    foreach ($this->selected_attributes as $attr_name => $attr_label) {
                                        $attr_slug = isset($attribute_values[$ind])? $attribute_values[$ind] : '';
                                        $term = get_term_by('slug', $attr_slug, 'pa_' . $attr_name);
                                        $title_part = $term? $term->name : $attr_slug;
                                        if ($title_part) {
                                            $combination_column_title[] = $title_part;
                                        }
                                        $ind++;
                                    }
                                    $this->attributes_combinations_columns[$key] = implode(' / ', $combination_column_title);
                                }

                                $quantity_sum = 0;
                                $quantity_str = implode(' + ' . "\n", $quantity_str);

                                foreach ($quantity as $q) {
                                    $quantity_sum += $q;
                                }

                                $size_row[$key] = $quantity_str;
                                $size_row['quantity'] += $quantity_sum;
                            }

                            $size_row['quantity'] = $size_row['quantity'] . ' ' . $product_data['units'];
                            $rows[] = $size_row;
                        }
                    }

                    else {

                        $row = array(
                            'shipping_date' => $shipping_date,
                            'product_name' => $product_data['product_name'],
                            'quantity' => 0,
                            'notes' => '',
                            'export_page' => $export_page
                        );

                        foreach ($product_data['attributes_summary'] as $key => $attributes_summary_data) {

                            $quantity = $attributes_summary_data['quantity'];
                            $quantity_str = $attributes_summary_data['quantity_str'];
                            $order_d = $attributes_summary_data['order_data'];
                            //$row['notes'] .= '; ' . implode(', ', $order_d);

                            if (!isset($this->attributes_combinations_columns[$key])) {

                                $attribute_values = explode('|', $key, count($this->selected_attributes));
                                $combination_column_title = array();

                                $ind = 0;
                                foreach ($this->selected_attributes as $attr_name => $attr_label) {
                                    $attr_slug = isset($attribute_values[$ind])? $attribute_values[$ind] : '';
                                    $term = get_term_by('slug', $attr_slug, 'pa_' . $attr_name);
                                    $title_part = $term? $term->name : $attr_slug;
                                    if ($title_part) {
                                        $combination_column_title[] = $title_part;
                                    }
                                    $ind++;
                                }
                                $this->attributes_combinations_columns[$key] = implode(' / ', $combination_column_title);
                            }

                            $quantity_sum = 0;
                            $quantity_str = implode(' + ' . "\n", $quantity_str);

                            foreach ($quantity as $q) {
                                $quantity_sum += $q;
                            }

                            $row[$key] = $quantity_str;
                            $row['quantity'] += $quantity_sum;
                        }

                        $row['quantity'] = $row['quantity'] . ' ' . $product_data['units'];
                        $rows[] = $row;
                    }



                }
                else {

                    foreach ($product_data['order_items'] as $order_item_details) {

                        $row = array(
                            'shipping_date' => $shipping_date,
                            'product_name' => $product_data['product_name'],
                        );
                        foreach ($order_item_details['attributes'] as $attr => $value) {
                            $term = get_term_by('slug', $value, 'pa_' . $attr);
                            $row[$attr] = $term? $term->name : $value;
                        }
                        $row['quantity'] = $order_item_details['quantity_summary_text'];
                        $row['notes'] = '';//$order_item_details['notes'];

                        $rows[] = $row;
                    }
                }



            }

        }

        // error_log('found '.count($report).' shipping dates');
        // error_log(print_r($rows, 1));

        return $rows;
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
                ->setTitle(__('Summary for production', 'ocws'))
                ->setSubject(__('Summary for production', 'ocws'));

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
            //$this->print_columns();
            //$return = $this->print_rows();
            return false;
        }

        if ( $return ) {
            return true;
        } else {
            return false;
        }
    }


    public function print_rows_to_spreadsheet_adv($data) {

        $alphas = range('A', 'Z');
        $columns = $this->get_columns();

        $page_row_index = array();
        foreach ($this->pages as $k => $page) {
            $page_row_index[$k] = 0;
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
