<?php
namespace LegalLogin;

use Html;
use HTMLHiddenField;
use HTMLTextAreaField;
use MWException;
use MWTimestamp;

class HTMLPolicyTextField extends HTMLTextAreaField {

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function getInputHTML( $value ) {
		$captionHtml = [];
		$captionHtml[] = Html::element( 'span', [], $value['caption'] );
		$ts = $value['timestamp'];
		$revDate = MWTimestamp::getInstance( $ts )->format( 'Y-m-d' );
		$revText = $this->msg( 'legallogin-policy-text-caption', $value['policyId'], $revDate );
		$captionHtml[] = Html::element( 'span', [], $revText->text() );
		$fpvText = $this->msg( 'legallogin-policy-full-page-view' )->text();
		$captionHtml[] = Html::element(
			'a',
			[
				'class' => 'legallogin-fpv-link',
				'data' => $this->mID,
			],
			$fpvText
		);

		$return = Html::rawElement(
			'div',
			[ 'class' => 'legallogin-policy-text-caption' ],
			implode( ' ',  $captionHtml )
		);

		$return .= $this->makeHiddenField( 'policyId', $value['policyId'] );
		$return .= $this->makeHiddenField( 'policyRevId', $value['policyRevId'] );
		$return .= $this->makeHiddenField( 'opened', $value['opened'] ?? false );
		$return .= $this->makeHiddenField( 'scrolled', $value['scrolled'] ?? false );
		$return .= parent::getInputHTML( $value['text'] );
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
