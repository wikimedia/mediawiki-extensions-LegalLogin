<?php

namespace MediaWiki\Extension\LegalLogin\Tests\Integration;

use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use LegalLogin\PolicyData;
use LegalLogin\PolicyLinks;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \LegalLogin\PolicyLinks
 * @group Database
 */
class PolicyLinksTest extends MediaWikiIntegrationTestCase {

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

	public function testGetInputHTMLReturnsMarkupWithNamePrefix(): void {
		$fieldsData = PolicyData::getFormFieldsData();
		$descriptor = [
			'class' => PolicyLinks::class,
			'fieldsData' => $fieldsData,
			'name' => 'LegalLoginPolicyLinks',
		];
		$context = new RequestContext();
		$context->setUser( $this->getTestUser()->getUser() );
		$form = HTMLForm::factory( 'codex', [ 'LegalLoginPolicyLinks' => $descriptor ], $context );
		$form->setTitle( \MediaWiki\Title\Title::newFromText( 'Special:UserLogin' ) );
		$form->prepareForm();

		$field = $form->getField( 'LegalLoginPolicyLinks' );
		$this->assertInstanceOf( PolicyLinks::class, $field );
		$html = $field->getInputHTML( [] );
		$this->assertStringContainsString( 'legal-login-field', $html );
		$this->assertStringContainsString( 'LegalLoginPolicyLinks[', $html );
	}

	public function testLoadDataFromRequestWithNamePrefixReturnsArray(): void {
		$fieldsData = PolicyData::getFormFieldsData();
		$descriptor = [
			'class' => PolicyLinks::class,
			'fieldsData' => $fieldsData,
			'name' => 'LegalLoginPolicyLinks',
		];
		$context = new RequestContext();
		$request = $context->getRequest();
		$request->setVal( 'LegalLoginPolicyLinks', [] );
		$form = HTMLForm::factory( 'codex', [ 'LegalLoginPolicyLinks' => $descriptor ], $context );
		$field = $form->getField( 'LegalLoginPolicyLinks' );
		$data = $field->loadDataFromRequest( $request );
		$this->assertIsArray( $data );
	}
}
