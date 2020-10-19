( function () {
	'use strict';

	$( function () {
		$( '.legallogin-fpv-link' ).on( 'click', function () {
			var $element = $( this ),
				id = $element.attr( 'data-id' ),
				text = $element.attr( 'data-text' ),
				manager = OO.ui.getWindowManager(),
				messageWindow = manager.openWindow( 'message', $.extend( {
					message: $( '<div>' ).text( text ).addClass( 'legallogin-policy-fullscreen-text' )
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
