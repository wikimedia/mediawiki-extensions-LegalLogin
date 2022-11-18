<?php
namespace LegalLogin;

use Html;
use HTMLFormField;

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
				];
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
			list( $pfElement, $pfValue ) = $pf;
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
