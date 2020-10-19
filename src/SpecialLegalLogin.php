<?php

use LegalLogin\PolicyData;

class SpecialLegalLogin extends FormSpecialPage {
	/**
	 * @var array|null
	 */
	private $logEntryParameters;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		global $wgUseMediaWikiUIEverywhere;
		parent::__construct( 'LegalLogin' );

		// Override UseMediaWikiEverywhere to true, to force the form to use mw ui
		$wgUseMediaWikiUIEverywhere = true;
	}

	/**
	 * @inheritDoc
	 */
	public function isListed() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		$return = PolicyData::getFormFieldInfo();
		// Add submit button
		$return['LegalLoginSubmit'] = [
			'type' => 'submit',
			'default' => $this->msg( 'legallogin-complete-checkboxes' )->text(),
			'name' => 'wpLegalLoginSubmit',
			'id' => 'wpLegalLoginSubmit',
			'weight' => 100,
		];
		$return['LegalLoginSubmitButtonText'] = [
			'type' => 'hidden',
			'default' => $this->msg( 'legallogin-submit' ),
		];
		return $return;
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->suppressDefaultSubmit();
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'vform';
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->logEntryParameters = [];
		$status = PolicyData::testFormSubmittedData(
			$data['LegalLoginField'] ?? null,
			$this->logEntryParameters
		);
		return Status::wrap( $status );
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		PolicyData::saveAcceptedPolicies( $this->getUser(), $this->logEntryParameters );
		$returnUrl = $this->getReturnUrl();
		$out = $this->getOutput();
		$out->clearHTML();
		$out->redirect( $returnUrl );
	}

	/**
	 * @return string|null
	 */
	private function getReturnUrl() {
		$request = $this->getRequest();
		$redirectParams = wfCgiToArray( $request->getText( 'redirectparams', '' ) );
		$returnTo = $redirectParams['returnto'] ?? null;
		$returnToQuery = $redirectParams['returntoquery'] ?? '';
		$title = Title::newFromText( $returnTo );
		if ( !$title ) {
			$title = Title::newMainPage();
		}
		return $title->getFullUrlForRedirect( $returnToQuery );
	}
}
