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

	const ACTION = 'ocws_wolt_create_delivery';

	/**
	 * Register add_meta_box for shop_order, and admin-post handler for manual create.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_create_action' ) );
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

		if ( $status || $delivery_id || $last_error ) {
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
		} else {
			echo '<p>' . esc_html__( 'No Wolt shipment for this order yet.', 'ocws' ) . '</p>';
		}

		if ( ! $delivery_id && OCWS_Wolt_Api::is_configured() ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=' . self::ACTION . '&order_id=' . $order->get_id() ),
				self::ACTION . '_' . $order->get_id()
			);
			echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Create Wolt delivery now', 'ocws' ) . '</a></p>';
		}
	}

	/**
	 * Handle the manual "Create delivery" admin-post action.
	 */
	public static function handle_create_action() {
		$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
		if ( ! $order_id || ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ocws' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION . '_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'ocws' ), '', array( 'response' => 404 ) );
		}

		$result   = OCWS_Wolt_Delivery_Trigger::create_for_order( $order, true );
		$redirect = wp_get_referer() ?: admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		$flag     = ! empty( $result['success'] ) ? 'ocws_wolt_ok' : 'ocws_wolt_err';
		$redirect = add_query_arg( $flag, '1', $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}
}
