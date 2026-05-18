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
	const OPTION_WEBHOOK_ID         = 'ocws_wolt_webhook_id';
	const OPTION_CURRENCY           = 'ocws_wolt_currency';
	const OPTION_METHOD_ID_PREFIX   = 'ocws_wolt_method_id_prefix';
	const OPTION_LANGUAGE           = 'ocws_wolt_language';            // ISO 639-1 2-letter; '' = auto from determine_locale()
	const OPTION_AGE_CHECK_18       = 'ocws_wolt_age_check_18';        // global flag — every parcel gets dropoff_restrictions
	const OPTION_SUBSCRIBE_LOCATION = 'ocws_wolt_subscribe_location';  // opt-in to high-volume courier location webhook

	const SETTINGS_GROUP            = 'ocws_wolt_settings';
	const SETTINGS_GROUP_WEBHOOK    = 'ocws_wolt_webhook_settings';

	const DEFAULT_SANDBOX_URL       = 'https://daas-public-api.development.dev.woltapi.com';
	const DEFAULT_PRODUCTION_URL    = 'https://daas-public-api.wolt.com';
	const DEFAULT_METHOD_ID_PREFIX  = 'oc_woo_advanced_shipping_method';

	/**
	 * Register every option with its sanitiser. Options are split across two
	 * settings groups so each form on the admin page can save independently:
	 *
	 * - SETTINGS_GROUP         → general settings tab (12 fields)
	 * - SETTINGS_GROUP_WEBHOOK → webhook tab (secret only)
	 *
	 * WEBHOOK_ID is not registered in any group: it is written exclusively
	 * via our AJAX handlers (register / unregister webhook), and registering
	 * it in any group would cause options.php to nuke it on every save of
	 * the OTHER form.
	 */
	public static function register_options() {
		$main_schema = array(
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
			self::OPTION_CURRENCY         => array( 'sanitize' => array( __CLASS__, 'sanitize_currency' ),     'default' => 'ILS' ),
			self::OPTION_METHOD_ID_PREFIX => array( 'sanitize' => 'sanitize_text_field',                       'default' => self::DEFAULT_METHOD_ID_PREFIX ),
			self::OPTION_LANGUAGE        => array( 'sanitize' => array( __CLASS__, 'sanitize_language' ),       'default' => '' ),
			self::OPTION_AGE_CHECK_18    => array( 'sanitize' => array( __CLASS__, 'sanitize_bool' ),           'default' => '' ),
			self::OPTION_SUBSCRIBE_LOCATION => array( 'sanitize' => array( __CLASS__, 'sanitize_bool' ),        'default' => '' ),
		);
		foreach ( $main_schema as $option => $spec ) {
			register_setting(
				self::SETTINGS_GROUP,
				$option,
				array(
					'sanitize_callback' => $spec['sanitize'],
					'default'           => $spec['default'],
				)
			);
		}

		register_setting(
			self::SETTINGS_GROUP_WEBHOOK,
			self::OPTION_WEBHOOK_SECRET,
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
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

	/**
	 * ISO 639-1 — two lowercase letters, or empty (= auto).
	 */
	public static function sanitize_language( $value ) {
		$v = strtolower( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );
		return strlen( $v ) === 2 ? substr( $v, 0, 2 ) : '';
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

	/**
	 * OC Advanced Shipping: optional per-group venue ID (option ocws_group{n}_wolt_venue_id).
	 *
	 * @param int $group_id From order meta ocws_shipping_group or checkout package.
	 * @return string Effective venue id (non-empty when global or override is set).
	 */
	public static function get_effective_venue_id_for_group( $group_id ) {
		$group_id = absint( $group_id );
		if ( $group_id > 0 ) {
			$opt = 'ocws_group' . $group_id . '_wolt_venue_id';
			$v   = trim( (string) get_option( $opt, '' ) );
			if ( '' !== $v ) {
				return $v;
			}
		}
		return self::get_venue_id();
	}

	/**
	 * OC Advanced Shipping: optional per-group pickup line (option ocws_group{n}_wolt_pickup_address).
	 *
	 * @param int $group_id Shipping group id.
	 * @return string
	 */
	public static function get_effective_pickup_address_for_group( $group_id ) {
		$group_id = absint( $group_id );
		if ( $group_id > 0 ) {
			$opt = 'ocws_group' . $group_id . '_wolt_pickup_address';
			$v   = trim( (string) get_option( $opt, '' ) );
			if ( '' !== $v ) {
				return $v;
			}
		}
		return self::get_pickup_address();
	}

	/**
	 * OC Advanced Shipping: per-group flag to skip Wolt shipment-promise at checkout (ocws_group{n}_wolt_disable_price_override).
	 *
	 * @param int $group_id Shipping group id (from package destination / order meta).
	 * @return bool True when this group must keep the host plugin rate (no Wolt quote override).
	 */
	public static function is_wolt_price_override_disabled_for_group( $group_id ) {
		$group_id = absint( $group_id );
		if ( $group_id < 1 ) {
			return false;
		}
		return '1' === get_option( 'ocws_group' . $group_id . '_wolt_disable_price_override', '' );
	}

	/**
	 * Merge OC checkout context (session + POST + customer) into WC package destination.
	 * WooCommerce sometimes passes an empty destination to package_rates while OC stores
	 * city/street in session (chosen_*) or in update_order_review post_data.
	 *
	 * @param array $destination Raw $package['destination'].
	 * @return array
	 */
	public static function merge_oc_checkout_destination( $destination ) {
		if ( ! is_array( $destination ) ) {
			$destination = array();
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$s = WC()->session;
			if ( empty( $destination['city_code'] ) ) {
				$v = $s->get( 'chosen_city_code', '' );
				if ( is_string( $v ) && '' !== $v ) {
					$destination['city_code'] = wc_clean( wp_unslash( $v ) );
				}
			}
			if ( empty( $destination['city_name'] ) ) {
				$v = $s->get( 'chosen_city_name', '' );
				if ( is_string( $v ) && '' !== $v ) {
					$destination['city_name'] = wc_clean( wp_unslash( $v ) );
				}
			}
			if ( empty( $destination['city'] ) ) {
				$v = $s->get( 'chosen_shipping_city', '' );
				if ( null !== $v && '' !== $v && '0' !== $v ) {
					$destination['city'] = $v;
				}
			}
			if ( empty( $destination['street'] ) ) {
				$v = $s->get( 'chosen_street', '' );
				if ( is_string( $v ) && '' !== $v ) {
					$destination['street'] = wc_clean( wp_unslash( $v ) );
				}
			}
			if ( empty( $destination['house_num'] ) ) {
				$v = $s->get( 'chosen_house_num', '' );
				if ( is_string( $v ) && '' !== $v ) {
					$destination['house_num'] = wc_clean( wp_unslash( $v ) );
				}
			}
			if ( empty( $destination['address_coords'] ) || ! is_array( $destination['address_coords'] ) ) {
				$raw = $s->get( 'chosen_address_coords', '' );
				if ( is_string( $raw ) && '' !== $raw ) {
					$parsed = self::parse_oc_address_coords_string( $raw );
					if ( null !== $parsed ) {
						$destination['address_coords'] = $parsed;
					}
				}
			}
		}

		$post_data = array();
		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			parse_str( wp_unslash( $_POST['post_data'] ), $post_data );
		} elseif ( ! empty( $_POST ) && is_array( $_POST ) ) {
			$post_data = wp_unslash( $_POST );
		}

		$ship_diff = ! empty( $post_data['ship_to_different_address'] );

		if ( empty( $destination['city_code'] ) && ! empty( $post_data['billing_city_code'] ) && ! $ship_diff ) {
			$destination['city_code'] = wc_clean( (string) $post_data['billing_city_code'] );
		}
		if ( $ship_diff && empty( $destination['city_code'] ) && ! empty( $post_data['shipping_city_code'] ) ) {
			$destination['city_code'] = wc_clean( (string) $post_data['shipping_city_code'] );
		}
		if ( ! $ship_diff ) {
			if ( empty( $destination['city_name'] ) && ! empty( $post_data['billing_city_name'] ) ) {
				$destination['city_name'] = wc_clean( (string) $post_data['billing_city_name'] );
			}
			if ( empty( $destination['city'] ) && ! empty( $post_data['billing_city'] ) ) {
				$destination['city'] = wc_clean( (string) $post_data['billing_city'] );
			}
			if ( empty( $destination['street'] ) && ! empty( $post_data['billing_street'] ) ) {
				$destination['street'] = wc_clean( (string) $post_data['billing_street'] );
			}
			if ( empty( $destination['house_num'] ) && ! empty( $post_data['billing_house_num'] ) ) {
				$destination['house_num'] = wc_clean( (string) $post_data['billing_house_num'] );
			}
			if ( ( empty( $destination['address_coords'] ) || ! is_array( $destination['address_coords'] ) ) && ! empty( $post_data['billing_address_coords'] ) ) {
				$parsed = self::parse_oc_address_coords_string( (string) $post_data['billing_address_coords'] );
				if ( null !== $parsed ) {
					$destination['address_coords'] = $parsed;
				}
			}
			if ( empty( $destination['postcode'] ) && ! empty( $post_data['billing_postcode'] ) ) {
				$destination['postcode'] = wc_clean( (string) $post_data['billing_postcode'] );
			}
		} else {
			if ( empty( $destination['city_name'] ) && ! empty( $post_data['shipping_city_name'] ) ) {
				$destination['city_name'] = wc_clean( (string) $post_data['shipping_city_name'] );
			}
			if ( empty( $destination['city'] ) && ! empty( $post_data['shipping_city'] ) ) {
				$destination['city'] = wc_clean( (string) $post_data['shipping_city'] );
			}
			if ( empty( $destination['street'] ) && ! empty( $post_data['shipping_street'] ) ) {
				$destination['street'] = wc_clean( (string) $post_data['shipping_street'] );
			}
			if ( empty( $destination['house_num'] ) && ! empty( $post_data['shipping_house_num'] ) ) {
				$destination['house_num'] = wc_clean( (string) $post_data['shipping_house_num'] );
			}
			if ( empty( $destination['postcode'] ) && ! empty( $post_data['shipping_postcode'] ) ) {
				$destination['postcode'] = wc_clean( (string) $post_data['shipping_postcode'] );
			}
		}

		/*
		 * WC_Checkout::get_value() — נתוני צ'קאאוט שכבר נטענו ל־checkout מהסשן/פוסט.
		 * לפעמים $package['destination'] עדיין ברירת־מחדל ריקה בעוד שהטופס כבר מולא.
		 */
		if ( function_exists( 'WC' ) && WC()->checkout() instanceof \WC_Checkout ) {
			$chk             = WC()->checkout();
			$use_shipping_addr = $ship_diff || (bool) $chk->get_value( 'ship_to_different_address' );
			if ( ! $use_shipping_addr ) {
				if ( empty( $destination['city_code'] ) ) {
					$v = $chk->get_value( 'billing_city_code' );
					if ( $v ) {
						$destination['city_code'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['city_name'] ) ) {
					$v = $chk->get_value( 'billing_city_name' );
					if ( $v ) {
						$destination['city_name'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['city'] ) ) {
					$v = $chk->get_value( 'billing_city' );
					if ( $v ) {
						$destination['city'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['street'] ) ) {
					$v = $chk->get_value( 'billing_street' );
					if ( $v ) {
						$destination['street'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['house_num'] ) ) {
					$v = $chk->get_value( 'billing_house_num' );
					if ( $v ) {
						$destination['house_num'] = wc_clean( (string) $v );
					}
				}
				if ( ( empty( $destination['address_coords'] ) || ! is_array( $destination['address_coords'] ) ) ) {
					$v = $chk->get_value( 'billing_address_coords' );
					if ( $v ) {
						$parsed = self::parse_oc_address_coords_string( (string) $v );
						if ( null !== $parsed ) {
							$destination['address_coords'] = $parsed;
						}
					}
				}
			} else {
				if ( empty( $destination['city_code'] ) ) {
					$v = $chk->get_value( 'shipping_city_code' );
					if ( $v ) {
						$destination['city_code'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['city_name'] ) ) {
					$v = $chk->get_value( 'shipping_city_name' );
					if ( $v ) {
						$destination['city_name'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['city'] ) ) {
					$v = $chk->get_value( 'shipping_city' );
					if ( $v ) {
						$destination['city'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['street'] ) ) {
					$v = $chk->get_value( 'shipping_street' );
					if ( $v ) {
						$destination['street'] = wc_clean( (string) $v );
					}
				}
				if ( empty( $destination['house_num'] ) ) {
					$v = $chk->get_value( 'shipping_house_num' );
					if ( $v ) {
						$destination['house_num'] = wc_clean( (string) $v );
					}
				}
			}
		}

		if ( function_exists( 'WC' ) && WC()->customer ) {
			$c = WC()->customer;
			if ( empty( $destination['city'] ) ) {
				$ship_city = $c->get_shipping_city();
				$bill_city = $c->get_billing_city();
				if ( $ship_city ) {
					$destination['city'] = $ship_city;
				} elseif ( $bill_city ) {
					$destination['city'] = $bill_city;
				}
			}
		}

		if ( empty( $destination['city'] ) && ! empty( $destination['city_name'] ) ) {
			$destination['city'] = $destination['city_name'];
		}

		return $destination;
	}

	/**
	 * Parse OC-style coordinate string "(lat, lng)" or "lat, lng" into WC destination shape.
	 *
	 * @param string $raw Raw coords from session or POST.
	 * @return array{lat: float, lng: float}|null
	 */
	protected static function parse_oc_address_coords_string( $raw ) {
		if ( '' === (string) $raw ) {
			return null;
		}
		$coords = wc_clean( wp_unslash( (string) $raw ) );
		$coords = str_replace( array( '(', ')', ' ' ), '', $coords );
		$coords = explode( ',', $coords, 2 );
		if ( isset( $coords[0], $coords[1] ) && is_numeric( $coords[0] ) && is_numeric( $coords[1] ) ) {
			return array(
				'lat' => (float) $coords[0],
				'lng' => (float) $coords[1],
			);
		}
		return null;
	}

	/**
	 * Whether OC Advanced Shipping DB tables exist (install/activation completed).
	 * Without `oc_woo_shipping_locations`, `ocws_get_group_id_by_city()` cannot resolve a group.
	 *
	 * @return bool
	 */
	public static function is_oc_shipping_locations_table_present() {
		global $wpdb;
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$table  = $wpdb->prefix . 'oc_woo_shipping_locations';
		$cached = ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
		return $cached;
	}

	/**
	 * Resolve group id when `oc_woo_shipping_locations.gm_place_id` stores the Google Place ID (e.g. ChIJ...).
	 *
	 * Polygon rows often use an internal hash as `location_code`; matching only works by coords unless `gm_place_id` is filled.
	 *
	 * @param string $place_id Place id from checkout (city_code / city may carry it).
	 * @return int 0 when unknown.
	 */
	protected static function get_oc_group_id_by_gm_place_id( $place_id ) {
		$place_id = is_string( $place_id ) ? trim( $place_id ) : '';
		if ( '' === $place_id || ! self::is_oc_shipping_locations_table_present() ) {
			return 0;
		}
		global $wpdb;
		$gid = $wpdb->get_var( $wpdb->prepare(
			"SELECT group_id FROM {$wpdb->prefix}oc_woo_shipping_locations WHERE gm_place_id = %s LIMIT 1",
			$place_id
		) );
		return $gid ? (int) $gid : 0;
	}

	/**
	 * Resolve OC shipping group id from a WC package destination (checkout).
	 *
	 * @param array $destination Package destination.
	 * @return int 0 when unknown.
	 */
	public static function resolve_group_id_from_destination( $destination ) {
		if ( ! is_array( $destination ) ) {
			return 0;
		}
		if ( ! empty( $destination['ocws_shipping_group'] ) ) {
			return absint( $destination['ocws_shipping_group'] );
		}

		$table_ok = self::is_oc_shipping_locations_table_present();

		// Polygon zones: point-in-polygon → internal location_code (hash) → group (not resolvable by Place ID alone).
		if ( $table_ok && class_exists( 'OC_Woo_Shipping_Polygon' ) && is_callable( array( 'OC_Woo_Shipping_Polygon', 'find_matching_polygon' ) ) && function_exists( 'ocws_get_group_id_by_city' ) ) {
			$lat = null;
			$lng = null;
			if ( ! empty( $destination['address_coords'] ) && is_array( $destination['address_coords'] ) ) {
				if ( isset( $destination['address_coords']['lat'], $destination['address_coords']['lng'] )
					&& '' !== (string) $destination['address_coords']['lat']
					&& '' !== (string) $destination['address_coords']['lng'] ) {
					$lat = (float) $destination['address_coords']['lat'];
					$lng = (float) $destination['address_coords']['lng'];
				}
			}
			if ( null !== $lat && null !== $lng ) {
				$poly_code = OC_Woo_Shipping_Polygon::find_matching_polygon( $lat, $lng );
				if ( $poly_code ) {
					$g = ocws_get_group_id_by_city( $poly_code );
					if ( $g ) {
						return (int) $g;
					}
				}
			}
		}

		if ( $table_ok ) {
			$place_candidate = '';
			if ( ! empty( $destination['city_code'] ) && is_string( $destination['city_code'] ) ) {
				$c = trim( $destination['city_code'] );
				if ( 0 === strpos( $c, 'ChIJ' ) ) {
					$place_candidate = $c;
				}
			}
			if ( '' === $place_candidate && ! empty( $destination['city'] ) && is_string( $destination['city'] ) ) {
				$c = trim( (string) $destination['city'] );
				if ( 0 === strpos( $c, 'ChIJ' ) ) {
					$place_candidate = $c;
				}
			}
			if ( '' !== $place_candidate ) {
				$g = self::get_oc_group_id_by_gm_place_id( $place_candidate );
				if ( $g ) {
					return $g;
				}
			}
		}

		if ( ! function_exists( 'ocws_get_group_id_by_city' ) ) {
			return 0;
		}
		$can_lookup_location = $table_ok;
		if ( ! empty( $destination['city_code'] ) && $can_lookup_location ) {
			$code = $destination['city_code'];
			$g    = ocws_get_group_id_by_city( $code );

			if ( ! $g && class_exists( 'OC_Woo_Shipping_Polygon' ) && is_callable( array( 'OC_Woo_Shipping_Polygon', 'find_matching_gm_city' ) ) ) {
				$mapped = OC_Woo_Shipping_Polygon::find_matching_gm_city( $code );
				if ( $mapped ) {
					$g = ocws_get_group_id_by_city( $mapped );
				}
			}
			if ( $g ) {
				return (int) $g;
			}
		}
		if ( ! empty( $destination['city'] ) && $can_lookup_location ) {
			$g = ocws_get_group_id_by_city( $destination['city'] );
			if ( $g ) {
				return (int) $g;
			}
		}
		return 0;
	}

	/**
	 * Resolve OC shipping group id from a completed order.
	 *
	 * @param \WC_Order $order Order.
	 * @return int 0 when unknown.
	 */
	public static function resolve_group_id_from_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return 0;
		}
		$gid = absint( $order->get_meta( 'ocws_shipping_group' ) );
		if ( $gid ) {
			return $gid;
		}
		if ( ! self::is_oc_shipping_locations_table_present() ) {
			return 0;
		}
		if ( function_exists( 'ocws_get_group_id_by_city' ) && class_exists( 'OC_Woo_Shipping_Polygon' ) && is_callable( array( 'OC_Woo_Shipping_Polygon', 'find_matching_polygon' ) ) ) {
			$raw_coords = (string) $order->get_meta( '_billing_address_coords' );
			if ( '' !== $raw_coords ) {
				$parsed = self::parse_oc_address_coords_string( $raw_coords );
				if ( $parsed && isset( $parsed['lat'], $parsed['lng'] ) ) {
					$poly_code = OC_Woo_Shipping_Polygon::find_matching_polygon( (float) $parsed['lat'], (float) $parsed['lng'] );
					if ( $poly_code ) {
						$g = ocws_get_group_id_by_city( $poly_code );
						if ( $g ) {
							return (int) $g;
						}
					}
				}
			}
		}
		if ( ! function_exists( 'ocws_get_group_id_by_city' ) ) {
			return 0;
		}
		$code = $order->get_meta( '_shipping_city_code' );
		if ( ! $code ) {
			$code = $order->get_meta( '_billing_city_code' );
		}
		if ( ! $code ) {
			$city = $order->get_shipping_city();
			if ( $city && ( is_numeric( $city ) || ( function_exists( 'ocws_is_hash' ) && ocws_is_hash( $city ) ) ) ) {
				$code = $city;
			} else {
				$city = $order->get_billing_city();
				if ( $city && ( is_numeric( $city ) || ( function_exists( 'ocws_is_hash' ) && ocws_is_hash( $city ) ) ) ) {
					$code = $city;
				}
			}
		}
		if ( ! $code ) {
			return 0;
		}
		$code_str = trim( (string) $code );
		if ( '' !== $code_str && 0 === strpos( $code_str, 'ChIJ' ) ) {
			$g = self::get_oc_group_id_by_gm_place_id( $code_str );
			if ( $g ) {
				return $g;
			}
		}
		$g = ocws_get_group_id_by_city( $code );
		if ( ! $g && class_exists( 'OC_Woo_Shipping_Polygon' ) && is_callable( array( 'OC_Woo_Shipping_Polygon', 'find_matching_gm_city' ) ) ) {
			$mapped = OC_Woo_Shipping_Polygon::find_matching_gm_city( $code );
			if ( $mapped ) {
				$g = ocws_get_group_id_by_city( $mapped );
			}
		}
		return $g ? (int) $g : 0;
	}

	public static function get_merchant_id() {
		return trim( (string) get_option( self::OPTION_MERCHANT_ID, '' ) );
	}

	public static function get_webhook_secret() {
		return (string) get_option( self::OPTION_WEBHOOK_SECRET, '' );
	}

	public static function get_webhook_id() {
		return trim( (string) get_option( self::OPTION_WEBHOOK_ID, '' ) );
	}

	public static function set_webhook_id( $id ) {
		update_option( self::OPTION_WEBHOOK_ID, sanitize_text_field( (string) $id ) );
	}

	public static function clear_webhook_id() {
		delete_option( self::OPTION_WEBHOOK_ID );
	}

	/**
	 * Wolt tracking language (ISO 639-1). Empty option → auto-detect from the
	 * site locale; falls back to "en" when no two-letter prefix is available.
	 */
	public static function get_language() {
		$opt = trim( (string) get_option( self::OPTION_LANGUAGE, '' ) );
		if ( '' !== $opt && 2 === strlen( $opt ) ) {
			return strtolower( $opt );
		}
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$two    = strtolower( substr( (string) $locale, 0, 2 ) );
		return '' === $two ? 'en' : $two;
	}

	public static function is_age_check_18_enabled() {
		return '1' === (string) get_option( self::OPTION_AGE_CHECK_18, '' );
	}

	public static function is_location_subscription_enabled() {
		return '1' === (string) get_option( self::OPTION_SUBSCRIBE_LOCATION, '' );
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
