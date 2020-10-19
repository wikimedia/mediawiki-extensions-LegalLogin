<?php
namespace LegalLogin;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use StatusValue;

class ExtraFieldsSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {
	/**
	 * @var ExtraFieldsAuthenticationRequest|null
	 */
	private $extraFieldsAuthRequest;

	/**
	 * @var PolicyLinksAuthenticationRequest|null
	 */
	private $policyLinksAuthRequest;

	/**
	 * @inheritDoc
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		if ( $action === AuthManager::ACTION_LOGIN ) {
			return [ new PolicyLinksAuthenticationRequest() ];
		}

		// Skip LegalLogin fields when user creates account for somebody else
		if ( $action === AuthManager::ACTION_CREATE && empty( $options['username'] ) ) {
			return [ new ExtraFieldsAuthenticationRequest() ];
		}
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		/** @var ExtraFieldsAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass( $reqs, ExtraFieldsAuthenticationRequest::class );
		if ( !$req ) {
			// It can be created by another user without LegalLogin fields
			return StatusValue::newGood();
		}

		$return = $req->testSubmittedData();
		if ( $return->isOK() ) {
			$this->extraFieldsAuthRequest = $req;
		}
		return $return;
	}

	/**
	 * @inheritDoc
	 */
	public function postAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		parent::postAccountCreation( $user, $creator, $response );
		if ( $response->status === AuthenticationResponse::PASS && $this->extraFieldsAuthRequest ) {
			$this->extraFieldsAuthRequest->addLogEntry( $user );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function postAuthentication( $user, AuthenticationResponse $response ) {
		parent::postAuthentication( $user, $response );
		if ( $response->status === AuthenticationResponse::PASS && $this->policyLinksAuthRequest ) {
			$this->policyLinksAuthRequest->addLogEntry( $user );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ) {
		/** @var PolicyLinksAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass( $reqs, PolicyLinksAuthenticationRequest::class );
		$this->policyLinksAuthRequest = $req;
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * @inheritDoc
	 */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
