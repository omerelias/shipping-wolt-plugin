<?php
/**
 * Order meta box: Wolt status, Delivery ID, Tracking link.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Order_Meta_Box
 */
if ( ! class_exists( 'OCWS_Wolt_Order_Meta_Box' ) ) :
class OCWS_Wolt_Order_Meta_Box {

	const ACTION = 'ocws_wolt_create_delivery';

	/**
	 * Register add_meta_box for shop_order, and admin-post handler for manual create.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_create_action' ) );
	}

	/**
	 * Add the Wolt meta box. Uses wc_get_page_screen_id( 'shop-order' ) so the
	 * box lands on the right screen under both legacy `post.php` and HPOS
	 * `admin.php?page=wc-orders` setups.
	 */
	public static function add_meta_box() {
		$screen = function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'ocws_wolt_info',
			__( 'Wolt Drive', 'oc-wolt-drive' ),
			array( __CLASS__, 'render' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Render meta box content. WP passes a WP_Post under the legacy screen,
	 * a WC_Order under HPOS — accept both.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order, depending on
	 *                                         which screen we're on.
	 */
	public static function render( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : (int) $post_or_order );

		if ( ! $order ) {
			return;
		}
		$status           = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_STATUS );
		$delivery_id      = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID );
		$wolt_status      = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_WOLT_STATUS );
		$tracking_url     = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_TRACKING_URL );
		$pickup_eta       = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_PICKUP_ETA );
		$dropoff_eta      = OCWS_Wolt_Delivery_Trigger::get_dropoff_eta_display( $order );
		$cost_amount      = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COST_AMOUNT );
		$cost_currency    = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COST_CURRENCY );
		$pickup_display   = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_PICKUP_DISPLAY_NAME );
		$customer_support_json = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_CUSTOMER_SUPPORT );
		$courier_info_json     = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COURIER_INFO );
		$courier_lat      = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COURIER_LAT );
		$courier_lng      = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COURIER_LNG );
		$last_error       = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_LAST_ERROR );

		if ( $status || $delivery_id || $last_error ) {
			echo '<p><strong>' . esc_html__( 'Internal status', 'oc-wolt-drive' ) . ':</strong> ' . esc_html( $status ?: '-' ) . '</p>';
			if ( $wolt_status ) {
				echo '<p><strong>' . esc_html__( 'Wolt status', 'oc-wolt-drive' ) . ':</strong> ' . esc_html( $wolt_status ) . '</p>';
			}
			if ( $pickup_eta ) {
				echo '<p><strong>' . esc_html__( 'Courier ETA at venue', 'oc-wolt-drive' ) . ':</strong> ' . esc_html( OCWS_Wolt_Delivery_Trigger::format_local_time( $pickup_eta ) ) . '</p>';
			}
			if ( $dropoff_eta ) {
				echo '<p><strong>' . esc_html__( 'Delivery ETA', 'oc-wolt-drive' ) . ':</strong> ' . esc_html( $dropoff_eta ) . '</p>';
			}
//			if ( '' !== $cost_amount && null !== $cost_amount ) {
//				$formatted_cost = function_exists( 'wc_price' )
//					? wc_price( $cost_amount, array( 'currency' => $cost_currency ) )
//					: ( number_format_i18n( (float) $cost_amount, 2 ) . ' ' . esc_html( $cost_currency ) );
//				echo '<p><strong>' . esc_html__( 'Wolt cost', 'oc-wolt-drive' ) . ':</strong> ' . wp_kses_post( $formatted_cost ) . '</p>';
//			}
			if ( $pickup_display ) {
				echo '<p><strong>' . esc_html__( 'Pickup venue (Wolt label)', 'oc-wolt-drive' ) . ':</strong> ' . esc_html( $pickup_display ) . '</p>';
			}
			$courier = $courier_info_json ? json_decode( $courier_info_json, true ) : null;
			if ( is_array( $courier ) && ! empty( $courier ) ) {
				$bits = array();
				if ( ! empty( $courier['vehicle_type'] ) ) {
					$bits[] = esc_html( $courier['vehicle_type'] );
				}
				if ( ! empty( $courier['id'] ) ) {
					$bits[] = '#' . esc_html( $courier['id'] );
				}
				if ( ! empty( $bits ) ) {
					echo '<p><strong>' . esc_html__( 'Courier', 'oc-wolt-drive' ) . ':</strong> ' . implode( ' · ', $bits ) . '</p>';
				}
			}
			if ( '' !== $courier_lat && '' !== $courier_lng ) {
				$maps_url = 'https://www.google.com/maps?q=' . rawurlencode( $courier_lat . ',' . $courier_lng );
				echo '<p><strong>' . esc_html__( 'Courier location', 'oc-wolt-drive' ) . ':</strong> '
					. '<a href="' . esc_url( $maps_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View on map', 'oc-wolt-drive' ) . '</a>'
					. '</p>';
			}
			$support = $customer_support_json ? json_decode( $customer_support_json, true ) : null;
			if ( is_array( $support ) ) {
				$support_bits = array();
				if ( ! empty( $support['phone_number'] ) ) {
					$support_bits[] = '<a href="tel:' . esc_attr( $support['phone_number'] ) . '">' . esc_html( $support['phone_number'] ) . '</a>';
				}
				if ( ! empty( $support['email'] ) ) {
					$support_bits[] = '<a href="mailto:' . esc_attr( $support['email'] ) . '">' . esc_html( $support['email'] ) . '</a>';
				}
				if ( ! empty( $support['url'] ) ) {
					$support_bits[] = '<a href="' . esc_url( $support['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Link', 'oc-wolt-drive' ) . '</a>';
				}
				if ( ! empty( $support_bits ) ) {
					echo '<p><strong>' . esc_html__( 'Wolt customer support', 'oc-wolt-drive' ) . ':</strong> ' . implode( ' · ', $support_bits ) . '</p>';
				}
			}
			if ( $delivery_id ) {
				echo '<p><strong>' . esc_html__( 'Delivery ID', 'oc-wolt-drive' ) . ':</strong> <code>' . esc_html( $delivery_id ) . '</code></p>';
			}
			if ( $tracking_url ) {
				echo '<p><strong>' . esc_html__( 'Tracking', 'oc-wolt-drive' ) . ':</strong> <a href="' . esc_url( $tracking_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View tracking', 'oc-wolt-drive' ) . '</a></p>';
			}
			if ( $last_error ) {
				echo '<p><strong>' . esc_html__( 'Last error', 'oc-wolt-drive' ) . ':</strong> <span style="color:#a00">' . esc_html( $last_error ) . '</span></p>';
			}
		} else {
			echo '<p>' . esc_html__( 'No Wolt shipment for this order yet.', 'oc-wolt-drive' ) . '</p>';
		}

		if ( ! $delivery_id && OCWS_Wolt_Api::is_configured() ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=' . self::ACTION . '&order_id=' . $order->get_id() ),
				self::ACTION . '_' . $order->get_id()
			);
			echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Create Wolt delivery now', 'oc-wolt-drive' ) . '</a></p>';
		}
	}

	/**
	 * Handle the manual "Create delivery" admin-post action.
	 */
	public static function handle_create_action() {
		$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
		if ( ! $order_id || ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'oc-wolt-drive' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION . '_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'oc-wolt-drive' ), '', array( 'response' => 404 ) );
		}

		$result   = OCWS_Wolt_Delivery_Trigger::create_for_order( $order, true );
		$redirect = wp_get_referer() ?: ocws_wolt_order_edit_url( $order_id );
		$flag     = ! empty( $result['success'] ) ? 'ocws_wolt_ok' : 'ocws_wolt_err';
		$redirect = add_query_arg( $flag, '1', $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}
}

endif;
