<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface OC_Woo_Shipping_Group_Data_Store_Interface {


	/**
	 * Method to create a new record of a OC_Woo_Shipping_Group based object.
	 *
	 * @param OC_Woo_Shipping_Group $data object.
	 */
	public function create( &$data );

	/**
	 * Method to read a record. Creates a new OC_Woo_Shipping_Group based object.
	 *
	 * @param OC_Woo_Shipping_Group $data object.
	 */
	public function read( &$data );

	/**
	 * Updates a record in the database.
	 *
	 * @param OC_Woo_Shipping_Group $data object.
	 */
	public function update( &$data );

	/**
	 * Deletes a record from the database.
	 *
	 * @param  OC_Woo_Shipping_Group $data object.
	 * @param  array   $args Array of args to pass to the delete method.
	 * @return bool result
	 */
	public function delete( &$data, $args = array() );

	/**
	 * Get a list of locations for a specific shipping group.
	 *
	 * @param  int  $group_id Group ID.
	 * @param  bool $enabled_only True to request enabled locations only.
	 * @return array Array of objects containing location_id, location_order, location_name, is_enabled
	 */
	public function get_locations( $group_id, $enabled_only );

	/**
	 * Get count of locations for a group.
	 *
	 * @param int $group_id Group ID.
	 * @return int Location Count
	 */
	public function get_location_count( $group_id );

	/**
	 * Add a location to a group.
	 *
	 * @param int    $group_id Group ID.
	 * @param string $type Location Type ( 'city' )
	 * @param int    $order Location Order ID.
	 * @param string $name Location Name
	 * @param string $code Location Code
	 * @return int Location ID
	 */
	public function add_location( $group_id, $type, $order, $name, $code );

	/**
	 * Delete a location.
	 *
	 * @param OC_Woo_Shipping_Group $group object.
	 * @param int $location_code Location code.
	 */
	public function delete_location( &$group, $location_code );


	/**
	 * Return an ordered list of groups.
	 *
	 * @return array An array of objects containing a group_id, group_name, and group_order.
	 */
	public function get_groups();


}
