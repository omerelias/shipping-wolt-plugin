<?php
/**
 * Public REST endpoint that lets external systems trigger Wolt dispatch
 * for a WooCommerce order and read back the resulting Wolt info.
 *
 * Endpoint:
 *   POST /wp-json/ocws-wolt/v1/dispatch
 *   Headers: Authorization: Bearer <ocws_wolt_dispatch_api_key>
 *   Body:    { "order_id": 17873 }   OR   { "order_number": "873" }
 *
 * Behaviour (idempotent):
 *   - If the order already has a Wolt delivery → return its current data
 *     without re-creating.
 *   - Otherwise → trigger create_for_order($order, manual=true) which
 *     resolves the venue via available-venues and POSTs to Wolt's
 *     create-delivery. Returns the freshly populated meta.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Dispatch_Api
 */
if ( ! class_exists( 'OCWS_Wolt_Dispatch_Api' ) ) :
class OCWS_Wolt_Dispatch_Api {

	const ROUTE_NAMESPACE = 'ocws-wolt/v1';
	const ROUTE           = '/dispatch';

	/**
	 * Wire up the REST route. Called from OCWS_Wolt::init_components().
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function register_route() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'check_auth' ),
				'args'                => array(
					'order_id'     => array( 'type' => 'integer', 'required' => false ),
					'order_number' => array( 'type' => 'string',  'required' => false ),
				),
			)
		);
	}

	/**
	 * Bearer-token check against the configured dispatch API key.
	 * Returns true when the request is authorised, otherwise a WP_Error so
	 * the REST layer surfaces a 401 with our message (instead of WP's
	 * default "Sorry, you are not allowed to do that").
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public static function check_auth( $request ) {
		$expected = OCWS_Wolt_Settings::get_dispatch_api_key();
		if ( '' === $expected ) {
			return new WP_Error(
				'ocws_wolt_dispatch_disabled',
				__( 'Dispatch API key is not configured.', 'oc-wolt-drive' ),
				array( 'status' => 503 )
			);
		}
		$auth = (string) $request->get_header( 'authorization' );
		if ( '' === $auth ) {
			return new WP_Error(
				'ocws_wolt_dispatch_unauthorized',
				__( 'Missing Authorization header.', 'oc-wolt-drive' ),
				array( 'status' => 401 )
			);
		}
		$provided = '';
		if ( stripos( $auth, 'Bearer ' ) === 0 ) {
			$provided = trim( substr( $auth, 7 ) );
		}
		if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'ocws_wolt_dispatch_unauthorized',
				__( 'Invalid bearer token.', 'oc-wolt-drive' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * Main route handler. Synchronous — by the time create_for_order returns,
	 * Wolt has either accepted (HTTP 201 → all meta populated) or rejected
	 * (we surface their error).
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public static function handle( $request ) {
		$body         = $request->get_json_params();
		$order_id     = isset( $body['order_id'] )     ? (int) $body['order_id']                                   : 0;
		$order_number = isset( $body['order_number'] ) ? sanitize_text_field( (string) $body['order_number'] )     : '';

		// Allow order_id from query string too (for quick GET-style testing
		// via curl `-G --data-urlencode order_id=…`).
		if ( ! $order_id && ! $order_number ) {
			$order_id     = (int) $request->get_param( 'order_id' );
			$order_number = (string) $request->get_param( 'order_number' );
		}

		$order = self::resolve_order( $order_id, $order_number );
		if ( ! $order ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'order_not_found',
					'message' => __( 'No matching WooCommerce order.', 'oc-wolt-drive' ),
				),
				404
			);
		}

		// Already has a Wolt delivery → return current data, idempotent.
		if ( $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'created' => false,
					'message' => __( 'Wolt delivery already exists; returning current data.', 'oc-wolt-drive' ),
					'order'   => self::serialize_order( $order ),
				),
				200
			);
		}

		// Synchronously dispatch — resolves the venue via available-venues
		// then POSTs create-delivery. Either side returns an array with
		// success / error.
		$result = OCWS_Wolt_Delivery_Trigger::create_for_order( $order, true );
		if ( empty( $result['success'] ) ) {
			$err = isset( $result['error'] ) ? (string) $result['error'] : 'unknown';
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'wolt_create_failed',
					'message' => $err,
				),
				502
			);
		}

		// Refresh the order so the meta we just wrote in create_for_order is read here.
		$order = wc_get_order( $order->get_id() );
		return new WP_REST_Response(
			array(
				'success' => true,
				'created' => true,
				'order'   => self::serialize_order( $order ),
			),
			200
		);
	}

	/**
	 * Resolve either an order_id (preferred) or a WC order_number into a
	 * WC_Order, or null when no match.
	 *
	 * @param int    $order_id     Numeric ID.
	 * @param string $order_number WC order number (often the same as the id,
	 *                              but differs when a sequential-numbering
	 *                              plugin is in use).
	 * @return WC_Order|null
	 */
	protected static function resolve_order( $order_id, $order_number ) {
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				return $order;
			}
		}
		if ( '' !== $order_number ) {
			$orders = wc_get_orders( array(
				'limit'            => 1,
				'order_number'     => $order_number,
				'return'           => 'ids',
			) );
			if ( ! empty( $orders ) ) {
				$order = wc_get_order( (int) $orders[0] );
				if ( $order ) {
					return $order;
				}
			}
			// Fallback: treat as integer (handles plain numeric strings).
			if ( ctype_digit( $order_number ) ) {
				$order = wc_get_order( (int) $order_number );
				if ( $order ) {
					return $order;
				}
			}
		}
		return null;
	}

	/**
	 * Build the JSON-friendly response describing the Wolt state of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected static function serialize_order( $order ) {
		$cost_amount      = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COST_AMOUNT );
		$customer_support = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_CUSTOMER_SUPPORT );
		$courier_info     = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COURIER_INFO );

		return array(
			'order_id'                  => $order->get_id(),
			'order_number'              => $order->get_order_number(),
			'internal_status'           => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_STATUS ),
			'wolt_status'               => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_WOLT_STATUS ),
			'delivery_id'               => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID ),
			'wolt_order_reference_id'   => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_WOLT_ORDER_REF ),
			'tracking'                  => array(
				'url' => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_TRACKING_URL ),
				'id'  => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_TRACKING_ID ),
			),
			'venue'                     => array(
				'id'           => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_VENUE_ID ),
				'display_name' => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_PICKUP_DISPLAY_NAME ),
			),
			'etas'                      => array(
				'pickup'      => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_PICKUP_ETA ),
				'dropoff_min' => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DROPOFF_ETA_MIN ),
				'dropoff_max' => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DROPOFF_ETA_MAX ),
				'delivered_at'=> $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERED_AT ),
			),
			'cost'                      => array(
				'amount'   => '' === $cost_amount ? null : (float) $cost_amount,
				'currency' => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COST_CURRENCY ),
			),
			'courier'                   => $courier_info ? json_decode( $courier_info, true ) : null,
			'customer_support'          => $customer_support ? json_decode( $customer_support, true ) : null,
			'last_error'                => $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_LAST_ERROR ),
		);
	}
}
endif;
