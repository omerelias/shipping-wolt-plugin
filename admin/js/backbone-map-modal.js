/*global jQuery, Backbone, _ */
( function( $, Backbone, _ ) {
	'use strict';

	/**
	 * WooCommerce Backbone Modal plugin
	 *
	 * @param {object} options
	 */
	$.fn.OCBackboneMapModal = function( options ) {
		return this.each( function() {
			( new $.OCBackboneMapModal( $( this ), options ) );
		});
	};

	/**
	 * Initialize the Backbone Modal
	 *
	 * @param {object} element [description]
	 * @param {object} options [description]
	 */
	$.OCBackboneMapModal = function( element, options ) {
		// Set settings
		var settings = $.extend( {}, $.OCBackboneMapModal.defaultOptions, options );

		if ( settings.template ) {
			new $.OCBackboneMapModal.View({
				target: settings.template,
				string: settings.variable
			});
		}
	};

	/**
	 * Set default options
	 *
	 * @type {object}
	 */
	$.OCBackboneMapModal.defaultOptions = {
		template: '',
		variable: {}
	};

	/**
	 * Create the Backbone Modal
	 *
	 * @return {null}
	 */
	$.OCBackboneMapModal.View = Backbone.View.extend({
		tagName: 'div',
		id: 'wc-backbone-modal-dialog',
		_target: undefined,
		_string: undefined,
		allShapes: undefined,
		selectedShape: undefined,
		events: {
			'click .modal-close': 'closeButton',
			'click #btn-ok'     : 'addButton',
			'touchstart #btn-ok': 'addButton',
			'keydown'           : 'keyboardActions'
		},
		resizeContent: function() {
			var $content  = $( '.wc-backbone-modal-content' ).find( 'article' );
			var max_h     = $( window ).height() * 0.75;

			$content.css({
				'max-height': max_h + 'px',
				'height': max_h + 'px'
			});
		},
		initialize: function( data ) {
			var view     = this;
			this._target = data.target;
			this._string = data.string;
			this.allShapes = [];
			this.selectedShape = null;
			_.bindAll( this, 'render' );
			this.render();

			$( window ).resize(function() {
				view.resizeContent();
			});
		},
		render: function() {
			var template = wp.template( this._target );

			this.$el.append(
				template( this._string )
			);

			console.log(this._string);

			$( document.body ).css({
				'overflow': 'hidden'
			}).append( this.$el );

			this.renderMap();

			this.resizeContent();
			this.$( '.wc-backbone-modal-content' ).attr( 'tabindex' , '0' ).focus();

			$( document.body ).trigger( 'init_tooltips' );

			$( document.body ).trigger( 'oc_backbone_map_modal_loaded', this._target );
		},
		renderMap: function(){

			var $self = this;

			this.clearSelection();

			this.modalMap = new google.maps.Map(document.getElementById('modalMap'), {
				zoom : this._string.map_zoom? this._string.map_zoom : 12,
				center : this._string.map_location,
				mapTypeId : google.maps.MapTypeId.ROADMAP
			});

			this.drawingManager = new google.maps.drawing.DrawingManager({
				drawingMode: google.maps.drawing.OverlayType.POLYGON,
				drawingControl: true,
				drawingControlOptions: {
					position: google.maps.ControlPosition.TOP_CENTER,
					drawingModes: [
						google.maps.drawing.OverlayType.POLYGON
					]
				},
				polygonOptions: {
					fillColor: "#00ff00",
					fillOpacity: 0.3,
					strokeWeight: 1,
					draggable: true,
					editable: true
				},
				markerOptions: {
					//draggable: true,
					//editable: true
				}
			});

			this.initShapes();

			//Event when a shape is drawed
			google.maps.event.addListener(this.drawingManager, 'overlaycomplete', function(e) {
				// Switch to non-drawing mode after drawing a shape.
				$self.drawingManager.setDrawingMode(null);
				// Add an event listener on this new shape, and make clickable
				//Click = selected
				var newShape = e.overlay;

				console.log('shape coords added', $self.getShapeCoords(newShape));
				newShape.setOptions({
					clickable: true
				});
				newShape.type = e.type;
				google.maps.event.addListener(newShape, 'click', function() {
					$self.setSelection(newShape);
				});
				google.maps.event.addListener(newShape.getPath(), 'insert_at', function() {
					//alert('updated shape, go update it in your db');
					console.log("allshapes index updated =", $self.allShapes.indexOf(newShape));
					$self.allShapes[$self.allShapes.indexOf(newShape)] = newShape;
					$self.updateFormData();
				});
				google.maps.event.addListener(newShape.getPath(), 'set_at', function() {
					console.log('shape coords updated', $self.getShapeCoords(newShape));
					//alert('updated shape, go update it in your db');
					$self.allShapes[$self.allShapes.indexOf(newShape)] = newShape;
					$self.updateFormData();
				});
				$self.allShapes.push(newShape);
				$self.updateFormData();
				console.log("all shapes array", $self.allShapes);
				console.log("first coords", $self.getShapeCoords($self.allShapes[0]));
				//after drawing = set selected
				$self.setSelection(newShape);
			});

			this.drawingManager.setMap(this.modalMap);

			google.maps.event.addListener(this.modalMap, 'click', this.clearSelection);

			var removeShapeControlDiv = document.createElement("div");
			this.removeShapeControl(removeShapeControlDiv);
			this.modalMap.controls[google.maps.ControlPosition.TOP_CENTER].push(removeShapeControlDiv);
		},

		updateFormData: function () {

			var gmShapes = [];
			for (var i = 0; i < this.allShapes.length; i++) {
				gmShapes.push(this.getShapeCoords(this.allShapes[i]));
			}
			this.$el.find($('input[name="gm_shapes"]')).val(JSON.stringify( gmShapes).replace(/"([+-]?\d+(\.\d+)?)"/g, '$1'));
			this.$el.find($('input[name="gm_center"]')).val(JSON.stringify( this.modalMap.getCenter() ).replace(/"([+-]?\d+(\.\d+)?)"/g, '$1'));
			this.$el.find($('input[name="gm_zoom"]')).val(this.modalMap.getZoom());

			/*this.$el.find($('input[name="gm_shapes"]')).val( gmShapes );
			this.$el.find($('input[name="gm_center"]')).val( this.modalMap.getCenter() );
			this.$el.find($('input[name="gm_zoom"]')).val( this.modalMap.getZoom() );*/
		},

		initShapes: function() {

			if ( ! this._string.map_shapes ) return;

			var map_shapes_param = this._string.map_shapes; //JSON.parse(this._string.map_shapes.replace(/\\"/g, '"'));

			if (map_shapes_param.length) {

				var $self = this;

				$.each(map_shapes_param, function (ind, paths) {
					var shape = new google.maps.Polygon({
						paths: paths,
						fillColor: "#00ff00",
						fillOpacity: 0.3,
						strokeWeight: 1,
						draggable: true,
						editable: true,
						clickable: true
					});
					shape.setMap($self.modalMap);

					google.maps.event.addListener(shape, 'click', function() {
						$self.setSelection(shape);
					});

					google.maps.event.addListener(shape.getPath(), 'insert_at', function() {
						//alert('updated shape, go update it in your db');
						console.log("allshapes index updated =", $self.allShapes.indexOf(shape));
						$self.allShapes[$self.allShapes.indexOf(shape)] = shape;
						$self.updateFormData();
					});
					google.maps.event.addListener(shape.getPath(), 'set_at', function() {
						console.log('shape coords updated', $self.getShapeCoords(shape));
						//alert('updated shape, go update it in your db');
						$self.allShapes[$self.allShapes.indexOf(shape)] = shape;
						$self.updateFormData();
					});

					$self.allShapes.push(shape);

				});

				this.updateFormData();
			}
		},

		//make no selection
		clearSelection: function() {
			if (this.selectedShape) {
				this.selectedShape.setEditable(false);
				this.selectedShape = null;
			}
		},
		//set selection to a shape
	 	setSelection: function(shape) {
			this.clearSelection();
			this.selectedShape = shape;
			shape.setEditable(true);
		},
		//delete selected shape
		deleteSelectedShape: function() {
			if (!this.selectedShape) {
				//alert("There are no shape selected");
				return;
			}
			var index = this.allShapes.indexOf(this.selectedShape);
			this.allShapes.splice(index, 1);
			this.selectedShape.setMap(null);
			console.log("allshapes after removing one", this.allShapes);
		},
		//get path coords
		getShapeCoords: function(shape) {
			var path = shape.getPath();
			var coords = [];
			for (var i = 0; i < path.length; i++) {
				coords.push({
					lat: path.getAt(i).lat(),
					lng: path.getAt(i).lng()
				});
			}
			return coords;
		},

		removeShapeControl: function(controlDiv) {
			// Set CSS for the control border.
			var controlUI = document.createElement("div");
			controlUI.style.backgroundColor = "#fff";
			controlUI.style.border = "2px solid #fff";
			controlUI.style.borderRadius = "3px";
			controlUI.style.boxShadow = "0 2px 6px rgba(0,0,0,.3)";
			controlUI.style.cursor = "pointer";
			controlUI.style.marginTop = "8px";
			controlUI.style.marginBottom = "22px";
			controlUI.style.textAlign = "center";
			controlUI.title = "Remove selected shape";
			controlDiv.appendChild(controlUI);
			// Set CSS for the control interior.
			var controlText = document.createElement("div");
			controlText.style.color = "rgb(25,25,25)";
			controlText.style.fontFamily = "Roboto,Arial,sans-serif";
			controlText.style.fontSize = "16px";
			controlText.style.lineHeight = "38px";
			controlText.style.paddingLeft = "5px";
			controlText.style.paddingRight = "5px";
			controlText.innerHTML = "Remove";
			controlUI.appendChild(controlText);

			var $self = this;

			$(controlUI).on('click', function() {
				$self.deleteSelectedShape();
			});
		},

		closeButton: function( e ) {
			e.preventDefault();
			$( document.body ).trigger( 'oc_backbone_map_modal_before_remove', this._target );
			this.undelegateEvents();
			$( document ).off( 'focusin' );
			$( document.body ).css({
				'overflow': 'auto'
			});
			this.remove();
			$( document.body ).trigger( 'oc_backbone_map_modal_removed', this._target );
		},
		addButton: function( e ) {
			$( document.body ).trigger( 'oc_backbone_map_modal_response', [ this._target, this.getFormData() ] );
			this.closeButton( e );
		},
		getFormData: function() {
			var data = {};

			$( document.body ).trigger( 'oc_backbone_map_modal_before_update', this._target );

			$.each( $( 'form', this.$el ).serializeArray(), function( index, item ) {
				if ( item.name.indexOf( '[]' ) !== -1 ) {
					item.name = item.name.replace( '[]', '' );
					data[ item.name ] = $.makeArray( data[ item.name ] );
					data[ item.name ].push( item.value );
				} else {
					data[ item.name ] = item.value;
				}
			});

			return data;
		},
		keyboardActions: function( e ) {
			var button = e.keyCode || e.which;

			// Enter key
			if (
				13 === button &&
				! ( e.target.tagName && ( e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea' ) )
			) {
				this.addButton( e );
			}

			// ESC key
			if ( 27 === button ) {
				this.closeButton( e );
			}
		}
	});

}( jQuery, Backbone, _ ));
