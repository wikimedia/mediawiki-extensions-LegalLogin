<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use LegalLogin\PolicyField;
use LegalLogin\PolicyLinksAuthenticationRequest;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \LegalLogin\PolicyLinksAuthenticationRequest
 * @group Database
 */
class PolicyLinksAuthenticationRequestTest extends MediaWikiIntegrationTestCase {

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

	public function testLoadFromSubmissionReturnsFalseWhenNoLegalLoginPolicyLinks(): void {
		$req = new PolicyLinksAuthenticationRequest();
		$this->assertFalse( $req->loadFromSubmission( [] ) );
		$this->assertFalse( $req->loadFromSubmission( [ 'username' => 'x' ] ) );
	}

	public function testLoadFromSubmissionReturnsTrueAndStoresData(): void {
		$req = new PolicyLinksAuthenticationRequest();
		$prefix = PolicyField::POLICY_FIELD_NAME_PREFIX . 'LegalLoginTestPolicy';
		$data = [
			'LegalLoginPolicyLinks' => [
				$prefix . '-policyId' => '0',
				$prefix . '-policyRevId' => '1',
				$prefix . '-opened' => 'true',
			],
		];
		$this->assertTrue( $req->loadFromSubmission( $data ) );
	}

	public function testGetFieldInfoReturnsPolicyAndQuestionTypes(): void {
		$req = new PolicyLinksAuthenticationRequest();
		$info = $req->getFieldInfo();
		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'LegalLoginTestPolicy', $info );
		$this->assertSame( 'checkbox', $info['LegalLoginTestPolicy']['type'] );
	}

	public function testAddLogEntryCallsPolicyDataOnUserLogin(): void {
		$user = $this->getTestUser()->getUser();
		$page = $this->getExistingTestPage( 'Mediawiki:LegalLoginTestPolicy' );
		$revId = $page->getRevisionRecord()->getId();
		$req = new PolicyLinksAuthenticationRequest();
		$prefix = PolicyField::POLICY_FIELD_NAME_PREFIX . 'LegalLoginTestPolicy';
		$req->loadFromSubmission( [
			'LegalLoginPolicyLinks' => [
				$prefix . '-policyId' => '0',
				$prefix . '-policyRevId' => (string)$revId,
				$prefix . '-opened' => 'true',
			],
		] );
		$req->addLogEntry( $user );
		$this->assertTrue( true, 'addLogEntry completed without exception' );
	}
}
