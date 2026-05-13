<?php
/**
 * Wolt Drive API client (shipment-promises, deliveries).
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Api
 */
if ( ! class_exists( 'OCWS_Wolt_Api' ) ) :
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
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Venue ID not configured.', 'oc-wolt-drive' ) );
		}
		$body = self::build_shipment_promise_body( $destination );

		// Skip the API call when we cannot satisfy Wolt's minimum payload
		// (Wolt needs post_code OR street+city — sending an empty body
		// yields "Input should be a valid dictionary" and just spams logs).
		$has_address = isset( $body['address'] ) && (
			! empty( $body['address']['post_code'] ) ||
			( ! empty( $body['address']['street'] ) && ! empty( $body['address']['city'] ) )
		);
		if ( ! $has_address ) {
			return array( 'success' => false, 'error' => 'insufficient_address' );
		}

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
			return array( 'success' => false, 'error' => __( 'Invalid shipment promise response.', 'oc-wolt-drive' ), 'raw' => $raw );
		}
		return array( 'success' => true, 'cost' => $cost, 'raw' => $raw );
	}

	/**
	 * Build request body for shipment-promises from package destination.
	 *
	 * Wolt's API requires either `post_code` OR (`street` AND `city`). Field
	 * names are exact: `street`, `city`, `post_code` (underscore, not camel,
	 * not `street_address`/`postal_code` — the docs were misleading).
	 *
	 * The OC Advanced Shipping plugin populates the destination with its own
	 * keys (`street`, `house_num`, `city_name`) from the Google autocomplete
	 * flow; WC's standard `address` / `address_1` / `city` keys are usually
	 * empty in that setup, so we try several sources in priority order.
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

		$street = self::resolve_street( $destination );
		$city   = self::resolve_city( $destination );
		$post   = isset( $destination['postcode'] ) ? trim( (string) $destination['postcode'] ) : '';

		$address = array();
		if ( '' !== $street ) {
			$address['street'] = $street;
		}
		if ( '' !== $city ) {
			$address['city'] = $city;
		}
		if ( '' !== $post ) {
			$address['post_code'] = $post;
		}
		if ( ! empty( $address ) ) {
			$body['address'] = $address;
		}

		return $body;
	}

	/**
	 * Pick the best street string from a destination/order address bag.
	 *
	 * Priority: OC plugin's `street` + `house_num` → WC `address_1` → WC `address`.
	 *
	 * @param array $a Destination or order-derived address array.
	 * @return string
	 */
	public static function resolve_street( $a ) {
		if ( ! empty( $a['street'] ) ) {
			$house = isset( $a['house_num'] ) ? trim( (string) $a['house_num'] ) : '';
			return trim( $a['street'] . ( '' !== $house ? ' ' . $house : '' ) );
		}
		if ( ! empty( $a['address_1'] ) ) {
			return (string) $a['address_1'];
		}
		if ( ! empty( $a['address'] ) ) {
			return (string) $a['address'];
		}
		return '';
	}

	/**
	 * Pick the best city string. Priority: city_name → city.
	 *
	 * @param array $a Destination or order-derived address array.
	 * @return string
	 */
	public static function resolve_city( $a ) {
		if ( ! empty( $a['city_name'] ) ) {
			return (string) $a['city_name'];
		}
		if ( ! empty( $a['city'] ) ) {
			return (string) $a['city'];
		}
		return '';
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
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Venue ID not configured.', 'oc-wolt-drive' ) );
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
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Merchant ID not configured.', 'oc-wolt-drive' ) );
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

	/* ─── Webhook management ─────────────────────────────────────── */

	/**
	 * Build a webhook endpoint URL: {base}/v1/merchants/{merchant_id}/webhooks[/{webhook_id}].
	 *
	 * @param string|null $webhook_id Optional webhook id for single-resource endpoints.
	 * @return string|null
	 */
	protected static function webhook_endpoint( $webhook_id = null ) {
		$base        = self::get_api_url();
		$merchant_id = OCWS_Wolt_Settings::get_merchant_id();
		if ( '' === $base || '' === $merchant_id ) {
			return null;
		}
		$path = '/v1/merchants/' . rawurlencode( $merchant_id ) . '/webhooks';
		if ( $webhook_id ) {
			$path .= '/' . rawurlencode( $webhook_id );
		}
		return $base . $path;
	}

	/**
	 * Register a webhook at Wolt. Returns the created resource (including its id).
	 *
	 * @param string $callback_url  Public URL Wolt will POST events to.
	 * @param string $client_secret HS256 signing secret (shared between Wolt and us).
	 * @param array  $retry_config  Optional override for callback_config.exponential_retry_backoff.
	 * @return array{ success: bool, id?: string, raw?: array, error?: string }
	 */
	public static function register_webhook( $callback_url, $client_secret, $retry_config = array() ) {
		$endpoint = self::webhook_endpoint();
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Merchant ID not configured.', 'oc-wolt-drive' ) );
		}
		if ( '' === trim( (string) $callback_url ) || '' === trim( (string) $client_secret ) ) {
			return array( 'success' => false, 'error' => __( 'callback_url and client_secret are required.', 'oc-wolt-drive' ) );
		}
		$body = array(
			'callback_url'    => $callback_url,
			'client_secret'   => $client_secret,
			'disabled'        => false,
			'callback_config' => array(
				'exponential_retry_backoff' => array(
					'exponent_base'   => isset( $retry_config['exponent_base'] )   ? (int) $retry_config['exponent_base']   : 5,
					'max_retry_count' => isset( $retry_config['max_retry_count'] ) ? (int) $retry_config['max_retry_count'] : 3,
				),
			),
		);
		$resp = self::request( 'POST', $endpoint, $body, 15 );
		if ( $resp['error'] && 0 === $resp['code'] ) {
			return array( 'success' => false, 'error' => $resp['error'] );
		}
		$raw = is_array( $resp['body'] ) ? $resp['body'] : array();
		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
		}
		$id = isset( $raw['id'] ) ? (string) $raw['id'] : '';
		return array( 'success' => true, 'id' => $id, 'raw' => $raw );
	}

	/**
	 * Get a single webhook's current configuration (to verify it still exists / is enabled).
	 *
	 * @param string $webhook_id Webhook id returned at registration.
	 * @return array{ success: bool, raw?: array, error?: string }
	 */
	public static function get_webhook( $webhook_id ) {
		$endpoint = self::webhook_endpoint( $webhook_id );
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Merchant ID not configured.', 'oc-wolt-drive' ) );
		}
		$resp = self::request( 'GET', $endpoint, null, 15 );
		$raw  = is_array( $resp['body'] ) ? $resp['body'] : array();
		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
		}
		return array( 'success' => true, 'raw' => $raw );
	}

	/**
	 * Delete a webhook at Wolt. Wolt stops sending events after this.
	 *
	 * @param string $webhook_id Webhook id returned at registration.
	 * @return array{ success: bool, error?: string }
	 */
	public static function delete_webhook( $webhook_id ) {
		$endpoint = self::webhook_endpoint( $webhook_id );
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Merchant ID not configured.', 'oc-wolt-drive' ) );
		}
		$resp = self::request( 'DELETE', $endpoint, null, 15 );
		if ( $resp['error'] && 0 === $resp['code'] ) {
			return array( 'success' => false, 'error' => $resp['error'] );
		}
		// 204 No Content is the documented success response; treat 404 (already gone) as success too.
		if ( 204 === $resp['code'] || 200 === $resp['code'] || 404 === $resp['code'] ) {
			return array( 'success' => true );
		}
		return array( 'success' => false, 'error' => $resp['error'] );
	}
}

endif;
