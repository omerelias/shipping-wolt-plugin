<?php
/**
 * Handles storage and retrieval of shipping groups
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipping groups class.
 */
class OC_Woo_Shipping_Companies {

	/**
	 * Get shipping companies from the database.
	 *
	 * @return array Array of arrays.
	 */
	public static function get_companies() {
		$data_store = new OC_Woo_Shipping_Company_Data_Store();
		$raw_comps  = $data_store->get_companies();
		$comps      = array();

		foreach ( $raw_comps as $raw_comp ) {
			$comps[ $raw_comp->company_id ] = (array) $raw_comp;
			$comps[ $raw_comp->company_id ]['company_id'] = $raw_comp->company_id;
		}

		return $comps;
	}

	/**
	 * Get shipping companies from the database.
	 *
	 * @return array .
	 */
	public static function get_companies_assoc() {
		$data_store = new OC_Woo_Shipping_Company_Data_Store();
		$raw_comps  = $data_store->get_companies();
		$comps      = array();

		foreach ( $raw_comps as $raw_comp ) {
			$comps[ $raw_comp->company_id ] = $raw_comp->company_name;
		}

		return $comps;
	}

	/**
	 * Get shipping company using it's ID
	 *
	 * @param int $company_id.
	 * @return object|bool
	 */
	public static function get_company( $company_id ) {
		$data_store = new OC_Woo_Shipping_Company_Data_Store();
		return $data_store->read($company_id);
	}

	/**
	 * Delete a company using it's ID
	 *
	 * @param int $company_id.
	 */
	public static function delete_company( $company_id ) {
		$data_store = new OC_Woo_Shipping_Company_Data_Store();
		$data_store->delete($company_id);
	}

	/**
	 * Create a company
	 *
	 * @param int $company_name.
	 */
	public static function add_company( $company_name ) {
		$data_store = new OC_Woo_Shipping_Company_Data_Store();
		$data_store->create($company_name);
	}

	/**
	 * Update a company
	 *
	 * @param int $company_id.
	 * @param string $company_name.
	 */
	public static function update_company( $company_id, $company_name ) {
		$data_store = new OC_Woo_Shipping_Company_Data_Store();
		$data_store->update($company_id, $company_name);
	}


}
