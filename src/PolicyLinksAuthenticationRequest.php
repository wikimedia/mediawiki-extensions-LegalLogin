<?php
namespace LegalLogin;

use MediaWiki\Auth\AuthenticationRequest;
use User;

class PolicyLinksAuthenticationRequest extends AuthenticationRequest {
	/**
	 * @var array
	 */
	private $legalLoginData = [];

	/**
	 * @inheritDoc
	 */
	public function loadFromSubmission( array $data ) {
		$fieldInfo = $this->getFieldInfo();
		if ( !$fieldInfo ) {
			return false;
		}
		if ( !isset( $data['LegalLoginPolicyLinks'] ) ) {
			return false;
		}
		$this->legalLoginData = $data['LegalLoginPolicyLinks'];
		return true;
	}

	/**
	 * @return array[]
	 */
	public function getFieldInfo() {
		return [
			'LegalLoginPolicyLinks' => [
				'type' => 'null',
				'class' => PolicyLinks::class,
				'fieldsData' => PolicyData::getFormFieldsData(),
			]
		];
	}

	/**
	 * Saves information about opened policies
	 * @param User $user
	 */
	public function addLogEntry( User $user ) {
		$policies = [];
		$prefix = preg_quote( PolicyField::POLICY_FIELD_NAME_PREFIX );
		foreach ( $this->legalLoginData as $key => $value ) {
			if ( preg_match( "/$prefix(.*?)-(policyId|policyRevId|opened)/", $key, $matches ) ) {
				$name = $matches[1];
				$k = $matches[2];
				if ( $k === 'opened' ) {
					// Convert to boolean value
					$value = $value === 'true';
				}
				$policies[$name][$k] = $value;
			}
		}
		PolicyData::onUserLogin( $user, $policies );
	}
}
