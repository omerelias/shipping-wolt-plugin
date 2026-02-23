<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://originalconcepts.co.il/
 * @since      1.0.0
 *
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/public
 * @author     Milla Shub <milla@originalconcepts.co.il>
 */
class Oc_Woo_Shipping_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style( 'select2', plugin_dir_url( __FILE__ ) . 'css/select2.css', array(), $this->version, 'all' );

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/oc-woo-shipping-public.css', array(), $this->version, 'all' );

		if ( function_exists( 'ocws_get_day_limit_overlay_css' ) ) {
			wp_add_inline_style( $this->plugin_name, ocws_get_day_limit_overlay_css() );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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

		wp_enqueue_script( 'select2', plugin_dir_url( __FILE__ ) . 'js/select2/select2.min.js', array( 'jquery' ), $this->version, true );

        if (!is_checkout()) {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jqueryui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css', false, null);
        }
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/oc-woo-shipping-public.js', array( 'jquery' ), $this->version, false );

		// Day-limit: hide add-to-cart/Quickview when li has ocws-day-limit-unavailable
		if ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) ) {
			$ocws_day_limit_js = "(function(\$){ function run(){ \$('.product.ocws-day-limit-unavailable').each(function(){ var \$p=\$(this); var sel='.add_to_cart_button, a[href*=\"add-to-cart\"], button[name=\"add-to-cart\"], [data-jckqvpid], a.iconic-wqv-button, button.iconic-wqv-button, .quantity-wraper, .quantity-wraper-by-units'; \$p.find(sel).addClass('ocws-day-limit-hidden').hide(); }); } \$(function(){ run(); \$(window).on('load', run); }); })(jQuery);";
			wp_add_inline_script( $this->plugin_name, $ocws_day_limit_js, 'after' );
		} 

		$polygons = array();

		if (ocws_use_google_cities_and_polygons()) {

			$data_store = new OC_Woo_Shipping_Group_Data_Store();
			$polygons_raw = $data_store->read_all_polygons();

			foreach ($polygons_raw as $l) {
				$polygon_data = '';
				$polygon_data = @unserialize($l->gm_shapes);

				if (false === $polygon_data) {
					$polygon_data = '';
				}
				if (is_array($polygon_data)) {
					$polygons[] = array(
						'location_code' => $l->location_code,
						'location_name' => $l->location_name,
						'is_enabled' => $l->is_enabled,
						'gm_shapes' => $polygon_data,
					);
				}
			}
		}

		wp_localize_script( $this->plugin_name, 'ocws',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'localize' => array(
					'loading' => __('Loading', 'ocws'),
					'select2' => array(
						'errorLoading' => __('The results could not be loaded', 'ocws'),
						'inputTooLong' => __('Input too long', 'ocws'),
						'inputTooShort' => __('Input too short', 'ocws'),
						'loadingMore' => __('Loading more results…', 'ocws'),
						'noResults' => __('No results found', 'ocws'),
						'searching' => __('Searching…', 'ocws')
					),
					'messages' => array(
						'noHouseNumberInAddress' => __('Please, include house number in the address', 'ocws'),
					),
				),
				'polygons' => $polygons
			));

		$maps_api_key = ocws_get_google_maps_api_key();

		if (ocws_use_google_cities_and_polygons()) {

			wp_register_script('ocws-google-maps-api', 'https://maps.googleapis.com/maps/api/js?key='.$maps_api_key.'&libraries=geometry,places&language=' . get_locale(), null, null, true);
			wp_enqueue_script('ocws-google-maps-init', plugin_dir_url(__FILE__) . 'js/google-maps-init.js', array('jquery', 'ocws-google-maps-api'), null, true);
		}
	}

	/**
	 * Change the checkout city field to a dropdown field.
	 */
	public function change_city_to_dropdown( $fields ) {

		$use_simple_cities = !ocws_use_google_cities_and_polygons();
		$use_polygons = ocws_use_google_cities_and_polygons();
		$use_google_cities = ocws_use_google_cities();

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(true, $use_simple_cities, $use_polygons, $use_google_cities),
			'input_class' => array(
				'ocws-enhanced-select',
			),
			'placeholder' => ''
		), $fields['shipping']['shipping_city'] );

		$fields['shipping']['shipping_city'] = $city_args;

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(true, $use_simple_cities, $use_polygons, $use_google_cities),
			'input_class' => array(
				'ocws-enhanced-select',
			),
			'placeholder' => ''
		), $fields['billing']['billing_city'] );

		error_log('change_city_to_dropdown ---------------------->');
		error_log(print_r($city_args['options'], 1));

		$fields['billing']['billing_city'] = $city_args; // Also change for billing field

		wc_enqueue_js( "
	jQuery( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});" );

		return $fields;

	}

	/**
	 * Change the checkout address fields if there is at least one active polygon.
	 */
	public function change_checkout_address_fields_if_polygon( $fields ) {

		if (!ocws_use_google_cities_and_polygons()) return $fields;

		$hide_city_and_street = true;

		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );
		} else {
			$post_data = $_POST; // fallback for final checkout (non-ajax)
		}

		$raw_address_coords = '';
		if (isset($post_data['billing_address_coords']) && $post_data['billing_address_coords']) {
			$raw_address_coords = $post_data['billing_address_coords'];
		} else if (isset(WC()->session)) {
			$raw_address_coords = WC()->session->get('chosen_address_coords', '');
		}
		$raw_street = '';
		if (isset($post_data['billing_street']) && $post_data['billing_street']) {
			$raw_street = $post_data['billing_street'];
		} else if (isset(WC()->session)) {
			$raw_street = WC()->session->get('chosen_street', '');
		}
		$raw_house_num = '';
		if (isset($post_data['billing_house_num']) && $post_data['billing_house_num']) {
			$raw_house_num = $post_data['billing_house_num'];
		} else if (isset(WC()->session)) {
			$raw_house_num = WC()->session->get('chosen_house_num', '');
		}
		$raw_city_name = '';
		if (isset($post_data['billing_city_name']) && $post_data['billing_city_name']) {
			$raw_city_name = $post_data['billing_city_name'];
		} else if (isset(WC()->session)) {
			$raw_city_name = WC()->session->get('chosen_city_name', '');
		}
		$raw_city_code = '';
		if (isset($post_data['billing_city_code']) && $post_data['billing_city_code']) {
			$raw_city_code = $post_data['billing_city_code'];
		} else if (isset(WC()->session)) {
			$raw_city_code = WC()->session->get('chosen_city_code', '');
		}

		if ($raw_address_coords) {
			$raw_address_coords = wc_clean( wp_unslash( $raw_address_coords ) );
		}

		if ($raw_street) {
			$raw_street = wc_clean( wp_unslash( $raw_street ) );
		}

		if ($raw_house_num) {
			$raw_house_num = wc_clean( wp_unslash( $raw_house_num ) );
		}

		if ($raw_city_name) {
			$raw_city_name = wc_clean( wp_unslash( $raw_city_name ) );
		}

		if ($raw_city_code) {
			$raw_city_code = wc_clean( wp_unslash( $raw_city_code ) );
		}

		$autocomplete_args = wp_parse_args( array(
			'label' => __('Type your address here', 'ocws'),
			'placeholder' => '',
			'required' => false,
			'input_class' => array(
				'ocws-google-address-autocomplete',
			),
			'type' => 'text',
			'class' => array( 'form-row', 'address-autocomplete-field' )
		), $fields['billing']['billing_city'] );

		$fields['billing']['billing_google_autocomplete'] = array (
			'label' => __('Type your address here', 'ocws'),
			'placeholder' => '',
			'required' => false,
			'input_class' => array(
				'ocws-google-address-autocomplete',
			),
			'type' => 'text',
			'class' => array( 'form-row', 'address-autocomplete-field' ),
			'priority' => 1
		);

		$fields_to_rewrite = array(
			'city', 'street', 'house_num'
		);

		foreach ($fields_to_rewrite as $addr_field) {

			if (isset($fields['billing']['billing_' . $addr_field])) {

				$input_class = array();
				$class = array();
				if (
					isset($fields['billing']['billing_' . $addr_field]['input_class']) &&
					is_array($fields['billing']['billing_' . $addr_field]['input_class'])
				) {
					$input_class = array_filter($fields['billing']['billing_' . $addr_field]['input_class'], function ($v) {
						return !strstr($v, 'ocws-enhanced-select');
					});
				}
				if (
					isset($fields['billing']['billing_' . $addr_field]['class']) &&
					is_array($fields['billing']['billing_' . $addr_field]['class'])
				) {
					$class = $fields['billing']['billing_' . $addr_field]['class'];
				}
				$input_class[] = 'ocws-readonly-form-field-input';
				$class[] = 'ocws-readonly-form-field';
				$class[] = 'ocws-polygon-related';

				if ($addr_field !== 'city') {

					$args = wp_parse_args( array(
						'class' => $class,
						'type' => 'text',
						'input_class' => $input_class,
						'custom_attributes' => array('readonly' => 'readonly')
					), $fields['billing']['billing_' . $addr_field] );

					$fields['billing']['billing_' . $addr_field] = $args;
				}
				else {
					$args = wp_parse_args( array(
						'class' => $class,
						'type' => 'text',
						'input_class' => $input_class,
						'placeholder' => __('City', 'ocws'),
						'custom_attributes' => array('readonly' => 'readonly'),
						'priority' => 2
					), $fields['billing']['billing_' . $addr_field] );

					$fields['billing']['billing_' . $addr_field . '_name'] = $args;

					$input_class[] = 'ocws-hidden-form-field-input';
					$class[] = 'ocws-hidden-form-field';

					$args = wp_parse_args( array(
						'class' => $class,
						'type' => 'hidden',
						'input_class' => $input_class
					), $fields['billing']['billing_' . $addr_field] );

					$fields['billing']['billing_' . $addr_field] = $args;
				}
			}
		}

		$address_coords_args = array(
			'type' => 'hidden',
			'class' => array('ocws-hidden-form-field'),
			'priority' => 100,
			'default' => $raw_address_coords
		);

		$fields['billing']['billing_address_coords'] = $address_coords_args;

		$city_code_args = array(
			'type' => 'hidden',
			'class' => array('ocws-hidden-form-field'),
			'priority' => 100,
			'default' => $raw_city_code
		);

		$fields['billing']['billing_city_code'] = $city_code_args;

		$polygon_code_args = array(
			'type' => 'hidden',
			'class' => array('ocws-hidden-form-field'),
			'priority' => 100
		);

		$fields['billing']['billing_polygon_code'] = $polygon_code_args;

		if (isset($fields['billing']['billing_city'])) {
			$fields['billing']['billing_city']['default'] = $raw_city_name;
		}
		if (isset($fields['billing']['billing_city_name'])) {
			$fields['billing']['billing_city_name']['default'] = $raw_city_name;
			if ($hide_city_and_street) {
				if (isset($fields['billing']['billing_city_name']['class'])) {
					$fields['billing']['billing_city_name']['class'][] = 'ocws-hidden-form-field';
				}
				if (isset($fields['billing']['billing_city_name']['input_class'])) {
					$fields['billing']['billing_city_name']['input_class'][] = 'ocws-hidden-form-field-input';
				}
				$fields['billing']['billing_city_name']['type'] = 'hidden';
			}
		}
		if (isset($fields['billing']['billing_house_num'])) {
			$fields['billing']['billing_house_num']['default'] = $raw_house_num;
            $fields['billing']['billing_house_num']['custom_attributes']['readonly'] = 'readonly';

		}
		if (isset($fields['billing']['billing_street'])) {
			$fields['billing']['billing_street']['default'] = $raw_street;
			if ($hide_city_and_street) {
				if (isset($fields['billing']['billing_street']['class'])) {
					$fields['billing']['billing_street']['class'][] = 'ocws-hidden-form-field';
				}
				if (isset($fields['billing']['billing_street']['input_class'])) {
					$fields['billing']['billing_street']['input_class'][] = 'ocws-hidden-form-field-input';
				}
				$fields['billing']['billing_street']['type'] = 'hidden';
			}
		}
		if (isset($fields['billing']['billing_google_autocomplete'])) {
			if (isset($fields['billing']['billing_street']) && isset($fields['billing']['billing_city_name'])) {
				$fields['billing']['billing_google_autocomplete']['default'] = $raw_street . ', ' . $raw_city_name;
				$street_value = WC()->checkout()->get_value( 'billing_street' );
				$city_value = WC()->checkout()->get_value( 'billing_city_name' );
				if ($street_value && $city_value) {
					$fields['billing']['billing_google_autocomplete']['value'] = $street_value . ', ' . $city_value;
				}
			}
		}

		return $fields;

	}

	public function change_checkout_billing_google_autocomplete_field( $field_value, $field_name ) {
		if ($field_name == 'billing_google_autocomplete') {
			$street_value = WC()->checkout()->get_value( 'billing_street' );
			$city_value = WC()->checkout()->get_value( 'billing_city_name' );
			if ($street_value && $city_value) {
				return $street_value . ', ' . $city_value;
			}
		}
		return $field_value;
	}

	public function woocommerce_cart_shipping_packages_filter( $packages ) {

		if (isset($packages[0]) && isset($packages[0]['destination'])) {

			error_log('Destination packages:');

			if ( isset( $_POST['post_data'] ) ) {

				parse_str( $_POST['post_data'], $post_data );

			} else {

				$post_data = $_POST; // fallback for final checkout (non-ajax)

			}

			$aff_id = false;
			if (isset($_POST['ocws_lp_popup']['ocws_lp_pickup_aff_id']) && $_POST['ocws_lp_popup']['ocws_lp_pickup_aff_id']) {
				$aff_id = intval($_POST['ocws_lp_popup']['ocws_lp_pickup_aff_id']);
			}
			else if (isset($post_data['ocws_lp_pickup_aff_id']) && $post_data['ocws_lp_pickup_aff_id']) {
				$aff_id = intval($post_data['ocws_lp_pickup_aff_id']);
			}
			if ($aff_id) {
				$packages[0]['destination']['ocws_lp_pickup_aff_id'] = $aff_id;
			}

			if (ocws_use_google_cities_and_polygons()) {

				$raw_address_coords = '';
				if (isset($post_data['billing_address_coords']) && $post_data['billing_address_coords']) {
					$raw_address_coords = $post_data['billing_address_coords'];
				} else if (isset(WC()->session)) {
					$raw_address_coords = WC()->session->get('chosen_address_coords', '');
				}
				$raw_street = '';
				if (isset($post_data['billing_street']) && $post_data['billing_street']) {
					$raw_street = $post_data['billing_street'];
				} else if (isset(WC()->session)) {
					$raw_street = WC()->session->get('chosen_street', '');
				}
				$raw_house_num = '';
				if (isset($post_data['billing_house_num']) && $post_data['billing_house_num']) {
					$raw_house_num = $post_data['billing_house_num'];
				} else if (isset(WC()->session)) {
					$raw_house_num = WC()->session->get('chosen_house_num', '');
				}
				$raw_city_name = '';
				if (isset($post_data['billing_city_name']) && $post_data['billing_city_name']) {
					$raw_city_name = $post_data['billing_city_name'];
				} else if (isset(WC()->session)) {
					$raw_city_name = WC()->session->get('chosen_city_name', '');
				}
				$raw_city_code = '';
				if (isset($post_data['billing_city_code']) && $post_data['billing_city_code']) {
					$raw_city_code = $post_data['billing_city_code'];
				} else if (isset(WC()->session)) {
					$raw_city_code = WC()->session->get('chosen_city_code', '');
				}
				$address_coords = '';
				$street = '';
				$house_num = '';
				$city_name = '';
				$city_code = '';

				if ($raw_address_coords) {
					$coords = wc_clean( wp_unslash( $raw_address_coords ) );
					$coords = str_replace(array('(', ')', ' '), '', $coords);
					$coords = explode(',', $coords, 2);
					if (isset($coords[0]) && isset($coords[1])) {
						$address_coords = array();
						$address_coords['lat'] = $coords[0];
						$address_coords['lng'] = $coords[1];
					}
				}

				if ($raw_street) {
					$street = wc_clean( wp_unslash( $raw_street ) );
				}

				if ($raw_house_num) {
					$house_num = wc_clean( wp_unslash( $raw_house_num ) );
				}

				if ($raw_city_name) {
					$city_name = wc_clean( wp_unslash( $raw_city_name ) );
				}

				if ($raw_city_code) {
					$city_code = wc_clean( wp_unslash( $raw_city_code ) );
				}

				$packages[0]['destination']['address_coords'] = $address_coords;
				$packages[0]['destination']['street'] = $street;
				$packages[0]['destination']['house_num'] = $house_num;
				$packages[0]['destination']['city_name'] = $city_name;
				$packages[0]['destination']['city_code'] = $city_code;
				$packages[0]['destination']['city'] = $city_name;
			}

			error_log(print_r($packages[0]['destination'], 1));
			error_log( print_r(WC()->session, 1) );
		}

		return $packages;
	}

	/**
	 * Change the checkout street (billing_address_1) field to a dropdown field.
	 */
	public function change_street_to_dropdown( $fields ) {

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		$checkout = WC()->checkout();

		if ( isset( $_POST['post_data'] ) ) {

			parse_str( $_POST['post_data'], $post_data );

		} else {

			$post_data = $_POST; // fallback for final checkout (non-ajax)

		}

		if (empty($chosen_methods)) {
			if (isset($post_data['shipping_method']) && is_array($post_data['shipping_method'])) {
				$chosen_methods = $post_data['shipping_method'];
			}
		}

		if (!isset($post_data['billing_city']) || empty($post_data['billing_city'])) {
			$post_data['billing_city'] = WC()->checkout->get_value('billing_city');
		}

		if (!$post_data['billing_city']) {
			return $fields;
		}

		$data_store = new OC_Woo_Shipping_Group_Data_Store();
		$city_data = $data_store->read_location_data($post_data['billing_city']);

		if (false === $city_data) {
			return $fields;
		}

		$streets_data = @unserialize($city_data->gm_streets);

		if (false === $streets_data || !is_array($streets_data)) {
			return $fields;
		}

		// the city is restricted for some streets

		$street_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''],
			'input_class' => array(
				'ocws-enhanced-select-ajax-streets',
			),
			'placeholder' => __('Start typing a street name', 'ocws')
		), $fields['shipping']['shipping_street'] );

		$fields['shipping']['shipping_street'] = $street_args;

		$street_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''],
			'input_class' => array(
				'ocws-enhanced-select-ajax-streets',
			),
			'placeholder' => __('Start typing a street name', 'ocws')
		), $fields['billing']['billing_street'] );

		$fields['billing']['billing_street'] = $street_args; // Also change for billing field

		/*wc_enqueue_js( "
	jQuery( ':input.ocws-enhanced-select-ajax-streets' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});" );*/

		return $fields;

	}

	/**
	 * Change the default city field to google places autocomplete if using polygon.
	 */
	public function change_default_city_if_polygon( $fields ) {

		if (!ocws_use_google_cities_and_polygons() || !is_account_page()) return $fields;

		$fields['google_autocomplete'] = array (
			'label' => __('Type your address here', 'ocws'),
			'placeholder' => '',
			'required' => false,
			'input_class' => array(
				'ocws-google-address-autocomplete',
			),
			'type' => 'text',
			'class' => array( 'form-row', 'address-autocomplete-field' ),
			'priority' => 8
		);

		$fields_to_rewrite = array(
			'city', 'street', 'house_num'
		);

		foreach ($fields_to_rewrite as $addr_field) {
			if (isset($fields[$addr_field])) {
				$input_class = array();
				$class = array();
				if (
					isset($fields[$addr_field]['input_class']) &&
					is_array($fields[$addr_field]['input_class'])
				) {
					$input_class = array_filter($fields[$addr_field]['input_class'], function ($v) {
						return !strstr($v, 'ocws-enhanced-select');
					});
				}
				if (
					isset($fields[$addr_field]['class']) &&
					is_array($fields[$addr_field]['class'])
				) {
					$class = $fields[$addr_field]['class'];
				}
				$input_class[] = 'ocws-readonly-form-field-input';
				$class[] = 'ocws-readonly-form-field';
				$class[] = 'ocws-polygon-related';
				if ($addr_field == 'city' || $addr_field == 'street') {
					$input_class[] = 'ocws-hidden-form-field-input';
					$class[] = 'ocws-hidden-form-field';
				}
				if ($addr_field == 'city') {
					$args = wp_parse_args( array(
						'class' => $class,
						'type' => 'hidden',
						'input_class' => $input_class,
						'placeholder' => __('City', 'ocws')
					), $fields['billing']['billing_' . $addr_field] );

					$fields['city'] = $args;
				}
				else {
					$args = wp_parse_args( array(
						'class' => $class,
						'type' => ($addr_field == 'street'? 'hidden' : 'text'),
						'input_class' => $input_class,
						'custom_attributes' => array('readonly' => 'readonly')
					), $fields[$addr_field] );

					$fields[$addr_field] = $args;
				}
			}
		}
		$address_coords_args = array(
			'type' => 'hidden',
			'class' => array('ocws-hidden-form-field'),
			'priority' => 100,
			//'default' => $raw_address_coords
		);

		$fields['address_coords'] = $address_coords_args;

		$city_code_args = array(
			'type' => 'hidden',
			'class' => array('ocws-hidden-form-field'),
			'priority' => 100,
		);

		$fields['city_code'] = $city_code_args;

		$polygon_code_args = array(
			'type' => 'hidden',
			'class' => array('ocws-hidden-form-field'),
			'priority' => 100
		);

		$fields['polygon_code'] = $polygon_code_args;

		return $fields;

	}

	/**
	 * Change the default city field to a dropdown field.
	 */
	public function change_default_city_to_dropdown( $fields ) {

		$use_simple_cities = !ocws_use_google_cities_and_polygons();
		$use_polygons = ocws_use_google_cities_and_polygons();
		$use_google_cities = ocws_use_google_cities();

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(false, $use_simple_cities, $use_polygons, $use_google_cities),
			'input_class' => array(
				'ocws-enhanced-select',
			),
			'placeholder' => __('Locality', 'ocws')
		), $fields['city'] );

		$fields['city'] = $city_args;

		wc_enqueue_js( "
	jQuery( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});" );

		return $fields;

	}

	public function trigger_update_checkout_on_change( $fields ) {

		// TODO: change fields if polygon

		$field_names = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_company',
			'billing_company_num',
			'billing_street',
			'billing_address_1',
			'billing_house_num',
			'billing_apartment',
			'billing_floor',
			'billing_enter_code',
			'billing_notes'
		);

		foreach ($field_names as $field_name) {
			if (isset($fields['billing'][$field_name])) {
				$fields['billing'][$field_name]['class'][] = 'ocws_update_checkout_on_change';
			}
		}

		return $fields;
	}

	public function change_default_guest_billing_fields( $fields ) {

		if (is_user_logged_in()) {
			return $fields;
		}

		if ( isset( $_POST['post_data'] ) ) {

			parse_str( $_POST['post_data'], $post_data );

		} else {

			$post_data = $_POST; // fallback for final checkout (non-ajax)

		}

		$field_names = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_company',
			'billing_company_num',
			'billing_street',
			'billing_address_1',
			'billing_house_num',
			'billing_apartment',
			'billing_floor',
			'billing_enter_code',
			'billing_notes'
		);

		foreach ($field_names as $field_name) {
			if (isset($fields['billing'][$field_name])) {
				$fields['billing'][$field_name]['default'] = isset($post_data[$field_name])? $post_data[$field_name] : ( isset( $fields['billing'][$field_name]['default'] )? $fields['billing'][$field_name]['default'] : '' );
			}
		}

		return $fields;
	}

	public function change_checkout_user_billing_field( $field_value, $field_name ) {

		if ( isset( $_POST['post_data'] ) ) {

			parse_str( $_POST['post_data'], $post_data );

		} else {

			$post_data = $_POST; // fallback for final checkout (non-ajax)

		}

		if ( ! empty( $post_data[ $field_name ] ) ) {
			return wc_clean( wp_unslash( $post_data[ $field_name ] ) );
		}

		$data = WC()->session->get( 'checkout_data' );
		if ( $data && isset($data[$field_name]) && !empty( $data[$field_name] ) ) {
			return is_bool( $data[$field_name] ) ? (int) $data[$field_name] : $data[$field_name];
		}
		return $field_value;
	}

	public function save_checkout_data_to_session( $posted_data ) {
		parse_str( $posted_data, $output );
		WC()->session->set( 'checkout_data', $output );
	}

	public function checkout_add_slot_date_time_fields( $fields ) {

		if ( isset( $_POST['post_data'] ) ) {

			parse_str( $_POST['post_data'], $post_data );

		} else {

			$post_data = $_POST; // fallback for final checkout (non-ajax)

		}

		$selected_slot = OC_Woo_Shipping_Info::get_shipping_info();
		$popup_shipping_info = OC_Woo_Shipping_Info::get_shipping_info_from_session();

		$selected_slot_arr = array(
			'date' => '',
			'slot_start' => '',
			'slot_end' => ''
		);
		if (null !== $selected_slot) {
			if (isset($selected_slot['date']) && $selected_slot['date']) {
				$selected_slot_arr['date'] = $selected_slot['date'];
			}
			else if ($popup_shipping_info['date']) {
				$selected_slot_arr['date'] = $popup_shipping_info['date'];
			}
			if (isset($selected_slot['slot_start']) && $selected_slot['slot_start']) {
				$selected_slot_arr['slot_start'] = $selected_slot['slot_start'];
			}
			else if ($popup_shipping_info['slot_start']) {
				$selected_slot_arr['slot_start'] = $popup_shipping_info['slot_start'];
			}
			if (isset($selected_slot['slot_end']) && $selected_slot['slot_end']) {
				$selected_slot_arr['slot_end'] = $selected_slot['slot_end'];
			}
			else if ($popup_shipping_info['slot_end']) {
				$selected_slot_arr['slot_end'] = $popup_shipping_info['slot_end'];
			}
		}

		$field_names = array(
			'order_expedition_date' => $selected_slot_arr['date'],
			'order_expedition_slot_start' => $selected_slot_arr['slot_start'],
			'order_expedition_slot_end' => $selected_slot_arr['slot_end'],
			'slots_state' => ''
		);

		foreach ($field_names as $field_name => $field_value) {

			$fields['ocws'][$field_name] = array(
				'required' => false,
				'type' => 'hidden',
				'default' => $field_value
			);
		}

		return $fields;
	}

	/**
	 * Render shipping method additional fields
	 * @return void
	 */
	public function render_shipping_additional_fields( $checkout=null )
	{
		ocws_render_shipping_additional_fields();
	}

	/**
	 * @param array $metaKeys
	 * @return array
	 */
    public function hidden_order_itemmeta($metaKeys)
	{
		$metaKeys[] = 'ocws_shipping_info';
		$metaKeys[] = 'ocws_lp_pickup_info';
		$metaKeys[] = 'ocws_shipping_info_date';
		$metaKeys[] = 'ocws_shipping_info_date_ts';
		$metaKeys[] = 'ocws_shipping_info_slot_start';
		$metaKeys[] = 'ocws_shipping_info_slot_end';

		$metaKeys[] = 'ocws_leave_at_the_door';
		$metaKeys[] = 'ocws_other_products';

		return $metaKeys;
	}

	/**
	 * @param string $text
	 * @param \WC_Order $order
	 * @return string
	 */
	public function email_shipping_info($text, $order)
	{
		// only in customer emails
		$html = OC_Woo_Shipping_Info::render_formatted_shipping_info( $order );
		//$html = '';
		return ($text . $html);
	}

	/**
	 * @param \WC_Order $order
	 * @return void
	 */
	public function order_details_after_order_table($order)
	{
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
	 * @param int $orderId
	 * @param array $data
	 * @param \WC_Order $order
	 */
	public function save_shipping_to_order($orderId, $data, $order)
	{
		OC_Woo_Shipping_Info::save_to_order($order);
	}

	public function validate_shipping_info()
	{
		$message = '<ul class="woocommerce-error" role="alert"><li>%s</li></ul>';
		$response = array(
			'messages'  => '',
			'refresh'   => false,
			'reload'    => false,
			'result'    => 'failure'
		);

		if (!isset($_POST['shipping_method'])) {
			return;
		}

		$shipping_methods = $_POST['shipping_method'];
		$is_ocws = false;

		foreach ($shipping_methods as $shipping_method) {
			if (substr($shipping_method, 0, strlen('oc_woo_advanced_shipping_method')) == 'oc_woo_advanced_shipping_method') {
				$is_ocws = true;
				break;
			}
		}

		//if ($is_ocws) {
			//$shipping_info = OC_Woo_Shipping_Info::get_shipping_info();
			//if (!$shipping_info || !$shipping_info['date'] || !$shipping_info['slot_start'] || !$shipping_info['slot_end'] ) {
				//$response['messages'] = sprintf($message, __('Please choose time slot', 'ocws'));
				//header('Content-type: application/json');
				//echo json_encode($response);
				//exit;
			//}
		//}
	}

	public function custom_checkout_field() {

		$chck1 = get_option('ocws_common_enable_at_the_door_checkbox', '');
		$chck2 = get_option('ocws_common_enable_other_products_checkbox', '');

		if (!empty($chck1)) {
			echo '<div id="ocws_leave_at_the_door">';

			woocommerce_form_field( 'ocws_leave_at_the_door', array(
				'type'      => 'checkbox',
				'class'     => array('input-checkbox'),
				'label'     => __($chck1, 'ocws'),
			),  WC()->checkout->get_value( 'ocws_leave_at_the_door' ) );
			echo '</div>';
		}

		if (!empty($chck2)) {
			echo '<div id="ocws_custom_checkout_field">';

			woocommerce_form_field( 'ocws_other_products', array(
				'type'      => 'checkbox',
				'class'     => array('input-checkbox'),
				'label'     => __($chck2, 'ocws'),
			),  WC()->checkout->get_value( 'ocws_other_products' ) );
			echo '</div>';
		}
	}

	public function custom_checkout_field_update_order_meta( $order_id ) {

		if ( ! empty( $_POST['ocws_leave_at_the_door'] ) )
			update_post_meta( $order_id, 'ocws_leave_at_the_door', $_POST['ocws_leave_at_the_door'] );

		if ( ! empty( $_POST['ocws_other_products'] ) )
			update_post_meta( $order_id, 'ocws_other_products', $_POST['ocws_other_products'] );

	}

	public function print_shipping_notices() {

		?>
		<div class="ocws-shipping-notices">
		<?php OC_Woo_Shipping_Notices::print_notices(); ?>
		<?php OC_Woo_Shipping_Notices::print_notices('ocws_lp_notices'); ?>
		</div>
		<?php
	}

	public function add_checkout_shipping_methods_fragment( $arr ) {
		global $woocommerce;
		$html = '';

        $disabled = false;
        $items = $woocommerce->cart->get_cart();
        foreach($items as $item => $values) {
            $parent = $values['data']->get_parent_id();
            $current = $values['data']->get_id();

            if (get_post_meta($parent == 0 ? $current : $parent, '_ocws_pickup_only', 'no') == 'yes') {
                $disabled = true;
                break;
            }
        }
        if ($disabled) {

			$message = get_option('ocws_common_pickup_only_message');
			if (empty($message)) {
				$message = __( 'Sorry, your cart contains pickup only products', 'ocws' );
			}
            if (!OC_Woo_Shipping_Notices::has_notice( $message, 'permanent-notice' )) {
                OC_Woo_Shipping_Notices::add_notice( $message, 'permanent-notice' );
            }
        }

		ob_start();

		?>

		<div class="header-shipping-methods">
			<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) { ?>

				<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
				<div class="ship-title"><?php _e('Shipping methods' , 'ocws');?></div>
				<?php wc_cart_totals_shipping_html(); ?>

				<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

			<?php } else { ?>

				<div>
					<?php //echo "WC()->cart->needs_shipping() - " . (WC()->cart->needs_shipping()? 'yes' : 'no') ?>
				</div>
				<div>
					<?php //echo "wc_shipping_enabled() - " . (wc_shipping_enabled()? 'yes' : 'no') ?>
				</div>
				<div>
					<?php //echo "wc_get_shipping_method_count( true ) - " . (wc_get_shipping_method_count( true )) ?>
				</div>
				<div>
					<?php //echo "WC()->cart->show_shipping() - " . (WC()->cart->show_shipping()? 'yes' : 'no') ?>
				</div>

			<?php } ?>
		</div>

		<?php

		$html = ob_get_clean();

		$arr['.header-shipping-methods'] = $html;

		ob_start();

		$this->render_shipping_additional_fields();

		$html = ob_get_clean();

		$arr['#oc-woo-shipping-additional'] = $html;

		ob_start();

		WC()->checkout()->checkout_form_billing();

		$billing_form = ob_get_clean();

		$html = str_get_html($billing_form);

		if ($html) {
			$ret = $html->find('div.woocommerce-billing-fields', 0);
			$arr['.woocommerce-billing-fields'] = $ret->outertext;
		}
		else {
			$arr['.woocommerce-billing-fields'] = 'MAX_FILE_SIZE: '.MAX_FILE_SIZE.', strlen: '.strlen($billing_form);
		}

		return $arr;
	}

	public function add_shipping_popup() {

		if (is_checkout() || wp_doing_ajax()) return;
		$customer_id = get_current_user_id();
		
		// added checkbox in general settings 
		$use_popup 	= get_option('ocws_common_use_popup');
		if ( !$use_popup || $use_popup == '' ){
			return;
		}

		/*if ($customer_id) {
			$customer = new WC_Customer( $customer_id );
			$city_code = $customer->get_billing_city();
			if ($city_code) return;
		}*/


		$show_popup = false;
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		if (empty($chosen_methods)) {
			$show_popup = true;
		}
		else {
			$is_ocws = false;

			echo '<div id="popup_chosen_shipping_methods" style="display: none;">'.print_r($chosen_methods, 1).'</div>';

			foreach ($chosen_methods as $shippingMethod) {
				if (substr($shippingMethod, 0, strlen('oc_woo_advanced_shipping_method')) == 'oc_woo_advanced_shipping_method') {
					$is_ocws = true;
					break;
				}
			}
			if ($is_ocws) {
				$chosen_city = WC()->checkout->get_value('billing_city');
				if (!$chosen_city || !ocws_is_location_enabled($chosen_city)) {
					$show_popup = true;
				}
				else {
					$popup_shipping_info = OC_Woo_Shipping_Info::get_shipping_info_from_session();
					if (!$popup_shipping_info['date']) {
						$show_popup = true;
					}
				}
			}
		}

		if (!$show_popup) {
			//return;
		}
		?>

		<?php

		/*$template = ocws_get_template_part('public/popup.php');
		if (!empty($template) && file_exists($template)) {
			include($template);
		}*/
		OCWS_Popup::output_shipping_popup();

		?>



		<?php

		wc_enqueue_js( "
	jQuery( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});" );
	}

	public function add_checkout_choose_city_popup() {

		if (!is_checkout() || wp_doing_ajax()) return;

		$template = ocws_get_template_part('public/checkout-popup.php');
		if (!empty($template) && file_exists($template)) {
			include($template);
		}

		wc_enqueue_js( "
	jQuery( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});" );
	}

	/**
	 * Filter the cart template path to use our cart.php template instead of the theme's
	 */
	public function locate_woo_template( $template, $template_name, $template_path ) {
		$basename = basename( $template );
		if( $basename == 'cart-shipping.php' ) {
			$template = trailingslashit( plugin_dir_path( dirname( __FILE__ ) ) ) . 'templates/cart-shipping.php';
		}
		return $template;
	}


	//add_filter( 'ocws_send_to_other_person_fields', 'ocws_send_to_other_person_fields' );
	public function ocws_send_to_other_person_fields() {

		if (isset($_POST['post_data'])) {

			parse_str($_POST['post_data'], $post_data);

		} else {

			$post_data = $_POST; // fallback for final checkout (non-ajax)

		}

		$chosen_methods = WC()->session->get('chosen_shipping_methods');
		$chosen_shipping = $chosen_methods[0];
		$local_pickup_chosen = ($chosen_shipping && strstr($chosen_shipping, 'local_pickup'));

		if ($local_pickup_chosen) return;

		$send_to_other_person_default = get_option('ocws_common_enable_send_to_other_checked_by_default', '') != 1 ? 0 : 1;

		$send_to_other_person_hidden = (isset($post_data['ocws_other_recipient_hidden']) && in_array($post_data['ocws_other_recipient_hidden'], array('yes', 'no'))) ? $post_data['ocws_other_recipient_hidden'] : '';

		if ($send_to_other_person_hidden == '') {
			$send_to_other_person_hidden = ocws_get_session_checkout_field('ocws_other_recipient_hidden');
		}
		if ($send_to_other_person_hidden == '') {
			$send_to_other_person = $send_to_other_person_default;
		}
		else {
			if ($send_to_other_person_hidden == 'yes' && (isset($post_data['ocws_other_recipient']) || ocws_get_session_checkout_field('ocws_other_recipient'))) {
				$send_to_other_person = 1;
			}
			else {
				$send_to_other_person = 0;
			}
		}

		//echo '<div id="ocws_other_recipient_container">';

		$l = get_option('ocws_common_checkout_send_to_other_checkbox_label');

		if (empty($l)) {
			$general_options_defaults = OC_Woo_Shipping_Group_Option::get_general_options_defaults();
			if (isset($general_options_defaults['checkout_send_to_other_checkbox_label'])) {
				$l = $general_options_defaults['checkout_send_to_other_checkbox_label'];
			}
		}

		woocommerce_form_field( 'ocws_other_recipient', array(
			'type'      => 'checkbox',
			'class'     => array('form-row-wide', 'other-recipient-field', 'checkbox', WC()->checkout->get_value( 'ocws_other_recipient' ), 'ocws_update_checkout_on_change'),
			'label'     => (empty($l)? __('Send to other person', 'ocws') : $l),
			'clear'		=> false,
		),  $send_to_other_person );
		//echo '</div>';

		woocommerce_form_field( 'ocws_other_recipient_hidden', array(
			'type'      => 'hidden',
			'class' => array('ocws_update_checkout_on_change')
		),  $send_to_other_person_hidden );

		if (!$send_to_other_person) return;

		//echo '<p class="form-row form-row-first other-recipient-field" style="'.($send_to_other_person? '' : 'display: none;').'">';

		$ocws_recipient_firstname = ocws_get_value( 'ocws_recipient_firstname', $post_data );
		if (empty($ocws_recipient_firstname)) {
			$ocws_recipient_firstname = ocws_get_session_checkout_field('ocws_recipient_firstname');
		}
		woocommerce_form_field( 'ocws_recipient_firstname', array(
			'type'      => 'text',
			'class'     => array('form-row-wide', 'other-recipient-field', 'other-recipient-field-toggle', 'ocws_update_checkout_on_change'),
			'label'     => __('Recipient first name', 'ocws'),
			'clear'		=> false,
          	'placeholder' => __('Recipient first name', 'ocws') . ' *',
			'required'	=> !!$send_to_other_person,
		),  $ocws_recipient_firstname );
		//echo '</p>';

		//echo '<p class="form-row form-row-last other-recipient-field" style="'.($send_to_other_person? '' : 'display: none;').'">';

		$ocws_recipient_lastname = ocws_get_value( 'ocws_recipient_lastname', $post_data );
		if (empty($ocws_recipient_lastname)) {
			$ocws_recipient_lastname = ocws_get_session_checkout_field('ocws_recipient_lastname');
		}
		woocommerce_form_field( 'ocws_recipient_lastname', array(
			'type'      => 'text',
			'class'     => array('form-row-wide', 'other-recipient-field', 'other-recipient-field-toggle', 'ocws_update_checkout_on_change'),
			'label'     => __('Recipient last name', 'ocws'),
			'clear'		=> false,
          	'placeholder' => __('Recipient last name', 'ocws') . ' *',
			'required'	=> !!$send_to_other_person,
		),  $ocws_recipient_lastname );
		//echo '</p>';

		//echo '<p class="form-row form-row-wide other-recipient-field" style="'.($send_to_other_person? '' : 'display: none;').'">';

		$ocws_recipient_phone = ocws_get_value( 'ocws_recipient_phone', $post_data );
		if (empty($ocws_recipient_phone)) {
			$ocws_recipient_phone = ocws_get_session_checkout_field('ocws_recipient_phone');
		}
		woocommerce_form_field( 'ocws_recipient_phone', array(
			'type'      => 'text',
			'class'     => array('form-row-wide', 'other-recipient-field', 'other-recipient-field-toggle', 'ocws_update_checkout_on_change'),
			'label'     => __('Recipient phone', 'ocws'),
			'clear'		=> false,
          	'placeholder' => __('Recipient phone', 'ocws') . ' *',
			'required'	=> !!$send_to_other_person,
		),  $ocws_recipient_phone );
		//echo '</p>';

	}


	//add_filter( 'ocws_send_to_other_person_greeting', 'ocws_send_to_other_person_greeting' );
	public function ocws_send_to_other_person_greeting() {

		if ( isset( $_POST['post_data'] ) ) {

			parse_str( $_POST['post_data'], $post_data );

		} else {

			$post_data = $_POST; // fallback for final checkout (non-ajax)

		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		$local_pickup_chosen = ($chosen_shipping && (strstr($chosen_shipping, 'local_pickup')));

		if ($local_pickup_chosen) return;

		$send_to_other_person_default = get_option('ocws_common_enable_send_to_other_checked_by_default', '') != 1 ? 0 : 1;

		$send_to_other_person_hidden = ( isset($post_data['ocws_other_recipient_hidden']) && in_array( $post_data['ocws_other_recipient_hidden'], array('yes', 'no') ) )? $post_data['ocws_other_recipient_hidden'] : '';

		if ($send_to_other_person_hidden == '') {
			$send_to_other_person = $send_to_other_person_default;
		}
		else {
			if ($send_to_other_person_hidden == 'yes' && isset($post_data['ocws_other_recipient'])) {
				$send_to_other_person = 1;
			}
			else {
				$send_to_other_person = 0;
			}
		}

		if (!$send_to_other_person) return;

		$enable_greeting = get_option('ocws_common_enable_greeting_field', '') != 1 ? 0 : 1;

		if (!$enable_greeting) return;

		$ocws_recipient_greeting = ocws_get_value( 'ocws_recipient_greeting', $post_data );
		if (empty($ocws_recipient_greeting)) {
			$ocws_recipient_greeting = ocws_get_session_checkout_field('ocws_recipient_greeting');
		}
		woocommerce_form_field( 'ocws_recipient_greeting', array(
			'type'      => 'textarea',
			'class'     => array('form-row-wide', 'other-recipient-field', 'other-recipient-field-toggle', 'ocws_update_checkout_on_change'),
			'label'     => __('Greeting', 'ocws'),
			'clear'		=> false,
			'placeholder' => __('Type your greeting here', 'ocws'),
			'required'	=> false,
		),  $ocws_recipient_greeting);

	}


	//add_filter( 'woocommerce_checkout_order_processed' , 'woo_checkout_order_processed', 10, 1 );
	public function woo_checkout_order_processed( $order_id, $data, $order ) {

		$send_to_other_person = (isset($_POST['ocws_other_recipient']) && !empty($_POST['ocws_other_recipient']));

		if ($send_to_other_person) {

			update_post_meta( $order_id, 'ocws_other_recipient', 1 );

			if ( isset($_POST['ocws_recipient_firstname']) && ! empty( $_POST['ocws_recipient_firstname'] ) ) {
				update_post_meta( $order_id, 'ocws_recipient_firstname', $_POST['ocws_recipient_firstname'] );
				update_post_meta( $order_id, '_shipping_first_name', $_POST['ocws_recipient_firstname'] );
			}

			if ( isset($_POST['ocws_recipient_lastname']) && ! empty( $_POST['ocws_recipient_lastname'] ) ) {
				update_post_meta( $order_id, 'ocws_recipient_lastname', $_POST['ocws_recipient_lastname'] );
				update_post_meta( $order_id, '_shipping_last_name', $_POST['ocws_recipient_lastname'] );
			}

			if ( isset($_POST['ocws_recipient_phone']) && ! empty( $_POST['ocws_recipient_phone'] ) ) {
				update_post_meta( $order_id, 'ocws_recipient_phone', $_POST['ocws_recipient_phone'] );
				update_post_meta( $order_id, '_shipping_phone', $_POST['ocws_recipient_phone'] );
			}

			if ( isset($_POST['ocws_recipient_greeting']) && ! empty( $_POST['ocws_recipient_greeting'] ) ) {
				update_post_meta( $order_id, 'ocws_recipient_greeting', $_POST['ocws_recipient_greeting'] );
				update_post_meta( $order_id, '_shipping_greeting', $_POST['ocws_recipient_greeting'] );
			}

		}

	}

	public function woo_checkout_add_shipping_phone( $fields ) {

		$fields['shipping']['shipping_phone'] = array(
			'label' => 'Phone',
			'required' => false,
			'class' => array( 'form-row-wide' ),
			'priority' => 25,
		);
		return $fields;

	}

	/* 'woocommerce_checkout_fields' filter*/
	public function woo_checkout_add_billing_street( $fields ) {

		$street_args = wp_parse_args( array(
			'label'        => __( 'Street', 'ocws' ),
			'placeholder'  => esc_attr__( 'Street', 'ocws' ),
			'required'     => true,
		), $fields['billing']['billing_address_1'] );

		$fields['billing']['billing_street'] = $street_args;

		$fields['billing']['billing_address_1']['required'] = false;
		$fields['billing']['billing_address_1']['class'][] = 'ocws-hidden-form-field';
		return $fields;

	}

	public function add_default_billing_street( $fields ) {

		$street_args = wp_parse_args( array(
			'label'        => __( 'Street', 'ocws' ),
			'placeholder'  => esc_attr__( 'Street', 'ocws' ),
			'required'     => true,
		), $fields['address_1'] );

		$fields['street'] = $street_args;

		$fields['address_1']['required'] = false;
		$fields['address_1']['class'][] = 'ocws-hidden-form-field';

		return $fields;

	}

	/**
	 * @param int $orderId
	 * @param array $data
	 * @param \WC_Order $order
	 */
	public function save_full_address_to_order($orderId, $data, $order)	{

		ocws_save_full_address_to_order($order);
	}

	public function woocommerce_customer_save_address_action($user_id, $load_address) {

		if ($load_address == 'billing') {

			update_user_meta($user_id, 'billing_address_1', get_user_meta($user_id, 'billing_street', true) . ' ' . get_user_meta($user_id, 'billing_house_num', true));
		}
		else if ($load_address == 'shipping') {

			update_user_meta($user_id, 'shipping_address_1', get_user_meta($user_id, 'shipping_street', true) . ' ' . get_user_meta($user_id, 'shipping_house_num', true));
		}
	}

	public function process_checkout_field_billing_city($value) {

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );

		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );
		} else {
			$post_data = $_POST; // fallback for final checkout (non-ajax)
		}

		if (empty($chosen_methods)) {
			if (isset($post_data['shipping_method']) && is_array($post_data['shipping_method'])) {
				$chosen_methods = $post_data['shipping_method'];
			}
		}
		$is_ocws = false;

		foreach ($chosen_methods as $shippingMethod) {
			if (substr($shippingMethod, 0, strlen('oc_woo_advanced_shipping_method')) == 'oc_woo_advanced_shipping_method') {
				$is_ocws = true;
				break;
			}
		}
		if ($is_ocws && ocws_use_google_cities_and_polygons() && isset($post_data['billing_city_name'])) {
			return $post_data['billing_city_name'];
		}
		return $value;
	}

	/*public function process_checkout_field_billing_google_autocomplete($value) {

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );

		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );
		} else {
			$post_data = $_POST; // fallback for final checkout (non-ajax)
		}

		if (empty($chosen_methods)) {
			if (isset($post_data['shipping_method']) && is_array($post_data['shipping_method'])) {
				$chosen_methods = $post_data['shipping_method'];
			}
		}
		$is_ocws = false;

		foreach ($chosen_methods as $shippingMethod) {
			if (substr($shippingMethod, 0, strlen('oc_woo_advanced_shipping_method')) == 'oc_woo_advanced_shipping_method') {
				$is_ocws = true;
				break;
			}
		}
		if ($is_ocws && ocws_use_google_cities_and_polygons() && isset($post_data['billing_city_name']) && isset($post_data['billing_street'])) {
			return $post_data['billing_city_name'] . ', ' . $post_data['billing_street'];
		}
		return $value;
	}*/

}
