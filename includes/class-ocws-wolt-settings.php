<?php
/**
 * Settings registry for OC Wolt Drive. Pure data layer — no UI here.
 * The settings page renders in OCWS_Wolt_Admin.
 *
 * Option names are kept stable (ocws_wolt_*) so installations that already
 * configured them from the legacy host-plugin module migrate cleanly.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Settings
 */
if ( ! class_exists( 'OCWS_Wolt_Settings' ) ) :
class OCWS_Wolt_Settings {

	const OPTION_ENABLED            = 'ocws_wolt_enabled';
	const OPTION_TRIGGER_STATUS     = 'ocws_wolt_trigger_status';
	const OPTION_DISPATCH_OFFSET    = 'ocws_wolt_dispatch_offset_minutes';
	const OPTION_MARKUP_TYPE        = 'ocws_wolt_markup_type';
	const OPTION_MARKUP_VALUE       = 'ocws_wolt_markup_value';
	const OPTION_API_URL            = 'ocws_wolt_api_url';
	const OPTION_API_KEY            = 'ocws_wolt_api_key';
	const OPTION_PICKUP_ADDRESS     = 'ocws_wolt_pickup_address';
	const OPTION_VENUE_ID           = 'ocws_wolt_venue_id';
	const OPTION_MERCHANT_ID        = 'ocws_wolt_merchant_id';
	const OPTION_WEBHOOK_SECRET     = 'ocws_wolt_webhook_secret';
	const OPTION_CURRENCY           = 'ocws_wolt_currency';
	const OPTION_METHOD_ID_PREFIX   = 'ocws_wolt_method_id_prefix';

	const SETTINGS_GROUP            = 'ocws_wolt_settings';

	const DEFAULT_SANDBOX_URL       = 'https://daas-public-api.development.dev.woltapi.com';
	const DEFAULT_PRODUCTION_URL    = 'https://daas-public-api.wolt.com';
	const DEFAULT_METHOD_ID_PREFIX  = 'oc_woo_advanced_shipping_method';

	/**
	 * Register every option in our group so options.php POSTs save cleanly
	 * and register_setting sanitisation runs.
	 */
	public static function register_options() {
		$schema = array(
			self::OPTION_ENABLED          => array( 'sanitize' => array( __CLASS__, 'sanitize_bool' ),         'default' => '' ),
			self::OPTION_TRIGGER_STATUS   => array( 'sanitize' => 'sanitize_key',                              'default' => 'wc-processing' ),
			self::OPTION_DISPATCH_OFFSET  => array( 'sanitize' => 'absint',                                    'default' => '30' ),
			self::OPTION_MARKUP_TYPE      => array( 'sanitize' => array( __CLASS__, 'sanitize_markup_type' ),  'default' => 'fixed' ),
			self::OPTION_MARKUP_VALUE     => array( 'sanitize' => array( __CLASS__, 'sanitize_float' ),        'default' => '0' ),
			self::OPTION_API_URL          => array( 'sanitize' => 'esc_url_raw',                               'default' => self::DEFAULT_SANDBOX_URL ),
			self::OPTION_API_KEY          => array( 'sanitize' => 'sanitize_text_field',                       'default' => '' ),
			self::OPTION_PICKUP_ADDRESS   => array( 'sanitize' => 'sanitize_text_field',                       'default' => '' ),
			self::OPTION_VENUE_ID         => array( 'sanitize' => 'sanitize_text_field',                       'default' => '' ),
			self::OPTION_MERCHANT_ID      => array( 'sanitize' => 'sanitize_text_field',                       'default' => '' ),
			self::OPTION_WEBHOOK_SECRET   => array( 'sanitize' => 'sanitize_text_field',                       'default' => '' ),
			self::OPTION_CURRENCY         => array( 'sanitize' => array( __CLASS__, 'sanitize_currency' ),     'default' => 'ILS' ),
			self::OPTION_METHOD_ID_PREFIX => array( 'sanitize' => 'sanitize_text_field',                       'default' => self::DEFAULT_METHOD_ID_PREFIX ),
		);
		foreach ( $schema as $option => $spec ) {
			register_setting(
				self::SETTINGS_GROUP,
				$option,
				array(
					'sanitize_callback' => $spec['sanitize'],
					'default'           => $spec['default'],
				)
			);
		}
	}

	/* ─── Sanitisers ──────────────────────────────────────────────── */

	public static function sanitize_bool( $value ) {
		return ( '1' === (string) $value || 1 === $value || true === $value ) ? '1' : '';
	}

	public static function sanitize_markup_type( $value ) {
		return ( 'percentage' === $value ) ? 'percentage' : 'fixed';
	}

	public static function sanitize_float( $value ) {
		return is_numeric( $value ) ? (string) (float) $value : '0';
	}

	public static function sanitize_currency( $value ) {
		$value = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $value ), 0, 3 ) );
		return '' === $value ? 'ILS' : $value;
	}

	/* ─── Getters ─────────────────────────────────────────────────── */

	public static function is_enabled() {
		return '1' === get_option( self::OPTION_ENABLED, '' );
	}

	public static function get_trigger_status() {
		return (string) get_option( self::OPTION_TRIGGER_STATUS, 'wc-processing' );
	}

	public static function get_dispatch_offset_minutes() {
		return max( 0, (int) get_option( self::OPTION_DISPATCH_OFFSET, 30 ) );
	}

	public static function get_markup_type() {
		return 'percentage' === get_option( self::OPTION_MARKUP_TYPE, 'fixed' ) ? 'percentage' : 'fixed';
	}

	public static function get_markup_value() {
		return (float) get_option( self::OPTION_MARKUP_VALUE, 0 );
	}

	public static function get_venue_id() {
		return trim( (string) get_option( self::OPTION_VENUE_ID, '' ) );
	}

	public static function get_merchant_id() {
		return trim( (string) get_option( self::OPTION_MERCHANT_ID, '' ) );
	}

	public static function get_webhook_secret() {
		return (string) get_option( self::OPTION_WEBHOOK_SECRET, '' );
	}

	public static function get_currency() {
		$c = trim( (string) get_option( self::OPTION_CURRENCY, '' ) );
		if ( '' === $c ) {
			$c = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'ILS';
		}
		return strtoupper( $c );
	}

	/**
	 * Get the configured shipping-method ID prefix that Wolt should override
	 * pricing for. Default matches the OC Advanced Shipping plugin.
	 *
	 * @return string
	 */
	public static function get_method_id_prefix() {
		$prefix = trim( (string) get_option( self::OPTION_METHOD_ID_PREFIX, self::DEFAULT_METHOD_ID_PREFIX ) );
		return '' === $prefix ? self::DEFAULT_METHOD_ID_PREFIX : $prefix;
	}

	/**
	 * Pickup address: explicit override → WooCommerce store address → site title.
	 *
	 * @return string
	 */
	public static function get_pickup_address() {
		$custom = trim( (string) get_option( self::OPTION_PICKUP_ADDRESS, '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}
		return self::get_woocommerce_store_address_formatted();
	}

	/**
	 * Build a one-line formatted address from WooCommerce → Settings → General.
	 *
	 * @return string
	 */
	public static function get_woocommerce_store_address_formatted() {
		$parts = array_filter(
			array(
				get_option( 'woocommerce_store_address', '' ),
				get_option( 'woocommerce_store_address_2', '' ),
				trim( get_option( 'woocommerce_store_city', '' ) . ' ' . get_option( 'woocommerce_store_postcode', '' ) ),
			)
		);
		$country = get_option( 'woocommerce_default_country', '' );
		if ( $country ) {
			if ( strpos( $country, ':' ) !== false ) {
				$country = explode( ':', $country )[0];
			}
			$parts[] = $country;
		}
		$formatted = implode( ', ', array_map( 'trim', $parts ) );
		return '' === $formatted ? get_bloginfo( 'name' ) : $formatted;
	}

	/**
	 * Apply the configured markup (fixed amount or percentage) to a base cost.
	 *
	 * @param float $cost Quote returned by Wolt.
	 * @return float
	 */
	public static function apply_markup( $cost ) {
		$value = self::get_markup_value();
		return 'percentage' === self::get_markup_type()
			? $cost * ( 1 + $value / 100 )
			: $cost + $value;
	}
}
endif;
