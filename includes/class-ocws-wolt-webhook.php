<?php
/**
 * Wolt webhook: on courier status change, add order note and update meta.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Webhook
 */
class OCWS_Wolt_Webhook {

	/**
	 * Register REST route for Wolt webhook.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Register REST route.
	 */
	public static function register_route() {
		register_rest_route( 'ocws-wolt/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => array( __CLASS__, 'permission_callback' ),
			'args'                => array(),
		) );
	}

	/**
	 * Permission callback: open to public (Wolt servers call us), JWT verification happens in handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function permission_callback( $request ) {
		return true;
	}

	/**
	 * Handle webhook payload. If a shared secret is configured, request body must be a JWT
	 * signed with HS256 using that secret; payload is read from the JWT claims.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_webhook( $request ) {
		$secret  = OCWS_Wolt_Settings::get_webhook_secret();
		$payload = null;

		if ( '' !== $secret ) {
			$raw_body = $request->get_body();
			$token    = trim( (string) $raw_body );
			$auth     = $request->get_header( 'authorization' );
			if ( $auth && stripos( $auth, 'Bearer ' ) === 0 ) {
				$token = trim( substr( $auth, 7 ) );
			}
			if ( '' === $token ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Missing JWT' ), 401 );
			}
			$decoded = self::verify_jwt_hs256( $token, $secret );
			if ( null === $decoded ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Invalid JWT signature' ), 401 );
			}
			$payload = $decoded;
		} else {
			$payload = $request->get_json_params();
		}

		if ( ! is_array( $payload ) || empty( $payload ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Invalid payload' ), 400 );
		}

		// Wolt may nest the event data; flatten common shapes.
		$data = $payload;
		if ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
			$data = $payload['data'];
		} elseif ( isset( $payload['order'] ) && is_array( $payload['order'] ) ) {
			$data = $payload['order'];
		}

		$delivery_id = $data['delivery_id'] ?? ( $data['id'] ?? ( $payload['delivery_id'] ?? '' ) );
		$status      = $data['status'] ?? ( $data['courier_status'] ?? ( $payload['event_type'] ?? '' ) );
		$message     = $data['message'] ?? ( $payload['message'] ?? '' );

		if ( ! $delivery_id ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Missing delivery_id' ), 400 );
		}
		$order_id = self::find_order_by_delivery_id( $delivery_id );
		if ( ! $order_id ) {
			return new WP_REST_Response( array( 'ok' => true, 'note' => 'Order not found' ), 200 );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'ok' => true, 'note' => 'Order not found' ), 200 );
		}
		$note = sprintf(
			/* translators: 1: status, 2: message */
			__( 'Wolt: Courier status changed to %1$s. %2$s', 'oc-wolt-drive' ),
			$status ?: __( 'N/A', 'oc-wolt-drive' ),
			$message ?: ''
		);
		$order->add_order_note( trim( $note ) );
		$order->update_meta_data( '_ocws_wolt_webhook_status', $status );
		$order->save();
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Verify an HS256 JWT and return its decoded payload, or null on failure.
	 *
	 * Implements minimal HS256 verification (header alg=HS256, exp claim respected if present).
	 * Avoids pulling in firebase/php-jwt to keep vendor footprint small.
	 *
	 * @param string $token JWT token (header.payload.signature, base64url-encoded).
	 * @param string $secret Shared HMAC secret.
	 * @return array|null Decoded payload claims, or null if invalid.
	 */
	protected static function verify_jwt_hs256( $token, $secret ) {
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 3 ) {
			return null;
		}
		list( $h64, $p64, $s64 ) = $parts;
		$header_json  = self::b64url_decode( $h64 );
		$payload_json = self::b64url_decode( $p64 );
		$signature    = self::b64url_decode( $s64 );
		if ( false === $header_json || false === $payload_json || false === $signature ) {
			return null;
		}
		$header = json_decode( $header_json, true );
		$claims = json_decode( $payload_json, true );
		if ( ! is_array( $header ) || ! is_array( $claims ) ) {
			return null;
		}
		if ( ! isset( $header['alg'] ) || strtoupper( $header['alg'] ) !== 'HS256' ) {
			return null;
		}
		$expected = hash_hmac( 'sha256', $h64 . '.' . $p64, $secret, true );
		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}
		// Enforce exp if present.
		if ( isset( $claims['exp'] ) && is_numeric( $claims['exp'] ) && time() >= (int) $claims['exp'] ) {
			return null;
		}
		// Enforce nbf if present.
		if ( isset( $claims['nbf'] ) && is_numeric( $claims['nbf'] ) && time() < (int) $claims['nbf'] - 60 ) {
			return null;
		}
		return $claims;
	}

	/**
	 * Base64url decode (RFC 7515 §3).
	 *
	 * @param string $data Input.
	 * @return string|false
	 */
	protected static function b64url_decode( $data ) {
		$pad = strlen( $data ) % 4;
		if ( $pad > 0 ) {
			$data .= str_repeat( '=', 4 - $pad );
		}
		return base64_decode( strtr( $data, '-_', '+/' ), true );
	}

	/**
	 * Find order ID by Wolt delivery ID (stored in _ocws_wolt_delivery_id).
	 *
	 * @param string $delivery_id Wolt delivery ID.
	 * @return int|null
	 */
	protected static function find_order_by_delivery_id( $delivery_id ) {
		$orders = wc_get_orders( array(
			'limit'      => 1,
			'meta_key'   => '_ocws_wolt_delivery_id',
			'meta_value' => $delivery_id,
			'return'     => 'ids',
		) );
		return ! empty( $orders ) ? (int) $orders[0] : null;
	}
}
