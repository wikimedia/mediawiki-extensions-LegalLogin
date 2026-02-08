<?php
namespace LegalLogin;

use HTMLFormField;
use MediaWiki\Html\Html;

class PolicyLinks extends HTMLFormField {

	/**
	 * @param array $value
	 * @return array
	 */
	private function getFields( array $value ) {
		$fieldsData = $this->mParams['fieldsData'] ?? [];
		$policyFields = [];
		foreach ( $fieldsData as $data ) {
			if ( $data['type'] === 'policy' ) {
				$policyFieldName = PolicyField::getPolicyFieldName( $data['name'] );
				$params = [
					'name' => $policyFieldName,
					'fieldname' => $policyFieldName,
					'parent' => $this->mParent
				];
				if ( $this->mName !== null && $this->mName !== '' ) {
					$params['namePrefix'] = $this->mName;
				}
				$pfElement = new HTMLPolicyLinkField( $params );
				$name = $pfElement->mName;
				$postedPolicyRevId = $value["$name-policyRevId"] ?? null;
				$pfValue = $data;
				if ( $postedPolicyRevId === $data['policyRevId'] ) {
					$pfValue['opened'] = $value["$name-opened"] ?? false;
				}
				$policyFields[] = [ $pfElement, $pfValue ];
			}
		}
		return $policyFields;
	}

	/**
	 * @inheritDoc
	 */
	public function getInputHTML( $value ) {
		$fieldsHtml = '';
		$policyFields = $this->getFields( $value );
		foreach ( $policyFields as $pf ) {
			/** @var HTMLPolicyLinkField $pfElement */
			[ $pfElement, $pfValue ] = $pf;
			$fieldsHtml .= $pfElement->getInputHTML( $pfValue );
		}

		if ( !$fieldsHtml ) {
			return '';
		}

		$output = $this->mParent->getOutput();
		$output->addModules( 'ext.LegalLogin.policyLinks' );
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
		if ( $this->mName !== null && $this->mName !== '' ) {
			return $request->getArray( $this->mName ) ?? [];
		}
		$return = [];
		foreach ( $request->getValueNames() as $name ) {
			if ( strncmp( 'wpLegalLoginField-', $name, 18 ) !== 0 ) {
				continue;
			}
			$return[$name] = $request->getVal( $name );
		}
		return $return;
	}
}
