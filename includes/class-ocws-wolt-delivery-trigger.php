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

	const META_STATUS              = '_ocws_wolt_status';            // our state: created | failed | …
	const META_DELIVERY_ID         = '_ocws_wolt_delivery_id';       // raw `id` from POST /deliveries
	const META_WOLT_ORDER_REF      = '_ocws_wolt_order_reference_id';// `wolt_order_reference_id` — webhooks reference this
	const META_WOLT_STATUS         = '_ocws_wolt_wolt_status';       // Wolt's courier state: INFO_RECEIVED, PICKED_UP, DELIVERED, …
	const META_TRACKING_URL        = '_ocws_wolt_tracking_url';
	const META_TRACKING_ID         = '_ocws_wolt_tracking_id';       // short tracking code (suitable for SMS)
	const META_PICKUP_ETA          = '_ocws_wolt_pickup_eta';        // ISO 8601 — when courier arrives at venue
	const META_DROPOFF_ETA_MIN     = '_ocws_wolt_dropoff_eta_min';   // ISO 8601 — earliest customer arrival
	const META_DROPOFF_ETA_MAX     = '_ocws_wolt_dropoff_eta_max';   // ISO 8601 — latest customer arrival
	const META_COST_AMOUNT         = '_ocws_wolt_cost_amount';       // numeric, major units (e.g. 42.00)
	const META_COST_CURRENCY       = '_ocws_wolt_cost_currency';     // ISO 4217
	const META_DELIVERED_AT        = '_ocws_wolt_delivered_at';      // ISO 8601 — set when dropoff_completed event arrives
	const META_LAST_ERROR          = '_ocws_wolt_last_error';

	/**
	 * Register order-lifecycle hooks.
	 *
	 * Why two hooks instead of `woocommerce_order_status_{slug}`:
	 *   - WC fires `woocommerce_order_status_{slug}` only on a STATUS
	 *     TRANSITION. Brand-new orders that are CREATED already in the
	 *     trigger status (typically `pending` for pay-on-delivery / COD)
	 *     never see a transition, so a single-slug hook misses them.
	 *   - `woocommerce_order_status_changed` catches every transition,
	 *     including ones from "" / "auto-draft" to a real status — but
	 *     not all WC versions fire this for the very first save.
	 *   - `woocommerce_new_order` is the only hook guaranteed for every
	 *     freshly created order regardless of its initial status.
	 * Listening to both gives us "any order that ENDS UP in the trigger
	 * status" coverage. `create_for_order()` itself is idempotent
	 * (META_DELIVERY_ID short-circuit), so being called twice for the
	 * same order is safe.
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
		add_action( 'woocommerce_new_order',            array( __CLASS__, 'on_new_order' ),       10, 2 );
	}

	/**
	 * Hook callback: fired on every WC order status transition.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status (without wc- prefix).
	 * @param string   $to       New status (without wc- prefix).
	 * @param WC_Order $order    Order, when WC passes it.
	 */
	public static function on_status_changed( $order_id, $from, $to, $order = null ) {
		if ( ! self::status_matches_trigger( $to ) ) {
			return;
		}
		self::on_trigger_status( $order_id, $order );
	}

	/**
	 * Hook callback: fired once per brand-new order.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order.
	 */
	public static function on_new_order( $order_id, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		if ( ! self::status_matches_trigger( $order->get_status() ) ) {
			return;
		}
		self::on_trigger_status( $order_id, $order );
	}

	/**
	 * Compare an arbitrary status against the configured trigger, tolerating
	 * the `wc-` prefix on either side.
	 *
	 * @param string $status Status to test.
	 * @return bool
	 */
	protected static function status_matches_trigger( $status ) {
		$trigger = preg_replace( '/^wc-/', '', (string) OCWS_Wolt_Settings::get_trigger_status() );
		$current = preg_replace( '/^wc-/', '', (string) $status );
		return '' !== $current && '' !== $trigger && $current === $trigger;
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
			if ( ! empty( $result['wolt_order_reference_id'] ) ) {
				$order->update_meta_data( self::META_WOLT_ORDER_REF, $result['wolt_order_reference_id'] );
			}
			if ( ! empty( $result['wolt_status'] ) ) {
				$order->update_meta_data( self::META_WOLT_STATUS, $result['wolt_status'] );
			}
			if ( ! empty( $result['tracking_url'] ) ) {
				$order->update_meta_data( self::META_TRACKING_URL, $result['tracking_url'] );
			}
			if ( ! empty( $result['tracking_id'] ) ) {
				$order->update_meta_data( self::META_TRACKING_ID, $result['tracking_id'] );
			}
			if ( ! empty( $result['pickup_eta'] ) ) {
				$order->update_meta_data( self::META_PICKUP_ETA, $result['pickup_eta'] );
			}
			if ( ! empty( $result['dropoff_eta_min'] ) ) {
				$order->update_meta_data( self::META_DROPOFF_ETA_MIN, $result['dropoff_eta_min'] );
			}
			if ( ! empty( $result['dropoff_eta_max'] ) ) {
				$order->update_meta_data( self::META_DROPOFF_ETA_MAX, $result['dropoff_eta_max'] );
			}
			if ( null !== $result['cost_amount'] ) {
				$order->update_meta_data( self::META_COST_AMOUNT, $result['cost_amount'] );
				$order->update_meta_data( self::META_COST_CURRENCY, $result['cost_currency'] );
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
		// Pull the OC plugin's custom fields first (they have the real values).
		// Use $order->get_meta() rather than get_post_meta() so this works under
		// WC's High-Performance Order Storage (custom tables) too.
		$street_name = $order->get_meta( '_shipping_street' );
		if ( ! $street_name ) { $street_name = $order->get_meta( '_billing_street' ); }
		$house_num   = $order->get_meta( '_shipping_house_num' );
		if ( ! $house_num )   { $house_num   = $order->get_meta( '_billing_house_num' ); }
		$city_name   = $order->get_meta( '_shipping_city_name' );
		if ( ! $city_name )   { $city_name   = $order->get_meta( '_billing_city_name' ); }
		$coords      = $order->get_meta( '_shipping_address_coords' );
		if ( ! $coords )      { $coords      = $order->get_meta( '_billing_address_coords' ); }

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

		// Wolt's create-delivery validator wants dropoff.location.address as
		// a STRUCTURED OBJECT (not a formatted string). The relevant error
		// is "Input should be a valid dictionary or object to extract
		// fields from" at body.dropoff.location.address.
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
		if ( '' !== $country ) {
			$address['country'] = $country;
		}

		$location = array(
			'address' => $address,
		);

		// Optional lat/lng if the host plugin saved them. Place them as
		// siblings of `address` under `location`.
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
				/* translators: %s: WC order number */
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
		// HPOS-safe meta reads via the order object.
		$floor      = $order->get_meta( '_shipping_floor' );
		if ( ! $floor ) {
			$floor = $order->get_meta( '_billing_floor' );
		}
		$apartment  = $order->get_meta( '_shipping_apartment' );
		if ( ! $apartment ) {
			$apartment = $order->get_meta( '_billing_apartment' );
		}
		$enter_code = $order->get_meta( '_shipping_enter_code' );
		if ( ! $enter_code ) {
			$enter_code = $order->get_meta( '_billing_enter_code' );
		}

		$parts = array();
		if ( $floor ) {
			/* translators: %s: floor number / label */
			$parts[] = sprintf( __( 'Floor: %s', 'oc-wolt-drive' ), $floor );
		}
		if ( $apartment ) {
			/* translators: %s: apartment number / label */
			$parts[] = sprintf( __( 'Apartment: %s', 'oc-wolt-drive' ), $apartment );
		}
		if ( $enter_code ) {
			/* translators: %s: building entry / door code */
			$parts[] = sprintf( __( 'Door code: %s', 'oc-wolt-drive' ), $enter_code );
		}
		if ( '1' === (string) $order->get_meta( 'ocws_leave_at_the_door' ) ) {
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

	/**
	 * Format an ISO 8601 timestamp into the site's local time using WP's
	 * date+time format. Returns an empty string on parse failure.
	 *
	 * @param string $iso ISO 8601 timestamp from Wolt.
	 * @param string $format Optional custom format. Defaults to WP's date+time.
	 * @return string
	 */
	public static function format_local_time( $iso, $format = '' ) {
		if ( ! $iso ) {
			return '';
		}
		try {
			$dt = new DateTime( $iso );
			$dt->setTimezone( wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}
		if ( '' === $format ) {
			$format = get_option( 'time_format', 'H:i' );
		}
		return wp_date( $format, $dt->getTimestamp() );
	}

	/**
	 * Build a "HH:MM" or "HH:MM – HH:MM" string from the dropoff ETA range
	 * stored on an order. Empty when no ETA is known.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function get_dropoff_eta_display( $order ) {
		$min = $order->get_meta( self::META_DROPOFF_ETA_MIN );
		$max = $order->get_meta( self::META_DROPOFF_ETA_MAX );
		$min_l = self::format_local_time( $min );
		$max_l = self::format_local_time( $max );
		if ( '' === $min_l && '' === $max_l ) {
			return '';
		}
		if ( $min_l === $max_l || '' === $max_l ) {
			return $min_l;
		}
		return $min_l . ' – ' . $max_l;
	}
}

endif;
