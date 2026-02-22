/* global shippingGroupEditLocalizeScript, ajaxurl */
( function( $, data, wp, ajaxurl ) {
    $( function() {
        var $table          = $( '.oc-woo-shipping-group-locations' ),
            $tbody          = $( '.oc-woo-shipping-group-locations-rows' ),
            $save_button    = $( '.oc-woo-shipping-group-save' ),
            $row_template   = wp.template( 'oc-woo-shipping-group-locations-row' ),
            $blank_template = wp.template( 'oc-woo-shipping-group-locations-row-blank' ),

        // Backbone model
            ShippingLocation       = Backbone.Model.extend({
                changes: {},
                logChanges: function( changedRows ) {
                    var changes = this.changes || {};

                    _.each( changedRows.locations, function( row, id ) {
                        changes.locations = changes.locations || { locations : {} };
                        changes.locations[ id ] = $.extend( true, changes.locations[ id ] || { location_code : id }, row );
                    } );

                    if ( typeof changedRows.group_name !== 'undefined' ) {
                        changes.group_name = changedRows.group_name;
                    }

                    this.changes = changes;
                    this.trigger( 'change:locations' );
                },
                save: function() {
                    $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_group_save_changes', {
                        oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
                        changes                 : this.changes,
                        group_id                 : data.group_id
                    }, this.onSaveResponse, 'json' );
                },
                onSaveResponse: function( response, textStatus ) {
                    if ( 'success' === textStatus ) {
                        if ( response.success ) {
                            if ( response.data.group_id !== data.group_id ) {
                                data.group_id = response.data.group_id;
                                if ( window.history.pushState ) {
                                    window.history.pushState({}, '', 'admin.php?page=ocws&tab=group' + response.data.group_id );
                                }
                            }
                            shippingLocation.set( 'locations', response.data.locations );
                            shippingLocation.trigger( 'change:locations' );
                            shippingLocation.changes = {};
                            shippingLocation.trigger( 'saved:locations' );
                        } else {
                            window.alert( data.strings.save_failed );
                        }
                    }
                }
            } ),

        // Backbone view
            ShippingLocationView = Backbone.View.extend({
                rowTemplate: $row_template,
                initialize: function() {
                    this.listenTo( this.model, 'change:locations', this.setUnloadConfirmation );
                    this.listenTo( this.model, 'saved:locations', this.clearUnloadConfirmation );
                    this.listenTo( this.model, 'saved:locations', this.render );
                    $tbody.on( 'change', { view: this }, this.updateModelOnChange );
                    $tbody.on( 'sortupdate', { view: this }, this.updateModelOnSort );
                    $( window ).on( 'beforeunload', { view: this }, this.unloadConfirmation );
                    $save_button.on( 'click', { view: this }, this.onSubmit );

                    $( document.body ).on( 'input change', '#group_name', { view: this }, this.onUpdateGroup );
                    $( document.body ).on( 'click', '.oc-woo-shipping-group-location-settings', { view: this }, this.onConfigureShippingLocation );
                    $( document.body ).on( 'click', '.oc-woo-shipping-group-add-location', { view: this }, this.onAddShippingLocation );
                    $( document.body ).on( 'click', '.oc-woo-shipping-group-add-polygon', { view: this }, this.onAddShippingLocationPolygon );
                    $( document.body ).on( 'click', '.oc-woo-shipping-group-add-gm-city', { view: this }, this.onAddShippingGMCity );
                    $( document.body ).on( 'wc_backbone_modal_response', this.onConfigureShippingLocationSubmitted );
                    $( document.body ).on( 'wc_backbone_modal_response', this.onAddShippingLocationSubmitted );
                    $( document.body ).on( 'oc_backbone_map_modal_response', this.onConfigureShippingLocationSubmitted );
                    $( document.body ).on( 'oc_backbone_map_modal_response', this.onAddShippingPolygonSubmitted );
                    $( document.body ).on( 'change', '.oc-woo-shipping-group-location-selector select', this.onChangeShippingLocationSelector );
                    // location-use-default-switch
                    $( document.body ).on( 'change', '.location-use-default-switch', { view: this }, this.onToggleUseDefault );
                    $( document.body ).on( 'input change', '.location-option-input', { view: this }, this.onLocationOptionInputChange );

                    $( document.body ).on( 'click', 'ul.streets-list span a', { view: this }, this.onRemoveStreetFromCity );

                    $( document.body ).on( 'click', '.ocws-admin-restrict-add-street-button', { view: this }, this.onAddStreetToCity );
                },
                onUpdateGroup: function( event ) {
                    var view      = event.data.view,
                        model     = view.model,
                        value     = $( this ).val(),
                        $target   = $( event.target ),
                        attribute = $target.data( 'attribute' ),
                        changes   = {};

                    event.preventDefault();

                    changes[ attribute ] = value;
                    model.set( attribute, value );
                    model.logChanges( changes );
                    view.render();
                },
                block: function() {
                    $( this.el ).block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });
                },
                unblock: function() {
                    $( this.el ).unblock();
                },
                render: function() {
                    var self = this;
                    var locations     = _.indexBy( this.model.get( 'locations' ), 'location_code' ),
                        group_name   = this.model.get( 'group_name' ),
                        view        = this;

                    // Set name.
                    $('.oc-woo-shipping-group-name').text( group_name ? group_name : data.strings.default_group_name );

                    // Blank out the contents.
                    this.$el.empty();
                    this.unblock();

                  console.group( "RENDER DELIZE !!!!!!" );
                    console.log( locations, 'locations' );
                    console.log( this.model, 'this.model' );
                    console.log( this.model.get( 'locations' ), 'GET locations' );
                    console.groupEnd( );

                    if ( _.size( locations ) ) {
                        // Sort methods
                        locations = _.sortBy( locations, function( location ) {
                            return parseInt( location.location_order, 10 );
                        } );

                        // Populate $tbody with the current locations
                        $.each( locations, function( id, rowData ) {
                            if ( 1 == rowData.is_enabled ) {
                                rowData.enabled_icon = '<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled">' + data.strings.yes + '</span>';
                            } else {
                                rowData.enabled_icon = '<span class="woocommerce-input-toggle woocommerce-input-toggle--disabled">' + data.strings.no + '</span>';
                            }

                            rowData.location_type_icon = '';
                            if (rowData.location_type == 'polygon') {
                                rowData.location_type_icon = data.polygon_icon_url;
                            }
                            else if (rowData.gm_place_id) {
                                rowData.location_type_icon = data.googlemaps_icon_url;
                            }

                            if ( !rowData.gm_shapes.gm_shapes || !rowData.gm_shapes.gm_center || !rowData.gm_shapes.gm_zoom ) {
                                rowData.gm_shapes.gm_shapes = '';
                                rowData.gm_shapes.gm_center = '';
                                rowData.gm_shapes.gm_zoom = '';
                            }

                            if ( !rowData.gm_streets ) {
                                rowData.gm_streets = [];
                            }

                            rowData.render_streets = '';
                            for (var i=0; i<rowData.gm_streets.length; i++) {
                                rowData.render_streets += '<li data-id="' + rowData.gm_streets[i].id + '" data-name="' + rowData.gm_streets[i].name + '">' + rowData.gm_streets[i].name + ' <span>[<a href="javascript:void(0);">X</a>]</span></li>';
                            }

                            if (rowData.options.shipping_price.use_default == 1) {
                                rowData.options.shipping_price.disabled = 'disabled="disabled"';
                                rowData.options.shipping_price.use_default_checked = 'checked="checked"';
                                rowData.options.shipping_price.value = rowData.options.shipping_price.default;
                            }
                            else {
                                rowData.options.shipping_price.disabled = '';
                                rowData.options.shipping_price.use_default_checked = '';
                                rowData.options.shipping_price.value = rowData.options.shipping_price.option_value;
                            }

                            if (rowData.options.min_total.use_default == 1) {
                                rowData.options.min_total.disabled = 'disabled="disabled"';
                                rowData.options.min_total.use_default_checked = 'checked="checked"';
                                rowData.options.min_total.value = rowData.options.min_total.default;
                            }
                            else {
                                rowData.options.min_total.disabled = '';
                                rowData.options.min_total.use_default_checked = '';
                                rowData.options.min_total.value = rowData.options.min_total.option_value;
                            }
                            rowData.options.price_depending.schema = JSON.parse(rowData.options.price_depending.option_value);
                            if (rowData.options.price_depending.schema.active) {
                                rowData.options.price_depending.use_default_checked = 'checked="checked"';
                                rowData.options.price_depending.display_price = '';
                                rowData.options.price_depending.display_fixed = 'display: none !important';
                                rowData.options.price_depending.choose_select_fixed = '';
                                rowData.options.price_depending.choose_select_depending = 'selected';
                            }
                            else {
                                rowData.options.price_depending.use_default_checked = '';
                                rowData.options.price_depending.display_price = 'display: none !important';
                                rowData.options.price_depending.display_fixed = '';
                                rowData.options.price_depending.choose_select_fixed = 'selected';
                                rowData.options.price_depending.choose_select_depending = '';
                            }

                            view.$el.append( view.rowTemplate( rowData ) );

                            var $tr = view.$el.find( 'tr[data-id="' + rowData.location_code + '"]');

                            /**
                             * Price depending
                             */
                            var $tr_price = $tr.find( 'td[data-id="' + rowData.location_code + '_price"]');
                            var $price_depending_status = $tr_price.find('.price_depending_active');
                            var $price_depending_rules = $tr_price.find('.price_depending_rules');
                            var $price_add = $tr_price.find('.price_depending_add');
                            var $price_depending_rules_body = $tr_price.find('.price_depending_rules tbody');

                            var $change_price_choose = $tr.find('.change_price_choose');

                            var $schema = $tr_price.find('input[type="hidden"]');

                            $change_price_choose.on('change', function (event) {
                                event.preventDefault();
                                if (this.value === 'off') {
                                    $price_depending_status.prop('checked', false);
                                }
                                else {
                                    $price_depending_status.prop('checked', true);
                                }
                                $price_depending_status.trigger('change');
                            });
                            $schema.on('change', function (event) {
                                var $item = $(this);
                                console.log(self.model.get( 'locations' ))
                                var changes = _.indexBy( self.model.get( 'locations' ), 'location_code' );

                                changes[rowData.location_code]['options']['price_depending']['option_value'] = $item.val();

                                var objSave = {
                                    locations: {

                                    }
                                }
                                objSave["locations"][rowData.location_code] = {
                                    options: {
                                        price_depending: {
                                            option_value: $item.val(),
                                            use_default: 0
                                        }
                                    }
                                }

                                self.model.set('locations', changes)
                                self.model.logChanges(objSave);
                                view.render();
                            });

                            for (const rule of rowData.options.price_depending.schema.rules) {
                                $price_depending_rules_body.append(`<tr>
                                        <td><input type="number" placeholder="0" class="price_depending_event cart_value" value="${rule.cart_value}" /></td>
                                        <td><input type="number" placeholder="0" class="price_depending_event shipping_price" value="${rule.shipping_price}" /></td>
                                        <td><button type="button" class="button price_depending_remove">Remove</button></td>
                                    </tr>`);
                            }

                            if ($price_add.length) {
                                $price_add.on('click', function (event) {
                                    event.preventDefault();
                                    $price_depending_rules_body.append(`<tr>
                                        <td><input type="number" placeholder="0" class="price_depending_event cart_value" value="0" /></td>
                                        <td><input type="number" placeholder="0" class="price_depending_event shipping_price" value="0" /></td>
                                        <td><button type="button" class="button price_depending_remove">Remove</button></td>
                                    </tr>`);
                                });
                            }

                            if ($price_depending_status.length && $price_depending_rules.length) {
                                if ($price_depending_status.is(":checked")) {
                                    $price_depending_rules.show();
                                }
                                $price_depending_status.on('change', function () {
                                    var $item = $(this);
                                    var $schema = $item.parent().siblings('input[type="hidden"]');
                                    var object = JSON.parse($schema.val());
                                    if ($item.is(":checked")) {
                                        object['active'] = true;
                                        $price_depending_rules.show();
                                    }
                                    else {
                                        object['active'] = false;
                                        $price_depending_rules.hide();
                                    }
                                    $schema.val(JSON.stringify(object));
                                    $schema.trigger('change');
                                });
                            }
                            

                            if ( ! rowData.has_settings ) {
                                $tr.find( '.oc-woo-shipping-group-location-title > a' ).replaceWith('<span>' + $tr.find( '.oc-woo-shipping-group-location-title > a' ).text() + '</span>' );
                                var $del = $tr.find( '.oc-woo-shipping-group-location-delete' );
                                var $edit = $tr.find( '.oc-woo-shipping-group-location-settings').addClass('oc-woo-shipping-group-polygon-settings').removeClass('oc-woo-shipping-group-location-settings');
                                var $restrict = $tr.find( '.oc-woo-shipping-group-location-restrict');
                                if ( rowData.location_type == 'polygon') {
                                    $tr.find( '.oc-woo-shipping-group-location-title .row-actions' ).empty().html($edit[0].outerHTML + ' | ' + $del[0].outerHTML);
                                }
                                else {
                                    $tr.find( '.oc-woo-shipping-group-location-title .row-actions' ).empty().html($restrict[0].outerHTML + ' | ' + $del[0].outerHTML);
                                    //$tr.find( '.oc-woo-shipping-group-location-title .row-actions' ).empty().html($del);
                                }
                            }
                        } );

                        $(document).on('click', '.oc-woo-shipping-group-settings .price_depending_remove', function (event) {
                            event.preventDefault();
                            var $item = $(this);
                            var $tbody = $item.parents('tbody').eq(0);
                            var $schema = $item.parents('.price_depending_rules').siblings('input[type="hidden"]');
                            let schema = [];
                            $item.parents('tr').eq(0).remove();
                            $tbody.children().each(function (index, row) {
                                var $row = $(row);
                                var $cart_value = $row.find('.cart_value');
                                var $shipping_price = $row.find('.shipping_price');
                                schema.push({
                                    cart_value: parseFloat($cart_value.val()),
                                    shipping_price: parseFloat($shipping_price.val())
                                })
                            });
                            $schema.val(JSON.stringify({ active: true, rules: schema }));
                            $schema.trigger('change');
                        })

                        $(document).on('change', '.oc-woo-shipping-group-settings .price_depending_event', function (event) {
                            var $item = $(this);
                            var $tbody = $item.parents('tbody').eq(0);
                            var $schema = $item.parents('.price_depending_rules').siblings('input[type="hidden"]');
                            let schema = [];
                            $tbody.children().each(function (index, row) {
                                var $row = $(row);
                                var $cart_value = $row.find('.cart_value');
                                var $shipping_price = $row.find('.shipping_price');
                                schema.push({
                                    cart_value: parseFloat($cart_value.val()),
                                    shipping_price: parseFloat($shipping_price.val())
                                })
                            });
                            $schema.val(JSON.stringify({ active: true, rules: schema }));
                            $schema.trigger('change');
                        });

                        // Make the rows function
                        this.$el.find( '.oc-woo-shipping-group-location-delete' ).on( 'click', { view: this }, this.onDeleteRow );
                        this.$el.find( '.oc-woo-shipping-group-polygon-settings' ).on( 'click', { view: this }, this.onEditPolygonRow );
                        this.$el.find( '.oc-woo-shipping-group-location-restrict' ).on( 'click', { view: this }, this.onRestrictRow );
                        this.$el.find( '.ocws-admin-restrict-cancel-button' ).on( 'click', { view: this }, this.onCancelRestrictRow );
                        this.$el.find( '.ocws-admin-restrict-save-button' ).on( 'click', { view: this }, this.onSaveRestrictRow );
                        this.$el.find( '.oc-woo-shipping-group-location-enabled a').on( 'click', { view: this }, this.onToggleEnabled );
                        this.$el.find( '.location-use-default-switch').on( 'change', { view: this }, this.onToggleUseDefault );
                    } else {
                        view.$el.append( $blank_template );
                    }

                    this.initTooltips();
                },
                initTooltips: function() {
                    $( '#tiptip_holder' ).removeAttr( 'style' );
                    $( '#tiptip_arrow' ).removeAttr( 'style' );
                    $( '.tips' ).tipTip({ 'attribute': 'data-tip', 'fadeIn': 50, 'fadeOut': 50, 'delay': 50 });
                },
                onSubmit: function( event ) {
                    event.data.view.block();
                    event.data.view.model.save();
                    event.preventDefault();
                },
                onDeleteRow: function( event ) {
                    var view    = event.data.view,
                        model   = view.model,
                        locations   = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        changes = {},
                        location_code = $( this ).closest('tr').data('id');

                    event.preventDefault();

                    delete locations[ location_code ];
                    changes.locations = changes.locations || { locations : {} };
                    changes.locations[ location_code ] = _.extend( changes.locations[ location_code ] || {}, { deleted : 'deleted' } );
                    model.set( 'locations', locations );
                    model.logChanges( changes );
                    view.render();
                },
                onEditPolygonRow: function ( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location_code = $target.closest( 'tr' ).data( 'id' ),
                        shapes     = $target.closest( 'tr' ).data( 'shapes'),
                        center     = $target.closest( 'tr' ).data( 'center'),
                        zoom     = $target.closest( 'tr' ).data( 'zoom' );

                    event.preventDefault();

                    $( this ).OCBackboneMapModal({
                        template : 'ocws-modal-edit-shipping-location-polygon',
                        variable : {
                            group_id : data.group_id,
                            map_shapes : JSON.parse( JSON.stringify(locations[ location_code ].gm_shapes.gm_shapes).replace(/"([+-]?\d+(\.\d+)?)"/g, '$1') ),
                            map_location : JSON.parse( JSON.stringify(locations[ location_code ].gm_shapes.gm_center).replace(/"([+-]?\d+(\.\d+)?)"/g, '$1') ),
                            map_zoom : parseInt( locations[ location_code ].gm_shapes.gm_zoom ),
                            polygon_name : locations[ location_code ].location_name,
                            location_code : location_code
                        }
                    });
                },
                onRestrictRow: function ( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location_code = $target.closest( 'tr' ).data( 'id' ),
                        streets     = $target.closest( 'tr' ).data( 'streets'),
                        restrict_streets_row = $target.closest( 'tr' ).next(),
                        restrict_pac_input = $('#restrict_pac_input');

                    event.preventDefault();

                    if (restrict_streets_row.data('id') == location_code + '_restrict') {

                        if (restrict_pac_input.length) {
                            restrict_pac_input.insertBefore(restrict_streets_row.find('.ocws-admin-restrict-add-street-button'));
                        }
                        restrict_streets_row.show();
                    }

                    /*$( this ).OCBackboneMapModal({
                        template : 'ocws-modal-edit-shipping-location-polygon',
                        variable : {
                            group_id : data.group_id,
                            map_shapes : JSON.parse( JSON.stringify(locations[ location_code ].gm_shapes.gm_shapes).replace(/"([+-]?\d+(\.\d+)?)"/g, '$1') ),
                            map_location : JSON.parse( JSON.stringify(locations[ location_code ].gm_shapes.gm_center).replace(/"([+-]?\d+(\.\d+)?)"/g, '$1') ),
                            map_zoom : parseInt( locations[ location_code ].gm_shapes.gm_zoom ),
                            polygon_name : locations[ location_code ].location_name,
                            location_code : location_code
                        }
                    });*/
                },
                onCancelRestrictRow: function ( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location_code = $target.closest( 'tr' ).data( 'id' ),
                        streets     = $target.closest( 'tr' ).data( 'streets'),
                        restrict_streets_row = $target.closest( 'tr' ),
                        restrict_pac_input = $('#restrict_pac_input');

                    event.preventDefault();

                    $('#restrict_pac_input_container').append(restrict_pac_input);

                    $target.closest( 'tr' ).hide()
                },
                onSaveRestrictRow: function ( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location_code = $target.closest( 'tr' ).data( 'locationcode' ),
                        streets     = $target.closest( 'tr' ).data( 'streets'),
                        restrict_streets_row = $target.closest( 'tr' ),
                        restrict_pac_input = $('#restrict_pac_input');

                    event.preventDefault();

                    $('#restrict_pac_input_container').append(restrict_pac_input);

                    var gmStreets = [];
                    var streetId = '';
                    var streetName = '';
                    var listItems = restrict_streets_row.find('ul.streets-list li');
                    if (listItems.length) {
                        for (var i=0; i < listItems.length; i++) {

                            streetId = $(listItems[i]).data('id');
                            streetName = $(listItems[i]).data('name');
                            var obj = {id: streetId, name: streetName};
                            gmStreets.push(obj);
                        }
                    }

                    if (!gmStreets.length) return;

                    shippingLocationView.block();

                    // Add location to group via ajax call
                    $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_group_edit_streets', {
                        oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
                        streets_data            : gmStreets,
                        group_id                : data.group_id,
                        location_code           : location_code
                    }, function( response, textStatus ) {
                        if ( 'success' === textStatus && response.success ) {
                            if ( response.data.group_id !== data.group_id ) {
                                data.group_id = response.data.group_id;
                                if ( window.history.pushState ) {
                                    window.history.pushState({}, '', 'admin.php?page=ocws&tab=group' + response.data.group_id );
                                }
                            }
                            // Trigger save if there are changes, or just re-render
                            if ( _.size( shippingLocationView.model.changes ) ) {
                                shippingLocationView.model.save();
                            } else {
                                shippingLocationView.model.set( 'locations', response.data.locations );
                                shippingLocationView.model.trigger( 'change:locations' );
                                shippingLocationView.model.changes = {};
                                shippingLocationView.model.trigger( 'saved:locations' );
                            }
                        }
                        shippingLocationView.unblock();
                    }, 'json' );

                    $target.closest( 'tr' ).hide()
                },
                onAddStreetToCity: function ( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        restrict_streets_row = $target.closest( 'tr' ),
                        restrict_pac_input = $('#restrict_pac_input');
                    var saveBtn = restrict_streets_row.find('.ocws-admin-restrict-save-button');

                    event.preventDefault();

                    if (restrict_pac_input.data('place_id') && restrict_pac_input.data('place_name')) {

                        var list = restrict_pac_input.closest('td').find('ul.streets-list');
                        var place_id = restrict_pac_input.data('place_id');
                        var place_name = restrict_pac_input.data('place_name');
                        var listItemExists = list.find('li[data-id="' + place_id + '"]');
                        if (!listItemExists.length) {
                            list.append('<li data-id="' + place_id + '" data-name="' + place_name + '">' + place_name + ' <span>[<a href="javascript:void(0);">X</a>]</span></li>');
                            if (saveBtn.length) {
                                $(saveBtn).removeAttr('disabled');
                            }
                        }
                    }
                    restrict_pac_input.val('');
                },
                onRemoveStreetFromCity: function ( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        restrict_streets_row = $target.closest( 'tr' );
                    var saveBtn = restrict_streets_row.find('.ocws-admin-restrict-save-button');

                    event.preventDefault();

                    $target.closest('li').remove();

                    if (saveBtn.length) {
                        $(saveBtn).removeAttr('disabled');
                    }
                },
                onToggleEnabled: function( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location_code = $target.closest( 'tr' ).data( 'id' ),
                        is_enabled     = $target.closest( 'tr' ).data( 'enabled' ) == 1 ? 0 : 1,
                        changes     = {};
                    event.preventDefault();
                    locations[ location_code ].is_enabled = is_enabled;
                    changes.locations = changes.locations || { locations : {} };
                    changes.locations[ location_code ] = _.extend( changes.locations[ location_code ] || {}, { is_enabled : is_enabled } );
                    model.set( 'locations', locations );
                    model.logChanges( changes );
                    view.render();
                },
                onToggleUseDefault: function( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location_code = $target.closest( 'tr' ).data( 'id' ),
                        opt_name = $target.closest( 'div' ).data( 'optname' ),
                        is_use_default     = $target.prop('checked')? '1' : '0',
                        changes     = {};
                    event.preventDefault();
                    locations[ location_code ].options[opt_name].use_default = is_use_default;
                    changes.locations = changes.locations || { locations : {} };
                    var obj = {};
                    obj.options = {};
                    obj.options[opt_name] = { use_default : is_use_default };
                    changes.locations[ location_code ] = _.extend( changes.locations[ location_code ] || {}, obj );
                    model.set( 'locations', locations );
                    model.logChanges( changes );
                    view.render();
                },
                onLocationOptionInputChange: function( event ) {
                    var view        = event.data.view,
                        $target     = $( event.target ),
                        model       = view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location_code = $target.closest( 'tr' ).data( 'id' ),
                        opt_name = $target.closest( 'div' ).data( 'optname' ),
                        opt_value     = $target.val(),
                        changes     = {};

                    event.preventDefault();
                    locations[ location_code ].options[opt_name].option_value = opt_value;
                    changes.locations = changes.locations || { locations : {} };
                    var obj = {};
                    obj.options = {};
                    obj.options[opt_name] = { option_value : opt_value };
                    changes.locations[ location_code ] = _.extend( changes.locations[ location_code ] || {}, obj );
                    model.set( 'locations', locations );
                    model.logChanges( changes );
                    //view.render();
                },
                setUnloadConfirmation: function() {
                    this.needsUnloadConfirm = true;
                    $save_button.removeAttr( 'disabled' );
                },
                clearUnloadConfirmation: function() {
                    this.needsUnloadConfirm = false;
                    $save_button.attr( 'disabled', 'disabled' );
                },
                unloadConfirmation: function( event ) {
                    if ( event.data.view.needsUnloadConfirm ) {
                        event.returnValue = data.strings.unload_confirmation_msg;
                        window.event.returnValue = data.strings.unload_confirmation_msg;
                        return data.strings.unload_confirmation_msg;
                    }
                },
                updateModelOnChange: function( event ) {

                    /*
                    * 5000:
                         enabled_icon: "<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled">Yes</span>"
                         is_enabled: "1"
                         location_code: "5000"
                         location_name: "?? ???? -???"
                         location_order: 0
                         location_type: "city"
                         options:
                             min_total:
                                 default: "100"
                                 option_name: "ocws_location5000_min_total"
                                 option_value: "100"
                                 use_default: ""
                             shipping_price:
                                 default: "10"
                                 disabled: ""
                                 option_name: "ocws_location5000_shipping_price"
                                 option_value: "10"
                                 use_default: ""
                                 use_default_checked: ""
                                 value: "10"
                    * */


                },
                updateModelOnSort: function( event ) {
                    var view         = event.data.view,
                        model        = view.model,
                        locations        = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        changes      = {};

                    _.each( locations, function( location ) {
                        var old_position = parseInt( location.location_order, 10 );
                        var new_position = parseInt( $table.find( 'tr[data-id="' + location.location_code + '"]').index() + 1, 10 );

                        if ( old_position !== new_position ) {
                            locations[ location.location_code ].location_order = new_position;
                            changes.locations = changes.locations || { locations : {} };
                            changes.locations[ location.location_code ] = _.extend( changes.locations[ location.location_code ] || {}, { location_order : new_position } );
                        }
                    } );

                    if ( _.size( changes ) ) {
                        model.logChanges( changes );
                    }
                },
                onConfigureShippingLocation: function( event ) {
                    var location_code = $( this ).closest( 'tr' ).data( 'id' ),
                        model       = event.data.view.model,
                        locations     = _.indexBy( model.get( 'locations' ), 'location_code' ),
                        location      = locations[ location_code ];

                    // Only load modal if supported
                    if ( ! location.settings_html ) {
                        return true;
                    }

                    event.preventDefault();
// edit here ------------------------------------------------------------------------
                    $( this ).WCBackboneModal({
                        template : 'ocws-modal-shipping-location-settings',
                        variable : {
                            location_code : location_code,
                            location      : location
                        },
                        data : {
                            location_code : location_code,
                            location      : location
                        }
                    });

                    $( document.body ).trigger( 'init_tooltips' );
                },
                onConfigureShippingLocationSubmitted: function( event, target, posted_data ) {
                    if ( 'ocws-modal-shipping-location-settings' === target ) {
                        shippingLocationView.block();
                        // Save method settings via ajax call
                        $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_group_locations_save_settings', {
                            oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
                            location_code             : posted_data.location_code,
                            data                    : posted_data
                        }, function( response, textStatus ) {
                            if ( 'success' === textStatus && response.success ) {
                                $( 'table.oc-woo-shipping-group-locations' ).parent().find( '#woocommerce_errors' ).remove();

                                // If there were errors, prepend the form.
                                if ( response.data.errors.length > 0 ) {
                                    shippingLocationView.showErrors( response.data.errors );
                                }

                                // Method was saved. Re-render.
                                if ( _.size( shippingLocationView.model.changes ) ) {
                                    shippingLocationView.model.save();
                                } else {
                                    shippingLocationView.model.onSaveResponse( response, textStatus );
                                }
                            } else {
                                window.alert( data.strings.save_failed );
                                shippingLocationView.unblock();
                            }
                        }, 'json' );
                    }
                },
                showErrors: function( errors ) {
                    var error_html = '<div id="woocommerce_errors" class="error notice is-dismissible">';

                    $( errors ).each( function( index, value ) {
                        error_html = error_html + '<p>' + value + '</p>';
                    } );
                    error_html = error_html + '</div>';

                    $( 'table.oc-woo-shipping-group-locations' ).before( error_html );
                },
                onAddShippingLocation: function( event ) {
                    event.preventDefault();

                    $( this ).WCBackboneModal({
                        template : 'ocws-modal-add-shipping-location',
                        variable : {
                            group_id : data.group_id
                        }
                    });

                    $( '.oc-woo-shipping-group-location-selector select' ).change();
                },
                onAddShippingLocationPolygon: function( event ) {
                    event.preventDefault();

                    var input = $(".ocws-admin-pac-input");

                    if (!input.data('map_bounds') || !input.data('map_location')) return;

                    $( this ).OCBackboneMapModal({
                        template : 'ocws-modal-add-shipping-location-polygon',
                        variable : {
                            group_id : data.group_id,
                            map_bounds: input.data('map_bounds'),
                            map_location: input.data('map_location'),
                            polygon_name: (input.data('city') !== ''? input.data('city') : input.data('place_name'))
                        }
                    });

                    // init a map for polygon
                },
                onAddShippingGMCity: function( event ) {
                    event.preventDefault();

                    var input = $(".ocws-admin-pac-input");

                    if (!input.data('place_id') || !input.data('place_name')) return;

                    shippingLocationView.block();

                    // Add location to group via ajax call
                    $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_group_add_gm_city', {
                        oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
                        location_code               : input.data('place_id'),
                        location_name               : input.data('place_name'),
                        group_id                 : data.group_id
                    }, function( response, textStatus ) {
                        if ( 'success' === textStatus && response.success ) {
                            if ( response.data.group_id !== data.group_id ) {
                                data.group_id = response.data.group_id;
                                if ( window.history.pushState ) {
                                    window.history.pushState({}, '', 'admin.php?page=ocws&tab=group' + response.data.group_id );
                                }
                            }
                            // Trigger save if there are changes, or just re-render
                            if ( _.size( shippingLocationView.model.changes ) ) {
                                shippingLocationView.model.save();
                            } else {
                                shippingLocationView.model.set( 'locations', response.data.locations );
                                shippingLocationView.model.trigger( 'change:locations' );
                                shippingLocationView.model.changes = {};
                                shippingLocationView.model.trigger( 'saved:locations' );
                            }
                        }
                        shippingLocationView.unblock();
                    }, 'json' );
                },
                onAddShippingLocationSubmitted: function( event, target, posted_data ) {
                    if ( 'ocws-modal-add-shipping-location' === target ) {
                        shippingLocationView.block();

                        // Add location to group via ajax call
                        $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_group_add_location', {
                            oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
                            location_code               : posted_data.add_location_code,
                            group_id                 : data.group_id
                        }, function( response, textStatus ) {
                            if ( 'success' === textStatus && response.success ) {
                                if ( response.data.group_id !== data.group_id ) {
                                    data.group_id = response.data.group_id;
                                    if ( window.history.pushState ) {
                                        window.history.pushState({}, '', 'admin.php?page=ocws&tab=group' + response.data.group_id );
                                    }
                                }
                                // Trigger save if there are changes, or just re-render
                                if ( _.size( shippingLocationView.model.changes ) ) {
                                    shippingLocationView.model.save();
                                } else {
                                    shippingLocationView.model.set( 'locations', response.data.locations );
                                    shippingLocationView.model.trigger( 'change:locations' );
                                    shippingLocationView.model.changes = {};
                                    shippingLocationView.model.trigger( 'saved:locations' );
                                }
                            }
                            shippingLocationView.unblock();
                        }, 'json' );
                    }
                },
                onChangeShippingLocationSelector: function() {
                    $( this ).closest( 'article' ).height( $( this ).parent().height() );
                },

                onAddShippingPolygonSubmitted: function ( event, target, posted_data ) {
                    if ( 'ocws-modal-add-shipping-location-polygon' === target ) {
                        var gmShapes = JSON.parse( posted_data.gm_shapes );
                        var polygonName = posted_data.polygon_name;

                        if (!gmShapes.length || polygonName == '') return;

                        shippingLocationView.block();

                        // Add location to group via ajax call
                        $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_group_add_polygon', {
                            oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
                            polygon_data            : gmShapes,
                            polygon_map_center      : JSON.parse( posted_data.gm_center ),
                            polygon_map_zoom        : posted_data.gm_zoom,
                            polygon_name            : polygonName,
                            group_id                : data.group_id
                        }, function( response, textStatus ) {
                            if ( 'success' === textStatus && response.success ) {
                                if ( response.data.group_id !== data.group_id ) {
                                    data.group_id = response.data.group_id;
                                    if ( window.history.pushState ) {
                                        window.history.pushState({}, '', 'admin.php?page=ocws&tab=group' + response.data.group_id );
                                    }
                                }
                                // Trigger save if there are changes, or just re-render
                                if ( _.size( shippingLocationView.model.changes ) ) {
                                    shippingLocationView.model.save();
                                } else {
                                    shippingLocationView.model.set( 'locations', response.data.locations );
                                    shippingLocationView.model.trigger( 'change:locations' );
                                    shippingLocationView.model.changes = {};
                                    shippingLocationView.model.trigger( 'saved:locations' );
                                }
                            }
                            shippingLocationView.unblock();
                        }, 'json' );
                    }

                    if ( 'ocws-modal-edit-shipping-location-polygon' === target ) {
                        var gmShapes = JSON.parse( posted_data.gm_shapes );
                        var polygonName = posted_data.polygon_name;

                        if (!gmShapes.length || polygonName == '') return;

                        shippingLocationView.block();

                        // Add location to group via ajax call
                        $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_group_edit_polygon', {
                            oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
                            polygon_data            : gmShapes,
                            polygon_map_center      : JSON.parse( posted_data.gm_center ),
                            polygon_map_zoom        : posted_data.gm_zoom,
                            polygon_name            : polygonName,
                            group_id                : data.group_id,
                            location_code           : posted_data.location_code
                        }, function( response, textStatus ) {
                            if ( 'success' === textStatus && response.success ) {
                                if ( response.data.group_id !== data.group_id ) {
                                    data.group_id = response.data.group_id;
                                    if ( window.history.pushState ) {
                                        window.history.pushState({}, '', 'admin.php?page=ocws&tab=group' + response.data.group_id );
                                    }
                                }
                                // Trigger save if there are changes, or just re-render
                                if ( _.size( shippingLocationView.model.changes ) ) {
                                    shippingLocationView.model.save();
                                } else {
                                    shippingLocationView.model.set( 'locations', response.data.locations );
                                    shippingLocationView.model.trigger( 'change:locations' );
                                    shippingLocationView.model.changes = {};
                                    shippingLocationView.model.trigger( 'saved:locations' );
                                }
                            }
                            shippingLocationView.unblock();
                        }, 'json' );
                    }
                }
            } ),
            shippingLocation = new ShippingLocation({
                locations: data.locations,
                group_name: data.group_name
            } ),
            shippingLocationView = new ShippingLocationView({
                model:    shippingLocation,
                el:       $tbody
            } );

        shippingLocationView.render();

        $tbody.sortable({
            items: 'tr',
            cursor: 'move',
            axis: 'y',
            handle: 'td.ocws-shipping-group-location-sort',
            scrollSensitivity: 40
        });
    });
})( jQuery, shippingGroupEditLocalizeScript, wp, ajaxurl );
