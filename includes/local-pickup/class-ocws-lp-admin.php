<?php

defined( 'ABSPATH' ) || exit;

class OCWS_LP_Admin {

    public static function init() {

        /*add_action( 'admin_menu', array( $plugin_admin, 'admin_menu' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_settings' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_options_hooks' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_product_hooks' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_admin_order_columns' ) );*/

        self::init_menu();
        add_action( 'admin_init', array( 'OCWS_LP_Admin', 'init_settings' ) );
        add_action( 'admin_init', array( 'OCWS_LP_Admin', 'setup_options_hooks' ) );
        add_action( 'admin_init', array( 'OCWS_LP_Admin', 'setup_admin_order_columns' ) );
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( 'OCWS_LP_Admin', 'admin_render_pickup_info' ), 10, 3 );
        add_filter( 'woocommerce_admin_shipping_fields', array( 'OCWS_LP_Admin', 'admin_hide_address_fields_for_pickup' ), 20, 1 );
        add_filter( 'woocommerce_admin_billing_fields', array( 'OCWS_LP_Admin', 'admin_hide_address_fields_for_pickup' ), 20, 1 );
        add_action( 'woocommerce_saved_order_items', array( 'OCWS_LP_Admin', 'woocommerce_saved_order_items_action' ), 10, 2);
        add_filter( 'woocommerce_order_formatted_billing_address', array( 'OCWS_LP_Admin', 'woocommerce_order_formatted_billing_address' ), 100, 2 );
        add_filter( 'woocommerce_order_formatted_shipping_address', array( 'OCWS_LP_Admin', 'woocommerce_order_formatted_shipping_address' ), 100, 2 );
    }

    public static function init_menu() {

        add_action( 'admin_menu', array( 'OCWS_LP_Admin', 'admin_menu' ) );
    }

    public static function admin_menu() {

        $menu_title = __( 'OC Local Pickup', 'ocws' );
        add_submenu_page( 'woocommerce', __( 'OC Local Pickup', 'ocws' ), $menu_title, 'manage_woocommerce', 'ocws-lp', array( 'OCWS_LP_Admin', 'admin_page' ) );
    }

    public static function admin_page() {

        $affiliates_ds = new OCWS_LP_Affiliates();
        $affiliates = $affiliates_ds->get_affiliates();

        $tabs = array(
            'affiliates' => array(
                'title' => __( 'Local Pickup Branches', 'ocws' )
            ),
            'default-affiliate' => array(
                'title' => __( 'Default Branch Settings', 'ocws' )
            ),
            'common-settings' => array(
                'title' => __( 'General settings', 'ocws' )
            ),
        );

        foreach ($affiliates as $aff) {
            $tabs['affiliate'.$aff['aff_id']] = array(
                'title' => $aff['aff_name']
            );
        }

        $first_tab      = array_keys( $tabs );
        $current_tab    = ! empty( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? sanitize_title( $_GET['tab'] ) : $first_tab[0];

        ?>
        <div class="wrap woocommerce">
            <h1><?php echo esc_html(__('OC Local Pickup', 'ocws')) ?></h1>
            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <?php
                foreach ( $tabs as $key => $tab ) {
                    echo '<a href="' . admin_url( 'admin.php?page=ocws-lp&tab=' . urlencode( $key ) ) . '" class="nav-tab ';
                    if ( $current_tab == $key ) {
                        echo 'nav-tab-active';
                    }
                    echo '">' . esc_html( $tab['title'] ) . '</a>';
                }
                ?>
            </nav>
            <h1 class="screen-reader-text"><?php echo esc_html( $tabs[ $current_tab ] ); ?></h1>
            <?php
            if( $current_tab == 'affiliates' ) {
                //OC_Woo_Shipping_Admin_Groups::output();
                self::output_affiliates_tab($affiliates);
            }
            else if ( substr( $current_tab, 0, 9 ) == 'affiliate' ) {

                $aff_id = intval(substr( $current_tab, 9 ));
                //OC_Woo_Shipping_Admin_Groups::output($group_id);
                self::output_affiliate_tab($aff_id);
            }
            else if ( $current_tab == 'default-affiliate' ) {
                self::output_default_affiliate_settings();
            }
            else if ( $current_tab == 'common-settings') {
                //OC_Woo_Shipping_Admin_Groups::output_common_settings();
                self::output_common_settings();
            }
            ?>
        </div>
        <?php
    }

    public static function output_affiliates_tab($affiliates) {

        global $hide_save_button;
        $hide_save_button = true;

        wp_localize_script(
            'ocws_lp_affiliates',
            'lpAffiliatesLocalizeScript',
            array(
                'affiliates'                   => $affiliates,
                'ocws_lp_affiliates_nonce' => wp_create_nonce( 'ocws_lp_affiliates_nonce' ),
                'strings'                 => array(
                    'unload_confirmation_msg'     => __( 'Your changed data will be lost if you leave this page without saving.', 'ocws' ),
                    'delete_confirmation_msg'     => __( 'Are you sure you want to delete this branch? This action cannot be undone.', 'ocws' ),
                    'save_failed'                 => __( 'Your changes were not saved. Please retry.', 'ocws' ),
                ),
            )
        );
        wp_enqueue_script( 'ocws_lp_affiliates' );

        include_once OCWS_PATH . '/admin/partials/local-pickup/html-admin-page-lp-affiliates.php';
    }

    public static function output_affiliate_tab($aff_id) {

        if ( 'new' === $aff_id ) {
            $affiliate = new OCWS_LP_Affiliate(0);
        } else {
            $aff_ds = new OCWS_LP_Affiliates();
            $aff = $aff_ds->db_get_affiliate($aff_id);
            if ( ! $aff ) {
                wp_die( esc_html__( 'Branch does not exist!', 'ocws' ) );
            }
            $affiliate = new OCWS_LP_Affiliate($aff);
        }

        //var_dump($affiliate);

        wp_localize_script(
            'ocws_lp_affiliate_edit',
            'lpAffiliateEditLocalizeScript',
            array(
                'aff_address'                 => $affiliate->get_aff_address(),
                'aff_name'               => $affiliate->get_aff_name(),
                'aff_id'                 => $affiliate->get_id(),
                'ocws_lp_affiliates_nonce' => wp_create_nonce( 'ocws_lp_affiliates_nonce' ),
                'strings'                 => array(
                    'unload_confirmation_msg' => __( 'Your changed data will be lost if you leave this page without saving.', 'ocws' ),
                    'save_changes_prompt'     => __( 'Do you wish to save your changes first? Your changed data will be discarded if you choose to cancel.', 'ocws' ),
                    'save_failed'             => __( 'Your changes were not saved. Please retry.', 'ocws' ),
                    'yes'                     => __( 'Yes', 'ocws' ),
                    'no'                      => __( 'No', 'ocws' ),
                    'default_affiliate_name'       => __( 'Branch', 'ocws' ),
                ),
            )
        );
        wp_enqueue_script( 'ocws_lp_affiliate_edit' );

        include_once OCWS_PATH . '/admin/partials/local-pickup/html-admin-page-lp-affiliate-edit.php';

        self::output_affiliate_settings($aff_id);
    }

    public static function output_common_settings() {

        $languages = ocws_get_languages();

        $aff_opts = new OCWS_LP_Affiliate_Option();

        $common_options = $aff_opts->general_options;

        ?>

        <h2><?php echo esc_html(__('General Settings', 'ocws')) ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields( OCWS_LP_Affiliate_Option::WP_OPTION_GROUP_COMMON ); ?>
            <?php do_settings_sections( OCWS_LP_Affiliate_Option::WP_OPTION_GROUP_COMMON ); ?>
            <table class="form-table">
                <?php foreach ($common_options as $opt) { ?>
                    <?php

                    if ($opt['type'] != 'subsection') {

                        $option_model = OCWS_LP_Affiliate_Option::get_common_option($opt['name'], $opt['default']);

                        if ($opt['callback']) {
                            call_user_func_array($opt['callback'], array($option_model, $opt));
                        }
                        else if ($opt['type'] == 'select') {
                            OCWS_LP_Affiliate_Option::output_select_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'checkbox') {
                            OCWS_LP_Affiliate_Option::output_checkbox_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'radio') {
                            OCWS_LP_Affiliate_Option::output_radio_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'text') {
                            OCWS_LP_Affiliate_Option::output_text_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'textarea') {
                            OCWS_LP_Affiliate_Option::output_textarea_option($option_model, $opt);
                        }

                    } else {

                        call_user_func_array($opt['callback'], array($opt));

                    }

                    ?>
                <?php } ?>

            </table>

            <?php submit_button(); ?>

        </form>

        <?php


    }


    public static function output_default_affiliate_settings() {

        $aff_opts = new OCWS_LP_Affiliate_Option();

        $default_options = $aff_opts->affiliate_options;

        ?>

        <h2><?php echo esc_html(__('Default branch settings', 'ocws')) ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields( OCWS_LP_Affiliate_Option::WP_OPTION_GROUP_DEFAULT ); ?>
            <?php do_settings_sections( OCWS_LP_Affiliate_Option::WP_OPTION_GROUP_DEFAULT ); ?>
            <table class="form-table">
                <?php foreach ($default_options as $opt) { ?>
                    <?php

                    if ($opt['type'] != 'subsection') {

                        $option_model = OCWS_LP_Affiliate_Option::get_default_option($opt['name'], $opt['default']);

                        if ($opt['callback']) {
                            call_user_func_array($opt['callback'], array($option_model, $opt));
                        }
                        else if ($opt['type'] == 'select') {
                            OCWS_LP_Affiliate_Option::output_default_select_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'checkbox') {
                            OCWS_LP_Affiliate_Option::output_default_checkbox_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'radio') {
                            OCWS_LP_Affiliate_Option::output_default_radio_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'text') {
                            OCWS_LP_Affiliate_Option::output_default_text_option($option_model, $opt);
                        }
                        else if ($opt['type'] == 'textarea') {
                            OCWS_LP_Affiliate_Option::output_default_textarea_option($option_model, $opt);
                        }

                    } else {

                        call_user_func_array($opt['callback'], array($opt));

                    }

                    ?>
                <?php } ?>

            </table>

            <?php submit_button(); ?>

        </form>

        <?php
    }

    public static function output_affiliate_settings($aff_id) {

        $aff_opts = new OCWS_LP_Affiliate_Option();

        $default_options = $aff_opts->affiliate_options;

        ?>

        <h2><?php echo esc_html(__('Branch settings', 'ocws')) ?></h2>
        <form id="aff_options_form" method="post" action="options.php">
            <?php settings_fields( OCWS_LP_Affiliate_Option::get_affiliate_option_group($aff_id) ); ?>
            <?php do_settings_sections( OCWS_LP_Affiliate_Option::get_affiliate_option_group($aff_id) ); ?>
            <table class="form-table">
                <?php foreach ($default_options as $opt) { ?>
                    <?php

                    if ($opt['type'] != 'subsection') {

                        $option_model = OCWS_LP_Affiliate_Option::get_option($aff_id, $opt['name'], $opt['default']);

                        if ($opt['callback']) {
                            call_user_func_array($opt['callback'], array($option_model, $opt, $aff_id));
                        }
                        else if ($opt['type'] == 'select') {
                            OCWS_LP_Affiliate_Option::output_affiliate_select_option($option_model, $opt, $aff_id);
                        }
                        else if ($opt['type'] == 'checkbox') {
                            OCWS_LP_Affiliate_Option::output_affiliate_checkbox_option($option_model, $opt, $aff_id);
                        }
                        else if ($opt['type'] == 'radio') {
                            OCWS_LP_Affiliate_Option::output_affiliate_radio_option($option_model, $opt, $aff_id);
                        }
                        else if ($opt['type'] == 'text') {
                            OCWS_LP_Affiliate_Option::output_affiliate_text_option($option_model, $opt, $aff_id);
                        }
                        else if ($opt['type'] == 'textarea') {
                            OCWS_LP_Affiliate_Option::output_affiliate_textarea_option($option_model, $opt, $aff_id);
                        }

                    } else {

                        call_user_func_array($opt['callback'], array($opt));

                    }

                    ?>
                <?php } ?>

            </table>

            <?php submit_button(); ?>

        </form>

        <?php
    }

    public static function init_settings() {


        $affOpts = new OCWS_LP_Affiliate_Option();
        $affOpts->init_pickup_options();
        $affOpts->register_pickup_options();
    }

    public static function setup_options_hooks() {

        add_filter( 'pre_update_option_ocws_lp_default_pickup_schedule_dates', function( $new_value, $old_value ) {

            $schedule = new OC_Woo_Shipping_Schedule();
            $schedule->set_scheduling_type('dates');
            return $schedule->import_from_json($new_value);

        }, 10, 2);

        add_filter( 'pre_update_option_ocws_lp_default_pickup_schedule_weekly', function( $new_value, $old_value ) {

            $schedule = new OC_Woo_Shipping_Schedule();
            $schedule->set_scheduling_type('weekly');
            return $schedule->import_from_json($new_value);

        }, 10, 2);

        add_filter( 'pre_update_option_ocws_lp_common_export_production_details_pages', function( $new_value, $old_value ) {

            if (!is_array($new_value)) {

                $new_value = array();
            }
            return array_filter($new_value);

        }, 10, 2);

        add_filter( 'pre_update_option', function( $new_value, $option, $old_value ) {

            if( preg_match('/^ocws_lp_aff_(\d+)_pickup_schedule_(dates|weekly)$/', $option, $matches ) ){

                $schedule = new OC_Woo_Shipping_Schedule();
                $schedule->set_scheduling_type($matches[2]);
                return $schedule->import_from_json($new_value);
            }

            /*if( preg_match('/^ocws_lp_(aff_(\d+)|default)_closing_weekdays$/', $option, $matches ) ){


            }*/

            return $new_value;

        }, 10, 3);
    }

    /**
     * @param \WC_Order $order
     */
    public static function admin_render_pickup_info($order)
    {
        foreach ($order->get_items('shipping') as $item) {

            echo OCWS_LP_Pickup_Info::admin_render_pickup_info($item);
        }
    }

    public static function admin_hide_address_fields_for_pickup($fields) {

        global $theorder;

        if ( ! is_object( $theorder ) ) {
            return $fields;
        }

        $order = $theorder;

        $pickup_shipping_item = OCWS_LP_Pickup_Info::get_shipping_item($order);

        if (null !== $pickup_shipping_item) {

            $names = array(
                'address_1',
                'address_2',
                'street',
                'city',
                'postcode',
                'country',
                'state',
                'house_num',
                'apartment',
                'floor',
                'enter_code',
            );

            foreach ($names as $fname) {
                if (isset($fields[$fname])) {
                    $fields[$fname]['show'] = false;
                }
            }
        }

        return $fields;
    }

    public function woocommerce_saved_order_items_action( $order_id, $items ) {

        $order = wc_get_order( $order_id );
        if (!$order) return;

        OCWS_LP_Pickup_Info::update_in_order($order);
    }

    public static function setup_admin_order_columns() {

        OCWS_LP_Pickup_Admin_Columns::get_instance();
    }

    /**
     * @param array $raw_address
     * @param \WC_Order $order
     * @return array
     */
    public static function woocommerce_order_formatted_billing_address( $raw_address, $order ) {

        $pickup_shipping_item = OCWS_LP_Pickup_Info::get_shipping_item($order);

        if (null !== $pickup_shipping_item) {
            $names = array(
                'address_1',
                'address_2',
                'street',
                'city',
                'postcode',
                'country',
                'state',
                'house_num',
                'apartment',
                'floor',
                'enter_code',
            );

            foreach ($names as $fname) {
                if (isset($raw_address[$fname])) {
                    $raw_address[$fname] = '';
                }
            }
        }
        return $raw_address;
    }

    /**
     * @param array $raw_address
     * @param \WC_Order $order
     * @return array
     */
    public static function woocommerce_order_formatted_shipping_address( $raw_address, $order ) {

        $pickup_shipping_item = OCWS_LP_Pickup_Info::get_shipping_item($order);

        if (null !== $pickup_shipping_item) {
            $names = array(
                'address_1',
                'address_2',
                'street',
                'city',
                'postcode',
                'country',
                'state',
                'house_num',
                'apartment',
                'floor',
                'enter_code',
            );

            foreach ($names as $fname) {
                if (isset($raw_address[$fname])) {
                    $raw_address[$fname] = '';
                }
            }
        }
        return $raw_address;
    }
}