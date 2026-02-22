<?php

defined( 'ABSPATH' ) || exit;

use Carbon\Carbon;

class OCWS_LP_Pickup_Admin_Columns
{

    const COLUMN_NAME_PREFIX = 'ocws_lp_';

    const OCWS_MODE_PICKUP_ORDERS = 'pickup_orders';

    private static $instance;

    private $admin_columns = array();

    private $variables = array();

    /**
     * Object being shown on the row.
     *
     * @var object|null
     */
    protected $object = null;

    protected $tz = '';

    private function __construct() {

        $this->tz = ocws_get_timezone();

        add_action( 'restrict_manage_posts', array($this, 'render_filter_set_by_date'), 3 );
        add_action( 'restrict_manage_posts', array($this, 'render_filter_set_by_shipping_data'), 2 );
        add_action( 'restrict_manage_posts', array($this, 'action_display_export_buttons'), 120 );

        if ($this->is_local_pickup_method_filter_chosen()) {

            $this->init_column_variables();
            add_action('admin_enqueue_scripts', array($this, 'action_enqueue_admin_scripts'));

            // WP admin post index tables ("All posts" screens)
            add_action('parse_request', array($this, 'action_prepare_columns'), 10);

            add_action('pre_get_posts', array($this, 'action_prepare_query_filter'));

            add_filter( 'posts_where', array($this, 'filter_admin_shipping_filter'), 10, 2 );

            //add_action( 'restrict_manage_posts', array($this, 'action_orders_filter_dropdown') );

            //add_action( 'restrict_manage_posts', array($this, 'action_display_export_buttons'), 100 );
        }

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
            'pickup_aff_name' => __('Pickup branch', 'ocws'),
            'pickup_date' => __('Pickup Date', 'ocws'),
            'pickup_time_slot' => __('Pickup time slot', 'ocws'),
            'order_products_count' => __('Products quantity', 'ocws'),
            'customer_phone' => __('Phone', 'ocws'),
            'order_notes' => __('Notes', 'ocws'),
        );

        if (get_option('ocws_common_orders_show_completed_date', '') === '1') {
            $this->variables['completion_date'] = __('Order completion date', 'ocws');
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
                !isset($_GET['ocws_order_shipping_method_filter']) ||
                empty($_GET['ocws_order_shipping_method_filter']) ||
                substr($_GET['ocws_order_shipping_method_filter'], 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) != OCWS_LP_Local_Pickup::PICKUP_METHOD_ID
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
                            array('key' => 'ocws_lp_pickup_date', 'compare' => 'NOT EXISTS'), // 'NOT EXISTS' needs to go first for proper sorting
                            array('key' => 'ocws_lp_pickup_date', 'compare' => 'EXISTS'),
                        ),
                        array(
                            'relation' => 'OR',
                            array('key' => 'ocws_lp_pickup_date_sortable', 'compare' => 'NOT EXISTS'), // 'NOT EXISTS' needs to go first for proper sorting
                            array('key' => 'ocws_lp_pickup_date_sortable', 'compare' => 'EXISTS'),
                        ),
                    );


                    $query->set('meta_query', $meta_query);
                    $query->set('orderby', array('ocws_lp_pickup_date_sortable' => $order, 'ocws_lp_pickup_date' => $order));
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
                isset($_GET['ocws_order_shipping_method_filter']) ||
                empty($_GET['ocws_order_shipping_method_filter']) ||
                substr($_GET['ocws_order_shipping_method_filter'], 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) != OCWS_LP_Local_Pickup::PICKUP_METHOD_ID
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
                                AND im.meta_value = %s
                                AND im2.meta_key = "instance_id"
                                AND im2.meta_value = %d
                            )', array($arr[0], $arr[1]) );
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
    public function action_prepare_query_filter($query)
    {

        if ($this->is_valid_admin_screen() && $query->is_main_query()) {

            if (
                !isset($_GET['ocws_order_shipping_method_filter']) ||
                empty($_GET['ocws_order_shipping_method_filter']) ||
                substr($_GET['ocws_order_shipping_method_filter'], 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) != OCWS_LP_Local_Pickup::PICKUP_METHOD_ID
            ) {
                // not our shipping method
                return $query;
            }


            $filter_date_start = $filter_date_end = '';
            $filter_date_start_sortable = $filter_date_end_sortable = '';
            $filter_aff_id = 0;
            $filter_company_id = 0;
            $filter_posts_by = '';

            if (!isset($_GET['ocws_order_shipping_date_filter'])) {
                $_GET['ocws_order_shipping_date_filter'] = '';
            }

            if (isset($_GET['ocws_order_shipping_date_filter']) && $_GET['ocws_order_shipping_date_filter']) {

                if ($_GET['ocws_order_shipping_date_filter'] == 'from_to') {

                    if(isset($_GET['ocws_filter_shipping_date_start'])) {
                        try {
                            $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_start'], $this->tz);
                            $filter_date_start = $dt->format('d/m/Y');
                            $filter_date_start_sortable = $dt->format('Y/m/d');
                        } catch (InvalidArgumentException $e) {
                        }
                    }
                    if(isset($_GET['ocws_filter_shipping_date_end'])) {
                        try {
                            $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_end'], $this->tz);
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
            if (isset($_GET['ocws_order_affiliate_name_filter']) && $_GET['ocws_order_affiliate_name_filter']) {
                $filter_aff_id = intval($_GET['ocws_order_affiliate_name_filter']);
            }
            if (isset($_GET['ocws_order_company_filter']) && $_GET['ocws_order_company_filter']) {
                $filter_company_id = intval($_GET['ocws_order_company_filter']);
            }

            $main_meta = array();
            $main_meta[] = array(
                'key'     => 'ocws_shipping_tag',
                'value'   => OCWS_LP_Local_Pickup::PICKUP_METHOD_TAG,
                'compare' => '='
            );

            if ($filter_posts_by == 'today') {
                $today_date = Carbon::now($this->tz);
                $date_to_compare = $today_date->format('d/m/Y');
                $main_meta[] = array(
                    'key'     => 'ocws_lp_pickup_date',
                    'value'   => $date_to_compare,
                    'compare' => '='
                );
                $main_meta[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'ocws_lp_pickup_slot_start',
                        'compare' => 'NOT EXISTS'
                    ),
                    'slot_start_clause' => array(
                        'key'     => 'ocws_lp_pickup_slot_start',
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
                //$query->set('orderby', array('pickup_aff_clause' => 'ASC', 'slot_start_clause' => 'ASC'));

                if ($query->query_vars && isset($query->query_vars['orderby']) && $query->query_vars['orderby'] == 'ocws_shipping_date') {

                    $order = isset($query->query_vars['order'])? $query->query_vars['order'] : 'ASC';
                    $query->set('orderby', array('date_sortable_clause' => $order, 'pickup_aff_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
                else {

                    //$query->set('orderby', array('date_sortable_clause' => 'ASC', 'pickup_aff_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
            }
            else if ($filter_posts_by == 'from_to') {
                if ($filter_date_start && $filter_date_end) {
                    $main_meta['date_sortable_clause'] = array(
                        'key'     => 'ocws_lp_pickup_date_sortable',
                        'compare' => 'EXISTS'
                    );
                }
                if ($filter_date_start) {
                    $main_meta[] = array(
                        'key'     => 'ocws_lp_pickup_date_sortable',
                        'value'   => $filter_date_start_sortable,
                        'compare' => '>='
                    );
                }
                if ($filter_date_end) {
                    $main_meta[] = array(
                        'key'     => 'ocws_lp_pickup_date_sortable',
                        'value'   => $filter_date_end_sortable,
                        'compare' => '<='
                    );
                }
                $main_meta[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'ocws_lp_pickup_slot_start',
                        'compare' => 'NOT EXISTS'
                    ),
                    'slot_start_clause' => array(
                        'key'     => 'ocws_lp_pickup_slot_start',
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
                    $query->set('orderby', array('date_sortable_clause' => $order, 'pickup_aff_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
                else {

                    //$query->set('orderby', array('date_sortable_clause' => 'ASC', 'pickup_aff_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
            }
            else {
                $main_meta['date_sortable_clause'] = array(
                    'key'     => 'ocws_lp_pickup_date_sortable',
                    'compare' => 'EXISTS'
                );
                $main_meta[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'ocws_lp_pickup_slot_start',
                        'compare' => 'NOT EXISTS'
                    ),
                    'slot_start_clause' => array(
                        'key'     => 'ocws_lp_pickup_slot_start',
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
                    $query->set('orderby', array('date_sortable_clause' => $order, 'pickup_aff_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
                else {

                    //$query->set('orderby', array('date_sortable_clause' => 'ASC', 'pickup_aff_clause' => 'ASC', 'slot_start_clause' => 'ASC'));
                }
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

        //error_log($query->request);
        return $query;
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
            !isset($_GET['ocws_order_shipping_method_filter']) ||
            empty($_GET['ocws_order_shipping_method_filter']) ||
            substr($_GET['ocws_order_shipping_method_filter'], 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) != OCWS_LP_Local_Pickup::PICKUP_METHOD_ID
        ) {
            // not our shipping method
            return $columns;
        }
        foreach ($this->admin_columns as $key => $col) {
            if ($key == 'ocws_lp_pickup_date') {
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
    public function render_ocws_lp_customer_full_name_column() {

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
     * Render columm: ocws_shipping_group_name.
     */
    public function render_ocws_lp_pickup_aff_name_column() {

        $aff_name = '';

        // TODO: continue edit from here, ***be sure not to process columns if $_GET['ocws_order_shipping_method_filter'] is empty

        $shipping_item = OCWS_LP_Pickup_Info::get_shipping_item( $this->object );

        if ( $shipping_item ) {

            $pickup_info = $shipping_item->get_meta('ocws_lp_pickup_info');
            if ($pickup_info) {

                $pickup_info = unserialize($pickup_info);

                if (isset( $pickup_info['aff_name'] )) {
                    $aff_name = $pickup_info['aff_name'];
                }
            }
        }

        echo esc_html( $aff_name );
    }

    /**
     * Render columm: ocws_shipping_date.
     */
    public function render_ocws_lp_pickup_date_column() {

        $date = '';

        $shipping_item = OCWS_LP_Pickup_Info::get_shipping_item( $this->object );

        if ( $shipping_item ) {

            $pickup_info = $shipping_item->get_meta('ocws_lp_pickup_info');
            if ($pickup_info) {

                $pickup_info = unserialize($pickup_info);

                if (isset( $pickup_info['date'] )) {
                    $date = $pickup_info['date'];
                }
            }
        }

        echo '<strong>' . esc_html( $date ) . '</strong>';
    }

    /**
     * Render columm: ocws_shipping_time_slot.
     */
    public function render_ocws_lp_pickup_time_slot_column() {

        $slot = '';

        $shipping_item = OCWS_LP_Pickup_Info::get_shipping_item( $this->object );

        if ( $shipping_item ) {

            $pickup_info = $shipping_item->get_meta('ocws_shipping_info');
            if ($pickup_info) {

                $pickup_info = unserialize($pickup_info);

                if (isset( $pickup_info['slot_start'] ) && isset( $pickup_info['slot_end'] )) {
                    $slot = sprintf(
                        '%s - %s',
                        $pickup_info['slot_start'],
                        $pickup_info['slot_end']
                    );
                }
            }
        }

        echo '<strong>' . esc_html( $slot ) . '</strong>';
    }

    /**
     * Render columm: ocws_order_products_count.
     */
    public function render_ocws_lp_order_products_count_column() {

        $count = '';

        $items = $this->object->get_items();

        if ( $items ) {

            $count = count( $items );
        }

        echo esc_html( $count );
    }

    /**
     * Render columm: ocws_order_notes_number.
     */
    public function render_ocws_lp_order_notes_column() {

        $notes = get_post_meta( $this->object->get_id() , "_billing_notes", true );

        if ($notes) {
            echo esc_html($notes);
        }
    }

    public function render_ocws_completion_date_column() {

        $date = get_post_meta( $this->object->get_id() , "_completed_date", true );
        if ($date) {
            echo esc_html($date);
        }
    }
    /* affiliate filter */
    public function render_aff_name_filter() {

        $affs_ds = new OCWS_LP_Affiliates();
        $affs_assoc = $affs_ds->get_affiliates_dropdown();
        $selected_option = isset($_GET['ocws_order_affiliate_name_filter']) && isset( $groups_assoc[$_GET['ocws_order_affiliate_name_filter']] )? $_GET['ocws_order_affiliate_name_filter'] : '';
        echo '<div><label for="ocws-order-aff-name-filter">'.__( 'Pickup branch', 'ocws' ).'</label>
		<select name="ocws_order_affiliate_name_filter" id="ocws-order-aff-name-filter">
			<option value="">', __( 'Pickup branch', 'ocws' ), '</option>';
        foreach ($affs_assoc as $aff_id => $aff_name) {
            echo '<option value="'.$aff_id.'"'. ($selected_option == $aff_id? ' selected' : '') .'>'.esc_attr($aff_name).'</option>';
        }
        echo '
		</select></div>';

    }

    public function render_pickup_date_filter() {

        if (!isset($_GET['ocws_order_shipping_date_filter'])) {
            $_GET['ocws_order_shipping_date_filter'] = '';
        }
        $selected_option = isset($_GET['ocws_order_shipping_date_filter']) && in_array( $_GET['ocws_order_shipping_date_filter'], array( 'today', 'from_to' ) )? $_GET['ocws_order_shipping_date_filter'] : '';
        echo '
        <div><label for="ocws-order-shipping-date-filter">'.__( 'Supply date', 'ocws' ).'</label>
		<select name="ocws_order_shipping_date_filter" id="ocws-order-shipping-date-filter">
			<option value="">', __( 'Pickup date', 'ocws' ), '</option>';
        echo '<option value="today"'. ($selected_option == 'today'? ' selected' : '') .'>'.__('Pickup today', 'ocws').'</option>';
        echo '<option value="from_to"'. ($selected_option == 'from_to'? ' selected' : '') .'>'.__('From...To', 'ocws').'</option>';
        echo '
		</select></div>';

        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_shipping_date_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_start'], $this->tz);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_shipping_date_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_end'], $this->tz);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        $inputs_style = ($selected_option != 'from_to'? 'display: none' : '');
        ?>

        <div style="<?php echo $inputs_style ?>">
            <label for="ocws_filter_shipping_date_start"><?php _e('Start shipping date', 'ocws'); ?></label>
            <input type="text" placeholder="<?php _e('Start shipping date', 'ocws'); ?>" name="ocws_filter_shipping_date_start" id="ocws_filter_shipping_date_start" value="<?php echo esc_attr($filter_date_start); ?>" />
        </div>
        <div style="<?php echo $inputs_style ?>">
            <label for="ocws_filter_shipping_date_end"><?php _e('End shipping date', 'ocws'); ?></label>
            <input type="text" placeholder="<?php _e('End shipping date', 'ocws'); ?>" name="ocws_filter_shipping_date_end" id="ocws_filter_shipping_date_end" value="<?php echo esc_attr($filter_date_end); ?>" />
        </div>

        <style>

        </style>

        <script>
            jQuery( function($) {

            });

        </script>
        <?php
    }

    public function render_shipping_date_filter_for_modal() {

        if (!isset($_GET['ocws_order_shipping_date_filter'])) {
            $_GET['ocws_order_shipping_date_filter'] = '';
        }
        $selected_option = isset($_GET['ocws_order_shipping_date_filter']) && in_array( $_GET['ocws_order_shipping_date_filter'], array( 'today', 'from_to' ) )? $_GET['ocws_order_shipping_date_filter'] : '';
        echo '
        <div><label for="ocws-order-shipping-date-filter">'.__( 'Supply date', 'ocws' ).'</label>
		<select name="ocws_order_shipping_date_filter_modal" id="ocws-order-shipping-date-filter-modal">
			<option value="">', __( 'Supply date', 'ocws' ), '</option>';
        echo '<option value="today"'. ($selected_option == 'today'? ' selected' : '') .'>'.__('Shipping today', 'ocws').'</option>';
        echo '<option value="from_to"'. ($selected_option == 'from_to'? ' selected' : '') .'>'.__('From...To', 'ocws').'</option>';
        echo '
		</select></div>';

        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_shipping_date_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_start'], $this->tz);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_shipping_date_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_shipping_date_end'], $this->tz);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        $inputs_style = ($selected_option != 'from_to'? 'display: none' : '');
        ?>

        <div class="dates-fields">
        <div style="<?php echo $inputs_style ?>">
            <label for="ocws_filter_shipping_date_start_modal"><?php _e('Start shipping date', 'ocws'); ?></label>
            <input type="text" placeholder="<?php _e('Start shipping date', 'ocws'); ?>" name="ocws_filter_shipping_date_start_modal" id="ocws_filter_shipping_date_start_modal" value="<?php echo esc_attr($filter_date_start); ?>" />
        </div>
        <div style="<?php echo $inputs_style ?>">
            <label for="ocws_filter_shipping_date_end_modal"><?php _e('End shipping date', 'ocws'); ?></label>
            <input type="text" placeholder="<?php _e('End shipping date', 'ocws'); ?>" name="ocws_filter_shipping_date_end_modal" id="ocws_filter_shipping_date_end_modal" value="<?php echo esc_attr($filter_date_end); ?>" />
        </div>
        </div>
        <?php
    }

    public function action_orders_filter_dropdown($post_type) {

        if('shop_order' != $post_type){
            return;
        }
        $this->render_pickup_date_filter();
        $this->render_aff_name_filter();
    }

    public function action_display_export_buttons($post_type) {

        if('shop_order' != $post_type || !$this->is_local_pickup_method_filter_chosen()) {
            return;
        }

        ?>

        <div class="ocwsbox">
            <div class="ocwsbox-header"><?php _e('Export', 'ocws') ?></div>
            <div class="ocwsbox-inside ocwsbox-buttons">
                <input type="button" name="ocws_export_action" id="ocws-export-action"
                       class="ocws_export_button button thickbox"
                       alt="#TB_inline?height=220&width=400&inlineId=ocws-export-action-modal"
                       title="<?php echo esc_attr(__('Export orders', 'ocws')); ?>"
                       value="<?php echo esc_attr(__('Export', 'ocws')); ?>">
                <input type="button"
                       name="ocws_export_for_production_action"
                       id="ocws-export-for-production-action"
                       class="ocws_export_for_production_button button thickbox"
                       alt="#TB_inline?height=220&width=400&inlineId=ocws-export-action-modal"
                       title="<?php echo esc_attr(__('Export orders for production', 'ocws')); ?>"
                       value="<?php echo esc_attr(__('Export for production', 'ocws')); ?>">
                <input type="button" name="ocws_export_for_packaging_action"
                       id="ocws-export-for-packaging-action"
                       class="ocws_export_for_packaging_button button thickbox"
                       alt="#TB_inline?height=220&width=400&inlineId=ocws-export-action-modal"
                       title="<?php echo esc_attr(__('Export orders for packaging', 'ocws')); ?>"
                       value="<?php echo esc_attr(__('Export for packaging', 'ocws')); ?>">
                <input type="button" name="ocws_export_sales_report_action"
                       id="ocws-export-sales-report-action"
                       class="ocws_export_sales_report_button button thickbox"
                       alt="#TB_inline?height=320&width=400&inlineId=ocws-export-sales-report-action-modal"
                       title="<?php echo esc_attr(__('Export sales report', 'ocws')); ?>"
                       value="<?php echo esc_attr(__('Export sales report', 'ocws')); ?>">
                <input type="button" name="ocws_export_orders_report_action"
                       id="ocws-export-orders-report-action"
                       class="ocws_export_orders_report_button button thickbox"
                       alt="#TB_inline?height=320&width=400&inlineId=ocws-export-sales-report-action-modal"
                       title="<?php echo esc_attr(__('Export orders report', 'ocws')); ?>"
                       value="<?php echo esc_attr(__('Export orders report', 'ocws')); ?>">
            </div>
        </div>

        <?php
        $this->export_preview_template();
    }

    public function render_filter_set_by_date($post_type) {

        if('shop_order' != $post_type || !$this->is_local_pickup_method_filter_chosen()) {
            return;
        }

        ?>
        <div class="ocwsbox">
            <div class="ocwsbox-header"><?php _e('Filter by dates', 'ocws') ?></div>
            <div class="ocwsbox-inside">
                <div class="ocwsbox-inside-part"><?php $this->render_order_date_filter($post_type); ?></div>
                <div class="ocwsbox-inside-part"><?php $this->render_order_completed_date_filter($post_type); ?></div>
                <div class="ocwsbox-inside-part"><?php $this->render_pickup_date_filter(); ?></div>
            </div>
        </div>

        <style>
            .ocwsbox {
                display: block;
                float: left;
                margin-left: 6px;
                margin-right: 6px;
                background-color: #ffffff;
                border: solid 1px #c3c4c7;
                padding-bottom: 5px;
            }
            .ocwsbox-header {
                padding: 3px 6px 3px 6px;
                font-weight: bold;
            }
            body.rtl .ocwsbox {
                float: right;
            }
            #posts-filter .actions, #posts-filter .actions select, #posts-filter .actions input, #posts-filter .actions .ocwsbox {
                margin-bottom: 5px;
            }
            #posts-filter .actions #filter-by-date {
                display: none;
            }
        </style>
        <?php
    }

    public function render_filter_set_by_shipping_data($post_type) {

        if('shop_order' != $post_type || !$this->is_local_pickup_method_filter_chosen()) {
            return;
        }

        ?>
        <div class="ocwsbox">
            <div class="ocwsbox-header"><?php _e('Filter by shipping data', 'ocws') ?></div>
            <div class="ocwsbox-inside">
                <div class="ocwsbox-inside-part"><?php $this->render_mode_buttons(); ?></div>
                <div class="ocwsbox-inside-part"><?php $this->render_aff_name_filter(); ?></div>
            </div>
        </div>
        <?php
    }

    public function render_order_date_filter($post_type) {

        if('shop_order' != $post_type){
            return;
        }
        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_order_date_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_start'], $this->tz);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_order_date_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_end'], $this->tz);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        ?>

        <div>
            <label for="ocws_filter_order_date_start"><?php _e('Start order date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('Start order date', 'ocws'); ?>" name="ocws_filter_order_date_start" id="ocws_filter_order_date_start" value="<?php echo esc_attr($filter_date_start); ?>" />
        </div>
        <div>
            <label for="ocws_filter_order_date_end"><?php _e('End order date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('End order date', 'ocws'); ?>" name="ocws_filter_order_date_end" id="ocws_filter_order_date_end" value="<?php echo esc_attr($filter_date_end); ?>" />
        </div>

        <style>

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

    public function render_order_date_filter_for_modal() {


        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_order_date_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_start'], $this->tz);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_order_date_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_date_end'], $this->tz);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        ?>

        <div>
            <label for="ocws_filter_order_date_start_modal"><?php _e('Start order date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('Start order date', 'ocws'); ?>" name="ocws_filter_order_date_start_modal" id="ocws_filter_order_date_start_modal" value="<?php echo esc_attr($filter_date_start); ?>" />
        </div>
        <div>
            <label for="ocws_filter_order_date_end_modal"><?php _e('End order date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('End order date', 'ocws'); ?>" name="ocws_filter_order_date_end_modal" id="ocws_filter_order_date_end_modal" value="<?php echo esc_attr($filter_date_end); ?>" />
        </div>

        <style>

        </style>

        <script>
            jQuery( function($) {
                var from = $('input[name="ocws_filter_order_date_start_modal"]'),
                    to = $('input[name="ocws_filter_order_date_end_modal"]');

                $( 'input[name="ocws_filter_order_date_start_modal"], input[name="ocws_filter_order_date_end_modal"]' ).datepicker( {dateFormat: "dd/mm/yy", altFormat: "yy/mm/dd"} );
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
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_start'], $this->tz);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_order_cdate_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_end'], $this->tz);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        ?>

        <div>
            <label for="ocws_filter_order_cdate_start"><?php _e('Start completed date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('Start completed date', 'ocws'); ?>" name="ocws_filter_order_cdate_start" id="ocws_filter_order_cdate_start" value="<?php echo esc_attr($filter_date_start); ?>" />
        </div>
        <div>
            <label for="ocws_filter_order_cdate_end"><?php _e('End completed date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('End completed date', 'ocws'); ?>" name="ocws_filter_order_cdate_end" id="ocws_filter_order_cdate_end" value="<?php echo esc_attr($filter_date_end); ?>" />
        </div>

        <style>

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

    public function render_order_completed_date_filter_for_modal() {

        $filter_date_start = $filter_date_end = '';
        if(isset($_GET['ocws_filter_order_cdate_start'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_start'], $this->tz);
                $filter_date_start = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }
        if(isset($_GET['ocws_filter_order_cdate_end'])) {
            try {
                $dt = Carbon::createFromFormat('d/m/Y', $_GET['ocws_filter_order_cdate_end'], $this->tz);
                $filter_date_end = $dt->format('d/m/Y');
            } catch (InvalidArgumentException $e) {
            }
        }

        ?>

        <div>
            <label for="ocws_filter_order_cdate_start_modal"><?php _e('Start completed date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('Start completed date', 'ocws'); ?>" name="ocws_filter_order_cdate_start_modal" id="ocws_filter_order_cdate_start_modal" value="<?php echo esc_attr($filter_date_start); ?>" />
        </div>
        <div>
            <label for="ocws_filter_order_cdate_start_modal"><?php _e('End completed date', 'ocws'); ?></label>
        <input type="text" style="" placeholder="<?php _e('End completed date', 'ocws'); ?>" name="ocws_filter_order_cdate_end_modal" id="ocws_filter_order_cdate_end_modal" value="<?php echo esc_attr($filter_date_end); ?>" />
        </div>

        <style>

        </style>

        <script>
            jQuery( function($) {
                var from = $('input[name="ocws_filter_order_cdate_start_modal"]'),
                    to = $('input[name="ocws_filter_order_cdate_end_modal"]');

                $( 'input[name="ocws_filter_order_cdate_start_modal"], input[name="ocws_filter_order_cdate_end_modal"]' ).datepicker( {dateFormat: "dd/mm/yy", altFormat: "yy/mm/dd"} );
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
        echo '<div><label>'.__('Shipping method', 'ocws').'</label>
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
            </select></div>';
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

    public function render_shipping_method_filter_for_modal() {

        ?>

        <script>
            jQuery( function($) {

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

        <input type="hidden" name="ocws_order_shipping_method_filter_modal" value="<?php echo esc_attr($selected_option); ?>">
        <?php
        echo '
            <div><label for="ocws_order_method_modal">'.__('Shipping method', 'ocws').'</label>
            <select id="ocws_order_method_modal" name="ocws_order_method_modal" class="ocws-order-shipping-method-filter-modal">
                <option value="all">'. __( 'All shipping methods', 'ocws' ). '</option>';
        foreach ($exp_types as $key => $title) {
            printf
            (
                '<option value="%s"%s>%s</option>',
                esc_attr(urldecode($key)),
                $key == $selected_option? ' selected="selected"':'',
                esc_html($title)
            );
        }
        echo '
            </select></div>';
        ?>

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

    private function is_local_pickup_method_filter_chosen() {

        if (
            isset($_GET['ocws_order_shipping_method_filter']) &&
            substr($_GET['ocws_order_shipping_method_filter'], 0, strlen(OCWS_LP_Local_Pickup::PICKUP_METHOD_ID)) == OCWS_LP_Local_Pickup::PICKUP_METHOD_ID
        ) {
            return true;
        }
        return false;
    }

    public function export_preview_template() {
        ?>
        <div class="ocwsbox-modal" id="ocws-export-action-modal" style="display: none;">
            <div class="ocwsbox-modal-inside" id="ocwsbox-export-modal-inside">
                <div class="ocwsbox-modal-inside-part"><?php $this->render_shipping_date_filter_for_modal(); ?></div>
                <div class="ocwsbox-modal-inside-part"><?php $this->render_shipping_method_filter_for_modal(); ?></div>
                <div class="ocwsbox-modal-inside-part ocwsbox-actions">
                    <div class="loader-container">
                        <div class="loader">
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                        </div>
                    </div>
                    <input type="button" name="ocws_export_action_modal" id="ocws-export-action-modal-submit"
                           class="ocws_export_button_modal button"
                           value="<?php echo esc_attr(__('Export orders shipping data', 'ocws')); ?>">
                    <input type="button" name="ocws_export_for_production_action_modal" id="ocws-export-for-production-action-modal-submit"
                           class="ocws_export_for_production_button_modal button"
                           value="<?php echo esc_attr(__('Export shipping data for production', 'ocws')); ?>">
                    <input type="button" name="ocws_export_for_packaging_action_modal" id="ocws-export-for-packaging-action-modal-submit"
                           class="ocws_export_for_packaging_button_modal button"
                           value="<?php echo esc_attr(__('Export shipping data for packaging', 'ocws')); ?>">
            </div>
        </div>
        </div>
        <div class="ocwsbox-modal" id="ocws-export-sales-report-action-modal" style="display: none;">
            <div class="ocwsbox-modal-inside" id="ocwsbox-export-sales-modal-inside">
                <div class="ocwsbox-modal-inside-part"><?php $this->render_order_date_filter_for_modal(); ?></div>
                <div class="ocwsbox-modal-inside-part"><?php $this->render_order_completed_date_filter_for_modal(); ?></div>
                <div class="ocwsbox-modal-inside-part"><?php $this->render_shipping_method_filter_for_modal(); ?></div>
                <div class="ocwsbox-modal-inside-part ocwsbox-actions">
                    <div class="loader-container">
                        <div class="loader">
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                            <div class="loader--dot"></div>
                        </div>
                    </div>
                    <input type="button" name="ocws_export_sales_report_action_modal" id="ocws-export-sales-report-action-modal-submit"
                           class="ocws_export_sales_report_button_modal button"
                           value="<?php echo esc_attr(__('Export sales report by products', 'ocws')); ?>">
                    <input type="button" name="ocws_export_orders_report_action_modal" id="ocws-export-orders-report-action-modal-submit"
                           class="ocws_export_orders_report_button_modal button"
                           value="<?php echo esc_attr(__('Export orders report', 'ocws')); ?>">
                </div>
            </div>
        </div>
        <?php
    }

}




