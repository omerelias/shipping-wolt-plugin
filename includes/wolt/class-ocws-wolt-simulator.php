<?php
/**
 * Wolt simulator: test price (shipment-promises) and scheduled time calculation.
 *
 * @package Oc_Woo_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Simulator
 */
class OCWS_Wolt_Simulator {

	/**
	 * Register admin menu and AJAX handler.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 25 );
		add_action( 'wp_ajax_ocws_wolt_simulate', array( __CLASS__, 'ajax_simulate' ) );
	}

	/**
	 * Add simulator submenu under OC Shipping.
	 */
	public static function add_menu() {
		add_submenu_page(
			'oc-woo-shipping',
			__( 'Wolt Simulator', 'ocws' ),
			__( 'Wolt Simulator', 'ocws' ),
			'manage_woocommerce',
			'ocws-wolt-simulator',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render simulator page: form (address, dummy slot), result (price + scheduled time).
	 */
	public static function render_page() {
		if ( ! OCWS_Wolt_Settings::is_enabled() ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Enable Wolt in Default settings first.', 'ocws' ) . '</p></div>';
			return;
		}
		$api_url = OCWS_Wolt_Api::get_api_url();
		$api_key = OCWS_Wolt_Api::get_api_key();
		if ( ! $api_url || ! $api_key ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Configure Wolt API URL and API Key in Default settings.', 'ocws' ) . '</p></div>';
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Wolt Drive Simulator', 'ocws' ); ?></h1>
			<p><?php esc_html_e( 'Test price (shipment-promises) and scheduled dropoff time calculation with a dummy address and slot.', 'ocws' ); ?></p>
			<form id="ocws-wolt-simulator-form" style="max-width: 480px;">
				<table class="form-table">
					<tr>
						<th><label for="sim_address"><?php esc_html_e( 'Address (or leave for default)', 'ocws' ); ?></label></th>
						<td><input type="text" id="sim_address" name="address" class="regular-text" placeholder="<?php esc_attr_e( 'Street, City', 'ocws' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="sim_lat"><?php esc_html_e( 'Latitude', 'ocws' ); ?></label></th>
						<td><input type="text" id="sim_lat" name="lat" placeholder="32.0853" /></td>
					</tr>
					<tr>
						<th><label for="sim_lng"><?php esc_html_e( 'Longitude', 'ocws' ); ?></label></th>
						<td><input type="text" id="sim_lng" name="lng" placeholder="34.7818" /></td>
					</tr>
					<tr>
						<th><label for="sim_slot_date"><?php esc_html_e( 'Slot date (d/m/Y)', 'ocws' ); ?></label></th>
						<td><input type="text" id="sim_slot_date" name="slot_date" placeholder="25/02/2025" /></td>
					</tr>
					<tr>
						<th><label for="sim_slot_start"><?php esc_html_e( 'Slot start (HH:MM)', 'ocws' ); ?></label></th>
						<td><input type="text" id="sim_slot_start" name="slot_start" placeholder="16:00" /></td>
					</tr>
				</table>
				<p>
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Run simulation', 'ocws' ); ?>" />
					<span id="ocws-sim-spinner" class="spinner" style="float: none; display: none;"></span>
				</p>
			</form>
			<div id="ocws-sim-result" style="margin-top: 16px; padding: 12px; background: #f0f0f1; border-left: 4px solid #2271b1; display: none;"></div>
		</div>
		<script>
		jQuery(function($) {
			$('#ocws-wolt-simulator-form').on('submit', function(e) {
				e.preventDefault();
				var $form = $(this), $spinner = $('#ocws-sim-spinner'), $result = $('#ocws-sim-result');
				$spinner.show();
				$result.hide();
				$.post(ajaxurl, {
					action: 'ocws_wolt_simulate',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ocws_wolt_simulate' ) ); ?>',
					address: $form.find('[name="address"]').val(),
					lat: $form.find('[name="lat"]').val(),
					lng: $form.find('[name="lng"]').val(),
					slot_date: $form.find('[name="slot_date"]').val(),
					slot_start: $form.find('[name="slot_start"]').val()
				}).done(function(r) {
					var html = (r.data && r.data.html) ? r.data.html : (r.html || r.message || 'OK');
					$result.html(html).show();
				}).fail(function() {
					$result.html('Request failed.').show();
				}).always(function() {
					$spinner.hide();
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: run simulation (price + scheduled time), return HTML for result box.
	 */
	public static function ajax_simulate() {
		check_ajax_referer( 'ocws_wolt_simulate', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'ocws' ) ) );
		}
		$destination = array(
			'address' => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'city'    => '',
			'postcode'=> '',
		);
		$lat = isset( $_POST['lat'] ) ? sanitize_text_field( wp_unslash( $_POST['lat'] ) ) : '';
		$lng = isset( $_POST['lng'] ) ? sanitize_text_field( wp_unslash( $_POST['lng'] ) ) : '';
		if ( $lat !== '' && $lng !== '' && is_numeric( $lat ) && is_numeric( $lng ) ) {
			$destination['address_coords'] = array( 'lat' => (float) $lat, 'lng' => (float) $lng );
		}
		$price_result = OCWS_Wolt_Api::get_shipment_promise( $destination );
		$slot_date    = isset( $_POST['slot_date'] ) ? sanitize_text_field( wp_unslash( $_POST['slot_date'] ) ) : '';
		$slot_start   = isset( $_POST['slot_start'] ) ? sanitize_text_field( wp_unslash( $_POST['slot_start'] ) ) : '';
		$scheduled_iso = null;
		if ( $slot_date && $slot_start ) {
			$tz = function_exists( 'ocws_get_timezone' ) ? ocws_get_timezone() : wp_timezone_string();
			try {
				$date_part = \Carbon\Carbon::createFromFormat( 'd/m/Y', $slot_date, $tz );
				if ( preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $slot_start ), $m ) ) {
					$date_part->setTime( (int) $m[1], (int) $m[2], 0 );
					$offset_min = OCWS_Wolt_Settings::get_dispatch_offset_minutes();
					$date_part->addMinutes( $offset_min );
					$scheduled_iso = $date_part->toIso8601String();
				}
			} catch ( Exception $e ) {
				$scheduled_iso = 'Error: ' . $e->getMessage();
			}
		}
		$html = '<h3>' . esc_html__( 'Price (shipment-promises)', 'ocws' ) . '</h3>';
		if ( $price_result['success'] ) {
			$with_markup = OCWS_Wolt_Settings::apply_markup( $price_result['cost'] );
			$html .= '<p><strong>' . esc_html__( 'Quote (before markup)', 'ocws' ) . ':</strong> ' . esc_html( number_format_i18n( $price_result['cost'], 2 ) ) . '</p>';
			$html .= '<p><strong>' . esc_html__( 'With markup', 'ocws' ) . ':</strong> ' . esc_html( number_format_i18n( $with_markup, 2 ) ) . '</p>';
		} else {
			$html .= '<p style="color:#a00">' . esc_html( $price_result['error'] ) . '</p>';
		}
		$html .= '<h3>' . esc_html__( 'Scheduled dropoff time', 'ocws' ) . '</h3>';
		if ( $scheduled_iso ) {
			$html .= '<p><strong>scheduled_dropoff_time (ISO 8601):</strong><br><code>' . esc_html( $scheduled_iso ) . '</code></p>';
		} else {
			$html .= '<p>' . esc_html__( 'No slot provided or invalid format; delivery would be sent as ASAP.', 'ocws' ) . '</p>';
		}
		wp_send_json_success( array( 'html' => $html ) );
	}
}
