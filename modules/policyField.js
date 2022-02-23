( function () {
	'use strict';

	$( function () {
		var $policyCheckboxes = $( '.legal-login-field > div.mw-ui-checkbox input[type=checkbox]' ),
			policyCheckboxesChecked = false,
			$questionsRadio = $( '.legal-login-field input[type=radio]' ),
			$questionElements = $( '.legallogin-policy-question' ),
			questionCorrectAnswers = [],
			questionCorrectAnswersSelector,
			questionsAnswered = false,
			$submitButton = $( '#bodyContent button[type=submit]' ),
			submitButtonDisabledText = $submitButton.text(),
			submitButtonOriginalText = $( '#mw-input-wpLegalLoginSubmitButtonText' ).attr( 'value' );

		$( '#wpLegalLoginFieldEnableJS' ).toggle( false );

		$questionElements.each( function () {
			questionCorrectAnswers.push( $( this ).attr( 'data-legallogin-answer' ) );
		} );
		if ( questionCorrectAnswers.length ) {
			questionCorrectAnswersSelector = '#' + questionCorrectAnswers.join( ':checked, #' ) + ':checked';
		}

		$( '.legallogin-fpv-link' ).on( 'click', function () {
			var $element = $( this ),
				id = $element.attr( 'data' ),
				$textarea = $( '#' + id ),
				policyHtml = $textarea.html(),
				manager = OO.ui.getWindowManager(),
				$message = $( '<div>' ).html( policyHtml ).addClass( 'legallogin-policy-fullscreen-text' ),
				messageWindow;

			mw.hook( 'wikipage.content' ).fire( $message );

			messageWindow = manager.openWindow( 'message', $.extend( {
				message: $message
			}, {
				title: $element.parent().children( 'span' ).text(),
				size: 'full',
				actions: [
					{ action: close, label: OO.ui.deferMsg( 'legallogin-close' ), flags: [ 'primary', 'progressive' ] }
				]
			} ) );

			messageWindow.opened.then( function () {
				$( '#' + id + '-opened' ).val( true );
				$( '#' + id + '-checkbox' ).prop( 'disabled', false );
			} );
		} );

		function onPolicyTextareaScrolled() {
			if ( Math.round( this.scrollHeight - this.scrollTop - this.clientHeight ) <= 3 ) {
				$( '#' + this.id + '-scrolled' ).val( true );
				$( '#' + this.id + '-checkbox' ).prop( 'disabled', false );
				$( this ).off( 'scroll', onPolicyTextareaScrolled );
			}
		}

		$( '.legal-login-field .legalPolicyText' )
			.on( 'scroll', onPolicyTextareaScrolled )
			.each( function () {
				onPolicyTextareaScrolled.apply( this );
			} );

		function updateSubmitButton() {
			if ( policyCheckboxesChecked && questionsAnswered ) {
				$submitButton.text( submitButtonOriginalText );
				$submitButton.prop( 'disabled', false );
			} else {
				$submitButton.text( submitButtonDisabledText );
				$submitButton.prop( 'disabled', true );
			}
		}

		function checkPolicyCheckboxes() {
			policyCheckboxesChecked = $( '.legal-login-field input[type=checkbox]:checked' ).length === $policyCheckboxes.length;
		}

		$policyCheckboxes.on( 'change', function () {
			if ( this.checked ) {
				checkPolicyCheckboxes();
			} else if ( policyCheckboxesChecked ) {
				policyCheckboxesChecked = false;
			}
			updateSubmitButton();
		} );

		function checkQuestionAnswers() {
			questionsAnswered = questionCorrectAnswers.length === 0 ||
				questionCorrectAnswers.length === $( questionCorrectAnswersSelector ).length;
			updateSubmitButton();
		}

		$questionsRadio.on( 'change', checkQuestionAnswers );

		checkPolicyCheckboxes();
		checkQuestionAnswers();
	} );

}() );
