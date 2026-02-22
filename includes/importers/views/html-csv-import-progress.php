<?php
/**
 * Admin View: Importer - CSV import progress
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wc-progress-form-content woocommerce-importer woocommerce-importer__importing">
	<header>
		<span class="spinner is-active"></span>
		<h2><?php esc_html_e( 'Importing', 'ocws' ); ?></h2>
		<p><?php esc_html_e( 'Your cities are now being imported...', 'ocws' ); ?></p>
	</header>
	<section>
		<progress class="woocommerce-importer-progress" max="100" value="0"></progress>
	</section>
</div>
