( function( $, wp, ajaxurl ) {
    $( function() {
        var input = $(".ocws-admin-pac-input");
        var options = {
            componentRestrictions: { country: "il" },
            fields: ["address_components", "geometry", "place_id", "name"],
            strictBounds: false,
            types: ['(regions)']
        };
        var autocomplete = new google.maps.places.Autocomplete(input[0], options);

        google.maps.event.addListener(autocomplete, 'place_changed', function () {
            var place = autocomplete.getPlace();
            if (!place.geometry || !place.geometry.location) {
                console.log("Returned place contains no geometry");
                return;
            }
            if (!place.hasOwnProperty('address_components')) return;

            var city, cityArea = '';
            var region = '';

            for (const component of place.address_components) {
                const componentType = component.types[0];

                switch (componentType) {
                    case "administrative_area_level_3":
                    {
                        cityArea = component.long_name;
                        break;
                    }
                    case "locality":
                    {
                        city = component.long_name;
                        break;
                    }
                    case "administrative_area_level_1":
                    {
                        region = component.long_name;
                        break;
                    }
                }
            }
            city = (city?city:cityArea);
            input.data('map_bounds', '');
            input.data('map_location', '');
            input.data('place_id', '');
            input.data('place_name', '');
            input.data('city', '');
            input.data('region', '');
            if (place.geometry.viewport) {
                // Only geocodes have viewport.
                input.data('map_bounds', place.geometry.viewport);
            }
            input.data('map_location', place.geometry.location);

            if (place.place_id && place.name) {
                input.data('place_id', place.place_id);
                input.data('place_name', place.name);
            }
            if (city) {
                input.data('city', city);
            }
            if (region) {
                input.data('region', region);
            }
            var polygonButton = $('.oc-woo-shipping-group-add-polygon');
            var cityButton = $('.oc-woo-shipping-group-add-gm-city');
            if (city !== '' && region !== '') {
                polygonButton.text('Add Polygon Placed In: "'+region+'"');
                cityButton.text('Or Add City: "'+city+'"');
                polygonButton.show();
                cityButton.show();
            }
            else if (region !== '') {
                polygonButton.text('Add Polygon Placed In: "'+region+'"');
                polygonButton.show();
            }
            else {
                polygonButton.text('Add Polygon Placed In: "'+city+'"');
                cityButton.text('Or Add City: "'+city+'"');
                polygonButton.show();
                cityButton.show();
            }
            console.log(place);
        });

        var restrict_streets_pac_input = $(".ocws-admin-restrict-pac-input");
        var restrict_streets_options = {
            componentRestrictions: { country: "il" },
            fields: ["address_components", "geometry", "place_id", "name"],
            strictBounds: false,
            types: ['address'],
            language: 'he'
        };
        var restrict_streets_autocomplete = new google.maps.places.Autocomplete(restrict_streets_pac_input[0], restrict_streets_options);

        google.maps.event.addListener(restrict_streets_autocomplete, 'place_changed', function () {
            var place = restrict_streets_autocomplete.getPlace();

            if (place.place_id && place.name) {

                restrict_streets_pac_input.data('place_id', place.place_id);
                restrict_streets_pac_input.data('place_name', place.name);
            }

            console.log(place);
        });
    });
})( jQuery, wp, ajaxurl );

