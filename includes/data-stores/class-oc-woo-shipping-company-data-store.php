<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OC_Woo_Shipping_Company_Data_Store {

	/**
	 * Method to create a new shipping company.
	 * @param string $name.
	 * @return int.
	 */
	public function create( $name ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'oc_woo_shipping_companies',
			array(
				'company_name'  => $name
			)
		);
		return $wpdb->insert_id;
	}

	/**
	 * Update company in the database.
	 *
	 * @since 3.0.0
	 * @param int $company_id.
	 * @param string $name.
	 */
	public function update( $company_id, $name ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'oc_woo_shipping_companies',
			array(
				'company_name'  => $name
			),
			array( 'company_id' => $company_id )
		);

	}

	/**
	 * Method to read a shipping company from the database.
	 *
	 * @param int $company_id.
	 * @return mixed object | boolean.
	 */
	public function read( $company_id ) {
		global $wpdb;

		$data = false;

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT company_name, company_order, is_enabled FROM {$wpdb->prefix}oc_woo_shipping_companies WHERE company_id = %d LIMIT 1",
				$company_id
			)
		);

		return ($data? $data : false);
	}

	/**
	 * Deletes a shipping company from the database.
	 *
	 * @param  int $company_id.
	 * @return void
	 */
	public function delete( $company_id ) {

		if ( $company_id ) {
			global $wpdb;

			// Delete company.
			$wpdb->delete( $wpdb->prefix . 'oc_woo_shipping_companies', array( 'company_id' => $company_id ) );

		}
	}

	/**
	 * Return an ordered list of companies.
	 *
	 * @return array An array of objects containing a company_id, company_name, company_order, is_enabled.
	 */
	public function get_companies() {
		global $wpdb;
		return $wpdb->get_results( "SELECT company_id, company_name, company_order, is_enabled FROM {$wpdb->prefix}oc_woo_shipping_companies order by company_order ASC, company_id ASC;" );
	}
}
