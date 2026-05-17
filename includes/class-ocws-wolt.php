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

if ( ! class_exists( 'OCWS_Wolt' ) ) {

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
		require_once $dir . '/class-ocws-wolt-frontend.php';

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
		OCWS_Wolt_Frontend::init();
	}
}

} else {
	// Diagnostic: another plugin/file already declared OCWS_Wolt. Log where so we can identify the duplicate source.
	if ( class_exists( 'ReflectionClass' ) ) {
		try {
			$ocws_wolt_existing = new ReflectionClass( 'OCWS_Wolt' );
			error_log( sprintf(
				'[OC Wolt Drive] Duplicate class OCWS_Wolt — first declared in %s (line %d). Skipping redeclaration in %s. Likely cause: a legacy "Wolt module" still lives inside a host shipping plugin folder, or two bootstrap files in this folder are both active.',
				$ocws_wolt_existing->getFileName(),
				$ocws_wolt_existing->getStartLine(),
				__FILE__
			) );
		} catch ( ReflectionException $e ) {
			error_log( '[OC Wolt Drive] Duplicate class OCWS_Wolt detected but Reflection failed: ' . $e->getMessage() );
		}
	}
}
