<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://originalconcepts.co.il/
 * @since             1.0.0
 * @package           Oc_Woo_Shipping
 *
 * @wordpress-plugin
 * Plugin Name:       Original Concepts WooCommerce Advanced Shipping
 * Plugin URI:        https://originalconcepts.co.il/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           2.1.2
 * Author:            Mili Shub
 * Author URI:        https://originalconcepts.co.il/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ocws
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

$autoloader = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoloader ) ) {
	require $autoloader;
} else {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log(  // phpcs:ignore
			sprintf(
			/* translators: 1: composer command. 2: plugin directory */
				esc_html__( 'Your installation of the OC Advanced Shipping plugin is incomplete. Please run %1$s within the %2$s directory.', 'ocws' ),
				'`composer install`',
				'`' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '`'
			)
		);
	}
	/**
	 * Outputs an admin notice if composer install has not been ran.
	 */
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
					/* translators: 1: composer command. 2: plugin directory */
						esc_html__( 'Your installation of the OC Advanced Shipping plugin is incomplete. Please run %1$s within the %2$s directory.', 'ocws' ),
						'<code>composer install</code>',
						'<code>' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '</code>'
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'OC_WOO_SHIPPING_VERSION', '2.1.1' );

define('OCWS_PATH_FILE', __FILE__);
define('OCWS_PATH', dirname(OCWS_PATH_FILE));

if ( ! defined( 'OCWS_ASSESTS_URL' ) ) {

	define('OCWS_ASSESTS_URL', plugin_dir_url(__FILE__).'public/');

}

if ( ! defined( 'OCWS_ADMIN_ASSESTS_URL' ) ) {

	define('OCWS_ADMIN_ASSESTS_URL', plugin_dir_url(__FILE__).'admin/');

}

define( 'OC_WOO_USE_COMPANIES', false );
define( 'OC_WOO_USE_OPENSEA_STYLE_EXPORT', false );

/* Define max file size for simple html DOM library */
defined( 'MAX_FILE_SIZE' ) || define( 'MAX_FILE_SIZE', 1000000 );

if (!function_exists('is_plugin_active')) {
	include_once(ABSPATH . '/wp-admin/includes/plugin.php');
}

/**
 * Check for the existence of WooCommerce and any other requirements
 */
if (!function_exists('oc_woo_check_requirements')) {
function oc_woo_check_requirements() {
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		return true;
	} else {
		add_action( 'admin_notices', 'oc_woo_missing_wc_notice' );
		return false;
	}
}}

/**
 * Display a message advising WooCommerce is required
 */
if (!function_exists('oc_woo_missing_wc_notice')) {
function oc_woo_missing_wc_notice() {
	$class = 'notice notice-error';
	$message = __( 'WooCommerce Advanced Shipping requires WooCommerce to be installed and active.', 'ocws' );

	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-oc-woo-shipping-activator.php
 */
function activate_oc_woo_shipping() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-oc-woo-shipping-activator.php';
	Oc_Woo_Shipping_Activator::activate();

	require_once plugin_dir_path( __FILE__ ) . 'includes/local-pickup/class-ocws-lp-activator.php';
	OCWS_LP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-oc-woo-shipping-deactivator.php
 */
function deactivate_oc_woo_shipping() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-oc-woo-shipping-deactivator.php';
	Oc_Woo_Shipping_Deactivator::deactivate();

	require_once plugin_dir_path( __FILE__ ) . 'includes/local-pickup/class-ocws-lp-activator.php';
	OCWS_LP_Activator::deactivate();
}

register_activation_hook( __FILE__, 'activate_oc_woo_shipping' );
register_deactivation_hook( __FILE__, 'deactivate_oc_woo_shipping' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-oc-woo-shipping.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_oc_woo_shipping() {

	if (oc_woo_check_requirements()) {
		$plugin = OCWS(); //new Oc_Woo_Shipping();
		$plugin->run();
	}
}
//error_log('running plugin');
run_oc_woo_shipping();

/**
 * Returns the main instance of OCWS.
 *
 */
function OCWS() {
	return OC_Woo_Shipping::instance();
}

// Global for backwards compatibility.
$GLOBALS['OCWS'] = OCWS();
