<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use LegalLogin\PolicyData;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \LegalLogin\PolicyData
 * @group Database
 */
class PolicyDataTest extends MediaWikiIntegrationTestCase {

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

	public function testGetConfigVariable(): void {
		$this->assertIsArray( PolicyData::getConfigVariable( 'LegalLoginPolicies' ) );
		$this->assertArrayHasKey( 'LegalLoginTestPolicy', PolicyData::getConfigVariable( 'LegalLoginPolicies' ) );
		$this->assertIsInt( PolicyData::getConfigVariable( 'LegalLoginExpiration' ) );
	}

	public function testGetFormFieldsData(): void {
		$data = PolicyData::getFormFieldsData();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
		$policy = $data[0];
		$this->assertSame( 'policy', $policy['type'] );
		$this->assertArrayHasKey( 'name', $policy );
		$this->assertArrayHasKey( 'policyId', $policy );
		$this->assertArrayHasKey( 'policyRevId', $policy );
		$this->assertArrayHasKey( 'text', $policy );
	}

	public function testGetFormFieldInfo(): void {
		$info = PolicyData::getFormFieldInfo();
		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'LegalLoginField', $info );
		$this->assertSame( 'null', $info['LegalLoginField']['type'] );
		$this->assertArrayHasKey( 'fieldsData', $info['LegalLoginField'] );
	}

	public function testHasUserAcceptedPoliciesWhenNoPoliciesRequired(): void {
		$this->overrideConfigValue( 'LegalLoginPolicies', [] );
		PolicyData::resetCurrentPoliciesCache();
		$user = $this->getTestUser()->getUser();
		$this->assertTrue( PolicyData::hasUserAcceptedPolicies( $user ) );
	}

	public function testCheckAllPoliciesWereAccepted(): void {
		$policies = [ 'LegalLoginTestPolicy' => '1' ];
		$accepted = [ 'LegalLoginTestPolicy' => [ '1', wfTimestamp( TS_MW, time() ) ] ];
		$this->assertTrue( PolicyData::checkAllPoliciesWereAccepted( $policies, $accepted ) );
	}

	public function testCheckAllPoliciesWereAcceptedReturnsFalseWhenMissing(): void {
		$policies = [ 'LegalLoginTestPolicy' => '1' ];
		$accepted = [];
		$this->assertFalse( PolicyData::checkAllPoliciesWereAccepted( $policies, $accepted ) );
	}
}
