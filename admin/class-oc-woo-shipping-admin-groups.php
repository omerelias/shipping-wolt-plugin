<?php
/**
 * Debug/Status page
 *
 * @package WooCommerce/Admin/System Status
 * @version 2.2.0
 */

use Automattic\Jetpack\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Admin_Status Class.
 */
class OC_Woo_Shipping_Admin_Groups {

	/**
	 * Handles output of the shipping groups page in admin.
	 * @param int $group_id
	 */
	public static function output($group_id = null) {

		global $hide_save_button;

		if ( $group_id ) {
			$hide_save_button = true;
			self::output_group( $group_id );
		}
		else if ( isset( $_REQUEST['group_id'] ) ) {
			$hide_save_button = true;
			self::output_group( wc_clean( wp_unslash( $_REQUEST['group_id'] ) ) );
		} else {
			$hide_save_button = true;
			self::groups_screen();
		}
	}

	public static function output_import($group_id = null) {

		if ( $group_id ) {

			include_once OCWS_PATH . '/includes/importers/class-ocws-cities-csv-importer.php';
			include_once OCWS_PATH . '/includes/importers/class-ocws-cities-csv-importer-controller.php';

			$importer = new OCWS_Cities_CSV_Importer_Controller($group_id);
			$importer->dispatch();
		}
	}

	/**
	 * Show groups
	 */
	protected static function groups_screen() {

		wp_localize_script(
			'oc-woo-shipping-groups',
			'shippingGroupsLocalizeScript',
			array(
				'groups'                   => OC_Woo_Shipping_Groups::get_groups(),
				'oc_woo_shipping_groups_nonce' => wp_create_nonce( 'oc_woo_shipping_groups_nonce' ),
				'strings'                 => array(
					'unload_confirmation_msg'     => __( 'Your changed data will be lost if you leave this page without saving.', 'ocws' ),
					'delete_confirmation_msg'     => __( 'Are you sure you want to delete this group? This action cannot be undone.', 'ocws' ),
					'save_failed'                 => __( 'Your changes were not saved. Please retry.', 'ocws' ),
					'no_shipping_locations_offered' => __( 'No shipping locations offered to this group.', 'ocws' ),
				),
			)
		);
		wp_enqueue_script( 'oc-woo-shipping-groups' );

		include_once dirname( __FILE__ ) . '/partials/html-admin-page-shipping-groups.php';
	}

	/**
	 * Handles output of group.
	 */
	protected static function output_group($group_id) {

		if ( 'new' === $group_id ) {
			$group = new OC_Woo_Shipping_Group();
		} else {
			$group = OC_Woo_Shipping_Groups::get_group( absint( $group_id ) );
			if ( ! $group ) {
				wp_die( esc_html__( 'Group does not exist!', 'ocws' ) );
			}
		}

		//var_dump($group);

		//$allowed_cities   = OCWS()->locations->get_cities();

		// Prepare locations.
		$locations = array();

		foreach ( $group->get_group_locations() as $location ) {
			if ( 'city' === $location->type ) {
				$locations[] = $location->type . ':' . $location->code;
			}
		}

		$use_simple_cities = !ocws_use_google_cities_and_polygons();
		$use_polygons = ocws_use_google_cities_and_polygons();
		$use_google_cities = ocws_use_google_cities();
		wp_localize_script(
			'oc-woo-shipping-group-edit',
			'shippingGroupEditLocalizeScript',
			array(
				'locations'                => $group->get_group_locations_response($use_simple_cities, $use_polygons, $use_google_cities),
				'group_name'               => $group->get_group_name(),
				'group_id'                 => $group->get_id(),
				'oc_woo_shipping_groups_nonce' => wp_create_nonce( 'oc_woo_shipping_groups_nonce' ),
				'strings'                 => array(
					'unload_confirmation_msg' => __( 'Your changed data will be lost if you leave this page without saving.', 'ocws' ),
					'save_changes_prompt'     => __( 'Do you wish to save your changes first? Your changed data will be discarded if you choose to cancel.', 'ocws' ),
					'save_failed'             => __( 'Your changes were not saved. Please retry.', 'ocws' ),
					'add_method_failed'       => __( 'Shipping location could not be added. Please retry.', 'ocws' ),
					'yes'                     => __( 'Yes', 'ocws' ),
					'no'                      => __( 'No', 'ocws' ),
					'default_group_name'       => __( 'Group', 'ocws' ),
				),
				'polygon_icon_url' => OCWS_ADMIN_ASSESTS_URL . 'images/icons/polygon_icon.png',
				'googlemaps_icon_url' => OCWS_ADMIN_ASSESTS_URL . 'images/icons/googlemaps_icon.png',
				'use_polygon_feature' => ocws_use_google_cities_and_polygons(),
				'use_google_cities' => ocws_use_google_cities()
			)
		);
		wp_enqueue_script( 'oc-woo-shipping-group-edit' );

		include_once dirname( __FILE__ ) . '/partials/html-admin-page-shipping-group-edit.php';

		self::output_group_settings($group_id);
	}

	public static function output_common_settings() {

		$languages = ocws_get_languages();

		?>

		<h2>General Settings</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'ocws_common' ); ?>
			<?php do_settings_sections( 'ocws_common' ); ?>
			<table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php echo __('Dates style', 'ocws'); ?></th>
                    <td> <!-- dates_style -->
                        <select name="ocws_common_dates_style">
                            <option
                                <?php if (get_option('ocws_common_dates_style', 'slider_style') == 'slider_style'): ?>selected<?php endif ?>
                                value="slider_style">
                                <?php echo __('Slider style', 'ocws'); ?>
                            </option>
                            <option
                                <?php if (get_option('ocws_common_dates_style', 'slider_style') == 'calendar_style'): ?>selected<?php endif ?>
                                value="calendar_style">
                                <?php echo __('Calendar style', 'ocws'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
				<tr valign="top">
					<th scope="row"><?php echo __('Pickup only products in a cart message', 'ocws') ?></th>
					<td><input type="text" name="ocws_common_pickup_only_message" value="<?php echo esc_attr( get_option('ocws_common_pickup_only_message') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Enable leave at the door checkbox', 'ocws') ?></th>
					<td>
						<input type="text" name="ocws_common_enable_at_the_door_checkbox" value="<?php echo esc_attr( get_option('ocws_common_enable_at_the_door_checkbox', '') ); ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Enable add other products checkbox', 'ocws') ?></th>
					<td>
						<input type="text" name="ocws_common_enable_other_products_checkbox" value="<?php echo esc_attr( get_option('ocws_common_enable_other_products_checkbox', '') ); ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Send to other person checked by default', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_enable_send_to_other_checked_by_default"
								   class="" id="ocws_common_enable_send_to_other_checked_by_default"
								   value="1" <?php if (get_option('ocws_common_enable_send_to_other_checked_by_default', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Enable greeting field', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_enable_greeting_field"
								   class="" id="ocws_common_enable_greeting_field"
								   value="1" <?php if (get_option('ocws_common_enable_greeting_field', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide time slots on checkout', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_show_dates_only"
								   class="" id="ocws_common_show_dates_only"
								   value="1" <?php if (get_option('ocws_common_show_dates_only', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide time slot in new order email to admin', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_hide_slot_in_admin_mail"
								   class="" id="ocws_common_hide_slot_in_admin_mail"
								   value="1" <?php if (get_option('ocws_common_hide_slot_in_admin_mail', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<?php

				// Export
				$attribute_taxonomies = wc_get_attribute_taxonomies();
				$attributes_assoc = array();
				if ( $attribute_taxonomies ) {

					foreach ( $attribute_taxonomies as $tax ) {
						$attributes_assoc[$tax->attribute_name] = $tax->attribute_label;
						//var_dump($tax);
					}
				}

				?>

				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php echo __('Export summary for production', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Choose attributes to show', 'ocws') ?></th>
					<td>

						<?php
							$selected = get_option('ocws_common_export_production_attributes_to_show', false);
							if (!$selected || !is_array($selected)) {
								$selected = array();
							}

							foreach ( $attributes_assoc as $id => $attr ) {
								echo '<div><label><input type="checkbox" name="ocws_common_export_production_attributes_to_show[]" value="'.esc_attr($id).'" '.(in_array($id, $selected)? 'checked="checked"' : '').'>'.esc_html($attr).'</label></div>';
							}
						?>

					</td>
				</tr>

				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php echo __('Export product details for production', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Choose attributes to show', 'ocws') ?></th>
					<td>
						<?php
							$selected = get_option('ocws_common_export_production_details_attributes_to_show', false);
							if (!$selected || !is_array($selected)) {
								$selected = array();
							}

							foreach ( $attributes_assoc as $id => $attr ) {
								echo '<div><label><input type="checkbox" name="ocws_common_export_production_details_attributes_to_show[]" value="'.esc_attr($id).'" '.(in_array($id, $selected)? 'checked="checked"' : '').'>'.esc_html($attr).'</label></div>';
							}
						?>

					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Add pages for export', 'ocws') ?></th>
					<td>
						<?php
						$pages = get_option('ocws_common_export_production_details_pages', false);
						if (!$pages || !is_array($pages)) {
							$pages = array(__('Main', 'ocws'));
						}
						$additionalPagesCount = (count($pages) < 20 ? 20 - count($pages) : 0);


						$pageIndex = 0;
						foreach ( $pages as $id => $title ) {
							$pageIndex++;
							?>
							<div class="ocws_export_page">
								<div><?php echo esc_html(__('Export page', 'ocws') . ' ' . $pageIndex) ?></div>
								<div>
									<input placeholder="<?php echo esc_attr(__('Export page title', 'ocws')) ?>" type="text" name="ocws_common_export_production_details_pages[]" value="<?php echo esc_attr( $title ); ?>" />
								</div>
							</div>
							<?php
						}
						?>

						<?php

						for ($i = 0; $i < $additionalPagesCount; $i++) {
							$pageIndex++;
							?>
							<div style="display: none" class="ocws_export_page">
								<div><?php echo esc_html(__('Export page', 'ocws') . ' ' . $pageIndex) ?></div>
								<div>
									<input placeholder="<?php echo esc_attr(__('Export page title', 'ocws')) ?>" type="text" name="ocws_common_export_production_details_pages[]" value="" />
								</div>
							</div>
							<?php
						}
						?>

						<div class="ocws_add_export_page">
							<input type="button" id="ocws-add-export-page" class="button" value="<?php echo esc_attr(__('Add export page', 'ocws')); ?>">
						</div>

					</td>
				</tr>

				<script>
					jQuery(document).ready(function () {

						jQuery('#ocws-add-export-page').on('click', function() {
							var hidden = jQuery("div.ocws_export_page:hidden");
							if (hidden.length > 0) {
								jQuery(hidden[0]).show();
							}
							if (hidden.length == 1) {
								jQuery('div.ocws_add_export_page').hide();
							}
						});
					});

				</script>

				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php echo __('Export settings', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Choose order statuses for export', 'ocws') ?></th>
					<td>
						<?php
							$selected = get_option('ocws_common_export_order_statuses', false);
							if (!$selected || !is_array($selected)) {
								$selected = array('wc-processing');
							}
							foreach ( wc_get_order_statuses() as $id => $status ) {
								echo '<div><label><input type="checkbox" name="ocws_common_export_order_statuses[]" value="'.esc_attr($id).'" '.(in_array($id, $selected)? 'checked="checked"' : '').'>'.esc_html($status).'</label></div>';
							}
						?>

					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide summary column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_hide_summary"
								   class="" id="ocws_common_export_hide_summary"
								   value="1" <?php if (get_option('ocws_common_export_hide_summary', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide time slots column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_hide_time_slots"
								   class="" id="ocws_common_export_hide_time_slots"
								   value="1" <?php if (get_option('ocws_common_export_hide_time_slots', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide order items column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_hide_order_items"
								   class="" id="ocws_common_export_hide_order_items"
								   value="1" <?php if (get_option('ocws_common_export_hide_order_items', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide number of items column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_hide_items_number"
								   class="" id="ocws_common_export_hide_items_number"
								   value="1" <?php if (get_option('ocws_common_export_hide_items_number', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide order notes column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_hide_order_notes"
								   class="" id="ocws_common_export_hide_order_notes"
								   value="1" <?php if (get_option('ocws_common_export_hide_order_notes', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Show order completion date column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_show_order_completed_date"
								   class="" id="ocws_common_export_show_order_completed_date"
								   value="1" <?php if (get_option('ocws_common_export_show_order_completed_date', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Show B2B column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_show_b2b"
								   class="" id="ocws_common_export_show_b2b"
								   value="1" <?php if (get_option('ocws_common_export_show_b2b', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Show customer email column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_show_customer_email"
								   class="" id="ocws_common_export_show_customer_email"
								   value="1" <?php if (get_option('ocws_common_export_show_customer_email', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide shipping method column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_hide_shipping_method"
								   class="" id="ocws_common_export_hide_shipping_method"
								   value="1" <?php if (get_option('ocws_common_export_hide_shipping_method', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Hide order total column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_export_hide_order_total"
								   class="" id="ocws_common_export_hide_order_total"
								   value="1" <?php if (get_option('ocws_common_export_hide_order_total', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<?php if (defined('OC_WOO_USE_OPENSEA_STYLE_EXPORT') && OC_WOO_USE_OPENSEA_STYLE_EXPORT) { ?>

					<tr valign="top">
						<th scope="row"><?php echo __('Maaraz S Capacity', 'ocws') ?></th>
						<td><input type="text" name="ocws_common_s_maaraz_capacity" value="<?php echo esc_attr( get_option('ocws_common_s_maaraz_capacity', '3') ); ?>" /></td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo __('Maaraz M Capacity', 'ocws') ?></th>
						<td><input type="text" name="ocws_common_m_maaraz_capacity" value="<?php echo esc_attr( get_option('ocws_common_m_maaraz_capacity', '2') ); ?>" /></td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo __('Maaraz L Capacity', 'ocws') ?></th>
						<td><input type="text" name="ocws_common_l_maaraz_capacity" value="<?php echo esc_attr( get_option('ocws_common_l_maaraz_capacity', '2') ); ?>" /></td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo __('Kalkar S Capacity', 'ocws') ?></th>
						<td><input type="text" name="ocws_common_s_kalkar_capacity" value="<?php echo esc_attr( get_option('ocws_common_s_kalkar_capacity', '42') ); ?>" /></td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo __('Kalkar M Capacity', 'ocws') ?></th>
						<td><input type="text" name="ocws_common_m_kalkar_capacity" value="<?php echo esc_attr( get_option('ocws_common_m_kalkar_capacity', '34') ); ?>" /></td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo __('Kalkar L Capacity', 'ocws') ?></th>
						<td><input type="text" name="ocws_common_l_kalkar_capacity" value="<?php echo esc_attr( get_option('ocws_common_l_kalkar_capacity', '28') ); ?>" /></td>
					</tr>

				<?php } ?>

				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php echo __('Sales report', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Choose order statuses for export', 'ocws') ?></th>
					<td>
						<?php
						$selected = get_option('ocws_common_export_sales_order_statuses', false);
						if (!$selected || !is_array($selected)) {
							$selected = array('wc-completed');
						}
						foreach ( wc_get_order_statuses() as $id => $status ) {
							echo '<div><label><input type="checkbox" name="ocws_common_export_sales_order_statuses[]" value="'.esc_attr($id).'" '.(in_array($id, $selected)? 'checked="checked"' : '').'>'.esc_html($status).'</label></div>';
						}
						?>

					</td>
				</tr>

				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php echo __('Admin order list settings', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Show order completion date column', 'ocws') ?></th>
					<td>
						<label>
							<input type="checkbox" name="ocws_common_orders_show_completed_date"
								   class="" id="ocws_common_orders_show_completed_date"
								   value="1" <?php if (get_option('ocws_common_orders_show_completed_date', '') === '1') { ?> checked="checked"<?php } ?> />
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php echo __('Texts and captions', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Popup title', 'ocws') ?></th><td>

					<?php

					if (!empty($languages)) {
						foreach($languages as $language_code) {
							?>

							<div><?php echo esc_html($language_code) ?></div>
							<div><input type="text" name="ocws_common_popup_title_<?php echo $language_code ?>" value="<?php echo esc_attr( get_option('ocws_common_popup_title_'.$language_code) ); ?>" /></div>

							<?php
						}
					}
					else {
						?>

						<input type="text" name="ocws_common_popup_title" value="<?php echo esc_attr( get_option('ocws_common_popup_title') ); ?>" />

						<?php
					}

					?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Popup shipping method button text', 'ocws') ?></th>
					<td><input type="text" name="ocws_common_popup_shipping_method_button_text" value="<?php echo esc_attr( get_option('ocws_common_popup_shipping_method_button_text') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Popup choose location title', 'ocws') ?></th>
					<td><input type="text" name="ocws_common_popup_choose_location_title" value="<?php echo esc_attr( get_option('ocws_common_popup_choose_location_title') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Popup choose location sub title', 'ocws') ?></th>
					<td><input type="text" name="ocws_common_popup_choose_location_sub_title" value="<?php echo esc_attr( get_option('ocws_common_popup_choose_location_sub_title') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Popup button text', 'ocws') ?></th>
					<td><input type="text" name="ocws_common_popup_button_text" value="<?php echo esc_attr( get_option('ocws_common_popup_button_text') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Send to other person checkbox label on checkout', 'ocws') ?></th>
					<td><input type="text" name="ocws_common_checkout_send_to_other_checkbox_label" value="<?php echo esc_attr( get_option('ocws_common_checkout_send_to_other_checkbox_label') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Time slots title on checkout', 'ocws') ?></th><td>

						<?php

						if (!empty($languages)) {
							foreach($languages as $language_code) {
								?>

								<div><?php echo esc_html($language_code) ?></div>
								<div><input type="text" name="ocws_common_checkout_slots_title_<?php echo $language_code ?>" value="<?php echo esc_attr( get_option('ocws_common_checkout_slots_title_'.$language_code) ); ?>" /></div>

								<?php
							}
						}
						else {
							?>

							<input type="text" name="ocws_common_checkout_slots_title" value="<?php echo esc_attr( get_option('ocws_common_checkout_slots_title') ); ?>" />

							<?php
						}

						?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Time slots description on checkout', 'ocws') ?></th><td>

						<?php

						if (!empty($languages)) {
							foreach($languages as $language_code) {
								?>

								<div><?php echo esc_html($language_code) ?></div>
								<div><input type="text" name="ocws_common_checkout_slots_description_<?php echo $language_code ?>" value="<?php echo esc_attr( get_option('ocws_common_checkout_slots_description_'.$language_code) ); ?>" /></div>

								<?php
							}
						}
						else {
							?>

							<input type="text" name="ocws_common_checkout_slots_description" value="<?php echo esc_attr( get_option('ocws_common_checkout_slots_description') ); ?>" />

							<?php
						}

						?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Out of service area message', 'ocws') ?></th><td>

						<?php

						if (!empty($languages)) {
							foreach($languages as $language_code) {
								?>

								<div><?php echo esc_html($language_code) ?></div>
								<div><input type="text" name="ocws_common_out_of_service_area_message_<?php echo $language_code ?>" value="<?php echo esc_attr( get_option('ocws_common_out_of_service_area_message_'.$language_code) ); ?>" /></div>

								<?php
							}
						}
						else {
							?>

							<input type="text" name="ocws_common_out_of_service_area_message" value="<?php echo esc_attr( get_option('ocws_common_out_of_service_area_message') ); ?>" />

							<?php
						}

						?>
					</td>
				</tr>

				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php echo __('Google Maps API', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Google Maps API key', 'ocws') ?></th><td>

						<input type="text" name="ocws_common_google_maps_api_key" value="<?php echo esc_attr( get_option('ocws_common_google_maps_api_key') ); ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Use Google Cities', 'ocws') ?></th><td>

						<label>
							<input name="ocws_common_use_google_cities" type="checkbox" value="1" <?php if (get_option('ocws_common_use_google_cities') === '1') { ?> checked="checked"<?php } ?> >
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Use Polygon Feature', 'ocws') ?></th><td>

						<label>
							<input name="ocws_common_use_google_cities_and_polygons" type="checkbox" value="1" <?php if (get_option('ocws_common_use_google_cities_and_polygons') === '1') { ?> checked="checked"<?php } ?> >
						</label>
					</td>
				</tr>

				<!-- New settings: popup  -->
				<tr valign="top">
					<th colspan="2" scope="row"><h3><?php _e( 'Popup settings:', 'ocws') ?></h3></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'Show shipping popup?', 'ocws') ?></th><td>

						<label>
							<input name="ocws_common_use_popup" type="checkbox" value="1" <?php if (get_option('ocws_common_use_popup') === '1') { ?> checked="checked"<?php } ?> >
						</label>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'Shipping popup description', 'ocws') ?></th><td>

						<label>
							<input name="ocws_common_shipping_popup_description" type="text" value="<?php echo get_option('ocws_common_shipping_popup_description') ;?>"  />
						</label>
					</td>
				</tr>

				<!-- ocws_shipping_popup_decription -->				


			</table>

			<?php submit_button(); ?>

		</form>

		<?php


	}

	public static function output_default_group_settings() {

		?>

		<h2>Default Settings</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'ocws_default' ); ?>
			<?php do_settings_sections( 'ocws_default' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php echo __('Minimum total', 'ocws') ?></th>
					<td><input type="text" name="ocws_default_min_total" value="<?php echo esc_attr( get_option('ocws_default_min_total') ); ?>" /></td>
				</tr>

				<tr valign="top" style="display: none;">
					<th scope="row"><?php echo __('Minimum total message in case it is applicable', 'ocws') ?></th>
					<td><input type="text" name="ocws_default_min_total_message_yes" value="<?php echo esc_attr( get_option('ocws_default_min_total_message_yes') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Minimum total message in case it is not applicable', 'ocws') ?></th>
					<td><input type="text" name="ocws_default_min_total_message_no" value="<?php echo esc_attr( get_option('ocws_default_min_total_message_no') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Preorder days', 'ocws') ?></th>
					<td><input type="text" name="ocws_default_preorder_days" value="<?php echo esc_attr( get_option('ocws_default_preorder_days') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Minimum wait times (in minutes)', 'ocws') ?></th>
					<td><input type="text" name="ocws_default_min_wait_times" value="<?php echo esc_attr( get_option('ocws_default_min_wait_times') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Shipping price', 'ocws') ?></th>
					<td><input type="text" name="ocws_default_shipping_price" value="<?php echo esc_attr( get_option('ocws_default_shipping_price') ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo __('Minimum total for free shipping', 'ocws') ?></th>
					<td><input type="text" name="ocws_default_min_total_for_free_shipping" value="<?php echo esc_attr( get_option('ocws_default_min_total_for_free_shipping') ); ?>" /></td>
				</tr>

				<?php
				if ( class_exists( 'OCWS_Wolt_Settings' ) ) {
					OCWS_Wolt_Settings::render_settings_section();
				}
				?>

				<tr valign="top" style="display: none;">
					<th scope="row"><?php echo __('Latest hour to order today', 'ocws') ?></th>
					<td><input style="position: relative; z-index: 100000;" class="timepicker latest-hour" type="text" name="ocws_default_max_hour_for_today" value="<?php echo esc_attr( get_option('ocws_default_max_hour_for_today') ); ?>" /></td>
				</tr>

				<?php

				$closing_weekdays = get_option('ocws_default_closing_weekdays', '');
				if (!$closing_weekdays && $closing_weekdays != 0) {
					$closing_weekdays_arr = array();
				}
				else {
					$closing_weekdays_arr = ocws_numbers_list_to_array( $closing_weekdays );
				}
				?>

				<tr valign="top">
					<th scope="row"><?php echo __('Exclude days of week', 'ocws') ?></th>
					<td>
						<input type="hidden" id="ocws_default_closing_weekdays" name="ocws_default_closing_weekdays"
							   value="<?php echo esc_attr( $closing_weekdays ); ?>">
						<div id="closing_weekdays">
							<label>
								<input type="checkbox" id="closing_weekdays-0" data-weekday="0"
									   value="1" <?php if (in_array('0', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Sunday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-1" data-weekday="1"
									   value="1" <?php if (in_array('1', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Monday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-2" data-weekday="2"
									   value="1" <?php if (in_array('2', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Tuesday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-3" data-weekday="3"
									   value="1" <?php if (in_array('3', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Wednesday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-4" data-weekday="4"
									   value="1" <?php if (in_array('4', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Thursday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-5" data-weekday="5"
									   value="1" <?php if (in_array('5', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Friday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-6" data-weekday="6"
									   value="1" <?php if (in_array('6', $closing_weekdays_arr)) { ?> checked="checked"<?php } ?> ><?php echo __('Saturday', 'ocws') ?>
							</label>
						</div>

						<script>
							jQuery(document).ready(function () {

								jQuery('#closing_weekdays input[type="checkbox"]').on('change', function() {
									var hidden = jQuery("#ocws_default_closing_weekdays");

                                    var values = [];
                                    jQuery('#closing_weekdays input[type=checkbox]:checked').each(function (index, value) {
                                        values.push(jQuery(value).data('weekday'));
                                    });
                                    hidden.val(values.join(','))

									// var val = hidden.val().split( ',' );
									// var weekday = jQuery(this).data('weekday');
									// var index = false;
									// for (var k=0; k < val.length; k++) {
									// 	if (weekday == val[k]) {
									// 		index = k;
									// 		break;
									// 	}
									// }
									// if (this.checked) {
									// 	if (index === false) {
									// 		val.push(weekday);
									// 	}
									// }
									// else {
									// 	if (index !== false) {
									// 		val.splice(index, 1);
									// 	}
									// }
									// hidden.val(val.join(','));
								});
							});

						</script>
					</td>
				</tr>

				<?php

					$closing_dates = ocws_dates_list_to_array( get_option( 'ocws_default_closing_dates', '' ), true );
				?>

				<tr valign="top">
					<th scope="row"><?php echo __('Exclude dates', 'ocws') ?></th>
					<td>
						<div>
							<input style="position: relative; z-index: 100000;" type="text" id="ocws_default_closing_dates" name="ocws_default_closing_dates" readonly="readonly"
							   	value="<?php esc_attr( get_option('ocws_default_closing_dates') ); ?>">
						</div>

						<script>
							/* <?php //var_dump($closing_dates) ?> */
							jQuery(document).ready(function () {

								jQuery("#ocws_default_closing_dates").multiDatesPicker({

									dateFormat: 'dd/mm/yy',
									minDate: 0,
									maxPicks: 100,
									<?php echo !empty($closing_dates)? 'addDates: ' . json_encode($closing_dates) . ',' : ''; ?>
									onSelect: function() {}
								});
							});
						//# scriptURL=multidatepicker-closing-dates-default.js
						</script>
					</td>
				</tr>

				<?php

					$scheduling_type = get_option('ocws_default_delivery_scheduling_type', 'weekly');
					$weekly_no_display = $scheduling_type == 'dates'? ' style="display:none;" ' : '';
					$dates_no_display = $scheduling_type == 'weekly'? ' style="display:none;" ' : '';
				?>

				<tr valign="top">
					<th scope="row"><?php echo __('Delivery scheduling type', 'ocws') ?></th>
					<td>
						<label class="as-block">
							<input type="radio" name="ocws_default_delivery_scheduling_type" id="ocws_default_delivery_scheduling_type_weekly"
								   value="weekly" <?php if ($scheduling_type == 'weekly') { ?> checked="checked"<?php } ?> />
							<?php echo __('Weekly', 'ocws') ?>
						</label>
						<label>
							<input class="as-block" type="radio" name="ocws_default_delivery_scheduling_type" id="ocws_default_delivery_scheduling_type_dates"
								   value="dates" <?php if ($scheduling_type == 'dates') { ?> checked="checked"<?php } ?> />
							<?php echo __('By specific days', 'ocws') ?>
						</label>

						<script>
							jQuery(document).ready(function () {

								jQuery('input[type=radio][name=ocws_default_delivery_scheduling_type]').on('change', function() {
									var schedule_type = jQuery(this).filter(':checked').val();
									if (schedule_type == 'weekly') {
										jQuery('tr[data-rel=dates_type]').hide();
										jQuery('tr[data-rel=weekly_type]').show();
									}
									else {
										jQuery('tr[data-rel=dates_type]').show();
										jQuery('tr[data-rel=weekly_type]').hide();
									}
								})
							});

						</script>
					</td>
				</tr>

				<?php

				$schedule_weekly_data = get_option('ocws_default_delivery_schedule_weekly', array());
				$schedule_weekly_object = new OC_Woo_Shipping_Schedule();
				$schedule_weekly_object->set_scheduling_type('weekly');
				// filter data
				$schedule_weekly_data = $schedule_weekly_object->set_days($schedule_weekly_data);
				$schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_weekly_data );
				$schedule_repeat_value = (int) get_option('ocws_default_delivery_schedule_repeat', 0);
				if (!$schedule_repeat_value || !in_array($schedule_repeat_value, array(1, 2, 4))) {
					$schedule_repeat_value = 1;
				}
				$schedule_repeat_start = get_option('ocws_default_delivery_schedule_repeat_start', '');

				$schedule_dates_data = get_option('ocws_default_delivery_schedule_dates', array());
				$schedule_dates_object = new OC_Woo_Shipping_Schedule();
				$schedule_dates_object->set_scheduling_type('dates');
				// filter data
				$schedule_dates_data = $schedule_dates_object->set_days($schedule_dates_data);
				$shipping_dates = $schedule_dates_object->get_dates();
				$schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_dates_data );

				?>

				<tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
					<th scope="row"><?php echo __('Choose specific dates', 'ocws') ?></th>
					<td>
						<script>
							jQuery(document).ready(function () {

								jQuery("#ocws_default_delivery_dates").multiDatesPicker({

									dateFormat: 'dd/mm/yy',
									minDate: 0,
									maxPicks: 7,
									<?php echo !empty($shipping_dates)? 'addDates: ' . json_encode($shipping_dates) . ',' : ''; ?>
									onSelect: function() {
										var dates = jQuery(this).multiDatesPicker("getDates");
										var schedule = JSON.parse(jQuery('#schedule_dates').jqs('export'));
										var newSchedule = [];
										for (var k=0; k < dates.length; k++) {
											var date = dates[k];
											var alreadyInSchedule = false;
											for (var l=0; l < schedule.length; l++) {
												if (schedule[l].day == date) {
													alreadyInSchedule = true;
													newSchedule.push(schedule[l]);
													break;
												}
											}
											if (!alreadyInSchedule) {
												newSchedule.push({'day':date,'periods':[]});
											}
										}
										jQuery('#schedule_dates').jqs('reset');
										jQuery('#schedule_dates').jqs('import', newSchedule);
										jQuery('#ocws_default_delivery_schedule_dates').val(jQuery('#schedule_dates').jqs('export'));
									}
								});
							});

						</script>
						<!--<div id="ocws_default_delivery_dates_mdp"></div>-->
						<div style="z-index: 100">
							<input style="position: relative; z-index: 100000;" type="text" id="ocws_default_delivery_dates" name="ocws_default_delivery_dates" readonly="readonly"
								   value="<?php printf(get_option('ocws_default_delivery_dates')); ?>">
						</div>
					</td>
				</tr>

				<tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
					<th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
					<td>
						<input type="hidden" name="ocws_default_delivery_schedule_dates" id="ocws_default_delivery_schedule_dates"
							   value="<?php echo esc_attr( $schedule_dates_json ); ?>" />
						<div style="direction:ltr;" id="schedule_dates"></div>
						<script>
							jQuery(document).ready(function () {

								var scheduleDatesDiv = jQuery('#schedule_dates');

								scheduleDatesDiv.jqs({
									mode: 'edit',
									type: 'dates',
									hour: 24,
									days: 7,
									periodDuration: 30,
									data: [],
									periodOptions: true,
									periodDefaultData: [
										{
											name: 'products',
											title: 'Max products',
											value: '',
											type: 'number'
										},
										{
											name: 'orders',
											title: 'Max orders',
											value: '',
											type: 'number'
										},
										{
											name: 'show_slot_after_start',
											title: 'Show the slot after it starts',
											value: false,
											type: 'boolean'
										},
										{
											name: 'hide_slot_x_min_before_end',
											title: 'Hide the slot x minutes before end',
											value: 0,
											type: 'number'
										}
									],
									periodColors: [],
									periodTitle: '',
									periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
									periodBorderColor: '#2a3cff',
									periodTextColor: '#000',
									periodRemoveButton: 'Remove',
									periodDuplicateButton: 'Duplicate',
									periodTitlePlaceholder: 'Title',
									daysList: [
										'-',
										'-',
										'-',
										'-',
										'-',
										'-',
										'-'
									],
									onInit: function () {},
									onAddPeriod: function (period, jqs) {
										console.log('Add period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));
									},
									onUpdatePeriod: function (period, jqs) {
										console.log('Update period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));
									},
									onDragPeriod: function (period, jqs) {
										console.log('Drag period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));
									},
									onResizePeriod: function (period, jqs) {
										console.log('Resize period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));
									},
									onAfterRemovePeriod: function (jqs) {
										console.log('Remove period');
										jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));
									},
									onDuplicatePeriod: function (period, jqs) {
										console.log('Duplicate period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));
									},
									onChangeMaxHour: function (jqs) {
										// if (jqs)
										 jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));
									},
									onClickPeriod: function () {}
								});

								scheduleDatesDiv.jqs('addMaxHour');

								scheduleDatesDiv.jqs('import', <?php echo $schedule_dates_json; ?>);
								jQuery('#ocws_default_delivery_schedule_dates').val(scheduleDatesDiv.jqs('export'));

							});
						</script>
					</td>
				</tr>

				<tr valign="top" data-rel="weekly_type" <?php echo $weekly_no_display; ?>>
					<th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
					<td>
						<input type="hidden" name="ocws_default_delivery_schedule_weekly" id="ocws_default_delivery_schedule_weekly"
							   value="<?php echo esc_attr( $schedule_weekly_json ); ?>" />
						<div id="schedule_repeat_options">
							<label><?php echo esc_html(__('Repeat delivery', 'ocws')); ?>
								<select name="ocws_default_delivery_schedule_repeat" id="schedule_repeat_value">
									<option value="1"<?php echo ($schedule_repeat_value == 1? ' selected' : '') ?>><?php echo esc_html(__('Every week', 'ocws')) ?></option>
									<option value="2"<?php echo ($schedule_repeat_value == 2? ' selected' : '') ?>><?php echo esc_html(__('Every two weeks', 'ocws')) ?></option>
									<option value="4"<?php echo ($schedule_repeat_value == 4? ' selected' : '') ?>><?php echo esc_html(__('Every four weeks', 'ocws')) ?></option>
								</select>
							</label>
							<label><?php echo esc_html(__('Start from date', 'ocws')); ?>
								<input style="position: relative; z-index: 100;" type="text" id="schedule_repeat_start" name="ocws_default_delivery_schedule_repeat_start" readonly="readonly"
									   value="<?php echo esc_attr($schedule_repeat_start); ?>">
							</label>
						</div>
						<div id="schedule_weekly"></div>
						<script>
							jQuery(document).ready(function () {

								jQuery("#schedule_repeat_start").multiDatesPicker({

									dateFormat: 'dd/mm/yy',
									minDate: 0,
									maxPicks: 1,
									<?php //echo !empty($schedule_repeat_start)? 'addDates: ' . json_encode(array($schedule_repeat_start)) . ',' : ''; ?>
									onSelect: function() {
										var dates = jQuery(this).multiDatesPicker("getDates");
									}//,
									//beforeShowDay: function(date) {
									//	var day = date.getDay();
									//	return [(day == 0), ''];
									//}
								});
							});

						</script>
						<script>
							jQuery(document).ready(function () {

								var scheduleWeeklyDiv = jQuery('#schedule_weekly');

								scheduleWeeklyDiv.jqs({
									mode: 'edit',
									hour: 24,
									days: 7,
									periodDuration: 30,
									data: [],
									periodOptions: true,
									periodDefaultData: [
										{
											name: 'products',
											title: 'Max products',
											value: '',
											type: 'number'
										},
										{
											name: 'orders',
											title: 'Max orders',
											value: '',
											type: 'number'
										},
										{
											name: 'show_slot_after_start',
											title: 'Show the slot after it starts',
											value: false,
											type: 'boolean'
										},
										{
											name: 'hide_slot_x_min_before_end',
											title: 'Hide the slot x minutes before end',
											value: 0,
											type: 'number'
										}
									],
									periodColors: [],
									periodTitle: '',
									periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
									periodBorderColor: '#2a3cff',
									periodTextColor: '#000',
									periodRemoveButton: 'Remove',
									periodDuplicateButton: 'Duplicate',
									periodTitlePlaceholder: 'Title',
									daysList: [
										'Sunday',
										'Monday',
										'Tuesday',
										'Wednesday',
										'Thursday',
										'Friday',
										'Saturday'
									],
									onInit: function () {},
									onAddPeriod: function (period, jqs) {
										console.log('Add period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onUpdatePeriod: function (period, jqs) {
										console.log('Update period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onDragPeriod: function (period, jqs) {
										console.log('Drag period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onResizePeriod: function (period, jqs) {
										console.log('Resize period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onAfterRemovePeriod: function (jqs) {
										console.log('Remove period');
										jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onDuplicatePeriod: function (period, jqs) {
										console.log('Duplicate period');
										console.log(period);
										jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
                                    onChangeFilterPicker: function (period, jqs) {
                                        console.log(period, jqs);
                                    },
									onChangeMaxHour: function (jqs) {
										// if (jqs)
										 jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));
									},
									onClickPeriod: function () {}
								});

								scheduleWeeklyDiv.jqs('addMaxHour');

								scheduleWeeklyDiv.jqs('import', <?php echo $schedule_weekly_json; ?>);
								jQuery('#ocws_default_delivery_schedule_weekly').val(scheduleWeeklyDiv.jqs('export'));

								scheduleWeeklyDiv.jqs('updateDaysList',
									[
									'Sun',
									'Mon',
									'Tue',
									'Wed',
									'Thu',
									'Fri',
									'Sat'
								]
								);
							});
						</script>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>

		</form>

		<?php


	}


	public static function output_group_settings($group_id) {

		$group = OC_Woo_Shipping_Groups::get_group( absint( $group_id ) );
		if ( ! $group ) {
			wp_die( esc_html__( 'Group does not exist!', 'ocws' ) );
		}

		$group_options = array(
            array(
                'name' => 'shipping_price',
                'title' => __('Shipping price', 'ocws'),
                'class' => ''
            ),
            array(
                'name' => 'min_total_for_free_shipping',
                'title' => __('Minimum total for free shipping', 'ocws'),
                'class' => ''
            ),
			array(
				'name' => 'min_total',
				'title' => __('Minimum total', 'ocws'),
				'class' => ''
			),
			array(
				'name' => 'min_total_message_yes',
				'title' => __('Minimum total message in case it is applicable', 'ocws'),
				'class' => ''
			),
			array(
				'name' => 'min_total_message_no',
				'title' => __('Minimum total message in case it is not applicable', 'ocws'),
				'class' => ''
			),
			array(
				'name' => 'preorder_days',
				'title' => __('Preorder days', 'ocws'),
				'class' => ''
			),
			array(
				'name' => 'min_wait_times',
				'title' => __('Minimum wait times (in minutes)', 'ocws'),
				'class' => ''
			),
			array(
				'name' => 'max_hour_for_today',
				'title' => __('Latest hour to order today', 'ocws'),
				'class' => 'timepicker'
			),
			/*'max_products_per_slot'*/
		);
		?>

		<h2>Group Settings</h2>
		<form id="group_options_form" method="post" action="options.php">
			<?php settings_fields( 'ocws_group' . $group_id ); ?>
			<?php do_settings_sections( 'ocws_group' . $group_id ); ?>
            <?php
            $option = OC_Woo_Shipping_Group_Option::get_option($group_id, 'price_depending');
            if (empty($option['option_value'])) {
                $schema = ['active' => false, 'rules' => []];
            }
            else {
                $schema = json_decode($option['option_value'], true);
            }
            ?>
			<table class="form-table">


                <tr id="<?php echo $option['option_name']; ?>">
                    <th scope="row">
                        <?php // echo __('Rules depending on the price'); ?>
                        <label>
                            <select id="<?php echo $group_id; ?>_price_depending_rules" style="width: 100%;">
                                <option <?php if (!$schema['active']): ?> selected <?php endif; ?> value="off"><?php echo __('Fixed price', 'ocws'); ?></option>
                                <option <?php if ($schema['active']): ?> selected <?php endif; ?> value="on"><?php echo __('Depending price', 'ocws'); ?></option>
                            </select>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   style="visibility: hidden; opacity: 0; width: 1px; height: 1px;"
                                   <?php if ($schema['active']): ?> checked <?php endif; ?>
                                   class="price_depending_active"
                                   value="1">
                            <input type="hidden" name="<?php echo $option['option_name']; ?>_ud" value="0">
                        </label>
                        <input type="hidden"
                               name="<?php echo $option['option_name']; ?>"
                               value='<?php echo json_encode($schema); ?>'>
                        <table <?php if (!$schema['active']): ?> style="display: none;" <?php endif; ?> class="price_depending_rules">
                            <thead>
                            <tr>
                                <th>Cart value</th>
                                <th>Shipping price</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($schema['rules'] as $rule): ?>
                                <tr>
                                    <td><input type="number" placeholder="0"
                                               class="price_depending_event cart_value"
                                               value="<?php echo $rule['cart_value']; ?>" /></td>
                                    <td><input type="number" placeholder="0"
                                               class="price_depending_event shipping_price"
                                               value="<?php echo $rule['shipping_price']; ?>" /></td>
                                    <td><button type="button" class="button price_depending_remove">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td>
                                    <button type="button" class="button price_depending_add"><?php esc_html_e( 'Add', 'ocws' ); ?></button>
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                        <script>
                            (function ($) {
                                $(document).ready(function ($) {
                                    var $activation_price_depending_rules = $('#<?php echo $group_id; ?>_price_depending_rules');
                                    var $price_depending_rules = $('#group_options_form .price_depending_rules');
                                    var $shipping_price = $('#group_options_form #<?php echo $group_id; ?>_shipping_price');
                                    var $min_total_for_free_shipping = $('#group_options_form #<?php echo $group_id; ?>_min_total_for_free_shipping');
                                    var $price_add = $('#group_options_form .price_depending_add');
                                    var $price_depending_rules_body = $price_depending_rules.find('tbody');
                                    var $price_depending_active = $('#group_options_form .price_depending_active');
                                    $activation_price_depending_rules.on('change', function (event) {
                                        event.preventDefault();
                                        if (this.value === 'off') {
                                            $price_depending_active.prop('checked', false);
                                        }
                                        else {
                                            $price_depending_active.prop('checked', true);
                                        }
                                        $price_depending_active.trigger('change');
                                    });
                                    if ($price_add.length) {
                                        $price_add.on('click', function (event) {
                                            event.preventDefault();
                                            $price_depending_rules_body.append(`<tr>
                                        <td><input type="number" placeholder="0" class="price_depending_event cart_value" value="0" /></td>
                                        <td><input type="number" placeholder="0" class="price_depending_event shipping_price" value="0" /></td>
                                        <td><button type="button" class="button price_depending_remove">Remove</button></td>
                                    </tr>`);
                                        });
                                    }
                                    $price_depending_active.on('change', function () {
                                        var $item = $(this);
                                        var $schema = $item.parent().siblings('input[type="hidden"]');
                                        var object = JSON.parse($schema.val());
                                        if ($item.is(":checked")) {
                                            object['active'] = true;
                                            $price_depending_rules.show();
                                            $shipping_price.hide();
                                            $min_total_for_free_shipping.hide();
                                        }
                                        else {
                                            object['active'] = false;
                                            $price_depending_rules.hide();
                                            $shipping_price.show();
                                            $min_total_for_free_shipping.show();
                                        }
                                        $schema.val(JSON.stringify(object));
                                        $schema.trigger('change');
                                    });
                                    $(document).on('click', '#group_options_form .price_depending_remove', function (event) {
                                        event.preventDefault();
                                        var $item = $(this);
                                        var $tbody = $item.parents('tbody').eq(0);
                                        var $schema = $item.parents('.price_depending_rules').siblings('input[type="hidden"]');
                                        let schema = [];
                                        $item.parents('tr').eq(0).remove();
                                        $tbody.children().each(function (index, row) {
                                            var $row = $(row);
                                            var $cart_value = $row.find('.cart_value');
                                            var $shipping_price = $row.find('.shipping_price');
                                            schema.push({
                                                cart_value: parseFloat($cart_value.val()),
                                                shipping_price: parseFloat($shipping_price.val())
                                            })
                                        });
                                        $schema.val(JSON.stringify({ active: true, rules: schema }));
                                        $schema.trigger('change');
                                    });
                                    $(document).on('change', '#group_options_form .price_depending_event', function (event) {
                                        var $item = $(this);
                                        var $tbody = $item.parents('tbody').eq(0);
                                        var $schema = $item.parents('.price_depending_rules').siblings('input[type="hidden"]');
                                        let schema = [];
                                        $tbody.children().each(function (index, row) {
                                            var $row = $(row);
                                            var $cart_value = $row.find('.cart_value');
                                            var $shipping_price = $row.find('.shipping_price');
                                            schema.push({
                                                cart_value: parseFloat($cart_value.val()),
                                                shipping_price: parseFloat($shipping_price.val())
                                            })
                                        });
                                        $schema.val(JSON.stringify({ active: true, rules: schema }));
                                        $schema.trigger('change');
                                    });
                                });
                            })(jQuery);
                        </script>
                    </td>
                </tr>
				<?php
					foreach ($group_options as $opt) {
						$option = OC_Woo_Shipping_Group_Option::get_option($group_id, $opt['name']);
						$display_option = true;
						if (in_array($opt['name'], array('min_total_message_yes', 'max_hour_for_today'))) $display_option = false;
                        // shipping_price
                        // min_total_for_free_shipping
                        if ($schema['active'] && in_array($opt['name'], array('shipping_price', 'min_total_for_free_shipping'))) {
                            $display_option = false;
                        }
						?>

						<tr id="<?php echo esc_html($group_id . '_' . $opt['name']); ?>" valign="top" style="<?php echo (!$display_option? 'display:none;' : ''); ?>">
							<th scope="row"><?php echo esc_html($opt['title']) ?></th>
							<td>
								<div>
									<?php
									//var_dump($option);
									?>
								</div>
								<input class="<?php echo esc_attr( $opt['class'] ) ?>"
									type="text" name="<?php echo esc_attr( $option['option_name'] ) ?>" value="<?php echo esc_attr( $option['option_value'] ); ?>"
									   data-default-value="<?php echo esc_attr( $option['default'] ); ?>" <?php echo ($option['use_default']? 'disabled="true"' : '') ?>/>
								<br />
								<label>
									<input data-rel="<?php echo esc_attr( $option['option_name'] ) ?>" type="checkbox" name="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>"
										   class="use-default-switch" id="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>"
										   value="1" <?php if ($option['use_default'] === '1') { ?> checked="checked"<?php } ?> />
									<?php echo __('Use default value', 'ocws') ?>
								</label>
							</td>
						</tr>

						<?php
					}
				?>

				<?php

				$option = OC_Woo_Shipping_Group_Option::get_option($group_id, 'closing_weekdays', '');
				$closing_weekdays = $option['option_value'];
				$default_closing_weekdays = $option['default'];
				if (!$closing_weekdays && $closing_weekdays != 0) {
					$closing_weekdays = array();
				}
				else {
					$closing_weekdays = ocws_numbers_list_to_array( $closing_weekdays );
				}
				if (!$default_closing_weekdays && $default_closing_weekdays != 0) {
					$default_closing_weekdays = array();
				}
				else {
					$default_closing_weekdays = ocws_numbers_list_to_array( $default_closing_weekdays );
				}
				?>

				<tr valign="top">
					<th scope="row"><?php echo __('Exclude days of week', 'ocws') ?></th>
					<td>
						<input type="hidden" id="<?php echo esc_attr( $option['option_name'] ) ?>" name="<?php echo esc_attr( $option['option_name'] ) ?>"
							   data-default-value="<?php echo esc_attr( $option['default'] ); ?>"
							   value="<?php echo esc_attr( $option['option_value'] ); ?>">
						<div id="closing_weekdays" style="<?php echo ($option['use_default'] === '1'? 'display: none' : '') ?>">
							<label>
							<input type="checkbox" id="closing_weekdays-0" data-weekday="0"
								   value="1" <?php if (in_array('0', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Sunday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-1" data-weekday="1"
									   value="1" <?php if (in_array('1', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Monday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-2" data-weekday="2"
									   value="1" <?php if (in_array('2', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Tuesday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-3" data-weekday="3"
									   value="1" <?php if (in_array('3', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Wednesday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-4" data-weekday="4"
									   value="1" <?php if (in_array('4', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Thursday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-5" data-weekday="5"
									   value="1" <?php if (in_array('5', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Friday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" id="closing_weekdays-6" data-weekday="6"
									   value="1" <?php if (in_array('6', $closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Saturday', 'ocws') ?>
							</label>
						</div>
						<div id="default_closing_weekdays" style="<?php echo ($option['use_default'] !== '1'? 'display: none' : '') ?>">
							<label>
								<input type="checkbox" disabled="disabled"
									   <?php if (in_array('0', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Sunday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" disabled="disabled"
									   <?php if (in_array('1', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Monday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" disabled="disabled"
									   <?php if (in_array('2', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Tuesday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" disabled="disabled"
									   <?php if (in_array('3', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Wednesday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" disabled="disabled"
									   <?php if (in_array('4', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Thursday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" disabled="disabled"
									   <?php if (in_array('5', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Friday', 'ocws') ?>
							</label>
							<label>
								<input type="checkbox" disabled="disabled"
									   <?php if (in_array('6', $default_closing_weekdays)) { ?> checked="checked"<?php } ?> ><?php echo __('Saturday', 'ocws') ?>
							</label>
						</div>
						<div>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>" id="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>"
									   data-default-value="<?php echo esc_attr( $option['default'] ); ?>"
									   value="1" <?php if ($option['use_default'] === '1') { ?> checked="checked"<?php } ?> />
								<?php echo __('Use default value', 'ocws') ?>
							</label>
						</div>

						<script>
							jQuery(document).ready(function () {

								jQuery('#closing_weekdays input[type=checkbox]').on('change', function() {
									var hidden = jQuery("#<?php echo esc_attr( $option['option_name'] ) ?>");
                                    var values = [];
                                    jQuery('#closing_weekdays input[type=checkbox]:checked').each(function (index, value) {
                                        values.push(jQuery(value).data('weekday'));
                                    });
                                    hidden.val(values.join(','))

									// var val = hidden.val().split( ',' );
									// var weekday = jQuery(this).data('weekday').toString();
									// var index = false;
									// for (var k=0; k < val.length; k++) {
                                    //     // here BUGs
									// 	if (weekday == val[k]) {
									// 		index = k;
									// 		break;
									// 	}
									// }
									// if (this.checked) {
									// 	if (index === false) {
									// 		val.push(weekday);
									// 	}
									// }
									// else {
									// 	if (index !== false) {
									// 		val.splice(index, 1);
									// 	}
									// }
									// hidden.val(val.join(','));
								});

								jQuery('input[type=checkbox][name=<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>]').on('change', function() {
									if (this.checked) {
										jQuery('#default_closing_weekdays').show();
										jQuery('#closing_weekdays').hide();
									}
									else {
										jQuery('#default_closing_weekdays').hide();
										jQuery('#closing_weekdays').show();
									}
								});
							});

						</script>
					</td>
				</tr>

				<?php

				$option = OC_Woo_Shipping_Group_Option::get_option($group_id, 'closing_dates', '');
				$closing_dates = $option['option_value'];
				if (!$closing_dates) {
					$closing_dates = array();
				}
				else {
					$closing_dates = ocws_dates_list_to_array( $closing_dates, true );
				}
				?>

				<tr valign="top">
					<th scope="row"><?php echo __('Exclude dates', 'ocws') ?></th>
					<td>
						<div>
							<input style="<?php echo ($option['use_default'] !== '1'? 'display: none;' : '') ?>" type="text" id="ocws_default_closing_dates" readonly="readonly"
								   value="<?php echo esc_attr( $option['default'] ); ?>">

							<input style="position: relative; z-index: 100000;<?php echo ($option['use_default'] === '1'? 'display: none;' : '') ?>"
								   type="text" id="<?php echo esc_attr( $option['option_name'] ) ?>"
								   name="<?php echo esc_attr( $option['option_name'] ) ?>" readonly="readonly"
								   value="">
						</div>
						<div>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>" id="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>"
									   data-default-value="<?php echo esc_attr( $option['default'] ); ?>"
									   value="1" <?php if ($option['use_default'] === '1') { ?> checked="checked"<?php } ?> />
								<?php echo __('Use default value', 'ocws') ?>
							</label>
						</div>

						<script>
							jQuery(document).ready(function () {

								jQuery("#<?php echo esc_attr( $option['option_name'] ) ?>").multiDatesPicker({

									dateFormat: 'dd/mm/yy',
									minDate: 0,
									maxPicks: 100,
									<?php echo !empty($closing_dates)? 'addDates: ' . json_encode($closing_dates) . ',' : ''; ?>
									onSelect: function() {}
								});

								jQuery('input[type=checkbox][name=<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>]').on('change', function() {
									if (this.checked) {
										jQuery('#ocws_default_closing_dates').show();
										jQuery('#<?php echo esc_attr( $option['option_name'] ) ?>').hide();
									}
									else {
										jQuery('#<?php echo esc_attr( $option['option_name'] ) ?>').show();
										jQuery('#ocws_default_closing_dates').hide();
									}
								});
							});
						//# scriptURL=multidatepicker-closing-dates.js
						</script>
					</td>
				</tr>

				<?php

				$option = OC_Woo_Shipping_Group_Option::get_option($group_id, 'delivery_scheduling_type', 'weekly');
				$scheduling_type = $option['option_value'];
				if (!$scheduling_type) {
					$scheduling_type = 'weekly';
				}
				$weekly_no_display = $scheduling_type == 'dates'? ' style="display:none;" ' : '';
				$dates_no_display = $scheduling_type == 'weekly'? ' style="display:none;" ' : '';

				?>

				<tr valign="top">
					<th scope="row"><?php echo __('Delivery scheduling type', 'ocws') ?></th>
					<td><?php echo $scheduling_type; ?>
						<label class="as-block">
							<input type="radio" name="<?php echo esc_attr( $option['option_name'] ) ?>" id="<?php echo esc_attr( $option['option_name'] ) ?>_weekly"
								   value="weekly" <?php if ($scheduling_type == 'weekly') { ?> checked="checked"<?php } ?> <?php echo ($option['use_default']? 'disabled="true"' : '') ?>/>
							<?php echo __('Weekly', 'ocws') ?>
						</label>
						<label class="as-block">
							<input type="radio" name="<?php echo esc_attr( $option['option_name'] ) ?>" id="<?php echo esc_attr( $option['option_name'] ) ?>_dates"
								   value="dates" <?php if ($scheduling_type == 'dates') { ?> checked="checked"<?php } ?> <?php echo ($option['use_default']? 'disabled="true"' : '') ?>/>
							<?php echo __('By specific days', 'ocws') ?>
						</label>

						<div>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>" id="<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>"
									   data-default-value="<?php echo esc_attr( $option['default'] ); ?>"
									   value="1" <?php if ($option['use_default'] === '1') { ?> checked="checked"<?php } ?> />
								<?php echo __('Use default value', 'ocws') ?>
							</label>
						</div>


						<script>
							jQuery(document).ready(function () {

								jQuery('input[type=radio][name=<?php echo esc_attr( $option['option_name'] ) ?>]').on('change', function() {
									var schedule_type = jQuery(this).filter(':checked').val();
									if (schedule_type == 'weekly') {
										jQuery('tr[data-rel=dates_type]').hide();
										jQuery('tr[data-rel=dates_type_choose]').hide();
										jQuery('tr[data-rel=weekly_type]').show();
									}
									else {
										jQuery('tr[data-rel=dates_type]').show();
										jQuery('tr[data-rel=weekly_type]').hide();
										jQuery( document.body ).trigger( 'type_chosen_dates' );
									}
								});

								jQuery('input[type=checkbox][name=<?php echo esc_attr( $option['option_name'] . '_ud' ) ?>]').on('click', function() {
									var radio = jQuery('input[type=radio][name=<?php echo esc_attr( $option['option_name'] ) ?>]');
									if (this.checked) {
										jQuery(radio).filter('[value='+jQuery(this).data('default-value')+']').prop('checked', true).trigger('change');
										jQuery(radio).prop('disabled', true);
									}
									else {
										jQuery(radio).filter('[value=<?php echo $scheduling_type ?>]').prop('checked', true).trigger('change');
										jQuery(radio).prop('disabled', false);
									}
								})
							});

						</script>
					</td>
				</tr>

				<?php

				$option_weekly = OC_Woo_Shipping_Group_Option::get_option($group_id, 'delivery_schedule_weekly', array());

				//var_dump($option_weekly);

				$schedule_weekly_data = $option_weekly['option_value'];
				$schedule_weekly_object = new OC_Woo_Shipping_Schedule();
				$schedule_weekly_object->set_scheduling_type('weekly');
				// filter data
				$schedule_weekly_data = $schedule_weekly_object->set_days($schedule_weekly_data);
				$schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_weekly_data );

				$default_schedule_weekly_data = $option_weekly['default'];
				$default_schedule_weekly_object = new OC_Woo_Shipping_Schedule();
				$default_schedule_weekly_object->set_scheduling_type('weekly');
				// filter data
				$default_schedule_weekly_data = $default_schedule_weekly_object->set_days($default_schedule_weekly_data);
				$default_schedule_weekly_json = OC_Woo_Shipping_Schedule::export_to_json( $default_schedule_weekly_data );
				$default_schedule_repeat_value = (int) get_option('ocws_default_delivery_schedule_repeat', 0);
				if (!$default_schedule_repeat_value || !in_array($default_schedule_repeat_value, array(1, 2, 4))) {
					$default_schedule_repeat_value = 1;
				}
				$default_schedule_repeat_start = get_option('ocws_default_delivery_schedule_repeat_start', '');

				$schedule_repeat_value = OC_Woo_Shipping_Group_Option::get_option($group_id, 'delivery_schedule_repeat', 0);
				if (!$schedule_repeat_value['option_value'] || !in_array($schedule_repeat_value['option_value'], array(1, 2, 4))) {
					$schedule_repeat_value['option_value'] = 1;
				}
				$schedule_repeat_start = OC_Woo_Shipping_Group_Option::get_option($group_id, 'delivery_schedule_repeat_start', '');

				$option_dates = OC_Woo_Shipping_Group_Option::get_option($group_id, 'delivery_schedule_dates', array());

				//var_dump($option_dates);

				$schedule_dates_data = $option_dates['option_value'];
				$schedule_dates_object = new OC_Woo_Shipping_Schedule();
				$schedule_dates_object->set_scheduling_type('dates');
				// filter data
				$schedule_dates_data = $schedule_dates_object->set_days($schedule_dates_data);
				$shipping_dates = $schedule_dates_object->get_dates();
				$schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $schedule_dates_data );
				//$schedule_dates_json = str_replace('"', "'", ($schedule_dates_json? $schedule_dates_json : ''));

				//echo ($schedule_dates_json);

				$default_schedule_dates_data = $option_dates['default'];
				$default_schedule_dates_object = new OC_Woo_Shipping_Schedule();
				$default_schedule_dates_object->set_scheduling_type('dates');
				// filter data
				$default_schedule_dates_data = $default_schedule_dates_object->set_days($default_schedule_dates_data);
				$default_shipping_dates = $default_schedule_dates_object->get_dates();
				$default_schedule_dates_json = OC_Woo_Shipping_Schedule::export_to_json( $default_schedule_dates_data );
				//$default_schedule_dates_json = str_replace('"', "'", ($default_schedule_dates_json? $default_schedule_dates_json : ''));

				$choose_dates_no_display = ($scheduling_type == 'weekly' || $option_dates['use_default'])? ' style="display:none;" ' : '';

				// 

				$allow_checkout_validation_without_slots 	= OC_Woo_Shipping_Group_Option::get_option( $group_id, 'allow_checkout_validation_without_slots', '' );
				$checkout_warning_message_without_slots 	= OC_Woo_Shipping_Group_Option::get_option( $group_id, 'checkout_warning_message_without_slots', '' );

				?>

				<tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
					<th scope="row"></th>
					<td>
						<div>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_dates['option_name'] . '_ud' ) ?>" id="<?php echo esc_attr( $option_dates['option_name'] . '_ud' ) ?>"
									   value="1" <?php if ($option_dates['use_default'] === '1') { ?> checked="checked"<?php } ?> />
								<?php echo __('Use default schedule', 'ocws') ?>
							</label>
						</div>
					</td>
				</tr>

				<tr valign="top" data-rel="dates_type_choose" <?php echo $choose_dates_no_display; ?>>
					<th scope="row"><?php echo __('Choose specific dates', 'ocws') ?></th>
					<td>

						<script>
							jQuery(document).ready(function () {

								jQuery("#ocws_group_delivery_dates").multiDatesPicker({

									dateFormat: 'dd/mm/yy',
									minDate: 0,
									maxPicks: 7,
									<?php echo !empty($shipping_dates)? 'addDates: ' . json_encode($shipping_dates) . ',' : ''; ?>
									onSelect: function() {
										var dates = jQuery(this).multiDatesPicker("getDates");
										var schedule = JSON.parse(jQuery('#schedule_dates').jqs('export'));
										var newSchedule = [];
										for (var k=0; k < dates.length; k++) {
											var date = dates[k];
											var alreadyInSchedule = false;
											for (var l=0; l < schedule.length; l++) {
												if (schedule[l].day == date) {
													alreadyInSchedule = true;
													newSchedule.push(schedule[l]);
													break;
												}
											}
											if (!alreadyInSchedule) {
												newSchedule.push({'day':date,'periods':[]});
											}
										}
										jQuery('#schedule_dates').jqs('reset');
										jQuery('#schedule_dates').jqs('import', newSchedule);
										jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(jQuery('#schedule_dates').jqs('export'));
									}
								});
							});
//# sourceURL=multidatepicker-inline.js
						</script>
						<!--<div id="ocws_default_delivery_dates_mdp"></div>-->
						<div style="<?php //echo ($option_dates['use_default']? 'display: none;' : '') ?>">
							<input style="position: relative; z-index: 100000;" type="text" id="ocws_group_delivery_dates" name="ocws_group_delivery_dates" readonly="readonly"
								   value="<?php printf(get_option('ocws_default_delivery_dates')); ?>">
						</div>
					</td>
				</tr>

				<tr valign="top" data-rel="dates_type" <?php echo $dates_no_display; ?>>
					<th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
					<td>
						<input type="hidden" testtag="milla" name="<?php echo esc_attr( $option_dates['option_name'] ) ?>" id="<?php echo esc_attr( $option_dates['option_name'] ) ?>"
							   value="<?php echo esc_attr( $schedule_dates_json ); ?>" />
						<div style="<?php echo ($option_dates['use_default']? 'display: none;direction:ltr;' : 'direction:ltr;') ?>" id="schedule_dates"></div>
						<div style="<?php echo ($option_dates['use_default']? '' : 'display: none;') ?>" id="default_schedule_dates"></div>
						<script>
							jQuery(document).ready(function () {

								var scheduleDatesDiv = jQuery('#schedule_dates');

								scheduleDatesDiv.jqs({
									mode: 'edit',
									type: 'dates',
									hour: 24,
									days: 7,
									periodDuration: 30,
									data: [],
									periodOptions: true,
									periodDefaultData: [
										{
											name: 'products',
											title: 'Max products',
											value: '',
											type: 'number'
										},
										{
											name: 'orders',
											title: 'Max orders',
											value: '',
											type: 'number'
										},
										{
											name: 'show_slot_after_start',
											title: 'Show the slot after it starts',
											value: false,
											type: 'boolean'
										},
										{
											name: 'hide_slot_x_min_before_end',
											title: 'Hide the slot x minutes before end',
											value: 0,
											type: 'number'
										}
									],
									periodColors: [],
									periodTitle: '',
									periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
									periodBorderColor: '#2a3cff',
									periodTextColor: '#000',
									periodRemoveButton: 'Remove',
									periodDuplicateButton: 'Duplicate',
									periodTitlePlaceholder: 'Title',
									daysList: [
										'-',
										'-',
										'-',
										'-',
										'-',
										'-',
										'-'
									],
									onInit: function () {},
									onAddPeriod: function (period, jqs) {
										console.log('Add period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));
									},
									onUpdatePeriod: function (period, jqs) {
										console.log('Update period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));
									},
									onDragPeriod: function (period, jqs) {
										console.log('Drag period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));
									},
									onResizePeriod: function (period, jqs) {
										console.log('Resize period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));
									},
									onAfterRemovePeriod: function (jqs) {
										console.log('Remove period');
										jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));
									},
									onDuplicatePeriod: function (period, jqs) {
										console.log('Duplicate period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));
									},
									onChangeMaxHour: function (jqs) {
										// if (jqs)
											jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));
									},
                                    onChangeFilterPicker: function (period, jqs) {
                                        console.log(period, jqs);
                                    },
									onClickPeriod: function () {}
								});


								scheduleDatesDiv.jqs('addMaxHour');
                                // HERE GOLD DEV
								scheduleDatesDiv.jqs('import', <?php echo $schedule_dates_json; ?>);
								jQuery('#<?php echo esc_attr( $option_dates['option_name'] ) ?>').val(scheduleDatesDiv.jqs('export'));

								// default schedule

								var defaultScheduleDatesDiv = jQuery('#default_schedule_dates');

								defaultScheduleDatesDiv.jqs({
									mode: 'read',
									type: 'dates',
									hour: 24,
									days: 7,
									periodDuration: 30,
									data: [],
									periodOptions: true,
									periodDefaultData: [
										{
											name: 'products',
											title: 'Max products',
											value: '',
											type: 'number'
										},
										{
											name: 'orders',
											title: 'Max orders',
											value: '',
											type: 'number'
										},
										{
											name: 'show_slot_after_start',
											title: 'Show the slot after it starts',
											value: false,
											type: 'boolean'
										},
										{
											name: 'hide_slot_x_min_before_end',
											title: 'Hide the slot x minutes before end',
											value: 0,
											type: 'number'
										}
									],
									periodColors: [],
									periodTitle: '',
									periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
									periodBorderColor: '#2a3cff',
									periodTextColor: '#000',
									periodRemoveButton: 'Remove',
									periodDuplicateButton: 'Duplicate',
									periodTitlePlaceholder: 'Title',
									daysList: [
										'-',
										'-',
										'-',
										'-',
										'-',
										'-',
										'-'
									],
									onInit: function () {},
									onAddPeriod: function (period, jqs) {

									},
									onUpdatePeriod: function (period, jqs) {

									},
									onDragPeriod: function (period, jqs) {

									},
									onResizePeriod: function (period, jqs) {

									},
									onAfterRemovePeriod: function (jqs) {

									},
									onDuplicatePeriod: function (period, jqs) {

									},
									onChangeMaxHour: function (jqs) {

									},
									onClickPeriod: function () {}
								});

								defaultScheduleDatesDiv.jqs('import', <?php echo $default_schedule_dates_json; ?>);

							});
						</script>
					</td>
				</tr>

				<tr valign="top" data-rel="weekly_type" <?php echo $weekly_no_display; ?>>
					<th scope="row"></th>
					<td>
						<div>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option_weekly['option_name'] . '_ud' ) ?>" id="<?php echo esc_attr( $option_weekly['option_name'] . '_ud' ) ?>"
									   value="1" <?php if ($option_weekly['use_default'] === '1') { ?> checked="checked"<?php } ?> />
								<?php echo __('Use default schedule', 'ocws') ?>
							</label>
						</div>
					</td>
				</tr>
				
				<!-- Group settings allow  -->
				<tr valign="top" style="background-color:#cecece;">
					<th><?php echo 'אזור שילוח אינו מאפשר בחירת תאריכים ספציפיים  '; ?></th>
					<td>
						<div style="margin: 0 0 10px;">
							<div>
								<?php  $warning_message_without_slots =  esc_attr( $checkout_warning_message_without_slots['option_value'] ); ?>
							</div>
							<div><?php //var_dump( $checkout_warning_message_without_slots ); ?></div>

							<label>
								<span><?php _e( 'Allow shipping validation event if it not set time slots' ); ?></span>
								<input type="checkbox" name="<?php echo esc_attr( $allow_checkout_validation_without_slots['option_name'] ) ?>" id="<?php echo esc_attr( $allow_checkout_validation_without_slots['option_name'] ) ?>" value="1" <?php checked( $allow_checkout_validation_without_slots['option_value'] ,'1' ); ?> />
							</label>
						</div>

						<div class="">
							<label for="">
								<div><?php _e( 'Enter message that apears instead of time slot' ) ?></div>
								<textarea style="min-height: 80px" id="default_schedule_repeat_start" name="<?php echo esc_attr($checkout_warning_message_without_slots['option_name']); ?>"><?php echo $warning_message_without_slots; ?></textarea>	
							</label>
						</div>
					</td>
				</tr>
				<!--  -->

				<tr valign="top" data-rel="weekly_type" <?php echo $weekly_no_display; ?>>
					<th scope="row"><?php echo __('Schedule', 'ocws') ?></th>
					<td>
                        <style>
                            .jqs-table .jqs-period {
                                position: absolute;
                            }
                        </style>
						<input type="hidden" name="<?php echo esc_attr( $option_weekly['option_name'] ) ?>" id="<?php echo esc_attr( $option_weekly['option_name'] ) ?>"
							   value="<?php echo esc_attr( $schedule_weekly_json ); ?>" />
						<div style="<?php echo ($option_weekly['use_default']? 'display: none;' : '') ?>" id="schedule_repeat_options">
							<label><?php echo esc_html(__('Repeat delivery', 'ocws')); ?>
								<select name="<?php echo esc_attr($schedule_repeat_value['option_name']) ?>" id="schedule_repeat_value">
									<option value="1"<?php echo ($schedule_repeat_value['option_value'] == 1? ' selected' : '') ?>><?php echo esc_html(__('Every week', 'ocws')) ?></option>
									<option value="2"<?php echo ($schedule_repeat_value['option_value'] == 2? ' selected' : '') ?>><?php echo esc_html(__('Every two weeks', 'ocws')) ?></option>
									<option value="4"<?php echo ($schedule_repeat_value['option_value'] == 4? ' selected' : '') ?>><?php echo esc_html(__('Every four weeks', 'ocws')) ?></option>
								</select>
							</label>
							<label><?php echo esc_html(__('Start from date', 'ocws')); ?>
								<input style="position: relative; z-index: 100;" type="text" id="schedule_repeat_start" name="<?php echo esc_attr($schedule_repeat_start['option_name']) ?>" readonly="readonly"
									   value="<?php echo esc_attr($schedule_repeat_start['option_value']); ?>">
							</label>
						</div>
						<div style="<?php echo ($option_weekly['use_default']? 'display: none;' : '') ?>" id="schedule_weekly"></div>
						<div style="<?php echo ($option_weekly['use_default']? '' : 'display: none;') ?>" id="default_schedule_repeat_options">
							<label><?php echo esc_html(__('Repeat delivery', 'ocws')); ?>
								<select id="default_schedule_repeat_value" disabled>
									<option value="1"<?php echo ($default_schedule_repeat_value == 1? ' selected' : '') ?>><?php echo esc_html(__('Every week', 'ocws')) ?></option>
									<option value="2"<?php echo ($default_schedule_repeat_value == 2? ' selected' : '') ?>><?php echo esc_html(__('Every two weeks', 'ocws')) ?></option>
									<option value="4"<?php echo ($default_schedule_repeat_value == 4? ' selected' : '') ?>><?php echo esc_html(__('Every four weeks', 'ocws')) ?></option>
								</select>
							</label>
							<label><?php echo esc_html(__('Start from date', 'ocws')); ?>
								<input style="position: relative; z-index: 100;" type="text" id="default_schedule_repeat_start" readonly="readonly" disabled
									   value="<?php echo esc_attr($default_schedule_repeat_start); ?>">
							</label>
						</div>
						<div style="<?php echo ($option_weekly['use_default']? '' : 'display: none;') ?>" id="default_schedule_weekly"></div>
						<script>
							jQuery(document).ready(function () {

								jQuery("#schedule_repeat_start").multiDatesPicker({

									dateFormat: 'dd/mm/yy',
									minDate: 0,
									maxPicks: 1,
									<?php //echo !empty($schedule_repeat_start['option_value'])? 'addDates: ' . json_encode(array($schedule_repeat_start['option_value'])) . ',' : ''; ?>
									onSelect: function() {
										var dates = jQuery(this).multiDatesPicker("getDates");
									}//,
									//beforeShowDay: function(date) {
									//	var day = date.getDay();
									//	return [(day == 0), ''];
									//}
								});
							});

						</script>
						<script>
							jQuery(document).ready(function () {

								var scheduleWeeklyDiv = jQuery('#schedule_weekly');

								scheduleWeeklyDiv.jqs({
									mode: 'edit',
									hour: 24,
									days: 7,
									periodDuration: 30,
									data: [],
									periodOptions: true,
									periodDefaultData: [
										{
											name: 'products',
											title: 'Max products',
											value: '',
											type: 'number'
										},
										{
											name: 'orders',
											title: 'Max orders',
											value: '',
											type: 'number'
										},
										{
											name: 'show_slot_after_start',
											title: 'Show the slot after it starts',
											value: false,
											type: 'boolean'
										},
										{
											name: 'hide_slot_x_min_before_end',
											title: 'Hide the slot x minutes before end',
											value: 0,
											type: 'number'
										}
									],
									periodColors: [],
									periodTitle: '',
									periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
									periodBorderColor: '#2a3cff',
									periodTextColor: '#000',
									periodRemoveButton: 'Remove',
									periodDuplicateButton: 'Duplicate',
									periodTitlePlaceholder: 'Title',
									daysList: [
										'Sunday',
										'Monday',
										'Tuesday',
										'Wednesday',
										'Thursday',
										'Friday',
										'Saturday'
									],
									onInit: function () {},
									onAddPeriod: function (period, jqs) {
										console.log('Add period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));
									},
									onUpdatePeriod: function (period, jqs) {
										console.log('Update period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));
									},
									onDragPeriod: function (period, jqs) {
										console.log('Drag period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));
									},
									onResizePeriod: function (period, jqs) {
										console.log('Resize period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));
									},
									onAfterRemovePeriod: function (jqs) {
										console.log('Remove period');
										jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));
									},
									onDuplicatePeriod: function (period, jqs) {
										console.log('Duplicate period');
										console.log(period);
										jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));
									},
									onChangeMaxHour: function (jqs) {
										// if (jqs)
											jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));
									},
                                    onChangeFilterPicker: function (period, jqs) {
                                        console.log(period, jqs);
                                    },
									onClickPeriod: function () {}
								});

								scheduleWeeklyDiv.jqs('addMaxHour');

								scheduleWeeklyDiv.jqs('import', <?php echo $schedule_weekly_json; ?>);
								jQuery('#<?php echo esc_attr( $option_weekly['option_name'] ) ?>').val(scheduleWeeklyDiv.jqs('export'));

								// default schedule

								var defaultScheduleWeeklyDiv = jQuery('#default_schedule_weekly');

								defaultScheduleWeeklyDiv.jqs({
									mode: 'read',
									hour: 24,
									days: 7,
									periodDuration: 30,
									data: [],
									periodOptions: true,
									periodDefaultData: [
										{
											name: 'products',
											title: 'Max products',
											value: '',
											type: 'number'
										},
										{
											name: 'orders',
											title: 'Max orders',
											value: '',
											type: 'number'
										},
										{
											name: 'show_slot_after_start',
											title: 'Show the slot after it starts',
											value: false,
											type: 'boolean'
										},
										{
											name: 'hide_slot_x_min_before_end',
											title: 'Hide the slot x minutes before end',
											value: 0,
											type: 'number'
										}
									],
									periodColors: [],
									periodTitle: '',
									periodBackgroundColor: 'rgba(82, 155, 255, 0.5)',
									periodBorderColor: '#2a3cff',
									periodTextColor: '#000',
									periodRemoveButton: 'Remove',
									periodDuplicateButton: 'Duplicate',
									periodTitlePlaceholder: 'Title',
									daysList: [
										'Sunday',
										'Monday',
										'Tuesday',
										'Wednesday',
										'Thursday',
										'Friday',
										'Saturday'
									],
									onInit: function () {},
									onAddPeriod: function (period, jqs) {

									},
									onUpdatePeriod: function (period, jqs) {

									},
									onDragPeriod: function (period, jqs) {

									},
									onResizePeriod: function (period, jqs) {

									},
									onAfterRemovePeriod: function (jqs) {

									},
									onDuplicatePeriod: function (period, jqs) {

									},
									onChangeMaxHour: function (jqs) {

									},
									onClickPeriod: function () {}
								});

								defaultScheduleWeeklyDiv.jqs('import', <?php echo $default_schedule_weekly_json; ?>);

							});
						</script>

						<script>
							jQuery(document).ready(function () {

								jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_weekly['option_name'] . '_ud' ) ?>]').on('change', function() {
									if (this.checked) {
										jQuery('#default_schedule_weekly').show();
										jQuery('#default_schedule_repeat_options').show();
										jQuery('#schedule_weekly').hide();
										jQuery('#schedule_repeat_options').hide();

									}
									else {
										jQuery('#schedule_weekly').show();
										jQuery('#schedule_repeat_options').show();
										jQuery('#default_schedule_weekly').hide();
										jQuery('#default_schedule_repeat_options').hide();
									}
								});

								jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_dates['option_name'] . '_ud' ) ?>]').on('change', function() {
									if (this.checked) {
										jQuery('#default_schedule_dates').show();
										jQuery('#schedule_dates').hide();
										jQuery('tr[data-rel=dates_type_choose]').hide();
									}
									else {
										jQuery('#schedule_dates').show();
										jQuery('#default_schedule_dates').hide();
										jQuery('tr[data-rel=dates_type_choose]').show();
									}
								});

								jQuery( document.body ).on( 'type_chosen_dates', function () {
									var chk = jQuery('input[type=checkbox][name=<?php echo esc_attr( $option_dates['option_name'] . '_ud' ) ?>]');
									if (chk[0].checked) {
										jQuery('tr[data-rel=dates_type_choose]').hide();
									}
									else {
										jQuery('tr[data-rel=dates_type_choose]').show();
									}
								} );
							});

						</script>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>

		</form>

		<?php


	}
}
