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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/oc-woo-shipping-public.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name, 'ocws',
			array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

	}

	/**
	 * Change the checkout city field to a dropdown field.
	 */
	public function change_city_to_dropdown( $fields ) {

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(true),
			'input_class' => array(
				'ocws-enhanced-select',
			),
			'placeholder' => ''
		), $fields['shipping']['shipping_city'] );

		$fields['shipping']['shipping_city'] = $city_args;

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(true),
			'input_class' => array(
				'ocws-enhanced-select',
			),
			'placeholder' => ''
		), $fields['billing']['billing_city'] );

		$fields['billing']['billing_city'] = $city_args; // Also change for billing field

		wc_enqueue_js( "
	jQuery( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});" );

		return $fields;

	}

	/**
	 * Change the default city field to a dropdown field.
	 */
	public function change_default_city_to_dropdown( $fields ) {

		$city_args = wp_parse_args( array(
			'type' => 'select',
			'options' => ['' => ''] + OC_Woo_Shipping_Groups::get_all_locations(),
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
			'billing_phone'
		);

		foreach ($field_names as $field_name) {
			if (isset($fields['billing'][$field_name])) {
				$fields['billing'][$field_name]['default'] = isset($post_data[$field_name])? $post_data[$field_name] : ( isset( $fields['billing'][$field_name]['default'] )? $fields['billing'][$field_name]['default'] : '' );
			}
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

		if ($is_ocws) {
			$shipping_info = OC_Woo_Shipping_Info::get_shipping_info();
			if (!$shipping_info || !$shipping_info['date'] || !$shipping_info['slot_start'] || !$shipping_info['slot_end'] ) {
				$response['messages'] = sprintf($message, __('Please choose time slot', 'ocws'));
				header('Content-type: application/json');
				echo json_encode($response);
				exit;
			}
		}
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
		</div>
		<?php
	}

	public function add_checkout_shipping_methods_fragment( $arr ) {
		global $woocommerce;
		$html = '';

		ob_start();

		?>

		<div class="header-shipping-methods">
			<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) { ?>

				<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
				<div class="ship-title"><?php _e('איך תרצו לקבל?' , 'meshek');?></div>
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

		$ret = $html->find('div.woocommerce-billing-fields', 0);

		$arr['.woocommerce-billing-fields'] = $ret->outertext;
		return $arr;
	}

	public function add_shipping_popup() {

		if (is_checkout() || wp_doing_ajax()) return;
		$customer_id = get_current_user_id();

		if ($customer_id) {
			$customer = new WC_Customer( $customer_id );
			$city_code = $customer->get_billing_city();
			if ($city_code) return;
		}
		?>

		<?php

		$template = ocws_get_template_part('public/popup.php');
		if (!empty($template) && file_exists($template)) {
			include($template);
		}

		?>



		<?php

		wc_enqueue_js( "
	jQuery( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});" );
	}

	public function add_checkout_choose_city_popup() {

		if (is_checkout() || wp_doing_ajax()) return;
		$customer_id = get_current_user_id();

		if ($customer_id) {
			$customer = new WC_Customer( $customer_id );
			$city_code = $customer->get_billing_city();
			if ($city_code) return;
		}
		?>

		<?php

		$template = ocws_get_template_part('public/checkout-popup.php');
		if (!empty($template) && file_exists($template)) {
			include($template);
		}

		?>



		<?php

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


	//add_filter( 'woocommerce_before_checkout_billing_form', 'woo_before_checkout_billing_form' );
	public function woo_before_checkout_billing_form() {

		if ( isset( $_POST['post_data'] ) ) {

			parse_str( $_POST['post_data'], $post_data );

		} else {

			$post_data = $_POST; // fallback for final checkout (non-ajax)

		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		$local_pickup_chosen = ($chosen_shipping && (substr($chosen_shipping, 0, 12) === 'local_pickup'));

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

		//echo '<div id="ocws_other_recipient_container">';

		woocommerce_form_field( 'ocws_other_recipient', array(
			'type'      => 'checkbox',
			'class'     => array('form-row-wide', 'other-recipient-field', 'checkbox', WC()->checkout->get_value( 'ocws_other_recipient' )),
			'label'     => __('Send to other person', 'ocws'),
			'clear'		=> false,
		),  $send_to_other_person );
		//echo '</div>';

		woocommerce_form_field( 'ocws_other_recipient_hidden', array(
			'type'      => 'hidden'
		),  $send_to_other_person_hidden );

		if (!$send_to_other_person) return;

		//echo '<p class="form-row form-row-first other-recipient-field" style="'.($send_to_other_person? '' : 'display: none;').'">';

		woocommerce_form_field( 'ocws_recipient_firstname', array(
			'type'      => 'text',
			'class'     => array('form-row-wide', 'other-recipient-field', 'other-recipient-field-toggle'),
			'label'     => __('Recipient first name', 'ocws'),
			'clear'		=> false,
          	'placeholder' => __('Recipient first name', 'ocws'),
			'required'	=> !!$send_to_other_person,
		),  WC()->checkout->get_value( 'ocws_recipient_firstname' ) );
		//echo '</p>';

		//echo '<p class="form-row form-row-last other-recipient-field" style="'.($send_to_other_person? '' : 'display: none;').'">';

		woocommerce_form_field( 'ocws_recipient_lastname', array(
			'type'      => 'text',
			'class'     => array('form-row-wide', 'other-recipient-field', 'other-recipient-field-toggle'),
			'label'     => __('Recipient last name', 'ocws'),
			'clear'		=> false,
          	'placeholder' => __('Recipient last name', 'ocws'),
			'required'	=> !!$send_to_other_person,
		),  WC()->checkout->get_value( 'ocws_recipient_lastname' ) );
		//echo '</p>';

		//echo '<p class="form-row form-row-wide other-recipient-field" style="'.($send_to_other_person? '' : 'display: none;').'">';

		woocommerce_form_field( 'ocws_recipient_phone', array(
			'type'      => 'text',
			'class'     => array('form-row-wide', 'other-recipient-field', 'other-recipient-field-toggle'),
			'label'     => __('Recipient phone', 'ocws'),
			'clear'		=> false,
          	'placeholder' => __('Recipient phone', 'ocws'),
			'required'	=> !!$send_to_other_person,
		),  WC()->checkout->get_value( 'ocws_recipient_phone' ) );
		//echo '</p>';

	}


	//add_filter( 'woocommerce_checkout_order_processed' , 'woo_checkout_order_processed', 10, 1 );
	public function woo_checkout_order_processed( $order_id, $data, $order ) {

		$send_to_other_person = (isset($_POST['ocws_other_recipient']) && !empty($_POST['ocws_other_recipient']));

		if ($send_to_other_person) {

			update_post_meta( $order_id, 'ocws_other_recipient', 1 );

			if ( ! empty( $_POST['ocws_recipient_firstname'] ) ) {
				update_post_meta( $order_id, 'ocws_recipient_firstname', $_POST['ocws_recipient_firstname'] );
				update_post_meta( $order_id, '_shipping_first_name', $_POST['ocws_recipient_firstname'] );
			}

			if ( ! empty( $_POST['ocws_recipient_lastname'] ) ) {
				update_post_meta( $order_id, 'ocws_recipient_lastname', $_POST['ocws_recipient_lastname'] );
				update_post_meta( $order_id, '_shipping_last_name', $_POST['ocws_recipient_lastname'] );
			}

			if ( ! empty( $_POST['ocws_recipient_phone'] ) ) {
				update_post_meta( $order_id, 'ocws_recipient_phone', $_POST['ocws_recipient_phone'] );
				update_post_meta( $order_id, '_shipping_phone', $_POST['ocws_recipient_phone'] );
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

}
