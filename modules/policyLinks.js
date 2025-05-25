( function () {
	'use strict';

	$( () => {
		$( 'a.legallogin-fpv-link' ).on( 'click', function () {
			let $element = $( this ),
				id = $element.attr( 'data-mw-ll-id' ),
				// We prefix this with data-mw to ensure that the MW
				// parser will never output an element with that attribute.
				policyHtml = $element.attr( 'data-mw-ll-html' ),
				manager = OO.ui.getWindowManager(),
				$message = $( '<div>' ).html( policyHtml ).addClass( 'legallogin-policy-fullscreen-text' ),
				messageWindow;

			mw.hook( 'wikipage.content' ).fire( $message );

			messageWindow = manager.openWindow( 'message', Object.assign( {
				message: $message
			}, {
				title: $element.text(),
				size: 'full',
				actions: [
					{ action: close, label: OO.ui.deferMsg( 'legallogin-close' ), flags: [ 'primary', 'progressive' ] }
				]
			} ) );

			messageWindow.opened.then( () => {
				$( '#' + id + '-opened' ).val( true );
			} );
		} );
	} );

}() );
