<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OC_Woo_Shipping_Group_Data_Store implements OC_Woo_Shipping_Group_Data_Store_Interface {

	/**
	 * Method to create a new shipping group.
	 * @param OC_Woo_Shipping_Group $group object.
	 *
	 */
	public function create( &$group ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'oc_woo_shipping_groups',
			array(
				'group_name'  => $group->get_group_name(),
				'group_order' => $group->get_group_order(),
			)
		);
		$group->set_id( $wpdb->insert_id );
		$this->save_locations( $group );
	}

	/**
	 * Update group in the database.
	 *
	 * @since 3.0.0
	 * @param OC_Woo_Shipping_Group $group object.
	 */
	public function update( &$group, $save_locations = false ) {
		global $wpdb;
		if ( $group->get_id() ) {
			$wpdb->update(
				$wpdb->prefix . 'oc_woo_shipping_groups',
				array(
					'group_name'  => $group->get_group_name(),
					'group_order' => $group->get_group_order(),
					'is_enabled' => $group->get_is_enabled()
				),
				array( 'group_id' => $group->get_id() )
			);
		}
		if ($save_locations) {
			$this->save_locations( $group );
		}
	}

	/**
	 * Method to read a shipping group from the database.
	 *
	 * @param OC_Woo_Shipping_Group $group object.
	 * @throws Exception If invalid data store.
	 */
	public function read( &$group ) {
		global $wpdb;

		$group_data = false;

		if ( 0 !== $group->get_id() && '0' !== $group->get_id() ) {
			$group_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT group_name, group_order, is_enabled FROM {$wpdb->prefix}oc_woo_shipping_groups WHERE group_id = %d LIMIT 1",
					$group->get_id()
				)
			);
		}

		if ( $group_data ) {
			$group->set_group_name( $group_data->group_name );
			$group->set_group_order( $group_data->group_order );
			$group->set_is_enabled( $group_data->is_enabled );
			$this->read_group_locations( $group );
		} else {
			throw new Exception( __( 'Invalid data store.', 'woocommerce' ) );
		}
	}

	/**
	 * Deletes a shipping group from the database.
	 *
	 * @param  OC_Woo_Shipping_Group $group object.
	 * @param  array            $args Array of args to pass to the delete method.
	 * @return void
	 */
	public function delete( &$group, $args = array() ) {
		$group_id = $group->get_id();

		if ( $group_id ) {
			global $wpdb;

			// Delete group.
			$wpdb->delete( $wpdb->prefix . 'oc_woo_shipping_locations', array( 'group_id' => $group_id ) );
			$wpdb->delete( $wpdb->prefix . 'oc_woo_shipping_groups', array( 'group_id' => $group_id ) );

			// delete group options
			$group_option_prefix = OC_Woo_Shipping_Group_Option::get_group_option_prefix($group_id);
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s", str_replace('_', '\_', OC_Woo_Shipping_Group_Option::get_group_option_prefix($group_id)).'%' ));

			wp_cache_flush();

			$group->set_id( 0 );
		}
	}

	/**
	 * Return an ordered list of groups.
	 *
	 * @return array An array of objects containing a group_id, group_name, and group_order.
	 */
	public function get_groups() {
		global $wpdb;
		return $wpdb->get_results( "SELECT group_id, group_name, group_order, is_enabled FROM {$wpdb->prefix}oc_woo_shipping_groups order by group_order ASC, group_id ASC;" );
	}

	/**
	 * Read location data from the database.
	 *
	 * @param OC_Woo_Shipping_Group $group object.
	 */
	public function read_group_locations( &$group ) {
		global $wpdb;

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code, gm_place_id, location_type, location_order, location_name, is_enabled, gm_shapes, gm_streets FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE group_id = %d",
				$group->get_id()
			)
		);

		if ( $locations ) {
			foreach ( $locations as $location ) {
				$group->add_location( $location->location_code, $location->location_type, $location->location_order, $location->location_name, $location->is_enabled, ($location->location_type == 'polygon'? $location->gm_shapes : $location->gm_streets), $location->gm_place_id );
			}
		}
	}

	/**
	 * Read location data from the database.
	 *
	 * @param string $location_code.
	 */
	public function read_location_data( $location_code ) {
		global $wpdb;

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code, gm_place_id, location_type, location_order, location_name, is_enabled, gm_shapes, gm_streets FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE location_code = %s",
				$location_code
			)
		);

		if ( $locations && count($locations) > 0 ) {
			return $locations[0];
		}
		return false;
	}

	/**
	 * Read location data from the database.
	 */
	public function read_all_polygons() {
		global $wpdb;

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code, gm_place_id, location_type, location_order, location_name, is_enabled, gm_shapes, gm_streets FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE location_type = %s",
				'polygon'
			)
		);

		if ( $locations ) {
			return $locations;
		}
		return array();
	}

	/**
	 * Read location data from the database.
	 */
	public function read_all_gm_cities() {
		global $wpdb;

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code, gm_place_id, location_type, location_order, location_name, is_enabled, gm_shapes, gm_streets FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE location_type = %s AND location_code <=> gm_place_id",
				'city'
			)
		);

		if ( $locations ) {
			return $locations;
		}
		return array();
	}

	/**
	 * Save locations to the DB.
	 * This function clears old locations, then re-inserts new if any changes are found.
	 *
	 *
	 * @param OC_Woo_Shipping_Group $group object.
	 *
	 * @return bool|void
	 */
	private function save_locations( &$group ) {

		global $wpdb;
		$prev_location_codes = $this->get_group_locations_codes($group->get_id());

		$wpdb->delete( $wpdb->prefix . 'oc_woo_shipping_locations', array( 'group_id' => $group->get_id() ) );

		foreach ( $group->get_group_locations() as $location ) {
			$wpdb->insert(
				$wpdb->prefix . 'oc_woo_shipping_locations',
				array(
					'group_id'       => $group->get_id(),
					'location_code' => $location->code,
					'location_type' => $location->type,
					'location_name' => $location->name,
					'location_order' => $location->order,
					'is_enabled' => ($location->is_enabled? 1 : 0),
					'gm_shapes' => $location->gm_shapes,
					'gm_streets' => $location->gm_streets
				)
			);
			if ( isset( $prev_location_codes[$location->code] ) ) {
				unset($prev_location_codes[$location->code]);
			}
		}
		// delete options
		foreach ($prev_location_codes as $code) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", 'ocws\_location\_' . $code . '\_%') );
		}
		// Clear any cached data that has been removed.
		wp_cache_flush();
	}

	/**
	 * Add a new group location to the database.
	 *
	 * @param OC_Woo_Shipping_Group $group object.
	 */
	public function save_new_location(&$group, $code, $order, $name, $type, $is_enabled = true, $data='') {

		global $wpdb;
		if ($group->get_id()) {

			$shapes = '';
			$streets = '';
			if ($type == 'polygon') {
				$shapes = $data;
			}
			else {
				$streets = $data;
			}

			try {
				$wpdb->insert(
					$wpdb->prefix . 'oc_woo_shipping_locations',
					array(
						'group_id'       => $group->get_id(),
						'location_code' => $code,
						'location_type' => $type,
						'location_name' => $name,
						'location_order' => $order,
						'is_enabled' => ($is_enabled? 1 : 0),
						'gm_shapes' => $shapes,
						'gm_streets' => $streets
					)
				);
				/*error_log('Added new location: ' . print_r( array(
						'group_id'       => $group->get_id(),
						'location_code' => $code,
						'location_type' => $type,
						'location_name' => $name,
						'location_order' => $order,
						'is_enabled' => ($is_enabled? 1 : 0)
					), 1 ));*/
				return true;
			}
			catch (Exception $e) {
				//error_log($e->getTrace());
				return false;
			}
		}
		return false;
	}

	public function save_new_gm_city(&$group, $code, $order, $name, $type, $is_enabled = true, $data='') {

		global $wpdb;
		if ($group->get_id()) {

			error_log('adding new gm city');

			$shapes = '';
			$streets = '';
			if ($type == 'polygon') {
				$shapes = $data;
			}
			else {
				$streets = $data;
			}

			try {
				$wpdb->insert(
					$wpdb->prefix . 'oc_woo_shipping_locations',
					array(
						'group_id'       => $group->get_id(),
						'location_code' => $code,
						'gm_place_id' => $code,
						'location_type' => $type,
						'location_name' => $name,
						'location_order' => $order,
						'is_enabled' => ($is_enabled? 1 : 0),
						'gm_shapes' => $shapes,
						'gm_streets' => $streets
					)
				);
				/*error_log('Added new location: ' . print_r( array(
                        'group_id'       => $group->get_id(),
                        'location_code' => $code,
                        'location_type' => $type,
                        'location_name' => $name,
                        'location_order' => $order,
                        'is_enabled' => ($is_enabled? 1 : 0)
                    ), 1 ));*/
				return true;
			}
			catch (Exception $e) {
				error_log($e->getMessage());
				return false;
			}
		}
		return false;
	}

	private function get_group_locations_codes( $group_id ) {

		global $wpdb;
		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE group_id = %d",
				$group_id
			)
		);

		$location_codes = array();
		if ( $locations ) {
			foreach ( $locations as $location ) {
				$location_codes[$location->location_code] = $location->location_code;
			}
		}

		return $location_codes;
	}

	public function get_group_by_location ($location_code) {

		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$wpdb->prefix}oc_woo_shipping_locations as locations WHERE locations.location_code = %s LIMIT 1;", $location_code ) );
	}

	public function is_location_enabled($location_code) {

		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT is_enabled FROM {$wpdb->prefix}oc_woo_shipping_locations as locations WHERE locations.location_code = %s LIMIT 1;", $location_code ) );
	}

	public function is_group_enabled($group_id) {

		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT is_enabled FROM {$wpdb->prefix}oc_woo_shipping_groups as grps WHERE grps.group_id = %d LIMIT 1;", $group_id ) );
	}

	public function get_locations($group_id, $enabled_only)
	{
		// TODO: Implement get_locations() method.
	}

	public function get_location_count($group_id)
	{
		// TODO: Implement get_location_count() method.
	}

	public function add_location($group_id, $type, $order, $name, $code)
	{
		// TODO: Implement add_location() method.
	}

	/*
	 * @param OC_Woo_Shipping_Group $group object.
	 * @param int $location_code Location code.
	 */
	public function delete_location(&$group, $location_code) {

		global $wpdb;
		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE group_id = %d AND location_code = %d",
				$group->get_id(), $location_code
			)
		);
		// delete all location options
		if (count($locations)) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", 'ocws\_location\_' . $location_code . '\_%') );
		}
		// delete location
		$wpdb->delete( $wpdb->prefix . 'oc_woo_shipping_locations', array( 'group_id' => $group->get_id(), 'location_code' => $location_code ) );
	}

	public function update_location_order($location_code, $location_order) {

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_woo_shipping_locations", array( 'location_order' => absint( $location_order ) ), array( 'location_code' => ( $location_code ) ) );
	}

	public function update_location_enabled($location_code, $is_enabled) {

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_woo_shipping_locations", array( 'is_enabled' => $is_enabled ), array( 'location_code' => ( $location_code ) ) );
	}

	public function update_location_shapes($location_code, $data) {

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_woo_shipping_locations", array( 'gm_shapes' => $data ), array( 'location_code' => ( $location_code ) ) );
	}

	public function update_location_streets($location_code, $data) {

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_woo_shipping_locations", array( 'gm_streets' => $data ), array( 'location_code' => ( $location_code ) ) );
	}

	public function update_location_name($location_code, $location_name) {

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_woo_shipping_locations", array( 'location_name' => $location_name ), array( 'location_code' => ( $location_code ) ) );
	}
}
