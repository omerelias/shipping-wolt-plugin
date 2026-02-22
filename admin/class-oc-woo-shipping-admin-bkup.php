<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://originalconcepts.co.il/
 * @since      1.0.0
 *
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/admin
 */

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-oc-woo-shipping-admin-groups.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/admin
 * @author     Milla Shub <milla@originalconcepts.co.il>
 */
class Oc_Woo_Shipping_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;


	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Oc_Woo_Shipping_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Oc_Woo_Shipping_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( 'ocws-woo-admin', plugin_dir_url( __FILE__ ) . 'css/woo-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/oc-woo-shipping-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'ocws-adminjquery-ui', plugin_dir_url( __FILE__ ) . 'css/adminjquery-ui.css', array(), $this->version, 'all'  );
		wp_enqueue_style( 'jquery-timepicker', plugin_dir_url( __FILE__ ) . 'css/jquery.timepicker.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'select2', plugin_dir_url( __FILE__ ) . 'css/select2.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'jquery-schedule', plugin_dir_url( __FILE__ ) . 'js/jquery-schedule/jquery.schedule.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Oc_Woo_Shipping_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Oc_Woo_Shipping_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		add_thickbox();
		$suffix = '.min';

		if( isset($_GET['page']) && ($_GET['page'] === 'ocws' || $_GET['page'] === 'ocws-lp') ) {

			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/oc-woo-shipping-admin.js', array( 'jquery' ), $this->version, false );
			wp_enqueue_script( 'jquery-blockui', plugin_dir_url( __FILE__ ) . 'js/jquery-blockui/jquery.blockUI' . $suffix . '.js', array( 'jquery' ), '2.70', true );
			wp_enqueue_script( 'jquery-tiptip', plugin_dir_url( __FILE__ ) . 'js/jquery-tiptip/jquery.tipTip' . $suffix . '.js', array( 'jquery' ), $this->version, true );
			wp_enqueue_script( 'select2', plugin_dir_url( __FILE__ ) . 'js/select2/select2' . $suffix . '.js', array( 'jquery' ), $this->version, true );
			wp_enqueue_script( 'ocws-admin-jquery-ui', plugin_dir_url( __FILE__ ) . 'js/jquery-ui.js', array( 'jquery' ), $this->version, true );
			wp_enqueue_script( 'jquery-multidatespicker', plugin_dir_url( __FILE__ ) . 'js/jquery-ui.multidatespicker' . '' . '.js', array( 'jquery', 'jquery-ui-datepicker' ), $this->version, true );
			wp_enqueue_script( 'jquery-timepicker', plugin_dir_url( __FILE__ ) . 'js/jquery.timepicker' . $suffix . '.js', array( 'jquery' ), $this->version, true );
			wp_enqueue_script( 'jquery-schedule-ocws', plugin_dir_url( __FILE__ ) . 'js/jquery-schedule/jquery.schedule-ocws' . '' . '.js', array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-resizable' ), $this->version, true );
			wp_register_script( 'oc-woo-shipping-groups', plugin_dir_url( __FILE__ ) . 'js/oc-woo-shipping-groups.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal' ), $this->version, false );
			wp_register_script( 'ocws_lp_affiliates', plugin_dir_url( __FILE__ ) . 'js/local-pickup/ocws-lp-affiliates.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal' ), $this->version, false );
			wp_register_script( 'ocws_lp_affiliate_edit', plugin_dir_url( __FILE__ ) . 'js/local-pickup/ocws-lp-affiliate-edit.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal' ), $this->version, false );
			wp_register_script( 'oc-woo-shipping-companies', plugin_dir_url( __FILE__ ) . 'js/oc-woo-shipping-companies.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal' ), $this->version, false );
			wp_register_script( 'oc-woo-shipping-group-edit', plugin_dir_url( __FILE__ ) . 'js/oc-woo-shipping-group-edit.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal' ), $this->version, false );
			wp_register_script( 'ocws-cities-import', plugin_dir_url( __FILE__ ) . 'js/ocws-cities-import.js', array( 'jquery' ), $this->version, true );

			$maps_api_key = ocws_get_google_maps_api_key(); // AIzaSyBBwkrNCn4jojbsjPL-DgeYXjTf-xLgwqo for meatstore

			if ($maps_api_key) {

				wp_register_script('ocws-google-maps-api', 'https://maps.googleapis.com/maps/api/js?key='.$maps_api_key.'&libraries=drawing,places&language='.get_locale(), null, null, true);
				wp_enqueue_script('ocws-backbone-maps-modal', plugin_dir_url(__FILE__) . 'js/backbone-map-modal.js', array('jquery', 'backbone', 'ocws-google-maps-api'), null, true);
				wp_enqueue_script('ocws-google-maps-init', plugin_dir_url(__FILE__) . 'js/google-maps-init.js', array('jquery', 'ocws-google-maps-api'), null, true);
			}
		}
		else if ( ocws_is_admin_order_screen() ) {
			//wp_enqueue_script( 'ocws-admin-jquery-ui', plugin_dir_url( __FILE__ ) . 'js/jquery-ui.js', array( 'jquery' ), $this->version, true );
			//wp_enqueue_script( 'jquery-multidatespicker', plugin_dir_url( __FILE__ ) . 'js/jquery-ui.multidatespicker' . '' . '.js', array( 'jquery', 'jquery-ui-datepicker' ), $this->version, true );
			wp_enqueue_script( 'jquery-timepicker', plugin_dir_url( __FILE__ ) . 'js/jquery.timepicker' . $suffix . '.js', array( 'jquery' ), $this->version, true );
		}
		wp_enqueue_script( 'ocws-admin-order-helper', plugin_dir_url( __FILE__ ) . 'js/oc-woo-shipping-admin-helper.js', array( 'jquery' ), $this->version, false );
	}

	public function setup_settings() {

		$group_options = array(
			'min_total',
			'min_total_message_yes',
			'min_total_message_no',
			'preorder_days',
			'min_wait_times',
			'shipping_price',
			'min_total_for_free_shipping',
			'delivery_scheduling_type',
			'delivery_schedule_weekly',
			'delivery_schedule_repeat',
			'delivery_schedule_repeat_start',
			'delivery_schedule_dates',
			'max_hour_for_today',
			'max_products_per_slot',
			'closing_weekdays',
			'closing_dates',
            'price_depending'
		);

		$location_options = array(
			'shipping_price',
			'min_total'
		);

		$general_options = array(
			'pickup_only_message',
			'enable_at_the_door_checkbox',
			'enable_other_products_checkbox',
			'enable_send_to_other_checked_by_default',
			'enable_greeting_field',
			'show_dates_only',
			'hide_slot_in_admin_mail',
			'export_production_attributes_to_show',
			'export_production_attributes_to_group_by',
			'export_production_details_attributes_to_show',
			'export_production_details_pages',
			'export_order_statuses',
			'export_show_order_completed_date',
			'export_show_b2b',
			'export_show_customer_email',
			'export_sales_order_statuses',
			'export_hide_summary',
			'export_hide_time_slots',
			'export_hide_order_items',
			'export_hide_items_number',
			'export_hide_order_notes',
			'export_hide_shipping_method',
			'export_hide_order_total',
			'checkout_send_to_other_checkbox_label',
			'google_maps_api_key',
			'use_google_cities_and_polygons',
            'dates_style',
			'orders_show_completed_date'

		);

		$general_options_defaults = OC_Woo_Shipping_Group_Option::get_general_options_defaults();

		$multilingual_general_options = array(

			'popup_title',
			'popup_shipping_method_button_text',
			'popup_choose_location_title',
			'popup_choose_location_sub_title',
			'popup_button_text',
			'checkout_slots_title',
			'checkout_slots_description',
		);

			/*array(
			'popup_title' => __('בחרו סוג משלוח', 'ocws'),
			'popup_shipping_method_button_text' => __('שליח עד הבית', 'ocws'),
			'popup_choose_location_title' => __('בחרו עיר / יישוב למשלוח', 'ocws'),
			'popup_choose_location_sub_title' => '',
			'popup_button_text' => __('אישור', 'ocws'),
			'checkout_send_to_other_checkbox_label' => __('אני שולח למישהו אחר', 'ocws'),
			'checkout_slots_title' => __('מתי נוח לך שנגיע?', 'ocws'),
			'checkout_slots_description' => __('זמני האספקה המוצגים הינם לפי איזור החלוקה וזמני המשלוח הפנויים', 'ocws'),

		);*/

		if (defined('OC_WOO_USE_OPENSEA_STYLE_EXPORT') && OC_WOO_USE_OPENSEA_STYLE_EXPORT) {
			$general_options[] = 's_maaraz_capacity';
			$general_options[] = 'm_maaraz_capacity';
			$general_options[] = 'l_maaraz_capacity';
			$general_options[] = 's_kalkar_capacity';
			$general_options[] = 'm_kalkar_capacity';
			$general_options[] = 'l_kalkar_capacity';
		}

		$groups = OC_Woo_Shipping_Groups::get_groups();

		foreach ($group_options as $option_name) {
			OC_Woo_Shipping_Group_Option::register_default_option( $option_name );
			foreach ($groups as $group_id => $group_data) {
				OC_Woo_Shipping_Group_Option::register_option($group_id, $option_name);
			}
		}

		foreach ($groups as $group_id => $group_data) {
			$group = new OC_Woo_Shipping_Group($group_id);
			$locations = $group->get_locations_codes();
			foreach ($locations as $code) {
				foreach ($location_options as $option_name) {
					OC_Woo_Shipping_Group_Option::register_location_option($code, $option_name);
				}
			}
		}

		foreach ($general_options as $option_name) {
			OC_Woo_Shipping_Group_Option::register_common_option( $option_name, (isset($general_options_defaults[$option_name])? $general_options_defaults[$option_name] : '') );
		}

		$languages = ocws_get_languages();

		if (!empty($languages)) {
			foreach($languages as $language_code) {
				foreach ($multilingual_general_options as $option_name) {
					OC_Woo_Shipping_Group_Option::register_common_option( $option_name . '_' . $language_code, (isset($general_options_defaults[$option_name])? $general_options_defaults[$option_name] : '') );
				}
			}
		}
		else {
			foreach ($multilingual_general_options as $option_name) {
				OC_Woo_Shipping_Group_Option::register_common_option( $option_name, (isset($general_options_defaults[$option_name])? $general_options_defaults[$option_name] : '') );
			}
		}

	}

	public function setup_product_hooks() {

		OC_Woo_Shipping_Product::init();
	}

	public function setup_options_hooks() {

		add_filter( 'pre_update_option_ocws_default_delivery_schedule_dates', function( $new_value, $old_value ) {

			$schedule = new OC_Woo_Shipping_Schedule();
			$schedule->set_scheduling_type('dates');
			return $schedule->import_from_json($new_value);

		}, 10, 2);

		add_filter( 'pre_update_option_ocws_default_delivery_schedule_weekly', function( $new_value, $old_value ) {

			$schedule = new OC_Woo_Shipping_Schedule();
			$schedule->set_scheduling_type('weekly');
			return $schedule->import_from_json($new_value);

		}, 10, 2);

		add_filter( 'pre_update_option_ocws_common_export_production_details_pages', function( $new_value, $old_value ) {

			if (!is_array($new_value)) {

				$new_value = array();
			}
			return array_filter($new_value);

		}, 10, 2);

		add_filter( 'pre_update_option', function( $new_value, $option, $old_value ) {

			if( preg_match('/^ocws_group(\d+)_delivery_schedule_(dates|weekly)$/', $option, $matches ) ){

				$schedule = new OC_Woo_Shipping_Schedule();
				$schedule->set_scheduling_type($matches[2]);
				return $schedule->import_from_json($new_value);
			}

			return $new_value;

		}, 10, 3);
	}

	function admin_menu() {

		$menu_title = __( 'OC Shipping', 'ocws' );
		add_submenu_page( 'woocommerce', __( 'OC Advanced Shipping', 'ocws' ), $menu_title, 'manage_woocommerce', 'ocws', array( $this, 'admin_page' ) );
	}

	function admin_page() {

		$groups = OC_Woo_Shipping_Groups::get_groups();

		$tabs = array(
			'groups' => array(
				'title' => __( 'Shipping groups', 'ocws' )
			),
			'default-group' => array(
				'title' => __( 'Default group settings', 'ocws' )
			),
			'common-settings' => array(
				'title' => __( 'General settings', 'ocws' )
			),
		);

		foreach ($groups as $group) {
			$tabs['group'.$group['group_id']] = array(
				'title' => $group['group_name']
			);
		}

		if (OC_WOO_USE_COMPANIES) {
			$tabs['companies'] = array(
				'title' => __('Shipping companies', 'ocws')
			);
		}

		$first_tab      = array_keys( $tabs );
		$current_tab    = ! empty( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? sanitize_title( $_GET['tab'] ) : $first_tab[0];

		?>
			<div class="wrap woocommerce">
				<h1>OC Advanced Shipping</h1>
				<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
					<?php
					foreach ( $tabs as $key => $tab ) {
						echo '<a href="' . admin_url( 'admin.php?page=ocws&tab=' . urlencode( $key ) ) . '" class="nav-tab ';
						if ( $current_tab == $key ) {
							echo 'nav-tab-active';
						}
						echo '">' . esc_html( $tab['title'] ) . '</a>';
					}
					?>
				</nav>
				<h1 class="screen-reader-text"><?php echo esc_html( $tabs[ $current_tab ] ); ?></h1>
				<?php
				if( $current_tab == 'groups' ) {
					OC_Woo_Shipping_Admin_Groups::output();
				}
				else if ( substr( $current_tab, 0, 5 ) == 'group' ) {
					$group_id = intval(substr( $current_tab, 5 ));

					if (isset($_GET['group-action']) && $_GET['group-action'] == 'import') {
						OC_Woo_Shipping_Admin_Groups::output_import($group_id);
					}
					else {
						OC_Woo_Shipping_Admin_Groups::output($group_id);
					}
				}
				else if ( $current_tab == 'default-group' ) {
					OC_Woo_Shipping_Admin_Groups::output_default_group_settings();
				}
				else if ( $current_tab == 'common-settings') {
					OC_Woo_Shipping_Admin_Groups::output_common_settings();
				}
				else if( $current_tab == 'companies' && OC_WOO_USE_COMPANIES) {
					OC_Woo_Shipping_Admin_Companies::output();
				}
				?>
			</div>
		<?php
	}

	/**
	 * @param \WC_Order $order
	 */
	public function admin_render_shipping_info($order)
	{
		foreach ($order->get_items('shipping') as $item) {

			echo OC_Woo_Shipping_Info::admin_render_shipping_info($item);
		}
	}

	/**
	 * @param \WC_Order $order
	 */
	public function admin_render_shipping_phone($order)
	{
		echo '<p><strong>'.__('Phone', 'ocws').':</strong> <a href="tel:'.get_post_meta( $order->get_id(), '_shipping_phone', true ).'">'.get_post_meta( $order->get_id(), '_shipping_phone', true ).'</a></p>';
	}

	/**
	 * @param \WC_Order $order
	 */
	public function admin_render_send_to_other_person($order)
	{
		$send_to_other_person = get_post_meta( $order->get_id(), 'ocws_other_recipient', true );
		if ($send_to_other_person) {
			echo '<p><strong>'.__('Send to other person', 'ocws').'</strong></p>';
		}
	}

	/**
	 * @param \WC_Order $order
	 */
	public function admin_render_send_to_other_person_greeting($order)
	{
		$greeting = get_post_meta( $order->get_id(), '_shipping_greeting', true );
		if ($greeting) {
			echo '<p><strong>'.__('Personal greeting', 'ocws').':</strong> '.esc_html($greeting).'</p>';
		}
	}

	/*
	 * @param \WC_Order $order
	 */
	public function display_custom_field_on_order_edit_pages( $order ){

		$chck1 = get_option('ocws_common_enable_at_the_door_checkbox', '');
		$chck2 = get_option('ocws_common_enable_other_products_checkbox', '');

		$ocws_leave_at_the_door = get_post_meta( $order->get_id(), 'ocws_leave_at_the_door', true );
		if( $ocws_leave_at_the_door == 1 )
			echo '<p><strong>' . esc_html(__($chck1, 'ocws')) . ': </strong> <span style="color:#22c646;">' . esc_html(__('enabled', 'ocws')) . '</span></p>';

		$ocws_other_products = get_post_meta( $order->get_id(), 'ocws_other_products', true );
		if( $ocws_other_products == 1 )
			echo '<p><strong>' . esc_html(__($chck2, 'ocws')) . ': </strong> <span style="color:#22c646;">' . esc_html(__('enabled', 'ocws')) . '</span></p>';

	}

	/**
	 * Change the order billing city field to a dropdown field. 'woocommerce_admin_billing_fields'
	 */
	public function change_billing_city_to_dropdown( $fields ) {

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(true),
			'input_class' => array(
				'ocws-enhanced-select',
			)
		), $fields['city'] );

		$fields['city'] = $city_args;

		return $fields;

	}

	/**
	 * Change the order shipping city field to a dropdown field. 'woocommerce_admin_billing_fields'
	 */
	public function change_shipping_city_to_dropdown( $fields ) {

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(true),
			'input_class' => array(
				'ocws-enhanced-select',
			)
		), $fields['city'] );

		$fields['city'] = $city_args;

		return $fields;

	}

	/**
	 * @param array $raw_address
	 * @param \WC_Order $order
	 * @return array
     */
	public function woocommerce_order_formatted_billing_address( $raw_address, $order ) {

		//error_log(print_r($raw_address, 1));
		if (isset( $raw_address['city'])) {
			$raw_address['city'] = ocws_get_city_title( $raw_address['city'] );
		}
		return $raw_address;
	}

	/**
	 * @param array $raw_address
	 * @param \WC_Order $order
	 * @return array
	 */
	public function woocommerce_order_formatted_shipping_address( $raw_address, $order ) {

		if (isset( $raw_address['city'])) {
			$raw_address['city'] = ocws_get_city_title( $raw_address['city'] );
		}
		return $raw_address;
	}

	public function woocommerce_order_get_city( $city_code, $order ) {

		return ocws_get_city_title( $city_code );
	}

	public function woocommerce_customer_meta_fields( $fields ) {

		if (isset( $fields['billing']['fields']['billing_city'] )) {

			$city_args = wp_parse_args(array(
				'type' => 'select',
				'options' => OC_Woo_Shipping_Groups::get_all_locations(true),
				'class' => 'ocws-enhanced-select',
			), $fields['billing']['fields']['billing_city']);

			$fields['billing']['fields']['billing_city'] = $city_args;
		}

		if (isset( $fields['shipping']['fields']['shipping_city'] )) {

			$city_args = wp_parse_args(array(
				'type' => 'select',
				'options' => OC_Woo_Shipping_Groups::get_all_locations(true),
				'class' => 'ocws-enhanced-select',
			), $fields['shipping']['fields']['shipping_city']);

			$fields['shipping']['fields']['shipping_city'] = $city_args;
		}

		return $fields;
	}

	public function setup_admin_order_columns() {

		OC_Woo_Shipping_Admin_Columns::get_instance();
		OCWS_Admin_Columns::get_instance();
	}

	// Display the custom actions on admin Orders bulk action dropdown
	public function set_companies_orders_bulk_actions( $bulk_actions ) {
		$companies = OC_Woo_Shipping_Companies::get_companies_assoc();
		foreach( $companies as $company_id => $company_name ) {
			$bulk_actions['set_shipping_company_'.$company_id] = sprintf( __('Assign to shipping company %s', 'ocws'), $company_name );
		}
		return $bulk_actions;
	}

	// Process the bulk action from selected orders
	public function set_companies_bulk_action_edit_shop_order( $redirect_to, $action, $post_ids ) {
		$companies = OC_Woo_Shipping_Companies::get_companies_assoc();
		$actions = array();
		foreach( $companies as $company_id => $company_name ) {
			$actions['set_shipping_company_'.$company_id] = sprintf( __('Assign to shipping company %s', 'ocws'), $company_name );
		}

		if ( in_array( $action, array_keys($actions) ) ) {
			$company_id = str_replace('set_shipping_company_', '', $action);
			$company_name = $companies[$company_id];

			$processed_ids = array(); // Initializing

			foreach ( $post_ids as $post_id ) {
				// Save the new value
				update_post_meta( $post_id, '_ocws_shipping_company_id', $company_id );
				update_post_meta( $post_id, '_ocws_shipping_company_name', $company_name );

				$processed_ids[] = $post_id; // Adding processed order IDs to an array
			}

			// Adding the right query vars to the returned URL
			$redirect_to = add_query_arg( array(
				'company_action'   => $action,
				'processed_count' => count( $processed_ids ),
				'processed_ids'   => implode( ',', $processed_ids ),
			), $redirect_to );
		}
		return $redirect_to;
	}

	// Display the results notice from bulk action on orders
	public function set_companies_bulk_action_admin_notice() {
		global $pagenow;

		if ( 'edit.php' === $pagenow && isset($_GET['post_type']) && 'shop_order' === $_GET['post_type']
			&& isset($_GET['company_action']) && isset($_GET['processed_count']) && isset($_GET['processed_ids']) ) {

			$companies = OC_Woo_Shipping_Companies::get_companies_assoc();
			foreach( $companies as $company_id => $company_name ) {
				if (  $_GET['company_action'] === 'set_shipping_company_'.$company_id ) {

					$count = intval( $_GET['processed_count'] );

					printf( '<div class="notice notice-success fade is-dismissible"><p>' .
						_n( '%s selected order assigned to "%s" shipping company.',
							'%s selected orders assigned to "%s" shipping company.',
							$count, 'ocws' )
						. '</p></div>', $count, $company_name );
				}
			}
		}
	}


	public function action_woocommerce_after_edit_attribute_fields() {

		$id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$value = $id ? get_option( "wc_attribute_separate_on_export-$id" ) : '';
		$checked = ($value? 'checked' : '');
		?>

		<tr class="form-field">
			<th valign="top" scope="row">
				<label for="display"><?php _e('Separate on export', ''); ?></label>
			</th>
			<td>
				<input name="separate_on_export" id="separate_on_export" type="checkbox" value="1" <?php echo $checked; ?>>
				<p class="description"><?php _e('Display each product in separate row on export by this attribute', ''); ?></p>
			</td>
		</tr>
		<?php
	}

	public function action_woocommerce_attribute_updated( $id ) {
		if (is_admin()) {
			$option = "wc_attribute_separate_on_export-$id";
			if ( isset( $_POST['separate_on_export'] ) ) {
				update_option( $option, sanitize_text_field( $_POST['separate_on_export'] ) );
			}
			else {
				delete_option( $option );
			}
		}

	}

	public function action_woocommerce_attribute_deleted( $id ) {

		$option = "wc_attribute_separate_on_export-$id";
		delete_option( $option );
	}


	//add_filter( 'woocommerce_admin_billing_fields', 'woo_admin_billing_fields' );

	public function woo_admin_billing_fields( $admin_fields ) {

		$admin_fields['recipient_firstname'] = array(
			'label' => __('Recipient first name', 'ocws'),
			'show' => true,
		);

		$admin_fields['recipient_lastname'] = array(
			'label' => __('Recipient last name', 'ocws'),
			'show' => true,
		);

		$admin_fields['recipient_phone'] = array(
			'label' => __('Recipient phone', 'ocws'),
			'show' => true,
		);

		$admin_fields['recipient_greeting'] = array(
			'label' => __('Personal greeting', 'ocws'),
			'show' => true,
		);

		return $admin_fields;
	}

	public function woo_product_cat_add_new_meta_field() {

		$pages = get_option('ocws_common_export_production_details_pages', false);
		if (!$pages || !is_array($pages)) {
			$pages = array(__('Main', 'ocws'));
		}
		?>
		<div class="form-field">
			<label for="ocws_export_page"><?php _e('Export page', 'ocws'); ?></label>

			<?php foreach ($pages as $id => $title) { ?>
			<div>
				<input type="radio" id="ocws_export_page_<?php echo esc_attr($id) ?>" name="ocws_export_page" value="<?php echo esc_attr($id) ?>">
				<label><?php echo esc_html($title) ?></label>
			</div>

			<?php } ?>

			<p class="description"><?php _e('Choose the export page for this category', 'ocws'); ?></p>
		</div>
		<?php
	}

	public function woo_product_cat_edit_meta_field($term) {

		$pages = get_option('ocws_common_export_production_details_pages', false);
		if (!$pages || !is_array($pages)) {
			$pages = array(__('Main', 'ocws'));
		}

		//getting term ID
		$term_id = $term->term_id;

		// retrieve the existing value(s) for this meta field.
		$export_page = get_term_meta($term_id, 'ocws_export_page', true);
		?>
		<div class="form-field">
			<label for="ocws_export_page"><?php _e('Export page', 'ocws'); ?></label>

			<?php foreach ($pages as $id => $title) { ?>
				<div>
					<input type="radio" id="ocws_export_page_<?php echo esc_attr($id) ?>" name="ocws_export_page" value="<?php echo esc_attr($id) ?>"
						<?php echo ($export_page == $id ? 'checked' : '') ?>>
					<label><?php echo esc_html($title) ?></label>
				</div>

			<?php } ?>

			<p class="description"><?php _e('Choose the export page for this category', 'ocws'); ?></p>
		</div>
		<?php
	}

	public function woo_save_product_cat_custom_meta($term_id) {

		$export_page = filter_input(INPUT_POST, 'ocws_export_page');
		update_term_meta($term_id, 'ocws_export_page', $export_page);
	}

	public function woocommerce_saved_order_items_action( $order_id, $items ) {

		$order = wc_get_order( $order_id );
		if (!$order) return;

		OC_Woo_Shipping_Info::update_in_order($order);
	}

	public function add_billing_street_field($admin_fields) {

		// TODO

		$admin_fields['street'] = array(
			'label' => __('Street', 'ocws'),
			'show' => true,
		);

		return $admin_fields;
	}

	public function reassign_orders_metas() {

		if (wp_doing_ajax()) return;
		// add sortable shipping date meta to orders
		// TODO: run once and comment
		// -------------------------------------------------------------------
		global $wpdb;
		$count =  absint( $wpdb->get_var( "SELECT COUNT( * ) FROM {$wpdb->posts} WHERE post_type = 'shop_order'" ) );

		$chunk_size = 500;
		$first = false;

		$session_offset = isset($_COOKIE['ocws_admin_orders_metas_offset'])? intval($_COOKIE['ocws_admin_orders_metas_offset']) : 0;

		error_log('--------------------- Starting loop for count = ' . $count . ' ---------------------------');
		for($offset = $session_offset; $offset < $count; $offset += $chunk_size) {

			try {
				if ($first) {
					throw new \Exception('One set has been processed');
				}
				error_log('----------------------- memory usage: ' . memory_get_usage() . ' -------------------------');
				error_log('Offset: '.$offset);

				global $wpdb;
				$order_ids = $wpdb->get_col(
					$wpdb->prepare("
                    SELECT posts.ID
				    FROM {$wpdb->posts} AS posts
				    WHERE   posts.post_type = 'shop_order'
				    LIMIT %d, %d",
						$offset, $chunk_size )
				);

				if ( empty( $order_ids ) ) {
					$order_ids = array();
				}
				//error_log(implode(', ', $order_ids));
				foreach ($order_ids as $order_id) {
					$this->reassign_order_metas_for($order_id);
				}
				$first = true;
			}
			catch (\Exception $ex) {
				setcookie('ocws_admin_orders_metas_offset', $offset);
				break;
			}

		}
		// -------------------------------------------------------------------
	}

	private function reassign_order_metas_for($order_id) {

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
	}

}
