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
		$fieldsData = PolicyData::getFormFieldsData();
		$fieldInfo = [];
		foreach ( $fieldsData as $field ) {
			if ( $field['type'] === 'policy' ) {
				$fieldInfo[ $field['name'] ] = [
					'type' => 'checkbox',
					'label' => wfMessage( 'legallogin-policy-apirequest-label', $field['caption'] ),
					'required' => AuthenticationRequest::REQUIRED,
					'help' => wfMessage( 'legallogin-policy-apirequest-help' ),
				];
			} elseif ( $field['type'] === 'question' ) {
				$fieldInfo[ $field['name'] ] = [
					'type' => 'string',
					'label' => wfMessage( 'legallogin-question-apirequest-label', $field['text'] ),
					'required' => AuthenticationRequest::REQUIRED,
					'help' => wfMessage( 'legallogin-policy-apirequest-help' ),
				];
			}
		}
		return $fieldInfo;
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
