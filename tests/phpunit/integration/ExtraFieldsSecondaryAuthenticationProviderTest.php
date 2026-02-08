<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\ExtraFieldsAuthenticationRequest;
use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use LegalLogin\PolicyLinksAuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \LegalLogin\ExtraFieldsSecondaryAuthenticationProvider
 * @group Database
 */
class ExtraFieldsSecondaryAuthenticationProviderTest extends MediaWikiIntegrationTestCase {

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

	public function testGetAuthenticationRequestsForLogin(): void {
		$provider = new ExtraFieldsSecondaryAuthenticationProvider();
		$reqs = $provider->getAuthenticationRequests( AuthManager::ACTION_LOGIN, [] );
		$this->assertCount( 1, $reqs );
		$this->assertInstanceOf( PolicyLinksAuthenticationRequest::class, $reqs[0] );
	}

	public function testGetAuthenticationRequestsForCreate(): void {
		$provider = new ExtraFieldsSecondaryAuthenticationProvider();
		$reqs = $provider->getAuthenticationRequests( AuthManager::ACTION_CREATE, [] );
		$this->assertCount( 1, $reqs );
		$this->assertInstanceOf( ExtraFieldsAuthenticationRequest::class, $reqs[0] );
	}

	public function testGetAuthenticationRequestsForCreateWithUsernameReturnsEmpty(): void {
		$provider = new ExtraFieldsSecondaryAuthenticationProvider();
		$reqs = $provider->getAuthenticationRequests( AuthManager::ACTION_CREATE, [ 'username' => 'Test' ] );
		$this->assertSame( [], $reqs );
	}

	public function testBeginSecondaryAuthenticationReturnsAbstain(): void {
		$provider = new ExtraFieldsSecondaryAuthenticationProvider();
		$user = $this->getTestUser()->getUser();
		$reqs = [ new PolicyLinksAuthenticationRequest() ];
		$response = $provider->beginSecondaryAuthentication( $user, $reqs );
		$this->assertSame( AuthenticationResponse::ABSTAIN, $response->status );
	}

	public function testBeginSecondaryAccountCreationReturnsAbstain(): void {
		$provider = new ExtraFieldsSecondaryAuthenticationProvider();
		$user = $this->getTestUser()->getUser();
		$creator = $this->getTestUser()->getUser();
		$reqs = [];
		$response = $provider->beginSecondaryAccountCreation( $user, $creator, $reqs );
		$this->assertSame( AuthenticationResponse::ABSTAIN, $response->status );
	}
}
