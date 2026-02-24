<?php
/**
 * Order meta box: Wolt status, Delivery ID, Tracking link.
 *
 * @package Oc_Woo_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Order_Meta_Box
 */
class OCWS_Wolt_Order_Meta_Box {

	/**
	 * Register add_meta_box for shop_order.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
	}

	/**
	 * Add Wolt Info meta box.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'ocws_wolt_info',
			__( 'Wolt Drive', 'ocws' ),
			array( __CLASS__, 'render' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Render meta box content.
	 *
	 * @param WP_Post $post Post (order).
	 */
	public static function render( $post ) {
		$order = wc_get_order( $post->ID );
		if ( ! $order ) {
			return;
		}
		$status = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_STATUS );
		$delivery_id = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID );
		$tracking_url = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_TRACKING_URL );
		$last_error = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_LAST_ERROR );
		if ( ! $status && ! $delivery_id && ! $last_error ) {
			echo '<p>' . esc_html__( 'No Wolt shipment for this order.', 'ocws' ) . '</p>';
			return;
		}
		echo '<p><strong>' . esc_html__( 'Status', 'ocws' ) . ':</strong> ' . esc_html( $status ?: '-' ) . '</p>';
		if ( $delivery_id ) {
			echo '<p><strong>' . esc_html__( 'Delivery ID', 'ocws' ) . ':</strong> ' . esc_html( $delivery_id ) . '</p>';
		}
		if ( $tracking_url ) {
			echo '<p><strong>' . esc_html__( 'Tracking', 'ocws' ) . ':</strong> <a href="' . esc_url( $tracking_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View tracking', 'ocws' ) . '</a></p>';
		}
		if ( $last_error ) {
			echo '<p><strong>' . esc_html__( 'Last error', 'ocws' ) . ':</strong> <span style="color:#a00">' . esc_html( $last_error ) . '</span></p>';
		}
	}
}
