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
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocws-lp&tab=affiliates' ) ); ?>"><?php esc_html_e( 'Local pickup branches', 'ocws' ); ?></a> &gt;
	<span class="ocws-lp-affiliate-name"><?php echo esc_html( $affiliate->get_aff_name() ? $affiliate->get_aff_name() : __( 'Branch', 'ocws' ) ); ?></span>
</h2>

<table class="form-table ocws-lp-affiliate-settings">
	<tbody>

			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="aff_name">
						<?php esc_html_e( 'Branch name', 'ocws' ); ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="aff_name" name="aff_name" id="aff_name" value="<?php echo esc_attr( $affiliate->get_aff_name() ); ?>" placeholder="<?php esc_attr_e( 'Branch name', 'ocws' ); ?>">
				</td>
			</tr>

			<tr valign="top" class="" style="display: none">
				<th scope="row" class="titledesc">
					<label for="aff_address">
						<?php esc_html_e( 'Branch address', 'ocws' ); ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="aff_address" name="aff_address" id="aff_address" value="<?php echo esc_attr( $affiliate->get_aff_address() ); ?>" placeholder="<?php esc_attr_e( 'Branch address', 'ocws' ); ?>">
				</td>
			</tr>

			<tr valign="top" class="" style="display: none">
				<th scope="row" class="titledesc">
					<label for="aff_descr">
						<?php esc_html_e( 'Branch description', 'ocws' ); ?>
					</label>
				</th>
				<td class="forminp">
					<textarea type="text" data-attribute="aff_descr" name="aff_descr" id="aff_descr" placeholder="<?php esc_attr_e( 'Branch description', 'ocws' ); ?>">
						<?php echo esc_attr( $affiliate->get_aff_descr() ); ?>
					</textarea>
				</td>
			</tr>

	</tbody>
</table>

<p class="submit">
	<button type="submit" name="submit" id="submit" class="button button-primary button-large ocws-lp-affiliate-save" value="<?php esc_attr_e( 'Save changes', 'ocws' ); ?>" disabled><?php esc_html_e( 'Save changes', 'ocws' ); ?></button>
</p>







