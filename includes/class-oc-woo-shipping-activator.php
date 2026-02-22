<?php

/**
 * Fired during plugin activation
 *
 * @link       https://originalconcepts.co.il/
 * @since      1.0.0
 *
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Oc_Woo_Shipping
 * @subpackage Oc_Woo_Shipping/includes
 * @author     Milla Shub <milla@originalconcepts.co.il>
 */
class Oc_Woo_Shipping_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		/*
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
		 * As of WP 4.2, however, they moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 */
		$max_index_length = 191;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = "
CREATE TABLE {$wpdb->prefix}oc_woo_shipping_groups (
  group_id BIGINT UNSIGNED NOT NULL auto_increment,
  group_name varchar(200) NOT NULL,
  group_order BIGINT UNSIGNED NOT NULL,
  is_enabled tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY  (group_id)
) $collate;
CREATE TABLE {$wpdb->prefix}oc_woo_shipping_locations (
  location_id BIGINT UNSIGNED NOT NULL auto_increment,
  group_id BIGINT UNSIGNED NOT NULL,
  location_code varchar(50) NOT NULL,
  gm_place_id varchar(50) NOT NULL,
  gm_shapes TEXT,
  gm_streets TEXT,
  location_type varchar(20) NOT NULL,
  location_name varchar(200) NOT NULL,
  location_order BIGINT UNSIGNED NOT NULL,
  is_enabled tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY  (location_id),
  UNIQUE KEY location_code (location_code),
  KEY location_type_code (location_type(10),location_code(20))
) $collate;
CREATE TABLE {$wpdb->prefix}oc_woo_shipping_companies (
  company_id BIGINT UNSIGNED NOT NULL auto_increment,
  company_name varchar(200) NOT NULL,
  company_order BIGINT UNSIGNED NOT NULL,
  is_enabled tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY  (company_id)
) $collate;
CREATE TABLE {$wpdb->prefix}oc_woo_shipping_cities_base (
  city_id BIGINT UNSIGNED NOT NULL auto_increment,
  city_code varchar(50) NOT NULL,
  city_name varchar(200) NOT NULL,
  city_name_en varchar(200) NOT NULL,
  is_imported tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY  (city_id),
  UNIQUE KEY city_code (city_code)
) $collate;
		";

        dbDelta( $tables );

        self::populate_cities_base();
	}

    public static function populate_cities_base() {

      global $wpdb;

      $cities = include plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/cities.php';
      $cities_en = include plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/cities-en.php';
      if ( is_array($cities) ) {
        foreach ( $cities as $city_code => $city_name ) {

          if (isset($cities_en[$city_code])) {
            $city_name_en = $cities_en[$city_code];
          }
          else {
            $city_name_en = $city_name;
          }
          $wpdb->query( $wpdb->prepare( "INSERT INTO `{$wpdb->prefix}oc_woo_shipping_cities_base` (`city_code`, `city_name`, `city_name_en`, `is_imported`) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE `city_name` = VALUES(`city_name`), `city_name_en` = VALUES(`city_name_en`), `is_imported` = VALUES(`is_imported`)", $city_code, $city_name, $city_name_en, '0' ) );

        }
      }

      $imported_cities = array();
      if (file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/imported-cities.php' )) {
        $imported_cities = include plugin_dir_path( dirname( __FILE__ ) ) . 'i18n/imported-cities.php';

        if ( is_array($imported_cities) ) {
          foreach ( $imported_cities as $city_code => $city_name ) {

            $city_name_en = $city_name;
            $wpdb->query( $wpdb->prepare( "INSERT INTO `{$wpdb->prefix}oc_woo_shipping_cities_base` (`city_code`, `city_name`, `city_name_en`, `is_imported`) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE `city_name` = VALUES(`city_name`), `city_name_en` = VALUES(`city_name_en`), `is_imported` = VALUES(`is_imported`)", $city_code, $city_name, $city_name_en, '1' ) );

          }
        }
      }
    }

}
