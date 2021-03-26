<?php
namespace LegalLogin;

use Html;
use HTMLHiddenField;
use HTMLRadioField;

class HTMLPolicyQuestionField extends HTMLRadioField {

	/**
	 * @inheritDoc
	 */
	public function getInputHTML( $value ) {
		$radioContents = parent::getInputHTML( $value ) . $this->getHelpTextHtmlDiv( $this->getHelpText() );
		$radio = Html::rawElement( 'div', [], $radioContents );
		$label = Html::element( 'label', [], $this->mLabel );
		$answer = $this->mParams['correctAnswer'];
		$revField = new HTMLHiddenField( [
			'name' => $this->mParams['name'] . '-revId',
			'fieldname' => $this->mParams['name'] . '-revId',
			'default' => $this->mParams['revId'],
		] );
		list( $name, $v, $params ) = $revField->getHiddenFieldData( $value );
		$revFieldHtml = Html::hidden( $name, $v, $params ) . "\n";
		$attr = [
			'class' => 'legallogin-policy-question',
			'data-legallogin-answer' => $this->mID . '-' . $answer,
		];
		return Html::rawElement( 'div', $attr, $label . ' ' . $radio . $revFieldHtml );
	}

	/**
	 * @inheritDoc
	 */
	public function getInputOOUI( $value ) {
		return false;
	}
}
