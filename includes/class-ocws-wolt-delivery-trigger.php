<?php
/**
 * On order status change (trigger status): POST to Wolt /deliveries with scheduled_dropoff_time = slot_start + offset, or ASAP.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Delivery_Trigger
 */
if ( ! class_exists( 'OCWS_Wolt_Delivery_Trigger' ) ) :
class OCWS_Wolt_Delivery_Trigger {

	const META_STATUS    = '_ocws_wolt_status';
	const META_DELIVERY_ID = '_ocws_wolt_delivery_id';
	const META_TRACKING_URL = '_ocws_wolt_tracking_url';
	const META_LAST_ERROR = '_ocws_wolt_last_error';

	/**
	 * Register dynamic status hook. WC fires woocommerce_order_status_{slug}
	 * WITHOUT the `wc-` prefix (`pending`, `processing`, …) — but
	 * wc_get_order_statuses() returns keys WITH the prefix (`wc-pending`).
	 * Strip it so the hook actually matches.
	 */
	public static function init() {
		$status = preg_replace( '/^wc-/', '', OCWS_Wolt_Settings::get_trigger_status() );
		add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'on_trigger_status' ), 10, 2 );
	}

	/**
	 * When order reaches trigger status: create Wolt delivery (auto path).
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
		self::create_for_order( $order, false );
	}

	/**
	 * Create a Wolt delivery for an order (used both by status hook and admin "Create now" button).
	 *
	 * @param WC_Order $order Order.
	 * @param bool     $manual If true, called from admin button: bypass shipping-method check, return result array.
	 * @return array{ success: bool, delivery_id?: string, tracking_url?: string, error?: string }
	 */
	public static function create_for_order( $order, $manual = false ) {
		if ( ! OCWS_Wolt_Api::is_configured() ) {
			return array( 'success' => false, 'error' => __( 'Wolt API not configured.', 'oc-wolt-drive' ) );
		}
		// In auto mode, only act on orders that used the configured host shipping method.
		if ( ! $manual ) {
			$prefix         = OCWS_Wolt_Settings::get_method_id_prefix();
			$is_oc_shipping = false;
			foreach ( $order->get_shipping_methods() as $item ) {
				if ( strpos( (string) $item->get_method_id(), $prefix ) === 0 ) {
					$is_oc_shipping = true;
					break;
				}
			}
			if ( ! $is_oc_shipping ) {
				return array( 'success' => false, 'error' => 'not_host_shipping' );
			}
		}
		if ( $order->get_meta( self::META_DELIVERY_ID ) ) {
			return array( 'success' => false, 'error' => __( 'Wolt delivery already exists for this order.', 'oc-wolt-drive' ) );
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
					__( 'Wolt: Shipment created. Delivery ID: %1$s. Tracking: %2$s', 'oc-wolt-drive' ),
					$result['delivery_id'],
					$result['tracking_url'] ? $result['tracking_url'] : __( 'N/A', 'oc-wolt-drive' )
				)
			);
		} else {
			$order->update_meta_data( self::META_STATUS, 'failed' );
			$order->update_meta_data( self::META_LAST_ERROR, $result['error'] );
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Wolt: Shipment creation failed. %s', 'oc-wolt-drive' ),
					$result['error']
				)
			);
		}
		return $result;
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
			'location' => self::build_dropoff_location( $order ),
		);
		$comments = self::build_dropoff_comments( $order );
		if ( '' !== $comments ) {
			$dropoff['comments'] = $comments;
		}

		$payload = array(
			'merchant_order_reference_id' => (string) $order->get_id(),
			'order_number'                => (string) $order->get_order_number(),
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
			'parcels'                     => self::build_parcels( $order ),
		);

		$scheduled = self::get_scheduled_dropoff_time_iso8601( $order );
		if ( $scheduled ) {
			$payload['scheduled_dropoff_time'] = $scheduled;
		}
		return $payload;
	}

	/**
	 * Build the structured dropoff.location object Wolt expects.
	 *
	 * Reads the OC Advanced Shipping custom fields (_billing_street /
	 * _billing_house_num / _billing_city_name) the host plugin stores on
	 * the order, with WC's standard shipping address as fallback. Includes
	 * lat/lng if a coords meta is present.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected static function build_dropoff_location( $order ) {
		$order_id = $order->get_id();

		// Pull the OC plugin's custom fields first (they have the real values).
		$street_name = get_post_meta( $order_id, '_shipping_street',   true );
		if ( ! $street_name ) { $street_name = get_post_meta( $order_id, '_billing_street', true ); }
		$house_num   = get_post_meta( $order_id, '_shipping_house_num', true );
		if ( ! $house_num )   { $house_num   = get_post_meta( $order_id, '_billing_house_num', true ); }
		$city_name   = get_post_meta( $order_id, '_shipping_city_name', true );
		if ( ! $city_name )   { $city_name   = get_post_meta( $order_id, '_billing_city_name', true ); }
		$coords      = get_post_meta( $order_id, '_shipping_address_coords', true );
		if ( ! $coords )      { $coords      = get_post_meta( $order_id, '_billing_address_coords', true ); }

		$bag = array(
			'street'    => $street_name ?: '',
			'house_num' => $house_num   ?: '',
			'city_name' => $city_name   ?: '',
			'city'      => $order->get_shipping_city(),
			'address_1' => $order->get_shipping_address_1(),
		);

		$street  = OCWS_Wolt_Api::resolve_street( $bag );
		$city    = OCWS_Wolt_Api::resolve_city( $bag );
		$post    = $order->get_shipping_postcode();
		$country = $order->get_shipping_country();

		// Wolt's create-delivery validator: "Either 'shipment_promise_id'
		// or 'dropoff.location.address' must be defined". Build a single
		// formatted address string and put it in `address`. Helpful
		// structured fields go alongside.
		$address_parts = array_filter( array( $street, $city, $post, $country ) );
		$location      = array(
			'address' => implode( ', ', $address_parts ),
		);
		if ( '' !== $city ) {
			$location['city'] = $city;
		}
		if ( '' !== $post ) {
			$location['post_code'] = $post;
		}
		if ( '' !== $country ) {
			$location['country'] = $country;
		}

		// Optional lat/lng if the host plugin saved it.
		$lat = null;
		$lng = null;
		if ( is_array( $coords ) && ! empty( $coords['lat'] ) && ! empty( $coords['lng'] ) ) {
			$lat = (float) $coords['lat'];
			$lng = (float) $coords['lng'];
		} elseif ( is_string( $coords ) && '' !== $coords ) {
			$decoded = json_decode( $coords, true );
			if ( is_array( $decoded ) && ! empty( $decoded['lat'] ) && ! empty( $decoded['lng'] ) ) {
				$lat = (float) $decoded['lat'];
				$lng = (float) $decoded['lng'];
			}
		}
		if ( null !== $lat && null !== $lng ) {
			$location['lat'] = $lat;
			$location['lng'] = $lng;
		}

		// Last-ditch: ensure `address` is never empty (use WC's formatted shipping).
		if ( '' === $location['address'] ) {
			$location['address'] = $order->get_formatted_shipping_address();
		}
		return $location;
	}

	/**
	 * Build parcels list from WC order line items. Wolt requires at least one parcel
	 * with description, identifier, and a price object { amount, currency }.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected static function build_parcels( $order ) {
		$currency = OCWS_Wolt_Settings::get_currency();
		$parcels  = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$qty   = max( 1, (int) $item->get_quantity() );
			$total = (float) $item->get_total() + (float) $item->get_total_tax();
			$unit  = $qty > 0 ? ( $total / $qty ) : $total;
			for ( $i = 0; $i < $qty; $i++ ) {
				$parcels[] = array(
					'description' => $item->get_name(),
					'identifier'  => sprintf( '%d-%d-%d', $order->get_id(), $item_id, $i ),
					'price'       => array(
						'amount'   => round( $unit, 2 ),
						'currency' => $currency,
					),
				);
			}
		}
		if ( empty( $parcels ) ) {
			$parcels[] = array(
				'description' => sprintf( __( 'Order #%s', 'oc-wolt-drive' ), $order->get_order_number() ),
				'identifier'  => (string) $order->get_id(),
				'price'       => array(
					'amount'   => round( (float) $order->get_total() - (float) $order->get_shipping_total(), 2 ),
					'currency' => $currency,
				),
			);
		}
		return $parcels;
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
			$parts[] = sprintf( __( 'Floor: %s', 'oc-wolt-drive' ), $floor );
		}
		if ( $apartment ) {
			$parts[] = sprintf( __( 'Apartment: %s', 'oc-wolt-drive' ), $apartment );
		}
		if ( $enter_code ) {
			$parts[] = sprintf( __( 'Door code: %s', 'oc-wolt-drive' ), $enter_code );
		}
		if ( 1 == get_post_meta( $order_id, 'ocws_leave_at_the_door', true ) ) {
			$parts[] = __( 'Leave at the door', 'oc-wolt-drive' );
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
	 * Reads OC Advanced Shipping meta keys (ocws_shipping_info_date / _slot_start)
	 * that the host shipping plugin sets on the order at checkout.
	 *
	 * @param WC_Order $order Order.
	 * @return string|null
	 */
	public static function get_scheduled_dropoff_time_iso8601( $order ) {
		$date_str   = $order->get_meta( 'ocws_shipping_info_date' );
		$slot_start = $order->get_meta( 'ocws_shipping_info_slot_start' );
		if ( ! $date_str || ! $slot_start ) {
			return null;
		}
		$tz_str = function_exists( 'ocws_get_timezone' ) ? ocws_get_timezone() : wp_timezone_string();
		try {
			$tz   = new DateTimeZone( $tz_str );
			$date = DateTime::createFromFormat( 'd/m/Y', $date_str, $tz );
		} catch ( Exception $e ) {
			return null;
		}
		if ( ! $date ) {
			return null;
		}
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $slot_start ), $m ) ) {
			return null;
		}
		$date->setTime( (int) $m[1], (int) $m[2], 0 );
		$offset_min = OCWS_Wolt_Settings::get_dispatch_offset_minutes();
		if ( $offset_min > 0 ) {
			$date->add( new DateInterval( 'PT' . $offset_min . 'M' ) );
		}
		return $date->format( DateTimeInterface::ATOM );
	}
}

endif;
