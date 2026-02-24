<?php
/**
 * Wolt Drive API client (shipment-promises, deliveries).
 *
 * @package Oc_Woo_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Api
 */
class OCWS_Wolt_Api {

	const OPTION_API_URL   = 'ocws_wolt_api_url';
	const OPTION_API_KEY   = 'ocws_wolt_api_key';

	/**
	 * Get base API URL (no trailing slash).
	 *
	 * @return string
	 */
	public static function get_api_url() {
		$url = get_option( self::OPTION_API_URL, '' );
		return rtrim( $url, '/' );
	}

	/**
	 * Get API key / bearer token.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Check if API is configured enough to call.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return self::get_api_url() !== '' && self::get_api_key() !== '';
	}

	/**
	 * Request shipment promise (quote) for a destination.
	 *
	 * @param array $destination Package destination (address, city, address_coords, etc.).
	 * @return array{ success: bool, cost?: float, error?: string, raw?: array }
	 */
	public static function get_shipment_promise( $destination ) {
		$url = self::get_api_url();
		if ( ! $url ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL not configured.', 'ocws' ) );
		}
		$endpoint = $url . '/shipment-promises';
		$body     = self::build_shipment_promise_body( $destination );
		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . self::get_api_key(),
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$raw  = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $raw['message'] ) ? $raw['message'] : wp_remote_retrieve_body( $response );
			return array( 'success' => false, 'error' => $msg, 'raw' => $raw );
		}
		$cost = null;
		if ( isset( $raw['price']['amount'] ) && is_numeric( $raw['price']['amount'] ) ) {
			$cost = (float) $raw['price']['amount'];
		} elseif ( isset( $raw['amount'] ) && is_numeric( $raw['amount'] ) ) {
			$cost = (float) $raw['amount'];
		}
		if ( $cost === null ) {
			return array( 'success' => false, 'error' => __( 'Invalid shipment promise response.', 'ocws' ), 'raw' => $raw );
		}
		return array( 'success' => true, 'cost' => $cost, 'raw' => $raw );
	}

	/**
	 * Build request body for shipment-promises from package destination.
	 *
	 * @param array $destination From $package['destination'].
	 * @return array
	 */
	protected static function build_shipment_promise_body( $destination ) {
		$body = array();
		if ( ! empty( $destination['address_coords']['lat'] ) && ! empty( $destination['address_coords']['lng'] ) ) {
			$body['location'] = array(
				'lat' => (float) $destination['address_coords']['lat'],
				'lng' => (float) $destination['address_coords']['lng'],
			);
		}
		if ( ! empty( $destination['address'] ) ) {
			$body['address'] = array(
				'street_address' => $destination['address'],
				'city'           => isset( $destination['city'] ) ? $destination['city'] : '',
				'postal_code'    => isset( $destination['postcode'] ) ? $destination['postcode'] : '',
			);
		}
		return $body;
	}

	/**
	 * Create delivery (POST /deliveries).
	 *
	 * @param WC_Order $order Order.
	 * @param array    $payload Delivery payload (address, scheduled_dropoff_time, etc.).
	 * @return array{ success: bool, delivery_id?: string, tracking_url?: string, error?: string, raw?: array }
	 */
	public static function create_delivery( $order, $payload ) {
		$url = self::get_api_url();
		if ( ! $url ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL not configured.', 'ocws' ) );
		}
		$endpoint = $url . '/deliveries';
		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type'   => 'application/json',
					'Authorization' => 'Bearer ' . self::get_api_key(),
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$raw  = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $raw['message'] ) ? $raw['message'] : wp_remote_retrieve_body( $response );
			return array( 'success' => false, 'error' => $msg, 'raw' => $raw );
		}
		$delivery_id   = isset( $raw['id'] ) ? $raw['id'] : ( isset( $raw['delivery_id'] ) ? $raw['delivery_id'] : '' );
		$tracking_url  = isset( $raw['tracking_url'] ) ? $raw['tracking_url'] : ( isset( $raw['tracking_link'] ) ? $raw['tracking_link'] : '' );
		return array(
			'success'      => true,
			'delivery_id'  => $delivery_id,
			'tracking_url' => $tracking_url,
			'raw'          => $raw,
		);
	}
}
