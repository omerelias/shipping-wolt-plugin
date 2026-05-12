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
	 * Check if API is configured enough to call: URL + key + venue_id.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return self::get_api_url() !== '' && self::get_api_key() !== '' && OCWS_Wolt_Settings::get_venue_id() !== '';
	}

	/**
	 * Build a venueful endpoint URL: {base}/v1/venues/{venue_id}/{path}.
	 *
	 * @param string $path Endpoint path without leading slash (e.g. "deliveries").
	 * @return string|null Full URL, or null if base/venue not configured.
	 */
	protected static function venue_endpoint( $path ) {
		$base     = self::get_api_url();
		$venue_id = OCWS_Wolt_Settings::get_venue_id();
		if ( '' === $base || '' === $venue_id ) {
			return null;
		}
		return $base . '/v1/venues/' . rawurlencode( $venue_id ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Build a merchant endpoint URL: {base}/merchants/{merchant_id}/{path}.
	 *
	 * @param string $path Endpoint path without leading slash.
	 * @return string|null
	 */
	protected static function merchant_endpoint( $path ) {
		$base        = self::get_api_url();
		$merchant_id = OCWS_Wolt_Settings::get_merchant_id();
		if ( '' === $base || '' === $merchant_id ) {
			return null;
		}
		return $base . '/merchants/' . rawurlencode( $merchant_id ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Common HTTP request wrapper. Returns array { code, body (decoded), error }.
	 *
	 * @param string $method POST|GET|PATCH.
	 * @param string $url Full URL.
	 * @param array|null $body JSON body for POST/PATCH.
	 * @param int $timeout Request timeout in seconds.
	 * @return array{ code: int, body: array|null, error: string|null }
	 */
	protected static function request( $method, $url, $body = null, $timeout = 15 ) {
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . self::get_api_key(),
			),
			'timeout' => $timeout,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => $response->get_error_message() );
		}
		$code   = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$err = null;
		if ( $code < 200 || $code >= 300 ) {
			$err = is_array( $decoded ) && isset( $decoded['message'] ) ? $decoded['message'] : wp_remote_retrieve_body( $response );
		}
		return array( 'code' => $code, 'body' => $decoded, 'error' => $err );
	}

	/**
	 * Request shipment promise (quote) for a destination.
	 *
	 * @param array $destination Package destination (address, city, address_coords, etc.).
	 * @return array{ success: bool, cost?: float, error?: string, raw?: array }
	 */
	public static function get_shipment_promise( $destination ) {
		$endpoint = self::venue_endpoint( 'shipment-promises' );
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Venue ID not configured.', 'ocws' ) );
		}
		$body = self::build_shipment_promise_body( $destination );
		$resp = self::request( 'POST', $endpoint, $body, 15 );
		if ( $resp['error'] && $resp['code'] === 0 ) {
			return array( 'success' => false, 'error' => $resp['error'] );
		}
		$raw = is_array( $resp['body'] ) ? $resp['body'] : array();
		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
		}
		$cost = null;
		if ( isset( $raw['price']['amount'] ) && is_numeric( $raw['price']['amount'] ) ) {
			$cost = (float) $raw['price']['amount'];
		} elseif ( isset( $raw['amount'] ) && is_numeric( $raw['amount'] ) ) {
			$cost = (float) $raw['amount'];
		}
		if ( null === $cost ) {
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
	 * Create delivery (POST /v1/venues/{venue_id}/deliveries).
	 *
	 * @param WC_Order $order Order.
	 * @param array    $payload Delivery payload (address, scheduled_dropoff_time, etc.).
	 * @return array{ success: bool, delivery_id?: string, tracking_url?: string, error?: string, raw?: array }
	 */
	public static function create_delivery( $order, $payload ) {
		$endpoint = self::venue_endpoint( 'deliveries' );
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Venue ID not configured.', 'ocws' ) );
		}
		$resp = self::request( 'POST', $endpoint, $payload, 20 );
		if ( $resp['error'] && $resp['code'] === 0 ) {
			return array( 'success' => false, 'error' => $resp['error'] );
		}
		$raw = is_array( $resp['body'] ) ? $resp['body'] : array();
		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
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

	/**
	 * Fetch delivery areas (polygons) for the configured merchant.
	 * Useful for sanity-checking credentials and visualising coverage.
	 *
	 * @return array{ success: bool, areas?: array, error?: string, raw?: array }
	 */
	public static function get_delivery_areas() {
		$endpoint = self::merchant_endpoint( 'delivery-areas' );
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Merchant ID not configured.', 'ocws' ) );
		}
		$resp = self::request( 'GET', $endpoint, null, 15 );
		if ( $resp['error'] && $resp['code'] === 0 ) {
			return array( 'success' => false, 'error' => $resp['error'] );
		}
		$raw = is_array( $resp['body'] ) ? $resp['body'] : array();
		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
		}
		return array( 'success' => true, 'areas' => $raw, 'raw' => $raw );
	}
}
