<?php
namespace LegalLogin;

use HTMLCheckField;
use HTMLFormField;
use MediaWiki\Html\Html;

class PolicyField extends HTMLFormField {
	public const POLICY_FIELD_NAME_PREFIX = 'wpLegalLoginField-policy-';
	public const QUESTION_FIELD_NAME_PREFIX = 'wpLegalLoginField-question-';

	/**
	 * @param array $value
	 * @return array
	 */
	private function getFields( array $value ) {
		$fieldsData = $this->mParams['fieldsData'] ?? [];
		$policyFields = [];
		$policyCheckboxes = [];
		$questionFields = [];
		foreach ( $fieldsData as $data ) {
			if ( $data['type'] === 'policy' ) {
				// Policy
				$policyFieldName = self::getPolicyFieldName( $data['name'] );
				$textAreaParams = [
					'readonly' => true,
					'name' => $policyFieldName,
					'fieldname' => $policyFieldName,
					'parent' => $this->mParent,
				];
				$checkboxValue = false;
				$checkboxParams = [
					'label' => $data['checkboxLabel'],
					'disabled' => $data['requireScrolling'],
					'name' => $policyFieldName . '-checkbox',
					'fieldname' => $policyFieldName . '-checkbox',
					'parent' => $this->mParent
				];
				$pfElement = new HTMLPolicyTextField( $textAreaParams );
				$name = $pfElement->mName;
				$postedPolicyRevId = $value["$name-policyRevId"] ?? null;
				$pfValue = $data;
				if ( $postedPolicyRevId === $data['policyRevId'] ) {
					$pfValue['opened'] = $value["$name-opened"] ?? false;
					$pfValue['scrolled'] = $value["$name-scrolled"] ?? false;
					if ( $pfValue['opened'] || $pfValue['scrolled'] || !$checkboxParams['disabled'] ) {
						$checkboxValue = $value["$name-checkbox"] ?? false;
						$checkboxParams['disabled'] = false;
					}
				}
				$policyFields[] = [ $pfElement, $pfValue ];
				$policyCheckboxes[] = [ new HTMLCheckField( $checkboxParams ), $checkboxValue ];
			} else {
				// Question
				$questionParams = [
					'label' => $data['text'],
					'revId' => $data['questionRevId'],
					'name' => self::getQuestionFieldName( $data['name'] ),
					'fieldname' => self::getQuestionFieldName( $data['name'] ),
					'help' => $data['help'] ?? null,
					'options-messages' => [
						'legallogin-true-answer-label' => 'true',
						'legallogin-false-answer-label' => 'false',
					],
					'correctAnswer' => $data['answer'] ? 'true' : 'false',
					'parent' => $this->mParent
				];
				$questionElement = new HTMLPolicyQuestionField( $questionParams );
				$questionValue = $value ? ( $value[$questionElement->mName] ?? null ) : null;
				$questionFields[] = [ $questionElement, $questionValue ];
			}
		}
		return [ $policyFields, $policyCheckboxes, $questionFields ];
	}

	/**
	 * @inheritDoc
	 */
	public function getInputHTML( $value ) {
		$fieldsHtml = '';
		[ $policyFields, $policyCheckboxes, $questionFields ] = $this->getFields( $value );
		foreach ( $policyFields as $pf ) {
			/** @var HTMLPolicyTextField $pfElement */
			[ $pfElement, $pfValue ] = $pf;
			$fieldsHtml .= $pfElement->getInputHTML( $pfValue );
		}
		foreach ( $policyCheckboxes as $ch ) {
			/** @var HTMLCheckField $checkbox */
			[ $checkbox, $checkboxValue ] = $ch;
			$fieldsHtml .= Html::rawElement(
				'div',
				[ 'class' => 'legal-login-checkbox-row' ],
				$checkbox->getInputHTML( $checkboxValue )
			);
		}
		foreach ( $questionFields as $qf ) {
			/** @var HTMLPolicyQuestionField $checkbox */
			[ $qfElement, $qfValue ] = $qf;
			$fieldsHtml .= $qfElement->getInputHTML( $qfValue );
		}

		if ( !$fieldsHtml ) {
			return '';
		}
		$fieldsHtml .= Html::element(
			'label',
			[ 'id' => 'wpLegalLoginFieldEnableJS' ],
			$this->msg( 'legallogin-js-should-be-enabled' )->text()
		);

		$output = $this->mParent->getOutput();
		$output->addModules( 'ext.LegalLogin.policyField' );
		return Html::rawElement( 'div', [ 'class' => 'legal-login-field' ], $fieldsHtml );
	}

	/**
	 * @inheritDoc
	 */
	public function getInputOOUI( $value ) {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function loadDataFromRequest( $request ) {
		$return = [];
		foreach ( $request->getValueNames() as $name ) {
			if ( strncmp( 'wpLegalLoginField-', $name, 18 ) !== 0 ) {
				continue;
			}
			$return[$name] = $request->getVal( $name );
		}
		return $return;
	}

	/**
	 * Returns field name for policy fields
	 * @param string $name
	 * @return string
	 */
	public static function getPolicyFieldName( string $name ) {
		return self::POLICY_FIELD_NAME_PREFIX . wfMessage( $name )->inContentLanguage()->getTitle()->getDBkey();
	}

	/**
	 * Returns field name for question fields
	 * @param string $name
	 * @return string
	 */
	public static function getQuestionFieldName( string $name ) {
		return self::QUESTION_FIELD_NAME_PREFIX . wfMessage( $name )->inContentLanguage()->getTitle()->getDBkey();
	}
}
