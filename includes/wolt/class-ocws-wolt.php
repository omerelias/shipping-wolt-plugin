<?php
/**
 * Wolt Drive Integration loader: load classes and init hooks.
 *
 * @package Oc_Woo_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt
 */
class OCWS_Wolt {

	/**
	 * Load Wolt integration.
	 */
	public static function load() {
		$dir = dirname( __FILE__ );
		require_once $dir . '/class-ocws-wolt-api.php';
		require_once $dir . '/class-ocws-wolt-settings.php';
		require_once $dir . '/class-ocws-wolt-price-override.php';
		require_once $dir . '/class-ocws-wolt-delivery-trigger.php';
		require_once $dir . '/class-ocws-wolt-order-meta-box.php';
		require_once $dir . '/class-ocws-wolt-webhook.php';
		require_once $dir . '/class-ocws-wolt-simulator.php';

		add_action( 'init', array( __CLASS__, 'register_options' ) );
		add_action( 'init', array( __CLASS__, 'init_components' ), 5 );
	}

	/**
	 * Register settings options (early so they save with options.php).
	 */
	public static function register_options() {
		OCWS_Wolt_Settings::register_options();
	}

	/**
	 * Init price override, delivery trigger, meta box, webhook, simulator.
	 */
	public static function init_components() {
		OCWS_Wolt_Price_Override::init();
		OCWS_Wolt_Delivery_Trigger::init();
		OCWS_Wolt_Order_Meta_Box::init();
		OCWS_Wolt_Webhook::init();
		OCWS_Wolt_Simulator::init();
	}
}
