<?php
/**
 * Override shipping rate cost with Wolt shipment-promises quote when Wolt is enabled.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Price_Override
 */
if ( ! class_exists( 'OCWS_Wolt_Price_Override' ) ) :
class OCWS_Wolt_Price_Override {

	/**
	 * Register woocommerce_package_rates filter.
	 */
	public static function init() {
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'filter_package_rates' ), 20, 2 );
	}

	/**
	 * Filter package rates: if Wolt enabled, fetch a quote and override the cost
	 * for the configured host shipping method. Falls back to a static default
	 * (ocws_default_shipping_price option, if present) when Wolt returns an error.
	 *
	 * @param array $rates   Package rates keyed by rate id.
	 * @param array $package Package being rated.
	 * @return array
	 */

	public static function filter_package_rates( $rates, $package ) {
		if ( ! OCWS_Wolt_Settings::is_enabled() || ! OCWS_Wolt_Api::is_configured() ) {
			return $rates;
		}
		$destination = isset( $package['destination'] ) ? $package['destination'] : array();
		if ( empty( $destination ) ) {
			return $rates;
		}
		$prefix = OCWS_Wolt_Settings::get_method_id_prefix();

		// Short-circuit: no matching rate, no need to call Wolt.
		$has_matching_rate = false;
		foreach ( $rates as $rate_id => $rate ) {
			if ( $rate instanceof WC_Shipping_Rate && 0 === strpos( $rate_id, $prefix ) ) {
				$has_matching_rate = true;
				break;
			}
		}
		if ( ! $has_matching_rate ) {
			return $rates;
		}

		$default_price = (float) get_option( 'ocws_default_shipping_price', 0 );
		$result        = OCWS_Wolt_Api::get_shipment_promise( $destination );

		if ( empty( $result['success'] ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[OC Wolt] shipment-promises failed: ' . ( isset( $result['error'] ) ? $result['error'] : 'unknown' ) );
		}

		$new_cost = ! empty( $result['success'] ) ? OCWS_Wolt_Settings::apply_markup( (float) $result['cost'] ) : $default_price;
		$new_cost = round( $new_cost, wc_get_price_decimals() );

		foreach ( $rates as $rate_id => $rate ) {
			if ( $rate instanceof WC_Shipping_Rate && 0 === strpos( $rate_id, $prefix ) ) {
				$rate->set_cost( $new_cost );
			}
		}
		return $rates;
	}
}

endif;
