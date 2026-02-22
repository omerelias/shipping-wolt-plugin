<?php
/**
 * Debug/Status page
 *
 * @package WooCommerce/Admin/System Status
 * @version 2.2.0
 */

use Automattic\Jetpack\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Admin_Status Class.
 */
class OC_Woo_Shipping_Admin_Companies {

	/**
	 * Handles output of the shipping companies page in admin.
	 */
	public static function output() {

		global $hide_save_button;

		$hide_save_button = true;
		self::companies_screen();

	}

	/**
	 * Show companies
	 */
	protected static function companies_screen() {

		wp_localize_script(
			'oc-woo-shipping-companies',
			'shippingCompaniesLocalizeScript',
			array(
				'companies'                   => OC_Woo_Shipping_Companies::get_companies(),
				'oc_woo_shipping_companies_nonce' => wp_create_nonce( 'oc_woo_shipping_companies_nonce' ),
				'strings'                 => array(
					'unload_confirmation_msg'     => __( 'Your changed data will be lost if you leave this page without saving.', 'ocws' ),
					'delete_confirmation_msg'     => __( 'Are you sure you want to delete this company? This action cannot be undone.', 'ocws' ),
					'save_failed'                 => __( 'Your changes were not saved. Please retry.', 'ocws' ),
				),
			)
		);
		wp_enqueue_script( 'oc-woo-shipping-companies' );

		include_once dirname( __FILE__ ) . '/partials/html-admin-page-shipping-companies.php';
	}

}
