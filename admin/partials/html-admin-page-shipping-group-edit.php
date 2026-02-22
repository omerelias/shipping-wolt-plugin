<?php
/**
 * Shipping group admin
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocws&tab=groups' ) ); ?>"><?php esc_html_e( 'Shipping groups', 'ocws' ); ?></a> &gt;
	<span class="oc-woo-shipping-group-name"><?php echo esc_html( $group->get_group_name() ? $group->get_group_name() : __( 'Group', 'ocws' ) ); ?></span>
</h2>

<table class="form-table oc-woo-shipping-group-settings">
	<tbody>
		<?php //if ( 0 !== $group->get_id() ) : ?>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="group_name">
						<?php esc_html_e( 'Group name', 'ocws' ); ?>
						<?php //echo wc_help_tip( __( 'This is the name of the group for your reference.', 'ocws' ) ); ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="group_name" name="group_name" id="group_name" value="<?php echo esc_attr( $group->get_group_name() ); ?>" placeholder="<?php esc_attr_e( 'Group name', 'ocws' ); ?>">
				</td>
			</tr>
			<tr valign="top" class="" style="display: none;">
				<th scope="row" class="titledesc">
					<label for="group_locations">
						<?php esc_html_e( 'Group locations', 'ocws' ); ?>
						<?php //echo wc_help_tip( __( 'These are locations inside this group. Customers will be matched against these locations.', 'ocws' ) ); ?>
					</label>
				</th>
				<td class="forminp">
					<select multiple="multiple" data-attribute="group_locations" id="group_locations" name="group_locations" data-placeholder="<?php esc_attr_e( 'Select locations within this group', 'ocws' ); ?>" class="oc-woo-shipping-group-location-select chosen_select">
						<?php
						foreach ( $allowed_cities as $code => $city ) {
							echo '<option value="city:' . esc_attr( $code ) . '"' . wc_selected( "city:$code", $locations ) . '>' . esc_html( $city ) . '</option>';
						}
						?>
					</select>
				</td>
			<?php //endif; ?>
		</tr>

		<tr valign="top" class="">
			<th scope="row" class="titledesc">
				<label>
					<?php esc_html_e( 'Shipping locations', 'ocws' ); ?>
					<?php echo wc_help_tip( __( 'These are locations inside this group. Customers will be matched against these locations.', 'ocws' ) ); ?>
				</label>
			</th>
			<td class="">
				<?php if (!ocws_use_google_cities_and_polygons()) { ?>
				<h3><a href="<?php echo esc_url(admin_url( 'admin.php?page=ocws&tab=group'. $group->get_id() . '&group-action=import' )) ?>"><?php esc_html_e( 'Import from CSV file', 'ocws' ); ?></a></h3>
				<?php } ?>
				<table class="oc-woo-shipping-group-locations widefat">
					<thead>
					<tr>
						<th class="ocws-shipping-group-location-sort"></th>
						<th class="ocws-shipping-group-location-type"></th>
						<th class="oc-woo-shipping-group-location-title"><?php esc_html_e( 'Title', 'ocws' ); ?></th>
						<th class="oc-woo-shipping-group-location-enabled"><?php esc_html_e( 'Enabled', 'ocws' ); ?></th>
                        <th class="oc-woo-shipping-group-location-enabled"><?php esc_html_e( 'Depending price', 'ocws' ); ?></th>
						<th class="oc-woo-shipping-group-location-description"><?php esc_html_e( 'Shipping price', 'ocws' ); ?></th>
						<th class="oc-woo-shipping-group-location-description"><?php esc_html_e( 'Minimum total', 'ocws' ); ?></th>
					</tr>
					</thead>
					<tfoot>
					<tr>
						<?php if (!ocws_use_google_cities_and_polygons()) { ?>
						<td colspan="6">
							<div>
								<label><?php echo esc_html(__('Add a city from the list')) ?></label>
								<button type="submit" class="button oc-woo-shipping-group-add-location" value="<?php esc_attr_e( 'Add location', 'ocws' ); ?>"><?php esc_html_e( 'Add location', 'ocws' ); ?></button>
							</div>
							<?php if (ocws_use_google_cities()) { ?>
								<div>
									<span><?php echo esc_html(__('OR', 'ocws')); ?></span>
								</div>
								<div>
									<label><?php echo esc_html(__('Add a city using Google Maps API')) ?></label>
									<input type="text" id="ocws-admin-pac-input" class="ocws-admin-pac-input" size="50" placeholder="<?php esc_attr_e( 'Start typing a city name', 'ocws' ); ?>">
									<button style="display: none;" type="submit" class="button oc-woo-shipping-group-add-gm-city" value="<?php esc_attr_e( 'Add city', 'ocws' ); ?>"><?php esc_html_e( 'Add city', 'ocws' ); ?></button>
								</div>
							<?php } ?>
							<div style="display: none;" id="restrict_pac_input_container">
								<input type="text" id="restrict_pac_input" class="ocws-admin-restrict-pac-input" size="50" placeholder="<?php esc_attr_e( 'Enter street name', 'ocws' ); ?>">
							</div>

						</td>
						<?php } else { ?>
						<td colspan="6">

							<input type="text" id="ocws-admin-pac-input" class="ocws-admin-pac-input" size="50" placeholder="<?php esc_attr_e( 'Start typing a place or city name', 'ocws' ); ?>">
							<button style="display: none;" type="submit" class="button oc-woo-shipping-group-add-polygon" value="<?php esc_attr_e( 'Add polygon', 'ocws' ); ?>"><?php esc_html_e( 'Add polygon', 'ocws' ); ?></button>
							<button style="display: none;" type="submit" class="button oc-woo-shipping-group-add-gm-city" value="<?php esc_attr_e( 'Add city', 'ocws' ); ?>"><?php esc_html_e( 'Add city', 'ocws' ); ?></button>

						</td>
						<?php } ?>
					</tr>
					</tfoot>
					<tbody class="oc-woo-shipping-group-locations-rows"></tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>

<p class="submit">
	<button type="submit" name="submit" id="submit" class="button button-primary button-large oc-woo-shipping-group-save" value="<?php esc_attr_e( 'Save changes', 'ocws' ); ?>" disabled><?php esc_html_e( 'Save changes', 'ocws' ); ?></button>
</p>

<script type="text/html" id="tmpl-oc-woo-shipping-group-locations-row-blank">
	<tr>
		<td class="oc-woo-shipping-group-location-blank-state" colspan="6">
			<p><?php esc_html_e( 'You can add multiple shipping locations within this group.', 'ocws' ); ?></p>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-oc-woo-shipping-group-locations-row">
	<tr data-id="{{ data.location_code }}" data-enabled="{{ data.is_enabled }}" data-shapes="{{ data.gm_shapes.gm_shapes }}" data-center="{{ data.gm_shapes.gm_center }}" data-zoom="{{ data.gm_shapes.gm_zoom }}" data-streets="">
		<td width="1%" class="ocws-shipping-group-location-sort"></td>
		<td width="1%" class="ocws-shipping-group-location-type">
			<span><img src="{{ data.location_type_icon }}"></span>
		</td>
		<td class="oc-woo-shipping-group-location-title">
			<a class="oc-woo-shipping-group-location-settings" href="admin.php?page=ocws&amp;tab=group{{ data.group_id }}&amp;location_code={{ data.location_code }}">{{{ data.location_name }}}</a>
			<div class="row-actions">
				<a class="oc-woo-shipping-group-location-settings" href="admin.php?page=ocws&amp;tab=group{{ data.group_id }}&amp;location_code={{ data.location_code }}"><?php esc_html_e( 'Edit', 'ocws' ); ?></a> |
				<a class="oc-woo-shipping-group-location-restrict" href="admin.php?page=ocws&amp;tab=group{{ data.group_id }}&amp;location_code={{ data.location_code }}"><?php esc_html_e( 'Restrict', 'ocws' ); ?></a> |
				<a href="#" class="oc-woo-shipping-group-location-delete"><?php esc_html_e( 'Delete', 'ocws' ); ?></a>
			</div>
		</td>
		<td width="1%" class="oc-woo-shipping-group-location-enabled"><a href="#">{{{ data.enabled_icon }}}</a></td>

        <td>
            <label>
                <select class="change_price_choose" style="width: 100%;">
                    <option {{{ data.options.price_depending.choose_select_fixed }}} value="off"><?php echo __('Fixed price', 'ocws'); ?></option>
                    <option {{{ data.options.price_depending.choose_select_depending }}} value="on"><?php echo __('Depending price', 'ocws'); ?></option>
                </select>
            </label>
        </td>

        <td colspan="2" data-id="{{ data.location_code }}_price" style="{{{ data.options.price_depending.display_price }}}">
            <label>
                <input type="checkbox"
                       {{{ data.options.price_depending.use_default_checked }}}
                       style="opacity: 0; visibility: hidden; width: 0; height: 0;"
                       class="price_depending_active"
                       value="1">
            </label>
            <input type="hidden"
                   name="{{ data.options.price_depending.option_name }}"
                   value="{{ data.options.price_depending.option_value }}">
            <table style="display: none" class="price_depending_rules">
                <thead>
                <tr>
                    <th>Cart value</th>
                    <th>Shipping price</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>

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
        </td>
		<td class="oc-woo-shipping-group-location-description" style="{{{ data.options.price_depending.display_fixed }}}">
			<div data-optname="shipping_price">
				<input type="text" name="{{ data.options.shipping_price.option_name }}"
					   class="location-option-input" data-default="{{ data.options.shipping_price.default }}"
					   value="{{ data.options.shipping_price.value }}" {{{ data.options.shipping_price.disabled }}} >
				<br />
				<label>
					<input type="checkbox" name="{{ data.options.shipping_price.option_name }}_ud"
						   class="location-use-default-switch" id="{{ data.options.shipping_price.option_name }}_ud"
						   value="1" {{{ data.options.shipping_price.use_default_checked }}}>
					<?php echo __('Use group price', 'ocws') ?>
				</label>
			</div>
		</td>
		<td class="oc-woo-shipping-group-location-description" style="{{{ data.options.price_depending.display_fixed }}}">
			<div data-optname="min_total">
				<input type="text" name="{{ data.options.min_total.option_name }}"
					   class="location-option-input" data-default="{{ data.options.min_total.default }}"
					   value="{{ data.options.min_total.value }}" {{{ data.options.min_total.disabled }}} >
				<br />
				<label>
					<input type="checkbox" name="{{ data.options.min_total.option_name }}_ud"
						   class="location-use-default-switch" id="{{ data.options.min_total.option_name }}_ud"
						   value="1" {{{ data.options.min_total.use_default_checked }}}>
					<?php echo __('Use group minimum', 'ocws') ?>
				</label>
			</div>
		</td>
	</tr>
    <!--<tr data-id="{{ data.location_code }}_price" data-type="price_depending">
        <td colspan="6">
            <label>
                <input type="checkbox"
                       {{{ data.options.price_depending.use_default_checked }}}
                       class="price_depending_active"
                       value="1">
                <?php /*echo __('Use price depending', 'ocws') */?>
            </label>
            <input type="hidden"
                   name="{{ data.options.price_depending.option_name }}"
                   value="{{ data.options.price_depending.option_value }}">
            <table style="display: none" class="price_depending_rules">
                <thead>
                <tr>
                    <th>Cart value</th>
                    <th>Shipping price</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>

                </tbody>
                <tfoot>
                <tr>
                    <td>
                        <button type="button" class="button price_depending_add"><?php /*esc_html_e( 'Add', 'ocws' ); */?></button>
                    </td>
                    <td></td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </td>
    </tr>-->
	<tr data-id="{{ data.location_code }}_restrict" data-locationcode="{{ data.location_code }}" style="display: none;">
		<td colspan="6">
			<div>
				<button type="submit" class="button ocws-admin-restrict-cancel-button" value="<?php esc_attr_e( 'Cancel', 'ocws' ); ?>"><?php esc_html_e( 'Cancel', 'ocws' ); ?></button>
			</div>

			<ul class="streets-list" style="max-height: 150px; overflow-x: hidden; overflow-y: auto;">
				{{{ data.render_streets }}}
			</ul>
			<?php if ($maps_api_key = ocws_get_google_maps_api_key()) { ?>
				<!-- <input type="text" id="restrict_pac_input" class="ocws-admin-restrict-pac-input" size="50" placeholder="<?php esc_attr_e( 'Enter street name', 'ocws' ); ?>"> -->
				<div>
					<button type="submit" class="button ocws-admin-restrict-add-street-button" value="<?php esc_attr_e( 'Add street', 'ocws' ); ?>"><?php esc_html_e( 'Add street', 'ocws' ); ?></button>
				</div>
			<?php } else { ?>
				<div>
					<?php esc_html_e( 'Please, consider adding Google Maps API key in common settings page', 'ocws' ); ?>
					<a href="<?php echo admin_url( 'admin.php?page=ocws&tab=common-settings' ); ?>" class=""><?php esc_html_e( 'Common Settings', 'ocws' ); ?></a>
				</div>
			<?php } ?>

			<div style="margin-top: 10px;">
				<button disabled type="submit" class="button ocws-admin-restrict-save-button" value="<?php esc_attr_e( 'Save street list changes', 'ocws' ); ?>"><?php esc_html_e( 'Save street list changes', 'ocws' ); ?></button>
			</div>
		</td>
	</tr>
</script>

<script type="text/template" id="tmpl-ocws-modal-shipping-location-settings">
	<div class="wc-backbone-modal wc-backbone-modal-shipping-location-settings">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1>
						<?php
						printf(
							esc_html__( '%s Settings', 'woocommerce' ),
							'{{{ data.location.location_title }}}'
						);
						?>
					</h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'ocws' ); ?></span>
					</button>
				</header>
				<article class="ocws-modal-shipping-location-settings">
					<form action="" method="post">
						{{{ data.location.settings_html }}}
						<input type="hidden" name="location_code" value="{{{ data.location_code }}}" />
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'ocws' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>

<script type="text/template" id="tmpl-ocws-modal-add-shipping-location">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add shipping location', 'ocws' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'ocws' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<div class="oc-woo-shipping-group-location-selector">
							<p><?php esc_html_e( 'Choose the shipping location you wish to add.', 'ocws' ); ?></p>

							<select name="add_location_code" class="city-select">
								<option value=""></option>
								<?php
								foreach ( $allowed_cities as $code => $city ) {
									echo '<option value="city:' . esc_attr( $code ) . '"' . ocws_disabled( "city:$code", $locations ) . '>' . esc_html( $city ) . '</option>';
								}
								?>
							</select>
						</div>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add location', 'ocws' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>

<script type="text/template" id="tmpl-ocws-modal-add-shipping-location-polygon">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add polygon', 'ocws' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'ocws' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<input type="hidden" name="gm_center" id="gm_center" value="">
						<input type="hidden" name="gm_zoom" id="gm_zoom" value="">
						<input type="hidden" name="gm_shapes" id="gm_shapes" value="">
						<input type="text" name="polygon_name" id="polygon_name" value="{{ data.polygon_name }}">
					</form>
					<div id="modalMap" style="height: 100%;"></div>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add polygon', 'ocws' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>

<script type="text/template" id="tmpl-ocws-modal-edit-shipping-location-polygon">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Edit polygon', 'ocws' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'ocws' ); ?></span>
					</button>
					<form action="" method="post">
						<input type="hidden" name="gm_center" id="gm_center" value="{{ data.center }}">
						<input type="hidden" name="gm_zoom" id="gm_zoom" value="{{ data.zoom }}">
						<input type="hidden" name="gm_shapes" id="gm_shapes_1" value="{{ data.shapes }}">
						<label><?php esc_html_e( 'Polygon name', 'ocws' ); ?></label>
						<input type="text" name="polygon_name" id="polygon_name_1" value="{{ data.polygon_name }}">
						<input type="hidden" name="location_code" id="location_code_1" value="{{ data.location_code }}">
					</form>
				</header>
				<article>

					<div id="modalMap" style="height: 100%;"></div>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Update polygon', 'ocws' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>