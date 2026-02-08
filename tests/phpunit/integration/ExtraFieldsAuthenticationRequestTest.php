<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\ExtraFieldsAuthenticationRequest;
use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use LegalLogin\PolicyData;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \LegalLogin\ExtraFieldsAuthenticationRequest
 * @group Database
 */
class ExtraFieldsAuthenticationRequestTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->editPage( 'Mediawiki:LegalLoginTestPolicy', 'Test policy contents' );
		$authConfig = $this->getConfVar( MainConfigNames::AuthManagerConfig );
		$authConfig['secondaryauth'][] = [
			'class' => ExtraFieldsSecondaryAuthenticationProvider::class,
			'services' => [ 'MainConfig' ],
			'sort' => 10,
		];
		$this->overrideConfigValue( MainConfigNames::AuthManagerConfig, $authConfig );
		$this->overrideConfigValue( 'LegalLoginPolicies', [
			'LegalLoginTestPolicy' => [ 'captionmsg' => 'privacy-policy-caption' ]
		] );
		$this->overrideConfigValue( 'LegalLoginQuestions', [] );
		$this->setContentLang( 'qqx' );
		$this->setUserLang( 'qqx' );
	}

	public function testGetFieldInfoReturnsFormFieldInfoFromPolicyData(): void {
		$req = new ExtraFieldsAuthenticationRequest();
		$info = $req->getFieldInfo();
		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'LegalLoginField', $info );
		$this->assertSame( PolicyData::getFormFieldInfo(), $info );
	}

	public function testLoadFromSubmissionReturnsFalseWhenNoLegalLoginField(): void {
		$req = new ExtraFieldsAuthenticationRequest();
		$this->assertFalse( $req->loadFromSubmission( [] ) );
	}

	public function testLoadFromSubmissionReturnsTrueWhenLegalLoginFieldPresent(): void {
		$req = new ExtraFieldsAuthenticationRequest();
		$this->assertTrue( $req->loadFromSubmission( [ 'LegalLoginField' => [] ] ) );
	}
}
