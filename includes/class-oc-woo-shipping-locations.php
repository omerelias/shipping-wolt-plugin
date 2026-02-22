<?php
/**
 * OC Woo Shipping Locations cities
 *
 */

defined( 'ABSPATH' ) || exit;

/**
 * The WooCommerce countries class stores country/state data.
 */
class OC_Woo_Shipping_Locations {

	/**
	 * Locales list.
	 *
	 * @var array
	 */
	public $cities = array();

	public $cities_en = array();

	public $imported_cities = array();

	protected $cache = array();

	/**
	 * Get all cities.
	 *
	 * @return array
	 */
	public function get_cities() {

		global $wpdb;
		if ( empty( $this->cities ) ) {

			$cities = $wpdb->get_results(
				"SELECT city_code, city_name, city_name_en, is_imported FROM {$wpdb->prefix}oc_woo_shipping_cities_base ORDER BY city_name"
			);

			if ($cities) {
				$this->cities = array();
				$this->cities_en = array();
				$this->imported_cities = array();

				foreach ($cities as $city_row) {
					$this->cities[$city_row->city_code] = $city_row->city_name;
					$this->cities_en[$city_row->city_code] = $city_row->city_name_en;
					if ($city_row->is_imported == 1) {
						$this->imported_cities[$city_row->city_code] = $city_row->city_name;
					}
				}
			}
			else {
				// cities in DB not found
				$this->cities = include plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/cities.php';
				$locale = get_locale();
				if (strpos($locale, 'en_') === 0) {
					$this->cities_en = include plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/cities-en.php';
				}
				if (file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/imported-cities.php' )) {
					$this->imported_cities = include plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/imported-cities.php';
					$this->cities = $this->cities + $this->imported_cities;
				}
				//uasort( $this->cities, 'wc_ascii_uasort_comparison' );
			}
		}

		return $this->cities;
	}

	public function get_imported_cities() {

		$this->get_cities();
		return $this->imported_cities;
	}

	public function import_city($city_code, $city_name) {

		$this->get_cities();
		$this->imported_cities[$city_code] = $city_name;
	}

	public function save_imported_cities() {

		global $wpdb;
		asort($this->imported_cities);
		$file = plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/imported-cities.php';
		$res = "<?php\n\n";
		$res .= "defined( 'ABSPATH' ) || exit;\n\n";

		$res .= "return array(\n";

		foreach ($this->imported_cities as $code => $name) {
			$res .= "   '" . $code . "' => '" . str_replace("'", "\'", $name) . "',\n";
			$wpdb->query( $wpdb->prepare( "INSERT INTO `{$wpdb->prefix}oc_woo_shipping_cities_base` (`city_code`, `city_name`, `city_name_en`, `is_imported`) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE `city_name` = VALUES(`city_name`), `city_name_en` = VALUES(`city_name_en`), `is_imported` = VALUES(`is_imported`)", $code, $name, $name, '1' ) );
		}

		$res .= ");\n";

		file_put_contents($file, $res);
	}

	public function get_city_name($city_code) {

		$city_data = $this->get_city_data($city_code);
		if ( $city_data ) {
			return $this->translate_name( $city_code, $city_data->city_name, $city_data );
		}
		return '';
	}

	/**
	 * Outputs the list of cities for use in dropdown boxes.
	 *
	 * @param string $selected_city Selected city.
	 * @param bool   $escape           If we should escape HTML.
	 */
	public function city_dropdown_options( $selected_city = '', $escape = false ) {
		$this->get_cities();
		if ( $this->cities ) {
			foreach ( $this->cities as $key => $value ) {
				echo '<option';
				if ( $selected_city === $key ) {
					echo ' selected="selected"';
				}
				echo ' value="' . esc_attr( $key ) . '">' . ( $escape ? esc_js( $value ) : $value ) . '</option>';
			}
		}
	}

	//TODO: city name translation
	public function translate_name( $city_code, $city_name, $city_data = null ) {

		$locale = get_locale();
		if (!$city_data) {
			$city_data = $this->get_city_data($city_code);
		}
		if ($city_data) {
			return $this->translate_name_by_city_data($city_code, $city_data);
		}
		return $city_name;
	}

	protected function translate_name_by_city_data( $city_code, $city_data ) {

		$locale = get_locale();
		if (strpos($locale, 'en_') === 0) {
			if (isset($city_data->city_name_en)) {
				return $city_data->city_name_en;
			}
		}
		if (isset($city_data->city_name)) {
			return $city_data->city_name;
		}
		return $city_code;

	}

	protected function get_city_data($city_code) {

		global $wpdb;

		if (empty($this->cache)) {
			$cities = $wpdb->get_results(
				"SELECT city_code, city_name, city_name_en, is_imported FROM {$wpdb->prefix}oc_woo_shipping_cities_base ORDER BY city_name"
			);
			if ($cities) {
				foreach ($cities as $row) {
					$this->cache[$row->city_code] = $row;
				}
			}
		}
		if (isset($this->cache[$city_code])) {
			return $this->cache[$city_code];
		}
		/*$city_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT city_code, city_name, city_name_en, is_imported FROM {$wpdb->prefix}oc_woo_shipping_cities_base WHERE city_code = %s LIMIT 1",
				$city_code
			)
		);
		$this->cache[$city_code] = $city_data;*/
		return null;
	}
}
