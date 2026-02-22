<?php

defined( 'ABSPATH' ) || exit;

use Carbon\Carbon;

class OC_Woo_Export_For_Production extends OC_Woo_Export {

    private $selected_attributes;

    public function __construct() {
        parent::__construct();
        $selected = get_option('ocws_common_export_production_attributes_to_show', false);
        if (!$selected || !is_array($selected)) {
            $selected = array();
        }
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes_assoc = array();
        if ( $attribute_taxonomies ) {

            foreach ( $attribute_taxonomies as $tax ) {
                if (in_array( $tax->attribute_name, $selected )) {
                    $attributes_assoc[$tax->attribute_name] = $tax->attribute_label;
                }
            }
        }
        $this->selected_attributes = $attributes_assoc;
    }


    public function process_shipping_data_export(){

        if ( ( $error = $this->check_export_location() ) !== true ) {
            wp_send_json_error( __( 'Filesystem ERROR: ' . $error, 'userswp' ) );
            wp_die();
        }

        $this->set_export_params();

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

        foreach ($this->selected_attributes as $attr_name => $attr_label) {
            $titles[$attr_name] = $attr_label;
        }

        return $titles;
    }

    public function get_columns() {

        $columns = array(
            'shipping_date',
            'product_name',
        );

        foreach ($this->selected_attributes as $attr_name => $attr_label) {
            $columns[] = $attr_name;
        }

        $columns[] = 'quantity';
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
        error_log('Attributes selected fot import:');
        error_log(print_r($selected, 1));
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
                $product_notes = wc_get_order_item_meta($item_id, __( 'הערות לקוח', 'sea2door' ), true);


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

                if (function_exists('get_field')) {
                    $unit_calc_label  = ocws_get_acf_label('unit_calc' , $parent_id);

                    if ($unit_calc_label) {
                        $units = $unit_calc_label;
                    }
                }

                if (!isset( $report[$shipping_date][$parent_id]['units'] )) {

                    $report[$shipping_date][$parent_id]['units'] = $units;

                }

                if (!isset( $report[$shipping_date][$parent_id]['weightable'] )) {

                    if (function_exists('get_field')) {
                        $unit_calc = ocws_get_acf_value('unit_calc', $parent_id);
                        $unit_title = ocws_get_acf_value('unit_title', $parent_id);

                        $weightable = false;
                        $weightable_units = array('kg', 'g');
                        if (in_array($unit_calc, $weightable_units) || in_array($unit_title, $weightable_units)) {
                            $weightable = true;
                        }
                    }

                    $report[$shipping_date][$parent_id]['weightable'] = $weightable;

                }

                $quantity = $item_product->get_quantity();

                $report[$shipping_date][$parent_id]['order_items'][] = array(
                    'order_id' => $order->get_id(),
                    'product_id' => $parent_id,
                    'variation_id' => $variation_id,
                    'name' => $product_name,
                    'attributes' => $attributes,
                    'units' => $units,
                    'quantity' => $quantity,
                    'prod_type' => $prod_type,
                    'notes' => $product_notes
                );

                $attributes_summary_key = implode('|', $attributes);

                if (!$product_notes) {
                    if (!isset( $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key] )) {

                        $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key] = array();
                    }
                    if (!isset( $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity'] )) {

                        $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity'] = array();
                    }
                    $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['quantity'][] = $quantity;

                    if (!isset( $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['order_data'] )) {

                        $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['order_data'] = array();
                    }
                    $order_d = 'Order: ' . $order->get_id() . '; Attributes: ' . print_r($all_variation_attributes, 1);
                    $report[$shipping_date][$parent_id]['attributes_summary'][$attributes_summary_key]['order_data'][] = $order_d;
                }

            }



        }

        foreach ( $report as $shipping_date => $data ) {

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

                    foreach ($product_data['attributes_summary'] as $key => $attributes_summary_data) {

                        $quantity = $attributes_summary_data['quantity'];
                        $order_d = $attributes_summary_data['order_data'];

                        $row = array(
                            'shipping_date' => $shipping_date,
                            'product_name' => $product_data['product_name'],
                        );

                        $attribute_values = explode('|', $key, count($this->selected_attributes));

                        $ind = 0;
                        foreach ($this->selected_attributes as $attr_name => $attr_label) {
                            $attr_slug = isset($attribute_values[$ind])? $attribute_values[$ind] : '';
                            $term = get_term_by('slug', $attr_slug, 'pa_' . $attr_name);
                            $row[$attr_name] = $term? $term->name : $attr_slug;
                            $ind++;
                        }

                        $quantity_sum = 0;
                        $quantity_str = ($product_data['weightable']? ' ( ' . implode(', ', $quantity) . ' )' : '');
                        foreach ($quantity as $q) {
                            $quantity_sum += $q;
                        }
                        $row['quantity'] = $quantity_sum . ' ' . $product_data['units'] . $quantity_str;
                        $row['notes'] = ($product_data['weightable']? ' ( ' . implode(', ', $order_d) . ' )' : '');

                        $rows[] = $row;
                    }

                    foreach ($product_data['order_items'] as $order_item_details) {
                        if ($order_item_details['notes']) {
                            $row = array(
                                'shipping_date' => $shipping_date,
                                'product_name' => $product_data['product_name'],
                            );
                            foreach ($order_item_details['attributes'] as $attr => $value) {
                                $term = get_term_by('slug', $value, 'pa_' . $attr);
                                $row[$attr] = $term? $term->name : $value;
                            }
                            $row['quantity'] = $order_item_details['quantity'] . ' ' . $product_data['units'];
                            $row['notes'] = $order_item_details['notes'];

                            $rows[] = $row;
                        }
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
                        $row['quantity'] = $order_item_details['quantity'] . ' ' . $product_data['units'];
                        $row['notes'] = $order_item_details['notes'];

                        $rows[] = $row;
                    }
                }



            }

        }

        error_log('found '.count($report).' shipping dates');
        error_log(print_r($rows, 1));

        return $rows;
    }

}
