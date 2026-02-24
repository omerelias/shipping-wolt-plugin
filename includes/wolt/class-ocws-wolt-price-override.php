<?php
/**
 * Override shipping rate cost with Wolt shipment-promises quote when Wolt is enabled.
 *
 * @package Oc_Woo_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Price_Override
 */
class OCWS_Wolt_Price_Override {

	const METHOD_ID_PREFIX = 'oc_woo_advanced_shipping_method';

	/**
	 * Register woocommerce_package_rates filter.
	 */
	public static function init() {
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'filter_package_rates' ), 20, 2 );
	}

	/**
	 * Filter package rates: if Wolt enabled, fetch quote and override cost for OC shipping method; fallback to default price on failure.
	 *
	 * @param array $rates Package rates.
	 * @param array $package Package.
	 * @return array
	 */
	public static function filter_package_rates( $rates, $package ) {
		if ( ! OCWS_Wolt_Settings::is_enabled() ) {
			return $rates;
		}
		if ( ! OCWS_Wolt_Api::is_configured() ) {
			return $rates;
		}
		$destination = isset( $package['destination'] ) ? $package['destination'] : array();
		if ( empty( $destination ) ) {
			return $rates;
		}
		$default_price = (float) get_option( 'ocws_default_shipping_price', 0 );
		$result        = OCWS_Wolt_Api::get_shipment_promise( $destination );
		$new_cost      = $result['success'] ? OCWS_Wolt_Settings::apply_markup( $result['cost'] ) : $default_price;
		$new_cost      = round( $new_cost, wc_get_price_decimals() );

		foreach ( $rates as $rate_id => $rate ) {
			if ( ! $rate instanceof WC_Shipping_Rate ) {
				continue;
			}
			if ( strpos( $rate_id, self::METHOD_ID_PREFIX ) !== 0 ) {
				continue;
			}
			$rate->set_cost( $new_cost );
		}
		return $rates;
	}
}
