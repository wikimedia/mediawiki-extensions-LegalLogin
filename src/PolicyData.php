<?php
namespace LegalLogin;

use BagOStuff;
use GitInfo;
use ManualLogEntry;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MWDebug;
use MWException;
use MWTimestamp;
use ObjectCache;
use SpecialPage;
use StatusValue;
use Title;
use User;
use WikiPage;
use WikitextContent;

class PolicyData {
	/**
	 * @param User $user
	 * @return bool
	 * @throws MWException
	 */
	public static function hasUserAcceptedPolicies( User $user ) {
		$policies = self::getCurrentPolicies();
		if ( !$policies ) {
			// No policy required
			return true;
		}

		$accepted = self::getAcceptedCache( $user->getId() );
		if ( $accepted === null ) {
			$loadedFromCache = false;
			$accepted = self::loadAcceptedPolicies( $user, array_keys( $policies ) );
		} else {
			$loadedFromCache = true;
		}
		if ( self::checkAllPoliciesWereAccepted( $policies, $accepted ) ) {
			return true;
		} elseif ( $loadedFromCache ) {
			// Maybe cache outdated
			$accepted = self::loadAcceptedPolicies( $user, array_keys( $policies ) );
			self::setAcceptedCache( $user->getId(), $accepted );
			return self::checkAllPoliciesWereAccepted( $policies, $accepted );
		}
		return false;
	}

	/**
	 * @param array $policies
	 * @param array $accepted
	 * @return bool
	 */
	public static function checkAllPoliciesWereAccepted( array $policies, array $accepted ): bool {
		$expiration = (int)self::getConfigVariable( 'LegalLoginExpiration' );
		$now = (int)MWTimestamp::now( TS_UNIX );
		foreach ( $policies as $name => $revId ) {
			$a = $accepted[$name] ?? null;
			if ( !$a ||
				$a[0] !== $revId ||
				$now - (int)MWTimestamp::convert( TS_UNIX, $a[1] ) >= $expiration
			) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param User $user
	 * @param array $policies
	 * @return array
	 */
	public static function loadAcceptedPolicies( User $user, array $policies ): array {
		$db = wfGetDB( DB_REPLICA );
		$res = $db->select(
			'legallogin_accepted',
			[ 'lla_name', 'lla_rev_id', 'lla_timestamp' ],
			[
				'lla_user' => $user->getId(),
				'lla_name' => $policies,
			],
			__METHOD__
		);
		$return = [];
		foreach ( $res as $row ) {
			$name = $row->lla_name;
			$return[$name] = [ $row->lla_rev_id, $row->lla_timestamp ];
		}
		return $return;
	}

	/**
	 * @param string|int ...$components Key components
	 * @return array
	 */
	private static function getCache( ...$components ) {
		$cache = ObjectCache::getLocalClusterInstance();
		$params = func_get_args();
		array_unshift( $params, 'LegalLogin' );
		$key = call_user_func_array( [ $cache, 'makeKey' ], $params );
		return [ $cache, $key ];
	}

	public static function resetCurrentPoliciesCache() {
		/** @var BagOStuff $cache */
		list( $cache, $key ) = self::getCache( 'policies' );
		$cache->delete( $key );
	}

	/**
	 * Returns current policies
	 * @return array
	 * @throws MWException
	 */
	public static function getCurrentPolicies(): array {
		$policies = self::getConfigVariable( 'LegalLoginPolicies' );
		$policiesMD5 = md5( serialize( $policies ) );
		/** @var BagOStuff $cache */
		list( $cache, $key ) = self::getCache( 'policies' );
		$return = $cache->get( $key );
		if ( $return !== false && ( $return['checksum'] ?? null ) === $policiesMD5 ) {
			unset( $return['checksum'] );
			return $return;
		}

		// Cache missed, load from database
		$return = self::loadCurrentPolicies( $policies );
		$cache->set( $key, $return + [ 'checksum' => $policiesMD5 ] );
		return $return;
	}

	/**
	 * Loads current policies from the database
	 * @param array $policies
	 * @return array
	 * @throws MWException
	 */
	private static function loadCurrentPolicies( array $policies ): array {
		$return = [];

		foreach ( $policies as $key => $value ) {
			$title = Title::makeTitle( NS_MEDIAWIKI, $key );
			if ( !$title->exists() ) {
				// TODO: wfMessage
				throw new MWException( "Title $key does not exists" );
			}
			$revId = $title->getLatestRevID();
			$dbKey = $title->getDBkey();
			$return[$dbKey] = (string)$revId;
		}
		return $return;
	}

	/**
	 * Save accepted policies to database
	 * @param User $user
	 * @param array $param
	 * @throws MWException
	 */
	public static function saveAcceptedPolicies( User $user, array $param ) {
		// Save accepted policies to database
		$userId = $user->getId();
		$db = wfGetDB( DB_PRIMARY );
		$index = [ 'lla_user' => $userId ];
		$set = [ 'lla_timestamp' => wfTimestampNow() ];
		$accepted = [];
		foreach ( $param['4::policies'] ?? [] as $policy ) {
			$name = $policy['name'];
			$revId = $policy['revId'];
			$index['lla_name'] = $name;
			$set['lla_rev_id'] = $revId;
			$db->upsert(
				'legallogin_accepted',
				[ $index + $set ],
				[ [ 'lla_user', 'lla_name' ] ],
				$set,
				__METHOD__
			);
			$accepted[$name] = $revId;
		}

		// Reset user logged in counter
		$loggedRow = $db->selectRow(
			'legallogin_logged',
			[ 'lll_count', 'lll_timestamp' ],
			[ 'lll_user' => $userId ],
			__METHOD__
		);
		if ( $loggedRow ) {
			$loggedCount = (int)$loggedRow->lll_count;
			$loggedTimestamp = $loggedRow->lll_timestamp;
			$db->update(
				'legallogin_logged',
				[ 'lll_count' => 0 ],
				[ 'lll_user' => $userId ],
				__METHOD__
			);
		} else {
			// We should never get here, just in case
			MWDebug::warning( 'No login record for user id ' . $userId . ' in legallogin_logged table.' );
			$loggedCount = 0;
			$loggedTimestamp = '<undefined>';
		}

		// Insert logentry
		$param['6::git'] = self::getGitInfo();
		$param['7:logged'] = [
			'count' => $loggedCount,
			'timestamp' => $loggedTimestamp,
		];
		if ( self::getConfigVariable( 'LegalLoginLogActions' ) ) {
			$logEntry = new ManualLogEntry( 'legallogin', 'accept' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $user->getUserPage() );
			$logEntry->setParameters( $param );
			$logId = $logEntry->insert();
			$logEntry->publish( $logId );
		}

		// Save debug information
		$csv = [];
		foreach ( $param['4::policies'] ?? [] as $value ) {
			$csv[] = "policy name: {$value['name']}";
			$csv[] = "id: {$value['policyId']}";
			$csv[] = "revId: {$value['revId']}";
			$csv[] = 'opened: ' . ( $value['opened'] ? 'Yes' : 'No' );
			$csv[] = 'scrolled: ' . ( $value['scrolled'] ? 'Yes' : 'No' );
			$csv[] = 'required: ' . ( $value['required'] ? 'Yes' : 'No' );
			$csv[] = 'accepted: ' . ( $value['accepted'] ? 'Yes' : 'No' );
		}
		foreach ( $param['5::questions'] ?? [] as $value ) {
			$csv[] = "question name: {$value['name']}";
			$csv[] = "revId: {$value['revId']}";
			$csv[] = 'answer: ' . ( $value['answer'] ? 'True' : 'False' );
		}
		$csv[] = "logged timestamp: {$param['7:logged']['timestamp']}";
		$csv[] = "logged count: {$param['7:logged']['count']}";
		$csv[] = "git: {$param['6::git']['sha1']}";
		$debugMsg = wfMessage(
			'legallogin-debug-acceptance',
			$userId,
			$user->getName(),
			implode( ';', $csv )
		);
		wfDebugLog(
			'LegalLogin',
			$debugMsg->text(),
			'private'
		);

		// Add to cache
		self::setAcceptedCache( $user->getId(), $accepted );
	}

	/**
	 * Save accepted policies to cache
	 * @param int $userId
	 * @param array $accepted
	 */
	private static function setAcceptedCache( int $userId, array $accepted ) {
		/** @var BagOStuff $cache */
		list( $cache, $key ) = self::getCache( 'accepted', $userId );
		$cache->set( $key, $accepted );
	}

	/**
	 * Get accepted policies from cache
	 * @param int $userId
	 * @return array|null
	 */
	private static function getAcceptedCache( int $userId ): ?array {
		/** @var BagOStuff $cache */
		list( $cache, $key ) = self::getCache( 'accepted', $userId );
		$return = $cache->get( $key );
		if ( $return === false ) {
			return null;
		}
		return $return;
	}

	/**
	 * Remove accepted policies cache
	 * @param int $userId
	 */
	private static function resetAcceptedCache( int $userId ) {
		/** @var BagOStuff $cache */
		list( $cache, $key ) = self::getCache( 'accepted', $userId );
		$cache->delete( $key );
	}

	/**
	 * @param string $name
	 * @param LinkTarget $page
	 * @return bool
	 */
	private static function isSameSpecialPage( string $name, LinkTarget $page ): bool {
		$specialPageFactory = MediaWikiServices::getInstance()->getSpecialPageFactory();
		if ( $page->getNamespace() == NS_SPECIAL ) {
			list( $thisName ) = $specialPageFactory->resolveAlias( $page->getDBkey() );
			if ( $name == $thisName ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if User can have access to Title without accepting the policies
	 * @param Title $title
	 * @param User $user
	 * @return bool
	 * @throws MWException
	 */
	public static function isWhitelisted( Title $title, User $user ) {
		$whiteListRead = self::getConfigVariable( 'WhitelistRead' );
		$whitelisted = false;
		if ( self::isSameSpecialPage( 'LegalLogin', $title ) ||
			self::isSameSpecialPage( 'Userlogin', $title )
			|| self::isSameSpecialPage( 'PasswordReset', $title )
			|| self::isSameSpecialPage( 'Userlogout', $title )
		) {
			# Always grant access to the special pages listed above.
			# Even anons need to be able to log in.
			$whitelisted = true;
		} elseif ( is_array( $whiteListRead ) && count( $whiteListRead ) ) {
			# Time to check the whitelist
			# Only do these checks is there's something to check against
			$name = $title->getPrefixedText();
			$dbName = $title->getPrefixedDBkey();

			// Check for explicit whitelisting with and without underscores
			if ( in_array( $name, $whiteListRead, true )
				|| in_array( $dbName, $whiteListRead, true ) ) {
				$whitelisted = true;
			} elseif ( $title->getNamespace() == NS_MAIN ) {
				# Old settings might have the title prefixed with
				# a colon for main-namespace pages
				if ( in_array( ':' . $name, $whiteListRead ) ) {
					$whitelisted = true;
				}
			} elseif ( $title->isSpecialPage() ) {
				# If it's a special page, ditch the subpage bit and check again
				$name = $title->getDBkey();
				$specialPageFactory = MediaWikiServices::getInstance()->getSpecialPageFactory();
				list( $name ) = $specialPageFactory->resolveAlias( $name );
				if ( $name ) {
					$pure = SpecialPage::getTitleFor( $name )->getPrefixedText();
					if ( in_array( $pure, $whiteListRead, true ) ) {
						$whitelisted = true;
					}
				}
			}
		}

		$whitelistReadRegexp = self::getConfigVariable( 'WhitelistReadRegexp' );
		if ( !$whitelisted && is_array( $whitelistReadRegexp )
			&& !empty( $whitelistReadRegexp ) ) {
			$name = $title->getPrefixedText();
			// Check for regex whitelisting
			foreach ( $whitelistReadRegexp as $listItem ) {
				if ( preg_match( $listItem, $name ) ) {
					$whitelisted = true;
					break;
				}
			}
		}

		if ( !$whitelisted ) {
			# If the title is not whitelisted, give extensions a chance to do so...
			$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
			$hookRunner = new HookRunner( $hookContainer );
			$hookRunner->onTitleReadWhitelist( $title, $user, $whitelisted );
		}
		return (bool)$whitelisted;
	}

	/**
	 * @return array[]
	 * @throws MWException
	 */
	public static function getFormFieldInfo() {
		return [
			'LegalLoginField' => [
				'type' => 'null',
				'class' => PolicyField::class,
				'fieldsData' => self::getFormFieldsData(),
			]
		];
	}

	/**
	 * @return array
	 * @throws MWException
	 */
	public static function getFormFieldsData() {
		$return = [];

		// Legal Login Policies
		$policies = self::getConfigVariable( 'LegalLoginPolicies' );
		foreach ( $policies as $key => $value ) {
			$title = Title::makeTitle( NS_MEDIAWIKI, $key );
			list( $policyId, $policyRevId, $policyText, $timestamp ) = self::getPageData( $title );

			$policyCaption = $key;
			if ( !empty( $value['captionmsg'] ) ) {
				$policyCaptionMsg = wfMessage( $value['captionmsg'] )->inContentLanguage();
				if ( $policyCaptionMsg->exists() ) {
					$policyCaption = $policyCaptionMsg->plain();
				} else {
					// TODO what to do?
				}
			}

			$checkboxLabel = null;
			if ( !empty( $value['checkboxmsg'] ) ) {
				$checkboxMsg = wfMessage( $value['checkboxmsg'] )->inContentLanguage();
				if ( $checkboxMsg->exists() ) {
					$checkboxLabel = $checkboxMsg->plain();
				} else {
					// TODO what to do?
				}
			}
			if ( !$checkboxLabel ) {
				$checkboxLabel = wfMessage( 'legallogin-policy-checkbox-label', $policyCaption )->text();
			}

			$return[] = [
				'type' => 'policy',
				'name' => $title->getDBkey(),
				'caption' => $policyCaption,
				'checkboxLabel' => $checkboxLabel,
				'text' => $policyText,
				'policyId' => (string)$policyId,
				'policyRevId' => (string)$policyRevId,
				'timestamp' => $timestamp,
				// TODO: should it be true by default?
				'requireScrolling' => $value['require scrolling'] ?? true,
			];
		}

		// Legal Login Test Questions
		$questions = self::getConfigVariable( 'LegalLoginQuestions' );
		foreach ( $questions as $key => $value ) {
			$title = Title::makeTitle( NS_MEDIAWIKI, $key );
			list( $questionId, $questionRevId, $questionText ) = self::getPageData( $title );
			$return[] = [
				'type' => 'question',
				'name' => $title->getDBkey(),
				'text' => $questionText,
				'questionId' => (string)$questionId,
				'questionRevId' => (string)$questionRevId,
				'answer' => $value['answer'] ?? null,
				'help' => $value['help'] ?? null,
			];
		}

		return $return;
	}

	/**
	 * @param Title $title
	 * @return array
	 * @throws MWException
	 */
	private static function getPageData( Title $title ) {
		if ( !$title->exists() ) {
			throw new MWException( 'Title ' . $title->getFullText() . ' does not exists' );
		}
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			$page = new WikiPage( $title );
		}
		$id = self::getPolicyId( $page->getId() );
		$revRecord = $page->getRevisionRecord();
		if ( !$revRecord ) {
			throw new MWException( 'Cannot get revision record for title ' . $title->getFullText() );
		}
		$revId = $revRecord->getId();
		$content = $revRecord->getContent( SlotRecord::MAIN );
		if ( $content instanceof WikitextContent ) {
			$text = $content->getText();
		} else {
			// TODO: what to do?
			$text = '';
		}
		$timestamp = $revRecord->getTimestamp();
		return [ $id, $revId, $text, $timestamp ];
	}

	/**
	 * Calculates policy ID based on number of saved revisions
	 * @param int $pageId
	 * @return int
	 */
	private static function getPolicyId( int $pageId ) {
		$db = wfGetDB( DB_PRIMARY );
		return $db->selectRowCount( 'revision', '*', [ 'rev_page' => $pageId ], __METHOD__ ) - 1;
	}

	/**
	 * Checks that submitted data are actual
	 * @param array|null $legalLoginData
	 * @param array &$logEntryParameters
	 * @return StatusValue
	 * @throws MWException
	 */
	public static function testFormSubmittedData( ?array $legalLoginData, array &$logEntryParameters ) {
		$logEntryParameters = [];
		$fieldsData = self::getFormFieldsData();
		if ( !$fieldsData ) {
			// No Legal Login extra fields required
			return StatusValue::newGood();
		}
		if ( !$legalLoginData ) {
			// No Legal Login extra fields provided
			return StatusValue::newFatal( 'legallogin-extra-fields-were-changed' );
		}

		foreach ( $fieldsData as $data ) {
			if ( $data['type'] === 'policy' ) {
				$fieldName = PolicyField::getPolicyFieldName( $data['name'] );
				$policyId = $legalLoginData["$fieldName-policyId"] ?? null;
				$policyRevId = $legalLoginData["$fieldName-policyRevId"] ?? null;
				$policyAccepted = $legalLoginData["$fieldName-checkbox"] ?? null;
				$policyText = $legalLoginData["$fieldName-text"] ?? '';
				if ( !$policyText ||
					preg_replace( '/[\n\r]+/m', ' ', $policyText ) !==
					preg_replace( '/[\n\r]+/m', ' ', $data['text'] ) ||
					$policyId !== $data['policyId'] ||
					$policyRevId !== $data['policyRevId']
				) {
					return StatusValue::newFatal( 'legallogin-extra-fields-were-changed' );
				}

				$scrolled = ( $legalLoginData["$fieldName-scrolled"] ?? null ) === 'true';
				$opened = ( $legalLoginData["$fieldName-opened"] ?? null ) === 'true';
				if ( $data['requireScrolling'] && !$scrolled && !$opened ) {
					return StatusValue::newFatal( 'legallogin-policy-must-be-read', $data['caption'] );
				}

				if ( $policyAccepted !== "1" ) {
					return StatusValue::newFatal( 'legallogin-policy-must-be-accepted', $data['caption'] );
				}
				$logEntryParameters['4::policies'][] = [
					'name' => $data['name'],
					'policyId' => $policyId,
					'revId' => $policyRevId,
					'required' => $data['requireScrolling'],
					'scrolled' => $scrolled,
					'opened' => $opened,
					'accepted' => $policyAccepted === '1',
				];
			} elseif ( $data['type'] === 'question' ) {
				$fieldName = PolicyField::getQuestionFieldName( $data['name'] );
				$revId = $legalLoginData["$fieldName-revId"] ?? null;
				if ( $revId !== $data['questionRevId'] ) {
					return StatusValue::newFatal( 'legallogin-extra-fields-were-changed' );
				}

				if ( $legalLoginData[$fieldName] !== ( $data['answer'] ? 'true' : 'false' ) ) {
					return StatusValue::newFatal( 'legallogin-incorrect-answer-on-question', $data['label'] );
				}
				$logEntryParameters['5::questions'][] = [
					'name' => $data['name'],
					'revId' => $revId,
					'answer' => $data['answer'],
				];
			} else {
				// We should never get there
				throw new MWException( 'Wrong field type: ' . $data['type'] );
			}
		}

		return StatusValue::newGood();
	}

	/**
	 * Get a configuration variable
	 * @param string $name
	 * @return mixed
	 */
	public static function getConfigVariable( string $name ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		return $config->get( $name );
	}

	/**
	 * Called when user logged in
	 * @param User $user
	 * @param array $policies
	 * @throws MWException
	 */
	public static function onUserLogin( User $user, array $policies ) {
		$userId = $user->getId();
		$db = wfGetDB( DB_PRIMARY );
		$loggedCount = (int)$db->selectField(
			'legallogin_logged',
			'lll_count',
			[ 'lll_user' => $userId ],
			__METHOD__
		);

		$loggedCount++;

		$timestamp = wfTimestampNow();
		$index = [ 'lll_user' => $userId ];
		$set = [
			'lll_count' => $loggedCount,
			'lll_timestamp' => $timestamp,
		];
		$db->upsert(
			'legallogin_logged',
			[ $index + $set ],
			[ [ 'lll_user' ] ],
			$set,
			__METHOD__
		);

		$legalLoginInterval = self::getConfigVariable( 'LegalLoginInterval' );
		if ( $legalLoginInterval > 0 && $legalLoginInterval <= $loggedCount ) {
			// Require acceptance of legal policies again because number of logins greater or equal LegalLoginInterval
			$db->delete(
				'legallogin_accepted',
				[ 'lla_user' => $userId ],
				__METHOD__
			);
			// Delete cached info
			self::resetAcceptedCache( $userId );
		}

		// Insert logentry
		$param = [];
		$param['4::policies'] = $policies;
		$param['5::count'] = $loggedCount;
		$param['6::timestamp'] = $timestamp;
		$param['7::requiredAcceptance'] = !self::hasUserAcceptedPolicies( $user );
		$param['8::git'] = self::getGitInfo();
		if ( self::getConfigVariable( 'LegalLoginLogActions' ) ) {
			$logEntry = new ManualLogEntry( 'legallogin', 'login' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $user->getUserPage() );
			$logEntry->setParameters( $param );
			$logId = $logEntry->insert();
			$logEntry->publish( $logId );
		}

		// Save debug information
		$csv = [];
		foreach ( $param['4::policies'] as $key => $value ) {
			$csv[] = "policy name: $key";
			$csv[] = "id: {$value['policyId']}";
			$csv[] = "revId: {$value['policyRevId']}";
			$csv[] = 'opened: ' . ( $value['opened'] ? 'Yes' : 'No' );
		}
		$csv[] = "count: {$param['5::count']}";
		$csv[] = "timestamp: {$param['6::timestamp']}";
		$csv[] = 'requiredAcceptance: ' . ( $param['7::requiredAcceptance'] ? 'Yes' : 'No' );
		$csv[] = "git: {$param['8::git']['sha1']}";
		$debugMsg = wfMessage(
			'legallogin-debug-login',
			$userId,
			$user->getName(),
			implode( ';', $csv )
		);
		wfDebugLog(
			'LegalLogin',
			$debugMsg->text(),
			'private'
		);
	}

	/**
	 * Returns information about the extension version
	 * @return array
	 */
	private static function getGitInfo() {
		$gitInfo = new GitInfo( __DIR__ . '/..' );
		return [
			'sha1' => $gitInfo->getHeadSHA1(),
			'url' => $gitInfo->getHeadViewUrl(),
		];
	}
}
