<?php
/**
 * Wolt webhook: on courier status change, add order note and update meta.
 *
 * @package Oc_Woo_Shipping
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
	 * Permission: allow unauthenticated (Wolt server calls). Validate by shared secret if needed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function permission_callback( $request ) {
		return true;
	}

	/**
	 * Handle webhook payload: find order by delivery_id, add order note with status change.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_webhook( $request ) {
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Invalid JSON' ), 400 );
		}
		$delivery_id = isset( $body['delivery_id'] ) ? $body['delivery_id'] : ( isset( $body['id'] ) ? $body['id'] : '' );
		$status      = isset( $body['status'] ) ? $body['status'] : ( isset( $body['courier_status'] ) ? $body['courier_status'] : '' );
		$message     = isset( $body['message'] ) ? $body['message'] : '';
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
			__( 'Wolt: Courier status changed to %1$s. %2$s', 'ocws' ),
			$status ?: __( 'N/A', 'ocws' ),
			$message ?: ''
		);
		$order->add_order_note( trim( $note ) );
		$order->update_meta_data( '_ocws_wolt_webhook_status', $status );
		$order->save();
		return new WP_REST_Response( array( 'ok' => true ), 200 );
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
