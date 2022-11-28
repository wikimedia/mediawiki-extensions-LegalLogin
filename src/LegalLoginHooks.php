<?php

use LegalLogin\ExtraFieldsAuthenticationRequest;
use LegalLogin\PolicyData;
use LegalLogin\PolicyLinksAuthenticationRequest;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

class LegalLoginHooks {

	/**
	 * Allows last minute changes to the output page
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @throws MWException
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$user = $out->getUser();
		// TODO Probably anonymous users should not have access to read already
		if ( $user->isRegistered() ) {

			// Bypass the check for groups allowed to bypass
			if ( MediaWikiServices::getInstance()
				->getPermissionManager()
				->userHasRight( $user, 'legallogin-bypass' )
			) {
				// Bypass
				return;
			}

			if ( !PolicyData::hasUserAcceptedPolicies( $user ) ) {
				$title = $out->getTitle();
				if ( !$title || PolicyData::isWhitelisted( $title, $user ) ) {
					return;
				}

				// Redirect user to Special:LegalLogin
				$returntoquery = array_diff_key(
					$out->getRequest()->getValues(),
					[ 'title' => true, 'returnto' => true, 'returntoquery' => true ]
				);
				$query = [ 'returntoquery' => wfArrayToCgi( $returntoquery ) ];
				$query['returnto'] = $title->getPrefixedDBkey();
				$url = SpecialPage::getTitleFor( 'LegalLogin' )->getFullURL( $query );
				$out->redirect( $url );
				$out->clearHTML();
				$out->addHTML( wfMessage( 'legallogin-policies-must-be-accepted' )->escaped() );
				$out->output();
			}
		}
	}

	/**
	 * Occurs before ApiMain's execute is called to process the request.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ApiBeforeMain
	 * @param ApiMain $main
	 * @throws MWException
	 */
	public static function onApiBeforeMain( ApiMain $main ) {
		$user = $main->getUser();
		$request = $main->getRequest();
		if (
			// Always allow logout action
			$request->getVal( 'action' ) === 'logout'
			// Always allow login action
			|| $request->getVal( 'action' ) === 'login'
		) {
			// Bypass
			return;
		}

		// Always allow token action
		if ( $request->getVal( 'action' ) === 'query'
			&& $request->getVal( 'meta' ) === 'tokens'
		) {
			// Make sure the meta=token parameter was not added to bypass another action=query request
			// Allow parameters from the main API module
			$allowedApiParams = $main->getFinalParams();
			// Allow parameters from the query module
			$allowedApiParams += $main->getModuleFromPath( 'query' )->getFinalParams();
			// Allow parameters from the query+tokens module
			$allowedApiParams += $main->getModuleFromPath( 'query+tokens' )->getFinalParams();
			if ( !array_diff_key( $request->getValues(), $allowedApiParams ) ) {
				// Bypass, there is no additional parameters
				return;
			}
		}

		// TODO Probably anonymous users should not have access to read already
		if ( $user->isRegistered() ) {

			// Bypass the check for groups allowed to bypass
			if ( MediaWikiServices::getInstance()
				->getPermissionManager()
				->userHasRight( $user, 'legallogin-bypass' )
			) {
				// Bypass
				return;
			}

			if ( !PolicyData::hasUserAcceptedPolicies( $user ) ) {
				$title = $main->getTitle();
				if ( !$title || PolicyData::isWhitelisted( $title, $user ) ) {
					// Bypass
					return;
				}

				throw new MWException( wfMessage( 'legallogin-policies-must-be-accepted' )->text() );
			}
		}
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, string $action
	) {
		// Do nothing in case if policies and questions are not defined
		$policies = PolicyData::getConfigVariable( 'LegalLoginPolicies' );
		$questions = PolicyData::getConfigVariable( 'LegalLoginQuestions' );
		if ( !$policies && !$questions ) {
			return;
		}

		if ( $action === AuthManager::ACTION_LOGIN ) {
			$req = AuthenticationRequest::getRequestByClass( $requests, PolicyLinksAuthenticationRequest::class );
			if ( $req ) {
				$formDescriptor['LegalLoginPolicyLinks'] = $fieldInfo['LegalLoginPolicyLinks'];
			}
			return;
		}

		if ( $action !== AuthManager::ACTION_CREATE ) {
			return;
		}
		// AuthManager::ACTION_CREATE
		$req = AuthenticationRequest::getRequestByClass( $requests, ExtraFieldsAuthenticationRequest::class );
		if ( !$req ) {
			// It can be created by another user without LegalLogin fields
			return;
		}

		$formDescriptor['createaccount']['disabled'] = true;
		// @phan-suppress-next-next-line PhanTypeInvalidDimOffset
		// Invalid offset "default" of $formDescriptor['createaccount'] of array type array{disabled:true}
		$submitButtonText = $formDescriptor['createaccount']['default'];
		$formDescriptor['createaccount']['default'] = wfMessage( 'legallogin-complete-checkboxes' )->text();
		$formDescriptor['LegalLoginField'] = $fieldInfo['LegalLoginField'];
		$formDescriptor['LegalLoginSubmitButtonText'] = [
			'type' => 'hidden',
			'default' => $submitButtonText,
		];
	}

	/**
	 * Check if policy was updated
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage, UserIdentity $user, string $summary, int $flags,
		RevisionRecord $revisionRecord, EditResult $editResult
	) {
		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() === NS_MEDIAWIKI ) {
			PolicyData::resetCurrentPoliciesCache();
		}
	}

	/**
	 * This is attached to the MediaWiki 'LoadExtensionSchemaUpdates' hook.
	 * Fired when MediaWiki is updated to allow extensions to update the database
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'legallogin_accepted', __DIR__ . '/../db_patches/accepted.sql' );
		$updater->addExtensionTable( 'legallogin_logged', __DIR__ . '/../db_patches/logged.sql' );
	}
}
