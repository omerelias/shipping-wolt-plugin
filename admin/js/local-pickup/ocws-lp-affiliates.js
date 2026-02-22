/* global lpAffiliatesLocalizeScript, ajaxurl */
( function( $, data, wp, ajaxurl ) {
	$( function() {
		var $table          = $( '.ocws-lp-affiliates' ),
			$tbody          = $( '.ocws-lp-affiliate-rows' ),
			$save_button    = $( '.ocws-lp-affiliate-save' ),
			$row_template   = wp.template( 'ocws-lp-affiliate-row' ),
			$blank_template = wp.template( 'ocws-lp-affiliate-row-blank' ),

			// Backbone model
			LPAffiliate       = Backbone.Model.extend({
				changes: {},
				logChanges: function( changedRows ) {
					var changes = this.changes || {};

					_.each( changedRows, function( row, id ) {
						changes[ id ] = _.extend( changes[ id ] || { aff_id : id }, row );
					} );

					this.changes = changes;
					this.trigger( 'change:affiliates' );
				},
				discardChanges: function( id ) {
					var changes      = this.changes || {},
						set_position = null,
						affiliates        = _.indexBy( this.get( 'affiliates' ), 'aff_id' );

					// Find current set position if it has moved since last save
					if ( changes[ id ] && changes[ id ].aff_order !== undefined ) {
						set_position = changes[ id ].aff_order;
					}

					// Delete all changes
					delete changes[ id ];

					// If the position was set, and this affiliate does exist in DB, set the position again so the changes are not lost.
					if ( set_position !== null && affiliates[ id ] && affiliates[ id ].aff_order !== set_position ) {
						changes[ id ] = _.extend( changes[ id ] || {}, { aff_id : id, aff_order : set_position } );
					}

					this.changes = changes;

					// No changes? Disable save button.
					if ( 0 === _.size( this.changes ) ) {
						lpAffiliateView.clearUnloadConfirmation();
					}
				},
				save: function() {
					if ( _.size( this.changes ) ) {
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_affiliates_save_changes', {
							ocws_lp_affiliates_nonce : data.ocws_lp_affiliates_nonce,
							changes                 : this.changes
						}, this.onSaveResponse, 'json' );
					} else {
						lpAffiliate.trigger( 'saved:affiliates' );
					}
				},
				onSaveResponse: function( response, textStatus ) {
					if ( 'success' === textStatus ) {
						if ( response.success ) {
							lpAffiliate.set( 'affiliates', response.data.affiliates );
							lpAffiliate.trigger( 'change:affiliates' );
							lpAffiliate.changes = {};
							lpAffiliate.trigger( 'saved:affiliates' );
						} else {
							window.alert( data.strings.save_failed );
						}
					}
				}
			} ),

			// Backbone view
			LPAffiliateView = Backbone.View.extend({
				rowTemplate: $row_template,
				initialize: function() {
					this.listenTo( this.model, 'change:affiliates', this.setUnloadConfirmation );
					this.listenTo( this.model, 'saved:affiliates', this.clearUnloadConfirmation );
					this.listenTo( this.model, 'saved:affiliates', this.render );
					$tbody.on( 'change', { view: this }, this.updateModelOnChange );
					$tbody.on( 'sortupdate', { view: this }, this.updateModelOnSort );
					$( window ).on( 'beforeunload', { view: this }, this.unloadConfirmation );
					$( document.body ).on( 'click', '.ocws-lp-affiliate-add', { view: this }, this.onAddNewAffiliateClick );
					$( document.body ).on( 'wc_backbone_modal_response', this.onAddNewAffiliateSubmitted );
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
					var affiliates = _.indexBy( this.model.get( 'affiliates' ), 'aff_id' ),
						view  = this;

					view.$el.empty();
					view.unblock();

					if ( _.size( affiliates ) ) {
						// Sort affiliates
						affiliates = _( affiliates )
							.chain()
							.sortBy( function ( affiliate ) { return parseInt( affiliate.aff_id, 10 ); } )
							.sortBy( function ( affiliate ) { return parseInt( affiliate.aff_order, 10 ); } )
							.value();

						// Populate $tbody with the current affiliates
						$.each( affiliates, function( id, rowData ) {

							if ( 1 == rowData.is_enabled ) {
								rowData.enabled_icon = '<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled">' + data.strings.yes + '</span>';
							} else {
								rowData.enabled_icon = '<span class="woocommerce-input-toggle woocommerce-input-toggle--disabled">' + data.strings.no + '</span>';
							}
							view.renderRow( rowData );
						} );
					} else {
						view.$el.append( $blank_template );
					}

					view.initRows();
				},
				renderRow: function( rowData ) {
					var view = this;
					view.$el.append( view.rowTemplate( rowData ) );
					view.initRow( rowData );
				},
				initRow: function( rowData ) {
					var view = this;
					var $tr = view.$el.find( 'tr[data-id="' + rowData.aff_id + '"]');

					$tr.find( '.ocws-lp-affiliate-delete' ).on( 'click', { view: this }, this.onDeleteRow );
					$tr.find( '.ocws-lp-affiliate-enabled a').on( 'click', { view: this }, this.onToggleEnabled );
				},
				initRows: function() {
					// Stripe
					if ( 0 === ( $( 'tbody.ocws-lp-affiliate-rows tr' ).length % 2 ) ) {
						$table.find( 'tbody.ocws-lp-affiliate-rows' ).next( 'tbody' ).find( 'tr' ).addClass( 'odd' );
					} else {
						$table.find( 'tbody.ocws-lp-affiliate-rows' ).next( 'tbody' ).find( 'tr' ).removeClass( 'odd' );
					}
					// Tooltips
					$( '#tiptip_holder' ).removeAttr( 'style' );
					$( '#tiptip_arrow' ).removeAttr( 'style' );
					$( '.tips' ).tipTip({ 'attribute': 'data-tip', 'fadeIn': 50, 'fadeOut': 50, 'delay': 50 });
				},
				onDeleteRow: function( event ) {
					var view    = event.data.view,
						model   = view.model,
						affiliates   = _.indexBy( model.get( 'affiliates' ), 'aff_id' ),
						changes = {},
						row     = $( this ).closest('tr'),
						aff_id = row.data('id');

					event.preventDefault();

					if ( window.confirm( data.strings.delete_confirmation_msg ) ) {
						if ( affiliates[ aff_id ] ) {
							delete affiliates[ aff_id ];
							changes[ aff_id ] = _.extend( changes[ aff_id ] || {}, { deleted : 'deleted' } );
							model.set( 'affiliates', affiliates );
							model.logChanges( changes );
							event.data.view.block();
							event.data.view.model.save();
						}
					}
				},
				onToggleEnabled: function ( event ) {

					var view        = event.data.view,
						$target     = $( event.target ),
						model       = view.model,
						is_enabled     = $target.closest( 'tr' ).data( 'enabled' ) == 1 ? 0 : 1,
						row     = $( this ).closest('tr'),
						aff_id = row.data('id'),
						affiliates   = _.indexBy( model.get( 'affiliates' ), 'aff_id' ),
						changes     = {};

					event.preventDefault();
					affiliates[ aff_id ].is_enabled = is_enabled;
					changes[ aff_id ] = _.extend( changes[ aff_id ] || {}, { is_enabled : is_enabled } );
					model.set( 'affiliates', affiliates );
					model.logChanges( changes );
					view.block();
					model.save();
				},
				setUnloadConfirmation: function() {
					this.needsUnloadConfirm = true;
					$save_button.prop( 'disabled', false );
				},
				clearUnloadConfirmation: function() {
					this.needsUnloadConfirm = false;
					$save_button.prop( 'disabled', true );
				},
				unloadConfirmation: function( event ) {
					if ( event.data.view.needsUnloadConfirm ) {
						event.returnValue = data.strings.unload_confirmation_msg;
						window.event.returnValue = data.strings.unload_confirmation_msg;
						return data.strings.unload_confirmation_msg;
					}
				},
				updateModelOnChange: function( event ) {
					var model     = event.data.view.model,
						$target   = $( event.target ),
						aff_id   = $target.closest( 'tr' ).data( 'id' ),
						attribute = $target.data( 'attribute' ),
						value     = $target.val(),
						affiliates   = _.indexBy( model.get( 'affiliates' ), 'aff_id' ),
						changes = {};

					if ( ! affiliates[ aff_id ] || affiliates[ aff_id ][ attribute ] !== value ) {
						changes[ aff_id ] = {};
						changes[ aff_id ][ attribute ] = value;
					}

					model.logChanges( changes );
				},
				updateModelOnSort: function( event ) {
					var view    = event.data.view,
						model   = view.model,
						affiliates   = _.indexBy( model.get( 'affiliates' ), 'aff_id' ),
						rows    = $( 'tbody.ocws-lp-affiliate-rows tr' ),
						changes = {};

					// Update sorted row position
					_.each( rows, function( row ) {
						var aff_id = $( row ).data( 'id' ),
							old_position = null,
							new_position = parseInt( $( row ).index(), 10 );

						if ( affiliates[ aff_id ] ) {
							old_position = parseInt( affiliates[ aff_id ].aff_order, 10 );
						}

						if ( old_position !== new_position ) {
							changes[ aff_id ] = _.extend( changes[ aff_id ] || {}, { aff_order : new_position } );
						}
					} );

					if ( _.size( changes ) ) {
						model.logChanges( changes );
						event.data.view.block();
						event.data.view.model.save();
					}
				},
				onAddNewAffiliateClick: function( event ) {
					event.preventDefault();

					$( this ).WCBackboneModal({
						template : 'ocws-lp-affiliate-add-affiliate',
						variable : {
							//aff_id : data.aff_id
						}
					});
				},
				onAddNewAffiliateSubmitted: function ( event, target, posted_data ) {
					if ( 'ocws-lp-affiliate-add-affiliate' === target ) {
						lpAffiliateView.block();

						// Add a new affiliate via ajax call
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_add_affiliate', {
							ocws_lp_affiliates_nonce : data.ocws_lp_affiliates_nonce,
							aff_name               : posted_data.new_aff_name
						}, function( response, textStatus ) {
							if ( 'success' === textStatus && response.success ) {

								// Trigger save if there are changes, or just re-render
								if ( _.size( lpAffiliateView.model.changes ) ) {
									lpAffiliateView.model.save();
								} else {
									lpAffiliateView.model.set( 'affiliates', response.data.affiliates );
									lpAffiliateView.model.trigger( 'change:affiliates' );
									lpAffiliateView.model.changes = {};
									lpAffiliateView.model.trigger( 'saved:affiliates' );
								}
							}
							lpAffiliateView.unblock();
						}, 'json' );
					}
				}
			} ),
			lpAffiliate = new LPAffiliate({
				affiliates: data.affiliates
			} ),
			lpAffiliateView = new LPAffiliateView({
				model:    lpAffiliate,
				el:       $tbody
			} );

		lpAffiliateView.render();

		$tbody.sortable({
			items: 'tr',
			cursor: 'move',
			axis: 'y',
			handle: 'td.ocws-lp-affiliate-sort',
			scrollSensitivity: 40
		});
	});
})( jQuery, lpAffiliatesLocalizeScript, wp, ajaxurl );
