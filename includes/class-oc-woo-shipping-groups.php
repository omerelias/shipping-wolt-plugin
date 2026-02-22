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
class OC_Woo_Shipping_Groups {

	/**
	 * Get shipping groups from the database.
	 *
	 * @return array Array of arrays.
	 */
	public static function get_groups() {
		$data_store = new OC_Woo_Shipping_Group_Data_Store();
		$raw_groups  = $data_store->get_groups();
		$groups      = array();

		foreach ( $raw_groups as $raw_group ) {
			$group = new OC_Woo_Shipping_Group( $raw_group );
			$groups[ $group->get_id() ] = $group->get_data();
			$groups[ $group->get_id() ]['group_id'] = $group->get_id();
			$groups[ $group->get_id() ]['is_enabled'] = $group->get_is_enabled();
			$groups[ $group->get_id() ]['formatted_group_location'] = $group->get_formatted_location();
		}

		return $groups;
	}

	/**
	 * Get shipping groups from the database.
	 *
	 * @return array .
	 */
	public static function get_groups_assoc() {
		$data_store = new OC_Woo_Shipping_Group_Data_Store();
		$raw_groups  = $data_store->get_groups();
		$groups      = array();

		foreach ( $raw_groups as $raw_group ) {
			$group = new OC_Woo_Shipping_Group( $raw_group );
			$groups[ $group->get_id() ] = $group->get_group_name();
		}

		return $groups;
	}

	public static function get_all_locations($enabled_only = false, $include_simple_cities=true, $include_polygons=true, $include_google_cities=true) {
		$data_store = new OC_Woo_Shipping_Group_Data_Store();
		$raw_groups  = $data_store->get_groups();
		$locations      = array();

		foreach ( $raw_groups as $raw_group ) {
			if ($enabled_only && $raw_group->is_enabled != 1) {
				continue;
			}
			$group = new OC_Woo_Shipping_Group( $raw_group );
			$loc = $group->get_locations_for_select($enabled_only, $include_simple_cities, $include_polygons, $include_google_cities);
			foreach ($loc as $code => $name) {
				$locations[$code] = ocws_get_city_title_translated($code, $name);
			}
		}

		return $locations;
	}

	public static function db_get_enabled_locations_count() {
		global $wpdb;
		$enabled_groups = $wpdb->get_results( $wpdb->prepare( "SELECT group_id FROM {$wpdb->prefix}oc_woo_shipping_groups WHERE is_enabled = %d", 1 ) );
		if (!$enabled_groups || count($enabled_groups) == 0) return 0;
		$ids = array();
		foreach ($enabled_groups as $obj) {
			$ids[] = $obj->group_id;
		}
		$sql = "
		SELECT COUNT(*) FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE is_enabled = 1 AND group_id IN (". implode(', ', array_fill( 0, count($ids), '%d' )) .")
		";
		$query = $wpdb->prepare( $sql, $ids );
		error_log('db_get_enabled_locations_count' . $query);
		return $wpdb->get_var( $query );
	}

	/**
	 * Get shipping group using it's ID
	 *
	 * @param int $group_id Group ID.
	 * @return OC_Woo_Shipping_Group|bool
	 */
	public static function get_group( $group_id ) {
		return self::get_group_by( 'group_id', $group_id );
	}

	/**
	 * Get shipping group by an ID.
	 *
	 * @param string $by Get by 'group_id' or 'location_code'.
	 * @param int    $id ID.
	 * @return OC_Woo_Shipping_Group|bool
	 */
	public static function get_group_by( $by = 'group_id', $id = 0 ) {
		$group_id = false;

		switch ( $by ) {
			case 'group_id':
				$group_id = $id;
				break;
			case 'location_code':
				$data_store = new OC_Woo_Shipping_Group_Data_Store();
				$group_id    = $data_store->get_group_by_location( $id );
				break;
		}

		if ( false !== $group_id ) {
			try {
				return new OC_Woo_Shipping_Group( $group_id );
			} catch ( Exception $e ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Delete a group using it's ID
	 *
	 * @param int $group_id Group ID.
	 */
	public static function delete_group( $group_id ) {
		$group = new OC_Woo_Shipping_Group( $group_id );
		$group->delete();
	}


}
