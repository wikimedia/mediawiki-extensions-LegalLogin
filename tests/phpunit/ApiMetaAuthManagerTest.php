<?php

namespace MediaWiki\Extension\LegalLogin\Tests;

use ApiTestCase;
use LegalLogin\ExtraFieldsSecondaryAuthenticationProvider;
use MediaWiki\MainConfigNames;
use User;

/**
 * @covers \LegalLogin\PolicyLinksAuthenticationRequest::getFieldInfo
 * @group Database
 *
 * This covers a side-effect of installing this extension, that the meta properties
 * of for auth manager actually displays.  This is because we install a new authentication
 * provider and the fields need to be available to the API.
 * See T355845
 */
class ApiMetaAuthManagerTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->editPage( 'Mediawiki:LegalLoginTestPolicy', 'Test policy contents' );

		// Override TestSetup::applyInitialConfig()
		$authConfig = $this->getConfVar( MainConfigNames::AuthManagerConfig );
		$authConfig['secondaryauth'][] = [
			'class' => ExtraFieldsSecondaryAuthenticationProvider::class,
			'services' => [ 'MainConfig' ],
			'sort' => 10,
		];
		$this->overrideConfigValue(
			MainConfigNames::AuthManagerConfig,
			$authConfig
		);
		$this->overrideConfigValue(
			'LegalLoginPolicies',
			[
				'LegalLoginTestPolicy' => [
					'captionmsg' => 'privacy-policy-caption'
				]
			]
		);
		$this->overrideConfigValue(
			'LegalLoginQuestions',
			[]
		);

		// Use qqx to simplify testing messages
		$this->setContentLang( 'qqx' );
		$this->setUserLang( 'qqx' );
	}

	public function testAuthManagerApi(): void {
		$anonUser = new User();

		[ $result, $requestContext, $session ] = $this->doApiRequest( [
			'action'     => 'query',
			'meta'       => 'authmanagerinfo|userinfo',
			'amirequestsfor' => 'login',
			'uselang' => 'qqx'
		], null, false, $anonUser );

		$this->assertTrue( isset( $result['query']['authmanagerinfo']['requests'] ) );
		$LLRequestMeta = false;
		foreach ( $result['query']['authmanagerinfo']['requests'] as $req ) {
			if ( $req['id'] === 'LegalLogin\\PolicyLinksAuthenticationRequest' ) {
				$LLRequestMeta = $req;
				break;
			}
		}
		$this->assertIsArray( $LLRequestMeta, "found a LegalLogin request block in the API" );
		$this->assertSame(
			[
			  'id' => 'LegalLogin\\PolicyLinksAuthenticationRequest',
			  'metadata' => [],
			  'required' => 'required',
			  'provider' => 'LegalLogin\\PolicyLinksAuthenticationRequest',
			  'account' => 'LegalLogin\\PolicyLinksAuthenticationRequest',
			  'fields' => [
				'LegalLoginTestPolicy' => [
				  'type' => 'checkbox',
				  'label' => '(legallogin-policy-apirequest-label: (privacy-policy-caption))',
				  'help' => '(legallogin-policy-apirequest-help)',
				  'optional' => false,
				  'sensitive' => false,
				],
			  ],
			],
			$LLRequestMeta,
			"Expected API result ok"
		);
	}

}
