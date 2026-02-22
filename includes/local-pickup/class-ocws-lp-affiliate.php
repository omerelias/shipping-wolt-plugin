<?php
/**
 * Represents a single local pickup affiliate *
 *
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCWS_LP_Affiliate class.
 */
class OCWS_LP_Affiliate {

	/**
	 * Affiliate ID
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Group Data.
	 *
	 * @var array
	 */
	protected $data = array(
		'aff_name'      => '',
		'aff_address'      => '',
		'aff_descr'      => '',
		'aff_order'     => 0,
		'is_enabled'      => 0,
	);

	/**
	 * Constructor for groups.
	 *
	 * @param int|object $group Group ID to load from the DB or group object.
	 */
	public function __construct( $aff ) {
		if ( is_numeric( $aff ) && ! empty( $aff ) ) {
			$this->set_id( $aff );
		} elseif ( is_object( $aff ) ) {
			$this->set_id( $aff->aff_id );
			$this->set_aff_name($aff->aff_name);
			$this->set_aff_address($aff->aff_address);
			$this->set_aff_descr($aff->aff_descr);
			$this->set_aff_order($aff->aff_order);
			$this->set_is_enabled($aff->is_enabled);
		} else {
			$this->set_id( 0 );
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
	 * Get affiliate name.
	 *
	 * @return string
	 */
	public function get_aff_name() {
		return $this->get_prop( 'aff_name' );
	}


	/**
	 * Get affiliate address.
	 *
	 * @return string
	 */
	public function get_aff_address() {
		return $this->get_prop( 'aff_address' );
	}

	/**
	 * Get affiliate description.
	 *
	 * @return string
	 */
	public function get_aff_descr() {
		return $this->get_prop( 'aff_descr' );
	}

	/**
	 * Get affiliate order.
	 *
	 * @return int
	 */
	public function get_aff_order() {
		return $this->get_prop( 'aff_order' );
	}

	/**
	 * Get affiliate is enabled.
	 *
	 * @return int
	 */
	public function get_is_enabled() {
		return $this->get_prop( 'is_enabled' );
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
	 * Set affiliate name.
	 *
	 * @param string $set Value to set.
	 */
	public function set_aff_name( $set ) {
		$this->set_prop( 'aff_name', ocws_clean( $set ) );
	}

	/**
	 * Set affiliate address.
	 *
	 * @param string $set Value to set.
	 */
	public function set_aff_address( $set ) {
		$this->set_prop( 'aff_address', ocws_clean( $set ) );
	}

	/**
	 * Set affiliate description.
	 *
	 * @param string $set Value to set.
	 */
	public function set_aff_descr( $set ) {
		$this->set_prop( 'aff_descr', ocws_clean( $set ) );
	}

	/**
	 * Set affiliate order. Value to set.
	 *
	 * @param int $set Value to set.
	 */
	public function set_aff_order( $set ) {
		$this->set_prop( 'aff_order', absint( $set ) );
	}

	/**
	 * Set affiliate is_enabled. Value to set.
	 *
	 * @param int $set Value to set.
	 */
	public function set_is_enabled( $set ) {
		$this->set_prop( 'is_enabled', absint( $set ) );
	}



	/**
	 * --------------------------------------------------------------------------
	 * Other
	 * --------------------------------------------------------------------------
	 */

	/**
	 * Save affiliate data to the database.
	 *
	 * @return int
	 */
	public function save() {
		if ( ! $this->get_aff_name() ) {
			$this->set_aff_name( __('New branch', 'ocws') );
		}

		$data_store = new OCWS_LP_Affiliates();

		if ( $this->get_id() > 0 ) {
			$data_store->db_update_affiliate( $this );
		} else {
			$data_store->db_create_affiliate( $this );
		}

		return $this->get_id();
	}

	/**
	 * Delete an object, set the ID to 0, and return result.
	 *
	 * @return bool result
	 */
	public function delete() {

		$data_store = new OCWS_LP_Affiliates();

		$data_store->db_delete_affiliate( $this );
		$this->set_id( 0 );
		return true;
	}

}
