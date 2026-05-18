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
	protected static function venue_endpoint( $path, $venue_id = null ) {
		$base = self::get_api_url();
		if ( null === $venue_id ) {
			$venue_id = OCWS_Wolt_Settings::get_venue_id();
		} else {
			$venue_id = trim( (string) $venue_id );
		}
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
		$destination = OCWS_Wolt_Settings::merge_oc_checkout_destination(
			is_array( $destination ) ? $destination : array()
		);
		$group_id = OCWS_Wolt_Settings::resolve_group_id_from_destination( $destination );
		$venue_id = OCWS_Wolt_Settings::get_effective_venue_id_for_group( $group_id );
		$endpoint = self::venue_endpoint( 'shipment-promises', $venue_id );
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Venue ID not configured.', 'oc-wolt-drive' ) );
		}
		$body = self::build_shipment_promise_body( $destination );

		// Wolt needs post_code OR (street AND city) at the root of the body.
		// Anything less and the validator rejects — skip the call to avoid
		// log spam on every initial checkout render.
		$has_address = ! empty( $body['post_code'] )
			|| ( ! empty( $body['street'] ) && ! empty( $body['city'] ) );
		if ( ! $has_address ) {
			return array( 'success' => false, 'error' => 'insufficient_address' );
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[OC Wolt SP] destination: ' . wp_json_encode( $destination, JSON_UNESCAPED_UNICODE ) );
			error_log( '[OC Wolt SP] body: ' . wp_json_encode( $body, JSON_UNESCAPED_UNICODE ) );
		}

		$resp = self::request( 'POST', $endpoint, $body, 15 );
		if ( $resp['error'] && $resp['code'] === 0 ) {
			return array( 'success' => false, 'error' => $resp['error'] );
		}
		$raw = is_array( $resp['body'] ) ? $resp['body'] : array();

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[OC Wolt SP] response (HTTP ' . $resp['code'] . '): ' . wp_json_encode( $raw, JSON_UNESCAPED_UNICODE ) );
		}

		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
		}

		// Wolt returns amounts in MINOR currency units (e.g. agorot for ILS,
		// cents for USD/EUR). Convert to major units. For ILS / USD / EUR /
		// most currencies that's divide-by-100; for JPY etc. it'd be 1 — we
		// hard-code 100 because Wolt operate in markets that all use that
		// factor.
		$cost = null;
		if ( isset( $raw['price']['amount'] ) && is_numeric( $raw['price']['amount'] ) ) {
			$cost = ( (float) $raw['price']['amount'] ) / 100;
		} elseif ( isset( $raw['amount'] ) && is_numeric( $raw['amount'] ) ) {
			$cost = ( (float) $raw['amount'] ) / 100;
		}
		if ( null === $cost ) {
			return array( 'success' => false, 'error' => __( 'Invalid shipment promise response.', 'oc-wolt-drive' ), 'raw' => $raw );
		}
		return array( 'success' => true, 'cost' => $cost, 'raw' => $raw );
	}

	/**
	 * Build the request body for POST /v1/venues/{venue_id}/shipment-promises.
	 *
	 * Wolt expects **flat fields at the root**, not nested under `address` —
	 * the documented schema is `{ street, city, post_code, lat, lon, ... }`.
	 * Their validator enforces "post_code OR (street AND city)" against the
	 * root of the body. The coordinate key is `lon`, not `lng`.
	 *
	 * The OC Advanced Shipping plugin populates the destination with its own
	 * keys (`street`, `house_num`, `city_name`) from the Google autocomplete
	 * flow; WC's standard `address` / `address_1` / `city` keys are usually
	 * empty in that setup, so resolve_* tries several sources.
	 *
	 * @param array $destination From $package['destination'].
	 * @return array Flat body suitable for wp_json_encode.
	 */
	protected static function build_shipment_promise_body( $destination ) {
		$body = array();

		$street = self::resolve_street( $destination );
		$city   = self::resolve_city( $destination );
		$post   = isset( $destination['postcode'] ) ? trim( (string) $destination['postcode'] ) : '';

		if ( '' !== $street ) {
			$body['street'] = $street;
		}
		if ( '' !== $city ) {
			$body['city'] = $city;
		}
		if ( '' !== $post ) {
			$body['post_code'] = $post;
		}
		if ( ! empty( $destination['address_coords']['lat'] ) && ! empty( $destination['address_coords']['lng'] ) ) {
			$body['lat'] = (float) $destination['address_coords']['lat'];
			$body['lon'] = (float) $destination['address_coords']['lng'];
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
	 * @param string|null $venue_id Optional venue id (per OC shipping group override).
	 * @return array{ success: bool, delivery_id?: string, tracking_url?: string, error?: string, raw?: array }
	 */
	public static function create_delivery( $order, $payload, $venue_id = null ) {
		if ( null !== $venue_id ) {
			$venue_id = trim( (string) $venue_id );
		}
		if ( null === $venue_id || '' === $venue_id ) {
			$venue_id = OCWS_Wolt_Settings::get_venue_id();
		}
		$endpoint = self::venue_endpoint( 'deliveries', $venue_id );
		if ( null === $endpoint ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL or Venue ID not configured.', 'oc-wolt-drive' ) );
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[OC Wolt CD] body: ' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );
		}

		$resp = self::request( 'POST', $endpoint, $payload, 20 );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[OC Wolt CD] response (HTTP ' . $resp['code'] . '): ' . wp_json_encode( is_array( $resp['body'] ) ? $resp['body'] : array( 'raw' => $resp['error'] ), JSON_UNESCAPED_UNICODE ) );
		}

		if ( $resp['error'] && $resp['code'] === 0 ) {
			return array( 'success' => false, 'error' => $resp['error'] );
		}
		$raw = is_array( $resp['body'] ) ? $resp['body'] : array();
		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
		}
		// Field paths in the real Wolt response:
		//   id                        → our internal META_DELIVERY_ID
		//   wolt_order_reference_id   → the value future webhook events
		//                               reference; needed for lookup
		//   status                    → Wolt's own delivery state
		//                               (INFO_RECEIVED, PICKED_UP, …)
		//   tracking.url              → public tracking page link
		$delivery_id    = isset( $raw['id'] ) ? (string) $raw['id'] : ( isset( $raw['delivery_id'] ) ? (string) $raw['delivery_id'] : '' );
		$wolt_order_ref = isset( $raw['wolt_order_reference_id'] ) ? (string) $raw['wolt_order_reference_id'] : '';
		$wolt_status    = isset( $raw['status'] ) ? (string) $raw['status'] : '';
		$tracking_url   = '';
		if ( isset( $raw['tracking']['url'] ) ) {
			$tracking_url = (string) $raw['tracking']['url'];
		} elseif ( isset( $raw['tracking_url'] ) ) {
			$tracking_url = (string) $raw['tracking_url'];
		} elseif ( isset( $raw['tracking_link'] ) ) {
			$tracking_url = (string) $raw['tracking_link'];
		}

		$tracking_id  = isset( $raw['tracking']['id'] ) ? (string) $raw['tracking']['id'] : '';
		$pickup_eta   = isset( $raw['pickup']['eta'] )  ? (string) $raw['pickup']['eta']  : '';
		// Create-delivery returns dropoff.eta as a single string; webhook events
		// return it as { min, max }. Capture both shapes.
		$dropoff_eta_min = '';
		$dropoff_eta_max = '';
		if ( isset( $raw['dropoff']['eta'] ) ) {
			if ( is_array( $raw['dropoff']['eta'] ) ) {
				$dropoff_eta_min = isset( $raw['dropoff']['eta']['min'] ) ? (string) $raw['dropoff']['eta']['min'] : '';
				$dropoff_eta_max = isset( $raw['dropoff']['eta']['max'] ) ? (string) $raw['dropoff']['eta']['max'] : '';
			} else {
				$dropoff_eta_min = (string) $raw['dropoff']['eta'];
				$dropoff_eta_max = $dropoff_eta_min;
			}
		}

		// Wolt returns the delivery price in minor currency units (agorot).
		$cost_amount   = null;
		$cost_currency = '';
		if ( isset( $raw['price']['amount'] ) && is_numeric( $raw['price']['amount'] ) ) {
			$cost_amount   = ( (float) $raw['price']['amount'] ) / 100;
			$cost_currency = isset( $raw['price']['currency'] ) ? (string) $raw['price']['currency'] : '';
		}

		// Pickup display name (Wolt's own label for the venue) + customer_support
		// (their support contact for this specific delivery).
		$pickup_display_name = isset( $raw['pickup']['display_name'] ) ? (string) $raw['pickup']['display_name'] : '';
		$customer_support    = isset( $raw['customer_support'] ) && is_array( $raw['customer_support'] ) ? $raw['customer_support'] : array();

		return array(
			'success'                 => true,
			'delivery_id'             => $delivery_id,
			'wolt_order_reference_id' => $wolt_order_ref,
			'wolt_status'             => $wolt_status,
			'tracking_url'            => $tracking_url,
			'tracking_id'             => $tracking_id,
			'pickup_eta'              => $pickup_eta,
			'dropoff_eta_min'         => $dropoff_eta_min,
			'dropoff_eta_max'         => $dropoff_eta_max,
			'cost_amount'             => $cost_amount,
			'cost_currency'           => $cost_currency,
			'pickup_display_name'     => $pickup_display_name,
			'customer_support'        => $customer_support,
			'raw'                     => $raw,
		);
	}

	/**
	 * Cancel an active Wolt delivery.
	 *
	 * Per Wolt docs: PATCH /order/{wolt_order_reference_id}/status/cancel.
	 * The cancel endpoint is NOT scoped to the venue path. Cancellation
	 * is only valid until the courier accepts the pickup task — beyond
	 * that, Wolt returns 4xx and the merchant has to call Wolt support.
	 *
	 * @param string $wolt_order_reference_id  Wolt order reference id.
	 * @param string $reason                   Required cancellation reason.
	 * @return array{ success: bool, error?: string, raw?: array }
	 */
	public static function cancel_delivery( $wolt_order_reference_id, $reason ) {
		$base = self::get_api_url();
		if ( '' === $base ) {
			return array( 'success' => false, 'error' => __( 'Wolt API URL not configured.', 'oc-wolt-drive' ) );
		}
		if ( '' === trim( (string) $wolt_order_reference_id ) ) {
			return array( 'success' => false, 'error' => __( 'Missing wolt_order_reference_id.', 'oc-wolt-drive' ) );
		}
		$endpoint = $base . '/order/' . rawurlencode( $wolt_order_reference_id ) . '/status/cancel';
		$body     = array( 'reason' => (string) $reason );
		$resp     = self::request( 'PATCH', $endpoint, $body, 15 );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[OC Wolt Cancel] HTTP ' . $resp['code'] . ' resp: ' . wp_json_encode( $resp['body'], JSON_UNESCAPED_UNICODE ) );
		}

		$raw = is_array( $resp['body'] ) ? $resp['body'] : array();
		if ( $resp['code'] < 200 || $resp['code'] >= 300 ) {
			return array( 'success' => false, 'error' => $resp['error'], 'raw' => $raw );
		}
		return array( 'success' => true, 'raw' => $raw );
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
