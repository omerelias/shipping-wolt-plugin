( function( $, ocws ) {
    $( function() {
        var cityIdInput;
        var cityInput;
        var cityNameInput;
        var addressInput;
        var houseNumInput;
        var addressCoordsInput;
        var cityNameAutocompleteInput;
        var autocomplete;

        var cityIdInputPopup;
        var cityInputPopup;
        var cityNameInputPopup;
        var addressInputPopup;
        var houseNumInputPopup;
        var cityNameAutocompleteInputPopup;
        var addressCoordsInputPopup;
        var autocompletePopup;

        var cityIdInputChooseShippingPopup;
        var cityInputChooseShippingPopup;
        var cityNameInputChooseShippingPopup;
        var addressInputChooseShippingPopup;
        var houseNumInputChooseShippingPopup;
        var cityNameAutocompleteInputChooseShippingPopup;
        var addressCoordsInputChooseShippingPopup;
        var autocompleteChooseShippingPopup;

        var accountCityIdInput;
        var accountCityInput;
        var accountCityNameInput;
        var accountAddressInput;
        var accountHouseNumInput;
        var accountAddressCoordsInput;
        var accountCityNameAutocompleteInput;
        var accountAutocomplete;

        var isCheckout = !!($('form.checkout').length);
        var isAccount = !!($('.woocommerce-MyAccount-content').length);

        const geocoder = new google.maps.Geocoder();

        function ocwsInitChooseShippingPopupAutocomplete() {

            $("#choose-shipping").on("keypress keyup", function (event) {
                cityNameAutocompleteInputChooseShippingPopup.next('span.error').text('');
                cityNameAutocompleteInputChooseShippingPopup.next('span.error').hide();
                var keyPressed = event.keyCode || event.which;
                if (keyPressed === 13) {
                    event.preventDefault();
                    return false;
                }
            });

            cityNameAutocompleteInputChooseShippingPopup = $('#choose-shipping input[name="billing_google_autocomplete"]');
            cityNameInputChooseShippingPopup = $('#choose-shipping input[name="billing_city_name"]');
            cityInputChooseShippingPopup = $('#choose-shipping input[name="billing_city"]');
            cityIdInputChooseShippingPopup = $('#choose-shipping input[name="billing_city_code"]');
            addressInputChooseShippingPopup = $('#choose-shipping input[name="billing_street"]');
            houseNumInputChooseShippingPopup = $('#choose-shipping input[name="billing_house_num"]');
            addressCoordsInputChooseShippingPopup = $('#choose-shipping input[name="billing_address_coords"]');

            var data = cityNameAutocompleteInputChooseShippingPopup.data('chooseFirstOnEnter');
            if (!data) {
                selectFirstOnEnter(cityNameAutocompleteInputChooseShippingPopup[0]);
                cityNameAutocompleteInputChooseShippingPopup.data('chooseFirstOnEnter', true);
                cityNameAutocompleteInputChooseShippingPopup.after('<span class="error"></span>');
            }

            autocompleteChooseShippingPopup = new google.maps.places.Autocomplete(cityNameAutocompleteInputChooseShippingPopup[0], {
                componentRestrictions: { country: ["il"] },
                fields: ["address_components", "geometry", "place_id", "name"],
                types: ["address"]
            });
            // When the user selects an address from the drop-down, populate the
            // address fields in the form.
            autocompleteChooseShippingPopup.addListener("place_changed", ocwsFillInAddressChooseShippingPopup);
        }

        function ocwsFillInAddressChooseShippingPopup() {

            const billingAddressPlaceChooseShippingPopup = autocompleteChooseShippingPopup.getPlace();
            var street = "";
            var city = "";
            var house = "";
            console.log(billingAddressPlaceChooseShippingPopup);

            if (!billingAddressPlaceChooseShippingPopup.hasOwnProperty('address_components')) return;

            for (const component of billingAddressPlaceChooseShippingPopup.address_components) {
                const componentType = component.types[0];

                switch (componentType) {
                    case "street_number":
                    {
                        house = component.long_name;
                        break;
                    }
                    case "locality":
                    {
                        city = component.long_name;
                        break;
                    }
                    case "route":
                    {
                        street = component.long_name;
                        break;
                    }
                }
            }

            if (!house || house == '') {
                if (billingAddressPlaceChooseShippingPopup.hasOwnProperty('name')) {
                    var regexp = new RegExp(billingAddressPlaceChooseShippingPopup.name+"\\s([0-9]+)", "i");
                    var matches = regexp.exec(cityNameAutocompleteInputChooseShippingPopup.val());
                    if (matches && matches.length > 1) {
                        house = matches[1];
                    }
                }
            }

            if (city == '' || street == '' || house == '') {
                cityNameAutocompleteInputChooseShippingPopup.next('span.error').text(ocws.localize.messages.noHouseNumberInAddress);
                cityNameAutocompleteInputChooseShippingPopup.next('span.error').show();
                return;
            }
            cityNameAutocompleteInputChooseShippingPopup.next('span.error').text('');
            cityNameAutocompleteInputChooseShippingPopup.next('span.error').hide();

            if (city) {

                $('#choose-shipping input[type="submit"]').prop('disabled', true);
                geocoder
                    .geocode({ address: city, componentRestrictions: { country: 'IL' } })
                    .then(({results}) => {
                        console.log(results);
                        if (results.length && results[0] && results[0].place_id) {
                            addressInputChooseShippingPopup.val(street);
                            cityNameInputChooseShippingPopup.val(city);
                            cityInputChooseShippingPopup.val(city);
                            cityIdInputChooseShippingPopup.val(results[0].place_id);
                            houseNumInputChooseShippingPopup.val(house);

                            if (billingAddressPlaceChooseShippingPopup.geometry && billingAddressPlaceChooseShippingPopup.geometry.location) {

                                addressCoordsInputChooseShippingPopup.val(billingAddressPlaceChooseShippingPopup.geometry.location);
                                addressCoordsInputChooseShippingPopup.trigger('change');
                            }
                        }
                        $('#choose-shipping input[type="submit"]').prop('disabled', false);
                    })
                    .catch((e) =>
                        console.log("Geocode was not successful for the following reason: " + e)
                );
            }
        }

        function ocwsInitPopupCheckoutAutocomplete() {

            $("#ocws-checkout-choose-city-form").on("keypress keyup", function (event) {
                var keyPressed = event.keyCode || event.which;
                if (keyPressed === 13) {
                    event.preventDefault();
                    return false;
                }
            });

            cityNameAutocompleteInputPopup = $('#ocws-checkout-choose-city-form input[name="billing_google_autocomplete"]');
            cityNameInputPopup = $('#ocws-checkout-choose-city-form input[name="billing_city_name"]');
            cityInputPopup = $('#ocws-checkout-choose-city-form input[name="billing_city"]');
            cityIdInputPopup = $('#ocws-checkout-choose-city-form input[name="billing_city_code"]');
            addressInputPopup = $('#ocws-checkout-choose-city-form input[name="billing_street"]');
            houseNumInputPopup = $('#ocws-checkout-choose-city-form input[name="billing_house_num"]');
            addressCoordsInputPopup = $('#ocws-checkout-choose-city-form input[name="billing_address_coords"]');

            var data = cityNameAutocompleteInputPopup.data('chooseFirstOnEnter');
            if (!data) {
                selectFirstOnEnter(cityNameAutocompleteInputPopup[0]);
                cityNameAutocompleteInputPopup.data('chooseFirstOnEnter', true);
                cityNameAutocompleteInputPopup.after('<span class="error"></span>');
            }

            autocompletePopup = new google.maps.places.Autocomplete(cityNameAutocompleteInputPopup[0], {
                componentRestrictions: { country: ["il"] },
                fields: ["address_components", "geometry", "place_id", "name", "formatted_address"],
                types: ["address"]
            });
            // When the user selects an address from the drop-down, populate the
            // address fields in the form.
            autocompletePopup.addListener("place_changed", ocwsFillInAddressPopup);
        }

        function ocwsFillInAddressPopup() {

            const billingAddressPlacePopup = autocompletePopup.getPlace();
            var street = "";
            var city = "";
            var house = "";
            console.log(billingAddressPlacePopup);

            if (!billingAddressPlacePopup.hasOwnProperty('address_components')) return;

            for (const component of billingAddressPlacePopup.address_components) {
                const componentType = component.types[0];

                switch (componentType) {
                    case "street_number":
                    {
                        house = component.long_name;
                        break;
                    }
                    case "locality":
                    {
                        city = component.long_name;
                        break;
                    }
                    case "route":
                    {
                        street = component.long_name;
                        break;
                    }
                }
            }

            if (!house || house == '') {
                if (billingAddressPlacePopup.hasOwnProperty('name')) {
                    var regexp = new RegExp(billingAddressPlacePopup.name+"\\s([0-9]+)", "i");
                    var matches = regexp.exec(cityNameAutocompleteInputPopup.val());
                    if (matches && matches.length > 1) {
                        house = matches[1];
                    }
                }
            }

            if (city == '' || street == '' || house == '') {
                cityNameAutocompleteInputPopup.next('span.error').text(ocws.localize.messages.noHouseNumberInAddress);
                cityNameAutocompleteInputPopup.next('span.error').show();
                return;
            }
            cityNameAutocompleteInputPopup.next('span.error').text('');
            cityNameAutocompleteInputPopup.next('span.error').hide();

            if (city) {

                geocoder
                    .geocode({ address: city, componentRestrictions: { country: 'IL' } })
                    .then(({results}) => {
                        console.log(results);
                        if (results.length && results[0] && results[0].place_id) {
                            addressInputPopup.val(street);
                            cityNameInputPopup.val(city);
                            cityInputPopup.val(city);
                            cityIdInputPopup.val(results[0].place_id);
                            houseNumInputPopup.val(house);

                            if (billingAddressPlacePopup.geometry && billingAddressPlacePopup.geometry.location) {

                                addressCoordsInputPopup.val(billingAddressPlacePopup.geometry.location);
                                addressCoordsInputPopup.trigger('change');
                            }
                        }
                    })
                    .catch((e) =>
                        console.log("Geocode was not successful for the following reason: " + e)
                );
            }
        }

        function ocwsInitCheckoutAutocomplete() {

            $("form.checkout").on("keypress keyup", function (event) {
                var keyPressed = event.keyCode || event.which;
                if (keyPressed === 13) {
                    event.preventDefault();
                    return false;
                }
            });

            cityNameAutocompleteInput = $('form.checkout input[name="billing_google_autocomplete"]');
            cityNameInput = $('form.checkout input[name="billing_city_name"]');
            cityInput = $('form.checkout input[name="billing_city"]');
            cityIdInput = $('form.checkout input[name="billing_city_code"]');
            addressInput = $('form.checkout input[name="billing_street"]');
            houseNumInput = $('form.checkout input[name="billing_house_num"]');
            addressCoordsInput = $('form.checkout input[name="billing_address_coords"]');

            var data = cityNameAutocompleteInput.data('chooseFirstOnEnter');
            if (!data) {
                selectFirstOnEnter(cityNameAutocompleteInput[0]);
                cityNameAutocompleteInput.data('chooseFirstOnEnter', true);
                cityNameAutocompleteInput.parent('.woocommerce-input-wrapper').after('<span class="error"></span>');
            }

            autocomplete = new google.maps.places.Autocomplete(cityNameAutocompleteInput[0], {
                componentRestrictions: { country: ["il"] },
                fields: ["address_components", "geometry", "place_id", "name"],
                types: ["address"]
            });
            // When the user selects an address from the drop-down, populate the
            // address fields in the form.
            autocomplete.addListener("place_changed", ocwsFillInAddress);
        }

        function ocwsFillInAddress() {

            const billingAddressPlace = autocomplete.getPlace();
            console.log(billingAddressPlace);
            var street = "";
            var city = "";
            var house = "";

            if (!billingAddressPlace.hasOwnProperty('address_components')) return;

            for (const component of billingAddressPlace.address_components) {
                const componentType = component.types[0];

                switch (componentType) {
                    case "street_number":
                    {
                        house = component.long_name;
                        break;
                    }
                    case "locality":
                    {
                        city = component.long_name;
                        break;
                    }
                    case "route":
                    {
                        street = component.long_name;
                        break;
                    }
                }
            }

            if (!house || house == '') {
                if (billingAddressPlace.hasOwnProperty('name')) {
                    var regexp = new RegExp(billingAddressPlace.name+"\\s([0-9]+)", "i");
                    var matches = regexp.exec(cityNameAutocompleteInput.val());
                    if (matches && matches.length > 1) {
                        house = matches[1];
                    }
                }
            }

            if (city == '' || street == '' || house == '') {
                cityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').text(ocws.localize.messages.noHouseNumberInAddress);
                cityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').show();
                return;
            }
            cityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').text('');
            cityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').hide();

            if (city) {

                geocoder
                    .geocode({ address: city, componentRestrictions: { country: 'IL' } })
                    .then(({results}) => {
                        console.log(results);
                        if (results.length && results[0] && results[0].place_id) {
                            addressInput.val(street);
                            cityNameInput.val(city);
                            cityInput.val(city);
                            cityIdInput.val(results[0].place_id);
                            houseNumInput.val(house);

                            if (billingAddressPlace.geometry && billingAddressPlace.geometry.location) {

                                addressCoordsInput.val(billingAddressPlace.geometry.location);
                                addressCoordsInput.trigger('change');
                            }
                        }
                    })
                    .catch((e) =>
                        console.log("Geocode was not successful for the following reason: " + e)
                );
            }
        }

        function ocwsInitAccountAutocomplete(type) {

            if (type !== 'billing' && type !== 'shipping') {
                return;
            }
            $(".woocommerce-MyAccount-content form").on("keypress keyup", function (event) {
                var keyPressed = event.keyCode || event.which;
                if (keyPressed === 13) {
                    event.preventDefault();
                    return false;
                }
            });

            accountCityNameAutocompleteInput = $('.woocommerce-MyAccount-content form input[name="'+type+'_google_autocomplete"]');
            accountCityNameInput = $('.woocommerce-MyAccount-content form input[name="'+type+'_city_name"]');
            accountCityInput = $('.woocommerce-MyAccount-content form input[name="'+type+'_city"]');
            accountCityIdInput = $('.woocommerce-MyAccount-content form input[name="'+type+'_city_code"]');
            accountAddressInput = $('.woocommerce-MyAccount-content form input[name="'+type+'_street"]');
            accountHouseNumInput = $('.woocommerce-MyAccount-content form input[name="'+type+'_house_num"]');
            accountAddressCoordsInput = $('.woocommerce-MyAccount-content form input[name="'+type+'_address_coords"]');

            var data = accountCityNameAutocompleteInput.data('chooseFirstOnEnter');
            if (!data) {
                selectFirstOnEnter(accountCityNameAutocompleteInput[0]);
                accountCityNameAutocompleteInput.data('chooseFirstOnEnter', true);
                accountCityNameAutocompleteInput.parent('.woocommerce-input-wrapper').after('<span class="error"></span>');
            }

            autocomplete = new google.maps.places.Autocomplete(accountCityNameAutocompleteInput[0], {
                componentRestrictions: { country: ["il"] },
                fields: ["address_components", "geometry", "place_id", "name"],
                types: ["address"]
            });
            // When the user selects an address from the drop-down, populate the
            // address fields in the form.
            autocomplete.addListener("place_changed", ocwsFillInAccountAddress);
        }

        function ocwsFillInAccountAddress() {

            const addressPlace = autocomplete.getPlace();
            console.log(addressPlace);
            var street = "";
            var city = "";
            var house = "";

            if (!addressPlace.hasOwnProperty('address_components')) return;

            for (const component of addressPlace.address_components) {
                const componentType = component.types[0];

                switch (componentType) {
                    case "street_number":
                    {
                        house = component.long_name;
                        break;
                    }
                    case "locality":
                    {
                        city = component.long_name;
                        break;
                    }
                    case "route":
                    {
                        street = component.long_name;
                        break;
                    }
                }
            }

            if (!house || house == '') {
                if (addressPlace.hasOwnProperty('name')) {
                    var regexp = new RegExp(addressPlace.name+"\\s([0-9]+)", "i");
                    var matches = regexp.exec(accountCityNameAutocompleteInput.val());
                    if (matches && matches.length > 1) {
                        house = matches[1];
                    }
                }
            }

            if (city == '' || street == '' || house == '') {
                accountCityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').text(ocws.localize.messages.noHouseNumberInAddress);
                accountCityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').show();
                return;
            }
            accountCityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').text('');
            accountCityNameAutocompleteInput.parent('.woocommerce-input-wrapper').next('span.error').hide();

            if (city) {

                geocoder
                    .geocode({ address: city, componentRestrictions: { country: 'IL' } })
                    .then(({results}) => {
                        console.log(results);
                        if (results.length && results[0] && results[0].place_id) {
                            accountAddressInput.val(street);
                            accountCityNameInput.val(city);
                            accountCityInput.val(city);
                            accountCityIdInput.val(results[0].place_id);
                            accountHouseNumInput.val(house);

                            if (addressPlace.geometry && addressPlace.geometry.location) {

                                accountAddressCoordsInput.val(addressPlace.geometry.location);
                                accountAddressCoordsInput.trigger('change');
                            }
                        }
                    })
                    .catch((e) =>
                        console.log("Geocode was not successful for the following reason: " + e)
                );
            }
        }

        if (isCheckout) {
            var autocompleteInputContainer = $('#billing_google_autocomplete_field');
            if (autocompleteInputContainer.length && autocompleteInputContainer.hasClass('ocws-hidden-form-field')) {
                ocwsInitPopupCheckoutAutocomplete();
            }
            else {
                ocwsInitCheckoutAutocomplete();
            }
        } else if (isAccount) {
            ocwsInitAccountAutocomplete(($('.woocommerce-MyAccount-content form input[name="shipping_city"]').length? 'shipping' : 'billing'));
        } else {
            ocwsInitChooseShippingPopupAutocomplete();
        }



        $( document.body).on( 'updated_checkout', function () {
            var autocompleteInputContainer = $('#billing_google_autocomplete_field');
            if (autocompleteInputContainer.length && autocompleteInputContainer.hasClass('ocws-hidden-form-field')) {
                ocwsInitPopupCheckoutAutocomplete();
            }
            else {
                ocwsInitCheckoutAutocomplete();
            }
        } );

        function ocwsGeocodeAddress(address) {

            var coords = false;
            geocoder
                .geocode({ address: address })
                .then(({ results }) => {
                    coords = results[0].geometry.location;
                })
                .catch((e) =>
                    console.log("Geocode was not successful for the following reason: " + e)
                );
            return coords;
        }

        function selectFirstOnEnter(input) {  // store the original event binding function
            var _addEventListener = (input.addEventListener) ? input.addEventListener : input.attachEvent;
            function addEventListenerWrapper(type, listener) {  // Simulate a 'down arrow' keypress on hitting 'return' when no pac suggestion is selected, and then trigger the original listener.
                if (type == "keydown") {
                    var orig_listener = listener;
                    listener = function(event) {
                        var suggestion_selected = $(".pac-item-selected").length > 0;
                        if (event.which == 13 && !suggestion_selected) {
                            var simulated_downarrow = $.Event("keydown", {keyCode: 40, which: 40});
                            orig_listener.apply(input, [simulated_downarrow]);
                        }
                        orig_listener.apply(input, [event]);
                    };
                }
                _addEventListener.apply(input, [type, listener]); // add the modified listener
            }
            if (input.addEventListener) {
                input.addEventListener = addEventListenerWrapper;
            } else if (input.attachEvent) {
                input.attachEvent = addEventListenerWrapper;
            }
        }
    });
})( jQuery, ocws );

