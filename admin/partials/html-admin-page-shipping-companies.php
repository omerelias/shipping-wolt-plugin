<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2 class="oc-woo-shipping-companies-heading">
	<?php _e( 'Shipping companies', 'ocws' ); ?>
	<a href="#" class="oc-woo-shipping-company-add page-title-action"><?php esc_html_e( 'Add shipping company', 'ocws' ); ?></a>
</h2>

<table class="oc-woo-shipping-companies widefat">
	<thead>
		<tr>
			<th class="oc-woo-shipping-company-name"><?php esc_html_e( 'Company name', 'ocws' ); ?></th>
			<th class="oc-woo-shipping-company-actions"><?php esc_html_e( 'Actions', 'ocws' ); ?></th>
		</tr>
	</thead>
	<tbody class="oc-woo-shipping-company-rows"></tbody>
	<tbody>

	</tbody>
</table>

<script type="text/html" id="tmpl-oc-woo-shipping-company-row-blank">

</script>

<script type="text/html" id="tmpl-oc-woo-shipping-company-row">
	<tr data-id="{{ data.company_id }}" data-name="{{ data.company_name }}">
		<td class="oc-woo-shipping-company-name">
			<input style="display: none;" name="company_name" value="{{ data.company_name }}" type="text">
			<span class="oc-woo-shipping-company-name">{{ data.company_name }}</span>
			<a href="#" class="oc-woo-shipping-company-edit"><?php _e( 'Edit', 'ocws' ); ?></a>
			<a href="#" style="display: none;" class="oc-woo-shipping-company-save"><?php _e( 'Save', 'ocws' ); ?></a>
		</td>
		<td class="oc-woo-shipping-company-actions">
			<div class="">
				 <a href="#" class="oc-woo-shipping-company-delete"><?php _e( 'Delete', 'ocws' ); ?></a>
			</div>
		</td>
	</tr>
</script>

<script type="text/template" id="tmpl-oc-woo-shipping-add-company">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add new company', 'ocws' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'ocws' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<div class="oc-woo-shipping-company-add-div">
							<p><?php esc_html_e( 'Type company name here', 'ocws' ); ?></p>
							<input name="new_company_name" type="text">
						</div>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add company', 'ocws' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
