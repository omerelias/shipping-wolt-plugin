(function( $, ocws_lp ) {
	'use strict';

	$(function() {

		$( document.body ).on('change', '.select[name="ocws_lp_pickup_aff_id"]', function(event) {

			event.preventDefault();
			$( document.body ).trigger( 'update_checkout' );
		});

		$( document.body ).on( 'updated_checkout', function() {
			$('#oc-woo-pickup-additional').unblock();
		});

		$( document.body ).on( 'update_checkout', function() {
			$('#oc-woo-pickup-additional').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		});

	});

})( jQuery, ocws_lp );
