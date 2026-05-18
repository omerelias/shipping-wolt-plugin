<?php
/**
 * Uninstall handler — runs only when an admin clicks "Delete" on Plugins.
 * Removes every option this plugin owns. Order meta added by the plugin
 * (_ocws_wolt_*) is left alone so that historical orders keep their audit trail.
 *
 * @package OC_Wolt_Drive
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$options = array(
	'ocws_wolt_enabled',
	'ocws_wolt_trigger_status',
	'ocws_wolt_dispatch_offset_minutes',
	'ocws_wolt_markup_type',
	'ocws_wolt_markup_value',
	'ocws_wolt_api_url',
	'ocws_wolt_api_key',
	'ocws_wolt_pickup_address',
	'ocws_wolt_venue_id',
	'ocws_wolt_merchant_id',
	'ocws_wolt_webhook_secret',
	'ocws_wolt_webhook_id',
	'ocws_wolt_currency',
	'ocws_wolt_method_id_prefix',
	'ocws_wolt_language',
	'ocws_wolt_min_preparation_time',
	'ocws_wolt_age_check_18',
	'ocws_wolt_subscribe_location',
);

foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option ); // belt + braces for multisite.
}
