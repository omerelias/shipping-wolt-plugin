<?php
/**
 * Plugin Name:       OC Wolt Drive
 * Plugin URI:        https://github.com/omerelias/shipping-wolt-plugin
 * Description:       Wolt Drive courier integration for the OC Advanced Shipping plugin. Adds live pricing at checkout, automatic + manual delivery dispatch to Wolt, JWT-signed status webhooks, and an admin console.
 * Version:           1.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Omer Elias
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       oc-wolt-drive
 * Domain Path:       /languages
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/* ─── Constants ────────────────────────────────────────────────────────── */

define( 'OCWS_WOLT_VERSION', '1.3.0' );
define( 'OCWS_WOLT_FILE', __FILE__ );
define( 'OCWS_WOLT_PATH', plugin_dir_path( __FILE__ ) );
define( 'OCWS_WOLT_URL', plugin_dir_url( __FILE__ ) );
define( 'OCWS_WOLT_BASENAME', plugin_basename( __FILE__ ) );

/* ─── Bootstrap ────────────────────────────────────────────────────────── */

/**
 * Show an admin notice and abort the plugin when a hard requirement is missing.
 *
 * @param string $message HTML-safe message.
 */
function ocws_wolt_requirement_notice( $message ) {
	add_action(
		'admin_notices',
		function () use ( $message ) {
			echo '<div class="notice notice-error"><p><strong>OC Wolt Drive:</strong> ' . wp_kses_post( $message ) . '</p></div>';
		}
	);
}

/**
 * Verify environment and load classes.
 */
function ocws_wolt_bootstrap() {
	load_plugin_textdomain( 'oc-wolt-drive', false, dirname( OCWS_WOLT_BASENAME ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		ocws_wolt_requirement_notice(
			__( 'WooCommerce must be installed and active.', 'oc-wolt-drive' )
		);
		return;
	}

	// Soft check: warn if no recognised OC shipping plugin is active. Wolt still loads.
	if ( ! ocws_wolt_host_shipping_active() && is_admin() ) {
		ocws_wolt_requirement_notice(
			__( 'The OC Advanced Shipping plugin was not detected. Wolt features that depend on its shipping method (price override, slot scheduling, address fields) will be inactive until it is enabled.', 'oc-wolt-drive' )
		);
	}

	require_once OCWS_WOLT_PATH . 'includes/class-ocws-wolt.php';
	require_once OCWS_WOLT_PATH . 'includes/class-ocws-wolt-admin.php';

	OCWS_Wolt::load();
	OCWS_Wolt_Admin::init();
}
add_action( 'plugins_loaded', 'ocws_wolt_bootstrap', 20 );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 *
 * This plugin reads/writes order meta exclusively through $order->get_meta()
 * / update_meta_data() / save() (no get_post_meta on order IDs), and uses
 * wc_get_orders() for listing — both work transparently against the new
 * wc_orders table.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				OCWS_WOLT_FILE,
				true
			);
		}
	}
);

/**
 * Build the admin URL for editing a WooCommerce order, honouring HPOS.
 *
 * @param int $order_id Order ID.
 * @return string URL or empty string when no id given.
 */
function ocws_wolt_order_edit_url( $order_id ) {
	$order_id = absint( $order_id );
	if ( ! $order_id ) {
		return '';
	}
	if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
		return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
	}
	return get_edit_post_link( $order_id, '' );
}

/**
 * Detect whether the host OC Advanced Shipping plugin is active.
 * Used as a soft check — Wolt does not refuse to load without it, but some
 * features are dormant.
 *
 * @return bool
 */
function ocws_wolt_host_shipping_active() {
	// Class registered by the OC Advanced Shipping plugin's main bootstrap.
	if ( class_exists( 'OC_Woo_Shipping' ) ) {
		return true;
	}
	// Fallback: look for the actual shipping method ID inside WC.
	if ( did_action( 'woocommerce_shipping_init' ) || class_exists( 'WC_Shipping_Method' ) ) {
		$methods = WC()->shipping() ? WC()->shipping()->get_shipping_methods() : array();
		foreach ( (array) $methods as $id => $method ) {
			if ( strpos( (string) $id, 'oc_woo_advanced_shipping_method' ) === 0 ) {
				return true;
			}
		}
	}
	return false;
}

/* ─── Activation / deactivation ───────────────────────────────────────── */

register_activation_hook( __FILE__, 'ocws_wolt_activate' );
register_deactivation_hook( __FILE__, 'ocws_wolt_deactivate' );

/**
 * Activation hook: seed sane defaults without overwriting existing values.
 */
function ocws_wolt_activate() {
	$defaults = array(
		'ocws_wolt_api_url'                 => 'https://daas-public-api.development.dev.woltapi.com',
		'ocws_wolt_trigger_status'          => 'wc-processing',
		'ocws_wolt_dispatch_offset_minutes' => '30',
		'ocws_wolt_markup_type'             => 'fixed',
		'ocws_wolt_markup_value'            => '0',
		'ocws_wolt_currency'                => 'ILS',
		'ocws_wolt_method_id_prefix'        => 'oc_woo_advanced_shipping_method',
	);
	foreach ( $defaults as $option => $value ) {
		if ( false === get_option( $option, false ) ) {
			add_option( $option, $value );
		}
	}
}

/**
 * Deactivation hook: clear scheduled events (none currently), keep options
 * so that re-activation is non-destructive.
 */
function ocws_wolt_deactivate() {
	// Intentionally empty — options stay so re-enabling is seamless.
}
