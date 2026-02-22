<?php
/**
 * Represents a single shipping group *
 *
 */

defined( 'ABSPATH' ) || exit;

/**
 * OC_Woo_Shipping_Group class.
 */
class OC_Woo_Shipping_Group {

	/**
	 * Group ID
	 *
	 * @var int|null
	 */
	protected $id = 0;

	/**
	 * Group Data.
	 *
	 * @var array
	 */
	protected $data = array(
		'group_name'      => '',
		'group_order'     => 0,
		'is_enabled'      => 0,
		'group_locations' => array(),
	);

	/**
	 * Contains a reference to the data store for this class.
	 */
	protected $data_store;

	/**
	 * Constructor for groups.
	 *
	 * @param int|object $group Group ID to load from the DB or group object.
	 */
	public function __construct( $group = null ) {
		if ( is_numeric( $group ) && ! empty( $group ) ) {
			$this->set_id( $group );
		} elseif ( is_object( $group ) ) {
			$this->set_id( $group->group_id );
			$this->set_group_name($group->group_name);
			$this->set_group_order($group->group_order);
			$this->set_is_enabled($group->is_enabled);
		} else {
			$this->set_id( 0 );
		}

		$this->data_store = new OC_Woo_Shipping_Group_Data_Store();
		if (!empty($this->get_id())) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * --------------------------------------------------------------------------
	 * Getters
	 * --------------------------------------------------------------------------
	 */

	/**
	 * Returns the unique ID for this object.
	 *
	 */
	public function get_id() {
		return $this->id;
	}

	protected function get_prop( $prop ) {
		$value = null;

		if ( array_key_exists( $prop, $this->data ) ) {
			$value = $this->data[ $prop ];
		}

		return $value;
	}

	/**
	 * Returns all data for this object.
	 *
	 * @return array
	 */
	public function get_data() {
		return array_merge( array( 'id' => $this->get_id() ), $this->data );
	}

	/**
	 * Get group name.
	 *
	 * @return string
	 */
	public function get_group_name() {
		return $this->get_prop( 'group_name' );
	}

	/**
	 * Get group order.
	 *
	 * @return int
	 */
	public function get_group_order() {
		return $this->get_prop( 'group_order' );
	}

	/**
	 * Get group is enabled.
	 *
	 * @return int
	 */
	public function get_is_enabled() {
		return $this->get_prop( 'is_enabled' );
	}

	/**
	 * Get group locations.
	 *
	 * @return array of group objects
	 */
	public function get_group_locations($enabled_only = false) {
		$locations = $this->get_prop( 'group_locations' );
		if ($enabled_only) {
			$loc_response = array();
			foreach ($locations as $l) {
				if ($l->is_enabled == 1) {
					$loc_response[] = $l;
				}
			}
			return $loc_response;
		}
		return $locations;
	}

	public function get_group_locations_response($include_simple_cities=true, $include_polygons=true, $include_google_cities=true) {

		$locations = $this->get_group_locations();
		$loc_response = array();

		foreach ($locations as $l) {

			$polygon_data = '';
			$streets_data = [];

			if (!$include_simple_cities) {
				if ($l->type == 'city' && $l->gm_place_id != $l->code) {
					continue;
				}
			}
			if (!$include_google_cities) {
				if ($l->type == 'city' && $l->gm_place_id == $l->code) {
					continue;
				}
			}
			if (!$include_polygons) {
				if ($l->type == 'polygon') {
					continue;
				}
			}

			if ($l->type == 'polygon') {
				$polygon_data = @unserialize($l->gm_shapes);

				if (false === $polygon_data) {
					$polygon_data = '';
				}
			}
			else {
				$streets_data = @unserialize($l->gm_streets);

				if (false === $streets_data || !is_array($streets_data)) {
					$streets_data = [];
				}

				$streets = array();

				foreach ($streets_data as $id => $name) {
					$streets[] = array(
						'id' => $id,
						'name' => $name
					);
				}
			}

			$loc_response[$l->code] = array(
				'location_code' => $l->code,
				'location_order' => $l->order,
				'location_name' => $l->name,
				'location_type' => $l->type,
				'is_enabled' => $l->is_enabled,
				'gm_shapes' => $polygon_data,
				'gm_streets' => $streets,
				'gm_place_id' => $l->gm_place_id,
				'options' => $this->get_location_options($l->code, array('shipping_price', 'min_total', 'price_depending'))
			);
		}
		return $loc_response;
	}

	public function save_new_location($code, $order, $name, $type, $is_enabled = true, $data='') {
		if ($this->get_id()) {
			$this->data_store->save_new_location($this, $code, $order, $name, $type, $is_enabled, $data);
			$this->data_store->read_group_locations($this);
		}
	}

	public function save_new_gm_city($code, $order, $name, $type, $is_enabled = true, $data='') {
		if ($this->get_id()) {
			$this->data_store->save_new_gm_city($this, $code, $order, $name, $type, $is_enabled, $data);
			$this->data_store->read_group_locations($this);
		}
	}

	public function get_location_options($location_code, $options_names) {

		$res = array();

		foreach ($options_names as $opt) {
			$res[$opt] = OC_Woo_Shipping_Group_Option::get_location_option($location_code, $this->get_id(), $opt);
            if ($opt == 'price_depending') {
                if (!$res[$opt]['option_value']) {
                    $res[$opt]['option_value'] = json_encode([
                        "active" => false,
                        "rules" => []
                    ]);
                }
            }
		}

		return $res;
	}

	public function get_locations_codes() {

		$locations = $this->get_group_locations();
		$loc_response = array();
		foreach ($locations as $l) {
			$loc_response[] = $l->code;
		}
		return $loc_response;
	}

	public function get_locations_for_select($enabled_only = false, $include_simple_cities=true, $include_polygons=true, $include_google_cities=true) {

		$locations = $this->get_group_locations($enabled_only);
		$loc_response = array();

		foreach ($locations as $l) {

			if (!$include_simple_cities) {
				if ($l->type == 'city' && $l->gm_place_id != $l->code) {
					continue;
				}
			}
			if (!$include_google_cities) {
				if ($l->type == 'city' && $l->gm_place_id == $l->code) {
					continue;
				}
			}
			if (!$include_polygons) {
				if ($l->type == 'polygon') {
					continue;
				}
			}
			$loc_response[$l->code] = html_entity_decode($l->name);
		}
		return $loc_response;
	}

	/**
	 * Return a text string representing what this group is for.
	 *
	 * @param  int    $max Max locations to return.
	 * @return string
	 */
	public function get_formatted_location( $max = 10 ) {
		$location_parts = array();

		$locations      = $this->get_group_locations();
		$cities     = array_filter( $locations, array( $this, 'location_is_city' ) );
		$polygons      = array_filter( $locations, array( $this, 'location_is_polygon' ) );

		foreach ( $cities as $location ) {
			$location_parts[] = $location->name;
		}
		foreach ( $polygons as $location ) {
			$location_parts[] = $location->name;
		}

		// Fix display of encoded characters.
		$location_parts = array_map( 'html_entity_decode', $location_parts );

		if ( count( $location_parts ) > $max ) {
			$remaining = count( $location_parts ) - $max;
			// @codingStandardsIgnoreStart
			return sprintf( _n( '%s and %d other region', '%s and %d other regions', $remaining, 'woocommerce' ), implode( ', ', array_splice( $location_parts, 0, $max ) ), $remaining );
			// @codingStandardsIgnoreEnd
		} elseif ( ! empty( $location_parts ) ) {
			return implode( ', ', $location_parts );
		} else {
			return __( 'No locations', 'ocws' );
		}
	}

	public function get_location_name_by_code($location_code) {

		$locations = $this->get_group_locations();
		$loc_name = '';
		foreach ($locations as $l) {
			if ($l->code == $location_code) {
				return $l->name;
			}
		}
		return $loc_name;
	}

	/**
	 * --------------------------------------------------------------------------
	 * Setters
	 * --------------------------------------------------------------------------
	 */

	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	protected function set_prop( $prop, $value ) {
		if ( array_key_exists( $prop, $this->data ) ) {
			$this->data[ $prop ] = $value;
		}
	}

	/**
	 * Set group name.
	 *
	 * @param string $set Value to set.
	 */
	public function set_group_name( $set ) {
		$this->set_prop( 'group_name', ocws_clean( $set ) );
	}

	/**
	 * Set group order. Value to set.
	 *
	 * @param int $set Value to set.
	 */
	public function set_group_order( $set ) {
		$this->set_prop( 'group_order', absint( $set ) );
	}

	/**
	 * Set group is_enabled. Value to set.
	 *
	 * @param int $set Value to set.
	 */
	public function set_is_enabled( $set ) {
		$this->set_prop( 'is_enabled', absint( $set ) );
	}

	/**
	 * Set group locations.
	 *
	 */
	public function set_group_locations( $locations ) {
		$this->set_prop( 'group_locations', $locations );
	}

	/**
	 * --------------------------------------------------------------------------
	 * Other
	 * --------------------------------------------------------------------------
	 */

	/**
	 * Save group data to the database.
	 *
	 * @return int
	 */
	public function save($save_locations = true) {
		if ( ! $this->get_group_name() ) {
			$this->set_group_name( $this->generate_group_name() );
		}

		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		if ( 0 !== $this->get_id() ) {
			$this->data_store->update( $this );
		} else {
			$this->data_store->create( $this );
		}

		return $this->get_id();
	}

	/**
	 * Generate a group name based on location.
	 *
	 * @return string
	 */
	protected function generate_group_name() {
		$group_name = $this->get_formatted_location();

		if ( empty( $group_name ) ) {
			$group_name = __( 'Group', 'ocws' );
		}

		return $group_name;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private function location_is_city( $location ) {
		return 'city' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private function location_is_polygon( $location ) {
		return 'polygon' === $location->type;
	}

	/**
	 * Is passed location type valid?
	 *
	 * @param  string $type Type to check.
	 * @return boolean
	 */
	public function is_valid_location_type( $type ) {
		return in_array( $type, array( 'city', 'polygon' ), true );
	}

	/**
	 * Add location (city or polygon) to a group.
	 *
	 * @param string $code Location code.
	 * @param string $type city or polygon.
	 * @param int $order
	 * @param string $name
	 */
	public function add_location( $code, $type, $order=0, $name='', $is_enabled=1, $data='', $gm_place_id='' ) {

		if ( 0 === $this->get_id() ) {
			//$this->save();
		}

		if ( $this->is_valid_location_type( $type ) ) {

			//$locations = OCWS()->locations->get_cities();
			$code = ocws_clean( $code );
			$name = ocws_clean( $name );
			$city_name = OCWS()->locations->get_city_name($code);
			$name = ( !empty($name)? $name : ( $city_name? $city_name : 'no name' ) );

			$shapes = '';
			$streets = '';
			if ($type == 'polygon') {
				$shapes = $data;
			}
			else {
				$streets = $data;
			}

			$location         = array(
				'code' => $code,
				'type' => ocws_clean( $type ),
				'order' => intval( $order ),
				'name' => $name,
				'is_enabled' => $is_enabled,
				'gm_shapes' => $shapes,
				'gm_streets' => $streets,
				'gm_place_id' => $gm_place_id
			);
			$group_locations   = $this->get_prop( 'group_locations' );
			$group_locations[] = (object) $location;
			$this->set_prop( 'group_locations', $group_locations );

			//$this->save();

		}

	}

	public function delete_location( $location_code ) {

		if ( 0 === $this->get_id() ) {
			$this->save();
		}

		$this->data_store->delete_location($this, $location_code);

		$this->clear_locations();
		$this->data_store->read_group_locations($this);

	}

	public function update_location_data( $location_code, $location_data ) {

		if ( 0 === $this->get_id() ) {
			$this->save();
		}

		$is_update = false;

		if ( isset( $location_data['location_order'] ) ) {
			$this->data_store->update_location_order( $location_code, $location_data['location_order'] );
			$is_update = true;
		}

		if ( isset( $location_data['is_enabled'] ) ) {
			$is_enabled = absint( '1' === $location_data['is_enabled'] || true === $location_data['is_enabled'] );
			$this->data_store->update_location_enabled( $location_code, $location_data['is_enabled'] );
			$is_update = true;
		}

		if ( isset( $location_data['gm_shapes'] ) && isset( $location_data['polygon_name'] ) ) {

			error_log('Updating location shapes data...');

			$this->data_store->update_location_shapes( $location_code, $location_data['gm_shapes'] );
			$this->data_store->update_location_name( $location_code, $location_data['polygon_name'] );
			$is_update = true;
		}

		if ( isset( $location_data['gm_streets'] ) ) {  /// updating via ajax action

			error_log('Updating location streets data...');

			$this->data_store->update_location_streets( $location_code, $location_data['gm_streets'] );
			$is_update = true;
		}

		if ($is_update) {
			$this->clear_locations();
			$this->data_store->read_group_locations($this);
		}

	}

	/**
	 * Clear all locations for this group.
	 *
	 * @param array|string $types of location to clear.
	 */
	public function clear_locations( $types = array( 'city', 'polygon' ) ) {
		if ( ! is_array( $types ) ) {
			$types = array( $types );
		}
		$group_locations = $this->get_prop( 'group_locations' );
		foreach ( $group_locations as $key => $values ) {
			if ( in_array( $values->type, $types, true ) ) {
				unset( $group_locations[ $key ] );
			}
		}
		$group_locations = array_values( $group_locations ); // reindex.
		$this->set_prop( 'group_locations', $group_locations );
	}

	/**
	 * Set locations.
	 *
	 * @param array $locations Array of locations.
	 */
	public function set_locations( $locations = array() ) {
		$this->clear_locations();
		foreach ( $locations as $location ) {
			$this->add_location( $location['code'], $location['type'], $location['order'], $location['name'], $location['is_enabled'] );
		}
	}

	/**
	 * Delete an object, set the ID to 0, and return result.
	 *
	 * @return bool result
	 */
	public function delete() {
		if ( $this->data_store ) {
			$this->data_store->delete( $this );
			$this->set_id( 0 );
			return true;
		}
		return false;
	}

}
