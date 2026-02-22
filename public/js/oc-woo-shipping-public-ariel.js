(function( $, ocws ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
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
	$(function() {

		$( document.body ).on('click', '.slot-list-container a.slot', function(event) {

			event.preventDefault();
			var data = {};
			data.date = $(this).data('date');
			data.slot_start = $(this).data('slot-start');
			data.slot_end = $(this).data('slot-end');

			var btnShowMore = $('#slot-list-button-show-all');
			var btnShowLess = $('#slot-list-button-show-less');
			if (btnShowMore.length && btnShowLess.length) {
				if (btnShowMore.css('display') != 'none') {
					data.state = 'less';
				}
				else if (btnShowLess.css('display') != 'none') {
					data.state = 'more';
				}
			}

			var self = $(this);

			$('#oc-woo-shipping-additional').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.post( ocws.ajaxurl + ( ocws.ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_checkout_save_shipping_info', {
				ocws_shipping_info: data
			}, function (response, textStatus) {
				if (response.data.fragment) {
					$('#oc-woo-shipping-additional').replaceWith(response.data.fragment);
				}
				$('#oc-woo-shipping-additional').unblock();
				//$( document.body ).trigger( 'update_checkout' );
			}, 'json' );
		});

		$( document.body ).on('click', '#slot-list-button-show-all', function (event) {
			$('.slot-list-container .day-data-hidden').show();
			$(this).hide();
			$('#slot-list-button-show-less').show();
		});

		$( document.body ).on('click', '#slot-list-button-show-less', function (event) {
			$('.slot-list-container .day-data-hidden').hide();
			$(this).hide();
			$('#slot-list-button-show-all').show();
		});

		$( document.body ).on('change', '.ocws-enhanced-select[name="billing_city"], .ocws-enhanced-select[name="shipping_city"]', function(event) {

			event.preventDefault();
			//$('#oc-woo-shipping-additional .slot').hide();
			$('.woocommerce-billing-fields').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$( document.body ).trigger( 'update_checkout' );
		});

		$( document.body ).on('change', '.ocws-enhanced-select[name="other_city"]', function(event) {

			event.preventDefault();
			$('.ocws-enhanced-select[name="billing_city"]').val(this.value);
			$('.woocommerce-billing-fields').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$( document.body ).trigger( 'update_checkout' );
		});

		$( document.body ).on('change', 'input[name="ocws_other_recipient"]', function(event) {

			event.preventDefault();

			$('input[name="ocws_other_recipient_hidden"]').val($(this).prop('checked')? 'yes' : 'no');

			$('.woocommerce-billing-fields').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$( document.body ).trigger( 'update_checkout' );
		});

		$( document.body ).on( 'updated_checkout', function() {
			$('#oc-woo-shipping-additional').show();
			$('.woocommerce-billing-fields').unblock();
			jQuery( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
				var select2_args = { minimumResultsForSearch: 5 };
				jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
			});
		});

		$( document.body ).on( 'update_checkout', function() {
			$('#oc-woo-shipping-additional').hide();
		});

		$( document.body ).on( 'click', '.show-shipping-location-button', function(e) {
			e.preventDefault();
			$('.show-shipping-location').show();
		});


		// TODO: find more common way to figure out if cart is empty
		$( document.body ).on( 'adding_to_cart', function() {
			// ArielChange
			if (parseInt($(".header-menu-left .site-header-cart .cart-contents .count").text()) == 0) {
				$('.choose-shipping-popup').addClass('shown');
			}
		});

		$( document.body ).on( 'orak_adding_to_cart', function() {
			// ArielChange
			if (parseInt($(".header-menu-left .site-header-cart .cart-contents .count").text()) == 0) {
				$('.choose-shipping-popup').addClass('shown');
			}
		});

		$(document).on('submit', '#choose-shipping' , function(e){
			e.stopPropagation();
			e.preventDefault();

			var form = $(this);

			var delivery_option = $(this).find('input[data-title="משלוח"]');
			var city_option = $(this).find('select[name="selected-city"] option:selected').val();

			if($(delivery_option).is(':checked') && city_option == 'בחר את אזור החלוקה שלך'){
				$('#choose-shipping').find('select[name="selected-city"]').addClass('invalid');
				$('#form-messages').html('<span class="error">יש לבחור את אזור חלוקת המשלוח</span>');
				return;
			}

			if(!$("input[name='popup-shipping-method']:checked").val()){
				$('#form-messages').html('<span class="error">יש לבחור אפשרות משלוח</span>');
				return;
			}

			var formData = $(form).serialize();

			$.ajax({
				method: "POST",
				url: ocws.ajaxurl,
				data: {action: "oc_woo_shipping_set_shipping_city", formData: formData},
				beforeSend: function() {
					$('#form-messages').html('<span class="loading">מעבד...</span>');
				},
				success: function(response) {
					console.log(response);
					$('#form-messages').html('');
					$('.choose-shipping-popup').removeClass('shown');

				}
			});

		});

		$('#choose-shipping').find('select[name="selected-city"]').on('change', function(){
			if($(this).val() != 'בחר את אזור החלוקה שלך') {
				$(this).removeClass('invalid');
			} else {
				$(this).addClass('invalid');
			}
		});

		$('#choose-shipping').find('input[name="popup-shipping-method"]').on('change', function(){
			if($(this).val().substr(0, 12) != 'local_pickup') {
				$('#popup-shipping-options').css('display', 'block');
			} else {
				$('#popup-shipping-options').css('display', 'none');
			}
		});

	});

})( jQuery, ocws );
