<?php
namespace LegalLogin;

use Html;
use HTMLFormField;
use HTMLHiddenField;
use MWException;
use MWTimestamp;

class HTMLPolicyLinkField extends HTMLFormField {

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function getInputHTML( $value ) {
		$ts = $value['timestamp'];
		$revDate = MWTimestamp::getInstance( $ts )->format( 'Y-m-d' );
		$fpvText = $this->msg( 'legallogin-policy-text-caption', $value['policyId'], $revDate );
		$link = Html::element(
			'a',
			[
				'class' => 'legallogin-fpv-link',
				// prefixing with data-mw ensures that MW parser will never
				// output that attribute. This is a defense in case of an
				// error message that uses wfMessage()->parse and takes a
				// user controllable parameter.
				'data-mw-ll-id' => $this->mID,
				'data-mw-ll-html' => HTMLPolicyTextField::parse( $value['text'] ),
			],
			$value['caption'] . ' ' . $fpvText
		);
		$return = Html::rawElement( 'div', [], $link );
		$return .= $this->makeHiddenField( 'policyId', $value['policyId'] );
		$return .= $this->makeHiddenField( 'policyRevId', $value['policyRevId'] );
		$return .= $this->makeHiddenField( 'opened', $value['opened'] ?? false );
		return $return;
	}

	/**
	 * @param string $namePostfix
	 * @param mixed $value
	 * @return string
	 * @throws MWException
	 */
	private function makeHiddenField( string $namePostfix, $value ) {
		$params = [
			'name' => $this->mParams['name'] . '-' . $namePostfix,
			'fieldname' => $this->mParams['name'] . '-' . $namePostfix,
			'default' => $value,
			'parent' => $this->mParent
		];
		$fpvInputClicked = new HTMLHiddenField( $params );
		[ $name, $v, $params ] = $fpvInputClicked->getHiddenFieldData( $value );
		return Html::hidden( $name, $v, $params ) . "\n";
	}

	/**
	 * @inheritDoc
	 */
	public function getInputOOUI( $value ) {
		return false;
	}
}
