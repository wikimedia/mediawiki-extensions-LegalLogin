<?php
namespace LegalLogin;

use MediaWiki\Auth\AuthenticationRequest;
use MWException;
use StatusValue;
use User;

class ExtraFieldsAuthenticationRequest extends AuthenticationRequest {
	/**
	 * @var array|null
	 */
	private $legalLoginData;

	/**
	 * @var array
	 */
	private $logEntryParameters = [];

	/**
	 * @inheritDoc
	 */
	public function loadFromSubmission( array $data ) {
		$fieldInfo = $this->getFieldInfo();
		if ( !$fieldInfo ) {
			return false;
		}
		if ( !isset( $data['LegalLoginField'] ) ) {
			return false;
		}
		$this->legalLoginData = $data['LegalLoginField'];
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldInfo() {
		return PolicyData::getFormFieldInfo();
	}

	/**
	 * Checks that submitted data are actual
	 * @return StatusValue
	 * @throws MWException
	 */
	public function testSubmittedData() {
		$this->logEntryParameters = [];
		return PolicyData::testFormSubmittedData(
			$this->legalLoginData,
			$this->logEntryParameters
		);
	}

	/**
	 * Saves information about accepted policies
	 * @param User $user
	 * @throws MWException
	 */
	public function addLogEntry( User $user ) {
		PolicyData::saveAcceptedPolicies( $user, $this->logEntryParameters );
	}
}
