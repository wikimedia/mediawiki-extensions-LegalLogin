<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\AcceptanceLogFormatter;
use LegalLogin\LoginLogFormatter;
use ManualLogEntry;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \LegalLogin\AcceptanceLogFormatter
 * @covers \LegalLogin\LoginLogFormatter
 * @group Database
 */
class LogFormattersTest extends MediaWikiIntegrationTestCase {

	public function testAcceptanceLogFormatterGetActionMessage(): void {
		$this->editPage( 'MediaWiki:LegalLoginTestPolicy', 'Policy text' );
		$logEntry = new ManualLogEntry( 'legallogin', 'accept' );
		$logEntry->setPerformer( $this->getTestUser()->getUser() );
		$logEntry->setTarget( Title::newFromText( 'User:Test' ) );
		$logEntry->setParameters( [
			'4::policies' => [],
			'5::questions' => 0,
		] );
		$formatter = new AcceptanceLogFormatter( $logEntry );
		$formatter->setContext( RequestContext::newExtraneousContext( Title::newFromText( 'Special:Log' ) ) );
		$text = $formatter->getActionText();
		$this->assertIsString( $text );
		$this->assertNotEmpty( $text );
	}

	public function testLoginLogFormatterGetActionMessage(): void {
		$logEntry = new ManualLogEntry( 'legallogin', 'login' );
		$logEntry->setPerformer( $this->getTestUser()->getUser() );
		$logEntry->setTarget( $this->getTestUser()->getUser()->getUserPage() );
		$logEntry->setParameters( [
			'4::policies' => [],
			'5::count' => 1,
			'6::timestamp' => wfTimestampNow(),
			'7::requiredAcceptance' => false,
			'8::git' => [ 'sha1' => '', 'url' => '' ],
		] );
		$formatter = new LoginLogFormatter( $logEntry );
		$formatter->setContext( RequestContext::newExtraneousContext( Title::newFromText( 'Special:Log' ) ) );
		$text = $formatter->getActionText();
		$this->assertIsString( $text );
		$this->assertNotEmpty( $text );
	}
}
