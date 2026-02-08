<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use LegalLogin\PolicyField;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \LegalLogin\PolicyField
 * @group Database
 */
class PolicyFieldTest extends MediaWikiIntegrationTestCase {

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

	public function testGetPolicyFieldName(): void {
		$name = PolicyField::getPolicyFieldName( 'LegalLoginTestPolicy' );
		$this->assertStringStartsWith( PolicyField::POLICY_FIELD_NAME_PREFIX, $name );
		$this->assertStringContainsString( 'LegalLoginTestPolicy', $name );
	}

	public function testGetQuestionFieldName(): void {
		$name = PolicyField::getQuestionFieldName( 'SomeQuestion' );
		$this->assertStringStartsWith( PolicyField::QUESTION_FIELD_NAME_PREFIX, $name );
	}
}
