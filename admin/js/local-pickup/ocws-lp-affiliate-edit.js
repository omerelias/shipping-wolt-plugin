/* global lpAffiliateEditLocalizeScript, ajaxurl */
( function( $, data, wp, ajaxurl ) {
    $( function() {
        var $save_button    = $( '.ocws-lp-affiliate-save' ),
            $tbody = $( '.ocws-lp-affiliate-settings tbody' ),

        // Backbone model
            Affiliate = Backbone.Model.extend({
                changes: {},
                logChanges: function( changedRows ) {
                    var changes = this.changes || {};

                    if ( typeof changedRows.aff_name !== 'undefined' ) {
                        changes.aff_name = changedRows.aff_name;
                    }

                    if ( typeof changedRows.aff_address !== 'undefined' ) {
                        changes.aff_address = changedRows.aff_address;
                    }

                    if ( typeof changedRows.aff_descr !== 'undefined' ) {
                        changes.aff_descr = changedRows.aff_descr;
                    }

                    this.changes = changes;
                    this.trigger( 'change:affiliate' );
                },
                save: function() {
                    $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_affiliate_save_changes', {
                        ocws_lp_affiliates_nonce : data.ocws_lp_affiliates_nonce,
                        changes                 : this.changes,
                        aff_id                 : data.aff_id
                    }, this.onSaveResponse, 'json' );
                },
                onSaveResponse: function( response, textStatus ) {
                    if ( 'success' === textStatus ) {
                        if ( response.success ) {
                            if ( response.data.aff_id !== data.aff_id ) {
                                data.aff_id = response.data.aff_id;
                                if ( window.history.pushState ) {
                                    window.history.pushState({}, '', 'admin.php?page=ocws-lp&tab=affiliate' + response.data.aff_id );
                                }
                            }
                            affiliate.set( 'aff_name', response.data.aff_name );
                            affiliate.set( 'aff_address', response.data.aff_address );
                            affiliate.set( 'aff_descr', response.data.aff_descr );
                            affiliate.trigger( 'change:affiliate' );
                            affiliate.changes = {};
                            affiliate.trigger( 'saved:affiliate' );
                        } else {
                            window.alert( data.strings.save_failed );
                        }
                    }
                }
            } ),

        // Backbone view
            AffiliateView = Backbone.View.extend({
                initialize: function() {
                    this.listenTo( this.model, 'change:affiliate', this.setUnloadConfirmation );
                    this.listenTo( this.model, 'saved:affiliate', this.clearUnloadConfirmation );
                    this.listenTo( this.model, 'saved:affiliate', this.render );
                    $( window ).on( 'beforeunload', { view: this }, this.unloadConfirmation );
                    $save_button.on( 'click', { view: this }, this.onSubmit );

                    $( document.body ).on( 'input change', '#aff_name', { view: this }, this.onUpdateGroup );
                    $( document.body ).on( 'input change', '#aff_address', { view: this }, this.onUpdateGroup );
                    $( document.body ).on( 'input change', '#aff_descr', { view: this }, this.onUpdateGroup );

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
                    var aff_name   = this.model.get( 'aff_name' ),
                        aff_address = this.model.get( 'aff_address' ),
                        aff_descr = this.model.get( 'aff_descr' ),
                        view        = this;

                    // Set name.
                    $('.ocws-lp-affiliate-name').text( aff_name ? aff_name : data.strings.default_affiliate_name );

                    // Blank out the contents.
                    //this.$el.empty();
                    this.unblock();

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
                showErrors: function( errors ) {
                    var error_html = '<div id="woocommerce_errors" class="error notice is-dismissible">';

                    $( errors ).each( function( index, value ) {
                        error_html = error_html + '<p>' + value + '</p>';
                    } );
                    error_html = error_html + '</div>';

                    $( 'table.ocws-lp-affiliate-settings' ).before( error_html );
                }
            } ),
            affiliate = new Affiliate({
                aff_name: data.aff_name,
                aff_address: data.aff_address,
                aff_descr: data.aff_descr
            } ),
            affiliateView = new AffiliateView({
                model:    affiliate,
                el:       $tbody
            } );

        affiliateView.render();


    });
})( jQuery, lpAffiliateEditLocalizeScript, wp, ajaxurl );
