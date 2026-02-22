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

		$( document.body ).on('click', 'form.checkout .slot-list-container a.slot', function(event) {

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

			var shippingParent = $(this).closest('#oc-woo-shipping-additional');
			var pickupParent = $(this).closest('#oc-woo-pickup-additional');

			if (shippingParent.length) {

				$('input[name="order_expedition_date"]').val(data.date);
				$('input[name="order_expedition_slot_start"]').val(data.slot_start);
				$('input[name="order_expedition_slot_end"]').val(data.slot_end);
				$('input[name="slots_state"]').val(data.state);

				$('#oc-woo-shipping-additional').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$( document.body ).trigger( 'update_checkout' );
			}
			else if (pickupParent.length) {

				$('input[name="ocws_lp_pickup_date"]').val(data.date);
				$('input[name="ocws_lp_pickup_slot_start"]').val(data.slot_start);
				$('input[name="ocws_lp_pickup_slot_end"]').val(data.slot_end);
				$('input[name="slots_state"]').val(data.state);

				$('#oc-woo-pickup-additional').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$( document.body ).trigger( 'update_checkout' );
			}

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

		$( document.body ).on('focusout', '.ocws_update_checkout_on_change', function(event) {

			//event.preventDefault();
			//$( document.body ).trigger( 'update_checkout' );
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
			initCheckoutSliders();
			$('.woocommerce-billing-fields').unblock();

			$( ':input.ocws-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
				var select2_args = { minimumResultsForSearch: 5 };
				$( this ).select2( select2_args ).addClass( 'enhanced' );
			});

			$( ':input.ocws-enhanced-select-ajax-streets' ).filter( ':not(.enhanced)' ).each( function() {

				var elem = $(this);

				var language = {
					errorLoading: function () {
						return ocws.localize.select2.errorLoading;
					},
					inputTooLong: function (args) {
						return ocws.localize.select2.inputTooLong;
					},
					inputTooShort: function (args) {
						return ocws.localize.select2.inputTooShort;
					},
					loadingMore: function () {
						return ocws.localize.select2.loadingMore;
					},
					noResults: function () {
						return ocws.localize.select2.noResults;
					},
					searching: function () {
						return ocws.localize.select2.searching;
					}
				};

				var select2_args = {
					//minimumResultsForSearch: 5,
					//multiple: true,
					//maximumSelectionSize: 1,
					language: language,
					ajax: {
						url: ocws.ajaxurl,
						dataType: 'json',
						delay: 150,
						data: function (data) {

							var city_code = '';
							if (elem.attr('name') == 'billing_address_1') {
								city_code = $('#billing_city').val();
							}
							else if (elem.attr('name') == 'shipping_address_1') {
								city_code = $('#shipping_city').val();
							}

							return {
								search_term: data.term, // search term
								action: "oc_woo_shipping_get_streets",
								city_code: city_code
							};
						},
						processResults: function (response) {
							return {
								results:response.data.results
							};
						},
						cache: false
					}
				};
				$( this ).select2( select2_args ).addClass( 'enhanced' );
			});
		});

		$( ':input.ocws-enhanced-select-ajax-streets' ).filter( ':not(.enhanced)' ).each( function() {

			var elem = $(this);

			var select2_args = {
				//minimumResultsForSearch: Infinity,
				//multiple: true,
				//maximumSelectionSize: 1,
				ajax: {
					url: ocws.ajaxurl,
					dataType: 'json',
					delay: 150,
					data: function (data) {

						var city_code = '';
						if (elem.attr('name') == 'billing_address_1') {
							city_code = $('#billing_city').val();
						}
						else if (elem.attr('name') == 'shipping_address_1') {
							city_code = $('#shipping_city').val();
						}

						return {
							search_term: data.term, // search term
							action: "oc_woo_shipping_get_streets",
							city_code: city_code
						};
					},
					processResults: function (response) {
						return {
							results: response.data.results
						};
					},
					cache: false
				}
			};
			$( this ).select2( select2_args ).addClass( 'enhanced' );
		});

		$( document.body ).on('click', 'form.checkout .slot-list-container .ocws-days-list-slider .day-data', function(event) {

			event.preventDefault();

			var dataId = $(this).data('id');

			var form = $(this).closest('form.checkout');

			if (form.length) {

				form.find('.ocws-days-with-slots-list .day-data').removeClass('active');
				form.find('.ocws-days-with-slots-list .day-data').css('display', 'none');

				var daySlots = form.find('.ocws-days-with-slots-list .day-data[data-rel-id="'+dataId+'"]');
				daySlots.css('display', '');
				if (daySlots.length) {
					form.find('.ocws-days-with-slots-list-label').css('display', '');
				}
				else {
					form.find('.ocws-days-with-slots-list-label').css('display', 'none');
				}
			}

			$('form.checkout .slot-list-container .ocws-days-list-slider .day-data').removeClass('active');
			$(this).addClass('active');

		});

		$( document.body ).on('click', 'form.checkout .slot-list-container .ocws-days-with-slots-list .day-data', function(event) {

			event.preventDefault();

			var form = $(this).closest('form.checkout');

			if (form.length) {

				form.find('.ocws-days-with-slots-list .day-data').removeClass('active');

			}

			$(this).addClass('active');

		});
		
		// setTimeout(function(){
		// }, 1000);
		// console.clear();

		$( document.body ).on( 'update_checkout', function(response) {
			$('#oc-woo-shipping-additional').hide();
		});

		// In case cart total under shipping required sum - it shows message, in this case if selected advanced shipping method > show checkout popup with additional buttons 
		$( document.body ).on( 'updated_checkout', function(response) {
			let chosenShipping 	= $('#shipping_method input:checked').val();
			let notice 			= $('.show-shipping-block .important-notice');
			let noticeHtml 		= notice.html();
			let noticeMessage  = 'המינימום למשלוח עד הבית';
	
			// console.group( 'UPDATED CHECKOUT| without check notice' );
			// console.log( notice, 'notice' );
			// console.log( noticeHtml, 'noticeHtml' );
			// console.log( chosenShipping, 'checked radiobutton value' );
			// console.groupEnd();
			$('.ocws-checkout-choose-city-popup .inner-wrapper').unblock();
			
			if ( typeof chosenShipping !== 'undefined' && chosenShipping.indexOf( 'oc_woo_advanced_shipping_method' ) != -1 ){
				if (  notice.length && noticeHtml.indexOf( noticeMessage ) != -1  ){
					let message  		=  $('#oc-woo-shipping-additional--message');
					let messagePopup 	= message.html();
					if ( message.length ){  
						$('.ocws-checkout-choose-city-popup .ajax-message').html( messagePopup );
						$('.ocws-checkout-choose-city-popup').addClass('shown');
						$('#checkout-popup-submit-btn').prop( 'disabled', false )
						$('.ocws-checkout-choose-city-popup').removeClass('active-city-form');
						$('.ocws-checkout-choose-city-popup').addClass('hide-cross');
					}
				}
				if ( !notice.length ) {
					$('.ocws-checkout-choose-city-popup').removeClass('shown');
					$('.ocws-checkout-choose-city-popup').removeClass('hide-cross');

				}
			}
		});


		$( document.body ).on( 'click', '.show-shipping-location-button', function(e) {
			e.preventDefault();
			$('.show-shipping-location').show();
		});

		$( document.body ).on( 'click', 'input.ocws-disabled-shipping-method-input, .show-shipping-location-button-polygon', function(e) {

			e.preventDefault();
			$('.ocws-checkout-choose-city-popup #form-messages').html('');
			$('.ocws-checkout-choose-city-popup').addClass('shown');

		});

		$( document.body ).on( 'submit', '#ocws-checkout-choose-city-form', function(e) {
			e.preventDefault();
			var selectedCity = $(this).find('select[name="selected-city"]').val();
			// console.log( $('.ocws-checkout-choose-city-popup .inner-wrapper'), "CHECKOUT popup !!!" );
			// console.log( selectedCity, "selectedCity" );

			if ( typeof selectedCity === null || selectedCity === null  ){
				alert('בחירת עיר/ישוב');
				return
			}

			// $('.ocws-checkout-choose-city-popup').removeClass('shown');
			$('.ocws-checkout-choose-city-popup .inner-wrapper').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			if (selectedCity) {
				var otherCity = $('.ocws-enhanced-select[name="other_city"]');
				if (otherCity.length) {
					otherCity.val(selectedCity);
					otherCity.trigger('change');
				} else {

					$('.ocws-enhanced-select[name="billing_city"]').val(selectedCity);
					$('.woocommerce-billing-fields').block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					$( document.body ).trigger( 'update_checkout' );
				}

			}
			else {
				var autocompleteInput = $(this).find('input[name="billing_google_autocomplete"]');
				if (autocompleteInput.length) {
					var billingCity = $(this).find('input[name="billing_city"]').val();
					var billingCityName = $(this).find('input[name="billing_city_name"]').val();
					var billingCityCode = $(this).find('input[name="billing_city_code"]').val();
					var billingAddress = $(this).find('input[name="billing_street"]').val();
					var billingHouseNum = $(this).find('input[name="billing_house_num"]').val();
					var billingAddressCoords = $(this).find('input[name="billing_address_coords"]').val();

					$('form.checkout input[name="billing_google_autocomplete"]').val(autocompleteInput.val());
					$('form.checkout input[name="billing_street"]').val(billingAddress);
					$('form.checkout input[name="billing_city"]').val(billingCityName);
					$('form.checkout input[name="billing_city_code"]').val(billingCityCode);
					$('form.checkout input[name="billing_city_name"]').val(billingCityName);
					$('form.checkout input[name="billing_house_num"]').val(billingHouseNum);
					$('form.checkout input[name="billing_address_coords"]').val(billingAddressCoords);

					$('.woocommerce-billing-fields').block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					autocompleteInput.val('');
					$( document.body ).trigger( 'update_checkout' );

				}
			}
			return false;

		});


		// TODO: find more common way to figure out if cart is empty
		$( document.body ).on( 'adding_to_cart', function() {
			if (
				//parseInt($('.site-header .cart-contents .count').text() == 0) ||
				//parseInt($(".header-menu-left .site-header-cart .cart-contents .count").text()) == 0 ||
				!readCookie('popupdisplayed')
				//$('.woocommerce-cart-form ul li').length == 0
			) {
				$('.choose-shipping-popup').addClass('shown');
				jQuery('body').css({ overflow: 'hidden' });
				addCookie();
			}
		});

		$( document.body ).on( 'orak_adding_to_cart', function() {
			if (
				//parseInt($('.site-header .cart-contents .count').text() == 0) ||
				//parseInt($(".header-menu-left .site-header-cart .cart-contents .count").text()) == 0 ||
				!readCookie('popupdisplayed')
				//$('.woocommerce-cart-form ul li').length == 0
			) {
				$('.choose-shipping-popup').addClass('shown');
				jQuery('body').css({ overflow: 'hidden' });
				addCookie();
			}
		});

		function addCookie() {
			var now = new Date();
			var time = now.getTime();
			time += 24 * 3600 * 1000;
			now.setTime(time);
			document.cookie =
				'popupdisplayed=' + '1' +
				'; expires=' + now.toUTCString() +
				'; path=/';
		}

		function readCookie(name) {
			var nameEQ = name + "=";
			var ca = document.cookie.split(';');
			for(var i=0;i < ca.length;i++) {
				var c = ca[i];
				while (c.charAt(0)==' ') c = c.substring(1,c.length);
				if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
			}
			return null;
		}

		$(document).on('submit', '#choose-shipping' , function(e) {
			e.stopPropagation();
			e.preventDefault();

			if (fetchingSlots) return;

			var form = $(this);

			var delivery_option = $(this).find('input[id^="oc_woo_advanced_shipping_method"]');
			var pickup_option = $(this).find('input[id^="oc_woo_local_pickup_method"]');

			if ($(delivery_option).is(':checked')) {

				var city_option = $(this).find('select[name="selected-city"] option:selected').val();

				if(city_option == '') {
					$('#choose-shipping').find('select[name="selected-city"]').addClass('invalid');
					$('#popup-shipping-form-messages').html('<span class="error">יש לבחור נקודת איסוף</span>');
					return;
				}

				var formData = $(form).serialize();

				$.ajax({
					method: "POST",
					url: ocws.ajaxurl,
					data: {action: "oc_woo_shipping_set_shipping_city", formData: formData},
					beforeSend: function() {
						$('#popup-shipping-form-messages').html('<span class="loading">'+ocws.localize.loading+'...</span>');
					},
					success: function(response) {
						console.log(response);
						$('#popup-shipping-form-messages').html('');
						$('.choose-shipping-popup').removeClass('shown');
						jQuery('body').css({ overflow: 'auto' });
						if (response && response.data && response.data.refresh_page) {
							location.reload();
						}
					}
				});
			}
			else if ($(pickup_option).is(':checked')) {

				var aff_option = $(this).find('select[name="ocws_lp_pickup_aff_id"] option:selected').val();

				if(aff_option == '') {
					$('#choose-shipping').find('select[name="aff_option"]').addClass('invalid');
					$('#popup-pickup-form-messages').html('<span class="error">יש לבחור את אזור חלוקת המשלוח</span>');
					return;
				}

				var formData = $(form).serialize();

				$.ajax({
					method: "POST",
					url: ocws.ajaxurl,
					data: {action: "oc_woo_shipping_set_pickup_branch", formData: formData},
					beforeSend: function() {
						$('#popup-pickup-form-messages').html('<span class="loading">'+ocws.localize.loading+'...</span>');
					},
					success: function(response) {
						console.log(response);
						$('#popup-pickup-form-messages').html('');
						$('.choose-shipping-popup').removeClass('shown');
						jQuery('body').css({ overflow: 'auto' });
					}
				});
			}
			else if(!$("input[name='popup-shipping-method']:checked").val()){
				$('#popup-form-messages').html('<span class="error">יש לבחור אפשרות משלוח</span>');
				return;
			}
		});

		var fetchingSlots = false;
		var fetchingState = false;
		$('#checkout-popup-submit-btn').prop('disabled', true);

		function updateDayLimitNotice(date) {
			var $container = $('#choose-shipping');
			var $notice = $container.find('.ocws-popup-day-limit-notice');
			if (!$notice.length && $('#popup-shipping-city-slots').length) {
				$notice = $('<p class="ocws-popup-day-limit-notice" style="display: none; margin: 0.5em 0 0; font-size: 0.78em; color: #c00;"><span class="ocws-popup-day-limit-message"></span></p>');
				$('#popup-shipping-city-slots').after($notice);
			}
			if (!date) {
				$notice.hide();
				return;
			}
			$.post(ocws.ajaxurl, { action: 'oc_woo_shipping_check_cart_availability_for_date', date: date })
				.done(function(response) {
					if (response && response.success && response.data && response.data.unavailable_names && response.data.unavailable_names.length > 0) {
						var name = response.data.unavailable_names[0];
						$notice.find('.ocws-popup-day-limit-message').text('שימו לב: ' + name + ' לא זמין ביום שנבחר');
						$notice.css('display', 'block');
					} else {
						$notice.hide();
					}
				})
				.fail(function() { $notice.hide(); });
		}

		$('#choose-shipping').find('select[name="selected-city"]').on('change', function(){
			if($(this).val()) { //console.log('billing city: ' + $(this).val());
				$(this).removeClass('invalid');

				var form = $(this).closest('form');
				var chosenMethod = form.find('input[name="popup-shipping-method"]').val();
				$('#popup-shipping-city-slots').html('');
				fetchingSlots = true;

				$.ajax({ 
					method: "POST",
					url: ocws.ajaxurl,
					data: {action: "oc_woo_shipping_fetch_slots_for_city", billing_city: $(this).val(), shipping_method: chosenMethod, show_as_slider: true},
					beforeSend: function() {
						$('#popup-shipping-form-messages').html('<span class="loading">'+ocws.localize.loading+'...</span>');
					},
					success: function(response) {
						console.log(response);
						$('#popup-shipping-form-messages').html('');
						$('#popup-shipping-city-slots').html(response.data.resp);
						$('.ocws-popup-day-limit-notice').hide();

						// oc-woo-shipping-additional--message	
						let block  		=  $('#popup-shipping-city-slots').find('#oc-woo-shipping-additional--message');
						if ( block.length ){
							// let messagePopup = block.find('.first').text() + '\n' +  block.find('.second').text();
							// alert( messagePopup  );
							// $('.additional-choose-shipping-popup .ajax-message').html( messagePopup );
							// $('.additional-choose-shipping-popup').addClass('shown');
							block.show();
						}

						// console.log( block, 'block' );
						// console.log( messagePopup, 'messagePopup' );

						$('#popup-shipping-city-slots .ocws-days-list-slider').owlCarousel({
							margin: 10,
							loop: false,
							stagePadding: 10,
							items: 4,
							rtl: ($(document.body).hasClass('rtl')),
							nav: true,
							dots: false,
							pagination: false,
							responsiveClass:true,
							responsive:{
								0:{
									items:2
								},
								600:{
									items:3
								},
								1000:{
									items:5
								}
							}
						});

						$('#popup-shipping-city-slots .ocws-days-with-slots-list .day-data').owlCarousel({
							margin: 10,
							loop: false,
							stagePadding: 10,
							items: 4,
							rtl: ($(document.body).hasClass('rtl')),
							nav: true,
							dots: false,
							pagination: false,
							responsiveClass:true,
							responsive:{
								0:{
									items:2
								},
								600:{
									items:3
								},
								1000:{
									items:5
								}
							}
						});

						$('#popup-shipping-city-slots .ocws-dates-only-list-slider').owlCarousel({
							margin: 10,
							loop: false,
							stagePadding: 10,
							items: 4,
							rtl: ($(document.body).hasClass('rtl')),
							nav: true,
							dots: false,
							pagination: false,
							responsiveClass:true,
							responsive:{
								0:{
									items:2
								},
								600:{
									items:3
								},
								1000:{
									items:5
								}
							}
						});

						fetchingSlots = false;
						// todo: define different handlers for on slot click event on checkout page and on the popup
					}
				});

			} else {
				$(this).addClass('invalid');
			}
		});

		// additional popup for choosing action
		// $(document).on( 'click', '.additional-choose-shipping-popup--control button', function(e){
		$(document).on( 'click', 'button.popup-shipping-controll', function(e){

			let val = $(this).val();
			if ( val == 'close' ){
				// $('.additional-choose-shipping-popup').removeClass('shown');
			} else if ( val == 'back' ){
				// $('.choose-shipping-popup').removeClass('shown');
				$('.ocws-checkout-choose-city-popup').removeClass('shown');
			} else if ( val == 'localpickup' ){
				// loop instead selector
				$('#shipping_method li').each(function(i, el){
					let attrClass = $(el).attr('class');
					if ( attrClass.indexOf( 'oc_woo_advanced_shipping_method' ) == -1 ){
						$(el).find('.shipping_method').trigger( 'click' );
					}
				});
				// $('.additional-choose-shipping-popup').removeClass('shown');
				$('.ocws-checkout-choose-city-popup').removeClass('shown');
			} else if ( val == 'choose-city' ){
				$('.ocws-checkout-choose-city-popup').addClass( 'active-city-form' );
				// $('.additional-choose-shipping-popup').removeClass('shown');
				// $('.ocws-checkout-choose-city-popup').addClass('shown');
			}
		});

		//
		$(document).on( 'click', 'button.back-to-main-popup', function(){
			$('.ocws-checkout-choose-city-popup').removeClass( 'active-city-form' );
		})

		$('#choose-shipping').find('input[name="billing_address_coords"]').on('change', function(){
			if($(this).val()) {

				var form = $(this).closest('form');
				var chosenMethod = form.find('input[name="popup-shipping-method"]').val();
				var cityCode = form.find('input[name="billing_city_code"]').val();
				$('#popup-shipping-city-slots').html('');
				fetchingSlots = true;

				$.ajax({
					method: "POST",
					url: ocws.ajaxurl,
					data: {
						action: "oc_woo_shipping_fetch_slots_for_coords",
						billing_address_coords: $(this).val(),
						billing_city_code: cityCode,
						shipping_method: chosenMethod,
						show_as_slider: true},
					beforeSend: function() {
						$('#popup-shipping-form-messages').html('<span class="loading">'+ocws.localize.loading+'...</span>');
					},
					success: function(response) {
						console.log(response);
						$('#popup-shipping-form-messages').html('');
						$('#popup-shipping-city-slots').html(response.data.resp);
						$('.ocws-popup-day-limit-notice').hide();

						$('#popup-shipping-city-slots .ocws-days-list-slider').owlCarousel({
							margin: 10,
							loop: false,
							stagePadding: 10,
							items: 4,
							rtl: ($(document.body).hasClass('rtl')),
							nav: true,
							dots: false,
							pagination: false,
							responsiveClass:true,
							responsive:{
								0:{
									items:2
								},
								600:{
									items:3
								},
								1000:{
									items:5
								}
							}
						});

						$('#popup-shipping-city-slots .ocws-days-with-slots-list .day-data').owlCarousel({
							margin: 10,
							loop: false,
							stagePadding: 10,
							items: 4,
							rtl: ($(document.body).hasClass('rtl')),
							nav: true,
							dots: false,
							pagination: false,
							responsiveClass:true,
							responsive:{
								0:{
									items:2
								},
								600:{
									items:3
								},
								1000:{
									items:5
								}
							}
						});

						$('#popup-shipping-city-slots .ocws-dates-only-list-slider').owlCarousel({
							margin: 10,
							loop: false,
							stagePadding: 10,
							items: 4,
							rtl: ($(document.body).hasClass('rtl')),
							nav: true,
							dots: false,
							pagination: false,
							responsiveClass:true,
							responsive:{
								0:{
									items:2
								},
								600:{
									items:3
								},
								1000:{
									items:5
								}
							}
						});

						fetchingSlots = false;
						// todo: define different handlers for on slot click event on checkout page and on the popup
					}
				});

			} else {
				$(this).addClass('invalid');
			}
		});

		$('#ocws-checkout-choose-city-form').find('input[name="billing_address_coords"]').on('change', function(){
			// console.log( 'BILLING COORDINATE CHANGE' );
			if($(this).val()) {

				var form = $(this).closest('form');
				$('#popup-shipping-city-slots').html('');
				var cityCode = form.find('input[name="billing_city_code"]').val();
				fetchingState = true;
				$('#checkout-popup-submit-btn').prop('disabled', true);

				$.ajax({
					method: "POST",
					url: ocws.ajaxurl,
					data: {
						action: "oc_woo_shipping_fetch_state_for_coords",
						billing_address_coords: $(this).val(),
						billing_city_code: cityCode
					},
					beforeSend: function() {
						$('#form-messages').html('<span class="loading">'+ocws.localize.loading+'...</span>');
					},
					success: function(response) {
						console.log(response);
						$('#form-messages').html(response.data.resp);

						fetchingState = false;
						$('#checkout-popup-submit-btn').prop('disabled', false);
					}
				});

			} else {
				$(this).addClass('invalid');
			}
		});

		function loadPickupData(chosenMethod) {

			//chosenMethod = 'oc_woo_local_pickup_method';
			var chosenMethod = $('#choose-shipping input[id^="oc_woo_local_pickup_method"]').val();
			var aff_id_input = $('#choose-shipping select[name="ocws_lp_pickup_aff_id"]');
			var date_input = $('#choose-shipping input[name="ocws_lp_pickup_date"]');
			var aff_id = (aff_id_input.length? aff_id_input.val() : '');
			var date_value = (date_input.length? date_input.val() : '');
			$('#popup-pickup-options').html('');
			fetchingSlots = true;

			$.ajax({
				method: "POST",
				url: ocws.ajaxurl,
				data: {action: "oc_woo_shipping_fetch_slots_for_aff", ocws_lp_pickup_aff_id: aff_id, ocws_lp_pickup_date: date_value, shipping_method: chosenMethod, show_as_slider: true},
				beforeSend: function() {
					$('#popup-pickup-form-messages').html('<span class="loading">'+ocws.localize.loading+'...</span>');
				},
				success: function(response) {
					console.log(response);
					$('#popup-pickup-form-messages').html('');
					$('#popup-pickup-options').html(response.data.resp);

					$('#popup-pickup-options .ocws-days-list-slider').owlCarousel({
						margin: 10,
						loop: false,
						stagePadding: 10,
						items: 4,
						rtl: ($(document.body).hasClass('rtl')),
						nav: true,
						dots: false,
						pagination: false,
						responsiveClass:true,
						responsive:{
							0:{
								items:2
							},
							600:{
								items:3
							},
							1000:{
								items:5
							}
						}
					});

					$('#popup-pickup-options .ocws-days-with-slots-list .day-data').owlCarousel({
						margin: 10,
						loop: false,
						stagePadding: 10,
						items: 4,
						rtl: ($(document.body).hasClass('rtl')),
						nav: true,
						dots: false,
						pagination: false,
						responsiveClass:true,
						responsive:{
							0:{
								items:2
							},
							600:{
								items:3
							},
							1000:{
								items:5
							}
						}
					});

					$('#popup-pickup-options .ocws-dates-only-list-slider').owlCarousel({
						margin: 10,
						loop: false,
						stagePadding: 10,
						items: 4,
						rtl: ($(document.body).hasClass('rtl')),
						nav: true,
						dots: false,
						pagination: false,
						responsiveClass:true,
						responsive:{
							0:{
								items:2
							},
							600:{
								items:3
							},
							1000:{
								items:5
							}
						}
					});

					fetchingSlots = false;
					// todo: define different handlers for on slot click event on checkout page and on the popup
				}
			});
		}

		$( document.body ).on('change', '#choose-shipping select[name="ocws_lp_pickup_aff_id"]', function() {

			if($(this).val()) { //console.log('pickup branch: ' + $(this).val());
				$(this).removeClass('invalid');

				var form = $(this).closest('form');
				loadPickupData($('#choose-shipping input[name="popup-shipping-method"]').val());

			} else {
				$(this).addClass('invalid');
			}
		});

		$( document.body ).on('change', '#choose-shipping input[name="ocws_lp_pickup_date"]', function() {

			if($(this).val()) { //console.log('pickup branch: ' + $(this).val());
				$(this).removeClass('invalid');

				var form = $(this).closest('form');
				loadPickupData($('#choose-shipping input[name="popup-shipping-method"]').val());

			} else {
				$(this).addClass('invalid');
			}
		});

		$( document.body ).on('change', '#choose-shipping input[name="ocws_lp_pickup_slot_start"]', function() {


		});

		$( document.body ).on('change', '#choose-shipping input[name="ocws_lp_pickup_slot_end"]', function() {


		});

		$( document.body ).on('click', '#choose-shipping input[name="popup-shipping-method"]', function(){

			if (fetchingSlots) return;
			var form = $(this).closest('#choose-shipping');
			if($(this).val().substr(0, ('oc_woo_advanced_shipping_method').length) == 'oc_woo_advanced_shipping_method') {
				show_shipping();
				hide_pickup();
				var city = form.find('select[name="selected-city"] option:selected');
				if (city.val()) {
					city.trigger('change');
				}
			} else if ($(this).val().substr(0, ('oc_woo_local_pickup_method').length) == 'oc_woo_local_pickup_method') {
				show_pickup();
				hide_shipping();
				loadPickupData($(this).val());
			} else {
				hide_shipping();
				hide_pickup();
			}
		});

		function show_shipping() {
			$('#popup-shipping-options').css('display', 'block');
			$('#popup-shipping-form-messages').css('display', 'block');
			$('#popup-shipping-city-slots').css('display', 'block');
		}

		function hide_shipping() {
			$('#popup-shipping-options').css('display', 'none');
			$('#popup-shipping-form-messages').css('display', 'none');
			$('#popup-shipping-city-slots').css('display', 'none');
		}

		function show_pickup() {
			$('#popup-pickup-options').css('display', 'block');
			$('#popup-pickup-form-messages').css('display', 'block');
		}

		function hide_pickup() {
			$('#popup-pickup-options').css('display', 'none');
			$('#popup-pickup-form-messages').css('display', 'none');
		}

		$( document.body ).on('click', '#choose-shipping .ocws-days-list-slider .day-data', function(event) {

			event.preventDefault();

			var dataId = $(this).data('id');

			var popup = $(this).closest('#choose-shipping');

			if (popup.length) {

				popup.find('.ocws-days-with-slots-list .day-data').removeClass('active');
				popup.find('.ocws-days-with-slots-list .day-data').css('display', 'none');
				var daySlots = popup.find('.ocws-days-with-slots-list .day-data[data-rel-id="'+dataId+'"]');
				daySlots.css('display', '');
				if (daySlots.length) {
					popup.find('.ocws-days-with-slots-list-label').css('display', '');
				}
				else {
					popup.find('.ocws-days-with-slots-list-label').css('display', 'none');
				}
			}

			$('#choose-shipping .ocws-days-list-slider .day-data').removeClass('active');
			$(this).addClass('active');

			if (typeof updateDayLimitNotice === 'function') {
				updateDayLimitNotice(dataId);
			}
		});

		$( document.body ).on('click', '#choose-shipping .ocws-days-with-slots-list .day-data .slot', function(event) {

			event.preventDefault();

			var popup = $(this).closest('#choose-shipping');
			var shippingParent = $(this).closest('#oc-woo-shipping-additional');
			var pickupParent = $(this).closest('#oc-woo-pickup-additional');
			var parentDayData = $(this).closest('.day-data');

			if (popup.length) {

				popup.find('.ocws-days-with-slots-list .day-data').removeClass('active');

			}

			if (shippingParent.length) {

				$('#choose-shipping .ocws-days-with-slots-list .day-data .slot').removeClass('selected');
				$(this).addClass('selected');
				$(parentDayData).addClass('active');
				popup.find('input[name="order_expedition_slot_start"]').val($(this).data('slot-start'));
				popup.find('input[name="order_expedition_slot_end"]').val($(this).data('slot-end'));
				popup.find('input[name="order_expedition_date"]').val($(this).data('date'));
				if (typeof updateDayLimitNotice === 'function') {
					updateDayLimitNotice($(this).data('date'));
				}
			}
			else if (pickupParent.length) {

				$('#choose-shipping .ocws-days-with-slots-list .day-data .slot').removeClass('selected');
				$(this).addClass('selected');
				$(parentDayData).addClass('active');
				popup.find('input[name="ocws_lp_pickup_slot_start"]').val($(this).data('slot-start'));
				popup.find('input[name="ocws_lp_pickup_slot_end"]').val($(this).data('slot-end'));
				popup.find('input[name="ocws_lp_pickup_date"]').val($(this).data('date'));
			}


		});

		$( document.body ).on('click', '#choose-shipping .ocws-dates-only-list-slider .slot', function(event) {
			$('#choose-shipping input[type="submit"]').addClass('sActive');
			event.preventDefault();

			var popup = $(this).closest('#choose-shipping');
			var shippingParent = $(this).closest('#oc-woo-shipping-additional');
			var pickupParent = $(this).closest('#oc-woo-pickup-additional');

			if (shippingParent.length) {

				$('#choose-shipping .ocws-dates-only-list-slider .slot').removeClass('selected');
				$(this).addClass('selected');

				popup.find('input[name="order_expedition_slot_start"]').val($(this).data('slot-start'));
				popup.find('input[name="order_expedition_slot_end"]').val($(this).data('slot-end'));
				popup.find('input[name="order_expedition_date"]').val($(this).data('date'));
				if (typeof updateDayLimitNotice === 'function') {
					updateDayLimitNotice($(this).data('date'));
				}
			}
			else if (pickupParent.length) {

				$('#choose-shipping .ocws-dates-only-list-slider .slot').removeClass('selected');
				$(this).addClass('selected');

				popup.find('input[name="ocws_lp_pickup_slot_start"]').val($(this).data('slot-start'));
				popup.find('input[name="ocws_lp_pickup_slot_end"]').val($(this).data('slot-end'));
				popup.find('input[name="ocws_lp_pickup_date"]').val($(this).data('date'));
			}

		});

		function initCheckoutSliders() {
			$('form.checkout .slot-list-container .ocws-days-list-slider').owlCarousel({
				margin: 10,
				loop: false,
				stagePadding: 10,
				items: 4,
				rtl: ($(document.body).hasClass('rtl')),
				nav: true,
				dots: false,
				pagination: false,
				responsiveClass:true,
				responsive:{
					0:{
						items:2
					},
					600:{
						items:3
					},
					1000:{
						items:5
					}
				}
			});

			$('form.checkout .slot-list-container .ocws-days-with-slots-list .day-data').owlCarousel({
				margin: 10,
				loop: false,
				stagePadding: 10,
				items: 4,
				rtl: ($(document.body).hasClass('rtl')),
				nav: true,
				dots: false,
				pagination: false,
				responsiveClass:true,
				responsive:{
					0:{
						items:2
					},
					600:{
						items:3
					},
					1000:{
						items:5
					}
				}
			});

			$('form.checkout .slot-list-container .ocws-dates-only-list-slider').owlCarousel({
				margin: 10,
				loop: false,
				stagePadding: 10,
				items: 4,
				rtl: ($(document.body).hasClass('rtl')),
				nav: true,
				dots: false,
				pagination: false,
				responsiveClass:true,
				responsive:{
					0:{
						items:2
					},
					600:{
						items:3
					},
					1000:{
						items:5
					}
				}
			});
		}

		initCheckoutSliders();

		//shipping popup close
		jQuery(document).on('click', '.choose-shipping-popup .inner-wrapper .pop-close', function (e) {
			// let chosenShipping 	= $('#shipping_method input:checked').val();
			// let notice 			= $('.show-shipping-block .important-notice');
			// let noticeHtml 		= notice.html();
			// let noticeMessage  	= 'המינימום למשלוח עד הבית';
			// if ( $('body').hasClass( 'woocommerce-checkout' ) && chosenShipping.indexOf( 'oc_woo_advanced_shipping_method' ) != -1 ){
			// 	if (  notice.length && noticeHtml.indexOf( noticeMessage ) != -1  ){
			// 		console.log( 'message exist, dont close!!!' );
			// 		return false;
			// 	}
			// }
			jQuery('.choose-shipping-popup').removeClass('shown');
			jQuery('body').css({ overflow: 'auto' });
		});

		jQuery(document).on('click', '.ocws-checkout-choose-city-popup .inner-wrapper .pop-close', function () {

			// let chosenShipping 	= $('#shipping_method input:checked').val();
			// let notice 			= $('.show-shipping-block .important-notice');
			// let noticeHtml 		= notice.html();
			// let noticeMessage  	= 'המינימום למשלוח עד הבית';
			// if ( $('body').hasClass( 'woocommerce-checkout' ) && chosenShipping.indexOf( 'oc_woo_advanced_shipping_method' ) != -1 ){
			// 	if (  notice.length && noticeHtml.indexOf( noticeMessage ) != -1  ){
			// 		// console.log( 'message exist, dont close!!!' );
			// 		return;
			// 	}
			// }

			jQuery('.ocws-checkout-choose-city-popup').removeClass('shown');
			jQuery('body').css({ overflow: 'auto' });
		});

		$( document.body ).on( 'change', 'form.checkout input[name="billing_address_coords"]', function(e) {

			e.preventDefault();
			console.log($(this).val());

			$('.woocommerce-billing-fields').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$( document.body ).trigger( 'update_checkout' );

			// TODO check polygons
			/*var coords = $(this).val();
			if (coords && ocws.polygons) {

				coords = coords.replace('(', '{"lat":');
				coords = coords.replace(', ', ',"lng":');
				coords = coords.replace(')', '}');
				coords = JSON.parse(coords);

				$('.woocommerce-billing-fields').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				var foundPolygon = '';

				for (var i = 0; i < ocws.polygons.length; i++) {
					var polygon = ocws.polygons[i];
					if (polygon.is_enabled == 1) {
						var paths = JSON.parse(JSON.stringify(polygon.gm_shapes.gm_shapes).replace(/"([+-]?\d+(\.\d+)?)"/g, '$1'));
						var gmpolygon = new google.maps.Polygon({paths: paths});
						var result = google.maps.geometry.poly.containsLocation(
							new google.maps.LatLng(coords.lat, coords.lng),
							gmpolygon
						);
						console.log(result);
						if (true === result) {
							$('form.checkout input[name="billing_polygon_code"]').val(polygon.location_code);
							foundPolygon = polygon.location_code;
							break;
						}
					}
				}

				if (foundPolygon === '') {
					$('form.checkout input[name="billing_polygon_code"]').val('');
				}

				var cityName = $('form.checkout input[name="billing_city_name"]').val();

				if (cityName) {

					const geocoder = new google.maps.Geocoder();
					geocoder
						.geocode({ address: cityName, componentRestrictions: { country: 'IL' } })
						.then(({results}) => {
							console.log(results);
							if (results.length && results[0] && results[0].place_id) {
								$('form.checkout input[name="billing_city"]').val(results[0].place_id);
								$('.woocommerce-billing-fields').unblock();
								$( document.body ).trigger( 'update_checkout' );
							}
						})
						.catch((e) =>
							console.log("Geocode was not successful for the following reason: " + e)
					);
				}

				$('.woocommerce-billing-fields').unblock();
			}*/
		});

		if ( $('body').hasClass('woocommerce-checkout') ){
			
			let localStorageName 	= 'ocws_chekout_fields';
			var checkoutFields 		= get_checkout_storage_object();

			// on billing | shipping form inputs change - fire
			$( document.body ).on( 'change', '.ocws_update_checkout_on_change', save_field_value_on_change );
			// on checkout page init
			$( document.body ).on( 'init_checkout', retrieve_checkout_field_values );

			// Save field value on checkout field change
			function save_field_value_on_change(e){
				let $t 		= $(this);
				let parent 	= $t.find('.woocommerce-input-wrapper');
				let field 	= parent.find( 'input' );
				if ( !parent.length ){
					field 	= parent.find( 'select' );
				}
				if ( !parent.length ){
					field 	= parent.find( 'textarea' );
				}
				let fieldName 	= field.attr('name');

				checkoutFields[ fieldName ] = field.val();
				localStorage.setItem( localStorageName, JSON.stringify( checkoutFields ) );
			}

			// get object from local_storage or create new 
			function get_checkout_storage_object(e){
				checkoutFields = get_local_storage_checkout_obj();
				if ( !checkoutFields ){
					checkoutFields = init_local_storage_checkout_obj();
				}
				return checkoutFields;
			}

			function get_local_storage_checkout_obj(){
				let checkoutFields = localStorage.getItem( localStorageName );
				// console.log( checkoutFields, 'object exsist !' );
				// object exist
				if ( checkoutFields !== null && checkoutFields !== undefined ) {
					return JSON.parse( checkoutFields );			
				} else {
					//  doesn`t exist
					return false;
				}
			}

			// init local storage  object , get values from field | based on special class added to input wrapper
			function init_local_storage_checkout_obj(){
				let selectorFields = $('.ocws_update_checkout_on_change .woocommerce-input-wrapper');
				// init empty object
				checkoutFields 	= {}
				var len 		= selectorFields.length;
				while ( len-- ) {
					// regular input
					let currField = $( selectorFields[len] );
					let field 	  = currField.find( 'input' );
					// city select
					if ( !field.length ){
						field = currField.find( 'select' );
					}
					// notes
					if ( !field.length ){
						field = currField.find( 'textarea' );
					}
					let name = field.attr('name');
					let val 	= field.val();
					checkoutFields[ name ] = val;
				}

				localStorage.setItem( localStorageName, JSON.stringify( checkoutFields ) );
				return checkoutFields;
			}

			// retrive data from localstorage and set value to field
			function retrieve_checkout_field_values(){
				if ( checkoutFields !== null  ){
					for ( const field in checkoutFields ) {
						let fieldSelector = $('#' + field)
						// get field by id 
						if ( fieldSelector.val() == '' ){
							// retrieve value from local storage
							fieldSelector.val( checkoutFields[field] )
						}
					}
				}
			}
		}
	});

	// $(document).on( 'click', '.ajax-message', function(){
	// 	$('.ocws-checkout-choose-city-popup .inner-wrapper').block({
	// 		message: null,
	// 		overlayCSS: {
	// 			background: '#fff',
	// 			opacity: 0.6
	// 		}
	// 	});
	// })

})( jQuery, ocws );
