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
