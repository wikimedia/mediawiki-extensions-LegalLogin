( function () {
	'use strict';

	$( function () {
		$( '.legallogin-fpv-link' ).on( 'click', function () {
			var $element = $( this ),
				id = $element.attr( 'data-id' ),
				policyHtml = $element.attr( 'data-html' ),
				manager = OO.ui.getWindowManager(),
				$message = $( '<div>' ).html( policyHtml ).addClass( 'legallogin-policy-fullscreen-text' ),
				messageWindow;

			mw.hook( 'wikipage.content' ).fire( $message );

			messageWindow = manager.openWindow( 'message', $.extend( {
				message: $message
			}, {
				title: $element.text(),
				size: 'full',
				actions: [
					{ action: close, label: OO.ui.deferMsg( 'legallogin-close' ), flags: [ 'primary', 'progressive' ] }
				]
			} ) );

			messageWindow.opened.then( function () {
				$( '#' + id + '-opened' ).val( true );
			} );
		} );
	} );

}() );
