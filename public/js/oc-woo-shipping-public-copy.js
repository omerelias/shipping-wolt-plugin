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

			/*$.post( ocws.ajaxurl + ( ocws.ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_checkout_save_shipping_info', {
				ocws_shipping_info: data
			}, function (response, textStatus) {
				if (response.data.fragment) {
					$('#oc-woo-shipping-additional').replaceWith(response.data.fragment);
				}
				$('#oc-woo-shipping-additional').unblock();
				//$( document.body ).trigger( 'update_checkout' );
			}, 'json' );*/
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

		$( document.body ).on( 'update_checkout', function() {
			$('#oc-woo-shipping-additional').hide();
		});

		$( document.body ).on( 'click', '.show-shipping-location-button', function(e) {
			e.preventDefault();
			$('.show-shipping-location').show();
		});

		$( document.body ).on( 'click', 'input.ocws-disabled-shipping-method-input', function(e) {

			e.preventDefault();
			$('.ocws-checkout-choose-city-popup').addClass('shown');

		});

		$( document.body ).on( 'submit', '#ocws-checkout-choose-city-form', function(e) {

			e.preventDefault();
			$('.ocws-checkout-choose-city-popup').removeClass('shown');
			var selectedCity = $(this).find('select[name="selected-city"]').val();

			if (selectedCity) {
				var otherCity = $('.ocws-enhanced-select[name="other_city"]');
				if (otherCity.length) {
					otherCity.val(selectedCity);
					otherCity.trigger('change');
				}
				else {

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
					$('#popup-shipping-form-messages').html('<span class="error">יש לבחור את אזור חלוקת המשלוח</span>');
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

		});

		$( document.body ).on('click', '#choose-shipping .ocws-days-with-slots-list .day-data .slot', function(event) {

			event.preventDefault();

			var popup = $(this).closest('#choose-shipping');
			var parentDayData = $(this).closest('.day-data');

			if (popup.length) {

				popup.find('.ocws-days-with-slots-list .day-data').removeClass('active');

			}

			$('#choose-shipping .ocws-days-with-slots-list .day-data .slot').removeClass('selected');
			$(this).addClass('selected');
			$(parentDayData).addClass('active');
			popup.find('input[name="order_expedition_slot_start"]').val($(this).data('slot-start'));
			popup.find('input[name="order_expedition_slot_end"]').val($(this).data('slot-end'));
			popup.find('input[name="order_expedition_date"]').val($(this).data('date'));

		});

		$( document.body ).on('click', '#choose-shipping .ocws-dates-only-list-slider .slot', function(event) {

			event.preventDefault();

			var popup = $(this).closest('#choose-shipping');

			$('#choose-shipping .ocws-dates-only-list-slider .slot').removeClass('selected');
			$(this).addClass('selected');

			popup.find('input[name="order_expedition_slot_start"]').val($(this).data('slot-start'));
			popup.find('input[name="order_expedition_slot_end"]').val($(this).data('slot-end'));
			popup.find('input[name="order_expedition_date"]').val($(this).data('date'));

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
		jQuery(document).on('click', '.choose-shipping-popup .inner-wrapper .pop-close', function () {
			jQuery('.choose-shipping-popup').removeClass('shown');
			jQuery('body').css({ overflow: 'auto' });
		});

		jQuery(document).on('click', '.ocws-checkout-choose-city-popup .inner-wrapper .pop-close', function () {
			jQuery('.ocws-checkout-choose-city-popup').removeClass('shown');
			jQuery('body').css({ overflow: 'auto' });
		});

	});

})( jQuery, ocws );
