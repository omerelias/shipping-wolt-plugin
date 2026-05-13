<?php
/**
 * Wolt Drive integration: front-end + REST + order hooks.
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt
 *
 * Loads all front-facing Wolt components (price override at checkout,
 * delivery dispatch trigger, webhook endpoint, order meta box).
 * The admin settings UI is bootstrapped separately by OCWS_Wolt_Admin.
 */
class OCWS_Wolt {

	/**
	 * Wire everything up. Called once from the plugin bootstrap.
	 */
	public static function load() {
		$dir = OCWS_WOLT_PATH . 'includes';

		require_once $dir . '/class-ocws-wolt-settings.php';
		require_once $dir . '/class-ocws-wolt-api.php';
		require_once $dir . '/class-ocws-wolt-price-override.php';
		require_once $dir . '/class-ocws-wolt-delivery-trigger.php';
		require_once $dir . '/class-ocws-wolt-order-meta-box.php';
		require_once $dir . '/class-ocws-wolt-webhook.php';

		add_action( 'init', array( __CLASS__, 'register_options' ) );
		add_action( 'init', array( __CLASS__, 'init_components' ), 5 );
	}

	/**
	 * Register option defaults early so options.php saves correctly.
	 */
	public static function register_options() {
		OCWS_Wolt_Settings::register_options();
	}

	/**
	 * Boot runtime components after options are registered.
	 */
	public static function init_components() {
		OCWS_Wolt_Price_Override::init();
		OCWS_Wolt_Delivery_Trigger::init();
		OCWS_Wolt_Order_Meta_Box::init();
		OCWS_Wolt_Webhook::init();
	}
}
