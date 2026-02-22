/* global shippingGroupsLocalizeScript, ajaxurl */
( function( $, data, wp, ajaxurl ) {
	$( function() {
		var $table          = $( '.oc-woo-shipping-groups' ),
			$tbody          = $( '.oc-woo-shipping-group-rows' ),
			$save_button    = $( '.oc-woo-shipping-group-save' ),
			$row_template   = wp.template( 'oc-woo-shipping-group-row' ),
			$blank_template = wp.template( 'oc-woo-shipping-group-row-blank' ),

			// Backbone model
			ShippingGroup       = Backbone.Model.extend({
				changes: {},
				logChanges: function( changedRows ) {
					var changes = this.changes || {};

					_.each( changedRows, function( row, id ) {
						changes[ id ] = _.extend( changes[ id ] || { group_id : id }, row );
					} );

					this.changes = changes;
					this.trigger( 'change:groups' );
				},
				discardChanges: function( id ) {
					var changes      = this.changes || {},
						set_position = null,
						groups        = _.indexBy( this.get( 'groups' ), 'group_id' );

					// Find current set position if it has moved since last save
					if ( changes[ id ] && changes[ id ].group_order !== undefined ) {
						set_position = changes[ id ].group_order;
					}

					// Delete all changes
					delete changes[ id ];

					// If the position was set, and this group does exist in DB, set the position again so the changes are not lost.
					if ( set_position !== null && groups[ id ] && groups[ id ].group_order !== set_position ) {
						changes[ id ] = _.extend( changes[ id ] || {}, { group_id : id, group_order : set_position } );
					}

					this.changes = changes;

					// No changes? Disable save button.
					if ( 0 === _.size( this.changes ) ) {
						shippingGroupView.clearUnloadConfirmation();
					}
				},
				save: function() {
					if ( _.size( this.changes ) ) {
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_groups_save_changes', {
							oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
							changes                 : this.changes
						}, this.onSaveResponse, 'json' );
					} else {
						shippingGroup.trigger( 'saved:groups' );
					}
				},
				onSaveResponse: function( response, textStatus ) {
					if ( 'success' === textStatus ) {
						if ( response.success ) {
							shippingGroup.set( 'groups', response.data.groups );
							shippingGroup.trigger( 'change:groups' );
							shippingGroup.changes = {};
							shippingGroup.trigger( 'saved:groups' );
						} else {
							window.alert( data.strings.save_failed );
						}
					}
				}
			} ),

			// Backbone view
			ShippingGroupView = Backbone.View.extend({
				rowTemplate: $row_template,
				initialize: function() {
					this.listenTo( this.model, 'change:groups', this.setUnloadConfirmation );
					this.listenTo( this.model, 'saved:groups', this.clearUnloadConfirmation );
					this.listenTo( this.model, 'saved:groups', this.render );
					$tbody.on( 'change', { view: this }, this.updateModelOnChange );
					$tbody.on( 'sortupdate', { view: this }, this.updateModelOnSort );
					$( window ).on( 'beforeunload', { view: this }, this.unloadConfirmation );
					$( document.body ).on( 'click', '.oc-woo-shipping-group-add', { view: this }, this.onAddNewGroupClick );
					$( document.body ).on( 'wc_backbone_modal_response', this.onAddNewGroupSubmitted );
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
					var groups = _.indexBy( this.model.get( 'groups' ), 'group_id' ),
						view  = this;

					view.$el.empty();
					view.unblock();

					if ( _.size( groups ) ) {
						// Sort groups
						groups = _( groups )
							.chain()
							.sortBy( function ( group ) { return parseInt( group.group_id, 10 ); } )
							.sortBy( function ( group ) { return parseInt( group.group_order, 10 ); } )
							.value();

						// Populate $tbody with the current groups
						$.each( groups, function( id, rowData ) {

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
					var $tr = view.$el.find( 'tr[data-id="' + rowData.group_id + '"]');

					$tr.find( '.oc-woo-shipping-group-delete' ).on( 'click', { view: this }, this.onDeleteRow );
					$tr.find( '.oc-woo-shipping-group-enabled a').on( 'click', { view: this }, this.onToggleEnabled );
				},
				initRows: function() {
					// Stripe
					if ( 0 === ( $( 'tbody.oc-woo-shipping-group-rows tr' ).length % 2 ) ) {
						$table.find( 'tbody.oc-woo-shipping-group-rows' ).next( 'tbody' ).find( 'tr' ).addClass( 'odd' );
					} else {
						$table.find( 'tbody.oc-woo-shipping-group-rows' ).next( 'tbody' ).find( 'tr' ).removeClass( 'odd' );
					}
					// Tooltips
					$( '#tiptip_holder' ).removeAttr( 'style' );
					$( '#tiptip_arrow' ).removeAttr( 'style' );
					$( '.tips' ).tipTip({ 'attribute': 'data-tip', 'fadeIn': 50, 'fadeOut': 50, 'delay': 50 });
				},
				onDeleteRow: function( event ) {
					var view    = event.data.view,
						model   = view.model,
						groups   = _.indexBy( model.get( 'groups' ), 'group_id' ),
						changes = {},
						row     = $( this ).closest('tr'),
						group_id = row.data('id');

					event.preventDefault();

					if ( window.confirm( data.strings.delete_confirmation_msg ) ) {
						if ( groups[ group_id ] ) {
							delete groups[ group_id ];
							changes[ group_id ] = _.extend( changes[ group_id ] || {}, { deleted : 'deleted' } );
							model.set( 'groups', groups );
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
						group_id = row.data('id'),
						groups   = _.indexBy( model.get( 'groups' ), 'group_id' ),
						changes     = {};

					event.preventDefault();
					groups[ group_id ].is_enabled = is_enabled;
					changes[ group_id ] = _.extend( changes[ group_id ] || {}, { is_enabled : is_enabled } );
					model.set( 'groups', groups );
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
						group_id   = $target.closest( 'tr' ).data( 'id' ),
						attribute = $target.data( 'attribute' ),
						value     = $target.val(),
						groups   = _.indexBy( model.get( 'groups' ), 'group_id' ),
						changes = {};

					if ( ! groups[ group_id ] || groups[ group_id ][ attribute ] !== value ) {
						changes[ group_id ] = {};
						changes[ group_id ][ attribute ] = value;
					}

					model.logChanges( changes );
				},
				updateModelOnSort: function( event ) {
					var view    = event.data.view,
						model   = view.model,
						groups   = _.indexBy( model.get( 'groups' ), 'group_id' ),
						rows    = $( 'tbody.oc-woo-shipping-group-rows tr' ),
						changes = {};

					// Update sorted row position
					_.each( rows, function( row ) {
						var group_id = $( row ).data( 'id' ),
							old_position = null,
							new_position = parseInt( $( row ).index(), 10 );

						if ( groups[ group_id ] ) {
							old_position = parseInt( groups[ group_id ].group_order, 10 );
						}

						if ( old_position !== new_position ) {
							changes[ group_id ] = _.extend( changes[ group_id ] || {}, { group_order : new_position } );
						}
					} );

					if ( _.size( changes ) ) {
						model.logChanges( changes );
						event.data.view.block();
						event.data.view.model.save();
					}
				},
				onAddNewGroupClick: function( event ) {
					event.preventDefault();

					$( this ).WCBackboneModal({
						template : 'oc-woo-shipping-add-group',
						variable : {
							//group_id : data.group_id
						}
					});
				},
				onAddNewGroupSubmitted: function ( event, target, posted_data ) {
					if ( 'oc-woo-shipping-add-group' === target ) {
						shippingGroupView.block();

						// Add a new group via ajax call
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_add_group', {
							oc_woo_shipping_groups_nonce : data.oc_woo_shipping_groups_nonce,
							group_name               : posted_data.new_group_name
						}, function( response, textStatus ) {
							if ( 'success' === textStatus && response.success ) {

								// Trigger save if there are changes, or just re-render
								if ( _.size( shippingGroupView.model.changes ) ) {
									shippingGroupView.model.save();
								} else {
									shippingGroupView.model.set( 'groups', response.data.groups );
									shippingGroupView.model.trigger( 'change:groups' );
									shippingGroupView.model.changes = {};
									shippingGroupView.model.trigger( 'saved:groups' );
								}
							}
							shippingGroupView.unblock();
						}, 'json' );
					}
				}
			} ),
			shippingGroup = new ShippingGroup({
				groups: data.groups
			} ),
			shippingGroupView = new ShippingGroupView({
				model:    shippingGroup,
				el:       $tbody
			} );

		shippingGroupView.render();

		$tbody.sortable({
			items: 'tr',
			cursor: 'move',
			axis: 'y',
			handle: 'td.oc-woo-shipping-group-sort',
			scrollSensitivity: 40
		});
	});
})( jQuery, shippingGroupsLocalizeScript, wp, ajaxurl );
