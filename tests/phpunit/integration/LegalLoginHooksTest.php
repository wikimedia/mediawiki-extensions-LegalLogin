<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\ExtraFieldsAuthenticationRequest;
use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use LegalLogin\PolicyLinks;
use LegalLogin\PolicyLinksAuthenticationRequest;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \LegalLoginHooks::onAuthChangeFormFields
 * @group Database
 */
class LegalLoginHooksTest extends MediaWikiIntegrationTestCase {

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

	public function testOnAuthChangeFormFieldsForLoginAddsLegalLoginPolicyLinksDescriptor(): void {
		$requests = [ new PolicyLinksAuthenticationRequest() ];
		$fieldInfo = AuthenticationRequest::mergeFieldInfo( $requests );
		$formDescriptor = [];
		foreach ( $fieldInfo as $name => $info ) {
			$formDescriptor[$name] = [ 'type' => 'check', 'name' => $name ];
		}

		\LegalLoginHooks::onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, AuthManager::ACTION_LOGIN );

		$this->assertArrayNotHasKey( 'LegalLoginTestPolicy', $formDescriptor );
		$this->assertArrayHasKey( 'LegalLoginPolicyLinks', $formDescriptor );
		$this->assertSame( PolicyLinks::class, $formDescriptor['LegalLoginPolicyLinks']['class'] );
		$this->assertSame( 'LegalLoginPolicyLinks', $formDescriptor['LegalLoginPolicyLinks']['name'] );
		$this->assertArrayHasKey( 'fieldsData', $formDescriptor['LegalLoginPolicyLinks'] );
	}

	public function testOnAuthChangeFormFieldsForCreateAddsLegalLoginField(): void {
		$requests = [ new ExtraFieldsAuthenticationRequest() ];
		$fieldInfo = AuthenticationRequest::mergeFieldInfo( $requests );
		$formDescriptor = [
			'createaccount' => [ 'type' => 'submit', 'default' => 'Create account' ],
		];

		\LegalLoginHooks::onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, AuthManager::ACTION_CREATE );

		$this->assertTrue( $formDescriptor['createaccount']['disabled'] );
		$this->assertArrayHasKey( 'LegalLoginField', $formDescriptor );
		$this->assertArrayHasKey( 'LegalLoginSubmitButtonText', $formDescriptor );
	}

	public function testOnAuthChangeFormFieldsDoesNothingWhenNoPoliciesOrQuestions(): void {
		$this->overrideConfigValue( 'LegalLoginPolicies', [] );
		$this->overrideConfigValue( 'LegalLoginQuestions', [] );
		$requests = [];
		$fieldInfo = [];
		$formDescriptor = [ 'username' => [] ];

		\LegalLoginHooks::onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, AuthManager::ACTION_LOGIN );

		$this->assertSame( [ 'username' => [] ], $formDescriptor );
	}
}
