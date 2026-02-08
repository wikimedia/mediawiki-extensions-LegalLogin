<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\HTMLPolicyTextField;
use MediaWikiIntegrationTestCase;

/**
 * @covers \LegalLogin\HTMLPolicyTextField::parse
 * @group Database
 */
class HTMLPolicyTextFieldTest extends MediaWikiIntegrationTestCase {

	public function testParseReturnsStringForWikitext(): void {
		$html = HTMLPolicyTextField::parse( "Hello '''world'''", null );
		$this->assertIsString( $html );
		$this->assertStringContainsString( 'world', $html );
		// Parsed bold
		$this->assertMatchesRegularExpression( '/<b>.*world.*<\/b>/', $html );
	}

	public function testParseOutputHasNoSectionEditLinks(): void {
		// parse() uses getContentHolderText(), so output is raw body fragment
		// without the output pipeline (no section edit links). Same approach as
		// ParserOutputTest::testWrapperDivClass (assertStringNotContainsString on markup).
		$html = HTMLPolicyTextField::parse( '== Heading ==', null );
		$this->assertIsString( $html );
		$this->assertStringNotContainsString( 'mw-editsection', $html );
	}
}
