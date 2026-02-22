<?php

defined( 'ABSPATH' ) || exit;

use Carbon\Carbon;

class OCWS_Admin_Columns
{

    const COLUMN_NAME_PREFIX = 'ocws_';

    const OCWS_MODE_ALL_ORDERS = 'all_orders';
    const OCWS_MODE_SHIPPING_ORDERS = 'shipping_orders';

    private static $instance;

    private $admin_columns = array();

    private $variables = array();

    /**
     * Object being shown on the row.
     *
     * @var object|null
     */
    protected $object = null;

    private function __construct()
    {
        //add_action( 'manage_posts_extra_tablenav', array($this, 'render_mode_buttons') );
        add_action( 'restrict_manage_posts', array($this, 'render_mode_buttons'), 3 );
        add_action( 'restrict_manage_posts', array($this, 'render_order_date_filter'), 1 );
        add_action( 'restrict_manage_posts', array($this, 'render_order_completed_date_filter'), 2 );
        add_action('posts_where', array($this, 'action_prepare_query_filter_by_order_date'), 10, 2);
        add_action('pre_get_posts', array($this, 'action_prepare_query_filter_by_order_completed_date'), 1000, 1);

        if ($this->is_shipping_and_pickup_method_filter_chosen()) {

            $this->init_column_variables();
            add_action('admin_enqueue_scripts', array($this, 'action_enqueue_admin_scripts'));

            // WP admin post index tables ("All posts" screens)
            add_action('parse_request', array($this, 'action_prepare_columns'), 10);

            add_action('pre_get_posts', array($this, 'action_prepare_query_filter'));
            add_action('posts_request', array($this, 'filter_posts_request'), 10, 2); // temp.

            add_filter( 'posts_where', array($this, 'filter_admin_shipping_filter'), 10, 2 );

            add_action( 'restrict_manage_posts', array($this, 'action_orders_filter_dropdown') );

            add_action( 'restrict_manage_posts', array($this, 'action_display_export_buttons'), 100 );
        }
    }

    public function filter_posts_request( $request, $query ) {

        error_log($request);
        return $request;
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function init_column_variables() {

        $this->variables = array(
            'customer_full_name' => __('Customer Full Name', 'ocws'),
            'shipping_method' => __('Shipping method', 'ocws'),
            'shipping_group_name' => __('Branch', 'ocws'),
            'shipping_city' => __('City', 'ocws'),
            'shipping_date' => __('Shipping Date', 'ocws'),
            'shipping_time_slot' => __('Shipping time slot', 'ocws'),
            'order_products_count' => __('Products quantity', 'ocws'),
            'customer_phone' => __('Phone', 'ocws'),
            'customer_street' => __('Street', 'ocws'),
            'customer_house_number' => __('House number', 'ocws'),
            'order_notes' => __('Notes', 'ocws'),
        );

        if (OC_WOO_USE_COMPANIES) {
            $this->variables['shipping_company'] = __('Shipping company', 'ocws');
        }
    }

    public function action_enqueue_admin_scripts() {

        if (!$this->is_valid_admin_screen()) {
            return;
        }

        //wp_enqueue_script( 'jquery-ui-datepicker' );
    }

    /**
     * Checks which columns to show on the current screen and attaches to the respective WP hooks
     */
    public function action_prepare_columns()
    {
        $screen = $this->is_valid_admin_screen();
        if (count($this->admin_columns) > 0 || !$screen) {
            return;
        }

        // add sortable shipping date meta to orders
        // TODO: run once and comment
        // -------------------------------------------------------------------
        /*$order_ids = wc_get_orders( array(
            'limit'    => -1,
            'return'   => 'ids',
        ) );

        if ( empty( $order_ids ) ) {
            $order_ids = array();
        }

        foreach ($order_ids as $order_id) {
            $date = get_post_meta($order_id, 'ocws_lp_pickup_date', true);
            if ($date) {
                update_post_meta( $order_id, 'ocws_shipping_tag', OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG );
                update_post_meta( $order_id, 'ocws_shipping_info_date', $date );

                $sortable = get_post_meta( $order_id, 'ocws_lp_pickup_date_sortable', true);
                if ($sortable) {
                    update_post_meta( $order_id, 'ocws_shipping_info_date_sortable', $sortable );
                }

                $slot_start = get_post_meta( $order_id, 'ocws_lp_pickup_slot_start', true);
                if ($slot_start) {
                    update_post_meta( $order_id, 'ocws_shipping_info_slot_start', $slot_start );
                }

                $slot_end = get_post_meta( $order_id, 'ocws_lp_pickup_slot_end', true);
                if ($slot_end) {
                    update_post_meta( $order_id, 'ocws_shipping_info_slot_end', $slot_end );
                }
            }
            else {
                $date = get_post_meta($order_id, 'ocws_shipping_info_date', true);
                if ($date) {
                    update_post_meta( $order_id, 'ocws_shipping_tag', OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG );
                }
            }
        }*/
        // -------------------------------------------------------------------


        // add billing and shipping city name meta to orders
        // TODO: run once and comment
        // -------------------------------------------------------------------
        /*$order_ids = wc_get_orders( array(
            'limit'    => -1,
            'return'   => 'ids',
        ) );

        if ( empty( $order_ids ) ) {
            $order_ids = array();
        }

        foreach ($order_ids as $order_id) {
            $meta = get_post_meta($order_id, '_billing_city', true);
            if ($meta && is_numeric($meta)) {
                update_post_meta($order_id, '_billing_city_name', ocws_get_city_title($meta));
            }
            $meta = get_post_meta($order_id, '_shipping_city', true);
            if ($meta && is_numeric($meta)) {
                update_post_meta($order_id, '_shipping_city_name', ocws_get_city_title($meta));
            }
        }*/
        // -------------------------------------------------------------------

        foreach ($this->variables as $name => $label) {

            $this->admin_columns[self::COLUMN_NAME_PREFIX . $name] = $label;
        }

        if (!empty($this->admin_columns)) {
            add_filter('manage_' . $screen->post_type . '_posts_columns', array($this, 'filter_manage_posts_columns')); // creates the columns
            add_filter('manage_' . $screen->id . '_sortable_columns', array($this, 'filter_manage_sortable_columns')); // make columns sortable
            add_action('manage_' . $screen->post_type . '_posts_custom_column', array($this, 'action_manage_posts_custom_column'), 10, 2); // outputs the columns values for each post
        }
    }

    /**
     * prepares WPs query object when ordering by shipping date column
     *
     * @param WP_Query $query
     * @return mixed
     */
    public function action_prepare_query_sort($query)
    {

        if ($this->is_valid_admin_screen() && $query->is_main_query() && $query->query_vars && isset($query->query_vars['orderby'])) {

            if (
                isset($_GET['ocws_order_shipping_method_filter']) &&
                $_GET['ocws_order_shipping_method_filter'] != 'all' &&
                !empty($_GET['ocws_order_shipping_method_filter'])
            ) {
                // not our shipping method
                return $query;
            }

            $orderby = $query->query_vars['orderby'];
            $order = isset($query->query_vars['order'])? $query->query_vars['order'] : 'ASC';

            if (array_key_exists($orderby, $this->admin_columns)) {

                // this makes sure we sort also when the custom meta has never been set on some posts before
                if ($orderby == 'ocws_shipping_date') {

                    $meta_query = array(
                        'relation' => 'AND',
                        array(
                            'relation' => 'OR',
                            array('key' => 'ocws_shipping_info_date', 'compare' => 'NOT EXISTS'), // 'NOT EXISTS' needs to go first for proper sorting
                            array('key' => 'ocws_shipping_info_date', 'compare' => 'EXISTS'),
                        ),
                        array(
                            'relation' => 'OR',
                            array('key' => 'ocws_shipping_info_date_sortable', 'compare' => 'NOT EXISTS'), // 'NOT EXISTS' needs to go first for proper sorting
                            array('key' => 'ocws_shipping_info_date_sortable', 'compare' => 'EXISTS'),
                        ),
                    );


                    $query->set('meta_query', $meta_query);
                    $query->set('orderby', array('ocws_shipping_info_date_sortable' => $order, 'ocws_shipping_info_date' => $order));
                }
            }
        }

        return $query;
    }

    function filter_admin_shipping_filter( $where, $wp_query )
    {
        global $pagenow, $wpdb;

        if ( is_admin() && $pagenow=='edit.php' && $wp_query->query_vars['post_type'] == 'shop_order' ) {

            if (
                isset($_GET['ocws_order_shipping_method_filter']) &&
                $_GET['ocws_order_shipping_method_filter'] != 'all' &&
                !empty($_GET['ocws_order_shipping_method_filter'])
            ) {
                // not our shipping method

                /*$arr = explode(':', $_GET['ocws_order_shipping_method_filter'], 2);

                if (count($arr) == 2) {
                    $where .= $wpdb->prepare( 'AND ID
                            IN (
                                SELECT i.order_id
                                FROM ' . $wpdb->prefix . 'woocommerce_order_items as i
                                JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta as im
                                JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta as im2
                                ON (im.order_item_id = i.order_item_id AND im2.order_item_id = i.order_item_id)
                                WHERE i.order_item_type = "shipping" AND im.meta_key = "method_id"
                                AND (im.meta_value = %s OR im.meta_value = %s)
                            )', array(OCWS_Advanced_Shipping::SHIPPING_METHOD_ID, OCWS_LP_Local_Pickup::PICKUP_METHOD_ID) );
                }*/
            }
        }

        return $where;
    }

    /**
     * prepares WPs query object when filtering posts
     *
     * @param WP_Query $query
     * @return mixed
     */
    public function action_prepare_query_filter_by_order_completed_date($query) {

        if ($this->is_valid_admin_screen() && $query->is_main_query()) {

            $filter_date_start = $filter_date_end = '';

            if(isset($_GET['ocws_filter_order_cdate_start'])) {
                try {
                    $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_start']);
                    $dt->hour(0);
                    $dt->minute(0);
                    $dt->second(0);
                    $filter_date_start = $dt->format('Y-m-d H:i:s');
                } catch (InvalidArgumentException $e) {
                }
            }
            if(isset($_GET['ocws_filter_order_cdate_end'])) {
                try {
                    $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_end']);
                    $dt->hour(23);
                    $dt->minute(59);
                    $dt->second(59);
                    $filter_date_end = $dt->format('Y-m-d H:i:s');
                } catch (InvalidArgumentException $e) {
                }
            }

            $meta = array();
            if ($filter_date_start || $filter_date_end) {
                $meta['date_completed_clause'] = array(
                    'key'     => '_completed_date',
                    'compare' => 'EXISTS'
                );
            }
            if ($filter_date_start) {
                $meta[] = array(
                    'key'     => '_completed_date',
                    'value'   => $filter_date_start,
                    'compare' => '>='
                );
            }
            if ($filter_date_end) {
                $meta[] = array(
                    'key'     => '_completed_date',
                    'value'   => $filter_date_end,
                    'compare' => '<='
                );
            }
            if (count($meta) > 0) {
                $meta_query = $query->get('meta_query', array());
                $new_meta_query = array();

                if(isset($meta_query['relation'])){
                    //there is a special relation set within the meta_query.
                    //to preserve this, we have to encapsulate the old meta queries
                    $new_meta_query[] = $meta_query;
                } else {
                    //there is no special relation set on global level ("AND" is used)
                    //we can just merge them all together
                    foreach($meta_query as $old_single_meta_query){
                        $new_meta_query[] = $old_single_meta_query;
                    }
                }
                $new_meta_query[] = $meta;
                $query->set('meta_query', $new_meta_query);
            }
        }
    }

    /**
     * prepares WPs query object when filtering posts
     *
     * @param WP_Query $query
     * @return mixed
     */
    public function action_prepare_query_filter($query) {

        if ($this->is_valid_admin_screen() && $query->is_main_query()) {

            if (
                isset($_GET['ocws_order_shipping_method_filter']) &&
                $_GET['ocws_order_shipping_method_filter'] != 'all' &&
                !empty($_GET['ocws_order_shipping_method_filter'])
            ) {
                // not our shipping method
                return $query;
            }

            error_log('filtering orders query');


            $filter_date_start = $filter_date_end = '';
            $filter_date_start_sortable = $filter_date_end_sortable = '';
            $filter_group_id = 0;
            $filter_aff_id = 0;
            $filter_city_id = 0;
            $filter_company_id = 0;
            $filter_posts_by = '';

            if (!isset($_GET['ocws_order_shipping_date_filter'])) {
                $_GET['ocws_order_shipping_date_filter'] = '';
            }

            if (isset($_GET['ocws_order_shipping_date_filter']) && $_GET['ocws_order_shipping_date_filter']) {

                if ($_GET['ocws_order_shipping_date_filter'] == 'from_to') {

                    if(isset($_GET['ocws_filter_shipping_date_start'])) {
                        try {
                            $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_start']);
                            $filter_date_start = $dt->format('d/m/Y');
                            $filter_date_start_sortable = $dt->format('Y/m/d');
                        } catch (InvalidArgumentException $e) {
                        }
                    }
                    if(isset($_GET['ocws_filter_shipping_date_end'])) {
                        try {
                            $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_end']);
                            $filter_date_end = $dt->format('d/m/Y');
                            $filter_date_end_sortable = $dt->format('Y/m/d');
                        } catch (InvalidArgumentException $e) {
                        }
                    }
                    if ($filter_date_start || $filter_date_end) {
                        $filter_posts_by = 'from_to';
                    }
                }
                else if ($_GET['ocws_order_shipping_date_filter'] == 'today') {
                    $filter_posts_by = 'today';
                }
            }
            if (isset($_GET['ocws_order_group_name_filter']) && $_GET['ocws_order_group_name_filter']) {
                $filter_group_id = intval($_GET['ocws_order_group_name_filter']);
            }
            if (isset($_GET['ocws_order_company_filter']) && $_GET['ocws_order_company_filter']) {
                $filter_company_id = intval($_GET['ocws_order_company_filter']);
            }
            if (isset($_GET['ocws_order_shipping_city_filter']) && $_GET['ocws_order_shipping_city_filter']) {
                $filter_city_id = ($_GET['ocws_order_shipping_city_filter']);
            }
            if (isset($_GET['ocws_order_affiliate_name_filter']) && $_GET['ocws_order_affiliate_name_filter']) {
                $filter_aff_id = intval($_GET['ocws_order_affiliate_name_filter']);
            }

            $main_meta = array();
            /*$main_meta[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'ocws_shipping_tag',
                    'value'   => OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG,
                    'compare' => '='
                ),
                array(
                    'key'     => 'ocws_shipping_tag',
                    'value'   => OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG,
                    'compare' => '='
                )
            );*/

            if ($filter_posts_by == 'today') {
                $today_date = Carbon::now();
                $date_to_compare = $today_date->format('d/m/Y');
                $main_meta[] = array(
                    'key'     => 'ocws_shipping_info_date',
                    'value'   => $date_to_compare,
                    'compare' => '='
                );
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
                    'relation' => 'OR',
                    array(
                        'key'     => 'ocws_shipping_group',
                        'compare' => 'NOT EXISTS'
                    ),
                    'shipping_group_clause' => array(
                        'key'     => 'ocws_shipping_group',
                        'compare' => 'EXISTS'
                    )
                );
                $main_meta[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_billing_city',
                        'compare' => 'NOT EXISTS'
                    ),
                    'billing_city_clause' => array(
                        'key'     => '_billing_city',
                        'compare' => 'EXISTS'
                    )
                );
                $main_meta[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'ocws_lp_pickup_aff_id',
                        'compare' => 'NOT EXISTS'
                    ),
                    'pickup_aff_clause' => array(
                        'key'     => 'ocws_lp_pickup_aff_id',
                        'compare' => 'EXISTS'
                    )
                );
                //$query->set('orderby', array('pickup_aff_clause' => 'ASC', 'shipping_group_clause' => 'ASC', 'billing_city_clause' => 'ASC', 'slot_start_clause' => 'ASC'));

                if ($query->query_vars && isset($query->query_vars['orderby']) && $query->query_vars['orderby'] == 'ocws_shipping_date') {

                    $order = isset($query->query_vars['order'])? $query->query_vars['order'] : 'ASC';
                    $query->set('orderby', array('date_sortable_clause' => $order, 'pickup_aff_clause' => 'ASC', 'shipping_group_clause' => 'ASC', 'billing_city_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
                else {

                    //$query->set('orderby', array('date_sortable_clause' => 'ASC', 'pickup_aff_clause' => 'ASC', 'shipping_group_clause' => 'ASC', 'billing_city_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
            }
            else if ($filter_posts_by == 'from_to') {
                if ($filter_date_start && $filter_date_end) {
                    $main_meta['date_sortable_clause'] = array(
                        'key'     => 'ocws_shipping_info_date_sortable',
                        'compare' => 'EXISTS'
                    );
                }
                if ($filter_date_start) {
                    $main_meta[] = array(
                        'key'     => 'ocws_shipping_info_date_sortable',
                        'value'   => $filter_date_start_sortable,
                        'compare' => '>='
                    );
                }
                if ($filter_date_end) {
                    $main_meta[] = array(
                        'key'     => 'ocws_shipping_info_date_sortable',
                        'value'   => $filter_date_end_sortable,
                        'compare' => '<='
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
                    'relation' => 'OR',
                    array(
                        'key'     => 'ocws_shipping_group',
                        'compare' => 'NOT EXISTS'
                    ),
                    'shipping_group_clause' => array(
                        'key'     => 'ocws_shipping_group',
                        'compare' => 'EXISTS'
                    )
                );
                $main_meta[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_billing_city',
                        'compare' => 'NOT EXISTS'
                    ),
                    'billing_city_clause' => array(
                        'key'     => '_billing_city',
                        'compare' => 'EXISTS'
                    )
                );
                $main_meta[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'ocws_lp_pickup_aff_id',
                        'compare' => 'NOT EXISTS'
                    ),
                    'pickup_aff_clause' => array(
                        'key'     => 'ocws_lp_pickup_aff_id',
                        'compare' => 'EXISTS'
                    )
                );

                if ($query->query_vars && isset($query->query_vars['orderby']) && $query->query_vars['orderby'] == 'ocws_shipping_date') {

                    $order = isset($query->query_vars['order'])? $query->query_vars['order'] : 'ASC';
                    $query->set('orderby', array('date_sortable_clause' => $order, 'pickup_aff_clause' => 'ASC', 'shipping_group_clause' => 'ASC', 'billing_city_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
                else {

                    //$query->set('orderby', array('date_sortable_clause' => 'ASC', 'pickup_aff_clause' => 'ASC', 'shipping_group_clause' => 'ASC', 'billing_city_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
            }
            else {

                $need_sort_by_date = ($query->query_vars && isset($query->query_vars['orderby']) && $query->query_vars['orderby'] == 'ocws_shipping_date');

                if (/*$need_sort_by_date || */$filter_group_id) {

                    $main_meta[] = array(
                        'relation' => 'OR',
                        array(
                            'key'     => 'ocws_shipping_group',
                            'compare' => 'NOT EXISTS'
                        ),
                        'shipping_group_clause' => array(
                            'key'     => 'ocws_shipping_group',
                            'compare' => 'EXISTS'
                        )
                    );
                }

                if (/*$need_sort_by_date || */$filter_city_id) {

                    $main_meta[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_billing_city',
                            'compare' => 'NOT EXISTS'
                        ),
                        'billing_city_clause' => array(
                            'key' => '_billing_city',
                            'compare' => 'EXISTS'
                        )
                    );
                }

                if (/*$need_sort_by_date || */$filter_aff_id) {

                    $main_meta[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => 'ocws_lp_pickup_aff_id',
                            'compare' => 'NOT EXISTS'
                        ),
                        'pickup_aff_clause' => array(
                            'key' => 'ocws_lp_pickup_aff_id',
                            'compare' => 'EXISTS'
                        )
                    );
                }

                if ($need_sort_by_date) {

                    $main_meta['date_sortable_clause'] = array(
                        'key'     => 'ocws_shipping_info_date_sortable',
                        'compare' => 'EXISTS'
                    );
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
                }

                if ($query->query_vars && isset($query->query_vars['orderby']) && $query->query_vars['orderby'] == 'ocws_shipping_date') {

                    $order = isset($query->query_vars['order'])? $query->query_vars['order'] : 'ASC';
                    $query->set('orderby', array('date_sortable_clause' => $order, /*'pickup_aff_clause' => 'ASC', 'shipping_group_clause' => 'ASC', 'billing_city_clause' => 'ASC',*/ 'slot_start_clause' => 'ASC'));
                }
                else {

                    //$query->set('orderby', array('date_sortable_clause' => 'ASC', 'pickup_aff_clause' => 'ASC', 'shipping_group_clause' => 'ASC', 'billing_city_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
            }

            if ($filter_city_id) {

                $main_meta[] = array(
                    'key'     => '_billing_city',
                    'value'   => $filter_city_id,
                    'compare' => '='
                );
            }

            if ($filter_group_id) {

                $main_meta[] = array(
                    'key'     => 'ocws_shipping_group',
                    'value'   => $filter_group_id,
                    'compare' => '='
                );
            }

            if ($filter_aff_id) {

                $main_meta[] = array(
                    'key'     => 'ocws_lp_pickup_aff_id',
                    'value'   => $filter_aff_id,
                    'compare' => '='
                );
            }

            if ($filter_company_id) {

                $main_meta[] = array(
                    'key'     => '_ocws_shipping_company_id',
                    'value'   => $filter_company_id,
                    'compare' => '='
                );
            }

            if (count($main_meta)) {
                $query->set('meta_query', $main_meta);
            }

            /*if ($filter_posts_by == '') {

                return $this->action_prepare_query_sort($query);
            }*/
        }

        error_log($query->request);
        return $query;
    }

    /**
     * prepares WPs query object when filtering posts
     *
     * @param WP_Query $query
     * @return mixed
     */
    public function action_prepare_query_filter_by_order_date($where, $query)
    {
        global $wpdb;
        if ($this->is_valid_admin_screen() && $query->is_main_query()) {

            $filter_date_start = $filter_date_end = '';

            if(isset($_GET['ocws_filter_order_date_start'])) {
                try {
                    $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_start']);
                    $dt->hour(0);
                    $dt->minute(0);
                    $dt->second(0);
                    $filter_date_start = $dt->format('Y-m-d H:i:s');
                } catch (InvalidArgumentException $e) {
                }
            }
            if(isset($_GET['ocws_filter_order_date_end'])) {
                try {
                    $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_end']);
                    $dt->hour(23);
                    $dt->minute(59);
                    $dt->second(59);
                    $filter_date_end = $dt->format('Y-m-d H:i:s');
                } catch (InvalidArgumentException $e) {
                }
            }

            if ($filter_date_start) {
                $where .= " AND {$wpdb->posts}.post_date >= '" . $filter_date_start . "'";
            }
            if ($filter_date_end) {
                $where .= " AND {$wpdb->posts}.post_date <= '" . $filter_date_end . "'";
            }
            error_log('ocws_filter_order_date_start - ocws_filter_order_date_end');
            error_log($where);
        }

        return $where;
    }

    /**
     * Adds the designated columns to Wordpress admin post list table.
     *
     * @param $columns array passed by Wordpress
     * @return array
     */
    public function filter_manage_posts_columns($columns)
    {

        if (!empty($this->admin_columns)) {
            $columns = array_merge($columns, $this->admin_columns);
        }

        return $columns;
    }

    /**
     * Makes our columns rendered as sortable.
     *
     * @param $columns
     * @return mixed
     */
    public function filter_manage_sortable_columns($columns)
    {

        if (
            isset($_GET['ocws_order_shipping_method_filter']) &&
            $_GET['ocws_order_shipping_method_filter'] != 'all' &&
            !empty($_GET['ocws_order_shipping_method_filter'])
        ) {
            // not our shipping method
            return $columns;
        }
        foreach ($this->admin_columns as $key => $col) {
            if ($key == 'ocws_shipping_date') {
                $columns[$key] = $key;
            }
        }

        return $columns;
    }

    /**
     * WP Hook for displaying the field value inside of a columns cell in posts index pages
     *
     * @hook
     * @param $column
     * @param $post_id
     */
    public function action_manage_posts_custom_column($column, $post_id)
    {

        if (array_key_exists($column, $this->admin_columns)) {

            $this->prepare_row_data( $post_id );

            if ( ! $this->object ) {
                return;
            }

            if ( is_callable( array( $this, 'render_' . $column . '_column' ) ) ) {
                //error_log('render_' . $column . '_column' . ' is callable');
                $this->{"render_{$column}_column"}();
            }
        }
    }

    /**
     * Pre-fetch any data for the row each column has access to it.
     *
     * @param int $post_id Post ID being shown.
     */
    protected function prepare_row_data( $post_id ) {
        global $ocws_order;

        if ( empty( $this->object ) || $this->object->get_id() !== $post_id ) {
            $this->object = wc_get_order( $post_id );
            $ocws_order    = $this->object;
        }
    }

    /**
     * Render columm: ocws_customer_full_name.
     */
    public function render_ocws_customer_full_name_column() {

        $buyer = '';

        if ( $this->object->get_billing_first_name() || $this->object->get_billing_last_name() ) {
            /* translators: 1: first name 2: last name */
            $buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), $this->object->get_billing_first_name(), $this->object->get_billing_last_name() ) );
        } elseif ( $this->object->get_billing_company() ) {
            $buyer = trim( $this->object->get_billing_company() );
        } elseif ( $this->object->get_customer_id() ) {
            $user  = get_user_by( 'id', $this->object->get_customer_id() );
            $buyer = ucwords( $user->display_name );
        }

        echo esc_html( $buyer );
    }

    /**
     * Render columm: ocws_shipping_method_name.
     */
    public function render_ocws_shipping_method_column() {

        $tag = get_post_meta($this->object->get_id(), 'ocws_shipping_tag', true);

        if ($tag == OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG) {

            echo __('Shipping', 'ocws');
        }
        else if ($tag == OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG) {

            echo __('Pickup', 'ocws');
        }
    }

    /**
     * Render columm: ocws_shipping_group_name.
     */
    public function render_ocws_shipping_group_name_column() {

        $group_name = '';

        $tag = get_post_meta($this->object->get_id(), 'ocws_shipping_tag', true);

        if ($tag == OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG) {

            if ( $this->object->get_billing_city() ) {

                $group = OC_Woo_Shipping_Groups::get_group_by('location_code', $this->object->get_billing_city());

                if (false !== $group) {
                    // OC_Woo_Shipping_Group $group
                    $group_name = $group->get_group_name();
                }
            }
        }
        else if ($tag == OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG) {

            $group_name = get_post_meta( $this->object->get_id(), 'ocws_lp_pickup_aff_name', true );
        }

        echo esc_html( $group_name );
    }

    /**
     * Render columm: ocws_shipping_city.
     */
    public function render_ocws_shipping_city_column() {

        $city = '';

        $tag = get_post_meta($this->object->get_id(), 'ocws_shipping_tag', true);

        if ($tag == OCWS_Advanced_Shipping::SHIPPING_METHOD_TAG) {

            if ( $this->object->get_billing_city() ) {

                $city = ocws_get_city_title( $this->object->get_billing_city() );
            }
        }

        echo esc_html( $city );
    }

    /**
     * Render columm: ocws_shipping_date.
     */
    public function render_ocws_shipping_date_column() {

        $date = get_post_meta($this->object->get_id(), 'ocws_shipping_info_date', true);

        echo '<strong>' . esc_html( $date ) . '</strong>';
    }

    /**
     * Render columm: ocws_shipping_time_slot.
     */
    public function render_ocws_shipping_time_slot_column() {

        $slot = '';

        $start = get_post_meta($this->object->get_id(), 'ocws_shipping_info_slot_start', true);
        $end = get_post_meta($this->object->get_id(), 'ocws_shipping_info_slot_end', true);

        if ( $start && $end ) {

            $slot = sprintf(
                '%s - %s',
                $start,
                $end
            );
        }

        echo '<strong>' . esc_html( $slot ) . '</strong>';
    }

    /**
     * Render columm: ocws_order_products_count.
     */
    public function render_ocws_order_products_count_column() {

        $count = '';

        $items = $this->object->get_items();

        if ( $items ) {

            $count = count( $items );
        }

        echo esc_html( $count );
    }

    /**
     * Render columm: ocws_customer_phone.
     */
    public function render_ocws_customer_phone_column() {

        if ( $this->object->get_billing_phone() ) {

            echo esc_html( $this->object->get_billing_phone() );
        }
    }

    /**
     * Render columm: ocws_customer_street.
     */
    public function render_ocws_customer_street_column() {

        if ( $this->object->get_billing_address_1() ) {

            echo esc_html( $this->object->get_billing_address_1() );
        }
    }

    /**
     * Render columm: ocws_customer_house_number.
     */
    public function render_ocws_customer_house_number_column() {

        $house_num = get_post_meta( $this->object->get_id() , "_billing_house_num", true );

        if ($house_num) {
            echo esc_html($house_num);
        }
    }

    /**
     * Render columm: ocws_order_notes_number.
     */
    public function render_ocws_order_notes_column() {

        $notes = get_post_meta( $this->object->get_id() , "_billing_notes", true );

        if ($notes) {
            echo esc_html($notes);
        }
    }

    public function render_ocws_shipping_company_column() {

        $comp = get_post_meta( $this->object->get_id() , "_ocws_shipping_company_name", true );
        //error_log('render_shipping_company_column');
        if ($comp) {
            echo esc_html($comp);
        }
    }

    public function render_shipping_company_filter() {

        $companies = OC_Woo_Shipping_Companies::get_companies_assoc();

        $selected_option = isset($_GET['ocws_order_company_filter']) && isset( $companies[$_GET['ocws_order_company_filter']] )? $_GET['ocws_order_company_filter'] : '';
        echo '
		<select name="ocws_order_company_filter" id="ocws-order-company-filter">
			<option value="">', __( 'Shipping company', 'ocws' ), '</option>';
        foreach ($companies as $company_id => $company_name) {
            echo '<option value="'.$company_id.'"'. ($selected_option == $company_id? ' selected' : '') .'>'.esc_attr($company_name).'</option>';
        }
        echo '
		</select>';

    }

    public function render_shipping_date_filter() {

        if (!isset($_GET['ocws_order_shipping_date_filter'])) {
            $_GET['ocws_order_shipping_date_filter'] = '';
        }
        $selected_option = isset($_GET['ocws_order_shipping_date_filter']) && in_array( $_GET['ocws_order_shipping_date_filter'], array( 'today', 'from_to' ) )? $_GET['ocws_order_shipping_date_filter'] : '';
        echo '
		<select name="ocws_order_shipping_date_filter" id="ocws-order-shipping-date-filter">
			<option value="">', __( 'Supply date', 'ocws' ), '</option>';
        echo '<option value="today"'. ($selected_option == 'today'? ' selected' : '') .'>'.__('Shipping today', 'ocws').'</option>';
        echo '<option value="from_to"'. ($selected_option == 'from_to'? ' selected' : '') .'>'.__('From...To', 'ocws').'</option>';
        echo '
		</select>';

        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_shipping_date_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_start']);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_shipping_date_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_end']);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        $inputs_style = ($selected_option != 'from_to'? 'display: none' : '');
        ?>

        <input type="text" style="<?php echo $inputs_style ?>" placeholder="<?php _e('From'); ?>" name="ocws_filter_shipping_date_start" id="ocws_filter_shipping_date_start" value="<?php echo esc_attr($filter_date_start); ?>" />
        <input type="text" style="<?php echo $inputs_style ?>" placeholder="<?php _e('To'); ?>" name="ocws_filter_shipping_date_end" id="ocws_filter_shipping_date_end" value="<?php echo esc_attr($filter_date_end); ?>" />

        <style>
            input[name="ocws_filter_shipping_date_start"], input[name="ocws_filter_shipping_date_end"]{
                float: right;
                margin-left: 6px;
            }
            .ocws_export_button, .ocws_export_for_production_button, .ocws_export_for_packaging_button {
                float: right;
                margin-right: 6px;
                margin-left: 6px !important;
            }
        </style>

        <script>
            jQuery( function($) {
                var from = $('input[name="ocws_filter_shipping_date_start"]'),
                    to = $('input[name="ocws_filter_shipping_date_end"]'),
                    selectFilter = $('select[name="ocws_order_shipping_date_filter"]');

                $( 'input[name="ocws_filter_shipping_date_start"], input[name="ocws_filter_shipping_date_end"]' ).datepicker( {dateFormat: "dd/mm/yy", altFormat: "yy/mm/dd"} );
                // by default, the dates look like this "April 3, 2017"

                // the rest part of the script prevents from choosing incorrect date interval
                from.on( 'change', function() {
                    to.datepicker( 'option', 'minDate', from.val() );
                });

                to.on( 'change', function() {
                    from.datepicker( 'option', 'maxDate', to.val() );
                });

                selectFilter.on('change', function () {
                    if ($(this).val() == 'from_to') {
                        from.show();
                        to.show();
                    }
                    else {
                        from.hide();
                        to.hide();
                    }
                });

                $('.ocws_export_button').on('click', function () {

                    var $self = $(this);
                    var data = {};
                    var method_filter_value = $('input[name="ocws_order_shipping_method_filter"]').val();
                    var method = 'all';
                    if (method_filter_value) {
                        method_filter_value = method_filter_value.split(':');
                        method = method_filter_value[0];
                    }

                    if (selectFilter.val() == 'from_to') {
                        data.from = from.val();
                        data.to = to.val();
                        data.type = 'from_to';
                        data.method = method;
                    }
                    else if (selectFilter.val() == 'today') {
                        data.type = 'today';
                        data.from = '';
                        data.to = '';
                        data.method = method;
                    }
                    else {
                        return;
                    }
                    $self.block();
                    $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_export_orders', {
                        from : data.from,
                        to: data.to,
                        type: data.type,
                        method: data.method
                    }, function (response, textStatus) {
                        $self.unblock();
                        if (response.success) {
                            if (response.data && response.data.file) {
                                var file_path = response.data.file.u;
                                var a = document.createElement('A');
                                a.href = file_path;
                                a.download = file_path.substr(file_path.lastIndexOf('/') + 1);
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                            }
                        }
                    }, 'json' );
                });

                $('.ocws_export_for_production_button').on('click', function () {

                    var $self = $(this);
                    var data = {};

                    var method_filter_value = $('input[name="ocws_order_shipping_method_filter"]').val();
                    var method = 'all';
                    if (method_filter_value) {
                        method_filter_value = method_filter_value.split(':');
                        method = method_filter_value[0];
                    }

                    if (selectFilter.val() == 'from_to') {
                        data.from = from.val();
                        data.to = to.val();
                        data.type = 'from_to';
                        data.method = method;
                    }
                    else if (selectFilter.val() == 'today') {
                        data.type = 'today';
                        data.from = '';
                        data.to = '';
                        data.method = method;
                    }
                    else {
                        return;
                    }
                    $self.block();
                    $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_export_orders_for_production', {
                        from : data.from,
                        to: data.to,
                        type: data.type,
                        method: data.method
                    }, function (response, textStatus) {
                        $self.unblock();
                        if (response.success) {
                            if (response.data && response.data.file) {
                                var file_path = response.data.file.u;
                                var a = document.createElement('A');
                                a.href = file_path;
                                a.download = file_path.substr(file_path.lastIndexOf('/') + 1);
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                            }
                        }
                    }, 'json' );
                });

                $('.ocws_export_for_packaging_button').on('click', function () {

                    var $self = $(this);
                    var data = {};

                    var method_filter_value = $('input[name="ocws_order_shipping_method_filter"]').val();
                    var method = 'all';
                    if (method_filter_value) {
                        method_filter_value = method_filter_value.split(':');
                        method = method_filter_value[0];
                    }

                    if (selectFilter.val() == 'from_to') {
                        data.from = from.val();
                        data.to = to.val();
                        data.type = 'from_to';
                        data.method = method;
                    }
                    else if (selectFilter.val() == 'today') {
                        data.type = 'today';
                        data.from = '';
                        data.to = '';
                        data.method = method;
                    }
                    else {
                        return;
                    }
                    $self.block();
                    $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_export_orders_for_packaging', {
                        from : data.from,
                        to: data.to,
                        type: data.type,
                        method: data.method
                    }, function (response, textStatus) {
                        $self.unblock();
                        if (response.success) {
                            if (response.data && response.data.file) {
                                var file_path = response.data.file.u;
                                var a = document.createElement('A');
                                a.href = file_path;
                                a.download = file_path.substr(file_path.lastIndexOf('/') + 1);
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                            }
                        }
                    }, 'json' );
                });

                // sales

            });

        </script>
        <?php
    }

    public function render_group_name_filter() {

        $groups = OC_Woo_Shipping_Groups::get_groups();
        $groups_assoc = array();
        foreach ($groups as $group) {
            $groups_assoc[$group['group_id']] = $group['group_name'];
        }
        $selected_option = isset($_GET['ocws_order_group_name_filter']) && isset( $groups_assoc[$_GET['ocws_order_group_name_filter']] )? $_GET['ocws_order_group_name_filter'] : '';
        echo '
		<select name="ocws_order_group_name_filter" id="ocws-order-group-name-filter">
			<option value="">', __( 'Shipping group', 'ocws' ), '</option>';
        foreach ($groups_assoc as $group_id => $group_name) {
            echo '<option value="'.$group_id.'"'. ($selected_option == $group_id? ' selected' : '') .'>'.esc_attr($group_name).'</option>';
        }
        echo '
		</select>';

    }

    public function render_shipping_city_filter() {

        $selected_option = isset($_GET['ocws_order_shipping_city_filter']) && $_GET['ocws_order_shipping_city_filter'] ? ($_GET['ocws_order_shipping_city_filter']) : '';
        echo '
		<select name="ocws_order_shipping_city_filter" id="ocws-order-shipping-city-filter">
			<option value="">', __( 'Shipping city', 'ocws' ), '</option>';
        OCWS()->locations->city_dropdown_options($selected_option);
        echo '
		</select>';
    }

    public function render_aff_name_filter() {

        $affs_ds = new OCWS_LP_Affiliates();
        $affs_assoc = $affs_ds->get_affiliates_dropdown();
        $selected_option = isset($_GET['ocws_order_affiliate_name_filter']) && isset( $groups_assoc[$_GET['ocws_order_affiliate_name_filter']] )? $_GET['ocws_order_affiliate_name_filter'] : '';
        echo '
		<select name="ocws_order_affiliate_name_filter" id="ocws-order-aff-name-filter">
			<option value="">', __( 'Pickup branch', 'ocws' ), '</option>';
        foreach ($affs_assoc as $aff_id => $aff_name) {
            echo '<option value="'.$aff_id.'"'. ($selected_option == $aff_id? ' selected' : '') .'>'.esc_attr($aff_name).'</option>';
        }
        echo '
		</select>';

    }

    public function render_shipping_method_filter() {

        $exp_types = array();

        $zones = WC_Shipping_Zones::get_zones();
        foreach((array)$zones as $z) {
            foreach($z['shipping_methods'] as $method) {
                $shipping_attr = $method->id.':'.$method->instance_id;
                $exp_types[$shipping_attr] = $z['zone_name'] . ' : ' . $method->title;
            }
        }

        if (!isset($_GET['ocws_order_shipping_method_filter']) || empty($_GET['ocws_order_shipping_method_filter'])) {

            foreach ($exp_types as $key => $title) {
                $methodId = substr($key, 0, strlen('oc_woo_advanced_shipping_method'));
                if ($methodId == 'oc_woo_advanced_shipping_method') {
                    $_GET['ocws_order_shipping_method_filter'] = $key;
                    break;
                }
            }
        }

        $selected_option = isset($_GET['ocws_order_shipping_method_filter']) && $_GET['ocws_order_shipping_method_filter'] ? $_GET['ocws_order_shipping_method_filter'] : '';

        echo '
		<select name="ocws_order_shipping_method_filter" id="ocws-order-shipping-method-filter">
			<option value="">', __( 'Shipping method', 'ocws' ), '</option>';
        foreach ($exp_types as $key => $title) {
            printf
            (
                '<option value="%s"%s>%s</option>',
                esc_attr($key),
                $key == $selected_option? ' selected="selected"':'',
                esc_html($title)
            );
        }
        echo '
		</select>';
    }

    public function action_orders_filter_dropdown($post_type) {

        if('shop_order' != $post_type){
            return;
        }
        //$this->render_mode_buttons();

        $this->render_shipping_date_filter();
        $this->render_group_name_filter();
        $this->render_shipping_city_filter();
        $this->render_aff_name_filter();

        //$this->render_shipping_method_filter();

        if (OC_WOO_USE_COMPANIES) {
            $this->render_shipping_company_filter();
        }
    }

    public function action_display_export_buttons() {

        ?>

        <input type="button" name="ocws_export_action" id="ocws-export-action" class="ocws_export_button button" value="<?php echo esc_attr(__('Export', 'ocws')); ?>">
        <input type="button" name="ocws_export_for_production_action" id="ocws-export-for-production-action" class="ocws_export_for_production_button button" value="<?php echo esc_attr(__('Export for production', 'ocws')); ?>">
        <input type="button" name="ocws_export_for_packaging_action" id="ocws-export-for-packaging-action" class="ocws_export_for_packaging_button button" value="<?php echo esc_attr(__('Export for packaging', 'ocws')); ?>">
        <input type="button" name="ocws_export_sales_report_action" id="ocws-export-sales-report-action" class="ocws_export_sales_report_button button" value="<?php echo esc_attr(__('Export sales report', 'ocws')); ?>">

        <?php
    }

    public function render_order_date_filter($post_type) {

        if('shop_order' != $post_type){
            return;
        }
        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_order_date_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_start']);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_order_date_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_end']);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        ?>

        <input type="text" style="" placeholder="<?php _e('From'); ?>" name="ocws_filter_order_date_start" id="ocws_filter_order_date_start" value="<?php echo esc_attr($filter_date_start); ?>" />
        <input type="text" style="" placeholder="<?php _e('To'); ?>" name="ocws_filter_order_date_end" id="ocws_filter_order_date_end" value="<?php echo esc_attr($filter_date_end); ?>" />

        <style>
            input[name="ocws_filter_order_date_start"], input[name="ocws_filter_order_date_end"]{
                float: right;
                margin-left: 6px;
            }
            .ocws_export_sales_report_button {
                float: right;
                margin-right: 6px;
                margin-left: 6px !important;
            }
        </style>

        <script>
            jQuery( function($) {
                var from = $('input[name="ocws_filter_order_date_start"]'),
                    to = $('input[name="ocws_filter_order_date_end"]');

                $( 'input[name="ocws_filter_order_date_start"], input[name="ocws_filter_order_date_end"]' ).datepicker( {dateFormat: "dd/mm/yy", altFormat: "yy/mm/dd"} );
                // by default, the dates look like this "April 3, 2017"

                // the rest part of the script prevents from choosing incorrect date interval
                from.on( 'change', function() {
                    to.datepicker( 'option', 'minDate', from.val() );
                });

                to.on( 'change', function() {
                    from.datepicker( 'option', 'maxDate', to.val() );
                });
            });

        </script>
        <?php
    }

    public function render_order_completed_date_filter($post_type) {

        if('shop_order' != $post_type){
            return;
        }
        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_order_cdate_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_start']);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_order_cdate_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_end']);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        ?>

        <input type="text" style="" placeholder="<?php _e('From'); ?>" name="ocws_filter_order_cdate_start" id="ocws_filter_order_cdate_start" value="<?php echo esc_attr($filter_date_start); ?>" />
        <input type="text" style="" placeholder="<?php _e('To'); ?>" name="ocws_filter_order_cdate_end" id="ocws_filter_order_cdate_end" value="<?php echo esc_attr($filter_date_end); ?>" />

        <style>
            input[name="ocws_filter_order_cdate_start"], input[name="ocws_filter_order_cdate_end"]{
                float: right;
                margin-left: 6px;
            }
            .ocws_export_sales_report_button {
                float: right;
                margin-right: 6px;
                margin-left: 6px !important;
            }
        </style>

        <script>
            jQuery( function($) {
                var from = $('input[name="ocws_filter_order_cdate_start"]'),
                    to = $('input[name="ocws_filter_order_cdate_end"]');

                $( 'input[name="ocws_filter_order_cdate_start"], input[name="ocws_filter_order_cdate_end"]' ).datepicker( {dateFormat: "dd/mm/yy", altFormat: "yy/mm/dd"} );
                // by default, the dates look like this "April 3, 2017"

                // the rest part of the script prevents from choosing incorrect date interval
                from.on( 'change', function() {
                    to.datepicker( 'option', 'minDate', from.val() );
                });

                to.on( 'change', function() {
                    from.datepicker( 'option', 'maxDate', to.val() );
                });
            });

        </script>
        <?php
    }

    public function render_mode_buttons() {

        ?>

        <script>
            jQuery( function($) {
                var methodFilter = $('select[name="ocws_order_method"]');

                methodFilter.on( 'change', function() {
                    var href = $(this).find('option:selected').data('href');
                    if (href) {
                        window.location = href;
                    }
                });

            });
        </script>

        <?php

        $exp_types = array();

        $zones = WC_Shipping_Zones::get_zones();
        foreach((array)$zones as $z) {
            foreach($z['shipping_methods'] as $method) {

                if (in_array($method->id, array('oc_woo_advanced_shipping_method', 'oc_woo_local_pickup_method'))) {
                    $shipping_attr = $method->id.':'.$method->instance_id;
                    $exp_types[$shipping_attr] = $z['zone_name'] . ' : ' . $method->title;
                }
            }
        }

        $selected_option = isset($_GET['ocws_order_shipping_method_filter']) && $_GET['ocws_order_shipping_method_filter'] ? $_GET['ocws_order_shipping_method_filter'] : 'all';

        ?>

        <!--<div class="alignleft actions ocws_mode_buttons" style="">-->

            <input type="hidden" name="ocws_order_shipping_method_filter" value="<?php echo esc_attr($selected_option); ?>">
            <?php
            echo '
            <select name="ocws_order_method" class="ocws-order-shipping-method-filter">
                <option data-href="'. esc_url(admin_url('edit.php?post_type=shop_order&ocws_order_shipping_method_filter=all')) .'" value="all">'. __( 'All shipping methods', 'ocws' ). '</option>';
                foreach ($exp_types as $key => $title) {
                printf
                (
                '<option data-href="'.esc_url(admin_url('edit.php?post_type=shop_order&ocws_order_shipping_method_filter='.urldecode($key))).'" value="%s"%s>%s</option>',
                esc_attr(urldecode($key)),
                $key == $selected_option? ' selected="selected"':'',
                esc_html($title)
                );
                }
                echo '
            </select>';
            ?>
            <!--<a href="<?php /*echo esc_url(admin_url('edit.php?post_type=shop_order&ocws_order_shipping_method_filter=all')) */?>"
               class="ocws_mode_button <?php /*echo ('all' == $selected_option? 'active' : 'not-active') */?>"><?php /*echo esc_html(__('All shipping methods', 'ocws')) */?></a>-->
            <?php /*foreach ($exp_types as $key => $title) { */?><!--
            <a href="<?php /*echo esc_url(admin_url('edit.php?post_type=shop_order&ocws_order_shipping_method_filter='.urldecode($key))) */?>"
               class="ocws_mode_button <?php /*echo ($key == $selected_option? 'active' : 'not-active') */?>"><?php /*echo esc_html($title) */?></a>
            --><?php /*} */?>
        <!--</div>-->

        <style>
            a.ocws_mode_button {
                display: inline-block;
                margin: 5px;
                padding: 5px;
                border: solid 1px #002a80;
                border-radius: 3px;
            }
            a.ocws_mode_button.active {
                display: inline-block;
                margin: 5px;
                padding: 5px;
                border: solid 1px #b80000;
            }
        </style>

        <?php
    }

    private function is_valid_admin_screen() {

        if (function_exists('get_current_screen') && $screen = get_current_screen()) {
            if ($screen->base == 'edit' && $screen->post_type == 'shop_order') {
                return $screen;
            }
        }

        return false;
    }

    private function is_shipping_and_pickup_method_filter_chosen() {

        if (
            isset($_GET['ocws_order_shipping_method_filter']) && !empty($_GET['ocws_order_shipping_method_filter']) && $_GET['ocws_order_shipping_method_filter'] != 'all'
        ) {
            return false;
        }
        return true;
    }

}




