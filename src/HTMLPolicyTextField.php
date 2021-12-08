<?php
namespace LegalLogin;

use Html;
use HTMLFormField;
use HTMLHiddenField;
use MediaWiki\MediaWikiServices;
use MWException;
use MWTimestamp;
use ParserOutput;
use Title;

class HTMLPolicyTextField extends HTMLFormField {

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
		$return .= $this->makePolicyTextField( $value['text'] );
		return $return;
	}

	/**
	 * @param string $string
	 * @param Title|null $title
	 * @return ParserOutput|string
	 */
	public static function parse( string $string, ?Title $title = null ) {
		$out = MediaWikiServices::getInstance()->getMessageCache()->parse(
			$string,
			$title ?? Title::newMainPage(),
			true,
			false
		);

		return $out instanceof ParserOutput
			? $out->getText( [
				'enableSectionEditLinks' => false,
				// Wrapping messages in an extra <div> is probably not expected. If
				// they're outside the content area they probably shouldn't be
				// targeted by CSS that's targeting the parser output, and if
				// they're inside they already are from the outer div.
				'unwrap' => true,
			] )
			: $out;
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

	/**
	 * @param string $text
	 * @return string
	 */
	private function makePolicyTextField( $text ) {
		$html = self::parse( $text );
		$style = <<<OED
display: inline-block;
max-height: 600px;
width: 600px;
overflow-x: scroll;
border: 1px solid;
padding: .5em;
OED;
		$attribs = [
			'id' => $this->mID,
			'style' => $style,
			'class' => 'legalPolicyText'
		];
		$inputHtml = $this->makeHiddenField( 'text', $text );
		return $inputHtml . Html::rawElement( 'div', $attribs, $html );
	}
}
