<?php
/**
 * On order status change (trigger status): POST to Wolt /deliveries with scheduled_dropoff_time = slot_start + offset, or ASAP.
 *
 * @package Oc_Woo_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Delivery_Trigger
 */
class OCWS_Wolt_Delivery_Trigger {

	const META_STATUS    = '_ocws_wolt_status';
	const META_DELIVERY_ID = '_ocws_wolt_delivery_id';
	const META_TRACKING_URL = '_ocws_wolt_tracking_url';
	const META_LAST_ERROR = '_ocws_wolt_last_error';

	/**
	 * Register dynamic status hook.
	 */
	public static function init() {
		$status = OCWS_Wolt_Settings::get_trigger_status();
		add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'on_trigger_status' ), 10, 2 );
	}

	/**
	 * When order reaches trigger status: create Wolt delivery, save meta, add order note.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order (optional).
	 */
	public static function on_trigger_status( $order_id, $order = null ) {
		if ( ! OCWS_Wolt_Settings::is_enabled() || ! OCWS_Wolt_Api::is_configured() ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		// Only for orders that used OC advanced shipping (delivery).
		$is_oc_shipping = false;
		foreach ( $order->get_shipping_methods() as $item ) {
			if ( strpos( $item->get_method_id(), 'oc_woo_advanced_shipping_method' ) === 0 ) {
				$is_oc_shipping = true;
				break;
			}
		}
		if ( ! $is_oc_shipping ) {
			return;
		}
		if ( $order->get_meta( self::META_DELIVERY_ID ) ) {
			return; // Already created.
		}
		$payload = self::build_delivery_payload( $order );
		$result  = OCWS_Wolt_Api::create_delivery( $order, $payload );
		if ( $result['success'] ) {
			$order->update_meta_data( self::META_STATUS, 'created' );
			$order->update_meta_data( self::META_DELIVERY_ID, $result['delivery_id'] );
			if ( ! empty( $result['tracking_url'] ) ) {
				$order->update_meta_data( self::META_TRACKING_URL, $result['tracking_url'] );
			}
			$order->delete_meta_data( self::META_LAST_ERROR );
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: 1: delivery id, 2: tracking url */
					__( 'Wolt: Shipment created. Delivery ID: %1$s. Tracking: %2$s', 'ocws' ),
					$result['delivery_id'],
					$result['tracking_url'] ? $result['tracking_url'] : __( 'N/A', 'ocws' )
				)
			);
		} else {
			$order->update_meta_data( self::META_STATUS, 'failed' );
			$order->update_meta_data( self::META_LAST_ERROR, $result['error'] );
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Wolt: Shipment creation failed. %s', 'ocws' ),
					$result['error']
				)
			);
		}
	}

	/**
	 * Build delivery payload: address from order, scheduled_dropoff_time = slot_start + offset (ISO 8601) or omit for ASAP.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected static function build_delivery_payload( $order ) {
		$name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( '' === $name ) {
			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}

		$phone = $order->get_meta( '_shipping_phone' );
		if ( ! $phone ) {
			$phone = $order->get_billing_phone();
		}

		$dropoff = array(
			'location' => array(
				'formatted_address' => $order->get_formatted_shipping_address(),
			),
		);
		$comments = self::build_dropoff_comments( $order );
		if ( '' !== $comments ) {
			$dropoff['comments'] = $comments;
		}

		$payload = array(
			'merchant_order_reference_id' => (string) $order->get_id(),
			'pickup'                      => array(
				'location' => array(
					'formatted_address' => OCWS_Wolt_Settings::get_pickup_address(),
				),
			),
			'dropoff'                     => $dropoff,
			'recipient'                   => array(
				'name'         => $name,
				'phone_number' => $phone,
			),
		);

		$scheduled = self::get_scheduled_dropoff_time_iso8601( $order );
		if ( $scheduled ) {
			$payload['scheduled_dropoff_time'] = $scheduled;
		}
		return $payload;
	}

	/**
	 * Build dropoff comments from floor, apartment, enter code, leave-at-door flag, and customer note.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	protected static function build_dropoff_comments( $order ) {
		$order_id = $order->get_id();

		$floor      = get_post_meta( $order_id, '_shipping_floor', true );
		if ( ! $floor ) {
			$floor = get_post_meta( $order_id, '_billing_floor', true );
		}
		$apartment  = get_post_meta( $order_id, '_shipping_apartment', true );
		if ( ! $apartment ) {
			$apartment = get_post_meta( $order_id, '_billing_apartment', true );
		}
		$enter_code = get_post_meta( $order_id, '_shipping_enter_code', true );
		if ( ! $enter_code ) {
			$enter_code = get_post_meta( $order_id, '_billing_enter_code', true );
		}

		$parts = array();
		if ( $floor ) {
			$parts[] = sprintf( __( 'Floor: %s', 'ocws' ), $floor );
		}
		if ( $apartment ) {
			$parts[] = sprintf( __( 'Apartment: %s', 'ocws' ), $apartment );
		}
		if ( $enter_code ) {
			$parts[] = sprintf( __( 'Door code: %s', 'ocws' ), $enter_code );
		}
		if ( 1 == get_post_meta( $order_id, 'ocws_leave_at_the_door', true ) ) {
			$parts[] = __( 'Leave at the door', 'ocws' );
		}
		$note = $order->get_customer_note();
		if ( $note ) {
			$parts[] = $note;
		}
		return implode( '. ', $parts );
	}

	/**
	 * Get scheduled_dropoff_time: slot_start + dispatch_offset, ISO 8601. Return null if no slot (ASAP).
	 *
	 * @param WC_Order $order Order.
	 * @return string|null
	 */
	public static function get_scheduled_dropoff_time_iso8601( $order ) {
		$date_str = $order->get_meta( 'ocws_shipping_info_date' );
		$slot_start = $order->get_meta( 'ocws_shipping_info_slot_start' );
		if ( ! $date_str || ! $slot_start ) {
			return null;
		}
		$tz = function_exists( 'ocws_get_timezone' ) ? ocws_get_timezone() : wp_timezone_string();
		try {
			$date_part = \Carbon\Carbon::createFromFormat( 'd/m/Y', $date_str, $tz );
		} catch ( Exception $e ) {
			return null;
		}
		$slot_part = trim( $slot_start );
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $slot_part, $m ) ) {
			$date_part->setTime( (int) $m[1], (int) $m[2], 0 );
		} else {
			return null;
		}
		$offset_min = OCWS_Wolt_Settings::get_dispatch_offset_minutes();
		$date_part->addMinutes( $offset_min );
		return $date_part->format( \DateTimeInterface::ATOM );
	}
}
