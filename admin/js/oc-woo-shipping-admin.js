(function( $, ajaxurl ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$(document).ready(function() {
		$('#group_options_form .use-default-switch').on('change', function() {
			var inputName = $(this).data('rel');
			var input = $('#group_options_form input[name=' + inputName + ']');
			if (input.length) {
				if (this.checked) {
					var defaultValue = $(input).data('default-value');
					$(input).val(defaultValue);
					$(input).prop('disabled', true);
				}
				else {
					$(input).val('');
					$(input).prop('disabled', false);
				}
			}
		});

		$('#aff_options_form .use-default-switch').on('change', function() {
			var inputName = $(this).data('rel');
			var input = $('#aff_options_form input[name=' + inputName + ']');
			if (input.length) {
				if (this.checked) {
					var defaultValue = $(input).data('default-value');
					$(input).val(defaultValue);
					$(input).prop('disabled', true);
				}
				else {
					$(input).val('');
					$(input).prop('disabled', false);
				}
			}
		});

		$('.timepicker').timepicker({
			timeFormat: 'HH:mm',
			interval: 60,
			minTime: '07:00',
			maxTime: '23:00',
			//defaultTime: '18:00',
			startTime: '07:00',
			dynamic: false,
			dropdown: true,
			scrollbar: true
		});



		$( '.tips, .help_tip, .woocommerce-help-tip' ).tipTip( {
			'attribute': 'data-tip',
			'fadeIn': 50,
			'fadeOut': 50,
			'delay': 200
		} );

		$( document.body ).on( 'wc_backbone_modal_loaded', function() {

			var suggUrl = ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_admin_add_city_suggestions';
			$( '.city-select' ).filter( ':not(.enhanced)' ).each( function() {
				var select2_args = {
					minimumInputLength: 2,
					minimumResultsForSearch: -1,
					ajax: {
						url: function(params) {
							return suggUrl + '&trm=' + params.term;
						},
						dataType: 'json',
						processResults: function (data) {
							// Transforms the top-level key of the response object from 'items' to 'results'
							return {
								results: data.data.items
							};
						}
					}
				};
				$( this ).select2( select2_args ).addClass( 'enhanced' );
			});
		} );


	});


})( jQuery, ajaxurl );
