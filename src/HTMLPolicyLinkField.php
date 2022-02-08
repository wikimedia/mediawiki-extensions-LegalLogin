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
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		$link = Html::element(
			'a',
			[
				'class' => 'legallogin-fpv-link',
				'data-id' => $this->mID,
				'data-html' => HTMLPolicyTextField::parse( $value['text'] ),
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
		];
		$fpvInputClicked = new HTMLHiddenField( $params );
		list( $name, $v, $params ) = $fpvInputClicked->getHiddenFieldData( $value );
		return Html::hidden( $name, $v, $params ) . "\n";
	}

	/**
	 * @inheritDoc
	 */
	public function getInputOOUI( $value ) {
		return false;
	}
}
