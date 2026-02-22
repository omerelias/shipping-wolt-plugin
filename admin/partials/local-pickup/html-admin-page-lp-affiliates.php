<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2 class="ocws-lp-affiliates-heading">
	<?php _e( 'Local pickup branches', 'ocws' ); ?>
	<a href="<?php echo admin_url( 'admin.php?page=ocws-lp&tab=affiliates&aff_id=new' ); ?>" class="ocws-lp-affiliate-add page-title-action"><?php esc_html_e( 'Add branch', 'ocws' ); ?></a>
</h2>
<p><?php //echo __( '', 'ocws' ); ?></p>
<table class="ocws-lp-affiliates widefat">
	<thead>
		<tr>
			<th class="ocws-lp-affiliate-sort"><?php echo wc_help_tip( __( 'Drag and drop to re-order your branches. This is the order in which they will be displayed in a reports.', 'ocws' ) ); ?></th>
			<th class="ocws-lp-affiliate-name"><?php esc_html_e( 'Branch name', 'ocws' ); ?></th>
			<th class="ocws-lp-affiliate-enabled"><?php esc_html_e( 'Enabled', 'ocws' ); ?></th>
			<!--<th class="ocws-lp-affiliate-address"><?php /*esc_html_e( 'Address', 'ocws' ); */?></th>-->
		</tr>
	</thead>
	<tbody class="ocws-lp-affiliate-rows"></tbody>
	<tbody>

	</tbody>
</table>

<script type="text/html" id="tmpl-ocws-lp-affiliate-row-blank">

</script>

<script type="text/html" id="tmpl-ocws-lp-affiliate-row">
	<tr data-id="{{ data.aff_id }}" data-enabled="{{ data.is_enabled }}">
		<td width="1%" class="ocws-lp-affiliate-sort"></td>
		<td class="ocws-lp-affiliate-name">
			<a href="admin.php?page=ocws-lp&amp;tab=affiliate{{ data.aff_id }}">{{ data.aff_name }}</a>
			<div class="row-actions">
				<a href="admin.php?page=ocws-lp&amp;tab=affiliate{{ data.aff_id }}"><?php _e( 'Edit', 'ocws' ); ?></a> | <a href="#" class="ocws-lp-affiliate-delete"><?php _e( 'Delete', 'ocws' ); ?></a>
			</div>
		</td>
		<td width="1%" class="ocws-lp-affiliate-enabled"><a href="#">{{{ data.enabled_icon }}}</a></td>
		<!--<td class="ocws-lp-affiliate-address">
			{{ data.aff_address }}
		</td>-->
	</tr>
</script>

<script type="text/template" id="tmpl-ocws-lp-affiliate-add-affiliate">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add new branch', 'ocws' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'ocws' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<div class="ocws-lp-affiliate-add-div">
							<p><?php esc_html_e( 'Type branch name here', 'ocws' ); ?></p>
							<input name="new_aff_name" type="text">
						</div>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add branch', 'ocws' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
