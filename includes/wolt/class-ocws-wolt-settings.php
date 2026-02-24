<?php
/**
 * Wolt Drive admin settings: register options and render Wolt section.
 *
 * @package Oc_Woo_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Settings
 */
class OCWS_Wolt_Settings {

	const OPTION_ENABLED           = 'ocws_wolt_enabled';
	const OPTION_TRIGGER_STATUS    = 'ocws_wolt_trigger_status';
	const OPTION_DISPATCH_OFFSET    = 'ocws_wolt_dispatch_offset_minutes';
	const OPTION_MARKUP_TYPE       = 'ocws_wolt_markup_type';
	const OPTION_MARKUP_VALUE      = 'ocws_wolt_markup_value';
	const OPTION_API_URL           = 'ocws_wolt_api_url';
	const OPTION_API_KEY           = 'ocws_wolt_api_key';
	const OPTION_PICKUP_ADDRESS    = 'ocws_wolt_pickup_address';

	/**
	 * Register Wolt options with WordPress.
	 */
	public static function register_options() {
		register_setting( 'ocws_default', self::OPTION_ENABLED, array( 'default' => '' ) );
		register_setting( 'ocws_default', self::OPTION_TRIGGER_STATUS, array( 'default' => 'wc-processing' ) );
		register_setting( 'ocws_default', self::OPTION_DISPATCH_OFFSET, array( 'default' => '30' ) );
		register_setting( 'ocws_default', self::OPTION_MARKUP_TYPE, array( 'default' => 'fixed' ) );
		register_setting( 'ocws_default', self::OPTION_MARKUP_VALUE, array( 'default' => '0' ) );
		register_setting( 'ocws_default', self::OPTION_API_URL, array( 'default' => '' ) );
		register_setting( 'ocws_default', self::OPTION_API_KEY, array( 'default' => '' ) );
		register_setting( 'ocws_default', self::OPTION_PICKUP_ADDRESS, array( 'default' => '' ) );
	}

	/**
	 * Get pickup address (venue) for Wolt. Not hardcoded: uses setting or WooCommerce store address.
	 *
	 * @return string Formatted pickup address for API.
	 */
	public static function get_pickup_address() {
		$custom = trim( get_option( self::OPTION_PICKUP_ADDRESS, '' ) );
		if ( $custom !== '' ) {
			return $custom;
		}
		return self::get_woocommerce_store_address_formatted();
	}

	/**
	 * Build formatted address from WooCommerce store settings (WooCommerce → Settings → General).
	 *
	 * @return string
	 */
	public static function get_woocommerce_store_address_formatted() {
		$parts = array_filter( array(
			get_option( 'woocommerce_store_address', '' ),
			get_option( 'woocommerce_store_address_2', '' ),
			trim( get_option( 'woocommerce_store_city', '' ) . ' ' . get_option( 'woocommerce_store_postcode', '' ) ),
		) );
		$country = get_option( 'woocommerce_default_country', '' );
		if ( $country ) {
			if ( strpos( $country, ':' ) !== false ) {
				$country = explode( ':', $country )[0];
			}
			$parts[] = $country;
		}
		$formatted = implode( ', ', array_map( 'trim', $parts ) );
		if ( $formatted === '' ) {
			$formatted = get_bloginfo( 'name' );
		}
		return $formatted;
	}

	/**
	 * Check if Wolt pricing is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( self::OPTION_ENABLED, '' ) === '1';
	}

	/**
	 * Get order status that triggers POST /deliveries.
	 *
	 * @return string
	 */
	public static function get_trigger_status() {
		return get_option( self::OPTION_TRIGGER_STATUS, 'wc-processing' );
	}

	/**
	 * Get dispatch offset in minutes (after slot start).
	 *
	 * @return int
	 */
	public static function get_dispatch_offset_minutes() {
		return max( 0, (int) get_option( self::OPTION_DISPATCH_OFFSET, 30 ) );
	}

	/**
	 * Get markup type: 'fixed' or 'percentage'.
	 *
	 * @return string
	 */
	public static function get_markup_type() {
		$t = get_option( self::OPTION_MARKUP_TYPE, 'fixed' );
		return $t === 'percentage' ? 'percentage' : 'fixed';
	}

	/**
	 * Get markup value (fixed amount or percentage 0-100).
	 *
	 * @return float
	 */
	public static function get_markup_value() {
		return (float) get_option( self::OPTION_MARKUP_VALUE, 0 );
	}

	/**
	 * Apply markup to a cost.
	 *
	 * @param float $cost Base cost from Wolt.
	 * @return float
	 */
	public static function apply_markup( $cost ) {
		$type  = self::get_markup_type();
		$value = self::get_markup_value();
		if ( $type === 'percentage' ) {
			return $cost * ( 1 + $value / 100 );
		}
		return $cost + $value;
	}

	/**
	 * Render Wolt Drive settings section HTML (to be included in admin Default settings form).
	 */
	public static function render_settings_section() {
		$enabled        = get_option( self::OPTION_ENABLED, '' ) === '1';
		$trigger        = get_option( self::OPTION_TRIGGER_STATUS, 'wc-processing' );
		$offset         = get_option( self::OPTION_DISPATCH_OFFSET, '30' );
		$markup_type    = get_option( self::OPTION_MARKUP_TYPE, 'fixed' );
		$markup_value   = get_option( self::OPTION_MARKUP_VALUE, '0' );
		$api_url        = get_option( self::OPTION_API_URL, '' );
		$api_key        = get_option( self::OPTION_API_KEY, '' );
		$statuses       = wc_get_order_statuses();
		?>
		<tr><td colspan="2"><h3><?php esc_html_e( 'Wolt Drive Integration', 'ocws' ); ?></h3></td></tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Enable Wolt pricing', 'ocws' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" value="1" <?php checked( $enabled ); ?> />
					<?php esc_html_e( 'Override shipping price with Wolt quote (fallback to default price if unavailable)', 'ocws' ); ?>
				</label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Wolt API URL', 'ocws' ); ?></th>
			<td>
				<input type="url" name="<?php echo esc_attr( self::OPTION_API_URL ); ?>" value="<?php echo esc_attr( $api_url ); ?>" class="regular-text" placeholder="https://api.wolt.com/..." />
				<p class="description"><?php esc_html_e( 'Base URL for Wolt Drive API (e.g. https://restaurant-api.wolt.com/v1).', 'ocws' ); ?></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Wolt API Key', 'ocws' ); ?></th>
			<td>
				<input type="password" name="<?php echo esc_attr( self::OPTION_API_KEY ); ?>" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" />
				<p class="description"><?php esc_html_e( 'Bearer token for Wolt Drive API.', 'ocws' ); ?></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Pickup address (venue)', 'ocws' ); ?></th>
			<td>
				<?php
				$pickup_address = get_option( self::OPTION_PICKUP_ADDRESS, '' );
				$store_fallback = self::get_woocommerce_store_address_formatted();
				?>
				<input type="text"
					name="<?php echo esc_attr( self::OPTION_PICKUP_ADDRESS ); ?>"
					id="ocws_wolt_pickup_address"
					class="regular-text ocws-wolt-pickup-autocomplete"
					value="<?php echo esc_attr( $pickup_address ); ?>"
					placeholder="<?php echo esc_attr( $store_fallback ); ?>"
					autocomplete="off" />
				<p class="description"><?php esc_html_e( 'Address where the courier picks up the order. Start typing for Google address suggestions (uses the same Google Maps API key as in General Settings). Leave empty to use the WooCommerce store address.', 'ocws' ); ?></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Trigger status', 'ocws' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( self::OPTION_TRIGGER_STATUS ); ?>">
					<?php foreach ( $statuses as $status_id => $label ) : ?>
						<option value="<?php echo esc_attr( $status_id ); ?>" <?php selected( $trigger, $status_id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Order status that triggers the API call to create the Wolt delivery.', 'ocws' ); ?></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Dispatch offset (minutes)', 'ocws' ); ?></th>
			<td>
				<input type="number" name="<?php echo esc_attr( self::OPTION_DISPATCH_OFFSET ); ?>" value="<?php echo esc_attr( $offset ); ?>" min="0" step="1" />
				<p class="description"><?php esc_html_e( 'Minutes after slot start for scheduled_dropoff_time (e.g. 30 = delivery at 16:30 if slot is 16:00–19:00).', 'ocws' ); ?></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Markup type', 'ocws' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( self::OPTION_MARKUP_TYPE ); ?>">
					<option value="fixed" <?php selected( $markup_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'ocws' ); ?></option>
					<option value="percentage" <?php selected( $markup_type, 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'ocws' ); ?></option>
				</select>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Markup value', 'ocws' ); ?></th>
			<td>
				<input type="number" name="<?php echo esc_attr( self::OPTION_MARKUP_VALUE ); ?>" value="<?php echo esc_attr( $markup_value ); ?>" min="0" step="0.01" />
				<p class="description"><?php esc_html_e( 'Fixed amount to add, or percentage (e.g. 10 for 10%).', 'ocws' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
