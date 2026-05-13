/* global OCWSWolt, jQuery */
( function ( $ ) {
	'use strict';

	function ajax( action, data ) {
		return $.post( OCWSWolt.ajaxUrl, $.extend( { action: action, nonce: OCWSWolt.nonce }, data || {} ) );
	}

	/* ─── Test connection ──────────────────────────────────────── */

	$( '#ocws-wolt-test-connection' ).on( 'click', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $msg = $( '#ocws-wolt-test-result' );
		$msg.removeClass( 'is-ok is-bad' ).text( OCWSWolt.i18n.testing );
		$btn.prop( 'disabled', true );

		ajax( 'ocws_wolt_test_connection' )
			.done( function ( res ) {
				if ( res && res.success ) {
					var count = ( res.data && typeof res.data.count !== 'undefined' ) ? res.data.count : 0;
					$msg.addClass( 'is-ok' ).text( OCWSWolt.i18n.connOk.replace( '%d', count ) );
				} else {
					var err = ( res && res.data && res.data.message ) ? res.data.message : OCWSWolt.i18n.connFail;
					$msg.addClass( 'is-bad' ).text( OCWSWolt.i18n.connFail + ' — ' + err );
				}
			} )
			.fail( function () {
				$msg.addClass( 'is-bad' ).text( OCWSWolt.i18n.connFail );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	/* ─── Generate webhook secret ──────────────────────────────── */

	$( '#ocws-wolt-generate-secret' ).on( 'click', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		$btn.prop( 'disabled', true );

		ajax( 'ocws_wolt_generate_secret' )
			.done( function ( res ) {
				if ( res && res.success && res.data && res.data.secret ) {
					$( '#ocws-wolt-webhook-secret' ).val( res.data.secret ).trigger( 'change' );
				}
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	/* ─── Copy buttons ─────────────────────────────────────────── */

	$( '.ocws-wolt-copy' ).on( 'click', function ( e ) {
		e.preventDefault();
		var $btn    = $( this );
		var target  = $btn.data( 'copyTarget' );
		var $target = $( target );
		if ( ! $target.length ) {
			return;
		}
		$target.trigger( 'select' );
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( _err ) {
			ok = false;
		}
		if ( ok ) {
			var originalLabel = $btn.text();
			$btn.addClass( 'is-copied' ).text( OCWSWolt.i18n.copied );
			setTimeout( function () {
				$btn.removeClass( 'is-copied' ).text( originalLabel );
			}, 1400 );
		}
	} );

	/* ─── Register / Unregister webhook ───────────────────────── */

	function setWebhookMsg( text, cls ) {
		var $msg = $( '#ocws-wolt-webhook-msg' );
		$msg.removeClass( 'is-ok is-bad' );
		if ( cls ) {
			$msg.addClass( cls );
		}
		$msg.text( text || '' );
	}

	$( '#ocws-wolt-register-webhook' ).on( 'click', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		setWebhookMsg( OCWSWolt.i18n.registering );
		$btn.prop( 'disabled', true );

		ajax( 'ocws_wolt_register_webhook' )
			.done( function ( res ) {
				if ( res && res.success ) {
					setWebhookMsg( OCWSWolt.i18n.registerOk, 'is-ok' );
					setTimeout( function () { window.location.reload(); }, 900 );
				} else {
					var err = ( res && res.data && res.data.message ) ? res.data.message : 'Failed';
					setWebhookMsg( err, 'is-bad' );
					$btn.prop( 'disabled', false );
				}
			} )
			.fail( function () {
				setWebhookMsg( 'Request failed.', 'is-bad' );
				$btn.prop( 'disabled', false );
			} );
	} );

	$( '#ocws-wolt-unregister-webhook' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( ! window.confirm( OCWSWolt.i18n.confirmUnreg ) ) {
			return;
		}
		var $btn = $( this );
		setWebhookMsg( OCWSWolt.i18n.unregistering );
		$btn.prop( 'disabled', true );

		ajax( 'ocws_wolt_unregister_webhook' )
			.done( function ( res ) {
				if ( res && res.success ) {
					setWebhookMsg( OCWSWolt.i18n.unregisterOk, 'is-ok' );
					setTimeout( function () { window.location.reload(); }, 700 );
				} else {
					var err = ( res && res.data && res.data.message ) ? res.data.message : 'Failed';
					setWebhookMsg( err, 'is-bad' );
					$btn.prop( 'disabled', false );
				}
			} )
			.fail( function () {
				setWebhookMsg( 'Request failed.', 'is-bad' );
				$btn.prop( 'disabled', false );
			} );
	} );

	/* ─── Cancel delivery (Deliveries tab) ────────────────────── */

	var CANCEL_REASONS = [
		{ value: 'customer_request',        label: 'Customer requested' },
		{ value: 'merchant_out_of_stock',   label: 'Out of stock' },
		{ value: 'wrong_address',           label: 'Wrong address' },
		{ value: 'other',                   label: 'Other' }
	];

	function buildCancelDialog( orderId ) {
		var $overlay = $( '<div class="ocws-wolt-modal-overlay" />' );
		var $modal   = $( '<div class="ocws-wolt-modal" />' );
		var $title   = $( '<h3 />' ).text( OCWSWolt.i18n.confirmCancel );
		var $select  = $( '<select class="ocws-wolt-reason-select" />' );
		$select.append( $( '<option />' ).val( '' ).text( '— select reason —' ) );
		CANCEL_REASONS.forEach( function ( r ) {
			$select.append( $( '<option />' ).val( r.value ).text( r.label ) );
		} );
		var $actions = $( '<div class="ocws-wolt-modal-actions" />' );
		var $btnNo   = $( '<button type="button" class="button" />' ).text( 'Back' );
		var $btnYes  = $( '<button type="button" class="button button-primary ocws-wolt-modal-confirm" />' ).text( 'Confirm cancel' );
		var $msg     = $( '<div class="ocws-wolt-modal-msg" />' );
		$actions.append( $btnNo, $btnYes );
		$modal.append( $title, $select, $actions, $msg );
		$overlay.append( $modal );
		$( 'body' ).append( $overlay );

		function close() { $overlay.remove(); }
		$btnNo.on( 'click', close );
		$overlay.on( 'click', function ( e ) { if ( e.target === $overlay[0] ) { close(); } } );

		$btnYes.on( 'click', function () {
			var reason = $select.val();
			if ( ! reason ) {
				$msg.text( OCWSWolt.i18n.reasonRequired ).addClass( 'is-bad' );
				return;
			}
			$btnYes.prop( 'disabled', true );
			$msg.removeClass( 'is-bad' ).text( OCWSWolt.i18n.cancelling );
			ajax( 'ocws_wolt_cancel_delivery', { order_id: orderId, reason: reason } )
				.done( function ( res ) {
					if ( res && res.success ) {
						$msg.text( OCWSWolt.i18n.cancelOk );
						setTimeout( function () { window.location.reload(); }, 700 );
					} else {
						var err = ( res && res.data && res.data.message ) ? res.data.message : 'Failed';
						$msg.addClass( 'is-bad' ).text( err );
						$btnYes.prop( 'disabled', false );
					}
				} )
				.fail( function () {
					$msg.addClass( 'is-bad' ).text( 'Request failed.' );
					$btnYes.prop( 'disabled', false );
				} );
		} );
	}

	$( document ).on( 'click', '.ocws-wolt-btn-cancel', function ( e ) {
		e.preventDefault();
		buildCancelDialog( $( this ).data( 'orderId' ) );
	} );

	/* ─── Quote simulator ─────────────────────────────────────── */

	$( '#ocws-wolt-sim-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		var $form   = $( this );
		var $result = $( '#ocws-wolt-sim-result' );
		$result.removeClass( 'is-visible' ).html( '<p>' + OCWSWolt.i18n.simRunning + '</p>' ).addClass( 'is-visible' );

		var data = {
			address:    $form.find( '[name="address"]' ).val(),
			lat:        $form.find( '[name="lat"]' ).val(),
			lng:        $form.find( '[name="lng"]' ).val(),
			slot_date:  $form.find( '[name="slot_date"]' ).val(),
			slot_start: $form.find( '[name="slot_start"]' ).val()
		};

		ajax( 'ocws_wolt_simulate', data )
			.done( function ( res ) {
				if ( res && res.success && res.data && res.data.html ) {
					$result.html( res.data.html );
				} else {
					var err = ( res && res.data && res.data.message ) ? res.data.message : 'Unknown error';
					$result.html( '<p class="ocws-wolt-error">' + err + '</p>' );
				}
			} )
			.fail( function () {
				$result.html( '<p class="ocws-wolt-error">Request failed.</p>' );
			} );
	} );
}( jQuery ) );
