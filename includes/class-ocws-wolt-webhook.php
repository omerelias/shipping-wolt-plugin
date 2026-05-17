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
if ( ! class_exists( 'OCWS_Wolt_Webhook' ) ) :
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

		// Wolt event payload (per docs):
		//   { dispatched_at, type: "order.received" | "order.picked_up" | …, details: { id, wolt_order_reference_id, merchant_order_reference_id, … } }
		$details = isset( $payload['details'] ) && is_array( $payload['details'] ) ? $payload['details'] : $payload;
		if ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
			$details = $payload['data'];
		} elseif ( isset( $payload['order'] ) && is_array( $payload['order'] ) ) {
			$details = $payload['order'];
		}

		// Collect every identifier Wolt might send so we can find the order
		// regardless of which one they use.
		$wolt_order_ref = $details['wolt_order_reference_id']     ?? '';
		$delivery_id    = $details['id']                          ?? ( $details['delivery_id'] ?? '' );
		$merchant_ref   = $details['merchant_order_reference_id'] ?? '';
		$event_type     = $payload['type']                        ?? ( $details['type'] ?? '' );
		$status         = $details['status']                      ?? ( $details['courier_status'] ?? $event_type );
		$message        = $details['message']                     ?? ( $payload['message']       ?? '' );

		$order_id = 0;
		if ( $wolt_order_ref ) {
			$order_id = self::find_order_by_meta( '_ocws_wolt_order_reference_id', $wolt_order_ref );
		}
		if ( ! $order_id && $delivery_id ) {
			$order_id = self::find_order_by_meta( '_ocws_wolt_delivery_id', $delivery_id );
		}
		if ( ! $order_id && $merchant_ref ) {
			$order = wc_get_order( (int) $merchant_ref );
			if ( $order ) {
				$order_id = $order->get_id();
			}
		}

		if ( ! $order_id ) {
			return new WP_REST_Response( array( 'ok' => true, 'note' => 'Order not found' ), 200 );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'ok' => true, 'note' => 'Order not found' ), 200 );
		}
		$note = sprintf(
			/* translators: 1: event type / status, 2: optional message */
			__( 'Wolt: %1$s. %2$s', 'oc-wolt-drive' ),
			$event_type ?: ( $status ?: __( 'update', 'oc-wolt-drive' ) ),
			$message ?: ''
		);
		$order->add_order_note( trim( $note ) );
		if ( $status ) {
			$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_WOLT_STATUS, $status );
		}
		$order->update_meta_data( '_ocws_wolt_last_event_at', current_time( 'mysql' ) );

		// Refresh ETAs / price / delivered_at from event details when present.
		if ( isset( $details['pickup']['eta'] ) && '' !== $details['pickup']['eta'] ) {
			$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_PICKUP_ETA, (string) $details['pickup']['eta'] );
		}
		if ( isset( $details['dropoff']['eta'] ) ) {
			$eta = $details['dropoff']['eta'];
			if ( is_array( $eta ) ) {
				if ( ! empty( $eta['min'] ) ) {
					$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_DROPOFF_ETA_MIN, (string) $eta['min'] );
				}
				if ( ! empty( $eta['max'] ) ) {
					$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_DROPOFF_ETA_MAX, (string) $eta['max'] );
				}
			} elseif ( '' !== $eta ) {
				$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_DROPOFF_ETA_MIN, (string) $eta );
				$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_DROPOFF_ETA_MAX, (string) $eta );
			}
		}
		if ( ! empty( $details['dropoff']['completed_at'] ) ) {
			$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_DELIVERED_AT, (string) $details['dropoff']['completed_at'] );
		}
		if ( isset( $details['price']['amount'] ) && is_numeric( $details['price']['amount'] ) ) {
			$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_COST_AMOUNT, ( (float) $details['price']['amount'] ) / 100 );
			if ( ! empty( $details['price']['currency'] ) ) {
				$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_COST_CURRENCY, (string) $details['price']['currency'] );
			}
		}

		$order->save();
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Find an order by a single meta key/value pair. Generic helper used
	 * for both delivery_id and wolt_order_reference_id lookups.
	 *
	 * @param string $key   Meta key.
	 * @param string $value Meta value.
	 * @return int Order ID or 0.
	 */
	protected static function find_order_by_meta( $key, $value ) {
		$orders = wc_get_orders( array(
			'limit'      => 1,
			'meta_key'   => $key,
			'meta_value' => $value,
			'return'     => 'ids',
		) );
		return ! empty( $orders ) ? (int) $orders[0] : 0;
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

endif;
