<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://originalconcepts.co.il/
 * @since      1.0.0
 *
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/includes
 */

defined( 'ABSPATH' ) || exit;



/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/includes
 * @author     Milla Shub <milla@originalconcepts.co.il>
 */
class Oc_Woo_Shipping {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Oc_Woo_Shipping_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    public $use_shipping_companies = true;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */

    /**
     * Locations instance.
     *
     * @var OC_Woo_Shipping_Locations
     */
    public $locations = null;

    /**
     * The single instance of the class.
     *
     * @var Oc_Woo_Shipping
     */
    protected static $_instance = null;

    /**
     * Main Oc_Woo_Shipping Instance.
     *
     * Ensures only one instance of Oc_Woo_Shipping is loaded or can be loaded.
     *
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            //error_log('main plugin class new instance');
            //error_log(print_r(debug_backtrace(), 1));
        }
        return self::$_instance;
    }

    public function __construct() {
        if ( defined( 'OC_WOO_SHIPPING_VERSION' ) ) {
            $this->version = OC_WOO_SHIPPING_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'ocws';

        /*
         * Do not use date_default_timezone_set(). WordPress core requires the timezone in PHP to be GMT+0.
         * Several features are dependent on this, and will break if the timezone is adjusted.
         * */
        //date_default_timezone_set( 'Asia/Jerusalem' );

        $this->load_dependencies();
        $this->set_locale();
        $this->setup_shipping_method();
        $this->define_common_hooks();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->setup_locations();

        //error_log('main plugin class construct');
    }

    private function setup_locations() {

        $this->locations = new OC_Woo_Shipping_Locations();
    }

    private function setup_shipping_method() {

        add_filter( 'woocommerce_shipping_methods', 'ocws_add_shipping_method' );

        add_action( 'woocommerce_shipping_init', 'ocws_shipping_method_init' );

        //add_action( 'woocommerce_review_order_before_cart_contents', array('OC_Woo_Advanced_Shipping_Method', 'validate_order') , 10 );
        add_action( 'woocommerce_after_checkout_validation', array('OC_Woo_Advanced_Shipping_Method', 'validate_order') , 10, 2 );

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Oc_Woo_Shipping_Loader. Orchestrates the hooks of the plugin.
     * - Oc_Woo_Shipping_i18n. Defines internationalization functionality.
     * - Oc_Woo_Shipping_Admin. Defines all hooks for the admin area.
     * - Oc_Woo_Shipping_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-loader.php';

        //
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-i18n.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-locations.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-group.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-groups.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-companies.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-slots.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-product.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-info.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-notices.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-ajax.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-export.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-export-for-production.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-export-for-production-adv.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-export-for-production-opensea.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-export-for-packaging.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-export-sales-report.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-export-orders-report.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/oc-woo-shipping-core-functions.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-group-option.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-schedule.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/interfaces/class-oc-woo-shipping-group-data-store-interface.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/data-stores/class-oc-woo-shipping-group-data-store.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/data-stores/class-oc-woo-shipping-company-data-store.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocws-advanced-shipping.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocws-popup.php';

        /*
         * Local pickup classes
         * */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-affiliate.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-affiliate-default-option-model.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-affiliate-option.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-affiliate-option-model.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-general-option-model.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-affiliates.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-local-pickup.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-pickup-info.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-pickup-slots.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/local-pickup/class-ocws-lp-pickup-admin-columns.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocws-admin-columns.php';



        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-oc-woo-shipping-admin.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-oc-woo-shipping-admin-groups.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-oc-woo-shipping-admin-companies.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-admin-columns.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin/class-ocws-admin-profile.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-oc-woo-shipping-public.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'simplehtmldom_1_9_1/simple_html_dom.php';

        /*
         * shortcodes
         * */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-shortcode.php';
        /*
         * polygon
         * */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-oc-woo-shipping-polygon.php';

        /*
         * admin edit order shipping
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocws-admin-shipping.php';

        $this->loader = new Oc_Woo_Shipping_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Oc_Woo_Shipping_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {

        $plugin_i18n = new Oc_Woo_Shipping_i18n();

        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

    }

    public function define_common_hooks() {

        add_filter('ocws_get_city_title', 'ocws_get_city_title');

        add_filter('ocws_order_billing_city_filter', 'ocws_order_billing_city_filter', 10, 2);
        add_filter('ocws_order_shipping_city_filter', 'ocws_order_shipping_city_filter', 10, 2);

        add_filter('woocommerce_my_account_my_address_formatted_address', 'ocws_my_account_my_address_filter', 10, 3);

        add_filter( 'woocommerce_rest_prepare_shop_order_object', 'ocws_woocommerce_rest_prepare_shop_order_object_filter', 10, 3 );

        add_filter( 'woocommerce_api_order_response', 'ocws_woocommerce_api_order_response_filter', 10, 2 );

        add_action( 'init', array('OC_Woo_Shipping_Shortcode', 'register_shortcodes') );

        add_action (
            'save_post',
            function ($post_id, \WP_Post $post, $update) {

                if( get_post_type( $post_id ) === 'shop_order' ) {

                    // Ignore order (post) creation
                    if ($update !== true) {
                        return;
                    }

                    $order = wc_get_order($post_id);
                    if ($order) {
                        ocws_save_full_address_to_order($order);
                    }
                }
            },
            10,
            3
        );

        add_filter('cardcom_parameter_billing_city', 'ocws_cardcom_parameter_billing_city_filter', 10, 2);

        // Limit product to specific delivery days (e.g. weekend-only products)
        add_filter( 'woocommerce_product_is_purchasable', 'ocws_filter_woocommerce_product_is_purchasable', 10, 2 );
        add_filter( 'woocommerce_loop_add_to_cart_link', 'ocws_filter_woocommerce_loop_add_to_cart_link', 10, 3 );
        add_filter( 'woocommerce_post_class', 'ocws_filter_woocommerce_post_class', 10, 2 );
        add_filter( 'post_class', 'ocws_filter_post_class', 10, 3 );
        add_action( 'woocommerce_before_add_to_cart_form', 'ocws_action_woocommerce_single_product_limit_to_days_message' );
        add_filter( 'woocommerce_add_to_cart_validation', 'ocws_filter_woocommerce_add_to_cart_validation', 10, 5 );

        // Iconic Quickview: replace add-to-cart in modal with day-limit message when applicable (run after Quickview registers on init 11).
        add_action( 'init', 'ocws_jckqv_day_limit_register', 20 );

        /* for FLASHY */
        add_filter('woocommerce_order_get_billing_city', function($billing_city, $order) {
            error_log('---------------------- woocommerce_order_get_billing_city filter ----------------------------');
            error_log('city to convert: "'.$billing_city.'"');
            $e = new Exception;
            $trace = $e->getTraceAsString();
            if ($trace && (strstr($trace, 'flashy_hook_new_order') || strstr($trace, 'flashy_purchase'))) {
                error_log('---------------------- woocommerce_order_get_billing_city filter ----------------------------');
                error_log('city to convert: "'.$billing_city.'"');
                if (!$order || !($order instanceof WC_Order)) {
                    error_log('no order');
                    return $billing_city;
                }
                $city = '';
                if (is_numeric($billing_city) || ocws_is_hash($billing_city)) {
                    $city = get_post_meta( $order->get_id(), '_billing_city_name', true);
                    if (!$city) {
                        $city = ocws_get_city_title($billing_city);
                    }
                }
                error_log('city: '.$city);
                error_log('---------------------------------------------------------');
                return ($city? $city : $billing_city);
            }
            return $billing_city;

        }, 10, 2);
        /* ----- for FLASHY */
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {

        $plugin_admin = new Oc_Woo_Shipping_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts', 99 );

        $this->loader->add_action( 'woocommerce_admin_order_data_after_shipping_address', $plugin_admin, 'admin_render_shipping_info', 10, 3);
        $this->loader->add_action( 'woocommerce_admin_order_data_after_shipping_address', $plugin_admin, 'admin_render_shipping_phone', 10, 3);
        $this->loader->add_action( 'woocommerce_admin_order_data_after_shipping_address', $plugin_admin, 'admin_render_send_to_other_person', 10, 3);
        $this->loader->add_action( 'woocommerce_admin_order_data_after_shipping_address', $plugin_admin, 'admin_render_send_to_other_person_greeting', 10, 3);

        // Display the custom field result on the order edit page (backend) when checkbox has been checked
        $this->loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $plugin_admin, 'display_custom_field_on_order_edit_pages', 10, 1 );

        $this->loader->add_filter( 'woocommerce_admin_billing_fields', $plugin_admin, 'change_billing_city_to_dropdown' );
        $this->loader->add_filter( 'woocommerce_admin_shipping_fields', $plugin_admin, 'change_shipping_city_to_dropdown' );
        $this->loader->add_filter( 'woocommerce_order_formatted_billing_address', $plugin_admin, 'woocommerce_order_formatted_billing_address', 10, 3 );
        $this->loader->add_filter( 'woocommerce_order_formatted_shipping_address', $plugin_admin, 'woocommerce_order_formatted_shipping_address', 10, 3 );

        $this->loader->add_filter( 'woocommerce_customer_meta_fields', $plugin_admin, 'woocommerce_customer_meta_fields', 20, 1);

        $this->loader->add_action( 'woocommerce_after_edit_attribute_fields', $plugin_admin, 'action_woocommerce_after_edit_attribute_fields', 10, 0 );
        $this->loader->add_action( 'woocommerce_after_add_attribute_fields', $plugin_admin, 'action_woocommerce_after_edit_attribute_fields', 10, 0 );
        $this->loader->add_action( 'woocommerce_attribute_added', $plugin_admin, 'action_woocommerce_attribute_updated', 10, 1 );
        $this->loader->add_action( 'woocommerce_attribute_updated', $plugin_admin, 'action_woocommerce_attribute_updated', 10, 1 );
        $this->loader->add_action( 'woocommerce_attribute_deleted', $plugin_admin, 'action_woocommerce_attribute_deleted', 10, 1 );

        add_action( 'admin_menu', array( $plugin_admin, 'admin_menu' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_settings' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_options_hooks' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_product_hooks' ) );
        add_action( 'admin_init', array( $plugin_admin, 'setup_admin_order_columns' ) );
        add_action( 'admin_init', array( 'OCWS_Admin_Shipping', 'init') );
        // TODO: run once and comment - reassign orders metas
        //add_action( 'admin_init', array( $plugin_admin, 'reassign_orders_metas'	) );

        OCWS_LP_Admin::init();

        $this->loader->add_action('product_cat_add_form_fields', $plugin_admin, 'woo_product_cat_add_new_meta_field', 10, 0);
        $this->loader->add_action('product_cat_edit_form_fields', $plugin_admin, 'woo_product_cat_edit_meta_field', 10, 1);
        $this->loader->add_action('edited_product_cat', $plugin_admin, 'woo_save_product_cat_custom_meta', 10, 1);
        $this->loader->add_action('create_product_cat', $plugin_admin, 'woo_save_product_cat_custom_meta', 10, 1);


        // checkout custom recipient
        $this->loader->add_filter( 'woocommerce_admin_billing_fields', $plugin_admin, 'woo_admin_billing_fields' );


        if (OC_WOO_USE_COMPANIES) {

            $this->loader->add_filter( 'bulk_actions-edit-shop_order', $plugin_admin, 'set_companies_orders_bulk_actions' );
            $this->loader->add_filter( 'handle_bulk_actions-edit-shop_order', $plugin_admin, 'set_companies_bulk_action_edit_shop_order', 10, 3 );
            $this->loader->add_action( 'admin_notices', $plugin_admin, 'set_companies_bulk_action_admin_notice' );

        }

        $this->loader->add_action( 'woocommerce_saved_order_items', $plugin_admin, 'woocommerce_saved_order_items_action', 10, 2);
        $this->loader->add_filter( 'woocommerce_admin_billing_fields', $plugin_admin, 'add_billing_street_field' );
        $this->loader->add_filter( 'woocommerce_admin_shipping_fields', $plugin_admin, 'add_billing_street_field' );

        new OCWS_Admin_Profile();

        $this->loader->add_action( 'woocommerce_admin_order_data_after_order_details', $plugin_admin, 'maybe_change_order_meta', 10, 1);

    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {

        $plugin_public = new Oc_Woo_Shipping_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

        $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'change_city_to_dropdown', 100 );
        $this->loader->add_filter( 'woocommerce_default_address_fields', $plugin_public, 'change_default_city_to_dropdown', 100 );
        $this->loader->add_filter( 'woocommerce_default_address_fields', $plugin_public, 'change_default_city_if_polygon', 501 );

        $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'change_street_to_dropdown', 100 );

        $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'change_default_guest_billing_fields', 100 );
        $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'trigger_update_checkout_on_change', 100 );
        $this->loader->add_filter( 'woocommerce_checkout_get_value', $plugin_public, 'change_checkout_user_billing_field', 100, 2 );
        $this->loader->add_filter( 'woocommerce_checkout_update_order_review', $plugin_public, 'save_checkout_data_to_session', 100, 1 );

        $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'checkout_add_slot_date_time_fields', 100 );

        $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'change_checkout_address_fields_if_polygon', 501 );

        $this->loader->add_action( 'woocommerce_after_checkout_billing_form', $plugin_public, 'render_shipping_additional_fields', 10, 1);

        $this->loader->add_filter( 'woocommerce_hidden_order_itemmeta', $plugin_public, 'hidden_order_itemmeta');

        $this->loader->add_filter( 'woocommerce_order_shipping_to_display', $plugin_public, 'email_shipping_info', 10, 2);


        $this->loader->add_action( 'woocommerce_checkout_order_processed', $plugin_public, 'save_shipping_to_order', 10, 3);

        $this->loader->add_action( 'woocommerce_before_checkout_process', $plugin_public, 'validate_shipping_info');

        // Add custom checkout field: woocommerce_review_order_before_submit
        $this->loader->add_action( 'woocommerce_review_order_before_submit', $plugin_public, 'custom_checkout_field' );

        // Save the custom checkout field in the order meta, when checkbox has been checked
        $this->loader->add_action( 'woocommerce_checkout_update_order_meta', $plugin_public, 'custom_checkout_field_update_order_meta', 10, 1 );

        $this->loader->add_filter( 'woocommerce_order_details_after_order_table', $plugin_public, 'order_details_after_order_table', 10, 2);

        $this->loader->add_action( 'woocommerce_review_order_after_shipping', $plugin_public, 'print_shipping_notices', 10, 0 );

        $this->loader->add_filter('woocommerce_update_order_review_fragments', $plugin_public, 'add_checkout_shipping_methods_fragment');

        $this->loader->add_action('ocws_shipping_popup', $plugin_public, 'add_shipping_popup', 10, 0);

        $this->loader->add_action('ocws_checkout_choose_city_popup', $plugin_public, 'add_checkout_choose_city_popup', 10, 0);


        $this->loader->add_filter( 'woocommerce_locate_template', $plugin_public, 'locate_woo_template', 20, 3 );

        // checkout custom recipient
        $this->loader->add_action( 'ocws_send_to_other_person_fields', $plugin_public, 'ocws_send_to_other_person_fields' );
        $this->loader->add_action( 'ocws_send_to_other_person_greeting', $plugin_public, 'ocws_send_to_other_person_greeting' );
        $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'woo_checkout_add_shipping_phone', 10 );
        $this->loader->add_action( 'woocommerce_checkout_order_processed', $plugin_public, 'woo_checkout_order_processed', 10, 3 );

        // we will use custom 'billing_street' field in place of 'billing_address_1', and hide 'billing_address_1'
        //$this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_public, 'woo_checkout_add_billing_street', 500 );
        $this->loader->add_filter( 'woocommerce_default_address_fields', $plugin_public, 'add_default_billing_street', 500 );
        $this->loader->add_action( 'woocommerce_checkout_order_processed', $plugin_public, 'save_full_address_to_order', 1000, 3);

        $this->loader->add_action( 'woocommerce_customer_save_address', $plugin_public, 'woocommerce_customer_save_address_action', 10, 2 );

        add_action( 'woocommerce_init', function(){
            if (isset(WC()->session)) {
                if (!WC()->session->has_session()) {
                    WC()->session->set_customer_session_cookie(true);
                }
            }
        } );

        $this->loader->add_filter( 'woocommerce_cart_shipping_packages', $plugin_public, 'woocommerce_cart_shipping_packages_filter', 10, 1 );

        OCWS_Advanced_Shipping::init();

        // Local pickup
        OCWS_LP_Local_Pickup::init();

        // Polygon related
        $this->loader->add_filter( 'woocommerce_checkout_get_value', $plugin_public, 'change_checkout_billing_google_autocomplete_field', 101, 2 );
        $this->loader->add_filter( 'woocommerce_process_checkout_field_billing_city', $plugin_public, 'process_checkout_field_billing_city', 10, 1 );
        /*$this->loader->add_filter( 'woocommerce_process_checkout_field_billing_google_autocomplete',
            $plugin_public, 'process_checkout_field_billing_google_autocomplete', 10, 1 );*/

    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Oc_Woo_Shipping_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

}


