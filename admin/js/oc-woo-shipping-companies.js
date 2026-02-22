/* global shippingCompaniesLocalizeScript, ajaxurl */
( function( $, data, wp, ajaxurl ) {
	$( function() {
		var $table          = $( '.oc-woo-shipping-companies' ),
			$tbody          = $( '.oc-woo-shipping-company-rows' ),
			$save_button    = $( '.oc-woo-shipping-company-save' ),
			$row_template   = wp.template( 'oc-woo-shipping-company-row' ),
			$blank_template = wp.template( 'oc-woo-shipping-company-row-blank' ),

			// Backbone model
			ShippingCompany       = Backbone.Model.extend({
				changes: {},
				logChanges: function( changedRows ) {
					var changes = this.changes || {};

					_.each( changedRows, function( row, id ) {
						changes[ id ] = _.extend( changes[ id ] || { company_id : id }, row );
					} );

					this.changes = changes;
					this.trigger( 'change:companies' );
				},
				discardChanges: function( id ) {
					var changes      = this.changes || {},
						set_position = null,
						companies        = _.indexBy( this.get( 'companies' ), 'company_id' );

					// Find current set position if it has moved since last save
					if ( changes[ id ] && changes[ id ].company_order !== undefined ) {
						set_position = changes[ id ].company_order;
					}

					// Delete all changes
					delete changes[ id ];

					// If the position was set, and this company does exist in DB, set the position again so the changes are not lost.
					if ( set_position !== null && companies[ id ] && companies[ id ].company_order !== set_position ) {
						changes[ id ] = _.extend( changes[ id ] || {}, { company_id : id, company_order : set_position } );
					}

					this.changes = changes;

					// No changes? Disable save button.
					if ( 0 === _.size( this.changes ) ) {
						shippingCompanyView.clearUnloadConfirmation();
					}
				},
				save: function() {
					if ( _.size( this.changes ) ) {
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_companies_save_changes', {
							oc_woo_shipping_companies_nonce : data.oc_woo_shipping_companies_nonce,
							changes                 : this.changes
						}, this.onSaveResponse, 'json' );
					} else {
						shippingCompany.trigger( 'saved:companies' );
					}
				},
				onSaveResponse: function( response, textStatus ) {
					if ( 'success' === textStatus ) {
						if ( response.success ) {
							shippingCompany.set( 'companies', response.data.companies );
							shippingCompany.trigger( 'change:companies' );
							shippingCompany.changes = {};
							shippingCompany.trigger( 'saved:companies' );
						} else {
							window.alert( data.strings.save_failed );
						}
					}
				}
			} ),

			// Backbone view
			ShippingCompanyView = Backbone.View.extend({
				rowTemplate: $row_template,
				initialize: function() {
					this.listenTo( this.model, 'change:companies', this.setUnloadConfirmation );
					this.listenTo( this.model, 'saved:companies', this.clearUnloadConfirmation );
					this.listenTo( this.model, 'saved:companies', this.render );
					$tbody.on( 'change', { view: this }, this.updateModelOnChange );
					$tbody.on( 'sortupdate', { view: this }, this.updateModelOnSort );
					$( window ).on( 'beforeunload', { view: this }, this.unloadConfirmation );
					$( document.body ).on( 'click', '.oc-woo-shipping-company-add', { view: this }, this.onAddNewCompanyClick );
					$( document.body ).on( 'wc_backbone_modal_response', this.onAddNewCompanySubmitted );
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
					var companies = _.indexBy( this.model.get( 'companies' ), 'company_id' ),
						view  = this;

					view.$el.empty();
					view.unblock();

					if ( _.size( companies ) ) {
						// Sort companies
						companies = _( companies )
							.chain()
							.sortBy( function ( company ) { return parseInt( company.company_id, 10 ); } )
							.sortBy( function ( company ) { return parseInt( company.company_order, 10 ); } )
							.value();

						// Populate $tbody with the current companies
						$.each( companies, function( id, rowData ) {

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
					var $tr = view.$el.find( 'tr[data-id="' + rowData.company_id + '"]');

					$tr.find( '.oc-woo-shipping-company-delete' ).on( 'click', { view: this }, this.onDeleteRow );
					$tr.find( '.oc-woo-shipping-company-edit').on( 'click', { view: this }, this.onEditCompany );
					$tr.find( '.oc-woo-shipping-company-save').on( 'click', { view: this }, this.onSaveCompany );
				},
				initRows: function() {
					// Stripe
					if ( 0 === ( $( 'tbody.oc-woo-shipping-company-rows tr' ).length % 2 ) ) {
						$table.find( 'tbody.oc-woo-shipping-company-rows' ).next( 'tbody' ).find( 'tr' ).addClass( 'odd' );
					} else {
						$table.find( 'tbody.oc-woo-shipping-company-rows' ).next( 'tbody' ).find( 'tr' ).removeClass( 'odd' );
					}
					// Tooltips
					$( '#tiptip_holder' ).removeAttr( 'style' );
					$( '#tiptip_arrow' ).removeAttr( 'style' );
					$( '.tips' ).tipTip({ 'attribute': 'data-tip', 'fadeIn': 50, 'fadeOut': 50, 'delay': 50 });
				},
				onDeleteRow: function( event ) {
					var view    = event.data.view,
						model   = view.model,
						companies   = _.indexBy( model.get( 'companies' ), 'company_id' ),
						changes = {},
						row     = $( this ).closest('tr'),
						company_id = row.data('id');

					event.preventDefault();

					if ( window.confirm( data.strings.delete_confirmation_msg ) ) {
						if ( companies[ company_id ] ) {
							delete companies[ company_id ];
							changes[ company_id ] = _.extend( changes[ company_id ] || {}, { deleted : 'deleted' } );
							model.set( 'companies', companies );
							model.logChanges( changes );
							event.data.view.block();
							event.data.view.model.save();
						}
					}
				},
				onEditCompany: function ( event ) {

					var $target   = $( event.target),
						td = $target.closest('td'),
						input = td.find('input'),
						span = td.find('span'),
						edit = td.find('a.oc-woo-shipping-company-edit'),
						save = td.find('a.oc-woo-shipping-company-save');

					event.preventDefault();

					$(span).hide();
					$(edit).hide();
					$(input).show();
					$(save).show();
				},
				onSaveCompany: function ( event ) {

					var $target   = $( event.target),
						view    = event.data.view,
						model   = view.model,
						td = $target.closest('td'),
						input = td.find('input'),
						span = td.find('span'),
						edit = td.find('a.oc-woo-shipping-company-edit'),
						save = td.find('a.oc-woo-shipping-company-save'),
						row     = $( this ).closest('tr'),
						company_id = row.data('id'),
						companies   = _.indexBy( model.get( 'companies' ), 'company_id'),
						changes = {};

					event.preventDefault();

					var value = $(input).val().replace(/\s/g, "");
					if (value == '') {
						return;
					}
					$(span).show();
					$(edit).show();
					$(input).hide();
					$(save).hide();

					if ( companies[ company_id ] ) {
						changes[ company_id ] = _.extend( changes[ company_id ] || {}, { company_name : value } );
						model.set( 'companies', companies );
						model.logChanges( changes );
						event.data.view.block();
						event.data.view.model.save();
					}
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
						company_id   = $target.closest( 'tr' ).data( 'id' ),
						attribute = $target.data( 'attribute' ),
						value     = $target.val(),
						companies   = _.indexBy( model.get( 'companies' ), 'company_id' ),
						changes = {};

					if ( ! companies[ company_id ] || companies[ company_id ][ attribute ] !== value ) {
						changes[ company_id ] = {};
						changes[ company_id ][ attribute ] = value;
					}

					model.logChanges( changes );
				},
				updateModelOnSort: function( event ) {
					var view    = event.data.view,
						model   = view.model,
						companies   = _.indexBy( model.get( 'companies' ), 'company_id' ),
						rows    = $( 'tbody.oc-woo-shipping-company-rows tr' ),
						changes = {};

					// Update sorted row position
					_.each( rows, function( row ) {
						var company_id = $( row ).data( 'id' ),
							old_position = null,
							new_position = parseInt( $( row ).index(), 10 );

						if ( companies[ company_id ] ) {
							old_position = parseInt( companies[ company_id ].company_order, 10 );
						}

						if ( old_position !== new_position ) {
							changes[ company_id ] = _.extend( changes[ company_id ] || {}, { company_order : new_position } );
						}
					} );

					if ( _.size( changes ) ) {
						model.logChanges( changes );
						event.data.view.block();
						event.data.view.model.save();
					}
				},
				onAddNewCompanyClick: function( event ) {
					event.preventDefault();

					$( this ).WCBackboneModal({
						template : 'oc-woo-shipping-add-company',
						variable : {
							//company_id : data.company_id
						}
					});
				},
				onAddNewCompanySubmitted: function ( event, target, posted_data ) {
					if ( 'oc-woo-shipping-add-company' === target ) {
						shippingCompanyView.block();

						// Add a new company via ajax call
						$.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=oc_woo_shipping_add_company', {
							oc_woo_shipping_companies_nonce : data.oc_woo_shipping_companies_nonce,
							company_name               : posted_data.new_company_name
						}, function( response, textStatus ) {
							if ( 'success' === textStatus && response.success ) {

								// Trigger save if there are changes, or just re-render
								if ( _.size( shippingCompanyView.model.changes ) ) {
									shippingCompanyView.model.save();
								} else {
									shippingCompanyView.model.set( 'companies', response.data.companies );
									shippingCompanyView.model.trigger( 'change:companies' );
									shippingCompanyView.model.changes = {};
									shippingCompanyView.model.trigger( 'saved:companies' );
								}
							}
							shippingCompanyView.unblock();
						}, 'json' );
					}
				}
			} ),
			shippingCompany = new ShippingCompany({
				companies: data.companies
			} ),
			shippingCompanyView = new ShippingCompanyView({
				model:    shippingCompany,
				el:       $tbody
			} );

		shippingCompanyView.render();

		$tbody.sortable({
			items: 'tr',
			cursor: 'move',
			axis: 'y',
			handle: 'td.oc-woo-shipping-company-sort',
			scrollSensitivity: 40
		});
	});
})( jQuery, shippingCompaniesLocalizeScript, wp, ajaxurl );
