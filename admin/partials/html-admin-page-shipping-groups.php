<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2 class="oc-woo-shipping-groups-heading">
	<?php _e( 'Shipping groups', 'ocws' ); ?>
	<a href="<?php echo admin_url( 'admin.php?page=ocws&tab=groups&group_id=new' ); ?>" class="oc-woo-shipping-group-add page-title-action"><?php esc_html_e( 'Add shipping group', 'ocws' ); ?></a>
</h2>
<p><?php echo __( 'A shipping group is a set of locations where a certain set of shipping options are offered.', 'ocws' ); ?></p>
<table class="oc-woo-shipping-groups widefat">
	<thead>
		<tr>
			<th class="oc-woo-shipping-group-sort"><?php echo wc_help_tip( __( 'Drag and drop to re-order your custom groups. This is the order in which they will be displayed in a reports.', 'ocws' ) ); ?></th>
			<th class="oc-woo-shipping-group-name"><?php esc_html_e( 'Group name', 'ocws' ); ?></th>
			<th class="oc-woo-shipping-group-enabled"><?php esc_html_e( 'Enabled', 'ocws' ); ?></th>
			<th class="oc-woo-shipping-group-locations"><?php esc_html_e( 'Location(s)', 'ocws' ); ?></th>
		</tr>
	</thead>
	<tbody class="oc-woo-shipping-group-rows"></tbody>
	<tbody>

	</tbody>
</table>

<script type="text/html" id="tmpl-oc-woo-shipping-group-row-blank">

</script>

<script type="text/html" id="tmpl-oc-woo-shipping-group-row">
	<tr data-id="{{ data.group_id }}" data-enabled="{{ data.is_enabled }}">
		<td width="1%" class="oc-woo-shipping-group-sort"></td>
		<td class="oc-woo-shipping-group-name">
			<a href="admin.php?page=ocws&amp;tab=group{{ data.group_id }}">{{ data.group_name }}</a>
			<div class="row-actions">
				<a href="admin.php?page=ocws&amp;tab=group{{ data.group_id }}"><?php _e( 'Edit', 'ocws' ); ?></a> | <a href="#" class="oc-woo-shipping-group-delete"><?php _e( 'Delete', 'ocws' ); ?></a>
			</div>
		</td>
		<td width="1%" class="oc-woo-shipping-group-enabled"><a href="#">{{{ data.enabled_icon }}}</a></td>
		<td class="oc-woo-shipping-group-locations">
			{{ data.formatted_group_location }}
		</td>
	</tr>
</script>

<script type="text/template" id="tmpl-oc-woo-shipping-add-group">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add new group', 'ocws' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'ocws' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<div class="oc-woo-shipping-group-add-div">
							<p><?php esc_html_e( 'Type group name here', 'ocws' ); ?></p>
							<input name="new_group_name" type="text">
						</div>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add group', 'ocws' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
