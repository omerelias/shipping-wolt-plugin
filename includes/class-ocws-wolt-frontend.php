<?php
/**
 * Customer-facing surfaces: tracking card on order-received (thank you) +
 * "My account" order details. Also adds a tracking row to WooCommerce
 * order confirmation / processing emails.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Frontend
 */
if ( ! class_exists( 'OCWS_Wolt_Frontend' ) ) :
class OCWS_Wolt_Frontend {

	/**
	 * Wire frontend hooks. Called from OCWS_Wolt::load().
	 */
	public static function init() {
		// Thank-you page (?order-received=…) + "View order" in My Account.
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'render_tracking_card' ), 5 );

		// Customer + processing emails — single row, plain text safe.
		add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'email_meta_fields' ), 10, 3 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Conditionally enqueue the front-end CSS only on pages that may show
	 * the card (order-received + My Account view-order). Cheap check to avoid
	 * loading on every page.
	 */
	public static function enqueue_assets() {
		if ( function_exists( 'is_wc_endpoint_url' )
			&& ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'view-order' ) ) ) {
			wp_enqueue_style(
				'ocws-wolt-frontend',
				OCWS_WOLT_URL . 'assets/css/frontend.css',
				array(),
				OCWS_WOLT_VERSION
			);
		}
	}

	/**
	 * Render the tracking card under the order summary table.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function render_tracking_card( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$tracking_url = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_TRACKING_URL );
		$delivery_id  = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID );
		if ( ! $tracking_url && ! $delivery_id ) {
			return; // No Wolt delivery on this order.
		}

		$wolt_status   = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_WOLT_STATUS );
		$dropoff_eta   = OCWS_Wolt_Delivery_Trigger::get_dropoff_eta_display( $order );
		$delivered_at  = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERED_AT );

		list( $headline, $subline ) = self::status_copy( $wolt_status, $dropoff_eta, $delivered_at );

		?>
		<section class="ocws-wolt-track-card" aria-label="<?php esc_attr_e( 'Delivery tracking', 'oc-wolt-drive' ); ?>">
			<div class="ocws-wolt-track-card__brand">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
					<path fill="currentColor" d="M3 12.5 5 7h2l1.6 4 1.4-4h2.1l1.4 4 1.6-4h2l-3 7h-2l-1-3-1 3H6.4z"/>
					<circle cx="18.5" cy="15.5" r="1.5" fill="currentColor"/>
				</svg>
				<span><?php esc_html_e( 'Wolt Drive', 'oc-wolt-drive' ); ?></span>
			</div>
			<h2 class="ocws-wolt-track-card__headline"><?php echo esc_html( $headline ); ?></h2>
			<?php if ( $subline ) : ?>
				<p class="ocws-wolt-track-card__subline"><?php echo esc_html( $subline ); ?></p>
			<?php endif; ?>
			<?php if ( $tracking_url ) : ?>
				<a class="ocws-wolt-track-card__btn" href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Track delivery', 'oc-wolt-drive' ); ?>
					<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
						<path fill="currentColor" d="M5 12h12.2l-4.6-4.6 1.4-1.4L21 12l-7 7-1.4-1.4 4.6-4.6H5z"/>
					</svg>
				</a>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Human-readable headline + subline based on Wolt's courier status.
	 *
	 * @param string $wolt_status  Wolt status (UPPERCASE_SNAKE).
	 * @param string $dropoff_eta  Already-formatted "HH:MM" or "HH:MM – HH:MM".
	 * @param string $delivered_at Optional ISO 8601 if delivered.
	 * @return string[] [ headline, subline ]
	 */
	protected static function status_copy( $wolt_status, $dropoff_eta, $delivered_at ) {
		$s = strtoupper( (string) $wolt_status );
		switch ( $s ) {
			case 'DELIVERED':
			case 'COMPLETED':
				$delivered_local = $delivered_at ? OCWS_Wolt_Delivery_Trigger::format_local_time( $delivered_at ) : '';
				return array(
					__( 'Delivered. Enjoy!', 'oc-wolt-drive' ),
					$delivered_local
						? sprintf( /* translators: %s: time */ __( 'Handed over at %s.', 'oc-wolt-drive' ), $delivered_local )
						: '',
				);
			case 'CANCELLED':
			case 'REJECTED':
			case 'FAILED':
				return array(
					__( 'Delivery cancelled.', 'oc-wolt-drive' ),
					__( 'Contact the store if this is unexpected.', 'oc-wolt-drive' ),
				);
			case 'PICKED_UP':
			case 'DROPOFF_STARTED':
			case 'DROPOFF_ARRIVAL':
				return array(
					__( 'Your order is on the way.', 'oc-wolt-drive' ),
					$dropoff_eta
						? sprintf( /* translators: %s: time or range */ __( 'Estimated arrival: %s.', 'oc-wolt-drive' ), $dropoff_eta )
						: __( 'The courier is heading to you.', 'oc-wolt-drive' ),
				);
			case 'PICKUP_STARTED':
			case 'PICKUP_ARRIVAL':
				return array(
					__( 'A Wolt courier is picking up your order.', 'oc-wolt-drive' ),
					$dropoff_eta
						? sprintf( /* translators: %s: time or range */ __( 'Estimated arrival: %s.', 'oc-wolt-drive' ), $dropoff_eta )
						: '',
				);
			default:
				// INFO_RECEIVED / CREATED / RECEIVED / unknown
				return array(
					__( 'Delivery booked.', 'oc-wolt-drive' ),
					$dropoff_eta
						? sprintf( /* translators: %s: time or range */ __( 'Estimated arrival: %s.', 'oc-wolt-drive' ), $dropoff_eta )
						: __( 'A Wolt courier will be assigned shortly.', 'oc-wolt-drive' ),
				);
		}
	}

	/**
	 * Append a plain-text tracking row to customer-facing WC emails.
	 *
	 * @param array     $fields Existing rows.
	 * @param bool      $sent_to_admin Whether the email is for admin.
	 * @param WC_Order  $order Order.
	 * @return array
	 */
	public static function email_meta_fields( $fields, $sent_to_admin, $order ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return $fields;
		}
		$tracking_url = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_TRACKING_URL );
		if ( ! $tracking_url ) {
			return $fields;
		}
		$fields['ocws_wolt_tracking'] = array(
			'label' => __( 'Track your delivery', 'oc-wolt-drive' ),
			'value' => $tracking_url,
		);
		return $fields;
	}
}
endif;
